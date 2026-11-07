<?php
require __DIR__.'/asaas_remote_monitor.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyPaymentReport.php';
$start=$GLOBALS['essentialChecks'];require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyInvoiceEditSchema.php';\Asaas\CompanyInvoiceEditSchema::ensure($db,$p);\Asaas\CompanyOriginPolicy::ensure($db,$p,true);$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.8.5','asaas']);
$report=new \Asaas\CompanyPaymentReport($db,$p);$requests=[];$mode='valid';$reads=0;
$reportTransport=function($env,$method,$path,$payload,$secret)use(&$requests,&$mode,&$reads,$account,$db,$p){
 essentialCheck(!$db->pdo->inTransaction(),'report HTTP is outside SQL transaction');
 essentialCheck($method==='GET'&&$payload===null&&$env==='sandbox','report cannot mutate remote financial state');$requests[]=$path;
 if($path==='wallets/')return ['data'=>[['id'=>$account->account_id]],'hasMore'=>false];
 parse_str(parse_url($path,PHP_URL_QUERY),$query);$offset=(int)$query['offset'];$reads++;
 if($mode==='revoke')$db->query('UPDATE '.$p.'asaas_company_accounts SET enabled=0 WHERE id=?',[$account->id]);
 $rows=[];for($i=$offset;$i<min(51,$offset+50);$i++)$rows[]=['id'=>'pay_report_'.($mode==='repeat'&&$offset>0?0:$i),'value'=>'1.01','status'=>'RECEIVED','billingType'=>'PIX','customer'=>'private-other-field','creditCard'=>['creditCardToken'=>'never-export']];
 return ['data'=>$rows,'hasMore'=>$offset===0,'totalCount'=>$mode==='changed'&&$offset>0?52:51];
};
$result=$report->export($a,7,$account->id,$account->revision,['offset'=>50],$dec,$reportTransport);
essentialCheck($result['rows']===51&&$reads===2,'export restarts at zero and includes all validated pages');
essentialCheck(str_starts_with($result['bytes'],"\xEF\xBB\xBF")&&substr_count($result['bytes'],"\n")===52,'CSV includes BOM and header plus every row');
essentialCheck(str_contains($result['bytes'],'sandbox')&&str_contains($result['filename'],'-'.$a.'-'.$account->id.'-sandbox'),'download carries company account and environment');
essentialCheck(!str_contains($result['bytes'],'private-other-field')&&!str_contains($result['bytes'],'never-export')&&!str_contains($result['bytes'],'secret_cipher'),'only minimum reviewed fields reach CSV');
essentialCheck(\Asaas\CompanyPaymentReport::csvText(" \t=HYPERLINK(1)")==="' \t=HYPERLINK(1)"&&\Asaas\CompanyPaymentReport::csvText('1,01')==='1,01','CSV formula neutralization preserves ordinary amount');
$n=count($requests);essentialThrows(fn()=>$report->export($b,7,$account->id,$account->revision,[],$dec,$reportTransport),'asaas_company_access_denied','cross-company account blocked before network');essentialCheck(count($requests)===$n,'cross-company export has zero requests');
$mode='repeat';essentialThrows(fn()=>$report->export($a,7,$account->id,$account->revision,[],$dec,$reportTransport),'asaas_report_repeated_page','duplicate across pages aborts entire export');
$mode='changed';essentialThrows(fn()=>$report->export($a,7,$account->id,$account->revision,[],$dec,$reportTransport),'asaas_monitor_response_invalid','inconsistent last page rejected without partial download');
$mode='revoke';essentialThrows(fn()=>$report->export($a,7,$account->id,$account->revision,[],$dec,$reportTransport),'asaas_company_stale','revocation during HTTP discards file');$db->query('UPDATE '.$p.'asaas_company_accounts SET enabled=1 WHERE id=?',[$account->id]);
$time=0;$slow=new \Asaas\CompanyPaymentReport($db,$p,function()use(&$time){$time+=46;return $time;});$n=count($requests);
essentialThrows(fn()=>$slow->export($a,7,$account->id,$account->revision,[],$dec,$reportTransport),'asaas_report_limit','deadline stops complete export before HTTP');essentialCheck(count($requests)===$n,'expired export performs no HTTP');

