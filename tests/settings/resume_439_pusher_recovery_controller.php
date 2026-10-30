<?php
/** Actual controller/services/storage/templates; only framework I/O and provider boundary are adapters. */
require __DIR__.'/resume_439_pusher_controller_fixture.php';
$recoveryStart=$GLOBALS['essentialChecks'];
class CompanyRequestScope
{
    public static bool $sealed=true;public static int $issuer;public static int $actor=7;
    public static function nativeFor($owner):bool{return true;}
    public static function sealedFor($owner):bool{return self::$sealed;}
    public static function context(){return new class {public function issuerId(){return CompanyRequestScope::$issuer;}public function staffId(){return CompanyRequestScope::$actor;}public function isPrimary(){return false;}public function available(){return [];}public function csrf(){return 'synthetic-switch';}};}
    public static function token(){return 'synthetic-pusher-context';}public static function invalidateCaches($owner):void{}
    public static function adminUrl($owner,string $path=''):string{return 'https://synthetic.invalid/admin/'.$path;}
}
class AdminController{public function __construct(){}public function __get($name){return $GLOBALS['CI']->$name;}}
function show_error($message,$status=500){$GLOBALS['CI']->output->status=$status;}
function redirect($url,$method='location',$code=302){$GLOBALS['pusherRedirect']=$url;$GLOBALS['CI']->output->status=$code;}
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function base_url($path=''){return 'https://synthetic.invalid/'.$path;}
function form_open($url,$attributes=[]){$attrs='';foreach($attributes as$k=>$v)$attrs.=' '.htmlspecialchars($k,ENT_QUOTES,'UTF-8').'="'.htmlspecialchars($v,ENT_QUOTES,'UTF-8').'"';return '<form method="post" action="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'"'.$attrs.'><input type="hidden" name="synthetic_csrf" value="native"><input type="hidden" name="_company_context" value="'.CompanyRequestScope::token().'">';}
function form_close(){return '</form>';}
$ci=$GLOBALS['CI'];
$ci->input=new class {public string $verb='GET';public array $gets=[],$posts=[];public function method($upper=false){return $upper?$this->verb:strtolower($this->verb);}public function get($k,$f=false){return $this->gets[$k]??null;}public function post($k,$f=false){return $this->posts[$k]??null;}};
$ci->output=new class {public int $status=200;public array $headers=[];public string $body='';public function set_header($v){$this->headers[]=$v;return $this;}public function set_status_header($v){$this->status=$v;return $this;}public function set_content_type($v){$this->headers[]='Content-Type: '.$v;return $this;}public function set_output($v){$this->body=$v;return $this;}};
$ci->session=new class {public array $values=[];public function userdata($k){return $this->values[$k]??null;}public function set_userdata($k,$v){$this->values[$k]=$v;}};
$ci->load=new class {public array $views=[];public function library($name){if($name!=='encryption')throw new LogicException('unexpected framework library');}public function view($n,$data){$this->views[]=[$n,$data];}};
$ci->encryption=new class {public function encrypt($v){return 'sealed:'.base64_encode($v);}public function decrypt($v){return str_starts_with($v,'sealed:')?base64_decode(substr($v,7),true):false;}};
$harness=new class {public function __get($name){return $GLOBALS['CI']->$name;}public function render(array $data):string{extract($data);ob_start();try{include APPPATH.'views/admin/settings/native_pusher.php';return ob_get_clean();}catch(Throwable $e){ob_end_clean();throw $e;}}};
require APPPATH.'controllers/admin/Settings.php';$controller=new Settings();
// Three distinct non-master roles in B; none has access to A.
foreach([13,14,15]as$staff){$db->query('INSERT INTO '.$p.'staff(staffid,active,admin,firstname,lastname) VALUES(?,1,0,?,?)',[$staff,'Synthetic','Recovery '.$staff]);$db->query('INSERT INTO '.$p.'issuers_staff(issuer_id,staff_id,can_view) VALUES(?,?,1)',[$b,$staff]);}
$grants=[13=>[['settings','view'],['settings','edit'],['pusher','view'],['pusher','configure'],['tasks','view'],['projects','view']],14=>[['pusher','view'],['tasks','view'],['projects','view']],15=>[['settings','view'],['settings','edit'],['pusher','view'],['pusher','configure']]];
foreach($grants as$staff=>$permissions)foreach($permissions as[$feature,$cap])$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,?,?,?)',[$b,$staff,$feature,$cap]);
$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,?,?)',[$b,'pusher','view']);
$beforeRevision=$profile->page($b,7)['revision'];$profile->save($b,12,$beforeRevision,(string)(900000+$b),'synthetic_public_key_'.$b,'eu','old-owner-recovery-secret',$encrypt);$epoch=$beforeRevision+1;
$oldDescriptor=$notices->descriptor($b,7);$intent=hash('sha256','recovery-no-adoption');$notices->queue($b,7,$tb,(int)$ts->get($b,7,$tb)['revision'],$epoch,$intent);$oldRows=$db->query('SELECT * FROM '.$p.'company_pusher_task_notices ORDER BY request_key')->result_array();
$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=? AND staff_id=12 AND feature='pusher' AND capability='configure'",[$b]);
essentialThrows(fn()=>$notices->descriptor($b,13),'company_pusher_denied','service still rejects old credential owner before any controller recovery');
$profileBefore=$db->query('SELECT * FROM '.$p.'company_pusher_profiles WHERE issuer_id=?',[$b])->row_array();$networkBefore=count($calls);
CompanyRequestScope::$issuer=$b;CompanyRequestScope::$actor=13;$controller->pusher();
essentialCheck($ci->output->status===200&&count($ci->load->views)===1,'current non-master configurator can reopen GUI after old owner loses configure');
$view=end($ci->load->views)[1];
essentialCheck($view['can_configure']&&$view['profile']['revision']===$epoch&&$view['descriptor']===null&&$view['inbox']===[]&&$view['activity']===['tasks'=>[],'publish'=>false,'notices'=>[]],'recovery renders only current configuration, without old descriptors, inbox, tasks or attempts');
$html=$harness->render($view);
essentialCheck(str_contains($html,'id="pusher-profile-form"')&&str_contains($html,'company_pusher_csrf')&&str_contains($html,'synthetic_csrf')&&str_contains($html,'_company_context')&&str_contains($html,'perfil anterior perdeu autorização'),'actual GUI offers explicit recovery with all CSRF layers and clear explanation');
essentialCheck(!str_contains($html,'id="pusher-start"')&&!str_contains($html,'pusher-task-queue')&&!str_contains($html,$intent)&&!str_contains($html,'old-owner-recovery-secret')&&!str_contains($html,'sealed:'),'recovery HTML never releases old notifications, channel or ciphertext');
essentialCheck($db->query('SELECT * FROM '.$p.'company_pusher_profiles WHERE issuer_id=?',[$b])->row_array()===$profileBefore&&$db->query('SELECT * FROM '.$p.'company_pusher_task_notices ORDER BY request_key')->result_array()===$oldRows&&count($calls)===$networkBefore,'GET recovery changes no source, epoch, intent or provider state');
// Configuration-only staff can see recovery, but does not become a receiver.
CompanyRequestScope::$actor=15;$ci->output->status=200;$controller->pusher();$configOnly=end($ci->load->views)[1];essentialCheck($ci->output->status===200&&$configOnly['can_configure']&&$configOnly['descriptor']===null&&$configOnly['activity']['publish']===false,'config-only permission does not acquire task/receiver rights');
// Read-only staff cannot use the old-owner denial as a configuration bypass.
CompanyRequestScope::$actor=14;$ci->output->status=200;$viewCount=count($ci->load->views);$controller->pusher();essentialCheck($ci->output->status===403&&count($ci->load->views)===$viewCount,'receiver without current settings/configure grants cannot recover');
CompanyRequestScope::$actor=13;CompanyRequestScope::$issuer=$a;$ci->output->status=200;$controller->pusher();essentialCheck($ci->output->status===403&&count($ci->load->views)===$viewCount,'B configurator cannot open A settings or notifications');CompanyRequestScope::$issuer=$b;
// Recovery rendering requires all four current grants; the existing POST service guards remain in force.
$csrf=$ci->session->userdata('company_pusher_csrf');$save=['action'=>'save','revision'=>(string)$epoch,'app_id'=>(string)(900000+$b),'app_key'=>'synthetic_public_key_'.$b,'cluster'=>'eu','app_secret'=>'new-explicit-owner-secret','company_pusher_csrf'=>$csrf];
foreach([['settings','view'],['settings','edit'],['pusher','view'],['pusher','configure']]as[$feature,$cap]){
 $db->query('DELETE FROM '.$p.'issuers_resource_permissions WHERE issuer_id=? AND staff_id=13 AND feature=? AND capability=?',[$b,$feature,$cap]);$ci->output->status=200;$ci->input->verb='GET';$ci->input->posts=[];$controller->pusher();essentialCheck($ci->output->status===403,'recovery requires current '.$feature.'.'.$cap);
 $db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,13,?,?)',[$b,$feature,$cap]);
}
$ci->input->verb='POST';$ci->input->posts=$save;unset($ci->input->posts['company_pusher_csrf']);$ci->output->status=200;$controller->pusher();essentialCheck($ci->output->status===403&&$db->query('SELECT revision FROM '.$p.'company_pusher_profiles WHERE issuer_id=?',[$b])->row()->revision==$epoch,'explicit save still rejects missing dedicated CSRF');
// Late audit failure cannot replace the credential owner or advance the epoch.
$ci->input->posts=$save;$db->failOn='INSERT INTO '.$p.'issuers_audit';$ci->output->status=200;$controller->pusher();$db->failOn=null;essentialCheck($ci->output->status===503&&$db->query('SELECT * FROM '.$p.'company_pusher_profiles WHERE issuer_id=?',[$b])->row_array()===$profileBefore,'failed recovery audit rolls back all profile/owner/epoch fields');
$ci->output->status=200;$controller->pusher();essentialCheck($ci->output->status===303&&$profile->page($b,13)['revision']===$epoch+1,'explicit authenticated save creates exactly one new profile epoch');
$afterProfile=$db->query('SELECT * FROM '.$p.'company_pusher_profiles WHERE issuer_id=?',[$b])->row_array();essentialCheck((int)$afterProfile['configured_by']===13&&$afterProfile['secret_cipher']!==$profileBefore['secret_cipher'],'new configuration is bound to the new current owner and explicitly supplied secret');
$ci->output->status=200;$controller->pusher();essentialCheck($ci->output->status===409&&$profile->page($b,13)['revision']===$epoch+1,'repeat old revision cannot silently create another recovery epoch');
$ci->input->verb='GET';$ci->input->posts=[];$ci->output->status=200;$controller->pusher();$recovered=end($ci->load->views)[1];essentialCheck($ci->output->status===200&&$recovered['descriptor']['revision']===$epoch+1&&$recovered['descriptor']['channel']!==$oldDescriptor['channel']&&$recovered['inbox']===[]&&!$recovered['activity']['publish'],'new epoch enables only existing receive rights and never publishes without explicit permission');
essentialThrows(fn()=>$notices->authorizeChannel($b,7,$oldDescriptor['channel'],'1.2',$decrypt),'company_pusher_denied','old epoch subscription remains un-signable after recovery');essentialThrows(fn()=>$notices->dispatch($b,7,$intent,$decrypt),'company_pusher_stale','new owner cannot adopt or resend an intent from the old profile epoch');
essentialCheck($db->query('SELECT * FROM '.$p.'company_pusher_task_notices ORDER BY request_key')->result_array()===$oldRows&&count($calls)===$networkBefore,'old receipts and all provider call counts stay unchanged throughout recovery');
CompanyRequestScope::$actor=14;$ci->input->gets=['reader'=>'1'];$ci->output->status=200;$controller->pusher();essentialCheck($ci->output->status===200&&json_decode($ci->output->body,true)===['items'=>[]],'read-only authorized receiver remains usable after current profile recovery');
CompanyRequestScope::$actor=13;$ci->input->gets=[];$ci->input->verb='DELETE';$ci->output->status=200;$controller->pusher();essentialCheck($ci->output->status===405,'recovery does not add a new HTTP verb');CompanyRequestScope::$sealed=false;$ci->input->verb='GET';$ci->output->status=200;$controller->pusher();essentialCheck($ci->output->status===503,'recovery does not bypass the sealed context');
essentialCheck($db->query('SELECT * FROM '.$p.'options ORDER BY name')->result_array()===$globalBefore,'recovery does not modify global settings or credentials');
echo 'Pusher production controller recovery: '.($GLOBALS['essentialChecks']-$recoveryStart).' added / '.$GLOBALS['essentialChecks'].' cumulative checks ('.$db->driver().'). No network; no old epoch adoption.'.PHP_EOL;
