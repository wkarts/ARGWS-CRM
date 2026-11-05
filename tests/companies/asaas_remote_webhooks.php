<?php
/** Company/account/environment boundary, using real SQL/cipher and a synthetic provider. */
require __DIR__.'/asaas_environments.php';
$start=$GLOBALS['essentialChecks'];
$dto=array_values(array_filter($asaas->accounts($a,7),fn($row)=>(int)$row['id']===$accounts[$a]))[0];
$rows=[];$webhookCalls=[];$fail=false;
$transport=function($environment,$method,$path,$payload,$secret)use(&$rows,&$webhookCalls,&$fail){
    $webhookCalls[]=[$environment,$method,$path,$secret];
    if($environment!=='sandbox'||$secret!=='key-A')throw new LogicException('wrong account credentials');
    if($path==='wallets/')return ['data'=>[['id'=>'wallet-A']],'hasMore'=>false];
    if($method==='GET')return ['data'=>$rows,'hasMore'=>false];
    if($fail)throw new RuntimeException('synthetic transport failure');
    if($method==='POST')return $rows[0]=$payload+['id'=>'hook_company_a'];
    if($method==='PUT')return $rows[0]=array_replace($rows[0],$payload);
    if($method==='DELETE'){$rows=[];return ['deleted'=>true,'id'=>'hook_company_a'];}
    throw new LogicException('unexpected operation');
};
$input=['name'=>'Company A','email'=>'ops@example.test','events'=>['PAYMENT_RECEIVED'],'enabled'=>'1'];
$base='https://crm.example.test/subdir/';
$call=fn($action,$fields=[])=>$asaas->remoteWebhook($a,7,$accounts[$a],$dto['revision'],$action,$fields,$base,'index.php',$dec,$transport);
$before=$db->query('SELECT * FROM '.$p.'asaas_payment_origins ORDER BY id')->result_array();
essentialThrows(fn()=>$call('create',$input),'asaas_module_161_required','remote administration requires native161 marker');
\Asaas\CompanyOriginPolicy::ensure($db,$p,true);
$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.6.1','asaas']);
$made=$call('create',$input);
essentialCheck($made['webhook']['id']==='hook_company_a','company remote creation confirmed');
essentialCheck($rows[0]['authToken']===$sandboxHook['one_time_token'],'exact locally accepted token sent to own provider account');
essentialCheck(str_contains($rows[0]['url'],'/subdir/index.php/company_asaas_callback/integration/'.$dto['webhook_public_id']),'configured subdir and exact company public receiver preserved');
essentialCheck(!str_contains(json_encode($made),$sandboxHook['one_time_token']),'remote response strips secret');
$count=count($webhookCalls);
essentialThrows(fn()=>$asaas->remoteWebhook($b,7,$accounts[$a],$dto['revision'],'inspect',[],$base,'',$dec,$transport),'asaas_company_access_denied','foreign account refused before transport');
essentialThrows(fn()=>$asaas->remoteWebhook($a,7,$accounts[$a],str_repeat('0',64),'inspect',[],$base,'',$dec,$transport),'asaas_company_stale','stale account revision refused');
essentialCheck(count($webhookCalls)===$count,'scope/revision denials never contact provider');
$updated=$call('update',array_replace($input,['name'=>'Updated A']));essentialCheck($updated['webhook']['name']==='Updated A','update actual company receiver');
$fail=true;$beforeFail=count($webhookCalls);
essentialThrows(fn()=>$call('update',$input),'asaas_company_transport_unconfirmed','uncertain update is surfaced');
essentialCheck(count($webhookCalls)===$beforeFail+3,'wallet, list and single PUT; no silent retry');
$audit=$asaas->remoteWebhookAudit($a,7);essentialCheck($audit[0]['event']==='asaas_webhook_update_unconfirmed','uncertainty recorded durably');
essentialCheck(!str_contains(json_encode($audit),'key-A')&&!str_contains(json_encode($audit),$sandboxHook['one_time_token']),'audit omits credentials and token');
$fail=false;$call('inspect');$call('delete');essentialCheck($rows===[],'explicit deletion confirmed');
essentialCheck($before===$db->query('SELECT * FROM '.$p.'asaas_payment_origins ORDER BY id')->result_array(),'webhook administration leaves all financial origins untouched');
echo 'Company remote webhooks: '.($GLOBALS['essentialChecks']-$start).' added checks passed.'.PHP_EOL;
