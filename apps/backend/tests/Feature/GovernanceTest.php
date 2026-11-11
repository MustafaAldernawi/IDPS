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
}
