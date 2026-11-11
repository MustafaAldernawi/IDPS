<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_keeps_month_and_unit_scope_and_excludes_unapproved_trend_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $facility = DB::table('facilities')->value('id');
        $product = DB::table('product_catalog')->where('code', 'DIESEL')->value('id');
        $unit = (string) Str::uuid();
        $other = (string) Str::uuid();
        DB::table('refinery_units')->insert([
            ['id' => $unit, 'facility_id' => $facility, 'code' => 'UNIT-A', 'name_ar' => 'أ'],
            ['id' => $other, 'facility_id' => $facility, 'code' => 'UNIT-B', 'name_ar' => 'ب'],
        ]);
        $actor = User::factory()->create();
        DB::table('user_roles')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $actor->id,
            'role_id' => DB::table('roles')->where('code', 'PROCESS_ENGINEER')->value('id'),
            'facility_id' => $facility,
            'unit_id' => $unit,
        ]);
        $base = [
            'facility_id' => $facility,
            'product_id' => $product,
            'unit_code' => 't',
            'source_reference' => 'synthetic:dashboard',
            'available_at' => now(),
            'created_by' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        foreach ([
            [$unit, '2026-01', 'approved', '2'],
            [$unit, '2026-02', 'approved', '4'],
            [$unit, '2026-02', 'draft', '100'],
            [$other, '2026-02', 'approved', '300'],
        ] as [$recordUnit, $month, $status, $quantity]) {
            DB::table('production_records')->insert($base + [
                'id' => (string) Str::uuid(),
                'unit_id' => $recordUnit,
                'period_month' => $month,
                'status' => $status,
                'quantity' => $quantity,
            ]);
        }

        $this->actingAs($actor)->getJson('/api/v1/dashboard?'.http_build_query([
            'facility_id' => $facility,
            'unit_id' => $unit,
            'period_month' => '2026-02',
            'product_id' => $product,
        ]))->assertOk()
            ->assertJsonPath('data.counts.production_records.total', 3)
            ->assertJsonPath('data.period_counts.production_records.draft', 1)
            ->assertJsonPath('data.period_counts.production_records.approved', 1)
            ->assertJsonPath('data.comparisons.0.actual', '4.000000')
            ->assertJsonPath('data.trend.0.actual', '2.000000')
            ->assertJsonCount(2, 'data.trend');
    }
}
