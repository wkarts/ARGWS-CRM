<?php
require __DIR__.'/asaas_remote_monitor.php';
$start=$GLOBALS['essentialChecks'];$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.8.9','asaas']);
$requests=[];$mode='normal';$response=['balance'=>'23.45'];
$reportTransport=function($env,$method,$path,$payload,$secret)use(&$requests,&$mode,&$response,$db,$p,$a,$account){
 essentialCheck(!$db->pdo->inTransaction()&&$env==='sandbox'&&$method==='GET'&&$payload===null,'report request outside transaction and no financial method');$requests[]=$path;
 if($path==='wallets/')return ['data'=>[['id'=>$account->account_id]],'hasMore'=>false];
 if($mode==='revoke')$db->query('DELETE FROM '.$p.'issuers_resource_permissions WHERE issuer_id=? AND staff_id=41 AND feature=?',[$a,'asaas_account_reports']);
 if($mode==='disable')$db->query('UPDATE '.$p.'asaas_company_accounts SET enabled=0 WHERE id=?',[$account->id]);
 if($mode==='paging'){
  parse_str(parse_url($path,PHP_URL_QUERY),$query);$offset=(int)$query['offset'];
  return ['data'=>[['id'=>'txn_'.$offset,'value'=>'10.01','description'=>'=FORMULA','balance'=>'23.45','creditCard'=>['token'=>'private-token']]],'totalCount'=>2,'hasMore'=>$offset===0];
 }
 return $response;
};
$before=$db->query('SELECT * FROM '.$p.'invoicepaymentrecords')->result_array();
$page=$asaas->accountReport($a,7,$account->id,$account->revision,'balance',[],false,$dec,$reportTransport)['report'];essentialCheck($page['scope']['issuer_id']===$a&&$page['rows'][0]['balance']==='23.45','master sees explicitly scoped account balance');
$n=count($requests);essentialThrows(fn()=>$asaas->accountReport($b,7,$account->id,$account->revision,'balance',[],false,$dec,$reportTransport),'asaas_company_access_denied','foreign account denied');essentialCheck(count($requests)===$n,'foreign account no HTTP');
$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,41,?,?)',[$a,'invoices','view']);
foreach(['view','export'] as $cap)$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,41,?,?)',[$a,'asaas_reports',$cap]);
essentialThrows(fn()=>$asaas->accountReport($a,41,$account->id,$account->revision,'balance',[],false,$dec,$reportTransport),'asaas_report_access_denied','ordinary reports plus global admin do not grant whole-account visibility');
$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,41,?,?)',[$a,'asaas_account_reports','view']);
$page=$asaas->accountReport($a,41,$account->id,$account->revision,'balance',[],false,$dec,$reportTransport)['report'];essentialCheck($page['rows'][0]['balance']==='23.45','explicit whole-account reader allowed');
essentialThrows(fn()=>$asaas->accountReport($a,41,$account->id,$account->revision,'balance',[],true,$dec,$reportTransport),'asaas_report_access_denied','view never grants export');
$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,41,?,?)',[$a,'asaas_account_reports','export']);
$mode='paging';$file=$asaas->accountReport($a,41,$account->id,$account->revision,'statement',[],true,$dec,$reportTransport);$bytes=stream_get_contents($file->stream());$file->close();essentialCheck($file->rows===2&&str_contains($bytes,'wallet-A')&&!str_contains($bytes,'private-token')&&str_contains($bytes,"'=FORMULA"),'complete paginated CSV has scope and formula protection, without raw token');
$mode='revoke';essentialThrows(fn()=>$asaas->accountReport($a,41,$account->id,$account->revision,'balance',[],true,$dec,$reportTransport),'asaas_report_access_denied','ACL revocation during GET discards private export');
$mode='disable';essentialThrows(fn()=>$asaas->accountReport($a,7,$account->id,$account->revision,'balance',[],false,$dec,$reportTransport),'asaas_company_stale','account disabled during query discards response');$db->query('UPDATE '.$p.'asaas_company_accounts SET enabled=1 WHERE id=?',[$account->id]);$mode='normal';
$accB=$db->query('SELECT * FROM '.$p.'asaas_company_accounts WHERE id=?',[$accounts[$b]])->row();
$transportB=function($env,$method,$path,$payload,$secret)use($accB){if($path==='wallets/')return ['data'=>[['id'=>$accB->account_id]],'hasMore'=>false];return ['balance'=>'56.78'];};
$pageB=$asaas->accountReport($b,7,$accB->id,$accB->revision,'balance',[],false,$dec,$transportB)['report'];essentialCheck($pageB['scope']['issuer_id']===$b&&$pageB['rows'][0]['balance']==='56.78','secondary company uses its own account result');
$prod=$db->query('SELECT * FROM '.$p.'asaas_company_accounts WHERE id=?',[$productionAccounts[$a]])->row();
$transportP=function($env,$method,$path,$payload,$secret)use($prod){essentialCheck($env==='production'&&$secret==='key-A-production','production credential only in simulated production transport');if($path==='wallets/')return ['data'=>[['id'=>$prod->account_id]],'hasMore'=>false];return ['balance'=>'78.90'];};
$pageP=$asaas->accountReport($a,7,$prod->id,$prod->revision,'balance',[],false,$dec,$transportP)['report'];essentialCheck($pageP['rows'][0]['balance']==='78.90'&&$pageP['scope']['environment']==='production','same wallet in different environment remains separate');
essentialCheck($db->query('SELECT * FROM '.$p.'invoicepaymentrecords')->result_array()===$before,'account reports never post native payments');
echo 'Account report isolation: '.($GLOBALS['essentialChecks']-$start).' additional checks; MySQL and provider simulated.'.PHP_EOL;
