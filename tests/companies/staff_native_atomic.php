<?php
require __DIR__.'/staff_fixture.php';
#[AllowDynamicProperties] class App_Model {}
function db_prefix(){return $GLOBALS['nativeDb']->prefix;}
function &get_instance(){return $GLOBALS['nativeCI'];}
function get_staff_user_id(){return $GLOBALS['nativeActor'];}
function is_admin(){return (int)$GLOBALS['nativeDb']->query('SELECT admin FROM '.db_prefix().'staff WHERE staffid=?',[get_staff_user_id()])->row()->admin===1;}
function is_staff_member($id){return (int)$GLOBALS['nativeDb']->query('SELECT is_not_staff FROM '.db_prefix().'staff WHERE staffid=?',[$id])->row()->is_not_staff===0;}
function app_hasher(){static $hasher;return $hasher??=new PasswordHash(8,true);}
function app_hash_password($value){return app_hasher()->HashPassword($value);}
function hooks(){return $GLOBALS['nativeHooks'];}
function slug_it($v){return strtolower(str_replace(' ','-',$v));}
function is_client_logged_in(){return false;}
function log_activity($message){nativeEffect('activity');}
function get_staff_full_name($id){return 'Synthetic';}
function nativeEffect($name){$GLOBALS['nativeEffects'][]=[$name,$GLOBALS['nativeDb']->pdo->inTransaction()];}
function send_mail_template(...$args){nativeEffect('welcome');return !$GLOBALS['nativeFailEffect'];}
$GLOBALS['nativeEffects']=[];$GLOBALS['nativeFailEffect']=false;$GLOBALS['nativeActor']=17;
$GLOBALS['nativeHooks']=new class{public array $filters=[];function has_filter($tag){return !empty($this->filters[$tag]);}function apply_filters($tag,$value,...$args){foreach($this->filters[$tag]??[]as$callback){$value=$callback($value,...$args);}return $value;}function do_action($tag,...$args){nativeEffect($tag);}};
require APPPATH.'helpers/custom_fields_helper.php';
$legacy=getenv('STAFF_REPRO452_MODEL');require $legacy?:APPPATH.'models/Staff_model.php';
$db=staffFixture();$GLOBALS['nativeDb']=$db;$GLOBALS['nativeCI']=(object)['db'=>$db];$p=$db->prefix;
$model=new Staff_model();$model->db=$db;$model->load=new class{function model($name){}};$model->departments_model=new class($db){function __construct(private $db){}function get_staff_departments($id){return $this->db->query('SELECT * FROM '.$this->db->prefix.'staff_departments WHERE staffid=?',[$id])->result_array();}};
$resources=['tasks'=>['view','create','edit'],'staff'=>['view','create','edit','delete']];$acl=new IssuerStaffAccess($db,$p,17,$resources);
$input=['firstname'=>'Native','lastname'=>'Synthetic','email'=>'native@example.invalid','phonenumber'=>'000','password'=>'synthetic-native-123','role'=>'','hourly_rate'=>'0','is_not_staff'=>0,'permissions'=>['tasks'=>['view']],'departments'=>[1,2],'custom_fields'=>['staff'=>[1=>'retained custom']],'send_welcome_email'=>'1'];
$prepared=$acl->prepare(null,[1=>'1',2=>'1'],2,[1=>['tasks'=>['view']],2=>['tasks'=>['view']]])+['personal'=>false];
if($legacy){
    // The published452 sequence writes the real model first and ACL second. No mock success.
    $input['role']=0;$id=$model->add($input);$before=staffSnapshot($db);$db->failOn='INSERT INTO '.$p.'issuers_audit';
    staffThrows(fn()=>$acl->save($id,[1=>'1',2=>'1'],2,[1=>['tasks'=>['view']],2=>['tasks'=>['view']]]),'issuer_storage_failed','452 ACL audit failure');$db->failOn=null;
    staffCheck((bool)$db->query('SELECT staffid FROM '.$p.'staff WHERE staffid=?',[$id])->result_array(),'RED452 profile remains after ACL failure');staffCheck($db->query('SELECT * FROM '.$p.'issuers_staff WHERE staff_id=?',[$id])->result_array()===[],'RED452 profile has no usable company');
    echo 'RED452 reproduced real profile/ACL partial commit; original model '.hash_file('sha256',$legacy).PHP_EOL;exit;
}
$result=$model->save_with_company_access('add',$input,null,$prepared,$resources);$id=$result['staff_id'];staffCheck($id>23&&$result['post_commit_failures']===0,'real native add committed with ACL');
$row=$db->query('SELECT * FROM '.$p.'staff WHERE staffid=?',[$id])->row_array();staffCheck((int)$row['role']===0&&app_hasher()->CheckPassword($input['password'],$row['password']),'empty role normalized0 with native password hashing');
staffCheck($row['hourly_rate']==='0.00','native form integer zero round-trips through DECIMAL without false readback failure');
staffCheck(count($db->query('SELECT * FROM '.$p.'staff_departments WHERE staffid=?',[$id])->result_array())===2,'native departments persisted');staffCheck(count($db->query('SELECT * FROM '.$p.'staff_permissions WHERE staff_id=?',[$id])->result_array())===1,'native permissions persisted');staffCheck(count($db->query('SELECT * FROM '.$p.'customfieldsvalues WHERE relid=?',[$id])->result_array())===1,'native custom field persisted');staffCheck(count($db->query('SELECT * FROM '.$p.'dismissed_announcements WHERE userid=?',[$id])->result_array())===1,'native announcements persisted');
staffCheck(count($db->query('SELECT * FROM '.$p.'issuers_staff WHERE staff_id=?',[$id])->result_array())===2,'both company links persisted');staffCheck(array_unique(array_column($GLOBALS['nativeEffects'],1))===[false],'welcome/log/hooks only after transaction commit');
$baseline=staffSnapshot($db);$effects=$GLOBALS['nativeEffects'];
foreach(['INSERT INTO '.$p.'staff ','INSERT INTO '.$p.'staff_departments ','INSERT INTO '.$p.'staff_permissions ','INSERT INTO '.$p.'customfieldsvalues ','INSERT INTO '.$p.'dismissed_announcements ','INSERT INTO '.$p.'issuers_audit ']as$needle){
    $db->failOn=$needle;staffThrows(fn()=>$model->save_with_company_access('add',array_replace($input,['email'=>'failed@example.invalid']),null,$prepared,$resources),'issuer_storage_failed','native failure '.$needle);$db->failOn=null;staffCheck(staffSnapshot($db)===$baseline,'profile plus ACL and native participants restored');staffCheck($effects===$GLOBALS['nativeEffects'],'no before-commit welcome/hooks/activity');
}
foreach(['INSERT INTO '.$p.'staff ','INSERT INTO '.$p.'staff_departments ','INSERT INTO '.$p.'staff_permissions ','INSERT INTO '.$p.'customfieldsvalues ','INSERT INTO '.$p.'dismissed_announcements ']as$needle){
    $db->ignoreOn=$needle;staffThrows(fn()=>$model->save_with_company_access('add',array_replace($input,['email'=>'ignored@example.invalid']),null,$prepared,$resources),'issuer_staff_write_unconfirmed','native ignored SQL '.$needle);$db->ignoreOn=null;staffCheck(staffSnapshot($db)===$baseline,'readback divergence rolls back every native participant');
}
// Each final witness is checked after the ACL audit, not only directly after its own write.
foreach(['staff_permissions'=>'UPDATE '.$p.'staff_permissions SET capability=\'edit\' WHERE staff_id='.$id,'staff_departments'=>'DELETE FROM '.$p.'staff_departments WHERE staffid='.$id,'customfieldsvalues'=>'UPDATE '.$p.'customfieldsvalues SET value=\'late\' WHERE relid='.$id,'staff'=>'UPDATE '.$p.'staff SET firstname=\'late\' WHERE staffid='.$id]as$table=>$mutation){
    $db->observer=static function($sql,$params,$db)use($mutation){if(str_starts_with($sql,'SELECT actor_id,event,issuer_id,subject_id,created_at,metadata_json FROM '.$db->prefix.'issuers_audit')){$db->observer=null;$db->pdo->exec($mutation);}};
    $change=array_replace($input,['firstname'=>'Changed','password'=>'','send_welcome_email'=>null]);unset($change['send_welcome_email']);
    staffThrows(fn()=>$model->save_with_company_access('update',$change,$id,$prepared,$resources),'issuer_staff_write_unconfirmed','late native witness '.$table);staffCheck(staffSnapshot($db)===$baseline,'late mutation rollback '.$table);
}
$change=array_replace($input,['firstname'=>'Changed','password'=>'']);unset($change['send_welcome_email']);$updated=$model->save_with_company_access('update',$change,$id,$prepared,$resources);staffCheck($db->query('SELECT firstname FROM '.$p.'staff WHERE staffid=?',[$id])->row()->firstname==='Changed','real native edit positive');
$change['hourly_rate']='12.5';$model->save_with_company_access('update',$change,$id,$prepared,$resources);staffCheck($db->query('SELECT hourly_rate FROM '.$p.'staff WHERE staffid=?',[$id])->row()->hourly_rate==='12.50','native one-decimal hourly rate remains exact');
$decimalBefore=staffSnapshot($db);$db->ignoreOn='UPDATE '.$p.'staff SET';staffThrows(fn()=>$model->save_with_company_access('update',array_replace($change,['hourly_rate'=>'99']),$id,$prepared,$resources),'issuer_staff_write_unconfirmed','ignored decimal profile update still rejected');$db->ignoreOn=null;staffCheck(staffSnapshot($db)===$decimalBefore,'decimal normalization never masks ignored write or mutates ACL');
$GLOBALS['nativeActor']=$id;$personal=['personal'=>true,'default'=>1];$model->save_with_company_access('update_profile',['firstname'=>'Own change'],$id,$personal,$resources);staffCheck((int)$db->query('SELECT issuer_id FROM '.$p.'issuers_staff_defaults WHERE staff_id=?',[$id])->row()->issuer_id===1,'personal profile and valid default commit together');
$own=staffSnapshot($db);$db->failOn='INSERT INTO '.$p.'issuers_audit ';staffThrows(fn()=>$model->save_with_company_access('update_profile',['firstname'=>'Lost'],$id,['personal'=>true,'default'=>2],$resources),'issuer_storage_failed','self profile/default audit failure');$db->failOn=null;staffCheck(staffSnapshot($db)===$own,'self profile and default rolled back together');
foreach(['admin','administrator','active','staffid','role','is_not_staff','permissions']as$field){staffThrows(fn()=>$model->save_with_company_access('update_profile',[$field=>1,'firstname'=>'Forgery'],$id,$personal,$resources),'issuer_staff_profile_field_denied','self protected field '.$field);}
$GLOBALS['nativeActor']=17;$GLOBALS['nativeHooks']->filters['before_update_staff_member']=[static fn($v)=>$v];$before=staffSnapshot($db);staffThrows(fn()=>$model->save_with_company_access('update',$change,$id,$prepared,$resources),'issuer_staff_extension_unreviewed','unreviewed pre-hook rejected before native writes');staffCheck($before===staffSnapshot($db),'pre-hook refusal preserves all rows');$GLOBALS['nativeHooks']->filters=[];
$GLOBALS['nativeFailEffect']=true;$result=$model->save_with_company_access('add',array_replace($input,['email'=>'effect-failure@example.invalid']),null,$prepared,$resources);staffCheck($result['post_commit_failures']===1,'failed welcome reported separately after confirmed commit');staffCheck((bool)$db->query('SELECT staffid FROM '.$p.'staff WHERE staffid=?',[$result['staff_id']])->result_array(),'postcommit transport failure does not undo saved identity');$GLOBALS['nativeFailEffect']=false;
$master=(new IssuerStaffAccess($db,$p,17,$resources))->prepare(17,[],2,[])+['personal'=>false];$identity=$db->query('SELECT * FROM '.$p.'issuers_installation_identity')->result_array();$links=$db->query('SELECT * FROM '.$p.'issuers_staff WHERE staff_id=17 ORDER BY issuer_id')->result_array();
$model->save_with_company_access('update',['administrator'=>'1','firstname'=>'Master retained','lastname'=>'Existing','password'=>''],17,$master,$resources);staffCheck($identity===$db->query('SELECT * FROM '.$p.'issuers_installation_identity')->result_array(),'recognized master and principal unchanged');staffCheck(count($db->query('SELECT * FROM '.$p.'issuers_staff WHERE staff_id=17 ORDER BY issuer_id')->result_array())===count($links)+1,'master default adds membership without deleting previous links');
$protected=staffSnapshot($db);staffThrows(fn()=>$model->save_with_company_access('update',['administrator'=>'1','active'=>0,'firstname'=>'Forbidden','lastname'=>'Master','password'=>''],17,$master,$resources),'issuer_master_protected','master cannot deactivate inside composition');staffCheck($protected===staffSnapshot($db),'master refusal rolls back profile/default/grants');
$db->trans_begin();staffThrows(fn()=>$model->save_with_company_access('update',$change,$id,$prepared,$resources),'issuer_outer_transaction_not_allowed','native refuses caller transaction');staffCheck($db->pdo->inTransaction(),'native leaves caller transaction open');$db->trans_rollback();
echo 'Native Staff atomic: '.$GLOBALS['staffChecks'].' checks passed; '.PHP_VERSION.'; '.$db->pdo->getAttribute(PDO::ATTR_SERVER_VERSION).PHP_EOL;
