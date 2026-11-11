<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportTranscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_copy_requires_independent_facility_scoped_review(): void
    {
        $this->seed(DatabaseSeeder::class);
        $facility = DB::table('facilities')->value('id');
        $otherFacility = (string) Str::uuid();
        DB::table('facilities')->insert(['id' => $otherFacility, 'code' => 'OTHER', 'name_ar' => 'Other']);
        $author = $this->actor($facility, ['DATA_STEWARD', 'PROCESS_ENGINEER']);
        $reviewer = $this->actor($facility, ['PROCESS_ENGINEER']);
        $outsider = $this->actor($otherFacility, ['PROCESS_ENGINEER']);
        $pdf = "%PDF-1.4\n% synthetic fixture ".Str::uuid()."\n%%EOF\n";
        $hash = hash('sha256', $pdf);
        $endpoint = '/api/v1/report-transcriptions';
        $payload = fn () => [
            'facility_id' => $facility, 'source' => UploadedFile::fake()->createWithContent('synthetic.pdf', $pdf),
            'page' => 2, 'locator' => 'Table 1, row 2', 'source_text' => 'Synthetic transcription',
        ];
        try {
            $id = $this->actingAs($author)->post($endpoint, $payload(), ['Accept' => 'application/json'])
                ->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.sha256', $hash)
                ->assertJsonMissingPath('data.storage_path')->json('data.id');
            $this->post($endpoint, $payload(), ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.id', $id);
            $decision = ['decision' => 'verified', 'reason' => 'Compared to page two'];
            $this->postJson("$endpoint/$id/review", $decision)->assertForbidden();
            $this->actingAs($outsider)->getJson("$endpoint?facility_id=$facility")->assertForbidden();
            $this->getJson("$endpoint/$id/source")->assertForbidden();
            $this->postJson("$endpoint/$id/review", $decision)->assertForbidden();
            $this->actingAs($reviewer)->get("$endpoint/$id/source")->assertDownload("source-$hash.pdf");
            $this->postJson("$endpoint/$id/review", $decision)->assertOk()
                ->assertJsonPath('data.status', 'verified')->assertJsonPath('data.page', 2)
                ->assertJsonPath('data.reviewed_by', $reviewer->id);
            $this->postJson("$endpoint/$id/review", $decision)->assertConflict();
            $this->assertDatabaseHas('audit_logs', ['resource_id' => $id, 'action' => 'transcription.verified', 'reason' => $decision['reason']]);
            $this->assertDatabaseCount('monthly_observations', 0);
        } finally {
            $path = storage_path("app/private/sources/$hash.pdf");
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function actor(string $facility, array $roles): User
    {
        $user = User::factory()->create();
        foreach ($roles as $role) {
            DB::table('user_roles')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'facility_id' => $facility,
                'role_id' => DB::table('roles')->where('code', $role)->value('id'),
            ]);
        }

        return $user;
    }
}
