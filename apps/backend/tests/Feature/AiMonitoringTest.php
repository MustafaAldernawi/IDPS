<?php

namespace Tests\Feature;

use App\Http\Controllers\AiController;
use App\Http\Controllers\AiMonitoringController;
use App\Http\Middleware\ApiIdentity;
use App\Jobs\EligibleRetraining;
use App\Jobs\TrainCandidate;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private User $trainer;

    private string $facility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->facility = DB::table('facilities')->value('id');
        $this->trainer = User::factory()->create();
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $this->trainer->id, 'role_id' => DB::table('roles')->where('code', 'ML_OPERATOR')->value('id')]);
        Route::middleware(ApiIdentity::class)->get('/test-ai/monitoring', [AiMonitoringController::class, 'monitoring']);
        Route::middleware(ApiIdentity::class)->post('/test-ai/forecasts/{id}/evaluate', [AiMonitoringController::class, 'evaluate']);
    }

    private function fact(string $period, string $status = 'approved', string $product = 'DIESEL', string $quantity = '100.000000'): string
    {
        $source = (string) Str::uuid();
        $batch = (string) Str::uuid();
        $row = (string) Str::uuid();
        $id = (string) Str::uuid();
        DB::table('source_files')->insert(['id' => $source, 'sha256' => hash('sha256', $source), 'original_name' => 'synthetic.csv', 'storage_path' => 'test-synthetic', 'bytes' => 0, 'created_at' => now()]);
        DB::table('import_batches')->insert(['id' => $batch, 'source_file_id' => $source, 'facility_id' => $this->facility, 'created_by' => $this->trainer->id, 'mapping_version' => 'test', 'created_at' => now()]);
        DB::table('import_rows')->insert(['id' => $row, 'batch_id' => $batch, 'source_locator' => '{}', 'raw_json' => '{}', 'qa_flags' => '[]']);
        DB::table('monthly_observations')->insert(['id' => $id, 'facility_id' => $this->facility, 'period_month' => $period, 'metric_code' => 'product', 'item_code' => $product, 'actual_or_target' => 'actual', 'report_status' => 'actual', 'quantity' => $quantity, 'unit_code' => 't', 'source_file_id' => $source, 'import_row_id' => $row, 'status' => $status, 'available_at' => $period.'-20 00:00:00', 'approved_at' => $status === 'approved' ? $period.'-21 00:00:00' : null, 'approved_by' => $status === 'approved' ? $this->trainer->id : null]);

        return $id;
    }

    private function forecast(string $period, string $product = 'DIESEL'): string
    {
        $id = (string) Str::uuid();
        DB::table('forecast_runs')->insert(['id' => $id, 'facility_id' => $this->facility, 'requested_by' => $this->trainer->id, 'product_code' => $product, 'period_month' => $period, 'as_of' => '2022-12-31 00:00:00', 'status' => 'completed', 'engine_type' => 'statistical_baseline', 'prediction' => '90.000000', 'unit_code' => 't', 'input_json' => '{}', 'result_json' => '{}', 'created_at' => now()]);

        return $id;
    }

    public function test_evaluation_requires_mature_approved_matching_fact_and_is_idempotent(): void
    {
        $forecast = $this->forecast('2023-01');
        $this->fact('2023-01', 'staged');
        $this->actingAs($this->trainer)->postJson('/test-ai/forecasts/'.$forecast.'/evaluate')->assertStatus(409);
        $id = $this->fact('2023-01', 'approved');
        $result = $this->postJson('/test-ai/forecasts/'.$forecast.'/evaluate')->assertCreated()->json('data');
        $this->assertSame($id, $result['observation_id']);
        $this->assertSame('10.000000', $result['absolute_error']);
        $this->postJson('/test-ai/forecasts/'.$forecast.'/evaluate')->assertOk();
        $this->assertDatabaseCount('forecast_evaluations', 1);
        $this->assertDatabaseHas('forecast_runs', ['id' => $forecast, 'prediction' => '90.000000']);
    }

    public function test_monitoring_reports_insufficient_samples_without_drift_claim(): void
    {
        $this->actingAs($this->trainer)->getJson('/test-ai/monitoring?facility_id='.$this->facility.'&product_code=DIESEL')->assertOk()->assertJsonPath('data.status', 'insufficient_samples')->assertJsonPath('data.drift', null);
    }

    public function test_monitoring_counts_one_forecast_per_month_and_refuses_mixed_engine_scopes(): void
    {
        $this->fact('2023-01');
        $first = $this->forecast('2023-01');
        $second = $this->forecast('2023-01');
        $this->actingAs($this->trainer)->postJson('/test-ai/forecasts/'.$first.'/evaluate')->assertCreated();
        $this->postJson('/test-ai/forecasts/'.$second.'/evaluate')->assertCreated();
        $url = '/test-ai/monitoring?facility_id='.$this->facility.'&product_code=DIESEL';
        $this->getJson($url)->assertOk()->assertJsonPath('data.sample_count', 1)->assertJsonPath('data.selection_policy', 'earliest_as_of_per_month');
        $third = $this->forecast('2023-01');
        DB::table('forecast_runs')->where('id', $third)->update(['engine_type' => 'engineering_baseline']);
        $this->postJson('/test-ai/forecasts/'.$third.'/evaluate')->assertCreated();
        $this->getJson($url)->assertOk()->assertJsonPath('data.status', 'ambiguous_scope')->assertJsonPath('data.mae', null)->assertJsonPath('data.drift', null);
        $this->getJson($url.'&engine_type=statistical_baseline')->assertOk()->assertJsonPath('data.sample_count', 1);
    }

    public function test_scheduler_skips_unapproved_and_retains_current_champion(): void
    {
        Queue::fake();
        $this->fact('2023-01', 'staged');
        $policy = (string) Str::uuid();
        DB::table('ai_retraining_policies')->insert(['id' => $policy, 'facility_id' => $this->facility, 'product_code' => 'DIESEL', 'actor_id' => $this->trainer->id, 'enabled' => true, 'min_new_months' => 2, 'created_at' => now(), 'updated_at' => now()]);
        (new EligibleRetraining('2026-11'))->handle();
        $this->assertDatabaseHas('ai_retraining_checks', ['policy_id' => $policy, 'period_month' => '2026-11', 'status' => 'insufficient_training_data']);
        Queue::assertNotPushed(TrainCandidate::class);
        $this->assertDatabaseCount('training_runs', 0);
    }

    public function test_scheduler_queues_only_after_new_approved_months_and_never_promotes(): void
    {
        Queue::fake();
        for ($year = 2021; $year <= 2023; $year++) {
            for ($month = 1; $month <= 12; $month++) {
                $this->fact(sprintf('%04d-%02d', $year, $month));
            }
        }
        $baselineRows = DB::table('monthly_observations')->orderBy('period_month')->get();
        $rows = AiController::rows($baselineRows);
        $dataset = (string) Str::uuid();
        $run = (string) Str::uuid();
        $champion = (string) Str::uuid();
        DB::table('dataset_snapshots')->insert(['id' => $dataset, 'facility_id' => $this->facility, 'name' => 'synthetic baseline', 'product_code' => 'DIESEL', 'mode' => 'approved', 'dataset_sha256' => hash('sha256', AiController::canonical($rows)), 'snapshot_json' => AiController::canonical($rows), 'source_observation_ids' => json_encode($baselineRows->pluck('id')->all()), 'created_by' => $this->trainer->id, 'created_at' => now()]);
        DB::table('training_runs')->insert(['id' => $run, 'dataset_id' => $dataset, 'trained_by' => $this->trainer->id, 'status' => 'completed', 'seed' => 42, 'idempotency_key' => $run, 'command_sha256' => hash('sha256', $run), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('model_versions')->insert(['id' => $champion, 'training_run_id' => $run, 'dataset_id' => $dataset, 'facility_id' => $this->facility, 'product_code' => 'DIESEL', 'status' => 'active', 'service_model_id' => hash('sha256', $champion), 'artifact_sha256' => str_repeat('a', 64), 'dataset_sha256' => hash('sha256', AiController::canonical($rows)), 'feature_schema' => 'test', 'method' => 'ridge', 'mode' => 'approved', 'evaluation_json' => '{}', 'trained_by' => $this->trainer->id, 'created_at' => now()]);
        $this->fact('2024-01');
        $this->fact('2024-02');
        $policy = (string) Str::uuid();
        DB::table('ai_retraining_policies')->insert(['id' => $policy, 'facility_id' => $this->facility, 'product_code' => 'DIESEL', 'actor_id' => $this->trainer->id, 'enabled' => true, 'min_new_months' => 2, 'created_at' => now(), 'updated_at' => now()]);
        (new EligibleRetraining('2026-11'))->handle();
        (new EligibleRetraining('2026-11'))->handle();
        Queue::assertPushed(TrainCandidate::class, 1);
        $this->assertDatabaseHas('ai_retraining_checks', ['policy_id' => $policy, 'period_month' => '2026-11', 'status' => 'queued']);
        $this->assertDatabaseCount('training_runs',2);
        $this->assertDatabaseHas('model_versions',['id' => $champion, 'status' => 'active']);
    }
}
