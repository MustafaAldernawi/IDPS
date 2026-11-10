<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Governance
{
    public static function hasPermission(string $userId, string $permission, ?string $unitId = null): bool
    {
        $query = DB::table('user_roles as ur')->join('role_permissions as rp', 'rp.role_id', '=', 'ur.role_id')->join('permissions as p', 'p.id', '=', 'rp.permission_id')->where('ur.user_id', $userId)->where('p.code', $permission);
        if ($unitId === null) {
            $query->whereNull('ur.unit_id')->whereNull('ur.facility_id');
        } else {
            $facility = DB::table('refinery_units')->where('id', $unitId)->value('facility_id');
            if (! $facility) {
                return false;
            }$query->where(function ($q) use ($unitId, $facility) {
                $q->where('ur.unit_id', $unitId)->orWhere(function ($q) use ($facility) {
                    $q->whereNull('ur.unit_id')->where('ur.facility_id', $facility);
                })->orWhere(function ($q) {
                    $q->whereNull('ur.unit_id')->whereNull('ur.facility_id');
                });
            });
        }

        return $query->exists();
    }

    public static function authorize(Request $request, string $permission, ?string $unitId = null): void
    {
        abort_unless($request->user(), 401);
        abort_unless(self::hasPermission($request->user()->id, $permission, $unitId), 403, 'Permission denied for this scope');
    }

    public static function audit(Request $request, string $action, string $type, string $id, ?array $before, ?array $after, ?string $reason = null): void
    {
        foreach (['password', 'token', 'token_hash', 'DB_PASSWORD', 'secret'] as $key) {
            unset($before[$key],$after[$key]);
        }
        DB::table('audit_logs')->insert(['id' => (string) Str::uuid(), 'actor_id' => $request->user()?->id, 'action' => $action, 'resource_type' => $type, 'resource_id' => $id, 'before_json' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR), 'after_json' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR), 'reason' => $reason, 'request_id' => $request->header('X-Request-ID') ?: (string) Str::uuid(), 'created_at' => now('UTC')]);
    }

    public static function identity(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'roles' => DB::table('user_roles as ur')->join('roles as r', 'r.id', '=', 'ur.role_id')->where('ur.user_id', $user->id)->distinct()->pluck('r.code')->all(), 'permissions' => DB::table('user_roles as ur')->join('role_permissions as rp', 'rp.role_id', '=', 'ur.role_id')->join('permissions as p', 'p.id', '=', 'rp.permission_id')->where('ur.user_id',$user->id)->distinct()->pluck('p.code')->all()];
    }
}
