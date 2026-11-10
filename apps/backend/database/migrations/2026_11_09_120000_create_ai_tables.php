<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function uuid(Blueprint $t, string $name = 'id'): void
    {
        $column = $t->char($name, 36)->charset('ascii')->collation('ascii_bin');
        if (in_array($name, ['approved_by', 'model_version_id', 'parent_run_id'], true)) {
            $column->nullable();
        }
    }

    public function up(): void
    {
        Schema::create('dataset_snapshots', function (Blueprint $t) {
            $this->uuid($t);
            $t->primary('id');
            $this->uuid($t, 'facility_id');
            $t->string('name');
            $t->string('product_code', 64);
            $t->string('mode', 20);
            $t->char('dataset_sha256', 64);
            $t->json('snapshot_json');
            $t->json('source_observation_ids');
            $t->text('quality_warning')->nullable();
            $this->uuid($t, 'created_by');
            $t->dateTime('created_at', 6);
            $t->foreign('facility_id')->references('id')->on('facilities');
            $t->foreign('created_by')->references('id')->on('users');
            $t->unique(['facility_id', 'product_code', 'mode', 'dataset_sha256'], 'uq_snapshot_scope_hash');
        });
        Schema::create('training_runs', function (Blueprint $t) {
            $this->uuid($t);
            $t->primary('id');
            $this->uuid($t, 'dataset_id');
            $this->uuid($t, 'trained_by');
            $t->string('status', 24)->default('queued')->index();
            $t->string('idempotency_key', 128)->unique();
            $t->char('command_sha256', 64);
            $t->unsignedInteger('seed')->default(42);
            $t->unsignedInteger('attempts')->default(0);
            $t->dateTime('lease_expires_at', 6)->nullable();
            $t->json('result_json')->nullable();
            $t->string('error_code', 100)->nullable();
            $t->dateTime('created_at', 6);
            $t->dateTime('updated_at', 6);
            $t->foreign('dataset_id')->references('id')->on('dataset_snapshots');
            $t->foreign('trained_by')->references('id')->on('users');
        });
        Schema::table('training_runs', function (Blueprint $t) {
            $t->dateTime('enqueued_at', 6)->nullable();
        });
        Schema::create('model_versions', function (Blueprint $t) {
            $this->uuid($t);
            $t->primary('id');
            $this->uuid($t, 'training_run_id');
            $this->uuid($t, 'dataset_id');
            $this->uuid($t, 'facility_id');
            $t->string('product_code', 64);
            $t->string('time_granularity', 20)->default('monthly');
            $t->string('prediction_horizon', 32)->default('next_month');
            $t->string('status', 24)->default('candidate');
            $t->char('service_model_id', 64);
            $t->char('artifact_sha256', 64);
            $t->char('dataset_sha256', 64);
            $t->string('feature_schema', 128);
            $t->string('method', 64);
            $t->string('mode', 20);
            $t->json('evaluation_json');
            $this->uuid($t, 'trained_by');
            $this->uuid($t, 'approved_by');
            $t->text('approval_reason')->nullable();
            $t->dateTime('approved_at', 6)->nullable();
            $t->dateTime('created_at', 6);
            $t->string('active_scope_key', 180)->nullable()->storedAs("CASE WHEN status = 'active' THEN CONCAT(facility_id,':',product_code,':',time_granularity,':',prediction_horizon) ELSE NULL END");
            $t->unique('active_scope_key', 'uq_one_active_ai_model');
            $t->unique('training_run_id');
            $t->foreign('training_run_id')->references('id')->on('training_runs');
            $t->foreign('dataset_id')->references('id')->on('dataset_snapshots');
            $t->foreign('facility_id')->references('id')->on('facilities');
            $t->foreign('trained_by')->references('id')->on('users');
            $t->foreign('approved_by')->references('id')->on('users');
        });
        Schema::create('ai_activation_requests', function (Blueprint $t) {
            $this->uuid($t);
            $t->primary('id');
            $this->uuid($t, 'model_id');
            $this->uuid($t, 'actor_id');
            $t->string('action', 24);
            $t->string('status', 24)->default('pending');
            $t->text('reason');
            $t->string('evaluation_reference', 500);
            $t->dateTime('created_at', 6);
            $t->dateTime('completed_at', 6)->nullable();
            $t->foreign('model_id')->references('id')->on('model_versions');
            $t->foreign('actor_id')->references('id')->on('users');
        });
        Schema::create('forecast_runs', function (Blueprint $t) {
            $this->uuid($t);
            $t->primary('id');
            $this->uuid($t, 'facility_id');
            $this->uuid($t, 'model_version_id');
            $this->uuid($t, 'requested_by');
            $t->string('product_code', 64);
            $t->char('period_month', 7);
            $t->dateTime('as_of', 6);
            $t->string('status', 24);
            $t->string('engine_type', 40);
            $t->decimal('prediction', 20, 6)->nullable();
            $t->string('unit_code', 64)->default('t');
            $t->json('input_json');
            $t->json('result_json');
            $this->uuid($t, 'parent_run_id');
            $t->string('trigger_reference', 128)->nullable();
            $t->dateTime('created_at', 6);
            $t->foreign('facility_id')->references('id')->on('facilities');
            $t->foreign('model_version_id')->references('id')->on('model_versions');
            $t->foreign('requested_by')->references('id')->on('users');
            $t->foreign('parent_run_id')->references('id')->on('forecast_runs');
        });
    }

    public function down(): void
    {
        foreach (['forecast_runs', 'ai_activation_requests', 'model_versions', 'training_runs', 'dataset_snapshots'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
