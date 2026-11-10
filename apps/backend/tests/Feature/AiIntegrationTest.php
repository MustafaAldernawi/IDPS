<?php

namespace Tests\Feature;

use App\Http\Controllers\AiController;
use App\Jobs\TrainCandidate;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role): User
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role_id' => DB::table('roles')->where('code', $role)->value('id')]);

        return $user;
    }

    private function snapshot(User $user, string $mode = 'approved'): string
    {
        $existing = DB::table('dataset_snapshots')->where('mode', $mode)->where('dataset_sha256', hash('sha256', '[]'))->value('id');
        if ($existing) {
            return $existing;
        }
        $id = (string) Str::uuid();
        DB::table('dataset_snapshots')->insert(['id' => $id, 'facility_id' => DB::table('facilities')->value('id'), 'name' => 'Synthetic test snapshot', 'product_code' => 'DIESEL', 'mode' => $mode, 'dataset_sha256' => hash('sha256', '[]'), 'snapshot_json' => '[]', 'source_observation_ids' => '[]', 'created_by' => $user->id, 'created_at' => now('UTC')]);

        return $id;
    }

    private function candidate(User $trainer, string $mode = 'approved', string $status = 'candidate'): string
    {
        $dataset = $this->snapshot($trainer, $mode);
        $run = (string) Str::uuid();
        $id = (string) Str::uuid();
        DB::table('training_runs')->insert(['id' => $run, 'dataset_id' => $dataset, 'trained_by' => $trainer->id, 'status' => 'completed', 'idempotency_key' => $run, 'command_sha256' => hash('sha256', $run), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('model_versions')->insert(['id' => $id, 'training_run_id' => $run, 'dataset_id' => $dataset, 'facility_id' => DB::table('facilities')->value('id'), 'product_code' => 'DIESEL', 'status' => $status, 'service_model_id' => hash('sha256', $id), 'artifact_sha256' => str_repeat('b', 64), 'dataset_sha256' => hash('sha256', '[]'), 'feature_schema' => 'monthly_previous_product_mean3_calendar_v1', 'method' => 'mean3', 'mode' => $mode, 'evaluation_json' => '{}', 'trained_by' => $trainer->id, 'created_at' => now()]);

        return $id;
    }

    public function test_training_is_authorized_async_and_replay_is_idempotent(): void
    {
        Queue::fake();
        $this->getJson('/api/v1/ai/datasets')->assertUnauthorized();
        $user = $this->actor('ML_OPERATOR');
        $dataset = $this->snapshot($user);
        $first = $this->actingAs($user)->postJson('/api/v1/ai/training-runs', ['dataset_id' => $dataset])->assertAccepted();
        $this->postJson('/api/v1/ai/training-runs', ['dataset_id' => $dataset])->assertAccepted()->assertJsonPath('data.id', $first->json('data.id'));
        Queue::assertPushed(TrainCandidate::class, 1);
        $this->assertDatabaseCount('training_runs', 1);
    }

    public function test_worker_records_not_ready_and_service_failures_without_fake_models(): void
    {
        Queue::fake();
        config(['idps_ai.key' => 'test-service-key', 'idps_ai.url' => 'http://ai.test']);
        $user = $this->actor('ML_OPERATOR');
        $dataset = $this->snapshot($user);
        $run = $this->actingAs($user)->postJson('/api/v1/ai/training-runs', ['dataset_id' => $dataset])->assertAccepted()->json('data.id');
        Http::fake(['ai.test/*' => Http::response(['status' => 'not_ready', 'can_deploy' => false, 'reason' => 'insufficient_time_safe_history'])]);
        (new TrainCandidate($run))->handle();
        $this->assertDatabaseHas('training_runs', ['id' => $run, 'status' => 'not_ready']);
        $this->assertDatabaseCount('model_versions', 0);
    }

    public function test_completed_training_stays_candidate_and_trainer_cannot_promote(): void
    {
        Queue::fake();
        config(['idps_ai.key' => 'test-service-key', 'idps_ai.url' => 'http://ai.test']);
        $user = $this->actor('ML_OPERATOR');
        $dataset = $this->snapshot($user);
        $run = $this->actingAs($user)->postJson('/api/v1/ai/training-runs', ['dataset_id' => $dataset])->assertAccepted()->json('data.id');
        Http::fake(['ai.test/*' => Http::response(['status' => 'completed', 'model_id' => str_repeat('a', 64), 'artifact_sha256' => str_repeat('b', 64), 'dataset_sha256' => hash('sha256', '[]'), 'feature_schema' => 'monthly_previous_product_mean3_calendar_v1', 'selected_method' => 'mean3', 'can_deploy' => false, 'candidates' => []])]);
        (new TrainCandidate($run))->handle();
        (new TrainCandidate($run))->handle();
        $this->assertDatabaseCount('model_versions', 1);
        $model = DB::table('model_versions')->first();
        $this->assertSame('candidate', $model->status);
        $this->postJson('/api/v1/ai/models/'.$model->id.'/promote', ['reason' => 'test'])->assertForbidden();
    }

    public function test_research_model_promotion_is_blocked_and_no_model_forecast_is_truthful(): void
    {
        $user = $this->actor('PROCESS_ENGINEER');
        $this->actingAs($user)->postJson('/api/v1/ai/forecasts', ['product_code' => 'DIESEL', 'period_month' => '2027-01', 'as_of' => '2026-12-31T23:59:59Z'])->assertCreated()->assertJsonPath('data.status', 'unavailable');
        $this->assertDatabaseCount('forecast_runs', 1);
    }

    public function test_research_promotion_and_self_approval_fail_even_with_promotion_permission(): void
    {
        $reviewer = $this->actor('PROCESS_ENGINEER');
        $trainer = $this->actor('ML_OPERATOR');
        $research = $this->candidate($trainer, 'research_only');
        $own = $this->candidate($reviewer);
        $decision = ['reason' => 'Independent evaluation accepted', 'evaluation_reference' => 'signed-scientific-review', 'confirm_independent_evaluation' => true];
        $this->actingAs($reviewer)->postJson('/api/v1/ai/models/'.$research.'/promote', $decision)->assertConflict();
        $this->postJson('/api/v1/ai/models/'.$own.'/promote', $decision)->assertForbidden();
        $this->assertDatabaseCount('ai_activation_requests', 0);
    }

    public function test_pending_activation_preserves_champion_and_retry_uses_same_durable_approval(): void
    {
        config(['idps_ai.key' => 'test-service-key', 'idps_ai.url' => 'http://ai.test']);
        $reviewer = $this->actor('PROCESS_ENGINEER');
        $trainer = $this->actor('ML_OPERATOR');
        $champion = $this->candidate($trainer, 'approved', 'active');
        $challenger = $this->candidate($trainer);
        $decision = ['reason' => 'Independent evaluation accepted', 'evaluation_reference' => 'signed-scientific-review', 'confirm_independent_evaluation' => true];
        $serviceId = DB::table('model_versions')->where('id', $challenger)->value('service_model_id');
        Http::fake(['ai.test/*' => Http::sequence()->push([], 503)->push(['status' => 'approved_for_internal_serving', 'model_id' => $serviceId], 200)]);
        $pending = $this->actingAs($reviewer)->postJson('/api/v1/ai/models/'.$challenger.'/promote', $decision)->assertAccepted()->assertJsonPath('data.status', 'activation_pending')->json('data.activation_id');
        $this->assertDatabaseHas('model_versions', ['id' => $champion, 'status' => 'active']);
        $this->postJson('/api/v1/ai/models/'.$challenger.'/promote', $decision)->assertOk();
        $this->assertDatabaseHas('ai_activation_requests', ['id' => $pending, 'status' => 'completed']);
        $this->assertDatabaseHas('model_versions', ['id' => $champion, 'status' => 'archived']);
        $this->assertDatabaseHas('model_versions', ['id' => $challenger, 'status' => 'active']);
        $this->assertSame(1, DB::table('model_versions')->where('status', 'active')->count());
        $this->assertDatabaseCount('ai_activation_requests', 1);
        Http::assertSent(fn ($request) => ($request['approval_reference'] ?? null) === $pending);
    }

    public function test_failed_training_remains_retryable_and_final_failure_is_explicit(): void
    {
        Queue::fake();
        config(['idps_ai.key' => 'test-service-key', 'idps_ai.url' => 'http://ai.test']);
        $user = $this->actor('ML_OPERATOR');
        $dataset = $this->snapshot($user);
        $run = $this->actingAs($user)->postJson('/api/v1/ai/training-runs', ['dataset_id' => $dataset])->assertAccepted()->json('data.id');
        Http::fake(['ai.test/*' => Http::response([], 503)]);
        $job = new TrainCandidate($run);
        try {
            $job->handle();
            $this->fail('Expected service failure');
        } catch (\RuntimeException $error) {
            $this->assertSame('AI_WORKER_FAILED', $error->getMessage());
        }
        $this->assertDatabaseHas('training_runs', ['id' => $run, 'status' => 'queued', 'error_code' => 'AI_WORKER_FAILED']);
        $job->failed(new \RuntimeException('test outage'));
        $this->assertDatabaseHas('training_runs', ['id' => $run, 'status' => 'failed']);
        $this->assertDatabaseCount('model_versions', 0);
    }

    public function test_snapshot_preserves_source_ids_unknown_availability_and_replay_hash(): void
    {
        Queue::fake();
        $user = $this->actor('ML_OPERATOR');
        $reviewer = $this->actor('PROCESS_ENGINEER');
        $facility = DB::table('facilities')->value('id');
        $source = (string) Str::uuid();
        $batch = (string) Str::uuid();
        $sourceRow = (string) Str::uuid();
        $observation = (string) Str::uuid();
        DB::table('source_files')->insert(['id' => $source, 'sha256' => hash('sha256', 'synthetic-fixture'), 'original_name' => 'synthetic.csv', 'storage_path' => 'test-only-not-a-refinery-source', 'bytes' => 0, 'created_at' => now()]);
        DB::table('import_batches')->insert(['id' => $batch, 'source_file_id' => $source, 'facility_id' => $facility, 'created_by' => $user->id, 'mapping_version' => 'synthetic-v1', 'created_at' => now()]);
        DB::table('import_rows')->insert(['id' => $sourceRow, 'batch_id' => $batch, 'source_locator' => '{"row":1}', 'raw_json' => '{"synthetic":true}', 'qa_flags' => '[]']);
        DB::table('monthly_observations')->insert(['id' => $observation, 'facility_id' => $facility, 'period_month' => '2021-01', 'metric_code' => 'product', 'item_code' => 'DIESEL', 'actual_or_target' => 'actual', 'report_status' => 'actual', 'quantity' => '123.456000', 'unit_code' => 't', 'source_file_id' => $source, 'import_row_id' => $sourceRow, 'status' => 'approved', 'available_at' => null, 'approved_at' => now(), 'approved_by' => $reviewer->id]);
        $request = ['name' => 'Synthetic snapshot', 'product_code' => 'DIESEL', 'mode' => 'approved'];
        $id = $this->actingAs($user)->postJson('/api/v1/ai/datasets', $request)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/ai/datasets', $request)->assertOk()->assertJsonPath('data.id', $id);
        $snapshot = DB::table('dataset_snapshots')->where('id', $id)->first();
        $rows = json_decode($snapshot->snapshot_json, true);
        $this->assertNull($rows[0]['available_at']);
        $this->assertSame('123.456000', $rows[0]['quantity']);
        $this->assertSame([$observation], json_decode($snapshot->source_observation_ids, true));
        $this->assertSame(hash('sha256', AiController::canonical($rows)), $snapshot->dataset_sha256);
        $run = $this->postJson('/api/v1/ai/training-runs', ['dataset_id' => $id])->assertAccepted()->json('data.id');
        Http::fake();
        (new TrainCandidate($run))->handle();
        Http::assertNothingSent();
        $this->assertDatabaseHas('training_runs',['id' => $run, 'status' => 'not_ready']);
        $this->assertSame($snapshot->snapshot_json,DB::table('dataset_snapshots')->where('id',$id)->value('snapshot_json'));
    }
}
