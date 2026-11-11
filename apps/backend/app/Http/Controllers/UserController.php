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

        $usersQuery = User::select('id', 'name', 'email', 'active');
        if ($request->filled('q')) {
            $q = $request->input('q');
            $usersQuery->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%");
            });
        }

        $assignments = DB::table('user_roles as ur')
            ->join('users as u', 'u.id', '=', 'ur.user_id')
            ->join('roles as r', 'r.id', '=', 'ur.role_id')
            ->leftJoin('facilities as f', 'f.id', '=', 'ur.facility_id')
            ->leftJoin('refinery_units as ru', 'ru.id', '=', 'ur.unit_id')
            ->select([
                'ur.id',
                'ur.user_id',
                'ur.role_id',
                'ur.facility_id',
                'ur.unit_id',
                'u.name as user_name',
                'u.email as user_email',
                'r.code as role_code',
                'r.name_ar as role_name_ar',
                'f.code as facility_code',
                'f.name_ar as facility_name_ar',
                'ru.code as unit_code',
                'ru.name_ar as unit_name_ar',
            ])
            ->orderBy('u.name')
            ->get();

        return response()->json([
            'data' => [
                'users' => $usersQuery->paginate(50),
                'roles' => DB::table('roles')->get(),
                'permissions' => DB::table('permissions')->get(),
                'assignments' => $assignments,
            ],
        ]);
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

    public function toggleStatus(Request $request, string $id)
    {
        Governance::authorize($request, 'users.manage');
        $user = User::findOrFail($id);
        abort_if($user->id === $request->user()->id, 403, 'Cannot change your own active status.');
        $data = $request->validate([
            'active' => 'required|boolean',
            'reason' => 'required|string|min:10|max:2000',
        ]);
        $oldActive = $user->active;
        $user->active = (bool) $data['active'];
        $user->save();
        Governance::audit(
            $request,
            $user->active ? 'user_activated' : 'user_deactivated',
            'users',
            $user->id,
            ['active' => $oldActive],
            ['active' => $user->active],
            $data['reason']
        );

        return response()->json(['data' => $user]);
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

    public function revokeRole(Request $request, string $userId, string $assignmentId)
    {
        Governance::authorize($request, 'users.manage');
        $assignment = DB::table('user_roles')->where('id', $assignmentId)->where('user_id', $userId)->first();
        abort_unless($assignment, 404, 'Role assignment not found.');
        $data = $request->validate([
            'reason' => 'required|string|min:10|max:2000',
        ]);
        DB::transaction(function () use ($request, $userId, $assignmentId, $assignment, $data) {
            DB::table('user_roles')->where('id', $assignmentId)->delete();
            Governance::audit(
                $request,
                'role_revoked',
                'users',
                $userId,
                (array) $assignment,
                null,
                $data['reason']
            );
        });

        return response()->json(['data' => ['revoked' => true]]);
    }
}
