<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DailyReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_sources_do_not_create_daily_coverage_and_null_is_not_zero(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();
        $reviewer = User::factory()->create();
        $role = DB::table('roles')->where('code', 'PROCESS_ENGINEER')->value('id');
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role_id' => $role]);
        $facility = DB::table('facilities')->value('id');
        $product = DB::table('product_catalog')->where('code', 'DIESEL')->value('id');
        foreach ([['2026-01-01', '0.000000', 'approved'], ['2026-01-02', null, 'approved'], ['2026-01-03', '3.000000', 'draft']] as [$date,$quantity,$status]) {
            DB::table('production_records')->insert(['id' => (string) Str::uuid(), 'facility_id' => $facility, 'product_id' => $product, 'period_month' => '2026-01', 'business_date' => $date, 'unit_code' => 't', 'quantity' => $quantity, 'status' => $status, 'source_reference' => 'synthetic fixture', 'created_by' => $user->id, 'approved_by' => $status === 'approved' ? $reviewer->id : null, 'approved_at' => $status === 'approved' ? now() : null, 'available_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('production_records')->insert(['id' => (string) Str::uuid(), 'facility_id' => $facility, 'product_id' => $product, 'period_month' => '2026-01', 'business_date' => '2026-01-04', 'shift_code' => 'A', 'unit_code' => 't', 'quantity' => '10', 'status' => 'approved', 'source_reference' => 'synthetic shift', 'created_by' => $user->id, 'approved_by' => $reviewer->id, 'approved_at' => now(), 'available_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->getJson('/api/v1/data/readiness/daily')->assertUnauthorized();
        $unprivileged = User::factory()->create();
        $this->actingAs($unprivileged)->getJson('/api/v1/data/readiness/daily?facility_id='.$facility.'&product_id='.$product.'&from=2026-01-01&to=2026-01-04')->assertForbidden();
        $this->actingAs($user)->getJson('/api/v1/data/readiness/daily?facility_id='.$facility.'&product_id='.$product.'&from=2026-01-01&to=2026-01-04')->assertOk()->assertJsonPath('data.expected_days', 4)->assertJsonPath('data.observed_days', 1)->assertJsonPath('data.zero_days', 1)->assertJsonPath('data.missing_days', 3)->assertJsonPath('data.status', 'DAILY_MODEL_NOT_READY');
    }
}
