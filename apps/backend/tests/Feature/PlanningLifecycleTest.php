<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlanningLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_revisions_require_independent_approval_and_preserve_the_active_target(): void
    {
        $this->seed(DatabaseSeeder::class);
        $planner = $this->userWithRole('PLANNING_ENGINEER');
        $reviewer = $this->userWithRole('OPERATIONS_MANAGER');
        $facility = DB::table('facilities')->value('id');
        $product = DB::table('product_catalog')->where('code', 'DIESEL')->value('id');
        $endpoint = '/api/v1/plans';
        $scope = $facility.':FACILITY:2026-01';
        $payload = [
            'facility_id' => $facility,
            'period_month' => '2026-01',
            'title' => 'Synthetic diesel plan',
            'source_reference' => 'synthetic:planning-lifecycle',
            'unit_code' => 't',
            'available_at' => '2025-12-31T00:00:00Z',
            'targets' => [['product_id' => $product, 'quantity' => '100', 'unit_code' => 't']],
        ];

        $first = $this->actingAs($planner)->postJson($endpoint, $payload)->assertCreated()->json('data.id');
        $this->postJson("$endpoint/$first/submit")->assertOk()->assertJsonPath('data.status', 'submitted');
        $this->actingAs($reviewer)->postJson("$endpoint/$first/approve", ['reason' => 'Original independently reviewed'])->assertOk();

        $draft = $this->actingAs($planner)->postJson($endpoint, $payload + [
            'replaces_id' => $first, 'notes' => 'Prepare replacement target',
        ])->assertCreated()->json('data.id');
        $payload['targets'][0]['quantity'] = '110';
        $edited = $this->postJson($endpoint, $payload + [
            'replaces_id' => $draft, 'notes' => 'Edit the draft target',
        ])->assertCreated()->assertJsonPath('data.revision_no', 3)->json('data.id');
        $this->postJson("$endpoint/$draft/submit")->assertConflict();
        $this->postJson("$endpoint/$edited/submit")->assertOk();
        $this->postJson("$endpoint/$edited/approve", ['reason' => 'Unauthorized review'])->assertForbidden();
        $this->actingAs($reviewer)->postJson("$endpoint/$edited/reject", ['reason' => 'Target source needs verification'])
            ->assertOk()->assertJsonPath('data.status', 'rejected');

        $payload['targets'][0]['quantity'] = '120';
        $corrected = $this->actingAs($planner)->postJson($endpoint, $payload + [
            'replaces_id' => $edited, 'notes' => 'Target source verified',
        ])->assertCreated()->assertJsonPath('data.revision_no', 4)->json('data.id');
        $this->postJson("$endpoint/$corrected/submit")->assertOk();
        $this->assertDatabaseHas('production_target_plans', ['id' => $first, 'active_scope' => $scope, 'status' => 'approved']);
        $this->assertDatabaseHas('production_target_plans', ['id' => $corrected, 'active_scope' => null, 'status' => 'submitted']);
        $this->actingAs($reviewer)->postJson("$endpoint/$corrected/approve", ['reason' => 'Replacement independently verified'])
            ->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertDatabaseHas('production_target_plans', ['id' => $first, 'active_scope' => null, 'status' => 'superseded']);
        $this->assertDatabaseHas('production_target_plans', ['id' => $corrected, 'active_scope' => $scope, 'status' => 'approved', 'approved_by' => $reviewer->id]);
        $this->assertDatabaseHas('production_target_plans', ['id' => $edited, 'status' => 'rejected']);
        $this->assertDatabaseHas('production_targets', ['plan_id' => $first, 'quantity' => '100.000000']);
        $this->assertDatabaseHas('production_targets', ['plan_id' => $corrected, 'quantity' => '120.000000']);
        $this->assertDatabaseHas('audit_logs', ['resource_id' => $edited, 'action' => 'reject', 'actor_id' => $reviewer->id, 'reason' => 'Target source needs verification']);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        DB::table('user_roles')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'role_id' => DB::table('roles')->where('code', $role)->value('id'),
        ]);

        return $user;
    }
}
