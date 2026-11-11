<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ImportMappingTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role): User
    {
        $u = User::factory()->create();
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $u->id, 'role_id' => DB::table('roles')->where('code', $role)->value('id')]);

        return $u;
    }

    public function test_unknown_header_is_not_imported_until_mapping_is_reviewed(): void
    {
        $this->seed(DatabaseSeeder::class);
        $writer = $this->actor('DATA_STEWARD');
        $reviewer = $this->actor('PROCESS_ENGINEER');
        $facility = DB::table('facilities')->value('id');
        $csv = "month,زيت ديزل\n2026-01,12.25\n";
        $context = [['alias' => 'زيت ديزل', 'source_scope' => 'actual', 'unit_code' => 't', 'granularity' => 'monthly', 'product_stage' => 'intermediate_stream', 'locator' => ['row' => 1, 'column' => 'زيت ديزل']]];
        $upload = fn () => UploadedFile::fake()->createWithContent('synthetic.csv', $csv);
        $this->actingAs($writer)->postJson('/api/v1/imports', ['file' => $upload(), 'facility_id' => $facility, 'mapping_context' => $context])->assertStatus(409);
        $this->assertDatabaseCount('monthly_observations', 0);
        $queue = DB::table('source_mapping_queue')->value('id');
        $this->assertNotNull($queue);
        $proposal = $this->postJson('/api/v1/source-mappings/proposals', ['queue_id' => $queue, 'metric_code' => 'product', 'item_code' => 'DIESEL', 'actual_or_target' => 'actual', 'report_status' => 'actual', 'reason' => 'Synthetic mapping proposal'])->assertCreated()->json('data.id');
        $this->actingAs($reviewer)->postJson('/api/v1/source-mappings/proposals/'.$proposal.'/approve', ['reason' => 'Independent mapping reviewed'])->assertOk();
        $this->actingAs($writer)->postJson('/api/v1/imports', ['file' => $upload(), 'facility_id' => $facility, 'mapping_context' => $context])->assertCreated();
        $this->assertDatabaseHas('monthly_observations', ['item_code' => 'DIESEL', 'quantity' => '12.250000', 'status' => 'staged']);
        $row = DB::table('import_rows')->first();
        $raw = json_decode($row->raw_json, true);
        $this->assertSame($proposal, $raw['mapping_version_id']);
    }

    public function test_explicit_flat_xlsx_sheet_uses_reviewed_mapping_and_cell_lineage(): void
    {
        $this->seed(DatabaseSeeder::class);
        $writer = $this->actor('DATA_STEWARD');
        $reviewer = $this->actor('PROCESS_ENGINEER');
        $facility = DB::table('facilities')->value('id');
        $file = tempnam(sys_get_temp_dir(), 'idps-test-');
        try {
            $script = 'import openpyxl,sys; w=openpyxl.Workbook(); s=w.active; s.title="Sheet1"; s.append(["month","DieselAlias"]); s.append(["2026-01",12.25]); w.save(sys.argv[1])';
            $python = env('IDPS_PYTHON') ?: (file_exists('C:\\Python314\\python.exe') ? 'C:\\Python314\\python.exe' : 'python');
            $process = new Process([$python, '-c', $script, $file]);
            $process->mustRun();
            $upload = fn () => new UploadedFile($file, 'synthetic.xlsx', null, null, true);
            $context = [['alias' => 'DieselAlias', 'source_scope' => 'Sheet1', 'unit_code' => 't', 'granularity' => 'monthly', 'product_stage' => 'intermediate_stream', 'locator' => ['sheet' => 'Sheet1', 'cell' => 'B1']]];
            $this->actingAs($writer)->postJson('/api/v1/imports', ['file' => $upload(), 'facility_id' => $facility, 'flat_sheet' => 'Sheet1', 'mapping_context' => $context])->assertStatus(409);
            $queue = DB::table('source_mapping_queue')->value('id');
            $proposal = $this->postJson('/api/v1/source-mappings/proposals', ['queue_id' => $queue, 'metric_code' => 'product', 'item_code' => 'DIESEL', 'actual_or_target' => 'actual', 'report_status' => 'actual', 'reason' => 'Synthetic mapping proposal'])->assertCreated()->json('data.id');
            $this->actingAs($reviewer)->postJson('/api/v1/source-mappings/proposals/'.$proposal.'/approve', ['reason' => 'Independent mapping reviewed'])->assertOk();
            $this->actingAs($writer)->postJson('/api/v1/imports', ['file' => $upload(), 'facility_id' => $facility, 'flat_sheet' => 'Sheet1', 'mapping_context' => $context])->assertCreated();
            $this->postJson('/api/v1/imports', ['file' => $upload(), 'facility_id' => $facility, 'flat_sheet' => 'Sheet1', 'mapping_context' => $context])->assertCreated()->assertJsonPath('data.idempotent', true);
            $this->assertDatabaseCount('monthly_observations', 1);
            $raw = json_decode(DB::table('import_rows')->first()->raw_json, true);
            $this->assertSame('Sheet1', $raw['locator']['sheet']);
            $this->assertSame('B2', $raw['locator']['cell']);
            $this->assertSame($proposal, $raw['mapping_version_id']);
        } finally {
            unlink($file);
        }
    }
}
