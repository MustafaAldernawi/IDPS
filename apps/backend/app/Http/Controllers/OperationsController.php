<?php

namespace App\Http\Controllers;

use App\Services\Governance;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OperationsController extends Controller
{
    private const RESOURCES = [
        'production-records' => ['production_records', 'production.write', 'production.approve'],
        'crude-feed-records' => ['crude_feed_records', 'production.write', 'production.approve'],
        'energy-records' => ['energy_consumptions', 'production.write', 'production.approve'],
        'loss-records' => ['loss_records', 'production.write', 'production.approve'],
        'chemical-records' => ['chemical_consumptions', 'production.write', 'production.approve'],
        'quality-samples' => ['laboratory_samples', 'quality.write', 'quality.approve'],
        'blend-batches' => ['blend_batches', 'quality.write', 'quality.approve'],
        'plans' => ['production_target_plans', 'planning.write', 'planning.approve'],
        'events' => ['operational_events', 'events.write', 'events.approve'],
    ];

    public function index(Request $request, string $resource): JsonResponse
    {
        [$table, $write] = $this->resource($resource);
        abort_unless($request->user(), 401);
        $permission = $this->hasAnyPermission($request, 'engineering.read') ? 'engineering.read' : $write;
        abort_unless($this->hasAnyPermission($request, $permission), 403);
        $query = self::scoped(DB::table($table), $request, $permission);
        foreach (['facility_id', 'unit_id', 'status', 'period_month'] as $filter) {
            if ($request->filled($filter) && ! ($resource === 'events' && $filter === 'period_month')) {
                $query->where($filter, $request->input($filter));
            }
        }
        $search = $request->validate(['q' => 'nullable|string|max:100'])['q'] ?? null;
        if ($search !== null && $resource !== 'events') {
            $query->where(function (Builder $filter) use ($search, $resource): void {
                $filter->where('id', $search)->orWhereLike('source_reference', '%'.$search.'%');
                if ($resource === 'plans') {
                    $filter->orWhereLike('title', '%'.$search.'%');
                }
            });
        }
        $result = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(min(100, max(1, $request->integer('per_page', 25))));
        $items = array_map(fn ($row) => $this->decorate($resource, (array) $row), $result->items());

        return response()->json(['data' => $items, 'meta' => ['total' => $result->total(), 'current_page' => $result->currentPage(), 'last_page' => $result->lastPage()]]);
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        [$table, $permission] = $this->resource($resource);
        abort_unless($request->user(), 401);
        $data = $request->validate($this->rules($resource));
        $this->validateScope($request, $data, $permission);
        if ($resource === 'events') {
            return $this->storeEvent($request, $data);
        }

        return DB::transaction(function () use ($request, $resource, $table, $data): JsonResponse {
            $children = $data['targets'] ?? $data['components'] ?? [];
            unset($data['targets'], $data['components']);
            $id = (string) Str::uuid();
            $replaces = $data['replaces_id'] ?? null;
            $revision = 1;
            if ($replaces) {
                $old = DB::table($table)->where('id', $replaces)->lockForUpdate()->first();
                abort_unless($old && in_array($old->status, ['approved', 'draft', 'rejected'], true), 409, 'Record cannot be revised in its current state.');
                abort_if($old->status !== 'approved' && $old->created_by !== $request->user()->id, 403, 'Only the author may revise this record.');
                abort_unless($old->facility_id === $data['facility_id'] && $old->unit_id === ($data['unit_id'] ?? null), 422, 'Revision scope must match original.');
                foreach (['period_month', 'business_date', 'shift_code', 'unit_code', 'product_id', 'crude_id', 'carrier_code', 'category_code', 'chemical_code', 'parameter_code'] as $dimension) {
                    if (property_exists($old, $dimension)) {
                        abort_unless(($old->{$dimension} ?? null) === ($data[$dimension] ?? null), 422, 'Correction cannot change the measurement scope: '.$dimension);
                    }
                }
                abort_if(DB::table($table)->where('replaces_id', $replaces)->whereNotIn('status', ['rejected'])->exists(), 409, 'A correction is already pending or approved.');
                $revision = $old->revision_no + 1;
                abort_unless($request->filled('notes'), 422, 'Correction reason is required in notes.');
            }
            $data['id'] = $id;
            $data['status'] = 'draft';
            $data['revision_no'] = $revision;
            $data['created_by'] = $request->user()->id;
            $data['available_at'] = $this->utc($data['available_at']);
            $data['created_at'] = $data['updated_at'] = now('UTC')->format('Y-m-d H:i:s.u');
            $data['unit_code'] ??= 't';
            DB::table($table)->insert($data);
            if ($replaces && $old->status === 'draft') {
                DB::table($table)->where('id', $old->id)->update(['status' => 'superseded', 'updated_at' => $data['updated_at']]);
                Governance::audit($request, 'supersede', $resource, $old->id, (array) $old, array_replace((array) $old, ['status' => 'superseded']), $data['notes']);
            }
            foreach ($children as $child) {
                DB::table($resource === 'plans' ? 'production_targets' : 'blend_components')->insert([
                    'id' => (string) Str::uuid(),
                    $resource === 'plans' ? 'plan_id' : 'blend_batch_id' => $id,
                    'product_id' => $child['product_id'],
                    'quantity' => $child['quantity'],
                    'unit_code' => $child['unit_code'],
                ]);
            }
            Governance::audit($request, 'create', $resource, $id, null, $data, $data['notes'] ?? null);

            return response()->json(['data' => $this->decorate($resource, $data)], 201);
        });
    }

    public function transition(Request $request, string $resource, string $id, string $action): JsonResponse
    {
        [$table, $write, $approve] = $this->resource($resource);
        abort_unless(in_array($action, ['submit', 'approve', 'reject', 'eta', 'restart', 'cancel'], true), 404);

        return DB::transaction(function () use ($request, $resource, $table, $write, $approve, $id, $action): JsonResponse {
            $row = DB::table($table)->where('id', $id)->lockForUpdate()->first();
            abort_unless($row, 404);
            $permission = in_array($action, ['approve', 'reject', 'cancel'], true) ? $approve : $write;
            $this->validateScope($request, (array) $row, $permission);
            if ($request->has('revision_no')) {
                abort_unless((int) $request->input('revision_no') === $row->revision_no, 409, 'Stale record revision.');
            }
            if ($resource === 'events') {
                return $this->transitionEvent($request, $row, $action);
            }
            abort_unless(in_array($action, ['submit', 'approve', 'reject'], true), 422);
            $expected = $action === 'submit' ? 'draft' : 'submitted';
            abort_unless($row->status === $expected, 409, 'Invalid approval state transition.');
            if ($action === 'submit') {
                abort_unless($row->created_by === $request->user()->id, 403, 'Only the author may submit this draft.');
            } else {
                abort_if($row->created_by === $request->user()->id, 403, 'An independent reviewer is required.');
                $request->validate(['reason' => 'required|string|max:2000']);
            }
            $changes = ['status' => match ($action) {
                'submit' => 'submitted', 'approve' => 'approved', default => 'rejected'
            }, 'updated_at' => now('UTC')->format('Y-m-d H:i:s.u')];
            if ($action === 'approve') {
                $previous = $row->replaces_id;
                $original = null;
                while ($previous) {
                    $ancestor = DB::table($table)->where('id', $previous)->lockForUpdate()->first();
                    abort_unless($ancestor, 409, 'Revision history is incomplete.');
                    if ($ancestor->status === 'approved') {
                        $original = $ancestor;
                        break;
                    }
                    abort_if($ancestor->approved_at !== null, 409, 'The approved ancestor was already superseded.');
                    abort_unless(in_array($ancestor->status, ['rejected', 'superseded'], true), 409);
                    $previous = $ancestor->replaces_id;
                }
                $changes['approved_by'] = $request->user()->id;
                $changes['approved_at'] = now('UTC')->format('Y-m-d H:i:s.u');
                if ($resource === 'plans') {
                    $scope = $row->facility_id.':'.($row->unit_id ?? 'FACILITY').':'.$row->period_month;
                    abort_if(DB::table($table)->where('active_scope', $scope)->when($original, fn ($q) => $q->where('id', '!=', $original->id))->exists(), 409, 'An approved target plan already exists for this scope; use a revision.');
                    $changes['active_scope'] = $scope;
                }
                if ($original) {
                    $old = $original;
                    $oldChanges = ['status' => 'superseded'];
                    if ($resource === 'plans') {
                        $oldChanges['active_scope'] = null;
                    }
                    DB::table($table)->where('id', $old->id)->update($oldChanges);
                    Governance::audit($request, 'supersede', $resource, $old->id, (array) $old, array_merge((array) $old, $oldChanges), $request->input('reason'));
                }
            }
            DB::table($table)->where('id', $id)->update($changes);
            $after = array_merge((array) $row, $changes);
            Governance::audit($request, $action, $resource, $id, (array) $row, $after, $request->input('reason'));
            $this->outbox($resource.':'.$id.':'.$row->revision_no.':'.$action, 'measurement.'.$action, ['resource' => $resource, 'record_id' => $id]);

            return response()->json(['data' => $this->decorate($resource, $after)]);
        });
    }

    /** Limit all domain reads by the same facility/unit scope used for writes. */
    public static function scoped(Builder $query, Request $request, string $permission): Builder
    {
        $table = $query->from;

        return $query->whereExists(function (Builder $scope) use ($table, $request, $permission): void {
            $scope->selectRaw('1')->from('user_roles as ur')
                ->join('role_permissions as rp', 'rp.role_id', '=', 'ur.role_id')
                ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
                ->where('ur.user_id', $request->user()->id)->where('p.code', $permission)
                ->where(function (Builder $q) use ($table): void {
                    $q->whereNull('ur.facility_id')->orWhereColumn('ur.facility_id', $table.'.facility_id');
                })
                ->where(function (Builder $q) use ($table): void {
                    $q->whereNull('ur.unit_id')->orWhereColumn('ur.unit_id', $table.'.unit_id');
                });
        });
    }

    private function resource(string $resource): array
    {
        abort_unless(isset(self::RESOURCES[$resource]), 404);

        return self::RESOURCES[$resource];
    }

    private function rules(string $resource): array
    {
        $rules = [
            'facility_id' => 'required|uuid|exists:facilities,id',
            'unit_id' => 'nullable|uuid|exists:refinery_units,id',
            'source_reference' => 'required|string|max:500',
        ];
        if ($resource === 'events') {
            $rules['unit_id'] = 'required|uuid|exists:refinery_units,id';

            return $rules + [
                'kind' => ['required', Rule::in(['planned', 'unplanned'])],
                'event_type' => 'required|string|max:64',
                'started_at' => 'required|date',
                'known_at' => 'required|date',
                'expected_end_at' => 'nullable|date|after:started_at',
                'reason' => 'required|string|max:2000',
                'idempotency_key' => 'required|string|max:128',
                'derating_factor' => 'sometimes|numeric|between:0,1',
            ];
        }
        $rules += [
            'period_month' => 'required|date_format:Y-m',
            'business_date' => 'nullable|date_format:Y-m-d',
            'shift_code' => 'nullable|string|max:32',
            'unit_code' => 'required|exists:measurement_units,code',
            'available_at' => 'required|date',
            'notes' => 'nullable|string|max:2000',
            'replaces_id' => 'nullable|uuid',
        ];
        if ($resource !== 'plans') {
            $rules['quantity'] = ['present', 'nullable', 'regex:/^\d{1,14}(\.\d{1,6})?$/'];
        }

        return $rules + match ($resource) {
            'production-records' => ['product_id' => 'required|uuid|exists:product_catalog,id'],
            'crude-feed-records' => ['crude_id' => 'required|uuid|exists:crude_catalog,id'],
            'energy-records' => ['carrier_code' => 'required|string|max:64'],
            'loss-records' => ['category_code' => 'required|string|max:64'],
            'chemical-records' => ['chemical_code' => 'required|string|max:64', 'target_quantity' => ['nullable', 'regex:/^\d{1,14}(\.\d{1,6})?$/']],
            'quality-samples' => ['product_id' => 'nullable|uuid|exists:product_catalog,id', 'parameter_code' => 'required|string|max:64', 'spec_min' => 'nullable|numeric', 'spec_max' => 'nullable|numeric|gte:spec_min', 'spec_version' => 'nullable|string|max:64'],
            'blend-batches' => ['product_id' => 'required|uuid|exists:product_catalog,id', 'components' => 'required|array|min:1', 'components.*.product_id' => 'required|uuid|distinct|exists:product_catalog,id', 'components.*.quantity' => ['required', 'regex:/^\d{1,14}(\.\d{1,6})?$/'], 'components.*.unit_code' => 'required|exists:measurement_units,code'],
            'plans' => ['title' => 'required|string|max:255', 'targets' => 'required|array|min:1', 'targets.*.product_id' => 'required|uuid|distinct|exists:product_catalog,id', 'targets.*.quantity' => ['required', 'regex:/^\d{1,14}(\.\d{1,6})?$/'], 'targets.*.unit_code' => 'required|exists:measurement_units,code'],
            default => [],
        };
    }

    private function validateScope(Request $request, array $data, string $permission): void
    {
        if (! empty($data['unit_id'])) {
            abort_unless(DB::table('refinery_units')->where('id', $data['unit_id'])->where('facility_id', $data['facility_id'])->exists(), 422, 'Unit does not belong to the selected facility.');
        }
        $allowed = DB::table('user_roles as ur')->join('role_permissions as rp', 'rp.role_id', '=', 'ur.role_id')->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('ur.user_id', $request->user()->id)->where('p.code', $permission)
            ->where(fn (Builder $q) => $q->whereNull('ur.facility_id')->orWhere('ur.facility_id', $data['facility_id']))
            ->where(fn (Builder $q) => $q->whereNull('ur.unit_id')->orWhere('ur.unit_id', $data['unit_id'] ?? null))->exists();
        abort_unless($allowed, 403, 'Facility/unit scope is forbidden.');
        if (! empty($data['business_date'])) {
            abort_unless(substr($data['business_date'], 0, 7) === $data['period_month'], 422, 'Business date and period month must match.');
        }
    }

    private function hasAnyPermission(Request $request, string $permission): bool
    {
        return DB::table('user_roles as ur')->join('role_permissions as rp', 'rp.role_id', '=', 'ur.role_id')->join('permissions as p', 'p.id', '=', 'rp.permission_id')->where('ur.user_id', $request->user()->id)->where('p.code', $permission)->exists();
    }

    private function storeEvent(Request $request, array $data): JsonResponse
    {
        $data['started_at'] = $this->utc($data['started_at']);
        $data['known_at'] = $this->utc($data['known_at']);
        $data['expected_end_at'] = empty($data['expected_end_at']) ? null : $this->utc($data['expected_end_at']);
        abort_if(CarbonImmutable::parse($data['known_at'], 'UTC')->isFuture(), 422, 'An event cannot be known in the future.');
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($request, $data, $hash): JsonResponse {
            $existing = DB::table('operational_events')->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
            if ($existing) {
                abort_unless(hash_equals($existing->payload_hash, $hash), 409, 'Idempotency key has a different payload.');

                return response()->json(['data' => (array) $existing]);
            }
            $id = (string) Str::uuid();
            $record = $data + ['id' => $id, 'payload_hash' => $hash, 'created_by' => $request->user()->id, 'status' => 'reported', 'revision_no' => 1, 'created_at' => now('UTC')->format('Y-m-d H:i:s.u'), 'updated_at' => now('UTC')->format('Y-m-d H:i:s.u')];
            DB::table('operational_events')->insert($record);
            if ($data['kind'] === 'unplanned' && $data['event_type'] === 'trip') {
                DB::table('alerts')->insert(['id' => (string) Str::uuid(), 'facility_id' => $data['facility_id'], 'unit_id' => $data['unit_id'], 'event_id' => $id, 'category' => 'trip_reported', 'severity' => 'informational', 'status' => 'open', 'message' => 'تم تسجيل توقف مفاجئ للوحدة؛ يحتاج إلى المتابعة والمراجعة.', 'created_at' => now('UTC')]);
            }
            $this->eventRevision($request, $record, $data['reason']);
            Governance::audit($request, 'create', 'events', $id, null, $record, $data['reason']);
            $this->outbox('event:'.$id.':1', 'event.reported', ['event_id' => $id, 'revision_no' => 1, 'known_at' => $data['known_at']]);

            return response()->json(['data' => $record, 'reforecast_status' => 'pending'], 201);
        });
    }

    private function transitionEvent(Request $request, object $row, string $action): JsonResponse
    {
        abort_unless(in_array($action, ['eta', 'restart', 'approve', 'cancel'], true), 422);
        $request->validate(['reason' => 'required|string|max:2000', 'revision_no' => 'required|integer|min:1']);
        abort_if(in_array($row->status, ['closed', 'cancelled'], true), 409, 'Event is already finalized.');
        $changes = ['revision_no' => $row->revision_no + 1, 'updated_at' => now('UTC')->format('Y-m-d H:i:s.u'), 'known_at' => now('UTC')->format('Y-m-d H:i:s.u')];
        if ($action === 'eta') {
            $request->validate(['expected_end_at' => 'present|nullable|date']);
            $end = $request->input('expected_end_at');
            abort_if($end && CarbonImmutable::parse($end)->lessThanOrEqualTo(CarbonImmutable::parse($row->started_at, 'UTC')), 422, 'ETA must follow the start.');
            $changes['expected_end_at'] = $end ? $this->utc($end) : null;
        } elseif ($action === 'restart') {
            $request->validate(['actual_end_at' => 'required|date|before_or_equal:now']);
            abort_unless(CarbonImmutable::parse($request->input('actual_end_at'))->greaterThan(CarbonImmutable::parse($row->started_at, 'UTC')), 422, 'Restart must follow start.');
            $changes['actual_end_at'] = $this->utc($request->input('actual_end_at'));
            $changes['status'] = 'resolved';
        } elseif ($action === 'approve') {
            abort_if($row->created_by === $request->user()->id, 403, 'An independent event reviewer is required.');
            $changes['approved_by'] = $request->user()->id;
            $changes['approved_at'] = now('UTC')->format('Y-m-d H:i:s.u');
            $changes['status'] = $row->actual_end_at ? 'closed' : 'acknowledged';
        } else {
            $changes['status'] = 'cancelled';
        }
        DB::table('operational_events')->where('id', $row->id)->update($changes);
        $after = array_merge((array) $row, $changes);
        $this->eventRevision($request, $after, $request->input('reason'));
        Governance::audit($request, $action, 'events', $row->id, (array) $row, $after, $request->input('reason'));
        $this->outbox('event:'.$row->id.':'.$changes['revision_no'], 'event.'.$action, ['event_id' => $row->id, 'revision_no' => $changes['revision_no'], 'known_at' => $changes['known_at']]);

        return response()->json(['data' => $after, 'reforecast_status' => 'pending']);
    }

    private function eventRevision(Request $request, array $record, string $reason): void
    {
        DB::table('event_revisions')->insert(['id' => (string) Str::uuid(), 'event_id' => $record['id'], 'revision_no' => $record['revision_no'], 'snapshot' => json_encode($record, JSON_THROW_ON_ERROR), 'reason' => $reason, 'actor_id' => $request->user()->id, 'created_at' => now('UTC')->format('Y-m-d H:i:s.u')]);
    }

    private function outbox(string $key, string $type, array $payload): void
    {
        DB::table('operations_outbox')->insertOrIgnore(['id' => (string) Str::uuid(), 'event_key' => $key, 'event_type' => $type, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'created_at' => now('UTC')->format('Y-m-d H:i:s.u')]);
    }

    private function decorate(string $resource, array $record): array
    {
        if ($resource === 'plans') {
            $record['targets'] = DB::table('production_targets')->where('plan_id', $record['id'])->get()->all();
        } elseif ($resource === 'blend-batches') {
            $record['components'] = DB::table('blend_components')->where('blend_batch_id', $record['id'])->get()->all();
        } elseif ($resource === 'events') {
            $record['revisions'] = DB::table('event_revisions')->where('event_id', $record['id'])->orderBy('revision_no')->get()->all();
            $record['eta_status'] = empty($record['expected_end_at']) ? 'unknown' : 'reported';
        }

        return $record;
    }

    private function utc(string $value): string
    {
        return CarbonImmutable::parse($value)->utc()->format('Y-m-d H:i:s.u');
    }
}
