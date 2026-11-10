<?php

namespace App\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PredictForecast implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    public array $backoff = [10, 30, 60];

    public function __construct(public string $forecastId) {}

    public function handle(): void
    {
        $run = DB::table('forecast_runs')->where('id', $this->forecastId)->first();
        if (! $run || in_array($run->status, ['completed', 'unavailable'], true)) {
            return;
        }
        $model = DB::table('model_versions')->where('id', $run->model_version_id)->first();
        if (! config('idps_ai.key')) {
            throw new \RuntimeException('AI_SERVICE_NOT_CONFIGURED');
        }
        $input = json_decode($run->input_json, true, 512, JSON_THROW_ON_ERROR);
        $response = Http::connectTimeout(5)->timeout(30)->withHeaders(['X-IDPS-Service-Key' => config('idps_ai.key')])->post(rtrim(config('idps_ai.url'), '/').'/internal/predict', ['model_id' => $model->service_model_id, 'artifact_sha256' => $model->artifact_sha256, 'dataset_sha256' => $model->dataset_sha256, 'product_code' => $run->product_code, 'feature_schema' => $model->feature_schema, 'period_month' => $run->period_month, 'as_of' => CarbonImmutable::parse($run->as_of, 'UTC')->format('Y-m-d\TH:i:s.u\Z'), 'rows' => $input['rows']]);
        if ($response->status() === 409) {
            $context = json_decode($run->result_json, true, 512, JSON_THROW_ON_ERROR);
            DB::table('forecast_runs')->where('id', $run->id)->where('status', 'queued')->update(['status' => 'unavailable', 'engine_type' => 'unavailable', 'result_json' => json_encode(array_merge($context, ['status' => 'unavailable', 'engine_type' => 'unavailable', 'quality_warning' => ($context['quality_warning'] ?? '').' Model or time-safe history failed the serving gate.']), JSON_THROW_ON_ERROR)]);

            return;
        }
        if (! $response->successful()) {
            throw new \RuntimeException('AI_PREDICTION_REQUEST_FAILED');
        }
        $result = $response->json();
        if (($result['model_version'] ?? null) !== $model->service_model_id || ($result['product'] ?? null) !== $run->product_code || ! is_numeric($result['prediction'] ?? null) || ! is_finite((float) $result['prediction']) || $result['prediction'] < 0) {
            throw new \RuntimeException('AI_PREDICTION_INTEGRITY_FAILED');
        }
        $context = json_decode($run->result_json, true, 512, JSON_THROW_ON_ERROR);
        $result = array_merge($result, array_intersect_key($context, array_flip(['event', 'event_adjusted', 'event_impact', 'scenario_status', 'unit_product_mapping_status', 'comparison_status', 'previous_run_id', 'target_plan_id'])));
        if (isset($context['event'])) {
            $result['quality_warning'] = ($context['quality_warning'] ?? '').' '.($result['quality_warning'] ?? '');
        }
        DB::table('forecast_runs')->where('id', $run->id)->where('status', 'queued')->update(['status' => 'completed', 'engine_type' => $result['engine_type'], 'prediction' => (string) $result['prediction'], 'result_json' => json_encode($result, JSON_THROW_ON_ERROR)]);
    }

    public function failed(?\Throwable $error): void
    {
        $run = DB::table('forecast_runs')->where('id', $this->forecastId)->first();
        $context = $run ? json_decode($run->result_json, true, 512, JSON_THROW_ON_ERROR) : [];
        DB::table('forecast_runs')->where('id', $this->forecastId)->where('status', 'queued')->update(['status' => 'failed', 'engine_type' => 'unavailable', 'result_json' => json_encode(array_merge($context, ['status' => 'failed', 'engine_type' => 'unavailable', 'quality_warning' => ($context['quality_warning'] ?? '').' AI worker failed; no quantity fabricated.']), JSON_THROW_ON_ERROR)]);
    }
}
