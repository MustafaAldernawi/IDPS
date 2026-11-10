<?php
namespace Tests\Feature;

use App\Jobs\ReforecastEvent;
use App\Jobs\TrainCandidate;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Integration E2E with real Laravel/MySQL and synthetic inputs. AI worker boundary is explicitly faked. */
class EndToEndWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function loginRole(string $role): string
    {
        $user=User::factory()->create(['password'=>Hash::make('Synthetic-test-password-2026!')]);
        DB::table('user_roles')->insert(['id'=>(string)Str::uuid(),'user_id'=>$user->id,'role_id'=>DB::table('roles')->where('code',$role)->value('id')]);
        return $this->postJson('/api/v1/auth/login',['email'=>$user->email,'password'=>'Synthetic-test-password-2026!'])->assertOk()->json('token');
    }

    public function test_end_to_end_review_kpis_event_and_research_retraining_preserve_champion(): void
    {
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
        config(['idps_ai.key'=>'synthetic-key','idps_ai.url'=>'http://ai.test']);
        Http::fake(['ai.test/*'=>Http::response(['status'=>'not_ready','reason'=>'synthetic_insufficient_history','can_deploy'=>false])]);
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $steward=$this->loginRole('DATA_STEWARD');
        $engineer=$this->loginRole('PROCESS_ENGINEER');
        $producer=$this->loginRole('PRODUCTION_ENGINEER');
        $planner=$this->loginRole('PLANNING_ENGINEER');
        $manager=$this->loginRole('OPERATIONS_MANAGER');
        $trainer=$this->loginRole('ML_OPERATOR');
        $facility=DB::table('facilities')->value('id');
        $product=DB::table('product_catalog')->where('code','DIESEL')->value('id');
        $unit=(string)Str::uuid();
        DB::table('refinery_units')->insert(['id'=>$unit,'facility_id'=>$facility,'code'=>'SYNTHETIC_E2E','name_ar'=>'وحدة تجريبية']);
        $import=$this->withToken($steward)->postJson('/api/v1/imports',['facility_id'=>$facility,'file'=>UploadedFile::fake()->createWithContent('synthetic.csv',"month,period_granularity,product_diesel_actual_t,product_diesel_target_t\n2026-01,month,100,125\n")])->assertCreated()->json('data.id');
        $this->withToken($steward)->postJson('/api/v1/imports/'.$import.'/approve',['reason'=>'synthetic unauthorized review','lock_version'=>1])->assertForbidden();
        $this->withToken($engineer)->getJson('/api/v1/imports/'.$import.'/preview')->assertOk();
        $this->withToken($trainer)->postJson('/api/v1/ai/datasets',['name'=>'Synthetic research','product_code'=>'DIESEL','mode'=>'research_only','facility_id'=>$facility])->assertCreated()->json('data.id');
        $this->withToken($engineer)->postJson('/api/v1/imports/'.$import.'/approve',['reason'=>'Synthetic independent quality review','lock_version'=>1])->assertOk()->assertJsonPath('data.approved_count',2);
        $planPayload=['facility_id'=>$facility,'period_month'=>'2026-02','title'=>'Synthetic target','unit_code'=>'t','source_reference'=>'synthetic:plan','available_at'=>'2026-01-31T00:00:00Z','targets'=>[['product_id'=>$product,'quantity'=>'200','unit_code'=>'t']]];
        $plan=$this->withToken($planner)->postJson('/api/v1/plans',$planPayload)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/plans/'.$plan.'/submit')->assertOk();
        $this->withToken($manager)->postJson('/api/v1/plans/'.$plan.'/approve',['reason'=>'Synthetic independent approval'])->assertOk();
        $payload=['facility_id'=>$facility,'unit_id'=>null,'product_id'=>$product,'period_month'=>'2026-02','quantity'=>'100','unit_code'=>'t','source_reference'=>'synthetic:production','available_at'=>'2026-03-01T00:00:00Z'];
        $record=$this->withToken($producer)->postJson('/api/v1/production-records',$payload)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/production-records/'.$record.'/submit')->assertOk();
        $this->withToken($engineer)->postJson('/api/v1/production-records/'.$record.'/approve',['reason'=>'Synthetic independent approval'])->assertOk();
        $this->getJson('/api/v1/comparisons/actual-target?facility_id='.$facility.'&period_month=2026-02')->assertOk()->assertJsonPath('data.0.achievement_pct','50.000000');
        $event=$this->withToken($producer)->postJson('/api/v1/events',['facility_id'=>$facility,'unit_id'=>$unit,'kind'=>'unplanned','event_type'=>'trip','started_at'=>'2026-02-01T01:00:00Z','known_at'=>'2026-02-01T01:01:00Z','expected_end_at'=>null,'source_reference'=>'synthetic:event','reason'=>'Synthetic trip','idempotency_key'=>'synthetic-e2e-trip'])->assertCreated()->json('data.id');
        $outbox=DB::table('operations_outbox')->where('event_key','event:'.$event.':1')->value('id');
        $this->assertNotNull($outbox);
        (new ReforecastEvent($outbox))->handle();
        $this->assertGreaterThan(0,DB::table('forecast_scenarios')->count());
        $this->assertSame(0,DB::table('forecast_scenarios')->whereNotNull('prediction')->count());
        $dataset=$this->withToken($trainer)->postJson('/api/v1/ai/datasets',['name'=>'Synthetic approved','product_code'=>'DIESEL','mode'=>'approved','facility_id'=>$facility])->assertCreated()->json('data.id');
        $run=$this->postJson('/api/v1/ai/training-runs',['dataset_id'=>$dataset,'idempotency_key'=>'synthetic-first'])->assertAccepted()->json('data.id');
        (new TrainCandidate($run))->handle();
        $this->assertDatabaseHas('training_runs',['id'=>$run,'status'=>'not_ready']);
        $this->assertDatabaseCount('model_versions',0);
        // Synthetic pre-existing champion: fixture only, no model is trained or deployed here.
        $trainerId=DB::table('api_tokens')->where('token_hash',hash('sha256',$trainer))->value('user_id');
        $priorRun=(string)Str::uuid();$champion=(string)Str::uuid();$datasetSha=DB::table('dataset_snapshots')->where('id',$dataset)->value('dataset_sha256');
        DB::table('training_runs')->insert(['id'=>$priorRun,'dataset_id'=>$dataset,'trained_by'=>$trainerId,'status'=>'completed','idempotency_key'=>'synthetic-prior-champion','command_sha256'=>hash('sha256','synthetic-prior-champion'),'created_at'=>now(),'updated_at'=>now()]);
        DB::table('model_versions')->insert(['id'=>$champion,'training_run_id'=>$priorRun,'dataset_id'=>$dataset,'facility_id'=>$facility,'product_code'=>'DIESEL','status'=>'active','service_model_id'=>hash('sha256','synthetic-champion'),'artifact_sha256'=>hash('sha256','synthetic-artifact'),'dataset_sha256'=>$datasetSha,'feature_schema'=>'synthetic-v1','method'=>'mean3','mode'=>'approved','evaluation_json'=>'{"fixture":"synthetic_only"}','trained_by'=>$trainerId,'created_at'=>now()]);
        $new=$this->withToken($steward)->postJson('/api/v1/imports',['facility_id'=>$facility,'file'=>UploadedFile::fake()->createWithContent('synthetic-new.csv',"month,period_granularity,product_diesel_actual_t\n2026-03,month,110\n")])->assertCreated()->json('data.id');
        $this->withToken($engineer)->postJson('/api/v1/imports/'.$new.'/approve',['reason'=>'Synthetic independent review','lock_version'=>1])->assertOk();
        $next=$this->withToken($trainer)->postJson('/api/v1/ai/datasets',['name'=>'Synthetic next','product_code'=>'DIESEL','mode'=>'approved','facility_id'=>$facility])->assertCreated()->json('data.id');
        $this->assertNotSame($dataset,$next);
        $retry=$this->postJson('/api/v1/ai/retrain',['dataset_id'=>$next,'idempotency_key'=>'synthetic-retrain'])->assertAccepted()->json('data.id');
        (new TrainCandidate($retry))->handle();
        $this->assertDatabaseHas('training_runs',['id'=>$retry,'status'=>'not_ready']);
        $this->assertDatabaseHas('model_versions',['id'=>$champion,'status'=>'active']);
        $this->assertDatabaseCount('model_versions',1);
    }
}
