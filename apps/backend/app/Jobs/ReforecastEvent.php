<?php

namespace App\Jobs;

use App\Http\Controllers\AiController;
use App\Models\User;
use App\Services\Governance;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReforecastEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public array $backoff = [10, 30, 60];

    public function __construct(public string $outboxId) {}

    public function handle(): void
    {
        $queued = DB::transaction(function () {
            $outbox = DB::table('operations_outbox')->where('id', $this->outboxId)->lockForUpdate()->first();
            if (! $outbox) {
                return [];
            }
            if ($outbox->processed_at) {
                return DB::table('forecast_runs')->where('trigger_reference', $outbox->event_key)->where('status', 'queued')->whereNull('reforecast_enqueued_at')->pluck('id')->all();
            }
            if (! str_starts_with($outbox->event_type, 'event.')) {
                DB::table('operations_outbox')->where('id', $outbox->id)->update(['processed_at' => now('UTC')]);

                return [];
            }
            $payload = json_decode($outbox->payload, true, 512, JSON_THROW_ON_ERROR);
            $revision = DB::table('event_revisions')->where('event_id', $payload['event_id'])->where('revision_no', $payload['revision_no'])->first();
            if (! $revision) {
                throw new \RuntimeException('EVENT_REVISION_NOT_AVAILABLE');
            }
            // Never substitute the current event row: delayed consumers must preserve old knowledge.
            $event = json_decode($revision->snapshot, true, 512, JSON_THROW_ON_ERROR);
            if ($event['id'] !== $payload['event_id'] || (int) $event['revision_no'] !== (int) $payload['revision_no']) {
                throw new \RuntimeException('EVENT_REVISION_INTEGRITY_FAILED');
            }
            $asOf = CarbonImmutable::parse($event['known_at'], 'UTC')->utc();
            DB::table('facilities')->where('id', $event['facility_id'])->lockForUpdate()->first();
            $period = $asOf->startOfMonth()->addMonth()->format('Y-m');
            $plan = DB::table('production_target_plans')->where('facility_id', $event['facility_id'])->whereNull('unit_id')->where('period_month', $period)->where('status', 'approved')->where('available_at', '<=', $asOf)->where('approved_at', '<=', $asOf)->orderByDesc('revision_no')->first();
            $queue = [];
            foreach (DB::table('product_catalog')->where('active', true)->orderBy('code')->get() as $product) {
                $command = hash('sha256', AiController::canonical(['outbox_key' => $outbox->event_key, 'product_code' => $product->code, 'period_month' => $period, 'time_granularity' => 'monthly', 'horizon' => 'next_month', 'plan_id' => $plan?->id]));
                if (DB::table('forecast_runs')->where('command_sha256', $command)->exists()) {
                    continue;
                }
                $previous = DB::table('forecast_runs')->where('facility_id', $event['facility_id'])->where('product_code', $product->code)->where('period_month', $period)->where('time_granularity', 'monthly')->where('prediction_horizon', 'next_month')->where('as_of', '<=', $asOf)->orderByDesc('as_of')->orderByDesc('created_at')->first();
                $comparable = $previous && $previous->target_plan_id === $plan?->id;
                $parent = $comparable ? $previous->id : null;
                $observations = DB::table('monthly_observations')->where('facility_id', $event['facility_id'])->where('metric_code', 'product')->where('item_code', $product->code)->where('actual_or_target', 'actual')->where('report_status', 'actual')->where('unit_code', 't')->whereIn('status', ['approved', 'superseded'])->whereNotNull('approved_at')->where('available_at', '<=', $asOf)->where('approved_at', '<=', $asOf)->where('period_month', '<', $period)->orderBy('period_month')->get();
                $superseded = $observations->pluck('supersedes_id')->filter()->all();
                $observations = $observations->reject(fn ($row) => in_array($row->id, $superseded, true))->values();
                $ambiguous = $observations->count() !== $observations->pluck('period_month')->unique()->count();
                $rows = $ambiguous ? [] : AiController::rows($observations);
                $model = DB::table('model_versions')->where('facility_id', $event['facility_id'])->where('product_code', $product->code)->where('status', 'active')->where('mode', 'approved')->where('time_granularity', 'monthly')->where('prediction_horizon', 'next_month')->where('approved_at', '<=', $asOf)->first();
                $status = $model && ! $ambiguous ? 'queued' : 'unavailable';
                $engine = $status === 'queued' ? 'pending' : 'unavailable';
                $prediction = null;
                if (! $model && ! $ambiguous) {
                    $history = collect($rows)->keyBy('period_month');
                    $prior = [];
                    $target = CarbonImmutable::parse($period.'-01', 'UTC');
                    for ($lag = 1; $lag <= 3; $lag++) {
                        $row = $history->get($target->subMonths($lag)->format('Y-m'));
                        if (! $row || $row['quantity'] === null) {
                            $prior = [];
                            break;
                        }$prior[] = (float) $row['quantity'];
                    }
                    if (count($prior) === 3) {
                        $prediction = array_sum($prior) / 3;
                        $status = 'completed';
                        $engine = 'statistical_baseline';
                    }
                }
                $eta = ! empty($event['actual_end_at']) ? 'observed_restart' : (empty($event['expected_end_at']) ? 'unknown' : 'reported');
                $result = ['status' => $status, 'engine_type' => $engine, 'event' => ['event_id' => $event['id'], 'revision_no' => (int) $event['revision_no'], 'kind' => $event['kind'], 'event_type' => $event['event_type'], 'event_status' => $event['status'], 'known_at' => $asOf->format('Y-m-d\TH:i:s.u\Z'), 'eta_status' => $eta, 'expected_end_at' => $event['expected_end_at'] ?? null, 'actual_end_at' => $event['actual_end_at'] ?? null, 'review_status' => ! empty($event['approved_at']) && CarbonImmutable::parse($event['approved_at'], 'UTC')->lte($asOf) ? 'approved' : 'pending_review'], 'event_adjusted' => false, 'event_impact' => null, 'scenario_status' => 'engineering_assumptions_required', 'unit_product_mapping_status' => 'not_available', 'comparison_status' => $previous ? ($comparable ? 'matching_scope_reference_only' : 'incomparable_plan_version') : 'no_previous_forecast', 'previous_run_id' => $previous?->id, 'target_plan_id' => $plan?->id, 'uncertainty_status' => 'not_calibrated', 'quality_warning' => $ambiguous ? 'Ambiguous source revisions; no quantity produced.' : 'Monthly labels do not establish a causal shutdown response. Any numeric reference excludes event adjustment; scenario quantities remain unavailable without signed engineering assumptions.'];
                if ($prediction !== null) {
                    $result['prediction'] = $prediction;
                    $result['method'] = 'mean_previous_3_months';
                }
                $id = (string) Str::uuid();
                DB::table('forecast_runs')->insert(['id' => $id, 'facility_id' => $event['facility_id'], 'model_version_id' => $model?->id, 'requested_by' => $revision->actor_id, 'product_code' => $product->code, 'period_month' => $period, 'as_of' => $asOf, 'status' => $status, 'engine_type' => $engine, 'prediction' => $prediction, 'input_json' => AiController::canonical(['rows' => $rows, 'event_revision_id' => $revision->id, 'source_observation_ids' => $observations->pluck('id')->all(), 'target_plan_id' => $plan?->id]), 'result_json' => json_encode($result, JSON_THROW_ON_ERROR), 'parent_run_id' => $parent, 'trigger_reference' => $outbox->event_key, 'event_id' => $event['id'], 'event_revision' => $event['revision_no'], 'target_plan_id' => $plan?->id, 'command_sha256' => $command, 'created_at' => now('UTC')]);
                $scenarios = $eta === 'unknown' ? [['code' => 'illustrative_1_day', 'days' => 1], ['code' => 'illustrative_3_days', 'days' => 3], ['code' => 'illustrative_7_days', 'days' => 7]] : [['code' => $eta, 'days' => null]];
                foreach ($scenarios as $scenario) {
                    DB::table('forecast_scenarios')->insert(['id' => (string) Str::uuid(), 'forecast_run_id' => $id, 'code' => $scenario['code'], 'status' => 'engineering_assumptions_required', 'illustrative_days' => $scenario['days'], 'prediction' => null, 'assumptions_json' => json_encode(['illustrative_only' => $scenario['days'] !== null, 'duration_is_observed_fact' => false, 'probability' => null, 'event_revision_id' => $revision->id, 'expected_end_at' => $event['expected_end_at'] ?? null, 'actual_end_at' => $event['actual_end_at'] ?? null, 'engineering_approval_reference' => null], JSON_THROW_ON_ERROR), 'quality_warning' => 'Illustrative assumptions only; not a downtime prediction, probability or quantified production loss.', 'created_at' => now('UTC')]);
                }
                $request = Request::create('/internal/event-reforecast');
                $request->setUserResolver(fn () => User::find($revision->actor_id));
                Governance::audit($request, 'event.reforecast', 'forecast_run', $id, null, ['event_id' => $event['id'], 'event_revision' => $event['revision_no'], 'parent_run_id' => $parent, 'status' => $status]);
                if ($status === 'queued') {
                    $queue[] = $id;
                }
            }
            DB::table('operations_outbox')->where('id', $outbox->id)->update(['processed_at' => now('UTC')]);

            return $queue;
        }, 3);
        // Durable run rows survive publication failure; retry reuses them without new forecasts.
        foreach ($queued as $id) {
            PredictForecast::dispatch($id)->afterCommit();
            DB::table('forecast_runs')->where('id',$id)->update(['reforecast_enqueued_at' => now('UTC')]);
        }
    }
}
