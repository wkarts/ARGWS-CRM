<?php
require __DIR__.'/native_blank_timers.php';
$db->query('UPDATE '.$p.'company_blank_task_timers SET closed_at=NULL WHERE id=?',[$second]);
require_once APPPATH.'services/CompanySettingsStorage.php';$formatSettings=new CompanySettingsStorage($db,$p);$formatSettings->mutate($a,7,'add','dateformat','d/m/Y|%d/%m/%Y');$formatSettings->mutate($a,7,'add','time_format','24');
function show_error($message,$status=500){throw new RuntimeException('http_'.$status);}
final class CompanyRequestScope{public static int $issuer;public static int $actor=7;public static function nativeFor($ci){return true;}public static function context(){return new class{public function issuerId(){return CompanyRequestScope::$issuer;}public function staffId(){return CompanyRequestScope::$actor;}};}}
#[AllowDynamicProperties]
class AdminController{public function __construct(){$this->db=$GLOBALS['db'];$this->input=new class{public string $verb='GET';public function method($upper=false){return $this->verb;}};$this->output=new class{public string $body='';public array $headers=[];public function set_header($v){$this->headers[]=$v;return $this;}public function set_content_type($v){return $this;}public function set_output($v){$this->body=$v;return $this;}};$this->load=new class{public array $last=[];public $observer=null;public function model($name){throw new RuntimeException('global_model_forbidden');}public function view($name,$data,$return){$this->last=[$name,$data,$return];if($this->observer)($this->observer)();return '<li data-native-timer="synthetic"></li>';}};}}
require APPPATH.'controllers/admin/Tasks.php';CompanyRequestScope::$issuer=$a;$controller=new Tasks;$controller->get_staff_started_timers();$json=json_decode($controller->output->body,true,512,JSON_THROW_ON_ERROR);
essentialCheck($json['total_timers']===2&&$controller->load->last[0]==='admin/tasks/started_timers','real controller reuses native partial with actual owned timers');
essentialCheck($controller->load->last[1]['nativeTimerPolicy']['can_start_blank']===false&&$controller->load->last[1]['nativeTimerPolicy']['auto_stop']===false,'existing blank suppresses duplicate using actual timer policy');
essentialCheck(isset($controller->load->last[1]['startedTimers'][0]['started_at_label']),'native view receives explicit formatted start label');
essentialCheck(in_array('Cache-Control: no-store',$controller->output->headers,true),'private timer response is not cacheable');
$controller->input->verb='POST';essentialThrows(fn()=>$controller->get_staff_started_timers(),'http_405','read endpoint rejects unexpected method');$controller->input->verb='GET';
$controller->output->body='';$controller->load->observer=function()use($db,$p,$a){$db->query('UPDATE '.$p.'issuers_registry SET active=0 WHERE id=?',[$a]);};essentialThrows(fn()=>$controller->get_staff_started_timers(),'http_403','company revocation during rendering rejects response');essentialCheck($controller->output->body==='','no timer HTML emitted after revoked context');
echo "Native Tasks timer controller: {$GLOBALS['essentialChecks']} cumulative checks passed.\n";
