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
        Governance::authorize($request, 'catalogs.read');
        $table = self::TABLES[$catalog] ?? abort(404);

        return response()->json(['data' => DB::table($table)->orderBy('code')->get()]);
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
