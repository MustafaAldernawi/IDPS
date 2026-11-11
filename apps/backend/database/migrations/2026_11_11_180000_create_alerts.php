<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $t) {
            $t->uuid('id')->charset('ascii')->collation('ascii_bin')->primary();
            foreach (['facility_id', 'unit_id', 'event_id'] as $field) {
                $t->uuid($field)->charset('ascii')->collation('ascii_bin');
            }$t->uuid('acknowledged_by')->charset('ascii')->collation('ascii_bin')->nullable();
            $t->foreign('facility_id')->references('id')->on('facilities');
            $t->foreign('unit_id')->references('id')->on('refinery_units');
            $t->foreign('event_id')->references('id')->on('operational_events');
            $t->foreign('acknowledged_by')->references('id')->on('users');
            $t->string('category', 40);
            $t->string('severity', 24);
            $t->string('status', 24)->default('open');
            $t->string('message', 255);
            $t->text('acknowledgment_reason')->nullable();
            $t->dateTime('acknowledged_at', 6)->nullable();
            $t->dateTime('created_at', 6);
            $t->unique(['event_id', 'category'], 'uq_alert_event_category');
            $t->index(['facility_id', 'unit_id', 'status'], 'idx_alert_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
