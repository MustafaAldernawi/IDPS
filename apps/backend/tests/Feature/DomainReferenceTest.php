<?php

namespace Tests\Feature;

use App\Http\Controllers\DomainReferenceController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class DomainReferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Route::post('/ref/{kind}', [DomainReferenceController::class, 'store']);
        Route::get('/ref/{kind}', [DomainReferenceController::class, 'index']);
        Route::post('/ref/{kind}/{id}/approve', [DomainReferenceController::class, 'approve']);
        Route::get('/sample/{id}/conformance', [DomainReferenceController::class, 'conformance']);
        Route::get('/downtime', [DomainReferenceController::class, 'downtime']);
    }

    private function actor(string $role): User
    {
        $u = User::factory()->create();
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $u->id, 'role_id' => DB::table('roles')->where('code', $role)->value('id')]);

        return $u;
    }

    private function setupData(): array
    {
        $this->seed();
        $facility = DB::table('facilities')->value('id');
        $unit = (string) Str::uuid();
        DB::table('refinery_units')->insert(['id' => $unit, 'facility_id' => $facility, 'code' => 'TEST', 'name_ar' => 'اختبار']);

        return [$this->actor('PRODUCTION_ENGINEER'), $this->actor('PROCESS_ENGINEER'), $facility, $unit];
    }

    public function test_unit_intervals_require_valid_utc_and_count_overlaps_once(): void
    {
        [$producer,$reviewer,$facility,$unit] = $this->setupData();
        $base = ['facility_id' => $facility, 'unit_id' => $unit, 'state' => 'trip', 'started_at' => '2027-01-01T01:00:00Z', 'ended_at' => '2027-01-01T05:00:00Z', 'source_reference' => 'synthetic'];
        $a = $this->actingAs($producer)->postJson('/ref/unit-status-intervals', $base)->assertCreated()->json('data.id');
        $b = $this->postJson('/ref/unit-status-intervals', array_merge($base, ['started_at' => '2027-01-01T04:00:00Z', 'ended_at' => '2027-01-01T07:00:00Z']))->assertCreated()->json('data.id');
        $this->actingAs($reviewer)->postJson('/ref/unit-status-intervals/'.$a.'/approve', ['reason' => 'independent review'])->assertOk();
        $this->postJson('/ref/unit-status-intervals/'.$b.'/approve', ['reason' => 'independent review'])->assertOk();
        $this->actingAs($producer);
        $this->postJson('/ref/unit-status-intervals', array_merge($base, ['started_at' => '2027-01-01T08:00:00Z']))->assertUnprocessable();
        $this->actingAs($reviewer)->getJson('/downtime?unit_id='.$unit.'&from=2027-01-01T00:00:00Z&as_of=2027-01-01T08:00:00Z')->assertOk()->assertJsonPath('data.seconds', 21600);
    }

    public function test_quality_limits_are_versioned_and_require_independent_approval(): void
    {
        [$producer,$engineer,$facility] = $this->setupData();
        $lab = $this->actor('LAB_ENGINEER');
        $data = ['facility_id' => $facility, 'parameter_code' => 'SULFUR', 'name_ar' => 'كبريت', 'unit_code' => 'percent', 'spec_min' => '0.000000', 'spec_max' => '2.000000', 'effective_from' => '2026-01-01', 'source_reference' => 'synthetic'];
        $id = $this->actingAs($lab)->postJson('/ref/quality-specifications', $data)->assertCreated()->assertJsonPath('data.status', 'pending')->json('data.id');
        $this->postJson('/ref/quality-specifications/'.$id.'/approve', ['reason' => 'synthetic check'])->assertForbidden();
        $this->actingAs($engineer)->postJson('/ref/quality-specifications/'.$id.'/approve', ['reason' => 'independent synthetic review'])->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertDatabaseHas('audit_logs', ['resource_type' => 'quality_specifications', 'resource_id' => $id]);
    }

    public function test_shift_calendar_has_explicit_times_and_scope(): void
    {
        [$producer,,$facility] = $this->setupData();
        $admin = $this->actor('SYSTEM_ADMIN');
        $shift = ['facility_id' => $facility, 'code' => 'A', 'name_ar' => 'أ', 'start_time' => '08:00', 'end_time' => '16:00', 'effective_from' => '2026-01-01'];
        $this->actingAs($producer)->postJson('/ref/shifts', $shift)->assertForbidden();
        $this->actingAs($admin)->postJson('/ref/shifts', $shift)->assertCreated();
        $this->postJson('/ref/operational-calendar', ['facility_id' => $facility, 'business_date' => '2026-01-01', 'shift_code' => 'A', 'starts_at' => '2027-01-01T06:00:00Z', 'ends_at' => '2027-01-01T14:00:00Z', 'source_reference' => 'synthetic'])->assertCreated();
    }
}
