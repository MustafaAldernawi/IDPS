<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Versioned mapping metadata only: callers must not infer facts from unresolved entries. */
class SourceMappingDictionary
{
    public function requireScope(Request $request, string $permission, string $facility): void
    {
        abort_unless($request->user(), 401);
        $allowed = DB::table('user_roles as ur')->join('role_permissions as rp', 'rp.role_id', '=', 'ur.role_id')->join('permissions as p', 'p.id', '=', 'rp.permission_id')->where('ur.user_id', $request->user()->id)->where('p.code', $permission)->whereNull('ur.unit_id')->where(fn ($q) => $q->whereNull('ur.facility_id')->orWhere('ur.facility_id', $facility))->exists();
        abort_unless($allowed, 403);
    }

    public function contextHash(string $facility, array $entry): string
    {
        return hash('sha256', json_encode([$facility, trim($entry['alias']), trim($entry['source_scope']), $entry['unit_code'], $entry['granularity'], $entry['product_stage']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function inspect(Request $request, array $data): array
    {
        $this->requireScope($request, 'imports.write', $data['facility_id']);
        $results = [];
        foreach ($data['entries'] as $entry) {
            $hash = $this->contextHash($data['facility_id'], $entry);
            $asOf = $data['as_of'] ?? now('UTC')->format('Y-m-d H:i:s.u');
            $mapping = DB::table('source_mapping_versions')->where('facility_id', $data['facility_id'])->where('context_sha256', $hash)->whereNotNull('approved_at')->where('approved_at', '<=', $asOf)->orderByDesc('version')->first();
            if ($mapping) {
                $results[] = ['status' => 'mapped', 'mapping' => ['id' => $mapping->id, 'version' => $mapping->version, 'metric_code' => $mapping->metric_code, 'item_code' => $mapping->item_code, 'actual_or_target' => $mapping->actual_or_target, 'report_status' => $mapping->report_status, 'unit_code' => $entry['unit_code'], 'granularity' => $entry['granularity'], 'product_stage' => $entry['product_stage'], 'approved_at' => $mapping->approved_at]];

                continue;
            }
            ksort($entry['locator']);
            $locator = json_encode($entry['locator'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $locatorHash = hash('sha256', $locator);
            $id = (string) Str::uuid();
            DB::transaction(function () use ($request, $data, $entry, $hash, $locator, $locatorHash, $id) {
                $inserted = DB::table('source_mapping_queue')->insertOrIgnore(['id' => $id, 'facility_id' => $data['facility_id'], 'source_file_id' => $data['source_file_id'], 'created_by' => $request->user()->id, 'alias' => trim($entry['alias']), 'source_scope' => trim($entry['source_scope']), 'unit_code' => $entry['unit_code'], 'granularity' => $entry['granularity'], 'product_stage' => $entry['product_stage'], 'context_sha256' => $hash, 'locator_sha256' => $locatorHash, 'source_locator' => $locator, 'status' => 'unresolved', 'created_at' => now('UTC')]);
                if ($inserted) {
                    Governance::audit($request, 'unknown_alias', 'source_mapping_queue', $id, null, ['context_sha256' => $hash, 'source_file_id' => $data['source_file_id']]);
                }
            });
            $queued = DB::table('source_mapping_queue')->where(['facility_id' => $data['facility_id'], 'source_file_id' => $data['source_file_id'], 'context_sha256' => $hash, 'locator_sha256' => $locatorHash])->first();
            $results[] = ['status' => 'unresolved', 'queue_id' => $queued->id];
        }

        return $results;
    }

    public function propose(Request $request, array $data): array
    {
        return DB::transaction(function () use ($request, $data) {
            $queue = DB::table('source_mapping_queue')->where('id', $data['queue_id'])->lockForUpdate()->first();
            abort_unless($queue, 404);
            $this->requireScope($request, 'imports.write', $queue->facility_id);
            abort_unless(in_array($queue->status, ['unresolved', 'resolved'], true), 409, 'Mapping request unavailable');
            DB::table('facilities')->where('id', $queue->facility_id)->lockForUpdate()->first();
            $unit = DB::table('measurement_units')->where('code', $queue->unit_code)->first();
            abort_unless($unit && $unit->dimension === 'mass', 422, 'Mass unit required');
            if ($data['metric_code'] === 'product') {
                abort_unless(DB::table('product_catalog')->where('code', $data['item_code'])->exists(), 422, 'Unknown product');
                abort_unless(in_array($queue->product_stage, ['intermediate_stream', 'final_product', 'byproduct', 'fuel', 'waste'], true), 422, 'Explicit product stage required');
            }
            if ($data['metric_code'] === 'feed') {
                abort_unless(DB::table('crude_catalog')->where('code', $data['item_code'])->exists(), 422, 'Unknown crude');
                abort_unless($queue->product_stage === 'not_applicable', 422, 'Feed is not a product stage');
            }
            $version = ((int) DB::table('source_mapping_versions')->where('facility_id', $queue->facility_id)->where('context_sha256', $queue->context_sha256)->max('version')) + 1;
            $existing = DB::table('source_mapping_versions')->where('facility_id', $queue->facility_id)->where('context_sha256', $queue->context_sha256)->where('status', 'approved')->first();
            $id = (string) Str::uuid();
            $row = ['id' => $id, 'facility_id' => $queue->facility_id, 'queue_id' => $queue->id, 'context_sha256' => $queue->context_sha256, 'version' => $version, 'metric_code' => $data['metric_code'], 'item_code' => $data['item_code'], 'actual_or_target' => $data['actual_or_target'], 'report_status' => $data['report_status'], 'status' => 'pending', 'reason' => $data['reason'], 'supersedes_id' => $existing?->id, 'created_by' => $request->user()->id, 'created_at' => now('UTC')];
            DB::table('source_mapping_versions')->insert($row);
            Governance::audit($request, 'proposed', 'source_mapping_versions', $id, null, ['context_sha256' => $queue->context_sha256, 'version' => $version], $data['reason']);

            return $row;
        }, 3);
    }

    public function approve(Request $request, string $id, string $reason): array
    {
        return DB::transaction(function () use ($request, $id, $reason) {
            $row = DB::table('source_mapping_versions')->where('id', $id)->lockForUpdate()->first();
            abort_unless($row, 404);
            $this->requireScope($request, 'imports.approve', $row->facility_id);
            abort_if($row->created_by === $request->user()->id, 403, 'Independent reviewer required');
            abort_unless($row->status === 'pending', 409, 'Mapping is no longer pending');
            DB::table('facilities')->where('id', $row->facility_id)->lockForUpdate()->first();
            $current = DB::table('source_mapping_versions')->where('facility_id', $row->facility_id)->where('context_sha256', $row->context_sha256)->where('status', 'approved')->lockForUpdate()->first();
            abort_unless(($current?->id) === $row->supersedes_id, 409, 'Stale mapping version; propose again');
            if ($current) {
                DB::table('source_mapping_versions')->where('id', $current->id)->update(['status' => 'superseded']);
            }$changes = ['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now('UTC'), 'approval_reason' => $reason];
            DB::table('source_mapping_versions')->where('id', $id)->update($changes);
            DB::table('source_mapping_queue')->where('facility_id', $row->facility_id)->where('context_sha256', $row->context_sha256)->where('status', 'unresolved')->update(['status' => 'resolved']);
            Governance::audit($request, 'approved', 'source_mapping_versions', $id, ['status' => 'pending'], ['status' => 'approved', 'version' => $row->version], $reason);

            return ['id' => $id, 'status' => 'approved', 'version' => $row->version];
        }, 3);
    }
}
