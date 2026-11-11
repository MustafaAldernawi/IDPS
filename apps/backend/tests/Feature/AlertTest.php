<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_trip_alert_is_scoped_idempotent_and_acknowledged_with_audit(): void
    {
        $this->seed(DatabaseSeeder::class);
        $writer = User::factory()->create();
        $reader = User::factory()->create();
        $outsider = User::factory()->create();
        $facility = DB::table('facilities')->value('id');
        $unit = (string) Str::uuid();
        DB::table('refinery_units')->insert(['id' => $unit, 'facility_id' => $facility, 'code' => 'SYN', 'name_ar' => 'Synthetic']);
        foreach ([[$writer, 'PRODUCTION_ENGINEER'], [$reader, 'PROCESS_ENGINEER']] as [$actor,$role]) {
            DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $actor->id, 'role_id' => DB::table('roles')->where('code', $role)->value('id'), 'facility_id' => $facility]);
        }
        $event = ['facility_id' => $facility, 'unit_id' => $unit, 'kind' => 'unplanned', 'event_type' => 'trip', 'started_at' => '2026-01-01T01:00:00Z', 'known_at' => '2026-01-01T01:01:00Z', 'expected_end_at' => null, 'source_reference' => 'synthetic:test', 'reason' => 'Synthetic test', 'idempotency_key' => 'alert-test-trip'];
        $this->actingAs($writer)->postJson('/api/v1/events', $event)->assertCreated();
        $this->postJson('/api/v1/events', $event)->assertOk();
        $this->actingAs($reader)->getJson('/api/v1/alerts')->assertOk()->assertJsonCount(1, 'data');
        $alert = DB::table('alerts')->first();
        $this->actingAs($outsider)->getJson('/api/v1/alerts')->assertForbidden();
        $this->actingAs($reader)->postJson('/api/v1/alerts/'.$alert->id.'/acknowledge', ['reason' => 'Reviewed synthetic event'])->assertOk()->assertJsonPath('data.status', 'acknowledged');
        $this->assertDatabaseHas('audit_logs', ['resource_type' => 'alerts', 'resource_id' => $alert->id, 'action' => 'acknowledged']);
        $this->assertDatabaseCount('alerts', 1);
    }
}
