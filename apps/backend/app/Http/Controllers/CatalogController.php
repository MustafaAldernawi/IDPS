<?php

namespace App\Http\Controllers;

use App\Services\Governance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CatalogController extends Controller
{
    private const TABLES = ['facilities' => 'facilities', 'units' => 'refinery_units', 'products' => 'product_catalog', 'crudes' => 'crude_catalog', 'measurement-units' => 'measurement_units'];

    public function index(Request $request, string $catalog)
    {
        $table = self::TABLES[$catalog] ?? abort(404);
        $scopes = DB::table('user_roles as ur')->join('role_permissions as rp', 'rp.role_id', '=', 'ur.role_id')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->leftJoin('refinery_units as u', 'u.id', '=', 'ur.unit_id')
            ->where('ur.user_id', $request->user()->id)->where('p.code', 'catalogs.read')
            ->select('ur.facility_id', 'ur.unit_id', 'u.facility_id as unit_facility_id')->get();
        abort_if($scopes->isEmpty(), 403);
        $global = $scopes->contains(fn ($s) => $s->facility_id === null && $s->unit_id === null);
        $facilityScopes = $scopes->filter(fn ($s) => $s->facility_id !== null && $s->unit_id === null)->pluck('facility_id')->all();
        $unitScopes = $scopes->filter(fn ($s) => $s->unit_id !== null && $s->unit_facility_id !== null && ($s->facility_id === null || $s->facility_id === $s->unit_facility_id));
        $unitIds = $unitScopes->pluck('unit_id')->all();
        $facilityIds = array_values(array_unique(array_merge($facilityScopes, $unitScopes->pluck('unit_facility_id')->all())));
        $query = DB::table($table);
        if (! $global && $catalog === 'facilities') {
            $query->whereIn('id', $facilityIds);
        } elseif (! $global && $catalog === 'units') {
            $query->where(fn ($q) => $q->whereIn('facility_id', $facilityScopes)->orWhereIn('id', $unitIds));
        }

        return response()->json(['data' => $query->orderBy('code')->get()]);
    }

    public function store(Request $request, string $catalog)
    {
        Governance::authorize($request, 'catalogs.manage');
        $table = self::TABLES[$catalog] ?? abort(404);
        $rules = ['code' => 'required|string|max:64|regex:/^[A-Za-z0-9_%-]+$/|unique:'.$table.',code'];
        if ($catalog === 'measurement-units') {
            $rules += ['dimension' => 'required|in:mass,volume,energy,time,ratio,quality', 'display_symbol' => 'required|string|max:32'];
        } else {
            $rules += ['name_ar' => 'required|string|max:255'];
        }if ($catalog === 'units') {
            $rules['facility_id'] = 'required|uuid|exists:facilities,id';
        }if ($catalog === 'products') {
            $rules['stage'] = 'required|in:pending_review,intermediate_stream,final_product,blend_additive,fuel,byproduct,waste,other';
        }$data = $request->validate($rules);
        if ($catalog !== 'measurement-units') {
            $data['id'] = (string) Str::uuid();
        }DB::transaction(function () use ($request, $table, $data) {
            DB::table($table)->insert($data);
            Governance::audit($request, 'created', $table, $data['id'] ?? $data['code'], null, $data);
        });

        return response()->json(['data' => $data], 201);
    }

    public function audit(Request $request)
    {
        Governance::authorize($request, 'audit.read');

        return response()->json(DB::table('audit_logs')->orderByDesc('created_at')->paginate(50));
    }
}
