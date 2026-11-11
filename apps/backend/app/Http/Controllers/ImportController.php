<?php

namespace App\Http\Controllers;

use App\Services\Governance;
use App\Services\HistoricalImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportController extends Controller
{
    public function index(Request $request)
    {
        Governance::authorize($request, 'imports.read');

        return response()->json(DB::table('import_batches')->orderByDesc('created_at')->paginate(30));
    }

    public function store(Request $request, HistoricalImporter $importer)
    {
        Governance::authorize($request, 'imports.write');
        $data = $request->validate(['file' => 'required|file|max:10240', 'facility_id' => 'required|uuid|exists:facilities,id', 'year' => 'nullable|integer|min:2000|max:2100', 'flat_sheet' => ['nullable', 'string', 'max:255', 'not_regex:/[\x00-\x1F\x7F]/'], 'mapping_context' => 'sometimes|array|max:256', 'mapping_context.*.alias' => ['required', 'string', 'max:128', 'not_regex:/[\x00-\x1F\x7F]/'], 'mapping_context.*.source_scope' => ['required', 'string', 'max:255', 'not_regex:/[\x00-\x1F\x7F]/'], 'mapping_context.*.unit_code' => 'required|in:t', 'mapping_context.*.granularity' => 'required|in:monthly', 'mapping_context.*.product_stage' => 'required|in:unknown,not_applicable,intermediate_stream,final_product,byproduct,fuel,waste', 'mapping_context.*.locator' => 'required|array:sheet,cell,row,column|min:1|max:4', 'mapping_context.*.locator.row' => 'sometimes|integer|min:1|max:1048576', 'mapping_context.*.locator.column' => 'sometimes|string|max:128', 'mapping_context.*.locator.sheet' => 'sometimes|string|max:255', 'mapping_context.*.locator.cell' => 'sometimes|string|max:32']);

        return response()->json(['data' => $importer->stage($request, $request->file('file')->path(), $request->file('file')->getClientOriginalName(), $data['facility_id'], $data['year'] ?? null)], 201);
    }

    public function preview(Request $request, string $id)
    {
        Governance::authorize($request, 'imports.read');
        $batch = DB::table('import_batches')->find($id);
        abort_unless($batch, 404);
        $rows = DB::table('monthly_observations as o')->join('import_rows as r', 'r.id', '=', 'o.import_row_id')->where('r.batch_id', $id)->select('o.*', 'r.source_locator', 'r.qa_flags', 'r.raw_json')->orderBy('o.period_month')->paginate(100);

        return response()->json(['data' => ['batch' => $batch, 'observations' => $rows, 'reconciliation_cases' => DB::table('reconciliation_cases')->where('batch_id', $id)->get()]]);
    }

    public function resolve(Request $request, string $id)
    {
        Governance::authorize($request, 'imports.approve');
        $data = $request->validate(['resolution' => 'required|string|min:10|max:2000']);

        return DB::transaction(function () use ($request, $id, $data) {
            $case = DB::table('reconciliation_cases')->where('id', $id)->lockForUpdate()->first();
            abort_unless($case, 404);
            DB::table('reconciliation_cases')->where('id', $id)->update(['status' => 'reviewed', 'resolution' => $data['resolution'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            Governance::audit($request, 'reviewed', 'reconciliation_cases', $id, (array) $case, $data);

            return response()->json(['data' => ['id' => $id, 'status' => 'reviewed']]);
        });
    }

    public function approve(Request $request, string $id)
    {
        Governance::authorize($request, 'imports.approve');
        $data = $request->validate(['reason' => 'required|string|min:10|max:2000', 'lock_version' => 'required|integer|min:1', 'observation_ids' => 'nullable|array', 'observation_ids.*' => 'uuid']);

        return DB::transaction(function () use ($request, $id, $data) {
            $batch = DB::table('import_batches')->where('id', $id)->lockForUpdate()->first();
            abort_unless($batch, 404);
            abort_if($batch->created_by === $request->user()->id, 403, 'Independent reviewer required');
            abort_unless($batch->lock_version === $data['lock_version'], 409, 'Stale review');
            $query = DB::table('monthly_observations')->whereIn('import_row_id', DB::table('import_rows')->where('batch_id', $id)->select('id'))->whereIn('status', ['staged', 'flagged', 'reviewed']);
            if (! empty($data['observation_ids'])) {
                $query->whereIn('id', $data['observation_ids']);
            }DB::table('facilities')->where('id', $batch->facility_id)->lockForUpdate()->first();
            $observations = $query->lockForUpdate()->get();
            abort_if($observations->isEmpty(), 409, 'No pending observations');
            abort_if(DB::table('reconciliation_cases')->where('batch_id', $id)->whereNull('observation_id')->where('status', 'open')->exists(), 409, 'Resolve batch source conflicts first');
            abort_if($observations->contains(fn ($o) => $o->quantity === null), 422, 'Missing quantities cannot be approved');
            abort_if(DB::table('reconciliation_cases')->whereIn('observation_id', $observations->pluck('id'))->where('status', 'open')->exists(), 409, 'Resolve flagged source issues first');
            foreach ($observations as $observation) {
                abort_if($observation->created_by === $request->user()->id, 403, 'Revision author cannot approve own fact');
                $duplicate = DB::table('monthly_observations')->where('facility_id', $observation->facility_id)->where('period_month', $observation->period_month)->where('metric_code', $observation->metric_code)->where('item_code', $observation->item_code)->where('actual_or_target', $observation->actual_or_target)->where('report_status', $observation->report_status)->where('status', 'approved')->lockForUpdate()->first();
                if ($duplicate) {
                    abort_unless($observation->supersedes_id === $duplicate->id, 409, 'Approved fact already exists; explicit revision required');
                    DB::table('monthly_observations')->where('id', $duplicate->id)->update(['status' => 'superseded']);
                }
            } $query->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $request->user()->id]);
            DB::table('import_batches')->where('id', $id)->update(['status' => 'reviewed', 'lock_version' => $batch->lock_version + 1]);
            DB::table('approval_actions')->insert(['id' => (string) Str::uuid(), 'resource_type' => 'import_batches', 'resource_id' => $id, 'actor_id' => $request->user()->id, 'action' => 'approved', 'reason' => $data['reason'], 'created_at' => now()]);
            Governance::audit($request, 'approved', 'import_batches', $id, ['status' => $batch->status], ['approved_count' => $observations->count()], $data['reason']);

            return response()->json(['data' => ['approved_count' => $observations->count(), 'lock_version' => $batch->lock_version + 1]]);
        }, 3);
    }

    public function observations(Request $request)
    {
        Governance::authorize($request, 'production.read');
        $query = DB::table('monthly_observations');
        foreach (['period_month', 'item_code', 'metric_code', 'status'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->string($field)->toString());
            }
        }

        return response()->json($query->orderBy('period_month')->paginate(100));
    }

    public function revise(Request $request, string $id)
    {
        Governance::authorize($request, 'imports.write');
        $data = $request->validate(['quantity' => 'required|regex:/^\d{1,14}(\.\d{1,6})?$/', 'reason' => 'required|string|min:10|max:2000']);

        return DB::transaction(function () use ($request, $id, $data) {
            $old = DB::table('monthly_observations')->where('id', $id)->lockForUpdate()->first();
            abort_unless($old, 404);
            abort_unless($old->status === 'approved', 409, 'Only approved facts have correction revisions');
            $originalRow = DB::table('import_rows')->find($old->import_row_id);
            $rowId = (string) Str::uuid();
            $newId = (string) Str::uuid();
            DB::table('import_rows')->insert(['id' => $rowId, 'batch_id' => $originalRow->batch_id, 'source_locator' => $originalRow->source_locator, 'raw_json' => json_encode(['manual_revision_of' => $old->id, 'quantity' => $data['quantity'], 'reason' => $data['reason'], 'author_id' => $request->user()->id]), 'qa_flags' => '[]']);
            $revision = collect((array) $old)->except(['approved_scope_key'])->all();
            $revision = array_merge($revision, ['id' => $newId, 'quantity' => $data['quantity'], 'import_row_id' => $rowId, 'revision_no' => $old->revision_no + 1, 'status' => 'staged', 'approved_at' => null, 'approved_by' => null, 'available_at' => now(), 'created_by' => $request->user()->id, 'supersedes_id' => $old->id]);
            DB::table('monthly_observations')->insert($revision);
            Governance::audit($request, 'revision_staged', 'monthly_observations', $newId, ['previous_id' => $id], ['quantity' => $data['quantity'], 'revision_no' => $revision['revision_no']], $data['reason']);

            return response()->json(['data' => ['id' => $newId, 'status' => 'staged', 'supersedes_id' => $id]], 201);
        }, 3);
    }
}
