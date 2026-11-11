<?php

namespace App\Http\Controllers;

use App\Services\Governance;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AlertController extends Controller
{
    private function scoped(Request $request): Builder
    {
        abort_unless($request->user(), 401);
        $permissions = ['events.read', 'events.write', 'engineering.read'];
        $has = DB::table('user_roles as ur')->join('role_permissions as rp', 'rp.role_id', '=', 'ur.role_id')->join('permissions as p', 'p.id', '=', 'rp.permission_id')->where('ur.user_id', $request->user()->id)->whereIn('p.code', $permissions)->exists();
        abort_unless($has, 403);

        return DB::table('alerts')->whereExists(function (Builder $q) use ($request, $permissions) {
            $q->selectRaw('1')->from('user_roles as ur')->join('role_permissions as rp', 'rp.role_id', '=', 'ur.role_id')->join('permissions as p', 'p.id', '=', 'rp.permission_id')->where('ur.user_id', $request->user()->id)->whereIn('p.code', $permissions)->where(fn (Builder $s) => $s->whereNull('ur.facility_id')->orWhereColumn('ur.facility_id', 'alerts.facility_id'))->where(fn (Builder $s) => $s->whereNull('ur.unit_id')->orWhereColumn('ur.unit_id', 'alerts.unit_id'));
        });
    }

    public function index(Request $request)
    {
        $data = $request->validate(['status' => 'nullable|in:open,acknowledged', 'facility_id' => 'nullable|uuid']);
        $q = $this->scoped($request);
        foreach (['status', 'facility_id'] as $f) {
            if (! empty($data[$f])) {
                $q->where('alerts.'.$f, $data[$f]);
            }
        }

return response()->json($q->orderByDesc('created_at')->paginate(50));
    }

    public function acknowledge(Request $request, string $id)
    {
        $data = $request->validate(['reason' => 'required|string|min:10|max:2000']);

        return DB::transaction(function () use ($request, $id, $data) {
            $row = $this->scoped($request)->where('alerts.id', $id)->lockForUpdate()->first();
            abort_unless($row, 404);
            if ($row->status === 'acknowledged') {
                abort_unless($row->acknowledged_by === $request->user()->id, 409, 'Already acknowledged');

                return response()->json(['data' => $row]);
            }$changes = ['status' => 'acknowledged', 'acknowledged_by' => $request->user()->id, 'acknowledged_at' => now('UTC'), 'acknowledgment_reason' => $data['reason']];
            DB::table('alerts')->where('id', $id)->update($changes);
            Governance::audit($request, 'acknowledged', 'alerts', $id, ['status' => 'open'], ['status' => 'acknowledged'], $data['reason']);

            return response()->json(['data' => array_merge((array) $row,$changes)]);
        }, 3);
    }
}
