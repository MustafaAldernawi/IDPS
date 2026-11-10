<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Governance;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class TrainCandidate implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 240;

    public array $backoff = [10, 30, 60];

    public function __construct(public string $runId) {}

    public function handle(): void
    {
        $run = DB::transaction(function () {
            $run = DB::table('training_runs')->where('id', $this->runId)->lockForUpdate()->first();
            if (! $run || in_array($run->status, ['completed', 'not_ready'], true)) {
                return null;
            }
            if ($run->status === 'running' && $run->lease_expires_at && now('UTC')->lt($run->lease_expires_at)) {
                return null;
            }
            DB::table('training_runs')->where('id', $run->id)->update(['status' => 'running', 'attempts' => $run->attempts + 1, 'lease_expires_at' => now('UTC')->addSeconds(270), 'error_code' => null, 'updated_at' => now('UTC')]);

            return $run;
        });
        if (! $run) {
            return;
        }
        try {
            $dataset = DB::table('dataset_snapshots')->where('id', $run->dataset_id)->first();
            $rows = json_decode($dataset->snapshot_json, true, 512, JSON_THROW_ON_ERROR);
            if (collect($rows)->contains(fn ($row) => $row['quantity'] === null || ($dataset->mode !== 'research_only' && $row['available_at'] === null))) {
                $result = ['status' => 'not_ready', 'reason' => 'missing_historical_availability_or_quantity', 'can_deploy' => false];
            } else {
                if (! config('idps_ai.key')) {
                    throw new \RuntimeException('AI_SERVICE_NOT_CONFIGURED');
                }
                $response = Http::connectTimeout(5)->timeout(config('idps_ai.timeout'))->withHeaders(['X-IDPS-Service-Key' => config('idps_ai.key')])->post(rtrim(config('idps_ai.url'), '/').'/internal/train', ['snapshot_id' => $dataset->id, 'dataset_sha256' => $dataset->dataset_sha256, 'product_code' => $dataset->product_code, 'seed' => $run->seed, 'mode' => $dataset->mode, 'rows' => $rows]);
                if (! $response->successful()) {
                    throw new \RuntimeException('AI_SERVICE_REQUEST_FAILED');
                }
                $result = $response->json();
                if (! is_array($result) || ! in_array($result['status'] ?? null, ['completed', 'not_ready'], true)) {
                    throw new \RuntimeException('AI_SERVICE_INVALID_RESPONSE');
                }
            }
            DB::transaction(function () use ($run, $dataset, $result) {
                $locked = DB::table('training_runs')->where('id', $run->id)->lockForUpdate()->first();
                if (in_array($locked->status, ['completed', 'not_ready'], true)) {
                    return;
                }
                if ($result['status'] === 'completed') {
                    foreach (['model_id', 'artifact_sha256', 'dataset_sha256', 'feature_schema', 'selected_method', 'candidates'] as $key) {
                        if (! array_key_exists($key, $result)) {
                            throw new \RuntimeException('AI_SERVICE_INCOMPLETE_RESULT');
                        }
                    }
                    if ($result['dataset_sha256'] !== $dataset->dataset_sha256 || ! preg_match('/^[a-f0-9]{64}$/', $result['model_id']) || ! preg_match('/^[a-f0-9]{64}$/', $result['artifact_sha256'])) {
                        throw new \RuntimeException('AI_RESULT_INTEGRITY_FAILED');
                    }
                    DB::table('model_versions')->insertOrIgnore(['id' => (string) Str::uuid(), 'training_run_id' => $run->id, 'dataset_id' => $dataset->id, 'facility_id' => $dataset->facility_id, 'product_code' => $dataset->product_code, 'status' => 'candidate', 'service_model_id' => $result['model_id'], 'artifact_sha256' => $result['artifact_sha256'], 'dataset_sha256' => $dataset->dataset_sha256, 'feature_schema' => $result['feature_schema'], 'method' => $result['selected_method'], 'mode' => $dataset->mode, 'evaluation_json' => json_encode($result, JSON_THROW_ON_ERROR), 'trained_by' => $run->trained_by, 'created_at' => now('UTC')]);
                }
                DB::table('training_runs')->where('id', $run->id)->update(['status' => $result['status'], 'result_json' => json_encode($result, JSON_THROW_ON_ERROR), 'lease_expires_at' => null, 'updated_at' => now('UTC')]);
                $request = Request::create('/internal/ai-worker');
                $request->setUserResolver(fn () => User::find($run->trained_by));
                Governance::audit($request, 'training.'.$result['status'], 'training_run', $run->id, null, ['status' => $result['status']]);
            });
        } catch (Throwable $error) {
            DB::table('training_runs')->where('id', $run->id)->whereNotIn('status', ['completed', 'not_ready'])->update(['status' => 'queued', 'error_code' => 'AI_WORKER_FAILED', 'lease_expires_at' => null, 'updated_at' => now('UTC')]);
            throw new \RuntimeException('AI_WORKER_FAILED', 0, $error);
        }
    }

    public function failed(?Throwable $error): void
    {
        DB::table('training_runs')->where('id', $this->runId)->whereNotIn('status', ['completed', 'not_ready'])->update(['status' => 'failed', 'error_code' => 'AI_WORKER_FAILED', 'lease_expires_at' => null, 'updated_at' => now('UTC')]);
    }
}
