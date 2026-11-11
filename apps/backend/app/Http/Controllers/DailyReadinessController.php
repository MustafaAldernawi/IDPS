<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DailyReadinessController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless($request->user(), 401);
        abort_unless(DB::table('user_roles as ur')->join('role_permissions as rp', 'rp.role_id', '=', 'ur.role_id')->join('permissions as p', 'p.id', '=', 'rp.permission_id')->where('ur.user_id', $request->user()->id)->where('p.code', 'engineering.read')->exists(), 403);
        $data = $request->validate(['facility_id' => 'required|uuid|exists:facilities,id', 'product_id' => 'required|uuid|exists:product_catalog,id', 'unit_id' => 'nullable|uuid|exists:refinery_units,id', 'from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from']);
        $from = CarbonImmutable::parse($data['from'], 'Africa/Tripoli');
        $to = CarbonImmutable::parse($data['to'], 'Africa/Tripoli');
        abort_if($from->diffInDays($to) > 3660, 422, 'Requested interval exceeds ten years');
        $query = OperationsController::scoped(DB::table('production_records'), $request, 'engineering.read')->where('facility_id', $data['facility_id'])->where('product_id', $data['product_id'])->where('status', 'approved')->whereNotNull('approved_at')->whereNotNull('business_date')->whereNull('shift_code')->whereBetween('business_date', [$data['from'], $data['to']])->where('unit_code', 't');
        if (! empty($data['unit_id'])) {
            $query->where('unit_id', $data['unit_id']);
        } else {
            $query->whereNull('unit_id');
        }
        $rows = $query->select('business_date')->selectRaw('COUNT(*) AS record_count, COUNT(quantity) AS value_count, MIN(quantity) AS quantity')->groupBy('business_date')->get()->keyBy('business_date');
        $missing = [];
        $ambiguous = [];
        $observed = 0;
        $zero = 0;
        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $key = $day->format('Y-m-d');
            $row = $rows->get($key);
            if ($row && $row->record_count > 1) {
                $ambiguous[] = $key;
            } elseif (! $row || ! $row->value_count) {
                $missing[] = $key;
            } else {
                $observed++;
                if (bccomp($row->quantity, '0', 6) === 0) {
                    $zero++;
                }
            }
        }
        $expected = $observed + count($missing) + count($ambiguous);

        return response()->json(['data' => ['facility_id' => $data['facility_id'], 'product_id' => $data['product_id'], 'from' => $data['from'], 'to' => $data['to'], 'expected_days' => $expected, 'observed_days' => $observed, 'zero_days' => $zero, 'missing_days' => count($missing), 'missing_dates' => $missing, 'ambiguous_days' => count($ambiguous), 'ambiguous_dates' => $ambiguous, 'coverage_pct' => bcdiv((string) ($observed * 100), (string) $expected, 2), 'status' => 'DAILY_MODEL_NOT_READY', 'reason' => 'Coverage alone cannot establish validated features, operating regimes or model readiness.']]);
    }
}
