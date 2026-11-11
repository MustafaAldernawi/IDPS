<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function uuid(Blueprint $table, string $name, bool $nullable = false): void
    {
        $c = $table->char($name, 36)->charset('ascii')->collation('ascii_bin');
        if ($nullable) {
            $c->nullable();
        }
    }

    public function up(): void
    {
        Schema::create('source_mapping_queue', function (Blueprint $t) {
            $this->uuid($t, 'id');
            $t->primary('id');
            $this->uuid($t, 'facility_id');
            $this->uuid($t, 'source_file_id');
            $this->uuid($t, 'created_by');
            $t->foreign('facility_id')->references('id')->on('facilities');
            $t->foreign('source_file_id')->references('id')->on('source_files');
            $t->foreign('created_by')->references('id')->on('users');
            $t->string('alias', 255);
            $t->string('source_scope', 255);
            $t->string('unit_code', 64)->nullable();
            $t->foreign('unit_code')->references('code')->on('measurement_units');
            $t->string('granularity', 20);
            $t->string('product_stage', 40);
            $t->char('context_sha256', 64);
            $t->char('locator_sha256', 64);
            $t->json('source_locator');
            $t->string('status', 24)->default('unresolved');
            $t->dateTime('created_at', 6);
            $t->unique(['source_file_id', 'facility_id', 'context_sha256', 'locator_sha256'], 'uq_unknown_source_locator');
            $t->index(['facility_id', 'status'], 'idx_mapping_queue');
        });
        Schema::create('source_mapping_versions', function (Blueprint $t) {
            $this->uuid($t, 'id');
            $t->primary('id');
            $this->uuid($t, 'facility_id');
            $this->uuid($t, 'queue_id');
            $this->uuid($t, 'created_by');
            $this->uuid($t, 'approved_by', true);
            $this->uuid($t, 'supersedes_id', true);
            $t->foreign('facility_id')->references('id')->on('facilities');
            $t->foreign('queue_id')->references('id')->on('source_mapping_queue');
            $t->foreign('created_by')->references('id')->on('users');
            $t->foreign('approved_by')->references('id')->on('users');
            $t->foreign('supersedes_id')->references('id')->on('source_mapping_versions');
            $t->char('context_sha256', 64);
            $t->unsignedInteger('version');
            $t->string('metric_code', 40);
            $t->string('item_code', 64);
            $t->string('actual_or_target', 16);
            $t->string('report_status', 24);
            $t->string('status', 24)->default('pending');
            $t->text('reason');
            $t->text('approval_reason')->nullable();
            $t->dateTime('created_at', 6);
            $t->dateTime('approved_at', 6)->nullable();
            $t->char('active_context', 64)->nullable()->storedAs("CASE WHEN status = 'approved' THEN context_sha256 ELSE NULL END");
            $t->unique(['facility_id', 'context_sha256', 'version'], 'uq_mapping_context_version');
            $t->unique(['facility_id', 'active_context'], 'uq_mapping_active_context');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_mapping_versions');
        Schema::dropIfExists('source_mapping_queue');
    }
};
