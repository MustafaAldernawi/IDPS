<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chemical_targets', function (Blueprint $table): void {
            $table->char('id', 36)->charset('ascii')->collation('ascii_bin')->primary();
            $table->char('facility_id', 36)->charset('ascii')->collation('ascii_bin');
            $table->foreign('facility_id')->references('id')->on('facilities');
            $table->char('unit_id', 36)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->foreign('unit_id')->references('id')->on('refinery_units');
            $table->char('period_month', 7);
            $table->string('chemical_code', 64);
            $table->string('unit_code', 64);
            $table->foreign('unit_code')->references('code')->on('measurement_units');
            $table->decimal('quantity', 20, 6);
            $table->string('source_reference', 500);
            $table->dateTime('available_at', 6);
            $table->string('status', 24)->default('draft');
            $table->char('active_scope', 64)->nullable()->unique();
            $table->char('replaces_id', 36)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->foreign('replaces_id')->references('id')->on('chemical_targets');
            $table->unsignedInteger('revision_no')->default(1);
            $table->char('created_by', 36)->charset('ascii')->collation('ascii_bin');
            $table->foreign('created_by')->references('id')->on('users');
            $table->char('approved_by', 36)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->foreign('approved_by')->references('id')->on('users');
            $table->dateTime('approved_at', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
    }

    public function down(): void { Schema::dropIfExists('chemical_targets'); }
};
