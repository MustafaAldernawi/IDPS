<?php

namespace App\Services;

use App\Http\Controllers\OperationsController;
use ArPHP\I18N\Arabic;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class EngineeringService
{
    public static function percentage(?string $numerator, ?string $denominator): ?string
    {
        if ($numerator === null || $denominator === null || bccomp($denominator, '0', 6) <= 0) {
            return null;
        }

        return bcdiv(bcmul($numerator, '100', 12), $denominator, 6);
    }

    /** Union confirmed full-outage intervals within an explicit period/as-of cutoff. */
    public static function downtimeSeconds(array $intervals, string $start, string $cutoff): int
    {
        $lower = CarbonImmutable::parse($start)->getTimestamp();
        $upper = CarbonImmutable::parse($cutoff)->getTimestamp();
        if ($upper <= $lower) {
            return 0;
        }
        $windows = [];
        foreach ($intervals as $interval) {
            $from = max($lower, CarbonImmutable::parse($interval['started_at'], 'UTC')->getTimestamp());
            $until = min($upper, empty($interval['actual_end_at']) ? $upper : CarbonImmutable::parse($interval['actual_end_at'], 'UTC')->getTimestamp());
            if ($until > $from) {
                $windows[] = [$from, $until];
            }
        }
        usort($windows, fn (array $a, array $b) => $a[0] <=> $b[0]);
        $seconds = 0;
        $last = null;
        foreach ($windows as [$from, $until]) {
            if ($last !== null && $from <= $last[1]) {
                $last[1] = max($last[1], $until);
            } else {
                if ($last !== null) {
                    $seconds += $last[1] - $last[0];
                }
                $last = [$from, $until];
            }
        }

        return $seconds + ($last === null ? 0 : $last[1] - $last[0]);
    }

    public function comparison(Request $request): JsonResponse
    {
        $this->authorize($request);
        $request->validate(['period_month' => 'sometimes|date_format:Y-m', 'product_id' => 'sometimes|uuid', 'facility_id' => 'sometimes|uuid', 'unit_id' => 'sometimes|nullable|uuid']);
        $period = $request->input('period_month', now('Africa/Tripoli')->format('Y-m'));
        $actualQuery = $this->facts('production_records', $request)->where('period_month', $period);
        if ($request->filled('product_id')) {
            $actualQuery->where('product_id', $request->input('product_id'));
        }
        $actuals = $actualQuery->select('facility_id', 'unit_id', 'product_id', 'unit_code')->selectRaw('SUM(quantity) AS quantity, COUNT(quantity) AS observations, MAX(approved_at) AS latest_approval')->groupBy('facility_id', 'unit_id', 'product_id', 'unit_code')->get();
        $plans = $this->facts('production_target_plans', $request)->where('period_month', $period)->get();
        $targets = [];
        foreach ($plans as $plan) {
            foreach (DB::table('production_targets')->where('plan_id', $plan->id)->get() as $target) {
                if ($request->filled('product_id') && $target->product_id !== $request->input('product_id')) {
                    continue;
                }
                $key = $plan->facility_id.'|'.($plan->unit_id ?? '').'|'.$target->product_id.'|'.$target->unit_code;
                $targets[$key] = ['facility_id' => $plan->facility_id, 'unit_id' => $plan->unit_id, 'product_id' => $target->product_id, 'unit_code' => $target->unit_code, 'target' => $target->quantity, 'plan_id' => $plan->id];
            }
        }
        $data = [];
        foreach ($actuals as $actual) {
            $key = $actual->facility_id.'|'.($actual->unit_id ?? '').'|'.$actual->product_id.'|'.$actual->unit_code;
            $target = $targets[$key] ?? null;
            $actualQuantity = $actual->observations > 0 ? $actual->quantity : null;
            $data[] = [
                'facility_id' => $actual->facility_id, 'unit_id' => $actual->unit_id, 'product_id' => $actual->product_id,
                'product_code' => DB::table('product_catalog')->where('id', $actual->product_id)->value('code'),
                'period_month' => $period, 'unit_code' => $actual->unit_code, 'actual' => $actualQuantity, 'target' => $target['target'] ?? null,
                'variance' => $actualQuantity !== null && $target !== null ? bcsub($actualQuantity, $target['target'], 6) : null,
                'achievement_pct' => self::percentage($actualQuantity, $target['target'] ?? null), 'plan_id' => $target['plan_id'] ?? null,
                'status' => $actualQuantity === null ? 'missing_actual' : ($target === null ? 'missing_target' : (bccomp($target['target'], '0', 6) === 0 ? 'zero_target' : 'valid')),
                'source_status' => 'approved', 'calculation_version' => 'achievement_v1', 'latest_approval' => $actual->latest_approval,
            ];
            unset($targets[$key]);
        }
        foreach ($targets as $target) {
            $data[] = $target + ['product_code' => DB::table('product_catalog')->where('id', $target['product_id'])->value('code'), 'period_month' => $period, 'actual' => null, 'variance' => null, 'achievement_pct' => null, 'status' => 'missing_actual', 'source_status' => 'approved', 'calculation_version' => 'achievement_v1'];
        }

        $data = $this->mergeMonthlyComparison($request, $period, $data);
        foreach ($data as &$comparison) {
            $comparison['scope_warning'] = DB::table('product_catalog')->where('id', $comparison['product_id'])->value('stage') === 'pending_review' ? 'product_stage_pending_engineering_review' : null;
        }

        return response()->json(['data' => $data]);
    }

    public function kpis(Request $request): JsonResponse
    {
        $this->authorize($request);
        $request->validate(['period_month' => 'sometimes|date_format:Y-m', 'facility_id' => 'sometimes|uuid', 'unit_id' => 'sometimes|nullable|uuid']);
        $period = $request->input('period_month', now('Africa/Tripoli')->format('Y-m'));
        $scope = ['facility_id' => $request->input('facility_id'), 'unit_id' => $request->input('unit_id'), 'period_month' => $period];
        $data = [
            ['code' => 'PRODUCTION_TARGET_ACHIEVEMENT', 'value' => null, 'unit' => 'percent', 'status' => 'product_scope_required', 'reason' => 'Aggregate product/stream grouping requires engineering signoff. Use per-product comparison.', 'calculation_version' => 'achievement_v1'],
            ['code' => 'SPECIFIC_ENERGY_CONSUMPTION', 'value' => null, 'unit' => 'GJ/t', 'status' => 'definition_pending', 'reason' => 'Approved LHV/HHV and electricity conversion factors are required.', 'calculation_version' => null],
            ['code' => 'REFINERY_LOSS_RATE', 'value' => null, 'unit' => 'percent', 'status' => 'definition_pending', 'reason' => 'Loss scope and denominator require engineering approval.', 'calculation_version' => null],
        ];
        if ($request->filled('product_id')) {
            $comparison = $this->comparison($request)->getData(true)['data'];
            if (count($comparison) === 1) {
                $data[0] = $data[0] + ['actual' => $comparison[0]['actual'], 'target' => $comparison[0]['target']];
                $data[0]['value'] = $comparison[0]['achievement_pct'];
                $data[0]['status'] = $comparison[0]['status'];
                $data[0]['reason'] = null;
            }
        }
        if ($request->filled('facility_id')) {
            $crude = $this->measurementTotal($request, $period, 'crude_feed_records', 'feed', 'TOTAL');
            $fuel = $this->fuelTotal($request, $period);
            $data[] = ['code' => 'FUEL_CONSUMPTION_RATIO', 'value' => self::percentage($fuel, $crude), 'unit' => 'percent', 'fuel_t' => $fuel, 'crude_t' => $crude, 'status' => self::percentage($fuel, $crude) === null ? 'missing_or_zero_denominator' : 'valid', 'reason' => 'Fuel mass ratio; this is not specific energy consumption.', 'calculation_version' => 'fuel_mass_ratio_v1'];
            foreach ([0 => 'PRODUCTION_TARGET_ACHIEVEMENT', 1 => 'SPECIFIC_ENERGY_CONSUMPTION', 2 => 'REFINERY_LOSS_RATE'] as $index => $code) {
                $definition = $this->approvedDefinition($request, $period, $code);
                if (! $definition) {
                    continue;
                }
                $data[$index]['definition_id'] = $definition->id;
                $data[$index]['calculation_version'] = $definition->formula_code.':v'.$definition->version;
                if ($index === 0 && ! $request->filled('product_id')) {
                    $products = DB::table('kpi_definition_products')->where('definition_id', $definition->id)->pluck('product_id')->all();
                    $rows = array_values(array_filter($this->comparison($request)->getData(true)['data'], fn (array $row) => in_array($row['product_id'], $products, true)));
                    if (count($rows) === count($products) && count($products) > 0 && count(array_filter($rows, fn (array $r) => $r['actual'] === null || $r['target'] === null || $r['status'] === 'source_conflict' || $r['unit_code'] !== 't')) === 0) {
                        $actual = '0';
                        $target = '0';
                        foreach ($rows as $row) {
                            $actual = bcadd($actual, $row['actual'], 6);
                            $target = bcadd($target, $row['target'], 6);
                        }
                        $data[$index]['actual'] = $actual;
                        $data[$index]['target'] = $target;
                        $data[$index]['value'] = self::percentage($actual, $target);
                        $data[$index]['status'] = $data[$index]['value'] === null ? 'zero_target' : 'valid';
                        $data[$index]['reason'] = null;
                    } else {
                        $data[$index]['status'] = 'incomplete_product_group';
                        $data[$index]['reason'] = 'Every approved non-overlapping product must have comparable actual and target observations.';
                    }
                } elseif ($index === 1) {
                    [$energy, $energyStatus] = $this->energyTotal($request, $period, $definition->id);
                    $data[$index]['value'] = $energy !== null && $crude !== null && bccomp($crude, '0', 6) > 0 ? bcdiv($energy, $crude, 6) : null;
                    $data[$index]['status'] = $data[$index]['value'] === null ? $energyStatus : 'valid';
                    $data[$index]['reason'] = $data[$index]['value'] === null ? 'Approved carrier factors, complete energy observations and a positive crude denominator are required.' : null;
                    $data[$index]['energy_gj'] = $energy;
                    $data[$index]['crude_t'] = $crude;
                } elseif ($index === 2) {
                    $loss = $this->measurementTotal($request, $period, 'loss_records', 'loss', $definition->loss_category_code, 'category_code');
                    $data[$index]['value'] = self::percentage($loss, $crude);
                    $data[$index]['status'] = $data[$index]['value'] === null ? 'missing_or_zero_denominator' : 'valid';
                    $data[$index]['reason'] = $data[$index]['value'] === null ? 'Approved loss and crude observations are required.' : null;
                    $data[$index]['loss_t'] = $loss;
                    $data[$index]['crude_t'] = $crude;
                }
            }
        }
        foreach ($data as &$kpi) {
            $kpi['scope'] = $scope;
            $kpi['source_status'] = 'approved_only';
        }

        return response()->json(['data' => $data]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $this->authorize($request);
        $request->validate(['period_month' => 'sometimes|date_format:Y-m', 'facility_id' => 'sometimes|uuid', 'unit_id' => 'sometimes|uuid', 'product_id' => 'sometimes|uuid']);
        $month = $request->input('period_month', now('Africa/Tripoli')->format('Y-m'));
        $start = CarbonImmutable::parse($month.'-01', 'Africa/Tripoli');
        $from = $start->utc();
        $until = $start->addMonth()->utc();
        $counts = [];
        $periodCounts = [];
        $scoped = function (string $table) use ($request): Builder {
            $query = OperationsController::scoped(DB::table($table), $request, 'engineering.read');
            foreach (['facility_id', 'unit_id'] as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->input($field));
                }
            }
            return $query;
        };
        foreach (['production_records', 'production_target_plans', 'operational_events', 'laboratory_samples'] as $table) {
            $query = $scoped($table);
            $counts[$table] = [
                'total' => (clone $query)->count(),
                'approved' => (clone $query)->whereIn('status', ['approved', 'acknowledged', 'closed'])->count(),
            ];
            $periodQuery = $table === 'operational_events'
                ? $query->where('started_at', '>=', $from)->where('started_at', '<', $until)
                : $query->where('period_month', $month);
            $periodCounts[$table] = $periodQuery->select('status')->selectRaw('COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        }
        $events = $scoped('operational_events')->where('started_at', '<', $until)
            ->where(fn ($q) => $q->where('started_at', '>=', $from)
                ->orWhere('actual_end_at', '>', $from)
                ->orWhere(fn ($open) => $open->whereNull('actual_end_at')->whereNotIn('status', ['closed', 'cancelled'])))
            ->orderByDesc('started_at')->limit(10)->get();

        $comparison = $this->comparison($request)->getData(true)['data'];
        $trend = [];
        if ($request->filled('product_id')) {
            for ($offset = 5; $offset > 0; $offset--) {
                $trendRequest = $request->duplicate(array_replace($request->query(), [
                    'period_month' => $start->subMonths($offset)->format('Y-m'),
                ]));
                array_push($trend, ...$this->comparison($trendRequest)->getData(true)['data']);
            }
            array_push($trend, ...$comparison);
        }
        $downtime = null;
        $cutoff = $until->min(CarbonImmutable::now('UTC'));
        if ($request->filled('unit_id') && $cutoff->gt($from)) {
            $intervals = $scoped('unit_status_intervals')->where('status', 'approved')
                ->whereIn('state', ['trip', 'maintenance', 'shutdown'])
                ->where('known_at', '<=', $cutoff)->where('approved_at', '<=', $cutoff)
                ->where('started_at', '<', $cutoff)
                ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', $from))
                ->get(['started_at', 'ended_at as actual_end_at']);
            if ($intervals->isNotEmpty()) {
                $downtime = self::downtimeSeconds($intervals->map(fn ($row) => (array) $row)->all(), $from->toIso8601String(), $cutoff->toIso8601String());
            }
        }

        return response()->json(['data' => [
            'counts' => $counts,
            'period_counts' => $periodCounts,
            'period_month' => $month,
            'kpis' => $this->kpis($request)->getData(true)['data'],
            'comparisons' => $comparison,
            'trend' => $trend,
            'events' => $events,
            'open_events' => $events->whereNotIn('status', ['closed', 'cancelled'])->values(),
            'downtime_seconds' => $downtime,
            'data_status' => 'approved_only',
            'as_of' => now('UTC')->toIso8601String(),
        ]]);
    }

    public function definitions(Request $request): JsonResponse
    {
        $this->authorize($request);
        $rows = OperationsController::scoped(DB::table('kpi_definitions'), $request, 'engineering.read')->orderByDesc('created_at')->limit(100)->get();
        foreach ($rows as $row) {
            $row->products = DB::table('kpi_definition_products')->where('definition_id', $row->id)->pluck('product_id');
            $row->energy_factors = DB::table('energy_conversion_factors')->where('definition_id', $row->id)->get();
        }

        return response()->json(['data' => $rows]);
    }

    public function storeDefinition(Request $request): JsonResponse
    {
        $data = $request->validate([
            'facility_id' => 'required|uuid|exists:facilities,id', 'unit_id' => 'nullable|uuid|exists:refinery_units,id',
            'code' => ['required', Rule::in(['PRODUCTION_TARGET_ACHIEVEMENT', 'SPECIFIC_ENERGY_CONSUMPTION', 'REFINERY_LOSS_RATE'])],
            'title' => 'required|string|max:255', 'formula_code' => ['required', Rule::in(['production_achievement_pct', 'specific_energy_gj_per_t', 'unaccounted_loss_pct'])],
            'loss_category_code' => 'nullable|string|max:64', 'effective_from' => 'required|date_format:Y-m-d', 'effective_until' => 'nullable|date_format:Y-m-d|after_or_equal:effective_from',
            'source_reference' => 'required|string|max:500', 'reason' => 'required|string|min:10|max:2000',
            'product_ids' => 'sometimes|array', 'product_ids.*' => 'uuid|distinct|exists:product_catalog,id',
            'energy_factors' => 'sometimes|array', 'energy_factors.*.carrier_code' => 'required|string|max:64',
            'energy_factors.*.input_unit_code' => 'required|exists:measurement_units,code',
            'energy_factors.*.gj_per_unit' => ['required', 'regex:/^\d{1,11}(\.\d{1,9})?$/', 'gt:0'],
            'energy_factors.*.basis' => ['required', Rule::in(['LHV', 'HHV', 'site_electricity', 'source_electricity'])],
            'energy_factors.*.source_reference' => 'required|string|max:500',
        ]);
        $this->authorizeScope($request, 'production.approve', $data['facility_id'], $data['unit_id'] ?? null);
        $expected = ['PRODUCTION_TARGET_ACHIEVEMENT' => 'production_achievement_pct', 'SPECIFIC_ENERGY_CONSUMPTION' => 'specific_energy_gj_per_t', 'REFINERY_LOSS_RATE' => 'unaccounted_loss_pct'];
        abort_unless($expected[$data['code']] === $data['formula_code'], 422, 'Formula does not match the KPI code.');
        abort_if($data['code'] === 'SPECIFIC_ENERGY_CONSUMPTION' && empty($data['energy_factors']), 422, 'Energy factors with source and basis are required.');
        abort_if($data['code'] === 'REFINERY_LOSS_RATE' && empty($data['loss_category_code']), 422, 'Declare the approved loss category explicitly.');
        abort_if($data['code'] === 'PRODUCTION_TARGET_ACHIEVEMENT' && empty($data['product_ids']), 422, 'Declare a non-overlapping product group for aggregate achievement.');

        return DB::transaction(function () use ($request, $data): JsonResponse {
            $products = $data['product_ids'] ?? [];
            $factors = $data['energy_factors'] ?? [];
            unset($data['product_ids'], $data['energy_factors']);
            $latest = DB::table('kpi_definitions')->where('facility_id', $data['facility_id'])->where('unit_id', $data['unit_id'] ?? null)->where('code', $data['code'])->orderByDesc('version')->lockForUpdate()->first();
            $data += ['id' => (string) Str::uuid(), 'version' => ($latest->version ?? 0) + 1, 'status' => 'pending', 'created_by' => $request->user()->id, 'created_at' => now('UTC')->format('Y-m-d H:i:s.u')];
            DB::table('kpi_definitions')->insert($data);
            foreach ($products as $product) {
                DB::table('kpi_definition_products')->insert(['definition_id' => $data['id'], 'product_id' => $product]);
            }
            foreach ($factors as $factor) {
                DB::table('energy_conversion_factors')->insert($factor + ['id' => (string) Str::uuid(), 'definition_id' => $data['id']]);
            }
            Governance::audit($request, 'create', 'kpi_definitions', $data['id'], null, $data, $data['reason']);

            return response()->json(['data' => $data], 201);
        });
    }

    public function approveDefinition(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|min:10|max:2000']);

        return DB::transaction(function () use ($request, $id, $data): JsonResponse {
            $definition = DB::table('kpi_definitions')->where('id', $id)->lockForUpdate()->first();
            abort_unless($definition, 404);
            $this->authorizeScope($request, 'planning.approve', $definition->facility_id, $definition->unit_id);
            abort_if($definition->created_by === $request->user()->id, 403, 'Independent approval of the engineering definition is required.');
            abort_unless($definition->status === 'pending', 409, 'Definition is no longer pending.');
            $scope = $definition->facility_id.':'.($definition->unit_id ?? 'FACILITY').':'.$definition->code;
            $old = DB::table('kpi_definitions')->where('active_scope', $scope)->lockForUpdate()->first();
            if ($old) {
                abort_unless($old->version < $definition->version, 409, 'A newer definition is already approved.');
                DB::table('kpi_definitions')->where('id', $old->id)->update(['status' => 'superseded', 'active_scope' => null]);
                Governance::audit($request, 'supersede', 'kpi_definitions', $old->id, (array) $old, ['status' => 'superseded'], $data['reason']);
            }
            $changes = ['status' => 'approved', 'active_scope' => $scope, 'approved_by' => $request->user()->id, 'approved_at' => now('UTC')->format('Y-m-d H:i:s.u')];
            DB::table('kpi_definitions')->where('id', $id)->update($changes);
            $after = array_merge((array) $definition, $changes);
            Governance::audit($request, 'approve', 'kpi_definitions', $id, (array) $definition, $after, $data['reason']);

            return response()->json(['data' => $after]);
        });
    }

    public function annotations(Request $request): JsonResponse
    {
        $this->authorize($request);
        $query = OperationsController::scoped(DB::table('engineering_annotations'), $request, 'engineering.read');
        foreach (['facility_id', 'unit_id', 'period_month', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        return response()->json(['data' => $query->orderByDesc('created_at')->limit(100)->get()]);
    }

    public function storeAnnotation(Request $request): JsonResponse
    {
        $data = $request->validate(['facility_id' => 'required|uuid|exists:facilities,id', 'unit_id' => 'nullable|uuid|exists:refinery_units,id', 'period_month' => 'required|date_format:Y-m', 'title' => 'required|string|max:255', 'analysis' => 'required|string|max:10000', 'hypothesis' => 'nullable|string|max:10000', 'source_reference' => 'required|string|max:500']);
        $this->authorizeScope($request, 'production.approve', $data['facility_id'], $data['unit_id'] ?? null);

        return DB::transaction(function () use ($request, $data): JsonResponse {
            $data += ['id' => (string) Str::uuid(), 'status' => 'pending', 'created_by' => $request->user()->id, 'created_at' => now('UTC')->format('Y-m-d H:i:s.u')];
            DB::table('engineering_annotations')->insert($data);
            Governance::audit($request, 'create', 'engineering_annotations', $data['id'], null, $data);

            return response()->json(['data' => $data], 201);
        });
    }

    public function approveAnnotation(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|min:10|max:2000']);

        return DB::transaction(function () use ($request, $id, $data): JsonResponse {
            $row = DB::table('engineering_annotations')->where('id', $id)->lockForUpdate()->first();
            abort_unless($row, 404);
            $this->authorizeScope($request, 'production.approve', $row->facility_id, $row->unit_id);
            abort_if($row->created_by === $request->user()->id, 403, 'Independent reviewer required.');
            abort_unless($row->status === 'pending', 409);
            $changes = ['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now('UTC')->format('Y-m-d H:i:s.u')];
            DB::table('engineering_annotations')->where('id', $id)->update($changes);
            Governance::audit($request, 'approve', 'engineering_annotations', $id, (array) $row, array_merge((array) $row, $changes), $data['reason']);

            return response()->json(['data' => array_merge((array) $row, $changes)]);
        });
    }

    public function export(Request $request): Response
    {
        $this->authorize($request);
        $request->validate(['period_month' => 'required|date_format:Y-m', 'facility_id' => 'required|uuid|exists:facilities,id', 'unit_id' => 'nullable|uuid|exists:refinery_units,id', 'format' => ['sometimes', Rule::in(['csv', 'xlsx', 'pdf', 'html', 'json'])]]);
        $this->authorizeScope($request, 'reports.export', $request->input('facility_id'), $request->input('unit_id'));
        $snapshot = ['period_month' => $request->input('period_month'), 'facility_id' => $request->input('facility_id'), 'unit_id' => $request->input('unit_id'), 'generated_at' => now('UTC')->toIso8601String(), 'source_status' => 'approved_only', 'comparisons' => $this->comparison($request)->getData(true)['data'], 'kpis' => $this->kpis($request)->getData(true)['data'], 'annotations' => OperationsController::scoped(DB::table('engineering_annotations'), $request, 'engineering.read')->where('facility_id', $request->input('facility_id'))->where('unit_id', $request->input('unit_id'))->where('period_month', $request->input('period_month'))->where('status', 'approved')->get()->all()];
        $rows = [['section', 'period_month', 'product_or_kpi', 'actual', 'target', 'value', 'unit', 'status', 'source_reference']];
        foreach ($snapshot['comparisons'] as $row) {
            $rows[] = ['comparison', $snapshot['period_month'], $row['product_code'], $row['actual'], $row['target'], $row['achievement_pct'], $row['unit_code'], $row['status'], json_encode($row['source_observation_ids'] ?? ['plan_id' => $row['plan_id'] ?? null], JSON_THROW_ON_ERROR)];
        }
        foreach ($snapshot['kpis'] as $row) {
            $rows[] = ['kpi', $snapshot['period_month'], $row['code'], $row['actual'] ?? null, $row['target'] ?? null, $row['value'], $row['unit'], $row['status'], $row['definition_id'] ?? $row['reason'] ?? ''];
        }
        foreach ($snapshot['annotations'] as $row) {
            $rows[] = ['annotation', $snapshot['period_month'], $row->title, null, null, $row->analysis, '', $row->status, $row->source_reference];
        }
        $format = $request->input('format', 'csv');
        $html = $this->reportHtml($snapshot, $rows);
        $contentType = match ($format) {
            'csv' => 'text/csv; charset=UTF-8', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'pdf' => 'application/pdf', 'html' => 'text/html; charset=UTF-8', default => 'application/json'
        };
        $body = match ($format) {
            'csv' => $this->csv($rows), 'xlsx' => $this->xlsx($rows), 'pdf' => $this->pdf($html), 'html' => $html, default => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
        };
        $id = (string) Str::uuid();
        DB::transaction(function () use ($request, $snapshot, $format, $body, $id): void {
            DB::table('report_exports')->insert(['id' => $id, 'facility_id' => $snapshot['facility_id'], 'unit_id' => $snapshot['unit_id'], 'period_month' => $snapshot['period_month'], 'format' => $format, 'sha256' => hash('sha256', $body), 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'created_by' => $request->user()->id, 'created_at' => now('UTC')->format('Y-m-d H:i:s.u')]);
            Governance::audit($request, 'export', 'monthly_report', $id, null, ['period_month' => $snapshot['period_month'], 'format' => $format, 'sha256' => hash('sha256', $body), 'comparison_rows' => count($snapshot['comparisons'])]);
        });

        return response($body, 200, ['Content-Type' => $contentType, 'Content-Disposition' => ($format === 'html' ? 'inline' : 'attachment').'; filename="IDPS-'.$snapshot['period_month'].'.'.$format.'"', 'X-Report-ID' => $id, 'Cache-Control' => 'private, no-store']);
    }

    private function mergeMonthlyComparison(Request $request, string $period, array $data): array
    {
        if ($request->filled('unit_id')) {
            return $data;
        }
        $query = $this->monthlyFacts($request)->where('period_month', $period)->where('metric_code', 'product');
        if ($request->filled('product_id')) {
            $query->where('item_code', DB::table('product_catalog')->where('id', $request->input('product_id'))->value('code'));
        }
        $grouped = [];
        foreach ($query->get() as $fact) {
            $product = DB::table('product_catalog')->where('code', $fact->item_code)->first();
            if (! $product) {
                continue;
            }
            $key = $fact->facility_id.'|'.$product->id.'|'.$fact->unit_code;
            $grouped[$key] ??= ['facility_id' => $fact->facility_id, 'unit_id' => null, 'product_id' => $product->id, 'product_code' => $product->code, 'period_month' => $period, 'unit_code' => $fact->unit_code, 'actual' => null, 'target' => null, 'plan_id' => null, 'source_observation_ids' => [], 'source_status' => 'approved', 'calculation_version' => 'achievement_v1'];
            $measure = $fact->actual_or_target;
            if (! in_array($measure, ['actual', 'target'], true)) {
                continue;
            }
            if ($grouped[$key][$measure] !== null) {
                $grouped[$key]['source_conflict'] = true;
            }
            $grouped[$key][$measure] = $fact->quantity;
            $grouped[$key]['source_observation_ids'][] = $fact->id;
        }
        foreach ($grouped as $row) {
            $existing = null;
            foreach ($data as $index => $entered) {
                if ($entered['facility_id'] === $row['facility_id'] && empty($entered['unit_id']) && $entered['product_id'] === $row['product_id'] && $entered['unit_code'] === $row['unit_code']) {
                    $existing = $index;
                    foreach (['actual', 'target'] as $measure) {
                        if ($entered[$measure] !== null && $row[$measure] !== null && bccomp($entered[$measure], $row[$measure], 6) !== 0) {
                            $row['source_conflict'] = true;
                        }
                        $row[$measure] ??= $entered[$measure];
                    }
                    $row['plan_id'] = $entered['plan_id'];
                    break;
                }
            }
            $row['variance'] = $row['actual'] !== null && $row['target'] !== null ? bcsub($row['actual'], $row['target'], 6) : null;
            $row['achievement_pct'] = self::percentage($row['actual'], $row['target']);
            $row['status'] = $row['actual'] === null ? 'missing_actual' : ($row['target'] === null ? 'missing_target' : ($row['achievement_pct'] === null ? 'zero_target' : 'valid'));
            if ($row['source_conflict'] ?? false) {
                $row['status'] = 'source_conflict';
                $row['achievement_pct'] = null;
                $row['variance'] = null;
            }
            if ($existing !== null) {
                $data[$existing] = $row;
            } else {
                $data[] = $row;
            }
        }

        return $data;
    }

    private function monthlyFacts(Request $request): Builder
    {
        $query = DB::table('monthly_observations')->where('status', 'approved')->where('report_status', 'actual')->whereExists(function (Builder $q) use ($request): void {
            $q->selectRaw('1')->from('user_roles as ur')->join('role_permissions as rp', 'rp.role_id', '=', 'ur.role_id')->join('permissions as p', 'p.id', '=', 'rp.permission_id')->where('ur.user_id', $request->user()->id)->where('p.code', 'engineering.read')->whereNull('ur.unit_id')->where(fn (Builder $s) => $s->whereNull('ur.facility_id')->orWhereColumn('ur.facility_id', 'monthly_observations.facility_id'));
        });
        if ($request->filled('facility_id')) {
            $query->where('facility_id', $request->input('facility_id'));
        }

        return $query;
    }

    private function authorizeScope(Request $request, string $permission, string $facilityId, ?string $unitId): void
    {
        abort_unless($request->user(), 401);
        if ($unitId !== null) {
            abort_unless(DB::table('refinery_units')->where('id', $unitId)->where('facility_id', $facilityId)->exists(), 422, 'Unit does not belong to facility.');
        }
        $query = DB::table('user_roles as ur')->join('role_permissions as rp', 'rp.role_id', '=', 'ur.role_id')->join('permissions as p', 'p.id', '=', 'rp.permission_id')->where('ur.user_id', $request->user()->id)->where('p.code', $permission)->where(fn (Builder $q) => $q->whereNull('ur.facility_id')->orWhere('ur.facility_id', $facilityId))->where(fn (Builder $q) => $q->whereNull('ur.unit_id')->orWhere('ur.unit_id', $unitId));
        abort_unless($query->exists(), 403, 'Permission denied for this facility/unit.');
    }

    public static function safeCsvCell(mixed $value): string
    {
        $text = (string) ($value ?? '');

        return preg_match('/^[\s]*[=+@\-]/u', $text) ? "'".$text : $text;
    }

    private function csv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($stream, array_map(self::safeCsvCell(...), $row), ',', '"', '');
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return $contents;
    }

    private function xlsx(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'idps-report-');
        $zip = new ZipArchive;
        abort_unless($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 503, 'Report archive cannot be created.');
        try {
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="IDPS Monthly Report" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
            $xml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView rightToLeft="1" workbookViewId="0"/></sheetViews><cols><col min="1" max="9" width="25" customWidth="1"/></cols><sheetData>';
            foreach ($rows as $index => $row) {
                $xml .= '<row r="'.($index + 1).'">';
                foreach ($row as $column => $value) {
                    $ref = chr(65 + $column).($index + 1);
                    $xml .= '<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
                }
                $xml .= '</row>';
            }
            $zip->addFromString('xl/worksheets/sheet1.xml', $xml.'</sheetData><autoFilter ref="A1:I'.count($rows).'"/></worksheet>');
            $zip->close();

            return file_get_contents($path);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function reportHtml(array $snapshot, array $rows): string
    {
        $html = '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><style>body{font-family:"DejaVu Sans",sans-serif;color:#163b39;font-size:10px}h1{font-size:20px}table{width:100%;border-collapse:collapse}td,th{border:1px solid #bccac8;padding:5px;word-wrap:break-word}th{background:#e5efeb}p{line-height:1.7}</style><body><h1>IDPS — تقرير الإنتاج الشهري</h1><p>'.e($snapshot['period_month']).' | '.e($snapshot['generated_at']).'</p><p>Approved observations only. Definitions awaiting engineering signoff remain N/A. No live plant connection.</p><table>';
        foreach ($rows as $index => $row) {
            $tag = $index === 0 ? 'th' : 'td';
            $html .= '<tr>';
            foreach ($row as $value) {
                $html .= '<'.$tag.'>'.e((string) ($value ?? 'N/A')).'</'.$tag.'>';
            }
            $html .= '</tr>';
        }

        return $html.'</table></body></html>';
    }

    private function pdf(string $html): string
    {
        abort_unless(class_exists(Dompdf::class), 503, 'PDF renderer is not installed.');
        if (class_exists(Arabic::class)) {
            $arabic = new Arabic;
            $html = preg_replace_callback('/>[^<]*[\x{0600}-\x{06FF}][^<]*</u', fn (array $match) => '>'.$arabic->utf8Glyphs(substr($match[0], 1, -1)).'<', $html);
        }
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        return $pdf->output();
    }

    private function approvedDefinition(Request $request, string $period, string $code): ?object
    {
        $start = CarbonImmutable::parse($period.'-01', 'Africa/Tripoli');

        return OperationsController::scoped(DB::table('kpi_definitions'), $request, 'engineering.read')->where('facility_id', $request->input('facility_id'))->where('unit_id', $request->input('unit_id'))->where('code', $code)->whereIn('status', ['approved', 'superseded'])->where('effective_from', '<=', $start->toDateString())->where(fn (Builder $q) => $q->whereNull('effective_until')->orWhere('effective_until', '>=', $start->endOfMonth()->toDateString()))->orderByDesc('version')->first();
    }

    private function measurementTotal(Request $request, string $period, string $table, string $metric, string $item, ?string $field = null): ?string
    {
        $typed = $this->facts($table, $request)->where('period_month', $period)->where('unit_code', 't');
        if ($field !== null) {
            $typed->where($field, $item);
        }
        $entered = $this->sum($typed);
        if ($request->filled('unit_id')) {
            return $entered;
        }
        $history = $this->sum($this->monthlyFacts($request)->where('period_month', $period)->where('unit_code', 't')->where('metric_code', $metric)->where('item_code', $item)->where('actual_or_target', 'actual'));

        return $this->singleSourceTotal($entered, $history);
    }

    private function singleSourceTotal(?string $entered, ?string $imported): ?string
    {
        if ($entered !== null && $imported !== null && bccomp($entered, $imported, 6) !== 0) {
            return null;
        }

        return $entered ?? $imported;
    }

    private function fuelTotal(Request $request, string $period): ?string
    {
        $carriers = ['FUEL_GAS', 'FUEL_OIL', 'LPG', 'DIESEL', 'HFO', 'HEAVY_FUEL_OIL'];
        $entered = $this->sum($this->facts('energy_consumptions', $request)->where('period_month', $period)->where('unit_code', 't')->whereIn('carrier_code', $carriers));
        if ($request->filled('unit_id')) {
            return $entered;
        }
        $history = $this->sum($this->monthlyFacts($request)->where('period_month', $period)->where('unit_code', 't')->where('metric_code', 'fuel')->whereIn('item_code', $carriers)->where('actual_or_target', 'actual'));

        return $this->singleSourceTotal($entered, $history);
    }

    /** All carriers declared by an approved definition must be observed; missing is never zero. */
    private function energyTotal(Request $request, string $period, string $definitionId): array
    {
        $entered = $this->facts('energy_consumptions', $request)->where('period_month', $period)->select('carrier_code', 'unit_code')->selectRaw('SUM(quantity) AS quantity, COUNT(quantity) AS observed')->groupBy('carrier_code', 'unit_code')->get();
        $quantities = [];
        foreach ($entered as $row) {
            if ($row->observed > 0) {
                $quantities[$row->carrier_code.'|'.$row->unit_code] = $row->quantity;
            }
        }
        if (! $request->filled('unit_id')) {
            $historical = $this->monthlyFacts($request)->where('period_month', $period)->where('metric_code', 'fuel')->where('item_code', '!=', 'REPORTED_TOTAL')->where('actual_or_target', 'actual')->select('item_code', 'unit_code')->selectRaw('SUM(quantity) AS quantity, COUNT(quantity) AS observed')->groupBy('item_code', 'unit_code')->get();
            foreach ($historical as $row) {
                $key = $row->item_code.'|'.$row->unit_code;
                if ($row->observed === 0) {
                    continue;
                }
                if (array_key_exists($key, $quantities) && bccomp($quantities[$key], $row->quantity, 6) !== 0) {
                    return [null, 'source_conflict'];
                }
                $quantities[$key] = $row->quantity;
            }
        }
        $factors = DB::table('energy_conversion_factors')->where('definition_id', $definitionId)->get();
        if ($quantities === [] || $factors->isEmpty()) {
            return [null, 'missing_energy_or_factors'];
        }
        $total = '0';
        $covered = [];
        foreach ($factors as $factor) {
            $key = $factor->carrier_code.'|'.$factor->input_unit_code;
            if (! array_key_exists($key, $quantities)) {
                return [null, 'missing_declared_carrier'];
            }
            $total = bcadd($total, bcmul($quantities[$key], $factor->gj_per_unit, 9), 9);
            $covered[] = $key;
        }
        if (array_diff(array_keys($quantities), $covered) !== []) {
            return [null, 'unmapped_energy_carrier'];
        }

        return [$total, 'valid'];
    }

    private function facts(string $table, Request $request): Builder
    {
        $query = OperationsController::scoped(DB::table($table), $request, 'engineering.read')->where('status', 'approved');
        foreach (['facility_id', 'unit_id'] as $key) {
            if ($request->filled($key)) {
                $query->where($key, $request->input($key));
            }
        }
        if (! $request->filled('unit_id')) {
            $query->whereNull('unit_id');
        }

        return $query;
    }

    private function sum(Builder $query): ?string
    {
        $row = $query->selectRaw('SUM(quantity) AS total, COUNT(quantity) AS observed')->first();

        return $row->observed > 0 ? $row->total : null;
    }

    private function authorize(Request $request): void
    {
        abort_unless($request->user(), 401);
        abort_unless(DB::table('user_roles as ur')->join('role_permissions as rp', 'rp.role_id', '=', 'ur.role_id')->join('permissions as p', 'p.id', '=', 'rp.permission_id')->where('ur.user_id', $request->user()->id)->where('p.code', 'engineering.read')->exists(), 403);
    }
}
