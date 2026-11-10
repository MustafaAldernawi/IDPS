<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function uuid(Blueprint $t, string $n, bool $nullable = false): void
    {
        $c = $t->char($n, 36)->charset('ascii')->collation('ascii_bin');
        if ($nullable) {
            $c->nullable();
        }
    }

    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $t) {
            $this->uuid($t, 'id');
            $t->primary('id');
            $this->uuid($t, 'facility_id');
            $t->foreign('facility_id')->references('id')->on('facilities');
            $t->string('code', 32);
            $t->string('name_ar');
            $t->time('start_time');
            $t->time('end_time');
            $t->date('effective_from');
            $t->date('effective_until')->nullable();
            $t->boolean('active')->default(true);
            $this->uuid($t, 'created_by');
            $t->foreign('created_by')->references('id')->on('users');
            $t->dateTime('created_at', 6);
            $t->unique(['facility_id', 'code', 'effective_from'], 'uq_shift_version');
        });
        Schema::create('operational_calendar', function (Blueprint $t) {
            $this->uuid($t, 'id');
            $t->primary('id');
            $this->uuid($t, 'facility_id');
            $t->foreign('facility_id')->references('id')->on('facilities');
            $this->uuid($t, 'shift_id');
            $t->foreign('shift_id')->references('id')->on('shifts');
            $t->date('business_date');
            $t->dateTime('starts_at', 6);
            $t->dateTime('ends_at', 6);
            $t->string('source_reference', 500);
            $this->uuid($t, 'created_by');
            $t->foreign('created_by')->references('id')->on('users');
            $t->dateTime('created_at', 6);
            $t->unique(['facility_id', 'business_date', 'shift_id'], 'uq_calendar_shift');
        });
        Schema::create('unit_status_intervals', function (Blueprint $t) {
            $this->uuid($t, 'id');
            $t->primary('id');
            $this->uuid($t, 'facility_id');
            $this->uuid($t, 'unit_id');
            $t->foreign('facility_id')->references('id')->on('facilities');
            $t->foreign('unit_id')->references('id')->on('refinery_units');
            $t->string('state', 32);
            $t->dateTime('started_at', 6);
            $t->dateTime('ended_at', 6)->nullable();
            $t->dateTime('known_at', 6);
            $t->decimal('derating_factor', 7, 6)->nullable();
            $t->string('source_reference', 500);
            $t->string('status', 24)->default('pending');
            $this->uuid($t, 'created_by');
            $this->uuid($t, 'approved_by', true);
            $t->foreign('created_by')->references('id')->on('users');
            $t->foreign('approved_by')->references('id')->on('users');
            $t->dateTime('approved_at', 6)->nullable();
            $t->dateTime('created_at', 6);
            $t->index(['unit_id', 'started_at'], 'idx_status_interval');
        });
        Schema::create('quality_specifications', function (Blueprint $t) {
            $this->uuid($t, 'id');
            $t->primary('id');
            $this->uuid($t, 'facility_id');
            $t->foreign('facility_id')->references('id')->on('facilities');
            $this->uuid($t, 'product_id', true);
            $t->foreign('product_id')->references('id')->on('product_catalog');
            $t->string('parameter_code', 64);
            $t->string('name_ar');
            $t->string('unit_code', 64);
            $t->foreign('unit_code')->references('code')->on('measurement_units');
            $t->decimal('spec_min', 20, 6)->nullable();
            $t->decimal('spec_max', 20, 6)->nullable();
            $t->date('effective_from');
            $t->date('effective_until')->nullable();
            $t->string('source_reference', 500);
            $t->unsignedInteger('version');
            $t->string('status', 24)->default('pending');
            $this->uuid($t, 'created_by');
            $this->uuid($t, 'approved_by', true);
            $t->foreign('created_by')->references('id')->on('users');
            $t->foreign('approved_by')->references('id')->on('users');
            $t->dateTime('created_at', 6);
            $t->dateTime('approved_at', 6)->nullable();
            $t->text('approval_reason')->nullable();
            $t->index(['facility_id', 'parameter_code', 'status'], 'idx_quality_spec_scope');
        });
    }

    public function down(): void
    {
        foreach (['quality_specifications', 'unit_status_intervals', 'operational_calendar', 'shifts'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
