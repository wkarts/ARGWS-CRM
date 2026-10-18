<?php
/** Execute real Staff_model methods with actual PDO rows and a minimal CI query builder. */
require __DIR__.'/issuers_staff_access.php';
$masterBaseline=$checks;
define('BASEPATH',__DIR__);define('APPPATH',dirname(__DIR__).'/application/');
#[AllowDynamicProperties] class App_Model {}
function db_prefix(){return $GLOBALS['prefix'];}
function get_staff_user_id(){return $GLOBALS['masterActor'];}
function is_admin(){return $GLOBALS['masterActorAdmin'];}
function hooks(){return $GLOBALS['masterHooks'];}
function log_activity($message){}
function get_staff_full_name($id){return 'Synthetic';}
function app_hash_password($value){throw new RuntimeException('own profile cannot change password');}
$masterActor=1;$masterActorAdmin=true;
$masterHooks=new class{public $filter=null;public array $actions=[];function apply_filters($event,$value,...$args){return $this->filter?($this->filter)($event,$value):$value;}function do_action(...$args){$this->actions[]=$args;}};
require __DIR__.'/../application/models/Staff_model.php';
class MasterStaffDb {
 public bool $db_debug=true;public bool $save_queries=true;private array $where=[];private int $affected=0;
 public function __construct(public $inner){}
 public function field_exists($field,$table){return $this->inner->field_exists($field,$table);}
 public function query($sql,$params=[]){return $this->inner->query($sql,$params);}
 public function where($field,$value){$this->where[$field]=$value;return $this;}
 public function update($table,$data){$where=$this->where;$this->where=[];if(!$data)return false;$sql='UPDATE '.$table.' SET '.implode(',',array_map(fn($key)=>$key.'=?',array_keys($data))).' WHERE '.implode(' AND ',array_map(fn($key)=>$key.'=?',array_keys($where)));$this->query($sql,array_merge(array_values($data),array_values($where)));$this->affected=1;return true;}
 public function affected_rows(){return $this->affected;}
}
class MasterStaffModel extends Staff_model { public function update_permissions($permissions,$id){return false;} }
$db->query("ALTER TABLE {$prefix}staff ADD is_not_staff INT DEFAULT 0");$db->query("ALTER TABLE {$prefix}staff ADD phonenumber VARCHAR(40)");$db->query("ALTER TABLE {$prefix}staff ADD email VARCHAR(100)");
$db->query("INSERT INTO {$prefix}staff(staffid,admin,active,firstname,lastname) VALUES(17,1,1,'Synthetic','Master')");$db->query("UPDATE {$p}installation_identity SET master_staff_id=17 WHERE id=1");
$model=new MasterStaffModel();$model->db=new MasterStaffDb($db);$model->load=new class{function model($name){}};$model->departments_model=new class{function get_staff_departments($id){return [];}};
rejects(fn()=>$model->delete(17,1),'issuer_master_protected');same([], $masterHooks->actions,'master delete denied before hooks/transfer');
rejects(fn()=>$model->change_staff_status(17,0),'issuer_master_protected');same(1,(int)$db->query("SELECT active FROM {$prefix}staff WHERE staffid=17")->result_array()[0]['active'],'master remains active');
same(['cant_remove_installation_master'=>true],$model->update(['firstname'=>'Synthetic','lastname'=>'Master'],17),'another admin cannot demote proven master17');
same(['cant_remove_installation_master'=>true],$model->update(['administrator'=>1,'active'=>0,'firstname'=>'Synthetic','lastname'=>'Master'],17),'administrator field cannot mask master deactivation');
$masterActor=17;same(['cant_remove_yourself_from_admin'=>true],$model->update(['firstname'=>'Synthetic','lastname'=>'Master'],17),'historical self-demotion protection retained');$masterActor=1;
same(true,$model->update(['administrator'=>1,'firstname'=>'Synthetic changed','lastname'=>'Master','phonenumber'=>'000'],17),'normal master contact edit remains possible');
same('000',$db->query("SELECT phonenumber FROM {$prefix}staff WHERE staffid=17")->result_array()[0]['phonenumber'],'normal contact persisted');
$model->change_staff_status(3,0);same(0,(int)$db->query("SELECT active FROM {$prefix}staff WHERE staffid=3")->result_array()[0]['active'],'ordinary staff status behavior retained');$model->change_staff_status(3,1);
$masterHooks->filter=static fn($event,$value)=>$event==='before_staff_status_change'?0:$value;
rejects(fn()=>$model->change_staff_status(17,1),'issuer_master_protected');$masterHooks->filter=null;
same(false,$model->delete(4,4),'ordinary administrator is not treated as master');
$masterActor=3;$masterActorAdmin=false;
foreach(['admin','administrator','active','staffid','role','is_not_staff','permissions'] as$field){rejects(fn()=>$model->update_profile([$field=>1,'firstname'=>'Attack'],3),'issuer_staff_profile_field_denied');}
$masterHooks->filter=static fn($event,$value)=>$event==='before_staff_update_profile'?array_merge($value,['admin'=>1,'active'=>0,'staffid'=>17,'password'=>'injected']):$value;
same(true,$model->update_profile(['firstname'=>'Own synthetic','password'=>'ignored'],3),'self contact edit permits only explicit fields even after hook');
$row=$db->query("SELECT * FROM {$prefix}staff WHERE staffid=3")->result_array()[0];same(0,(int)$row['admin'],'own profile cannot promote through hook');same(1,(int)$row['active'],'own profile cannot deactivate through hook');same('Own synthetic',$row['firstname'],'permitted contact field saved');
$masterHooks->filter=null;$masterActor=17;$masterActorAdmin=true;
rejects(fn()=>$model->update_profile(['active'=>0],17),'issuer_staff_profile_field_denied');
$db->query("UPDATE {$prefix}staff SET admin=0,active=0 WHERE staffid=17");
rejects(fn()=>$model->delete(17,1),'issuer_master_protected');same(false,$acl->isMaster(17),'preservation marker does not grant inactive master access');
echo 'Issuer master preservation/self-profile: '.($checks-$masterBaseline).' additional checks passed.'.PHP_EOL;
