<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    private function id(Blueprint $t,string $name='id',bool $nullable=false):void {$c=$t->char($name,36)->charset('ascii')->collation('ascii_bin');if($nullable){$c->nullable();}}
    public function up():void {
        Schema::create('forecast_evaluations',function(Blueprint $t){$this->id($t);$t->primary('id');$this->id($t,'forecast_run_id');$this->id($t,'observation_id');$t->decimal('actual',20,6);$t->decimal('prediction',20,6);$t->decimal('absolute_error',21,6);$t->decimal('signed_error',21,6);$t->string('unit_code',64);$this->id($t,'evaluated_by');$t->dateTime('created_at',6);$t->foreign('forecast_run_id')->references('id')->on('forecast_runs');$t->foreign('observation_id')->references('id')->on('monthly_observations');$t->foreign('evaluated_by')->references('id')->on('users');$t->unique(['forecast_run_id','observation_id'],'uq_forecast_eval_actual');});
        Schema::create('ai_retraining_policies',function(Blueprint $t){$this->id($t);$t->primary('id');$this->id($t,'facility_id');$t->string('product_code',64);$this->id($t,'actor_id');$t->boolean('enabled')->default(false);$t->unsignedSmallInteger('min_new_months')->default(2);$t->unsignedSmallInteger('min_training_months')->default(36);$t->unsignedSmallInteger('min_monitoring_samples')->default(12);$t->dateTime('created_at',6);$t->dateTime('updated_at',6);$t->foreign('facility_id')->references('id')->on('facilities');$t->foreign('actor_id')->references('id')->on('users');$t->unique(['facility_id','product_code'],'uq_retrain_policy_scope');});
        Schema::create('ai_retraining_checks',function(Blueprint $t){$this->id($t);$t->primary('id');$this->id($t,'policy_id');$t->char('period_month',7);$t->string('status',40);$t->string('reason',100)->nullable();$this->id($t,'dataset_id',true);$this->id($t,'training_run_id',true);$t->dateTime('created_at',6);$t->foreign('policy_id')->references('id')->on('ai_retraining_policies');$t->foreign('dataset_id')->references('id')->on('dataset_snapshots');$t->foreign('training_run_id')->references('id')->on('training_runs');$t->unique(['policy_id','period_month'],'uq_retrain_check_period');});
    }
    public function down():void{Schema::dropIfExists('ai_retraining_checks');Schema::dropIfExists('ai_retraining_policies');Schema::dropIfExists('forecast_evaluations');}
};
