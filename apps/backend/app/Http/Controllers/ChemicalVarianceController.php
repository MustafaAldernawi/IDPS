<?php

namespace App\Http\Controllers;

use App\Services\Governance;
use App\Services\SourceMappingDictionary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChemicalVarianceController extends Controller
{
    private function requirePermission(Request $request, string $permission): void
    {
        abort_unless(DB::table('user_roles as ur')->join('role_permissions as rp', 'rp.role_id', '=', 'ur.role_id')->join('permissions as p', 'p.id', '=', 'rp.permission_id')->where('ur.user_id', $request->user()->id)->where('p.code', $permission)->exists(), 403);
    }

    private function authorizeScope(Request $request, string $permission, string $facility, ?string $unit): void
    {
        if ($unit !== null) {
            abort_unless(DB::table('refinery_units')->where('id', $unit)->where('facility_id', $facility)->exists(), 422, 'Unit does not belong to facility');
            Governance::authorize($request, $permission, $unit);
        } else {
            app(SourceMappingDictionary::class)->requireScope($request, $permission, $facility);
        }
    }

    public function index(Request $request)
    {
        $this->requirePermission($request, 'planning.read');
        return response()->json(['data' => OperationsController::scoped(DB::table('chemical_targets'), $request, 'planning.read')->orderByDesc('created_at')->limit(500)->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'facility_id' => 'required|uuid|exists:facilities,id', 'unit_id' => 'nullable|uuid|exists:refinery_units,id',
            'period_month' => 'required|date_format:Y-m', 'chemical_code' => 'required|string|max:64',
            'unit_code' => 'required|exists:measurement_units,code', 'quantity' => ['required', 'regex:/^\d{1,14}(\.\d{1,6})?$/'],
            'source_reference' => 'required|string|max:500', 'available_at' => 'required|date',
            'replaces_id' => 'nullable|uuid|exists:chemical_targets,id',
        ]);
        $this->authorizeScope($request, 'planning.write', $data['facility_id'], $data['unit_id'] ?? null);
        $data['id'] = (string) Str::uuid();
        $data['created_by'] = $request->user()->id;
        $data['available_at'] = \Carbon\Carbon::parse($data['available_at'])->utc();
        $data['created_at'] = $data['updated_at'] = now('UTC');
        DB::transaction(function () use ($data, $request): void {
            if (! empty($data['replaces_id'])) {
                $previous = DB::table('chemical_targets')->where('id', $data['replaces_id'])->lockForUpdate()->first();
                abort_unless($previous && $previous->status === 'approved', 422, 'Only an approved target can be revised');
                foreach (['facility_id', 'unit_id', 'period_month', 'chemical_code', 'unit_code'] as $field) {
                    abort_unless(($data[$field] ?? null) === $previous->$field, 422, 'Revision scope must match its predecessor');
                }
                $data['revision_no'] = $previous->revision_no + 1;
            }
            DB::table('chemical_targets')->insert($data);
            Governance::audit($request, 'chemical_target.created', 'chemical_target', $data['id'], null, $data);
        });
        return response()->json(['data' => DB::table('chemical_targets')->find($data['id'])], 201);
    }

    public function approve(Request $request, string $id)
    {
        $data = $request->validate(['reason' => 'required|string|max:2000']);
        return DB::transaction(function () use ($request, $id, $data) {
            $row = DB::table('chemical_targets')->where('id', $id)->lockForUpdate()->first();
            abort_unless($row, 404);
            $this->authorizeScope($request, 'planning.approve', $row->facility_id, $row->unit_id);
            abort_if($row->created_by === $request->user()->id, 403);
            abort_unless($row->status === 'draft', 409);
            $scope = hash('sha256', json_encode([$row->facility_id, $row->unit_id, $row->period_month, $row->chemical_code, $row->unit_code], JSON_THROW_ON_ERROR));
            if ($row->replaces_id) {
                $previous = DB::table('chemical_targets')->where('id', $row->replaces_id)->lockForUpdate()->first();
                abort_unless($previous && $previous->status === 'approved' && $previous->active_scope === $scope, 409, 'The predecessor is no longer active');
                DB::table('chemical_targets')->where('id', $previous->id)->update(['status' => 'superseded', 'active_scope' => null, 'updated_at' => now('UTC')]);
                Governance::audit($request, 'chemical_target.superseded', 'chemical_target', $previous->id, (array) $previous, ['status' => 'superseded', 'replacement_id' => $id], $data['reason']);
            }
            if (DB::table('chemical_targets')->where('active_scope', $scope)->exists()) {
                throw ValidationException::withMessages(['target' => 'An approved target already exists for this scope.']);
            }
            $changes = ['status' => 'approved', 'active_scope' => $scope, 'approved_by' => $request->user()->id, 'approved_at' => now('UTC'), 'updated_at' => now('UTC')];
            DB::table('chemical_targets')->where('id', $id)->update($changes);
            Governance::audit($request, 'chemical_target.approved', 'chemical_target', $id, (array) $row, $changes, $data['reason']);
            return response()->json(['data' => DB::table('chemical_targets')->find($id)]);
        });
    }

    public function report(Request $request)
    {
        $this->requirePermission($request, 'engineering.read');
        $data = $request->validate(['facility_id' => 'required|uuid|exists:facilities,id', 'period_month' => 'required|date_format:Y-m']);
        $targets = OperationsController::scoped(DB::table('chemical_targets'), $request, 'engineering.read')->where('facility_id', $data['facility_id'])->where('period_month', $data['period_month'])->where('status', 'approved')->get();
        $actuals = OperationsController::scoped(DB::table('chemical_consumptions'), $request, 'engineering.read')->where('facility_id', $data['facility_id'])->where('period_month', $data['period_month'])->where('status', 'approved')->whereNull('business_date')->whereNull('shift_code')->get();
        $key = static fn ($row) => json_encode([$row->facility_id, $row->unit_id, $row->period_month, $row->chemical_code, $row->unit_code]);
        $groups = [];
        foreach ($targets as $row) { $groups[$key($row)]['target'][] = $row; }
        foreach ($actuals as $row) { $groups[$key($row)]['actual'][] = $row; }
        $result = [];
        foreach ($groups as $group) {
            $target = $group['target'][0] ?? null;
            $actual = $group['actual'][0] ?? null;
            $row = $target ?? $actual;
            $status = count($group['actual'] ?? []) > 1 ? 'ambiguous_actual' : (!$target ? 'missing_target' : (!$actual || $actual->quantity === null ? 'missing_actual' : 'valid'));
            $variance = $status === 'valid' ? DB::selectOne('SELECT CAST(? AS DECIMAL(20,6)) - CAST(? AS DECIMAL(20,6)) AS variance', [$actual->quantity, $target->quantity])->variance : null;
            $result[] = ['facility_id' => $row->facility_id, 'unit_id' => $row->unit_id, 'period_month' => $row->period_month, 'chemical_code' => $row->chemical_code, 'unit_code' => $row->unit_code, 'target' => $target?->quantity, 'actual' => $status === 'ambiguous_actual' ? null : $actual?->quantity, 'variance' => $variance, 'status' => $status, 'target_id' => $target?->id, 'target_revision' => $target?->revision_no, 'target_source' => $target?->source_reference, 'actual_id' => $status === 'ambiguous_actual' ? null : $actual?->id, 'actual_source' => $status === 'ambiguous_actual' ? null : $actual?->source_reference];
        }
        return response()->json(['data' => $result]);
    }
}
