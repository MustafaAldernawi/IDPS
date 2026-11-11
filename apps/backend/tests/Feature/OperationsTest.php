<?php

namespace Tests\Feature;

use App\Http\Controllers\OperationsController;
use App\Models\User;
use App\Services\EngineeringService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Route::get('/test-operations/{resource}', [OperationsController::class, 'index']);
        Route::post('/test-operations/{resource}', [OperationsController::class, 'store']);
        Route::post('/test-operations/{resource}/{id}/{action}', [OperationsController::class, 'transition']);
        Route::get('/test-comparison', [EngineeringService::class, 'comparison']);
        Route::get('/test-kpis', [EngineeringService::class, 'kpis']);
        Route::post('/test-definitions', [EngineeringService::class, 'storeDefinition']);
        Route::post('/test-definitions/{id}/approve', [EngineeringService::class, 'approveDefinition']);
        Route::post('/test-annotations', [EngineeringService::class, 'storeAnnotation']);
        Route::post('/test-annotations/{id}/approve', [EngineeringService::class, 'approveAnnotation']);
        Route::get('/test-reports/monthly', [EngineeringService::class, 'export']);
    }

    /**
     * A basic feature test example.
     */
    public function test_decimal_kpi_math_distinguishes_missing_zero_and_zero_denominator(): void
    {
        $this->assertSame('79.944687', EngineeringService::percentage('105798.000000', '132339.000000'));
        $this->assertSame('0.000000', EngineeringService::percentage('0', '100'));
        $this->assertNull(EngineeringService::percentage(null, '100'));
        $this->assertNull(EngineeringService::percentage('100', '0'));
        $this->assertSame('2.788834', EngineeringService::percentage('12051', '432116'));
    }

    public function test_downtime_union_clips_to_as_of_and_does_not_double_count_overlap(): void
    {
        $intervals = [
            ['started_at' => '2026-11-09T01:00:00Z', 'actual_end_at' => '2026-11-09T05:00:00Z'],
            ['started_at' => '2026-11-09T04:00:00Z', 'actual_end_at' => null],
        ];
        $this->assertSame(21600, EngineeringService::downtimeSeconds($intervals, '2026-11-09T00:00:00Z', '2026-11-09T07:00:00Z'));
        $this->assertSame(0, EngineeringService::downtimeSeconds([$intervals[0]], '2026-11-10T00:00:00Z', '2026-11-10T01:00:00Z'));
    }

    public function test_production_approval_requires_independent_reviewer_and_preserves_revision(): void
    {
        [$producer, $reviewer, $facility, $product] = $this->fixtures();
        $payload = $this->production($facility, $product);
        $id = $this->actingAs($producer)->postJson('/test-operations/production-records', $payload)->assertCreated()->json('data.id');
        $this->postJson("/test-operations/production-records/$id/approve", ['reason' => 'checked'])->assertForbidden();
        $this->postJson("/test-operations/production-records/$id/submit")->assertOk()->assertJsonPath('data.status', 'submitted');
        $this->actingAs($reviewer)->postJson("/test-operations/production-records/$id/approve", ['reason' => 'source independently checked'])->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertDatabaseHas('production_records', ['id' => $id, 'quantity' => '105798.123456']);
        $new = $this->actingAs($producer)->postJson('/test-operations/production-records', array_merge($payload, ['quantity' => '105799.123456', 'replaces_id' => $id, 'notes' => 'Corrected source revision']))->assertCreated()->json('data.id');
        $this->postJson("/test-operations/production-records/$new/submit")->assertOk();
        $this->actingAs($reviewer)->postJson("/test-operations/production-records/$new/approve", ['reason' => 'correction checked'])->assertOk();
        $this->assertDatabaseHas('production_records', ['id' => $id, 'quantity' => '105798.123456', 'status' => 'superseded']);
        $this->assertDatabaseHas('production_records', ['id' => $new, 'revision_no' => 2, 'status' => 'approved']);
    }

    public function test_author_can_revise_a_draft_and_resubmit_after_rejection(): void
    {
        [$producer, $reviewer, $facility, $product] = $this->fixtures();
        $endpoint = '/api/v1/production-records';
        $payload = $this->production($facility, $product);
        $draft = $this->actingAs($producer)->postJson($endpoint, $payload)->assertCreated()->json('data.id');
        $edited = $this->postJson($endpoint, $payload + ['replaces_id' => $draft, 'notes' => 'Correct draft'])->assertCreated()->json('data.id');
        $this->postJson("$endpoint/$draft/submit")->assertConflict();
        $this->postJson("$endpoint/$edited/submit")->assertOk();
        $this->actingAs($reviewer)->postJson("$endpoint/$edited/reject", ['reason' => 'Check the source'])->assertOk();
        $corrected = $this->actingAs($producer)->postJson($endpoint, $payload + ['replaces_id' => $edited, 'notes' => 'Source rechecked'])->assertCreated()->assertJsonPath('data.revision_no', 3)->json('data.id');
        $this->postJson("$endpoint/$corrected/submit")->assertOk();
        $this->actingAs($reviewer)->postJson("$endpoint/$corrected/approve", ['reason' => 'Independent verification'])->assertOk();
        $this->assertDatabaseHas('production_records', ['id' => $edited, 'status' => 'rejected']);
    }

    public function test_scope_filters_reads_and_rejects_cross_facility_writes(): void
    {
        [$producer, , $facility, $product] = $this->fixtures();
        $other = (string) Str::uuid();
        DB::table('facilities')->insert(['id' => $other, 'code' => 'OTHER', 'name_ar' => 'Synthetic other facility']);
        DB::table('user_roles')->where('user_id', $producer->id)->update(['facility_id' => $facility]);
        $this->actingAs($producer)->postJson('/test-operations/production-records', $this->production($other, $product))->assertForbidden();
        $this->postJson('/test-operations/production-records', $this->production($facility, $product))->assertCreated();
        $this->getJson('/test-operations/production-records')->assertOk()->assertJsonPath('meta.total', 1);
        $this->postJson('/test-operations/production-records', array_merge($this->production($facility, $product), ['quantity' => '-1']))->assertUnprocessable();
    }

    public function test_event_replay_eta_restart_and_history_are_transactional(): void
    {
        [$producer, $reviewer, $facility] = $this->fixtures();
        $unit = (string) Str::uuid();
        DB::table('refinery_units')->insert(['id' => $unit, 'facility_id' => $facility, 'code' => 'TESTUNIT', 'name_ar' => 'Synthetic test unit']);
        $payload = ['facility_id' => $facility, 'unit_id' => $unit, 'kind' => 'unplanned', 'event_type' => 'trip', 'started_at' => '2026-01-01T01:00:00Z', 'known_at' => '2026-01-01T01:01:00Z', 'expected_end_at' => null, 'source_reference' => 'synthetic:test', 'reason' => 'Synthetic test', 'idempotency_key' => 'synthetic-trip'];
        $id = $this->actingAs($producer)->postJson('/test-operations/events', $payload)->assertCreated()->json('data.id');
        $this->postJson('/test-operations/events', $payload)->assertOk()->assertJsonPath('data.id', $id);
        $this->postJson('/test-operations/events', array_merge($payload, ['reason' => 'changed']))->assertConflict();
        $this->postJson("/test-operations/events/$id/eta", ['revision_no' => 1, 'expected_end_at' => null, 'reason' => 'Still unknown'])->assertOk()->assertJsonPath('data.revision_no', 2);
        $this->postJson("/test-operations/events/$id/restart", ['revision_no' => 1, 'actual_end_at' => '2026-01-01T05:00:00Z', 'reason' => 'Restart'])->assertConflict();
        $this->postJson("/test-operations/events/$id/restart", ['revision_no' => 2, 'actual_end_at' => '2026-01-01T05:00:00Z', 'reason' => 'Restart'])->assertOk()->assertJsonPath('data.status', 'resolved');
        $this->actingAs($reviewer)->postJson("/test-operations/events/$id/approve", ['revision_no' => 3, 'reason' => 'Checked'])->assertOk()->assertJsonPath('data.status', 'closed');
        $this->assertSame(4, DB::table('event_revisions')->where('event_id', $id)->count());
        $this->assertSame(4, DB::table('operations_outbox')->where('event_type', 'like', 'event.%')->count());
    }

    public function test_engineering_definition_requires_independent_approval_before_loss_calculation(): void
    {
        [, $engineer, $facility] = $this->fixtures();
        $manager = User::factory()->create();
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $manager->id, 'role_id' => DB::table('roles')->where('code', 'OPERATIONS_MANAGER')->value('id')]);
        $definition = ['facility_id' => $facility, 'unit_id' => null, 'code' => 'REFINERY_LOSS_RATE', 'title' => 'Synthetic loss definition', 'formula_code' => 'unaccounted_loss_pct', 'loss_category_code' => 'UNACCOUNTED_LOSS', 'effective_from' => '2026-01-01', 'source_reference' => 'synthetic:engineering-signoff', 'reason' => 'Synthetic workflow test only'];
        $id = $this->actingAs($engineer)->postJson('/test-definitions', $definition)->assertCreated()->assertJsonPath('data.status', 'pending')->json('data.id');
        $this->postJson("/test-definitions/$id/approve", ['reason' => 'Own definition'])->assertForbidden();
        $this->actingAs($manager)->postJson("/test-definitions/$id/approve", ['reason' => 'Independent synthetic approval'])->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertDatabaseHas('kpi_definitions', ['id' => $id, 'approved_by' => $manager->id, 'version' => 1]);
        $this->assertDatabaseHas('audit_logs', ['resource_type' => 'kpi_definitions', 'resource_id' => $id, 'action' => 'approve']);
    }

    public function test_report_export_is_authorized_audited_and_csv_formula_safe(): void
    {
        [$producer, $engineer, $facility] = $this->fixtures();
        $this->actingAs($producer)->get('/test-reports/monthly?period_month=2026-01&facility_id='.$facility)->assertForbidden();
        $response = $this->actingAs($engineer)->get('/test-reports/monthly?period_month=2026-01&facility_id='.$facility.'&format=csv')->assertOk();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $response->getContent());
        $this->assertStringContainsString('period_month', $response->getContent());
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $engineer->id, 'action' => 'export', 'resource_type' => 'monthly_report']);
        $xlsx = $this->get('/test-reports/monthly?period_month=2026-01&facility_id='.$facility.'&format=xlsx')->assertOk();
        $this->assertSame('PK', substr($xlsx->getContent(), 0, 2));
        $pdf = $this->get('/test-reports/monthly?period_month=2026-01&facility_id='.$facility.'&format=pdf')->assertOk();
        $this->assertSame('%PDF', substr($pdf->getContent(), 0, 4));
        $this->assertSame("'=1+1", EngineeringService::safeCsvCell('=1+1'));
        $this->assertSame("' @HYPERLINK", EngineeringService::safeCsvCell(' @HYPERLINK'));
        $this->assertSame('0', EngineeringService::safeCsvCell('0'));
    }

    public function test_plans_approved_measurements_and_engineering_kpis_use_matching_scopes(): void
    {
        [$producer, $engineer, $facility, $product] = $this->fixtures();
        $planner = $this->withRole('PLANNING_ENGINEER');
        $manager = $this->withRole('OPERATIONS_MANAGER');
        $prod = $this->actingAs($producer)->postJson('/test-operations/production-records', array_merge($this->production($facility, $product), ['quantity' => '105798']))->assertCreated()->json('data.id');
        $this->postJson("/test-operations/production-records/$prod/submit")->assertOk();
        $this->actingAs($engineer)->postJson("/test-operations/production-records/$prod/approve", ['reason' => 'Synthetic independent review'])->assertOk();
        $planPayload = ['facility_id' => $facility, 'period_month' => '2026-01', 'title' => 'Synthetic target', 'unit_code' => 't', 'source_reference' => 'synthetic:plan', 'available_at' => '2025-12-31T00:00:00Z', 'targets' => [['product_id' => $product, 'quantity' => '132339', 'unit_code' => 't']]];
        $plan = $this->actingAs($planner)->postJson('/test-operations/plans', $planPayload)->assertCreated()->json('data.id');
        $this->postJson("/test-operations/plans/$plan/submit")->assertOk();
        $this->actingAs($manager)->postJson("/test-operations/plans/$plan/approve", ['reason' => 'Synthetic independent review'])->assertOk();
        $this->getJson('/test-comparison?facility_id='.$facility.'&period_month=2026-01')->assertOk()->assertJsonPath('data.0.achievement_pct', '79.944687')->assertJsonPath('data.0.target', '132339.000000');
        $this->getJson('/test-kpis?facility_id='.$facility.'&period_month=2026-01&product_id='.$product)->assertOk()->assertJsonPath('data.0.value', '79.944687')->assertJsonPath('data.1.status', 'definition_pending');
        $conflict = $this->actingAs($planner)->postJson('/test-operations/plans', $planPayload)->assertCreated()->json('data.id');
        $this->postJson("/test-operations/plans/$conflict/submit")->assertOk();
        $this->actingAs($manager)->postJson("/test-operations/plans/$conflict/approve", ['reason' => 'Synthetic duplicate conflict'])->assertConflict();
    }

    public function test_approved_energy_factors_enable_sec_and_annotations_keep_independent_review(): void
    {
        [$producer, $engineer, $facility] = $this->fixtures();
        $manager = $this->withRole('OPERATIONS_MANAGER');
        $base = ['facility_id' => $facility, 'unit_id' => null, 'period_month' => '2026-01', 'unit_code' => 't', 'source_reference' => 'synthetic:measurement', 'available_at' => '2026-02-01T00:00:00Z'];
        $crude = DB::table('crude_catalog')->value('id');
        foreach (['crude-feed-records' => ['crude_id' => $crude, 'quantity' => '100'], 'energy-records' => ['carrier_code' => 'FUEL_GAS', 'quantity' => '2']] as $resource => $fields) {
            $id = $this->actingAs($producer)->postJson('/test-operations/'.$resource, $base + $fields)->assertCreated()->json('data.id');
            $this->postJson("/test-operations/$resource/$id/submit")->assertOk();
            $this->actingAs($engineer)->postJson("/test-operations/$resource/$id/approve", ['reason' => 'Synthetic measurement reviewed'])->assertOk();
        }
        $def = $this->actingAs($engineer)->postJson('/test-definitions', ['facility_id' => $facility, 'code' => 'SPECIFIC_ENERGY_CONSUMPTION', 'title' => 'Synthetic energy factor', 'formula_code' => 'specific_energy_gj_per_t', 'effective_from' => '2026-01-01', 'source_reference' => 'synthetic:factor', 'reason' => 'Synthetic fixture not a refinery factor', 'energy_factors' => [['carrier_code' => 'FUEL_GAS', 'input_unit_code' => 't', 'gj_per_unit' => '50', 'basis' => 'LHV', 'source_reference' => 'synthetic:factor']]])->assertCreated()->json('data.id');
        $this->actingAs($manager)->postJson("/test-definitions/$def/approve", ['reason' => 'Synthetic test approval only'])->assertOk();
        $this->getJson('/test-kpis?facility_id='.$facility.'&period_month=2026-01')->assertOk()->assertJsonPath('data.1.value', '1.000000')->assertJsonPath('data.1.unit', 'GJ/t')->assertJsonPath('data.1.definition_id', $def)->assertJsonPath('data.3.value', '2.000000');
        $annotation = $this->actingAs($engineer)->postJson('/test-annotations', ['facility_id' => $facility, 'period_month' => '2026-01', 'title' => '=1+1', 'analysis' => 'Synthetic Arabic note: مراجعة تجريبية', 'source_reference' => 'synthetic:annotation'])->assertCreated()->json('data.id');
        $this->postJson("/test-annotations/$annotation/approve", ['reason' => 'Own annotation rejected'])->assertForbidden();
        $second = $this->withRole('PROCESS_ENGINEER');
        $this->actingAs($second)->postJson("/test-annotations/$annotation/approve", ['reason' => 'Independent synthetic annotation'])->assertOk();
        $csv = $this->get('/test-reports/monthly?period_month=2026-01&facility_id='.$facility.'&format=csv')->assertOk();
        $this->assertStringContainsString("'=1+1", $csv->getContent());
    }

    private function withRole(string $code): User
    {
        $user = User::factory()->create();
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role_id' => DB::table('roles')->where('code', $code)->value('id')]);

        return $user;
    }

    private function fixtures(): array
    {
        $this->seed(DatabaseSeeder::class);
        $producer = User::factory()->create();
        $reviewer = User::factory()->create();
        foreach ([[$producer, 'PRODUCTION_ENGINEER'], [$reviewer, 'PROCESS_ENGINEER']] as [$user, $code]) {
            DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role_id' => DB::table('roles')->where('code', $code)->value('id')]);
        }

        return [$producer, $reviewer, DB::table('facilities')->value('id'), DB::table('product_catalog')->where('code', 'DIESEL')->value('id')];
    }

    private function production(string $facility, string $product): array
    {
        return ['facility_id' => $facility, 'unit_id' => null, 'product_id' => $product, 'period_month' => '2026-01', 'quantity' => '105798.123456', 'unit_code' => 't', 'source_reference' => 'synthetic:test', 'available_at' => '2026-02-01T00:00:00Z'];
    }
}
