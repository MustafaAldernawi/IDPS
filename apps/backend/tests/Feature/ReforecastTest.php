<?php

namespace Tests\Feature;

use App\Jobs\ReforecastEvent;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReforecastTest extends TestCase
{
    use RefreshDatabase;

    private function event(): array
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();
        $facility = DB::table('facilities')->value('id');
        $unit = (string) Str::uuid();
        DB::table('refinery_units')->insert(['id' => $unit, 'facility_id' => $facility, 'code' => 'SYNTHETIC_UNIT', 'name_ar' => 'وحدة اختبار اصطناعية']);
        $event = ['id' => (string) Str::uuid(), 'facility_id' => $facility, 'unit_id' => $unit, 'kind' => 'unplanned', 'event_type' => 'trip', 'status' => 'reported', 'idempotency_key' => (string) Str::uuid(), 'payload_hash' => str_repeat('a', 64), 'started_at' => '2026-11-08 10:00:00.000000', 'known_at' => '2026-11-08 10:01:00.000000', 'expected_end_at' => null, 'actual_end_at' => null, 'reason' => 'Synthetic test incident', 'source_reference' => 'SYNTHETIC ONLY', 'revision_no' => 1, 'created_by' => $user->id, 'created_at' => '2026-11-08 10:01:00', 'updated_at' => '2026-11-08 10:01:00'];
        DB::table('operational_events')->insert($event);
        $outbox = $this->revision($event);

        return [$event, $outbox];
    }

    private function revision(array $event): string
    {
        DB::table('event_revisions')->insert(['id' => (string) Str::uuid(), 'event_id' => $event['id'], 'revision_no' => $event['revision_no'], 'snapshot' => json_encode($event), 'reason' => 'Synthetic test revision', 'actor_id' => $event['created_by'], 'created_at' => $event['known_at']]);
        $id = (string) Str::uuid();
        DB::table('operations_outbox')->insert(['id' => $id, 'event_key' => 'event:'.$event['id'].':'.$event['revision_no'], 'event_type' => $event['revision_no'] === 1 ? 'event.reported' : 'event.eta', 'payload' => json_encode(['event_id' => $event['id'], 'revision_no' => $event['revision_no'], 'known_at' => $event['known_at']]), 'created_at' => $event['known_at']]);

        return $id;
    }

    public function test_unknown_eta_replay_preserves_old_runs_and_never_invents_quantities(): void
    {
        Queue::fake();
        Http::fake();
        [$event,$outbox] = $this->event();
        (new ReforecastEvent($outbox))->handle();
        $count = DB::table('forecast_runs')->count();
        $this->assertSame(6, $count);
        $this->assertDatabaseCount('forecast_scenarios', 18);
        $original = DB::table('forecast_runs')->orderBy('id')->get()->toJson();
        (new ReforecastEvent($outbox))->handle();
        $this->assertSame($count, DB::table('forecast_runs')->count());
        $this->assertSame($original, DB::table('forecast_runs')->orderBy('id')->get()->toJson());
        $this->assertSame(0, DB::table('forecast_scenarios')->whereNotNull('prediction')->count());
        $this->assertSame(0, DB::table('forecast_runs')->whereNotNull('prediction')->count());
        Http::assertNothingSent();
        $this->assertNotNull(DB::table('operations_outbox')->where('id', $outbox)->value('processed_at'));
    }

    public function test_delayed_outbox_uses_original_revision_and_new_eta_links_comparable_parent(): void
    {
        Queue::fake();
        Http::fake();
        [$event,$first] = $this->event();
        $changed = $event;
        $changed['revision_no'] = 2;
        $changed['known_at'] = '2026-11-09 10:01:00';
        $changed['expected_end_at'] = '2026-11-10 14:00:00';
        $second = $this->revision($changed);
        DB::table('operational_events')->where('id', $event['id'])->update(['revision_no' => 2, 'known_at' => $changed['known_at'], 'expected_end_at' => $changed['expected_end_at']]);
        (new ReforecastEvent($first))->handle();
        $firstRun = DB::table('forecast_runs')->where('product_code', 'DIESEL')->where('event_revision', 1)->first();
        $firstResult = json_decode($firstRun->result_json, true);
        $this->assertSame('unknown', $firstResult['event']['eta_status']);
        (new ReforecastEvent($second))->handle();
        $secondRun = DB::table('forecast_runs')->where('product_code', 'DIESEL')->where('event_revision', 2)->first();
        $this->assertSame($firstRun->id, $secondRun->parent_run_id);
        $this->assertSame('reported', json_decode($secondRun->result_json, true)['event']['eta_status']);
        $this->assertSame($firstRun->result_json, DB::table('forecast_runs')->where('id', $firstRun->id)->value('result_json'));
        $this->assertDatabaseCount('forecast_scenarios',24);
    }
}
