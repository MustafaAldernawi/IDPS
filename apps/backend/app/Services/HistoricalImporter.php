<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class HistoricalImporter
{
    public function stage(Request $request, string $path, string $originalName, string $facility, ?int $year = null): array
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        abort_unless(in_array($extension, ['csv', 'xlsx']), 422, 'CSV/XLSX only');
        $sha = hash_file('sha256', $path);
        $destination = storage_path('app/private/sources/'.$sha.'.'.$extension);
        if (! is_dir(dirname($destination))) {
            mkdir(dirname($destination), 0700, true);
        }if (! file_exists($destination)) {
            copy($path, $destination);
        }
        abort_unless(hash_file('sha256', $destination) === $sha, 409, 'Source fingerprint mismatch');
        $command = [env('IDPS_PYTHON', 'python'), '-B', base_path('../../scripts/parse_monthly.py'), $destination];
        if ($year !== null) {
            $command[] = (string) $year;
        }
        $process = new Process($command, base_path('../..'), timeout: 45);
        $process->run();
        if (! $process->isSuccessful()) {
            throw ValidationException::withMessages(['file' => 'Monthly parsing failed: '.(json_decode($process->getOutput(), true)['error'] ?? 'unsupported or invalid file')]);
        }
        $facts = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        return DB::transaction(function () use ($request, $facts, $sha, $destination, $originalName, $path, $facility, $year) {
            DB::table('source_files')->insertOrIgnore(['id' => (string) Str::uuid(), 'sha256' => $sha, 'original_name' => basename($originalName), 'storage_path' => $destination, 'bytes' => filesize($path), 'created_at' => now()]);
            $source = DB::table('source_files')->where('sha256', $sha)->lockForUpdate()->first();
            $existing = DB::table('import_batches')->where('source_file_id', $source->id)->where('facility_id', $facility)->where('mapping_version', 'monthly-v1')->first();
            if ($existing) {
                return ['id' => $existing->id, 'idempotent' => true, 'status' => $existing->status];
            }
            $batch = (string) Str::uuid();
            DB::table('import_batches')->insert(['id' => $batch, 'source_file_id' => $source->id, 'facility_id' => $facility, 'created_by' => $request->user()->id, 'mapping_version' => 'monthly-v1', 'status' => 'staged', 'created_at' => now()]);
            foreach ($facts as $fact) {
                $row = (string) Str::uuid();
                $id = (string) Str::uuid();
                DB::table('import_rows')->insert(['id' => $row, 'batch_id' => $batch, 'source_locator' => json_encode($fact['locator']), 'raw_json' => json_encode($fact), 'qa_flags' => json_encode($fact['qa_flags'])]);
                $data = collect($fact)->only(['period_month', 'metric_code', 'item_code', 'actual_or_target', 'report_status', 'quantity', 'unit_code'])->all();
                DB::table('monthly_observations')->insert($data + ['id' => $id, 'facility_id' => $facility, 'source_file_id' => $source->id, 'import_row_id' => $row, 'created_by' => $request->user()->id, 'status' => count($fact['qa_flags']) ? 'flagged' : 'staged']);
                foreach ($fact['qa_flags'] as $flag) {
                    DB::table('reconciliation_cases')->insert(['id' => (string) Str::uuid(), 'batch_id' => $batch, 'observation_id' => $id, 'rule_code' => mb_substr($flag, 0, 100), 'details' => 'Review source locator and original value before approval', 'status' => 'open']);
                }
            }
            if ($year === 2023) {
                foreach (['2023_excel_vs_semi_actual_conflict', '2023_annual_fuel_total_requires_review'] as $rule) {
                    DB::table('reconciliation_cases')->insert(['id' => (string) Str::uuid(), 'batch_id' => $batch, 'observation_id' => null, 'rule_code' => $rule, 'details' => 'Known source conflict documented in docs/25; retain both sources and require process engineering decision', 'status' => 'open']);
                }
            }
            Governance::audit($request, 'staged', 'import_batches', $batch, null, ['source_sha256' => $sha, 'facts' => count($facts)]);

            return ['id' => $batch, 'idempotent' => false, 'status' => 'staged', 'observations' => count($facts)];
        }, 3);
    }
}
