<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function uuid(Blueprint $t, string $name = 'id')
    {
        return $t->uuid($name)->charset('ascii')->collation('ascii_bin');
    }

    public function up(): void
    {
        Schema::create('source_files', function (Blueprint $t) {
            $this->uuid($t)->primary();
            $t->char('sha256', 64)->unique();
            $t->string('original_name');
            $t->string('storage_path');
            $t->unsignedBigInteger('bytes');
            $t->string('data_role')->default('raw_document_not_auto_approved');
            $t->dateTime('created_at', 6);
        });
        Schema::create('import_batches', function (Blueprint $t) {
            $this->uuid($t)->primary();
            $this->uuid($t, 'source_file_id');
            $t->foreign('source_file_id')->references('id')->on('source_files');
            $this->uuid($t, 'facility_id');
            $t->foreign('facility_id')->references('id')->on('facilities');
            $this->uuid($t, 'created_by');
            $t->foreign('created_by')->references('id')->on('users');
            $t->string('mapping_version', 40);
            $t->string('status', 24)->default('staged');
            $t->unsignedInteger('lock_version')->default(1);
            $t->dateTime('created_at', 6);
            $t->unique(['source_file_id', 'facility_id', 'mapping_version'], 'uq_import_source_mapping');
        });
        Schema::create('import_rows', function (Blueprint $t) {
            $this->uuid($t)->primary();
            $this->uuid($t, 'batch_id');
            $t->foreign('batch_id')->references('id')->on('import_batches');
            $t->json('source_locator');
            $t->json('raw_json');
            $t->json('qa_flags');
        });
        Schema::create('monthly_observations', function (Blueprint $t) {
            $this->uuid($t)->primary();
            $this->uuid($t, 'facility_id');
            $t->foreign('facility_id')->references('id')->on('facilities');
            $t->char('period_month', 7);
            $t->string('metric_code', 40);
            $t->string('item_code', 64);
            $t->string('actual_or_target', 16);
            $t->string('report_status', 24)->default('actual');
            $t->decimal('quantity', 20, 6)->nullable();
            $t->string('unit_code', 64);
            $t->foreign('unit_code')->references('code')->on('measurement_units');
            $this->uuid($t, 'source_file_id');
            $t->foreign('source_file_id')->references('id')->on('source_files');
            $this->uuid($t, 'import_row_id');
            $t->foreign('import_row_id')->references('id')->on('import_rows');
            $t->unsignedInteger('revision_no')->default(1);
            $t->string('status', 24)->default('staged');
            $t->dateTime('available_at', 6)->nullable();
            $t->dateTime('approved_at', 6)->nullable();
            $this->uuid($t, 'approved_by')->nullable();
            $t->foreign('approved_by')->references('id')->on('users');
            $this->uuid($t, 'supersedes_id')->nullable();
            $t->foreign('supersedes_id')->references('id')->on('monthly_observations');
            $t->unique(['source_file_id', 'facility_id', 'period_month', 'metric_code', 'item_code', 'actual_or_target', 'revision_no'], 'uq_monthly_source_revision');
            $t->index(['facility_id', 'period_month', 'status'], 'ix_monthly_scope');
        });
        Schema::create('reconciliation_cases', function (Blueprint $t) {
            $this->uuid($t)->primary();
            $this->uuid($t, 'batch_id');
            $t->foreign('batch_id')->references('id')->on('import_batches');
            $this->uuid($t, 'observation_id')->nullable();
            $t->foreign('observation_id')->references('id')->on('monthly_observations');
            $t->string('rule_code', 100);
            $t->text('details');
            $t->string('status', 20)->default('open');
            $t->text('resolution')->nullable();
            $this->uuid($t, 'reviewed_by')->nullable();
            $t->foreign('reviewed_by')->references('id')->on('users');
            $t->dateTime('reviewed_at', 6)->nullable();
        });
        Schema::create('approval_actions', function (Blueprint $t) {
            $this->uuid($t)->primary();
            $t->string('resource_type');
            $this->uuid($t, 'resource_id');
            $this->uuid($t, 'actor_id');
            $t->foreign('actor_id')->references('id')->on('users');
            $t->string('action', 24);
            $t->text('reason');
            $t->dateTime('created_at', 6);
        });
    }

    public function down(): void
    {
        foreach (['approval_actions', 'reconciliation_cases', 'monthly_observations', 'import_rows', 'import_batches', 'source_files'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
