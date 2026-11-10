<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $uuid = static function (Blueprint $table, string $name, bool $nullable = false): void {
            $column = $table->char($name, 36)->charset('ascii')->collation('ascii_bin');
            if ($nullable) {
                $column->nullable();
            }
        };
        $common = static function (Blueprint $table) use ($uuid): void {
            $uuid($table, 'id');
            $table->primary('id');
            $uuid($table, 'facility_id');
            $uuid($table, 'unit_id', true);
            $table->char('period_month', 7)->index();
            $table->date('business_date')->nullable();
            $table->string('shift_code', 32)->nullable();
            $table->string('unit_code', 64)->default('t');
            $table->string('source_reference', 500);
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->unsignedInteger('revision_no')->default(1);
            $uuid($table, 'replaces_id', true);
            $uuid($table, 'created_by');
            $uuid($table, 'approved_by', true);
            $table->dateTime('available_at', 6);
            $table->dateTime('approved_at', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->foreign('facility_id')->references('id')->on('facilities');
            $table->foreign('unit_id')->references('id')->on('refinery_units');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('approved_by')->references('id')->on('users');
            $table->foreign('unit_code')->references('code')->on('measurement_units');
            $table->index(['facility_id', 'unit_id', 'period_month', 'status'], 'idx_'.substr(hash('sha256', $table->getTable()), 0, 12).'_scope');
        };
        foreach (['production_records', 'crude_feed_records', 'energy_consumptions', 'loss_records', 'chemical_consumptions', 'laboratory_samples', 'blend_batches'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name, $common, $uuid): void {
                $common($table);
                $table->decimal('quantity', 20, 6)->nullable();
                if (in_array($name, ['production_records', 'blend_batches'], true)) {
                    $uuid($table, 'product_id');
                    $table->foreign('product_id')->references('id')->on('product_catalog');
                } elseif ($name === 'crude_feed_records') {
                    $uuid($table, 'crude_id');
                    $table->foreign('crude_id')->references('id')->on('crude_catalog');
                } elseif ($name === 'energy_consumptions') {
                    $table->string('carrier_code', 64);
                } elseif ($name === 'loss_records') {
                    $table->string('category_code', 64);
                    $table->boolean('definition_approved')->default(false);
                } elseif ($name === 'chemical_consumptions') {
                    $table->string('chemical_code', 64);
                    $table->decimal('target_quantity', 20, 6)->nullable();
                } elseif ($name === 'laboratory_samples') {
                    $uuid($table, 'product_id', true);
                    $table->foreign('product_id')->references('id')->on('product_catalog');
                    $table->string('parameter_code', 64);
                    $table->decimal('spec_min', 20, 6)->nullable();
                    $table->decimal('spec_max', 20, 6)->nullable();
                    $table->string('spec_version', 64)->nullable();
                }
            });
            DB::statement("ALTER TABLE $name ADD CONSTRAINT chk_{$name}_qty CHECK (quantity IS NULL OR quantity >= 0)");
        }
        Schema::create('blend_components', function (Blueprint $table) use ($uuid): void {
            $uuid($table, 'id');
            $table->primary('id');
            $uuid($table, 'blend_batch_id');
            $uuid($table, 'product_id');
            $table->decimal('quantity', 20, 6);
            $table->string('unit_code', 64);
            $table->foreign('blend_batch_id')->references('id')->on('blend_batches');
            $table->foreign('product_id')->references('id')->on('product_catalog');
            $table->foreign('unit_code')->references('code')->on('measurement_units');
        });
        Schema::create('production_target_plans', function (Blueprint $table) use ($common): void {
            $common($table);
            $table->string('title', 255);
            $table->string('active_scope', 180)->nullable()->unique();
        });
        Schema::create('production_targets', function (Blueprint $table) use ($uuid): void {
            $uuid($table, 'id');
            $table->primary('id');
            $uuid($table, 'plan_id');
            $uuid($table, 'product_id');
            $table->decimal('quantity', 20, 6);
            $table->string('unit_code', 64)->default('t');
            $table->foreign('plan_id')->references('id')->on('production_target_plans');
            $table->foreign('product_id')->references('id')->on('product_catalog');
            $table->foreign('unit_code')->references('code')->on('measurement_units');
            $table->unique(['plan_id', 'product_id', 'unit_code']);
        });
        Schema::create('operational_events', function (Blueprint $table) use ($uuid): void {
            $uuid($table, 'id');
            $table->primary('id');
            $uuid($table, 'facility_id');
            $uuid($table, 'unit_id');
            $table->string('kind', 20);
            $table->string('event_type', 64);
            $table->string('status', 24)->default('reported');
            $table->string('idempotency_key', 128)->unique();
            $table->char('payload_hash', 64);
            $table->dateTime('started_at', 6);
            $table->dateTime('known_at', 6);
            $table->dateTime('expected_end_at', 6)->nullable();
            $table->dateTime('actual_end_at', 6)->nullable();
            $table->decimal('derating_factor', 7, 6)->default('1.000000');
            $table->text('reason');
            $table->string('source_reference', 500);
            $table->unsignedInteger('revision_no')->default(1);
            $uuid($table, 'created_by');
            $uuid($table, 'approved_by', true);
            $table->dateTime('approved_at', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->foreign('facility_id')->references('id')->on('facilities');
            $table->foreign('unit_id')->references('id')->on('refinery_units');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('approved_by')->references('id')->on('users');
            $table->index(['unit_id', 'started_at']);
        });
        Schema::create('event_revisions', function (Blueprint $table) use ($uuid): void {
            $uuid($table, 'id');
            $table->primary('id');
            $uuid($table, 'event_id');
            $table->unsignedInteger('revision_no');
            $table->json('snapshot');
            $table->text('reason');
            $uuid($table, 'actor_id');
            $table->dateTime('created_at', 6);
            $table->foreign('event_id')->references('id')->on('operational_events');
            $table->foreign('actor_id')->references('id')->on('users');
            $table->unique(['event_id', 'revision_no']);
        });
        Schema::create('operations_outbox', function (Blueprint $table) use ($uuid): void {
            $uuid($table, 'id');
            $table->primary('id');
            $table->string('event_key', 191)->unique();
            $table->string('event_type', 64);
            $table->json('payload');
            $table->dateTime('created_at', 6);
            $table->dateTime('processed_at', 6)->nullable();
        });
    }

    public function down(): void
    {
        foreach (['operations_outbox', 'event_revisions', 'operational_events', 'production_targets', 'production_target_plans', 'blend_components', 'blend_batches', 'laboratory_samples', 'chemical_consumptions', 'loss_records', 'energy_consumptions', 'crude_feed_records', 'production_records'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
