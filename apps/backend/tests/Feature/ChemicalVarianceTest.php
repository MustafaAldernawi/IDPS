<?php
namespace Tests\Feature;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
class ChemicalVarianceTest extends TestCase {
 use RefreshDatabase;
 private function actor(string $role):User{$u=User::factory()->create();DB::table('user_roles')->insert(['id'=>(string)Str::uuid(),'user_id'=>$u->id,'role_id'=>DB::table('roles')->where('code',$role)->value('id')]);return $u;}
 public function test_approved_planning_target_and_actual_are_compared_only_with_matching_units():void{
  $this->seed(DatabaseSeeder::class);$planner=$this->actor('PLANNING_ENGINEER');$manager=$this->actor('OPERATIONS_MANAGER');$engineer=$this->actor('PROCESS_ENGINEER');$facility=DB::table('facilities')->value('id');
  $target=['facility_id'=>$facility,'period_month'=>'2026-01','chemical_code'=>'SYNTHETIC','unit_code'=>'kg','quantity'=>'100.000000','source_reference'=>'synthetic plan','available_at'=>'2025-12-01T00:00:00Z'];
  $id=$this->actingAs($planner)->postJson('/api/v1/chemical-targets',$target)->assertCreated()->json('data.id');
  $this->postJson('/api/v1/chemical-targets/'.$id.'/approve',['reason'=>'Self approval prohibited'])->assertForbidden();
  $this->actingAs($manager)->postJson('/api/v1/chemical-targets/'.$id.'/approve',['reason'=>'Independent plan review'])->assertOk();
  DB::table('chemical_consumptions')->insert(['id'=>(string)Str::uuid(),'facility_id'=>$facility,'period_month'=>'2026-01','unit_code'=>'kg','chemical_code'=>'SYNTHETIC','quantity'=>'120.000000','status'=>'approved','source_reference'=>'synthetic actual','created_by'=>$planner->id,'approved_by'=>$engineer->id,'approved_at'=>now(),'available_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
  $this->actingAs($engineer)->getJson('/api/v1/engineering/chemical-variance?facility_id='.$facility.'&period_month=2026-01')->assertOk()->assertJsonPath('data.0.variance','20.000000')->assertJsonPath('data.0.unit_code','kg')->assertJsonPath('data.0.status','valid')->assertJsonPath('data.0.target_source','synthetic plan')->assertJsonPath('data.0.actual_source','synthetic actual')->assertJsonPath('data.0.target_id',$id);
  DB::table('chemical_consumptions')->insert(['id'=>(string)Str::uuid(),'facility_id'=>$facility,'period_month'=>'2026-01','unit_code'=>'t','chemical_code'=>'SYNTHETIC','quantity'=>'1.000000','status'=>'approved','source_reference'=>'other measurement','created_by'=>$planner->id,'approved_by'=>$engineer->id,'approved_at'=>now(),'available_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
  $this->getJson('/api/v1/engineering/chemical-variance?facility_id='.$facility.'&period_month=2026-01')->assertOk()->assertJsonFragment(['unit_code'=>'t','status'=>'missing_target','variance'=>null]);
  DB::table('chemical_consumptions')->insert(['id'=>(string)Str::uuid(),'facility_id'=>$facility,'period_month'=>'2026-01','unit_code'=>'kg','chemical_code'=>'SYNTHETIC','quantity'=>null,'status'=>'approved','source_reference'=>'second synthetic actual','created_by'=>$planner->id,'approved_by'=>$engineer->id,'approved_at'=>now(),'available_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
  $this->getJson('/api/v1/engineering/chemical-variance?facility_id='.$facility.'&period_month=2026-01')->assertOk()->assertJsonPath('data.0.status','ambiguous_actual')->assertJsonPath('data.0.variance',null);
  $revision=$target; $revision['quantity']='110.000000'; $revision['replaces_id']=$id;
  $new=$this->actingAs($planner)->postJson('/api/v1/chemical-targets',$revision)->assertCreated()->json('data.id');
  $this->actingAs($manager)->postJson('/api/v1/chemical-targets/'.$new.'/approve',['reason'=>'Revised plan'])->assertOk();
  $this->assertDatabaseHas('chemical_targets',['id'=>$id,'status'=>'superseded','active_scope'=>null]);
  $this->assertDatabaseHas('chemical_targets',['id'=>$new,'status'=>'approved','revision_no'=>2]);
  $invalid=$revision; $invalid['unit_code']='t';
  $this->actingAs($planner)->postJson('/api/v1/chemical-targets',$invalid)->assertStatus(422);
 }
}
