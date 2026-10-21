<?php
/** Same wallet/payment identifier across environments never merges sources. Remote API entirely simulated. */
require __DIR__.'/asaas_positive.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyRemoteOperations.php';
$envStart=$GLOBALS['essentialChecks'];$productionResponse=[];$productionCalls=[];$sameRemote=$remote[$a];
$productionTransport=function($environment,$method,$path,$payload,$secret)use($mock,$sameRemote,&$productionResponse,&$productionCalls){
    $productionCalls[]=[$environment,$method,$path,$secret];if($environment!=='production')throw new LogicException('unexpected environment fallback');
    if($method==='POST'){$productionResponse=$payload+['id'=>$sameRemote,'status'=>'PENDING','netValue'=>$payload['value'],'paymentDate'=>'','creditDate'=>'','deleted'=>false];return $productionResponse;}
    if($method==='DELETE'){$productionResponse['deleted']=true;return ['id'=>$sameRemote,'deleted'=>true];}
    if($path==='payments/'.$sameRemote)return $productionResponse;
    return $mock($environment,$method,$path,$payload,$secret);
};
$productionAccount=$asaas->saveAccount($a,7,'production','wallet-A','key-A-production',$zero,$enc);$config=array_values(array_filter($asaas->accounts($a,7),fn($r)=>$r['environment']==='production'))[0];$asaas->verifyAccount($a,7,$productionAccount,$config['revision'],$dec,$productionTransport);
essentialThrows(fn()=>$asaas->saveAccount($b,7,'production','wallet-A','key-B-foreign',$zero,$enc),'asaas_company_account_conflict','same wallet and environment cannot belong to another named company');
$invoice=$s->create($a,7,array_replace($input,['client_id'=>$ca]),hash('sha256','asaas-production-collision-invoice'));$origin=$asaas->issue($a,7,$invoice,$productionAccount,'cus_a','PIX',$s->revision($a,7,$invoice),hash('sha256','asaas-production-collision-origin'),$dec,$productionTransport);
$beforeSandbox=$db->query("SELECT * FROM {$p}asaas_payment_origins WHERE id=?",[$origins[$a]])->row_array();$asaas->query($a,7,$origin,$dec,$productionTransport);
$production=$db->query("SELECT * FROM {$p}asaas_payment_origins WHERE id=?",[$origin])->row_array();essentialCheck($production['remote_id']===$beforeSandbox['remote_id']&&$production['account_id']===$beforeSandbox['account_id']&&$production['environment']==='production'&&$beforeSandbox['environment']==='sandbox','same wallet/remoteID in production and sandbox creates distinct immutable origins');
$hook=asaasFixtureRotateWebhook($asaas,$a,7,$productionAccount,$config['revision']);$sandboxConfig=array_values(array_filter($asaas->accounts($a,7),fn($r)=>$r['environment']==='sandbox'))[0];$sandboxHook=asaasFixtureRotateWebhook($asaas,$a,7,$accounts[$a],$sandboxConfig['revision']);$body=json_encode(['id'=>'evt_same_remote_production','event'=>'PAYMENT_UPDATED','payment'=>['id'=>$sameRemote]],JSON_THROW_ON_ERROR);
essentialCheck($asaas->receive($hook['public_id'],['Content-Type'=>'application/json','asaas-access-token'=>$sandboxHook['one_time_token']],$body)['status']===401,'sandbox webhook credential cannot authorize production inbox');
essentialCheck($asaas->receive($hook['public_id'],['Content-Type'=>'application/json','asaas-access-token'=>$hook['one_time_token']],$body)['status']===200,'production callback resolves its own account/environment despite repeated remoteID');
$event=$db->query("SELECT account_row FROM {$p}asaas_company_events WHERE event_id='evt_same_remote_production'")->row_array();essentialCheck((int)$event['account_row']===$productionAccount,'event ledger binds production account instead of looking up remoteID alone');
$beforeCalls=count($productionCalls);essentialThrows(fn()=>$asaas->query($b,7,$origin,$dec,$productionTransport),'asaas_company_origin_denied','other company cannot consult production source');essentialCheck(count($productionCalls)===$beforeCalls,'cross-company denial precedes production transport');
$result=(new \Asaas\CompanyRemoteOperations($db,$p))->remove($a,7,$origin,hash('sha256','asaas-production-remove'),$dec,$productionTransport);essentialCheck($result['state']==='deleted','removal confirms exact production account/environment using synthetic transport');
essentialCheck($db->query("SELECT * FROM {$p}asaas_payment_origins WHERE id=?",[$origins[$a]])->row_array()===$beforeSandbox,'production callback/query/removal leave sandbox origin with identical remoteID untouched');
essentialCheck(count(array_filter($productionCalls,fn($r)=>$r[0]!=='production'||$r[3]!=='key-A-production'))===0,'every production request retains explicit environment and that account credential');
echo 'Company Asaas explicit environments: '.($GLOBALS['essentialChecks']-$envStart).' added checks; '.$GLOBALS['essentialChecks'].' cumulative passed ('.$db->driver().'). API simulated.'.PHP_EOL;
