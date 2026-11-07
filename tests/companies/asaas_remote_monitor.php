<?php
require __DIR__.'/asaas_preferences.php';
$start=$GLOBALS['essentialChecks'];$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.7.5','asaas']);
$account=$db->query('SELECT id,account_id,revision FROM '.$p.'asaas_company_accounts WHERE id=?',[$accounts[$a]])->row();
$remote=$db->query('SELECT remote_id FROM '.$p.'asaas_payment_origins WHERE issuer_id=? ORDER BY id LIMIT 1',[$a])->row()->remote_id;
$list=['data'=>[['id'=>$remote,'value'=>'100.00','status'=>'RECEIVED','billingType'=>'BOLETO','customer'=>'private-customer','description'=>'private-description','creditCard'=>['creditCardToken'=>'synthetic-secret']]],'hasMore'=>false,'totalCount'=>1];
$requests=[];$during=null;
$transport=function($env,$method,$path,$payload,$secret)use(&$requests,&$during,&$list,$account,$db){
 essentialCheck(!$db->pdo->inTransaction(),'monitor network is outside SQL transaction');
 $requests[]=[$method,$path];essentialCheck($env==='sandbox'&&$method==='GET'&&$payload===null,'monitor performs only bodyless GET in selected environment');
 if($path==='wallets/')return ['data'=>[['id'=>$account->account_id]],'hasMore'=>false];
 if($during)$during();
 return $list;
};
$result=$asaas->remotePayments($a,7,$account->id,$account->revision,[],$dec,$transport);
essentialCheck(count($result['items'])===1&&$result['items'][0]['origin_id']!==null&&$result['items'][0]['value']==='100.00','remote list returns permitted fields and exact existing origin');
essentialCheck(!str_contains(json_encode($result),'private-')&&!str_contains(json_encode($result),'synthetic-secret'),'raw provider extras never reach monitor DTO');
essentialCheck($requests===[['GET','wallets/'],['GET','payments?limit=50&offset=0']],'single wallet proof and bounded list call');
$n=count($requests);essentialThrows(fn()=>$asaas->remotePayments($b,7,$account->id,$account->revision,[],$dec,$transport),'asaas_company_access_denied','foreign account denied before network');essentialCheck(count($requests)===$n,'foreign account made no network request');
essentialCheck(\Asaas\PaymentListQuery::allowed(\Asaas\PaymentListQuery::path(['offset'=>'50','status'=>'RECEIVED','date_field'=>'due','date_from'=>'2026-01-01'])),'canonical dated pagination accepted');
foreach(['payments?limit=100&offset=0','payments?limit=50&offset=0&customer=foreign','payments?limit=50&offset=0&offset=1','payments?limit=50&offset=0&dueDate%5Bge%5D=2026-02-30'] as $path)essentialCheck(!\Asaas\PaymentListQuery::allowed($path),'noncanonical or unsupported list path rejected');
$list['data'][]=$list['data'][0];$list['totalCount']=2;
essentialThrows(fn()=>$asaas->remotePayments($a,7,$account->id,$account->revision,[],$dec,$transport),'asaas_monitor_response_invalid','duplicate provider IDs rejected');
$list['data']=[$list['data'][0]];$list['totalCount']=1;
$during=function()use($db,$p,$account){$db->query('UPDATE '.$p.'asaas_company_accounts SET enabled=0 WHERE id=?',[$account->id]);};
essentialThrows(fn()=>$asaas->remotePayments($a,7,$account->id,$account->revision,[],$dec,$transport),'asaas_company_stale','account disabled during GET discards response');
$db->query('UPDATE '.$p.'asaas_company_accounts SET enabled=1 WHERE id=?',[$account->id]);$during=null;
echo 'Remote monitor: '.($GLOBALS['essentialChecks']-$start).' additional checks ('.$db->driver().'), all remote responses simulated.'.PHP_EOL;
