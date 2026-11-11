<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role): User
    {
        $user = User::factory()->create();
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role_id' => DB::table('roles')->where('code', $role)->value('id')]);

        return $user;
    }

    public function test_import_replay_approval_and_source_lineage_are_preserved(): void
    {
        $this->seed();
        $writer = $this->actor('DATA_STEWARD');
        $reviewer = $this->actor('PROCESS_ENGINEER');
        $facility = DB::table('facilities')->value('id');
        $csv = "month,period_granularity,product_diesel_actual_t,product_diesel_target_t\n2026-01,month,123.123456,200\n";
        $response = $this->actingAs($writer)->postJson('/api/v1/imports', ['file' => UploadedFile::fake()->createWithContent('monthly.csv', $csv), 'facility_id' => $facility])->assertCreated();
        $id = $response->json('data.id');
        $this->assertDatabaseCount('monthly_observations', 2);
        $this->assertDatabaseHas('monthly_observations', ['quantity' => '123.123456', 'status' => 'staged', 'available_at' => null]);
        $this->actingAs($writer)->postJson('/api/v1/imports', ['file' => UploadedFile::fake()->createWithContent('monthly.csv', $csv), 'facility_id' => $facility])->assertCreated()->assertJsonPath('data.idempotent', true);
        $this->assertDatabaseCount('monthly_observations', 2);
        $this->actingAs($writer)->postJson('/api/v1/imports/'.$id.'/approve', ['reason' => 'Independent engineering review', 'lock_version' => 1])->assertForbidden();
        $this->actingAs($reviewer)->getJson('/api/v1/imports/'.$id.'/preview')->assertOk();
        $this->actingAs($reviewer)->postJson('/api/v1/imports/'.$id.'/approve', ['reason' => 'Synthetic test reviewed independently', 'lock_version' => 1])->assertOk()->assertJsonPath('data.approved_count', 2);
        $this->assertDatabaseHas('monthly_observations', ['status' => 'approved', 'approved_by' => $reviewer->id, 'available_at' => null]);
        $this->actingAs($reviewer)->postJson('/api/v1/imports/'.$id.'/approve', ['reason' => 'Stale review must be rejected', 'lock_version' => 1])->assertConflict();
    }

    public function test_rejecting_remaining_observations_preserves_previously_approved_values(): void
    {
        $this->seed();
        $writer = $this->actor('DATA_STEWARD');
        $reviewer = $this->actor('PROCESS_ENGINEER');
        $csv = "month,period_granularity,product_diesel_actual_t,product_diesel_target_t\n2026-01,month,123,200\n";
        $id = $this->actingAs($writer)->postJson('/api/v1/imports', [
            'file' => UploadedFile::fake()->createWithContent('monthly.csv', $csv),
            'facility_id' => DB::table('facilities')->value('id'),
        ])->assertCreated()->json('data.id');
        $actual = DB::table('monthly_observations')->where('actual_or_target', 'actual')->value('id');
        $reason = 'Target differs from source document';
        $this->postJson("/api/v1/imports/$id/reject", ['reason' => $reason, 'lock_version' => 1])->assertForbidden();
        $this->actingAs($reviewer)->postJson("/api/v1/imports/$id/approve", [
            'reason' => 'Verified the actual value', 'lock_version' => 1, 'observation_ids' => [$actual],
        ])->assertOk();
        $this->postJson("/api/v1/imports/$id/reject", ['reason' => $reason, 'lock_version' => 1])->assertConflict();
        $this->postJson("/api/v1/imports/$id/reject", ['reason' => $reason, 'lock_version' => 2])
            ->assertOk()->assertJsonPath('data.rejected_count', 1)->assertJsonPath('data.lock_version', 3);
        $this->assertDatabaseHas('monthly_observations', ['id' => $actual, 'status' => 'approved', 'quantity' => '123.000000']);
        $this->assertDatabaseHas('monthly_observations', ['actual_or_target' => 'target', 'status' => 'rejected', 'quantity' => '200.000000']);
        $this->assertDatabaseHas('approval_actions', ['resource_id' => $id, 'action' => 'rejected', 'actor_id' => $reviewer->id, 'reason' => $reason]);
        $this->getJson("/api/v1/imports/$id/preview")->assertOk()->assertJsonFragment(['action' => 'rejected', 'reason' => $reason]);
    }

    public function test_daily_disguised_as_monthly_and_missing_quantity_never_approved(): void
    {
        $this->seed();
        $writer = $this->actor('DATA_STEWARD');
        $facility = DB::table('facilities')->value('id');
        $this->actingAs($writer)->postJson('/api/v1/imports', ['file' => UploadedFile::fake()->createWithContent('daily.csv', "month,period_granularity,product_diesel_actual_t\n2026-01,day,1\n"), 'facility_id' => $facility])->assertUnprocessable();
        $this->assertDatabaseCount('monthly_observations',0);
    }
}
