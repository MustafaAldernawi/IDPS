<?php

namespace App\Http\Controllers;

use App\Services\Governance;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AiMonitoringController extends Controller
{
    public function evaluate(Request $request, string $id): JsonResponse
    {
        Governance::authorize($request, 'ai.train');

        return DB::transaction(function () use ($request, $id) {
            $forecast = DB::table('forecast_runs')->where('id', $id)->lockForUpdate()->first();
            abort_unless($forecast, 404);
            abort_unless($forecast->status === 'completed' && $forecast->prediction !== null && $forecast->unit_code === 't' && $forecast->time_granularity === 'monthly' && $forecast->prediction_horizon === 'next_month', 409, 'Forecast is not eligible for monthly evaluation');
            abort_unless($forecast->period_month < now('Africa/Tripoli')->format('Y-m'), 409, 'Target month has not closed');
            abort_unless(CarbonImmutable::parse($forecast->as_of, 'UTC')->lt(CarbonImmutable::parse($forecast->period_month.'-01', 'UTC')), 409, 'Forecast cutoff must precede target month');
            $facts = DB::table('monthly_observations')->where('facility_id', $forecast->facility_id)->where('period_month', $forecast->period_month)->where('metric_code', 'product')->where('item_code', $forecast->product_code)->where('actual_or_target', 'actual')->where('report_status', 'actual')->where('unit_code', 't')->where('status', 'approved')->whereNotNull('quantity')->whereNotNull('approved_by')->whereNotNull('approved_at')->where('approved_at', '<=', now('UTC'))->lockForUpdate()->get();
            abort_unless($facts->count() === 1, 409, 'Exactly one approved matching actual is required');
            $actual = $facts->first();
            $existing = DB::table('forecast_evaluations')->where('forecast_run_id', $id)->where('observation_id', $actual->id)->first();
            if ($existing) {
                return response()->json(['data' => $existing]);
            }
            $error = bcsub((string) $actual->quantity, (string) $forecast->prediction, 6);
            $absolute = str_starts_with($error, '-') ? substr($error, 1) : $error;
            $data = ['id' => (string) Str::uuid(), 'forecast_run_id' => $id, 'observation_id' => $actual->id, 'actual' => $actual->quantity, 'prediction' => $forecast->prediction, 'absolute_error' => $absolute, 'signed_error' => $error, 'unit_code' => 't', 'evaluated_by' => $request->user()->id, 'created_at' => now('UTC')];
            DB::table('forecast_evaluations')->insert($data);
            Governance::audit($request, 'forecast.evaluated', 'forecast_run', $id, null, ['evaluation_id' => $data['id'], 'observation_id' => $actual->id]);

            return response()->json(['data' => $data], 201);
        }, 3);
    }

    public function evaluations(Request $request): JsonResponse
    {
        Governance::authorize($request, 'ai.read');
        $data = $request->validate(['facility_id' => 'required|uuid|exists:facilities,id', 'product_code' => 'required|string|max:64']);
        $rows = DB::table('forecast_evaluations as e')->join('forecast_runs as f', 'f.id', '=', 'e.forecast_run_id')->where('f.facility_id', $data['facility_id'])->where('f.product_code', $data['product_code'])->select('e.*', 'f.period_month', 'f.model_version_id', 'f.engine_type')->orderByDesc('e.created_at')->paginate(100);

        return response()->json($rows);
    }

    public function monitoring(Request $request): JsonResponse
    {
        Governance::authorize($request, 'ai.read');
        $data = $request->validate(['facility_id' => 'required|uuid|exists:facilities,id', 'product_code' => 'required|string|max:64', 'engine_type' => 'nullable|string|max:40', 'model_version_id' => 'nullable|uuid']);
        $minimum = max(12, (int) (DB::table('ai_retraining_policies')->where('facility_id', $data['facility_id'])->where('product_code', $data['product_code'])->value('min_monitoring_samples') ?? 12));
        $query = DB::table('forecast_evaluations as e')->join('forecast_runs as f', 'f.id', '=', 'e.forecast_run_id')->join('monthly_observations as o', 'o.id', '=', 'e.observation_id')->where('f.facility_id', $data['facility_id'])->where('f.product_code', $data['product_code'])->where('o.status', 'approved')->where('f.time_granularity', 'monthly')->where('f.prediction_horizon', 'next_month');
        if (! empty($data['engine_type'])) {
            $query->where('f.engine_type', $data['engine_type']);
        }
        if (! empty($data['model_version_id'])) {
            $query->where('f.model_version_id', $data['model_version_id']);
        }
        $rows = $query->select('e.absolute_error', 'e.actual', 'f.id', 'f.period_month', 'f.as_of', 'f.engine_type', 'f.model_version_id')->orderBy('f.as_of')->orderBy('f.id')->get();
        $scopes = $rows->map(fn ($row) => $row->engine_type.'|'.($row->model_version_id ?? 'none'))->unique();
        if ($scopes->count() > 1) {
            return response()->json(['data' => ['facility_id' => $data['facility_id'], 'product_code' => $data['product_code'], 'status' => 'ambiguous_scope', 'sample_count' => 0, 'minimum_samples' => $minimum, 'mae' => null, 'wape_percent' => null, 'drift' => null, 'warning' => 'Select one engine and model version before comparing forecast errors.']]);
        }
        $rows = $rows->unique('period_month')->values();
        $count = $rows->count();
        $sumError = '0';
        $sumActual = '0';
        foreach ($rows as $row) {
            $sumError = bcadd($sumError, (string) $row->absolute_error, 6);
            $sumActual = bcadd($sumActual, (string) $row->actual, 6);
        }
        $ready = $count >= $minimum;

        return response()->json(['data' => ['facility_id' => $data['facility_id'], 'product_code' => $data['product_code'], 'sample_count' => $count, 'minimum_samples' => $minimum, 'selection_policy' => 'earliest_as_of_per_month', 'status' => $ready ? 'monitoring_only' : 'insufficient_samples', 'drift' => null, 'mae' => $count ? bcdiv($sumError, (string) $count, 6) : null, 'wape_percent' => bccomp($sumActual, '0', 6) !== 0 ? bcdiv(bcmul($sumError, '100', 6), $sumActual, 6) : null, 'warning' => $ready ? 'Descriptive error metrics only; no approved drift threshold or distribution test.' : 'Insufficient independent approved actuals for a drift claim.']]);
    }

    public function policies(Request $request): JsonResponse
    {
        Governance::authorize($request, 'ai.read');

        return response()->json(['data' => DB::table('ai_retraining_policies')->orderBy('product_code')->get()]);
    }

    public function savePolicy(Request $request): JsonResponse
    {
        Governance::authorize($request, 'ai.train');
        $data = $request->validate(['facility_id' => 'required|uuid|exists:facilities,id', 'product_code' => 'required|string|max:64|exists:product_catalog,code', 'enabled' => 'required|boolean', 'min_new_months' => 'required|integer|min:1|max:120', 'min_training_months' => 'required|integer|min:36|max:2400', 'min_monitoring_samples' => 'required|integer|min:12|max:2400', 'reason' => 'required|string|min:10|max:2000']);

        return DB::transaction(function () use ($request, $data) {
            $existing = DB::table('ai_retraining_policies')->where('facility_id', $data['facility_id'])->where('product_code', $data['product_code'])->lockForUpdate()->first();
            $id = $existing?->id ?? (string) Str::uuid();
            $values = collect($data)->except('reason')->all() + ['id' => $id, 'actor_id' => $request->user()->id, 'created_at' => $existing?->created_at ?? now('UTC'), 'updated_at' => now('UTC')];
            DB::table('ai_retraining_policies')->updateOrInsert(['id' => $id], $values);
            Governance::audit($request, 'retraining.policy', 'ai_retraining_policy', $id, $existing ? (array) $existing : null, collect($values)->except('created_at', 'updated_at')->all(), $data['reason']);

            return response()->json(['data' => DB::table('ai_retraining_policies')->find($id)], $existing ? 200 : 201);
        }, 3);
    }

    public function checks(Request $request): JsonResponse
    {
        Governance::authorize($request,'ai.read');
        $data = $request->validate(['facility_id' => 'required|uuid|exists:facilities,id']);

        return response()->json(['data' => DB::table('ai_retraining_checks as c')->join('ai_retraining_policies as p','p.id','=','c.policy_id')->where('p.facility_id',$data['facility_id'])->select('c.*','p.product_code')->orderByDesc('c.created_at')->limit(100)->get()]);
    }
}
