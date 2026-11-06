<?php
require __DIR__.'/pige_bridge_discovery_mysql.php';
$start=$GLOBALS['essentialChecks'];$uiActor=7;$uiAdmin=true;$_SERVER['HTTPS']='on';$_SERVER['SERVER_PORT']='443';
$db->query('UPDATE '.$p.'modules SET installed_version=?,active=1 WHERE module_name=?',['1.1.0','pige_bridge']);
$before=$row('disable-ui');$snapshot=$connections->preparationSnapshot('disable-ui',3,7);
essentialCheck($snapshot['scope']===$uiScope&&$snapshot['secret']===$cipher->decrypt($before['secret_cipher']),'server-only snapshot returns precisely the owned disabled connection');
essentialThrows(fn()=>$connections->preparationSnapshot('other-ui',1,12),'pige_configuration_access_denied','another company permission denied before secret access');
essentialThrows(fn()=>$connections->preparationSnapshot('disable-ui',2,7),'pige_configuration_changed_reload','stale local revision blocks preparation');
essentialThrows(fn()=>$connections->preparationSnapshot('other-ui',1,7),'pige_preparation_disabled_required','enabled connection cannot be provisioned');
$remotePreparationCalls=[];$preparationRemote=null;$preparationHook=null;
class PreparationSettingsFixture extends Settings{
 public $config;
 protected function discoveryService(callable $authorize):PigeBridgeDiscovery{return new PigeBridgeDiscovery($authorize,function($url,$credential,$host,$ip,$method,$payload){
  $GLOBALS['remotePreparationCalls'][]=$method;
  if($GLOBALS['preparationHook'])($GLOBALS['preparationHook'])($method);
  if($method==='GET')return ['status'=>200,'body'=>json_encode(['configuration'=>$GLOBALS['preparationRemote']])];
  $GLOBALS['preparationRemote']=['version'=>1,'enabled'=>false,'base_url'=>$payload['base_url'],'issuer_id'=>$payload['issuer_id'],'account_id'=>$payload['account_id'],'environment'=>$payload['environment'],'secret_configured'=>true];
  return ['status'=>200,'body'=>json_encode($GLOBALS['preparationRemote'])];
 },fn()=>['8.8.8.8']);}
}
$ui=new PreparationSettingsFixture();$ui->config=new class{function item($key){return 'https://crm.example/';}};
$payload=['connection_key'=>'disable-ui','revision'=>'3','confirm_transfer'=>'1','pige_csrf'=>str_repeat('a',64),'pige_url'=>'https://pige.example','admin_credential'=>'synthetic.header.signature'];$ui->input->values=$payload;
$ui->input->verb='GET';$ui->prepare_remote();essentialCheck(end($uiErrors)===405&&$remotePreparationCalls===[],'preparation GET denied');$ui->input->verb='POST';
$ui->input->values['pige_csrf']='invalid';$ui->prepare_remote();essentialCheck(end($uiErrors)===403&&$remotePreparationCalls===[],'preparation CSRF denied');$ui->input->values=$payload;
unset($ui->input->values['confirm_transfer']);$ui->prepare_remote();essentialCheck(end($uiAlerts)==='danger'&&$remotePreparationCalls===[],'secret transfer requires explicit confirmation');$ui->input->values=$payload;
$_SERVER['HTTPS']='off';$_SERVER['SERVER_PORT']='80';$_SERVER['HTTP_X_FORWARDED_PROTO']='https';$ui->prepare_remote();essentialCheck(end($uiAlerts)==='danger'&&$remotePreparationCalls===[],'forwarded HTTPS cannot authorize secret transfer');$_SERVER['HTTPS']='on';$_SERVER['SERVER_PORT']='443';
$ui->prepare_remote();essentialCheck(end($uiAlerts)==='success'&&$remotePreparationCalls===['GET','PUT'],'native controller prepares only an absent remote connection');
essentialCheck($row('disable-ui')===$before&&$row('other-ui')===$otherBefore,'preparation preserves local secret, revision, consent and other company');
$ui->prepare_remote();essentialCheck(end($uiAlerts)==='danger'&&$remotePreparationCalls===['GET','PUT','GET'],'repeated action never rewrites the remote configuration');
$preparationRemote=null;$preparationHook=function($method)use($db,$p,$a){if($method==='GET')$db->query('UPDATE '.$p.'pige_bridge_connections SET revision=revision+1 WHERE connection_key=?',['disable-ui']);};$beforeCalls=count($remotePreparationCalls);
$ui->prepare_remote();essentialCheck(end($uiAlerts)==='danger'&&count($remotePreparationCalls)===$beforeCalls+1,'local revision changed during preview blocks PUT');
essentialCheck(!str_contains(json_encode($GLOBALS['essentialLogs']),'synthetic.header.signature'),'preparation never logs temporary credential');
echo 'PIGE preparation controller/SQL: '.($GLOBALS['essentialChecks']-$start).' additional checks; remote transport simulated.'.PHP_EOL;
