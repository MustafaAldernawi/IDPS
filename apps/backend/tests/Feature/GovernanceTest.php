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
}
