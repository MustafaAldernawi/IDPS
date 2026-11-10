<?php

namespace App\Http\Controllers;

use App\Services\EngineeringService;
use App\Services\Governance;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DomainReferenceController extends Controller
{
    private const TABLES = ['shifts' => 'shifts', 'operational-calendar' => 'operational_calendar', 'unit-status-intervals' => 'unit_status_intervals', 'quality-specifications' => 'quality_specifications'];

    private function table(string $kind): string
    {
        return self::TABLES[$kind] ?? abort(404);
    }

    private function access(Request $request, string $permission, string $facility, ?string $unit = null): void
    {
        abort_unless($request->user(), 401);
        if ($unit !== null) {
            abort_unless(DB::table('refinery_units')->where('id', $unit)->where('facility_id', $facility)->exists(), 422, 'Unit/facility mismatch');
        }
        $q = DB::table('user_roles as ur')->join('role_permissions as rp', 'rp.role_id', '=', 'ur.role_id')->join('permissions as p', 'p.id', '=', 'rp.permission_id')->where('ur.user_id', $request->user()->id)->where('p.code', $permission)->where(function ($q) use ($facility, $unit) {
            $q->where(function ($q) {
                $q->whereNull('ur.facility_id')->whereNull('ur.unit_id');
            })->orWhere(function ($q) use ($facility) {
                $q->where('ur.facility_id', $facility)->whereNull('ur.unit_id');
            });
            if ($unit !== null) {
                $q->orWhere(function ($q) use ($unit) {
                    $q->where('ur.unit_id', $unit);
                });
            }
        });
        abort_unless($q->exists(), 403);
    }

    private function utc(string $value): string
    {
        abort_unless((bool) preg_match('/(?:Z|\+00:00)$/', $value), 422, 'Explicit UTC timestamp required');

        return CarbonImmutable::parse($value)->utc()->format('Y-m-d H:i:s.u');
    }

    private function grant(string $kind): string
    {
        return match ($kind) {
            'shifts','operational-calendar' => 'catalogs.manage','unit-status-intervals' => 'production.write','quality-specifications' => 'quality.write'
        };
    }

    public function index(Request $request, string $kind)
    {
        $table = $this->table($kind);
        $data = $request->validate(['facility_id' => 'required|uuid|exists:facilities,id', 'unit_id' => 'nullable|uuid|exists:refinery_units,id']);
        $permission = match ($kind) {
            'shifts','operational-calendar' => 'catalogs.read','unit-status-intervals' => 'production.read','quality-specifications' => 'quality.read'
        };
        $this->access($request, $permission, $data['facility_id'], $data['unit_id'] ?? null);
        $query = DB::table($table)->where('facility_id', $data['facility_id']);
        if ($kind === 'unit-status-intervals' && ! empty($data['unit_id'])) {
            $query->where('unit_id', $data['unit_id']);
        }

return response()->json($query->orderByDesc('created_at')->paginate(100));
    }

    public function store(Request $request, string $kind)
    {
        $table = $this->table($kind);
        $base = ['facility_id' => 'required|uuid|exists:facilities,id'];
        $rules = match ($kind) {
            'shifts' => ['code' => 'required|string|max:32|regex:/^[A-Za-z0-9_-]+$/', 'name_ar' => 'required|string|max:255', 'start_time' => 'required|date_format:H:i', 'end_time' => 'required|date_format:H:i|different:start_time', 'effective_from' => 'required|date_format:Y-m-d', 'effective_until' => 'nullable|date_format:Y-m-d|after_or_equal:effective_from'],
            'operational-calendar' => ['shift_code' => 'required|string|max:32', 'business_date' => 'required|date_format:Y-m-d', 'starts_at' => 'required|date', 'ends_at' => 'required|date|after:starts_at', 'source_reference' => 'required|string|max:500'],
            'unit-status-intervals' => ['unit_id' => 'required|uuid|exists:refinery_units,id', 'state' => ['required', Rule::in(['running', 'trip', 'startup', 'maintenance', 'derated', 'shutdown'])], 'started_at' => 'required|date', 'ended_at' => 'nullable|date|after:started_at', 'known_at' => 'sometimes|date', 'derating_factor' => 'nullable|decimal:0,6|between:0,1', 'source_reference' => 'required|string|max:500'],
            'quality-specifications' => ['product_id' => 'nullable|uuid|exists:product_catalog,id', 'parameter_code' => 'required|string|max:64|regex:/^[A-Za-z0-9_-]+$/', 'name_ar' => 'required|string|max:255', 'unit_code' => 'required|exists:measurement_units,code', 'spec_min' => 'nullable|decimal:0,6', 'spec_max' => 'nullable|decimal:0,6', 'effective_from' => 'required|date_format:Y-m-d', 'effective_until' => 'nullable|date_format:Y-m-d|after_or_equal:effective_from', 'source_reference' => 'required|string|max:500']
        };
        $data = $request->validate($base + $rules);
        $this->access($request, $this->grant($kind), $data['facility_id'], $data['unit_id'] ?? null);
        if ($kind === 'quality-specifications') {
            abort_if(! isset($data['spec_min']) && ! isset($data['spec_max']), 422, 'At least one signed specification bound required');
            if (isset($data['spec_min'],$data['spec_max'])) {
                abort_if(bccomp($data['spec_min'], $data['spec_max'], 6) > 0, 422, 'Minimum exceeds maximum');
            }
        }
        if ($kind === 'unit-status-intervals') {
            if ($data['state'] === 'derated') {
                abort_unless(isset($data['derating_factor']), 422, 'Explicit derating factor required');
            } elseif (isset($data['derating_factor'])) {
                abort(422, 'Derating applies only to derated state');
            } $data['started_at'] = $this->utc($data['started_at']);
            if (isset($data['ended_at'])) {
                $data['ended_at'] = $this->utc($data['ended_at']);
            }$data['known_at'] = isset($data['known_at']) ? $this->utc($data['known_at']) : now('UTC')->format('Y-m-d H:i:s.u');
        }
        if ($kind === 'operational-calendar') {
            $data['starts_at'] = $this->utc($data['starts_at']);
            $data['ends_at'] = $this->utc($data['ends_at']);
            $shift = DB::table('shifts')->where('facility_id', $data['facility_id'])->where('code', $data['shift_code'])->where('active', true)->where('effective_from', '<=', $data['business_date'])->where(function ($q) use ($data) {
                $q->whereNull('effective_until')->orWhere('effective_until', '>=', $data['business_date']);
            })->orderByDesc('effective_from')->first();
            abort_unless($shift, 422, 'No shift definition effective for business date');
            unset($data['shift_code']);
            $data['shift_id'] = $shift->id;
        }

        return DB::transaction(function () use ($request, $kind, $table, $data) {
            DB::table('facilities')->where('id', $data['facility_id'])->lockForUpdate()->first();
            if ($kind === 'quality-specifications') {
                $query = DB::table($table)->where('facility_id', $data['facility_id'])->where('parameter_code', $data['parameter_code'])->where('product_id', $data['product_id'] ?? null);
                $data['version'] = ((int) $query->max('version')) + 1;
                $data['status'] = 'pending';
            }if ($kind === 'unit-status-intervals') {
                $data['status'] = 'pending';
            }$data['id'] = (string) Str::uuid();
            $data['created_by'] = $request->user()->id;
            $data['created_at'] = now('UTC')->format('Y-m-d H:i:s.u');
            DB::table($table)->insert($data);
            Governance::audit($request, 'create', $table, $data['id'], null, $data);

            return response()->json(['data' => $data], 201);
        }, 3);
    }

    public function approve(Request $request, string $kind, string $id)
    {
        $table = $this->table($kind);
        abort_unless(in_array($kind, ['quality-specifications', 'unit-status-intervals']), 404);
        $data = $request->validate(['reason' => 'required|string|min:10|max:2000']);

        return DB::transaction(function () use ($request, $kind, $table, $id, $data) {
            $row = DB::table($table)->where('id', $id)->lockForUpdate()->first();
            abort_unless($row, 404);
            $this->access($request, $kind === 'quality-specifications' ? 'quality.approve' : 'production.approve', $row->facility_id, $row->unit_id ?? null);
            abort_if($row->created_by === $request->user()->id, 403, 'Independent reviewer required');
            abort_unless($row->status === 'pending', 409, 'Already reviewed');
            DB::table('facilities')->where('id', $row->facility_id)->lockForUpdate()->first();
            if ($kind === 'quality-specifications') {
                $overlap = DB::table($table)->where('facility_id', $row->facility_id)->where('parameter_code', $row->parameter_code)->where('product_id', $row->product_id)->where('status', 'approved')->where(function ($q) use ($row) {
                    $q->whereNull('effective_until')->orWhere('effective_until', '>=', $row->effective_from);
                })->when($row->effective_until, fn ($q) => $q->where('effective_from', '<=', $row->effective_until))->exists();
                abort_if($overlap, 409, 'Overlapping approved specification');
            }$changes = ['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now('UTC')->format('Y-m-d H:i:s.u')];
            if ($kind === 'quality-specifications') {
                $changes['approval_reason'] = $data['reason'];
            }DB::table($table)->where('id', $id)->update($changes);
            Governance::audit($request, 'approve', $table, $id, ['status' => $row->status], $changes, $data['reason']);

            return response()->json(['data' => ['id' => $id] + $changes]);
        }, 3);
    }

    public function downtime(Request $request)
    {
        $data = $request->validate(['unit_id' => 'required|uuid|exists:refinery_units,id', 'from' => 'required|date', 'as_of' => 'required|date|after:from']);
        $unit = DB::table('refinery_units')->find($data['unit_id']);
        $this->access($request, 'engineering.read', $unit->facility_id, $unit->id);
        $from = $this->utc($data['from']);
        $asOf = $this->utc($data['as_of']);
        $rows = DB::table('unit_status_intervals')->where('unit_id', $unit->id)->where('status', 'approved')->whereIn('state', ['trip', 'maintenance', 'shutdown'])->where('known_at', '<=', $asOf)->where('approved_at', '<=', $asOf)->where('started_at', '<', $asOf)->where(function ($q) use ($from) {
            $q->whereNull('ended_at')->orWhere('ended_at', '>', $from);
        })->get()->map(fn ($r) => ['started_at' => $r->started_at, 'actual_end_at' => $r->ended_at])->all();

        return response()->json(['data' => ['unit_id' => $unit->id, 'from' => $from, 'as_of' => $asOf, 'seconds' => EngineeringService::downtimeSeconds($rows, $from, $asOf), 'status' => 'approved_intervals_only', 'derating_status' => 'separate_not_included']]);
    }

    public function conformance(Request $request, string $id)
    {
        $sample = DB::table('laboratory_samples')->find($id);
        abort_unless($sample, 404);
        $this->access($request, 'quality.read', $sample->facility_id, $sample->unit_id);
        if ($sample->status !== 'approved' || $sample->quantity === null || $sample->business_date === null) {
            return response()->json(['data' => ['status' => 'not_evaluated', 'reason' => 'approved sample, value and actual business date required']]);
        }$spec = DB::table('quality_specifications')->where('facility_id', $sample->facility_id)->where('product_id', $sample->product_id)->where('parameter_code', $sample->parameter_code)->where('unit_code', $sample->unit_code)->where('status', 'approved')->where('approved_at', '<=', now('UTC'))->where('effective_from', '<=', $sample->business_date)->where(function ($q) use ($sample) {
            $q->whereNull('effective_until')->orWhere('effective_until', '>=', $sample->business_date);
        })->get();
        if ($spec->count() !== 1) {
            return response()->json(['data' => ['status' => 'not_evaluated', 'reason' => 'one approved matching specification required']]);
        }$s = $spec->first();
        $pass = ($s->spec_min === null || bccomp($sample->quantity,$s->spec_min,6) >= 0) && ($s->spec_max === null || bccomp($sample->quantity,$s->spec_max,6) <= 0);

        return response()->json(['data' => ['status' => $pass ? 'conforming' : 'out_of_spec', 'specification_id' => $s->id, 'version' => $s->version, 'unit_code' => $s->unit_code, 'sample_id' => $id]]);
    }
}
