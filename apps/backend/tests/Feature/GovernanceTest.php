<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Governance;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class GovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_denies_anonymous_and_issued_token_is_hashed_and_revoked(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $user = User::factory()->create(['password' => Hash::make('Secure-test-2026!')]);
        $response = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Secure-test-2026!'])->assertOk();
        $token = $response->json('token');
        $this->assertNotEmpty($token);
        $this->assertDatabaseMissing('api_tokens', ['token_hash' => $token]);
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('user.id', $user->id);
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_admin_manages_catalog_but_cannot_approve_production(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();
        $role = DB::table('roles')->where('code', 'SYSTEM_ADMIN')->value('id');
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role_id' => $role]);
        $this->assertTrue(Governance::hasPermission($user->id, 'users.manage'));
        $this->assertFalse(Governance::hasPermission($user->id, 'production.approve'));
        $this->actingAs($user)->postJson('/api/v1/catalogs/measurement-units', ['code' => 'TEST', 'dimension' => 'mass', 'display_symbol' => 't'])->assertCreated();
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $user->id, 'action' => 'created']);
        $this->actingAs($user)->patchJson('/api/v1/audit-logs', ['action' => 'changed'])->assertStatus(405);
    }

    public function test_disabled_user_and_bad_credentials_are_denied(): void
    {
        $user = User::factory()->create(['active' => false]);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'incorrect'])->assertUnauthorized();
    }

    public function test_scoped_catalogs_show_only_authorized_facilities_and_units(): void
    {
        $this->seed(DatabaseSeeder::class);
        $facility = DB::table('facilities')->value('id');
        $other = (string) Str::uuid();
        DB::table('facilities')->insert(['id' => $other, 'code' => 'OTHER', 'name_ar' => 'Other']);
        $unit = (string) Str::uuid(); $sibling = (string) Str::uuid(); $external = (string) Str::uuid();
        DB::table('refinery_units')->insert([
            ['id' => $unit, 'facility_id' => $facility, 'code' => 'A', 'name_ar' => 'A'],
            ['id' => $sibling, 'facility_id' => $facility, 'code' => 'B', 'name_ar' => 'B'],
            ['id' => $external, 'facility_id' => $other, 'code' => 'C', 'name_ar' => 'C'],
        ]);
        $user = User::factory()->create();
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role_id' => DB::table('roles')->where('code', 'PRODUCTION_ENGINEER')->value('id'), 'facility_id' => $facility, 'unit_id' => $unit]);
        $this->actingAs($user)->getJson('/api/v1/catalogs/facilities')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $facility);
        $this->getJson('/api/v1/catalogs/units')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $unit);
        $this->getJson('/api/v1/catalogs/measurement-units')->assertOk()->assertJsonFragment(['code' => 'kg']);
        DB::table('user_roles')->where('user_id', $user->id)->update(['unit_id' => null]);
        $this->getJson('/api/v1/catalogs/units')->assertOk()->assertJsonCount(2, 'data')->assertJsonMissing(['id' => $external]);
        DB::table('user_roles')->where('user_id', $user->id)->update(['facility_id' => null]);
        $this->getJson('/api/v1/catalogs/facilities')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/catalogs/units')->assertOk()->assertJsonCount(3, 'data');
        $this->actingAs(User::factory()->create())->getJson('/api/v1/catalogs/facilities')->assertForbidden();
    }

    public function test_user_active_status_toggle_and_role_revocation(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $adminRole = DB::table('roles')->where('code', 'SYSTEM_ADMIN')->value('id');
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $admin->id, 'role_id' => $adminRole]);

        $target = User::factory()->create(['active' => true]);
        $prodRole = DB::table('roles')->where('code', 'PRODUCTION_ENGINEER')->value('id');
        $assignmentId = (string) Str::uuid();
        DB::table('user_roles')->insert(['id' => $assignmentId, 'user_id' => $target->id, 'role_id' => $prodRole]);

        // Self status toggle is forbidden
        $this->actingAs($admin)->postJson("/api/v1/users/{$admin->id}/status", [
            'active' => false,
            'reason' => 'Administrative deactivation test',
        ])->assertForbidden();

        // Admin deactivates target user
        $this->actingAs($admin)->postJson("/api/v1/users/{$target->id}/status", [
            'active' => false,
            'reason' => 'User account suspended for audit',
        ])->assertOk()->assertJsonPath('data.active', false);
        $this->assertDatabaseHas('users', ['id' => $target->id, 'active' => false]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user_deactivated', 'resource_id' => $target->id]);

        // User list filtering
        $response = $this->getJson("/api/v1/users?q={$target->name}")->assertOk();
        $this->assertCount(1, $response->json('data.users.data'));
        $this->assertSame($target->id, $response->json('data.users.data.0.id'));

        // Revoke role assignment
        $this->deleteJson("/api/v1/users/{$target->id}/roles/{$assignmentId}", [
            'reason' => 'Role revoked per organizational change',
        ])->assertOk()->assertJsonPath('data.revoked', true);
        $this->assertDatabaseMissing('user_roles', ['id' => $assignmentId]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'role_revoked', 'resource_id' => $target->id]);

        // Reactivate target user
        $this->postJson("/api/v1/users/{$target->id}/status", [
            'active' => true,
            'reason' => 'User account restored after verification',
        ])->assertOk()->assertJsonPath('data.active', true);
        $this->assertDatabaseHas('users', ['id' => $target->id, 'active' => true]);
    }

    public function test_catalog_update_and_operations_history_expansion(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $adminRole = DB::table('roles')->where('code', 'SYSTEM_ADMIN')->value('id');
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $admin->id, 'role_id' => $adminRole]);

        $facility = DB::table('facilities')->first();
        $this->actingAs($admin)->putJson("/api/v1/catalogs/facilities/{$facility->id}", [
            'name_ar' => 'مصفاة الزاوية المحدثة',
        ])->assertOk()->assertJsonPath('data.name_ar', 'مصفاة الزاوية المحدثة');
        $this->assertDatabaseHas('facilities', ['id' => $facility->id, 'name_ar' => 'مصفاة الزاوية المحدثة']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'updated', 'resource_type' => 'facilities']);

        // Check history for energy-records
        $engineer = User::factory()->create();
        $engRole = DB::table('roles')->where('code', 'PROCESS_ENGINEER')->value('id');
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $engineer->id, 'role_id' => $engRole]);

        $energyId = (string) Str::uuid();
        DB::table('energy_consumptions')->insert([
            'id' => $energyId,
            'facility_id' => $facility->id,
            'period_month' => '2026-01',
            'carrier_code' => 'FUEL_GAS',
            'quantity' => '150.500000',
            'unit_code' => 't',
            'status' => 'draft',
            'revision_no' => 1,
            'source_reference' => 'Log sheet 101',
            'available_at' => now('UTC'),
            'created_by' => $engineer->id,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $this->actingAs($engineer)->getJson("/api/v1/energy-records/{$energyId}/history")
            ->assertOk()
            ->assertJsonPath('data.versions.0.id', $energyId)
            ->assertJsonPath('data.versions.0.revision_no', 1);
    }
}
