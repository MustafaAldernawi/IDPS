<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function uuid(Blueprint $t, string $name): void
    {
        $t->char($name, 36)->charset('ascii')->collation('ascii_bin')->nullable();
    }

    public function up(): void
    {
        Schema::table('forecast_runs', function (Blueprint $t) {
            $this->uuid($t, 'event_id');
            $t->unsignedInteger('event_revision')->nullable();
            $this->uuid($t, 'target_plan_id');
            $t->string('time_granularity', 20)->default('monthly');
            $t->string('prediction_horizon', 32)->default('next_month');
            $t->char('command_sha256', 64)->nullable()->unique();
            $t->dateTime('reforecast_enqueued_at', 6)->nullable();
            $t->foreign('event_id')->references('id')->on('operational_events');
            $t->foreign('target_plan_id')->references('id')->on('production_target_plans');
        });
        Schema::create('forecast_scenarios', function (Blueprint $t) {
            $t->char('id', 36)->charset('ascii')->collation('ascii_bin')->primary();
            $t->char('forecast_run_id', 36)->charset('ascii')->collation('ascii_bin');
            $t->string('code', 64);
            $t->string('status', 40);
            $t->unsignedInteger('illustrative_days')->nullable();
            $t->decimal('prediction', 20, 6)->nullable();
            $t->json('assumptions_json');
            $t->text('quality_warning');
            $t->dateTime('created_at', 6);
            $t->foreign('forecast_run_id')->references('id')->on('forecast_runs');
            $t->unique(['forecast_run_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_scenarios');
        Schema::table('forecast_runs', function (Blueprint $t) {
            $t->dropForeign(['event_id']);
            $t->dropForeign(['target_plan_id']);
            $t->dropUnique(['command_sha256']);
            $t->dropColumn(['event_id', 'event_revision', 'target_plan_id', 'time_granularity', 'prediction_horizon', 'command_sha256', 'reforecast_enqueued_at']);
        });
    }
};
