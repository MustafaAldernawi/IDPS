<?php

namespace App\Http\Controllers;

use App\Services\SourceMappingDictionary;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SourceMappingController extends Controller
{
    public function inspect(Request $request, SourceMappingDictionary $dictionary)
    {
        $data = $request->validate(['facility_id' => 'required|uuid|exists:facilities,id', 'source_file_id' => 'required|uuid|exists:source_files,id', 'as_of' => 'sometimes|date', 'entries' => 'required|array|min:1|max:500', 'entries.*.alias' => 'required|string|max:255|not_regex:/[\x00-\x1F\x7F]/', 'entries.*.source_scope' => 'required|string|max:255|not_regex:/[\x00-\x1F\x7F]/', 'entries.*.unit_code' => 'required|string|exists:measurement_units,code', 'entries.*.granularity' => ['required', Rule::in(['monthly', 'daily', 'shift'])], 'entries.*.product_stage' => ['required', Rule::in(['unknown', 'not_applicable', 'intermediate_stream', 'final_product', 'byproduct', 'fuel', 'waste'])], 'entries.*.locator' => 'required|array|min:1|max:4', 'entries.*.locator.sheet' => 'sometimes|string|max:255', 'entries.*.locator.cell' => 'sometimes|string|max:32', 'entries.*.locator.row' => 'sometimes|integer|min:1|max:1048576', 'entries.*.locator.column' => 'sometimes|string|max:128']);
        if (isset($data['as_of'])) {
            abort_unless((bool) preg_match('/(?:Z|\+00:00)$/', $data['as_of']), 422, 'Explicit UTC as_of required');
            $data['as_of'] = CarbonImmutable::parse($data['as_of'])->utc()->format('Y-m-d H:i:s.u');
        }

        return response()->json(['data' => $dictionary->inspect($request, $data)]);
    }

    public function unresolved(Request $request, SourceMappingDictionary $dictionary)
    {
        $data = $request->validate(['facility_id' => 'required|uuid|exists:facilities,id']);
        $dictionary->requireScope($request, 'imports.read', $data['facility_id']);

        return response()->json(DB::table('source_mapping_queue')->where('facility_id', $data['facility_id'])->where('status', 'unresolved')->orderBy('created_at')->paginate(100));
    }

    public function propose(Request $request, SourceMappingDictionary $dictionary)
    {
        $data = $request->validate(['queue_id' => 'required|uuid|exists:source_mapping_queue,id', 'metric_code' => ['required', Rule::in(['product', 'feed'])], 'item_code' => 'required|string|max:64|regex:/^[A-Z0-9_]+$/', 'actual_or_target' => ['required', Rule::in(['actual', 'target'])], 'report_status' => ['required', Rule::in(['actual', 'semi_actual'])], 'reason' => 'required|string|min:10|max:2000']);
        abort_if($data['actual_or_target'] === 'target' && $data['report_status'] === 'semi_actual', 422, 'Semi-actual is not a target');

        return response()->json(['data' => $dictionary->propose($request, $data)], 201);
    }

    public function approve(Request $request, string $id, SourceMappingDictionary $dictionary)
    {
        $data = $request->validate(['reason' => 'required|string|min:10|max:2000']);

        return response()->json(['data' => $dictionary->approve($request, $id, $data['reason'])]);
    }
}
