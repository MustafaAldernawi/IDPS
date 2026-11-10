<?php

namespace App\Http\Controllers;

use App\Jobs\PredictForecast;
use App\Jobs\TrainCandidate;
use App\Services\Governance;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AiController extends Controller
{
    public static function canonical(array $rows): string
    {
        $sort = function ($value) use (&$sort) {
            if (! is_array($value)) {
                return $value;
            }if (! array_is_list($value)) {
                ksort($value, SORT_STRING);
            }

return array_map($sort, $value);
        };

        return json_encode($sort($rows), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    private static function stamp(?string $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse($value, 'UTC')->utc()->format('Y-m-d\TH:i:s.u\Z');
    }

    private function facility(?string $value): string
    {
        if ($value) {
            abort_unless(DB::table('facilities')->where('id', $value)->exists(), 422, 'Unknown facility');

            return $value;
        }
        $ids = DB::table('facilities')->where('active', true)->pluck('id');
        abort_unless($ids->count() === 1, 422, 'Select an explicit facility');

        return $ids->first();
    }

    public static function rows($observations): array
    {
        return collect($observations)->map(fn ($row) => ['period_month' => $row->period_month, 'quantity' => $row->quantity === null ? null : (string) $row->quantity, 'available_at' => self::stamp($row->available_at), 'approved_at' => self::stamp($row->approved_at), 'status' => $row->status === 'approved' || $row->status === 'superseded' ? 'approved' : 'provisional'])->values()->all();
    }

    public function datasets(Request $request)
    {
        Governance::authorize($request, 'dataset.read');

        return response()->json(['data' => DB::table('dataset_snapshots')->select(['id', 'facility_id', 'name', 'product_code', 'mode', 'dataset_sha256', 'quality_warning', 'created_at'])->orderByDesc('created_at')->limit(100)->get()]);
    }

    public function storeDataset(Request $request)
    {
        Governance::authorize($request, 'dataset.write');
        $data = $request->validate(['name' => 'required|string|max:255', 'product_code' => 'required|string|max:64', 'mode' => 'required|in:approved,research_only', 'facility_id' => 'nullable|uuid']);
        $facility = $this->facility($data['facility_id'] ?? null);
        abort_unless(DB::table('product_catalog')->where('code', $data['product_code'])->exists(), 422, 'Unknown product code');
        $query = DB::table('monthly_observations')->where('facility_id', $facility)->where('metric_code', 'product')->where('item_code', $data['product_code'])->where('actual_or_target', 'actual')->where('report_status', 'actual')->where('unit_code', 't');
        $query = $data['mode'] === 'approved' ? $query->where('status', 'approved') : $query->whereIn('status', ['staged', 'flagged', 'reviewed', 'approved']);
        $observations = $query->orderBy('period_month')->get();
        abort_if($observations->isEmpty(), 422, 'No eligible monthly source observations');
        abort_if($observations->pluck('period_month')->unique()->count() !== $observations->count(), 422, 'Ambiguous source revisions require reconciliation before a snapshot');
        if ($data['mode'] === 'approved') {
            abort_if($observations->contains(fn ($row) => ! $row->approved_at || ! $row->approved_by), 422, 'Missing authenticated source approval');
        }
        $rows = self::rows($observations);
        $sha = hash('sha256', self::canonical($rows));
        $existing = DB::table('dataset_snapshots')->where(['facility_id' => $facility, 'product_code' => $data['product_code'], 'mode' => $data['mode'], 'dataset_sha256' => $sha])->first();
        if ($existing) {
            return response()->json(['data' => $this->datasetView($existing)], 200);
        }
        $id = (string) Str::uuid();
        DB::transaction(function () use ($request, $data, $facility, $rows, $observations, $sha, $id) {
            DB::table('dataset_snapshots')->insert(['id' => $id, 'facility_id' => $facility, 'name' => $data['name'], 'product_code' => $data['product_code'], 'mode' => $data['mode'], 'dataset_sha256' => $sha, 'snapshot_json' => self::canonical($rows), 'source_observation_ids' => json_encode($observations->pluck('id')->all(), JSON_THROW_ON_ERROR), 'quality_warning' => $data['mode'] === 'research_only' ? 'PROVISIONAL research dataset; never an approved operational model.' : 'Only approved source facts; model acceptance is a separate decision.', 'created_by' => $request->user()->id, 'created_at' => now('UTC')]);
            Governance::audit($request, 'dataset.snapshot', 'dataset_snapshot', $id, null, ['dataset_sha256' => $sha, 'rows' => count($rows), 'mode' => $data['mode']]);
        });

        return response()->json(['data' => $this->datasetView(DB::table('dataset_snapshots')->where('id', $id)->first())], 201);
    }

    private function datasetView($row): array
    {
        $data = (array) $row;
        unset($data['snapshot_json'],$data['source_observation_ids']);

        return $data;
    }

    public function trainingRuns(Request $request)
    {
        Governance::authorize($request, 'ai.read');

        return response()->json(['data' => DB::table('training_runs')->orderByDesc('created_at')->limit(100)->get()->map(fn ($row) => $this->decode($row, ['result_json']))]);
    }

    public function train(Request $request)
    {
        Governance::authorize($request, 'ai.train');
        $data = $request->validate(['dataset_id' => 'required|uuid|exists:dataset_snapshots,id', 'seed' => 'sometimes|integer|min:0|max:2147483647', 'idempotency_key' => 'sometimes|string|max:128']);
        $seed = (int) ($data['seed'] ?? 42);
        $command = hash('sha256', self::canonical(['dataset_id' => $data['dataset_id'], 'seed' => $seed]));
        $key = $data['idempotency_key'] ?? $request->header('Idempotency-Key') ?? $command;
        abort_if(strlen($key) > 128, 422, 'Invalid idempotency key');
        [$run,$created] = DB::transaction(function () use ($request, $data, $seed, $command, $key) {
            DB::table('dataset_snapshots')->where('id', $data['dataset_id'])->lockForUpdate()->first();
            $existing = DB::table('training_runs')->where('idempotency_key', $key)->first();
            if ($existing) {
                abort_unless($existing->command_sha256 === $command, 409, 'Idempotency key payload conflict');

                return [$existing, false];
            }
            $id = (string) Str::uuid();
            DB::table('training_runs')->insert(['id' => $id, 'dataset_id' => $data['dataset_id'], 'trained_by' => $request->user()->id, 'status' => 'queued', 'seed' => $seed, 'idempotency_key' => $key, 'command_sha256' => $command, 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
            Governance::audit($request, 'training.queued', 'training_run', $id, null, ['dataset_id' => $data['dataset_id'], 'seed' => $seed]);

            return [DB::table('training_runs')->where('id', $id)->first(), true];
        });
        if ($run->status === 'queued' && ! $run->enqueued_at) {
            TrainCandidate::dispatch($run->id)->afterCommit();
            DB::table('training_runs')->where('id', $run->id)->update(['enqueued_at' => now('UTC')]);
        }

        return response()->json(['data' => $run], 202);
    }

    public function retrain(Request $request)
    {
        return $this->train($request);
    }

    public function models(Request $request)
    {
        Governance::authorize($request, 'ai.read');

        return response()->json(['data' => DB::table('model_versions')->select('model_versions.*')->selectSub(DB::table('ai_activation_requests')->select('status')->whereColumn('model_id', 'model_versions.id')->orderByDesc('created_at')->limit(1), 'activation_status')->orderByDesc('created_at')->limit(100)->get()->map(fn ($row) => $this->decode($row, ['evaluation_json']))]);
    }

    public function promote(Request $request, string $id)
    {
        return $this->activate($request, $id, 'promote');
    }

    public function rollback(Request $request, string $id)
    {
        return $this->activate($request, $id, 'rollback');
    }

    private function activate(Request $request, string $id, string $action)
    {
        Governance::authorize($request, 'ai.promote');
        $data = $request->validate(['reason' => 'required|string|min:10|max:2000', 'evaluation_reference' => 'required|string|max:500', 'confirm_independent_evaluation' => 'required|accepted']);
        $model = DB::table('model_versions')->where('id', $id)->first();
        abort_unless($model, 404);
        abort_unless($model->mode === 'approved', 409, 'Research-only models cannot be promoted');
        abort_if($model->trained_by === $request->user()->id, 403, 'Trainer cannot approve own model');
        if ($action === 'rollback') {
            abort_unless($model->approved_at, 409, 'Rollback requires an earlier approved model');
        }
        if ($model->status === 'active') {
            return response()->json(['data' => $model]);
        }
        $activation = DB::transaction(function () use ($request, $data, $model, $action) {
            DB::table('facilities')->where('id', $model->facility_id)->lockForUpdate()->first();
            $pending = DB::table('ai_activation_requests')->where('model_id', $model->id)->where('action', $action)->where('status', 'pending')->first();
            if ($pending) {
                return $pending;
            }
            $id = (string) Str::uuid();
            DB::table('ai_activation_requests')->insert(['id' => $id, 'model_id' => $model->id, 'actor_id' => $request->user()->id, 'action' => $action, 'reason' => $data['reason'], 'evaluation_reference' => $data['evaluation_reference'], 'created_at' => now('UTC')]);
            Governance::audit($request, 'model.activation_pending', 'model_version', $model->id, null, ['activation_id' => $id], $data['reason']);

            return DB::table('ai_activation_requests')->where('id', $id)->first();
        });
        try {
            abort_unless(config('idps_ai.key'), 503, 'AI service not configured');
            $response = Http::connectTimeout(5)->timeout(15)->withHeaders(['X-IDPS-Service-Key' => config('idps_ai.key')])->post(rtrim(config('idps_ai.url'), '/').'/internal/'.$action, ['model_id' => $model->service_model_id, 'artifact_sha256' => $model->artifact_sha256, 'approval_reference' => $activation->id, 'approved_by' => $activation->actor_id]);
            if (! $response->successful() || $response->json('status') !== 'approved_for_internal_serving' || $response->json('model_id') !== $model->service_model_id) {
                return response()->json(['data' => ['status' => 'activation_pending', 'activation_id' => $activation->id, 'reason' => 'AI service did not acknowledge activation']], 202);
            }
        } catch (ConnectionException) {
            return response()->json(['data' => ['status' => 'activation_pending', 'activation_id' => $activation->id]], 202);
        }
        DB::transaction(function () use ($request, $model, $activation) {
            DB::table('facilities')->where('id', $model->facility_id)->lockForUpdate()->first();
            $locked = DB::table('ai_activation_requests')->where('id', $activation->id)->lockForUpdate()->first();
            if ($locked->status === 'completed') {
                return;
            }
            DB::table('model_versions')->where('facility_id', $model->facility_id)->where('product_code', $model->product_code)->where('time_granularity', 'monthly')->where('prediction_horizon', 'next_month')->where('status', 'active')->update(['status' => 'archived']);
            DB::table('model_versions')->where('id', $model->id)->update(['status' => 'active', 'approved_by' => $activation->actor_id, 'approved_at' => now('UTC'), 'approval_reason' => $activation->reason]);
            DB::table('ai_activation_requests')->where('id', $activation->id)->update(['status' => 'completed', 'completed_at' => now('UTC')]);
            Governance::audit($request, 'model.activated', 'model_version', $model->id, ['status' => $model->status], ['status' => 'active', 'activation_id' => $activation->id], $activation->reason);
        });

        return response()->json(['data' => DB::table('model_versions')->where('id', $id)->first()]);
    }

    public function forecasts(Request $request)
    {
        Governance::authorize($request, 'ai.read');

        return response()->json(['data' => DB::table('forecast_runs')->orderByDesc('created_at')->limit(100)->get()->map(function ($row) {
            $data = $this->decode($row, ['result_json']);
            $data['scenarios'] = DB::table('forecast_scenarios')->where('forecast_run_id', $row->id)->get()->map(fn ($scenario) => $this->decode($scenario, ['assumptions_json']));

            return $data;
        })]);
    }

    public function forecast(Request $request)
    {
        Governance::authorize($request, 'ai.read');
        $data = $request->validate(['product_code' => 'required|string|max:64', 'period_month' => 'required|date_format:Y-m', 'as_of' => 'required|date', 'facility_id' => 'nullable|uuid', 'parent_run_id' => 'nullable|uuid|exists:forecast_runs,id', 'trigger_reference' => 'nullable|string|max:128']);
        abort_unless(preg_match('/(?:Z|\+00:00)$/', $data['as_of']), 422, 'as_of must contain an explicit UTC offset');
        $facility = $this->facility($data['facility_id'] ?? null);
        $asOf = CarbonImmutable::parse($data['as_of'])->utc();
        abort_unless($asOf->lt(CarbonImmutable::parse($data['period_month'].'-01', 'UTC')), 422, 'as_of must precede target month');
        $observations = DB::table('monthly_observations')->where('facility_id', $facility)->where('metric_code', 'product')->where('item_code', $data['product_code'])->where('actual_or_target', 'actual')->where('report_status', 'actual')->where('unit_code', 't')->whereIn('status', ['approved', 'superseded'])->whereNotNull('approved_at')->where('available_at', '<=', $asOf)->where('approved_at', '<=', $asOf)->where('period_month', '<', $data['period_month'])->orderBy('period_month')->get();
        $superseded = $observations->pluck('supersedes_id')->filter()->all();
        $observations = $observations->reject(fn ($row) => in_array($row->id, $superseded, true))->values();
        abort_if($observations->pluck('period_month')->unique()->count() !== $observations->count(), 422, 'Ambiguous historical source revisions');
        $rows = self::rows($observations);
        $model = DB::table('model_versions')->where('facility_id', $facility)->where('product_code', $data['product_code'])->where('status', 'active')->where('time_granularity', 'monthly')->where('prediction_horizon', 'next_month')->first();
        // A current model/plan approved after as_of is not historical knowledge.
        if ($model && (! $model->approved_at || CarbonImmutable::parse($model->approved_at, 'UTC')->gt($asOf))) {
            $model = null;
        }
        $plan = DB::table('production_target_plans')->where('facility_id', $facility)->whereNull('unit_id')->where('period_month', $data['period_month'])->whereIn('status', ['approved', 'superseded'])->where('available_at', '<=', $asOf)->where('approved_at', '<=', $asOf)->orderByDesc('revision_no')->first();
        $id = (string) Str::uuid();
        $status = $model ? 'queued' : 'unavailable';
        $engine = $model ? 'pending' : 'unavailable';
        $prediction = null;
        $result = ['status' => $status, 'quality_warning' => $model ? 'Awaiting asynchronous model inference.' : 'No approved active model; no forecast quantity fabricated.'];
        if (! $model) {
            $target = CarbonImmutable::parse($data['period_month'].'-01', 'UTC');
            $history = collect($rows)->keyBy('period_month');
            $previous = [];
            for ($lag = 1; $lag <= 3; $lag++) {
                $row = $history->get($target->subMonths($lag)->format('Y-m'));
                if (! $row || $row['quantity'] === null) {
                    $previous = [];
                    break;
                }$previous[] = $row;
            }
            if (count($previous) === 3) {
                $prediction = array_sum(array_map(fn ($row) => (float) $row['quantity'], $previous)) / 3;
                $status = 'completed';
                $engine = 'statistical_baseline';
                $result = ['status' => 'completed', 'engine_type' => $engine, 'method' => 'mean_previous_3_months', 'prediction' => $prediction, 'unit' => 't', 'source_approval_status' => 'approved', 'uncertainty_status' => 'not_calibrated', 'quality_warning' => 'No approved ML model. Explicit statistical baseline from three contiguous approved months.', 'source_periods' => array_column($previous, 'period_month')];
            }
        }
        if (isset($data['parent_run_id'])) {
            $parent = DB::table('forecast_runs')->where('id', $data['parent_run_id'])->first();
            abort_unless($parent->facility_id === $facility && $parent->product_code === $data['product_code'] && $parent->period_month === $data['period_month'], 422, 'Parent forecast scope mismatch');
        }
        $result['target_plan_id'] = $plan?->id;
        if (isset($data['parent_run_id'])) {
            $parent = DB::table('forecast_runs')->where('id', $data['parent_run_id'])->first();
            abort_unless($parent->target_plan_id === $plan?->id, 422, 'Parent forecast plan version mismatch');
        }
        DB::transaction(function () use ($request, $data, $facility, $model, $asOf, $rows, $id, $status, $result, $engine, $prediction, $plan) {
            DB::table('forecast_runs')->insert(['id' => $id, 'facility_id' => $facility, 'model_version_id' => $model?->id, 'requested_by' => $request->user()->id, 'product_code' => $data['product_code'], 'period_month' => $data['period_month'], 'as_of' => $asOf, 'status' => $status, 'engine_type' => $engine, 'prediction' => $prediction, 'input_json' => self::canonical(['rows' => $rows, 'target_plan_id' => $plan?->id]), 'result_json' => json_encode($result, JSON_THROW_ON_ERROR), 'parent_run_id' => $data['parent_run_id'] ?? null, 'trigger_reference' => $data['trigger_reference'] ?? null, 'target_plan_id' => $plan?->id, 'created_at' => now('UTC')]);
            Governance::audit($request,'forecast.'.$status,'forecast_run',$id,null,['model_version_id' => $model?->id, 'status' => $status]);
        });
        if ($model) {
            PredictForecast::dispatch($id)->afterCommit();
        }

        return response()->json(['data' => $this->decode(DB::table('forecast_runs')->where('id',$id)->first(),['result_json'])],$model ? 202 : 201);
    }

    private function decode($row,array $columns): array
    {
        $data = (array) $row;
        foreach ($columns as $column) {
            $data[$column] = $data[$column] === null ? null : json_decode($data[$column],true,512,JSON_THROW_ON_ERROR);
        }unset($data['input_json']);

        return $data;
    }
}