$mode='valid';$n=count($requests);
essentialThrows(fn()=>$report->export($a,99999,$account->id,$account->revision,[],$dec,$reportTransport),'asaas_company_access_denied','unprivileged actor cannot export');essentialCheck(count($requests)===$n,'denied actor makes zero HTTP calls');
$wrongWallet=function($env,$method,$path,$payload,$secret){essentialCheck($path==='wallets/','wrong wallet stops before payment request');return ['data'=>[['id'=>'wallet-foreign']], 'hasMore'=>false];};
essentialThrows(fn()=>$report->export($a,7,$account->id,$account->revision,[],$dec,$wrongWallet),'asaas_company_account_unverified','credential for another account is rejected');
essentialThrows(fn()=>$asaas->saveAccount($b,7,'sandbox',$account->account_id,'synthetic-key',str_repeat('0',64),$enc),'asaas_company_stale','existing secondary account cannot be replaced with shared account using forged revision');
$accountB=$db->query('SELECT id,account_id,revision FROM '.$p.'asaas_company_accounts WHERE id=?',[$accounts[$b]])->row();
$transportB=function($env,$method,$path,$payload,$secret)use($accountB,$remote){
 if($path==='wallets/')return ['data'=>[['id'=>$accountB->account_id]],'hasMore'=>false];
 return ['data'=>[['id'=>$remote,'value'=>'2.02','status'=>'PENDING','billingType'=>'PIX']],'hasMore'=>false,'totalCount'=>1];
};
$other=$report->export($b,7,$accountB->id,$accountB->revision,[],$dec,$transportB);
essentialCheck($other['scope']['issuer_id']===$b&&str_contains($other['bytes'],'2,02')&&!str_contains($other['bytes'],'1,01'),'same remote ID in secondary account keeps separate amount and scope');
$csv=explode("\n",trim($other['bytes']));$line=str_getcsv($csv[1],';','"','');
$expectedB=$db->query('SELECT o.id,o.invoice_id FROM '.$p.'asaas_payment_origins o JOIN '.$p.'asaas_company_origin_meta m ON m.origin_id=o.id WHERE o.issuer_id=? AND m.account_row=? AND o.remote_id=?',[$b,$accountB->id,$remote])->row_array();essentialCheck($line[10]===(string)($expectedB['id']??'')&&$line[11]===(string)($expectedB['invoice_id']??''),'coincident remote ID resolves only secondary company origin when present');
$render=function(bool $can_configure)use($account,$a){
 $accounts=[['id'=>(int)$account->id,'enabled'=>1,'verified_at'=>'2026-01-01','account_id'=>$account->account_id,'environment'=>'sandbox','revision'=>$account->revision]];
 $remote_monitor=['account_row'=>(int)$account->id,'account_revision'=>$account->revision,'filters'=>['status'=>'all'],'page'=>['items'=>[],'next_offset'=>null]];
 $preferences_csrf=str_repeat('a',64);$action_url='/admin/settings/asaas';$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');$environmentLabel=fn($v)=>$v;
 $hidden=function($data)use($e){foreach($data as $k=>$v)echo '<input type="hidden" name="'.$e($k).'" value="'.$e($v).'">';};
 ob_start();include APPPATH.'views/admin/settings/native_asaas_remote_monitor.php';return ob_get_clean();
};
essentialCheck(str_contains($render(true),'export_payment_report')&&!str_contains($render(false),'export_payment_report'),'native view only offers export to settings editor');
echo 'Company payment report: '.($GLOBALS['essentialChecks']-$start).' additional checks; provider simulated, CSV not a remote snapshot.'.PHP_EOL;
