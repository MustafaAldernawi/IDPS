<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Governance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index(Request $request)
    {
        Governance::authorize($request, 'users.manage');

        return response()->json(['data' => ['users' => User::select('id', 'name', 'email', 'active')->paginate(50), 'roles' => DB::table('roles')->get(), 'permissions' => DB::table('permissions')->get(), 'assignments' => DB::table('user_roles')->get()]]);
    }

    public function store(Request $request)
    {
        Governance::authorize($request, 'users.manage');
        $data = $request->validate(['name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email', 'password' => 'required|string|min:12|max:128']);
        $user = DB::transaction(function () use ($data, $request) {
            $user = User::create($data);
            Governance::audit($request, 'created', 'users', $user->id, null, ['name' => $user->name, 'email' => $user->email]);

            return $user;
        });

        return response()->json(['data' => $user], 201);
    }

    public function assignRole(Request $request, string $id)
    {
        Governance::authorize($request, 'users.manage');
        abort_unless(User::find($id), 404);
        $data = $request->validate(['role_id' => 'required|uuid|exists:roles,id', 'facility_id' => 'nullable|uuid|exists:facilities,id', 'unit_id' => 'nullable|uuid|exists:refinery_units,id', 'reason' => 'required|string|min:10|max:2000']);
        if (! empty($data['unit_id'])) {
            abort_unless(DB::table('refinery_units')->where('id', $data['unit_id'])->value('facility_id') === ($data['facility_id'] ?? null), 422, 'Unit must belong to assigned facility');
        }DB::transaction(function () use ($request, $data, $id) {
            DB::table('user_roles')->insertOrIgnore(['id' => (string) Str::uuid(), 'user_id' => $id, 'role_id' => $data['role_id'], 'facility_id' => $data['facility_id'] ?? null, 'unit_id' => $data['unit_id'] ?? null]);
            Governance::audit($request, 'role_assigned', 'users', $id, null, collect($data)->except('reason')->all(), $data['reason']);
        });

        return response()->json(['data' => ['assigned' => true]]);
    }
}
