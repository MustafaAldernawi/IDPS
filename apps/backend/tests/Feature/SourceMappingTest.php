<?php

namespace Tests\Feature;

use App\Http\Controllers\SourceMappingController;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class SourceMappingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Route::post('/map/inspect', [SourceMappingController::class, 'inspect']);
        Route::get('/map/unresolved', [SourceMappingController::class, 'unresolved']);
        Route::post('/map/proposals', [SourceMappingController::class, 'propose']);
        Route::post('/map/proposals/{id}/approve', [SourceMappingController::class, 'approve']);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role_id' => DB::table('roles')->where('code', $role)->value('id')]);

        return $user;
    }

    public function test_unknown_alias_is_queued_until_independently_approved_and_exact_scope_matches(): void
    {
        $this->seed(DatabaseSeeder::class);
        $steward = $this->user('DATA_STEWARD');
        $engineer = $this->user('PROCESS_ENGINEER');
        $facility = DB::table('facilities')->value('id');
        $source = (string) Str::uuid();
        DB::table('source_files')->insert(['id' => $source, 'sha256' => hash('sha256', 'synthetic-dictionary'), 'original_name' => 'synthetic.xlsx', 'storage_path' => 'synthetic-only', 'bytes' => 0, 'created_at' => now()]);
        $context = ['facility_id' => $facility, 'source_file_id' => $source, 'entries' => [['alias' => 'زيت ديزل', 'source_scope' => 'الفعلي', 'unit_code' => 't', 'granularity' => 'monthly', 'product_stage' => 'intermediate_stream', 'locator' => ['sheet' => 'الفعلي', 'cell' => 'B19']]]];
        $first = $this->actingAs($steward)->postJson('/map/inspect', $context)->assertOk()->assertJsonPath('data.0.status', 'unresolved')->json('data.0.queue_id');
        $this->postJson('/map/inspect', $context)->assertOk()->assertJsonPath('data.0.queue_id', $first);
        $this->assertDatabaseCount('source_mapping_queue', 1);
        $proposal = ['queue_id' => $first, 'metric_code' => 'product', 'item_code' => 'DIESEL', 'actual_or_target' => 'actual', 'report_status' => 'actual', 'reason' => 'Synthetic source mapping'];
        $id = $this->postJson('/map/proposals', $proposal)->assertCreated()->assertJsonPath('data.status', 'pending')->json('data.id');
        $this->actingAs($engineer)->getJson('/api/v1/source-mappings/proposals?facility_id='.$facility)->assertOk()->assertJsonPath('data.0.id', $id);
        $this->actingAs($steward);
        $this->postJson('/map/inspect', $context)->assertOk()->assertJsonPath('data.0.status', 'unresolved');
        $this->postJson('/map/proposals/'.$id.'/approve', ['reason' => 'self review prohibited'])->assertForbidden();
        $this->actingAs($engineer)->postJson('/map/proposals/'.$id.'/approve', ['reason' => 'Independent synthetic mapping review'])->assertOk()->assertJsonPath('data.status', 'approved');
        $this->actingAs($steward)->postJson('/map/inspect', $context)->assertOk()->assertJsonPath('data.0.status', 'mapped')->assertJsonPath('data.0.mapping.item_code', 'DIESEL');
        $context['as_of'] = '2000-01-01T00:00:00Z';
        $this->postJson('/map/inspect', $context)->assertOk()->assertJsonPath('data.0.status', 'unresolved');
        unset($context['as_of']);
        $revision = $this->postJson('/map/proposals', $proposal)->assertCreated()->assertJsonPath('data.version', 2)->json('data.id');
        $this->actingAs($engineer)->postJson('/map/proposals/'.$revision.'/approve', ['reason' => 'Independent reviewed source revision'])->assertOk();
        $this->assertDatabaseHas('source_mapping_versions', ['id' => $id, 'status' => 'superseded']);
        $this->assertDatabaseHas('source_mapping_versions', ['id' => $revision, 'status' => 'approved', 'supersedes_id' => $id]);
        $this->actingAs($steward)->postJson('/map/inspect', $context)->assertOk()->assertJsonPath('data.0.mapping.version', 2);
        $context['entries'][0]['granularity'] = 'daily';
        $this->postJson('/map/inspect', $context)->assertOk()->assertJsonPath('data.0.status', 'unresolved');
        $this->assertDatabaseHas('audit_logs', ['resource_type' => 'source_mapping_versions', 'resource_id' => $id, 'action' => 'approved']);
    }

    public function test_scope_and_unit_validation_fail_closed_and_old_mapping_versions_remain(): void
    {
        $this->seed(DatabaseSeeder::class);
        $steward = $this->user('DATA_STEWARD');
        $facility = DB::table('facilities')->value('id');
        $source = (string) Str::uuid();
        DB::table('source_files')->insert(['id' => $source, 'sha256' => hash('sha256', 'synthetic-bad-unit'), 'original_name' => 'synthetic.csv', 'storage_path' => 'synthetic-only', 'bytes' => 0, 'created_at' => now()]);
        $context = ['facility_id' => $facility, 'source_file_id' => $source, 'entries' => [['alias' => 'زيت', 'source_scope' => 'actual', 'unit_code' => 'unknown', 'granularity' => 'monthly', 'product_stage' => 'final_product', 'locator' => ['row' => 1, 'column' => 'X']]]];
        $this->actingAs($steward)->postJson('/map/inspect', $context)->assertUnprocessable();
        $this->assertDatabaseCount('source_mapping_queue', 0);
    }
}
