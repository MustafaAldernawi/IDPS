<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $id = static function (Blueprint $table, string $column = 'id', bool $nullable = false): void {
            $definition = $table->char($column, 36)->charset('ascii')->collation('ascii_bin');
            if ($nullable) {
                $definition->nullable();
            }
        };
        Schema::create('kpi_definitions', function (Blueprint $table) use ($id): void {
            $id($table);
            $table->primary('id');
            $id($table, 'facility_id');
            $id($table, 'unit_id', true);
            $table->string('code', 64);
            $table->string('title');
            $table->string('formula_code', 64);
            $table->string('loss_category_code', 64)->nullable();
            $table->unsignedInteger('version');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->string('source_reference', 500);
            $table->text('reason');
            $table->string('status', 24)->default('pending');
            $table->string('active_scope', 180)->nullable()->unique();
            $id($table, 'created_by');
            $id($table, 'approved_by', true);
            $table->dateTime('created_at', 6);
            $table->dateTime('approved_at', 6)->nullable();
            $table->foreign('facility_id')->references('id')->on('facilities');
            $table->foreign('unit_id')->references('id')->on('refinery_units');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('approved_by')->references('id')->on('users');
            $table->index(['facility_id', 'code', 'version'], 'idx_kpi_definition_versions');
            $table->string('unit_scope_key', 36)->storedAs("COALESCE(unit_id,'FACILITY')");
            $table->unique(['facility_id', 'unit_scope_key', 'code', 'version'], 'uq_kpi_version_scope');
        });
        Schema::create('kpi_definition_products', function (Blueprint $table) use ($id): void {
            $id($table, 'definition_id');
            $id($table, 'product_id');
            $table->primary(['definition_id', 'product_id'], 'pk_kpi_definition_products');
            $table->foreign('definition_id')->references('id')->on('kpi_definitions');
            $table->foreign('product_id')->references('id')->on('product_catalog');
        });
        Schema::create('energy_conversion_factors', function (Blueprint $table) use ($id): void {
            $id($table);
            $table->primary('id');
            $id($table, 'definition_id');
            $table->string('carrier_code', 64);
            $table->string('input_unit_code', 64);
            $table->decimal('gj_per_unit', 20, 9);
            $table->string('basis', 32);
            $table->string('source_reference', 500);
            $table->foreign('definition_id')->references('id')->on('kpi_definitions');
            $table->foreign('input_unit_code')->references('code')->on('measurement_units');
            $table->unique(['definition_id', 'carrier_code', 'input_unit_code'], 'uq_energy_factor_scope');
        });
        Schema::create('engineering_annotations', function (Blueprint $table) use ($id): void {
            $id($table);
            $table->primary('id');
            $id($table, 'facility_id');
            $id($table, 'unit_id', true);
            $table->char('period_month', 7);
            $table->string('resource_type', 64)->nullable();
            $id($table, 'resource_id', true);
            $table->string('title');
            $table->text('analysis');
            $table->text('hypothesis')->nullable();
            $table->string('source_reference', 500);
            $table->string('status', 24)->default('pending');
            $id($table, 'created_by');
            $id($table, 'approved_by', true);
            $table->dateTime('created_at', 6);
            $table->dateTime('approved_at', 6)->nullable();
            $table->foreign('facility_id')->references('id')->on('facilities');
            $table->foreign('unit_id')->references('id')->on('refinery_units');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('approved_by')->references('id')->on('users');
            $table->index(['facility_id', 'period_month', 'status'], 'idx_engineering_annotation_scope');
        });
        Schema::create('report_exports', function (Blueprint $table) use ($id): void {
            $id($table);
            $table->primary('id');
            $id($table, 'facility_id', true);
            $id($table, 'unit_id', true);
            $table->char('period_month', 7);
            $table->string('format', 12);
            $table->char('sha256', 64);
            $table->json('snapshot');
            $id($table, 'created_by');
            $table->dateTime('created_at', 6);
            $table->foreign('facility_id')->references('id')->on('facilities');
            $table->foreign('unit_id')->references('id')->on('refinery_units');
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        foreach (['report_exports', 'engineering_annotations', 'energy_conversion_factors', 'kpi_definition_products', 'kpi_definitions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
