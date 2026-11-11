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

/** Monthly eligibility audit. It never promotes or replaces a model. */
class EligibleRetraining implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public ?string $periodMonth = null) {}

    public function handle(): void
    {
        $period = $this->periodMonth ?? now('Africa/Tripoli')->format('Y-m');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) || $period > now('Africa/Tripoli')->format('Y-m')) {
            throw new \InvalidArgumentException('Invalid scheduler month');
        }
        foreach (DB::table('ai_retraining_policies')->where('enabled', true)->pluck('id') as $policyId) {
            $runId = DB::transaction(function () use ($policyId, $period) {
                $policy = DB::table('ai_retraining_policies')->where('id', $policyId)->lockForUpdate()->first();
                if (! $policy || ! $policy->enabled) {
                    return null;
                }
                $existing = DB::table('ai_retraining_checks')->where('policy_id', $policyId)->where('period_month', $period)->first();
                if ($existing) {
                    return $existing->training_run_id;
                }
                $checkId = (string) Str::uuid();
                $record = function (string $status, string $reason, ?string $dataset = null, ?string $run = null) use ($policy, $period, $checkId) {
                    DB::table('ai_retraining_checks')->insert(['id' => $checkId, 'policy_id' => $policy->id, 'period_month' => $period, 'status' => $status, 'reason' => $reason, 'dataset_id' => $dataset, 'training_run_id' => $run, 'created_at' => now('UTC')]);
                    $actor = User::where('id', $policy->actor_id)->first();
                    $request = Request::create('/internal/retraining-scheduler');
                    $request->setUserResolver(fn () => $actor);
                    Governance::audit($request, 'retraining.'.$status, 'ai_retraining_policy', $policy->id, null, ['check_id' => $checkId, 'period_month' => $period, 'reason' => $reason]);

                    return $run;
                };
                $actor = User::where('id', $policy->actor_id)->where('active', true)->first();
                if (! $actor || ! Governance::hasPermission($actor->id, 'ai.train')) {
                    return $record('not_ready', 'scheduler_actor_not_authorized');
                }
                $baseline = DB::table('training_runs as t')->join('dataset_snapshots as d', 'd.id', '=', 't.dataset_id')->where('d.facility_id', $policy->facility_id)->where('d.product_code', $policy->product_code)->where('d.mode', 'approved')->where('t.status', 'completed')->orderByDesc('t.created_at')->select('d.*')->first();
                if (! $baseline) {
                    return $record('insufficient_training_data', 'no_completed_approved_baseline');
                }
                $observations = DB::table('monthly_observations')->where('facility_id', $policy->facility_id)->where('metric_code', 'product')->where('item_code', $policy->product_code)->where('actual_or_target', 'actual')->where('report_status', 'actual')->where('unit_code', 't')->where('status', 'approved')->whereNotNull('quantity')->whereNotNull('available_at')->whereNotNull('approved_at')->whereNotNull('approved_by')->where('available_at', '<=', now('UTC'))->where('approved_at', '<=', now('UTC'))->where('period_month', '<', $period)->orderBy('period_month')->get();
                if ($observations->count() < max(36, (int) $policy->min_training_months) || $observations->pluck('period_month')->unique()->count() !== $observations->count()) {
                    return $record('insufficient_training_data', 'coverage_or_duplicate');
                }
                $periods = $observations->pluck('period_month')->all();
                foreach (array_slice($periods, 1) as $index => $current) {
                    if (CarbonImmutable::parse($periods[$index].'-01', 'UTC')->addMonth()->format('Y-m') !== $current) {
                        return $record('insufficient_training_data', 'monthly_history_gap');
                    }
                }
                $oldRows = json_decode($baseline->snapshot_json, true, 512, JSON_THROW_ON_ERROR);
                $lastOld = collect($oldRows)->pluck('period_month')->max();
                $new = $observations->filter(fn ($row) => $lastOld !== null && $row->period_month > $lastOld)->count();
                if ($new < max(1, (int) $policy->min_new_months)) {
                    return $record('insufficient_training_data', 'insufficient_new_approved_months');
                }
                $rows = AiController::rows($observations);
                $sha = hash('sha256', AiController::canonical($rows));
                $snapshot = DB::table('dataset_snapshots')->where(['facility_id' => $policy->facility_id, 'product_code' => $policy->product_code, 'mode' => 'approved', 'dataset_sha256' => $sha])->first();
                if (! $snapshot) {
                    $dataset = (string) Str::uuid();
                    DB::table('dataset_snapshots')->insert(['id' => $dataset, 'facility_id' => $policy->facility_id, 'name' => 'Periodic approved monthly snapshot '.$period, 'product_code' => $policy->product_code, 'mode' => 'approved', 'dataset_sha256' => $sha, 'snapshot_json' => AiController::canonical($rows), 'source_observation_ids' => json_encode($observations->pluck('id')->all(), JSON_THROW_ON_ERROR), 'quality_warning' => 'Periodic candidate only; independent evaluation and approval required.', 'created_by' => $actor->id, 'created_at' => now('UTC')]);
                } else {
                    $dataset = $snapshot->id;
                }
                $seed = 42;
                $command = hash('sha256', AiController::canonical(['dataset_id' => $dataset, 'seed' => $seed]));
                $key = 'periodic:'.$policy->id.':'.$period;
                $run = (string) Str::uuid();
                DB::table('training_runs')->insert(['id' => $run, 'dataset_id' => $dataset, 'trained_by' => $actor->id, 'status' => 'queued', 'seed' => $seed, 'idempotency_key' => $key, 'command_sha256' => $command, 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);

                return $record('queued', 'new_approved_months', $dataset, $run);
            }, 3);
            if ($runId) {
                $run = DB::table('training_runs')->where('id', $runId)->first();
                if ($run && $run->status === 'queued' && ! $run->enqueued_at) {
                    TrainCandidate::dispatch($runId)->afterCommit();
                    DB::table('training_runs')->where('id',$runId)->whereNull('enqueued_at')->update(['enqueued_at' => now('UTC')]);
                }
            }
        }
    }
}
