<?php
require __DIR__.'/pige_bridge_preparation_mysql.php';
$start=$GLOBALS['essentialChecks'];$uiActor=7;$uiAdmin=true;$_SERVER['HTTPS']='on';$_SERVER['SERVER_PORT']='443';
$diagnosticCalls=[];$diagnosticHook=null;
$diagnosticScope=$connections->diagnosticSnapshot('other-ui',1,7);
essentialCheck(array_keys($diagnosticScope)===['school_id','issuer_id','account_id','environment'],'diagnostic snapshot has no secret or cipher');
essentialThrows(fn()=>$connections->diagnosticSnapshot('other-ui',1,12),'pige_configuration_access_denied','foreign company diagnostic denied');
essentialThrows(fn()=>$connections->diagnosticSnapshot('other-ui',2,7),'pige_configuration_changed_reload','obsolete diagnostic denied');
essentialThrows(fn()=>$connections->diagnosticSnapshot('disable-ui',4,7),'pige_diagnostic_enabled_required','disabled local connection remains disabled');
class DiagnosticSettingsFixture extends Settings{
 public $config;
 protected function discoveryService(callable $authorize):PigeBridgeDiscovery{return new PigeBridgeDiscovery($authorize,function($url,$credential,$host,$ip,$method,$payload){
  $GLOBALS['diagnosticCalls'][]=$method;if($GLOBALS['diagnosticHook'])($GLOBALS['diagnosticHook'])($method);
  return ['status'=>200,'body'=>json_encode($method==='GET'?['configuration'=>['id'=>'remote-id','version'=>3,'enabled'=>true,'secret_configured'=>true,'base_url'=>'https://crm.example']+$GLOBALS['diagnosticScope']]:['reachable'=>true,'authenticated'=>true,'charge_created'=>false])];
 },fn()=>['8.8.8.8']);}
}
$ui=new DiagnosticSettingsFixture();$ui->config=new class{function item($key){return 'https://crm.example/';}};
$payload=['connection_key'=>'other-ui','revision'=>'1','pige_csrf'=>str_repeat('a',64),'pige_url'=>'https://pige.example','admin_credential'=>'synthetic.header.signature'];$ui->input->values=$payload;
$before=$row('other-ui');$ui->input->verb='GET';$ui->test_connection();essentialCheck(end($uiErrors)===405&&$diagnosticCalls===[],'diagnostic GET rejected');$ui->input->verb='POST';
$ui->input->values['pige_csrf']='wrong';$ui->test_connection();essentialCheck(end($uiErrors)===403&&$diagnosticCalls===[],'diagnostic CSRF rejected');$ui->input->values=$payload;
$_SERVER['HTTPS']='off';$_SERVER['SERVER_PORT']='80';$ui->test_connection();essentialCheck(end($uiAlerts)==='danger'&&$diagnosticCalls===[],'diagnostic requires trusted HTTPS');$_SERVER['HTTPS']='on';$_SERVER['SERVER_PORT']='443';
$ui->test_connection();essentialCheck(end($uiAlerts)==='success'&&$diagnosticCalls===['GET','POST','GET'],'native action tests enabled scope and rechecks remote revision');
essentialCheck($row('other-ui')===$before,'diagnostic never changes local revision, secret, enabled state or consent');
$diagnosticCalls=[];$diagnosticHook=function($method)use($db,$p){if($method==='GET')$db->query('UPDATE '.$p.'pige_bridge_connections SET revision=revision+1 WHERE connection_key=?',['other-ui']);};
$ui->test_connection();essentialCheck(end($uiAlerts)==='danger'&&$diagnosticCalls===['GET'],'local change during preview prevents diagnostic POST');
essentialCheck(!str_contains(json_encode($GLOBALS['essentialLogs']),'synthetic.header.signature'),'diagnostic does not log administrative credential');
echo 'PIGE diagnostic controller/SQL: '.($GLOBALS['essentialChecks']-$start).' additional checks; remote transport simulated.'.PHP_EOL;
