<?php
/** Provider origin reads keep immutable ISO; new remote issuance vetoes corrupted mutable catalog. */
require __DIR__.'/asaas_positive.php';
$unitsStart=$GLOBALS['essentialChecks'];
[$currencyInvoice,$currencyOrigin,$currencyProof,$currencyRemote]=asaasFreshCredit($b,$customerB,'cus_b','currency-read','PENDING');
$meta=$db->query("SELECT snapshot_json FROM {$p}asaas_company_origin_meta WHERE origin_id=?",[$currencyOrigin])->row()->snapshot_json;
$keys=['id','issuer_id','clientid','hash','date','duedate','total','currency','currency_name','legal_identity_json','recipient_identity_json','formatted_number'];
$snapshot155=json_decode($meta,true,32,JSON_THROW_ON_ERROR);essentialCheck(array_keys($snapshot155)===array_merge($keys,['asaas_profile_contract'])&&$snapshot155['asaas_profile_contract']==='company_profile_v1','new155 origin preserves ordered149 invoice fields and appends its explicit immutable profile contract');
$nextInvoice=$s->create($b,7,array_replace($input,['client_id'=>$customerB]),hash('sha256','asaas-currency-new-invoice'));$nextRevision=$s->revision($b,7,$nextInvoice);
$db->query("UPDATE {$p}currencies SET name='USD' WHERE id=1");$observed=$asaas->query($b,7,$currencyOrigin,$dec,$mock);
essentialCheck($observed>$currencyProof&&$db->query("SELECT snapshot_json FROM {$p}asaas_company_origin_meta WHERE origin_id=?",[$currencyOrigin])->row()->snapshot_json===$meta,'historical origin query resolves capturedBRL and leaves149 snapshot unchanged after catalog corruption');
$beforeCalls=count($calls);essentialThrows(fn()=>asaasFixtureIssue($asaas,$b,7,$nextInvoice,$accounts[$b],'cus_b','PIX',$nextRevision,hash('sha256','currency-new-origin'),$dec,$mock),'company_currency_catalog_changed','new remote origin refuses altered catalog meaning');essentialCheck(count($calls)===$beforeCalls,'catalog mismatch denied before wallet/customer/provider HTTP');
$db->query("UPDATE {$p}currencies SET name='BRL' WHERE id=1");
$nextInvoice=$s->create($b,7,array_replace($input,['client_id'=>$customerB]),hash('sha256','asaas-currency-race-invoice'));$nextRevision=$s->revision($b,7,$nextInvoice);$beforePost=count(array_filter($calls,fn($x)=>$x[1]==='POST'));
$changeDuringGet=function($environment,$method,$path,$payload,$secret)use($mock,$db,$p){$response=$mock($environment,$method,$path,$payload,$secret);if($path==='wallets/')$db->query("UPDATE {$p}currencies SET name='USD' WHERE id=1");return $response;};
essentialThrows(fn()=>asaasFixtureIssue($asaas,$b,7,$nextInvoice,$accounts[$b],'cus_b','PIX',$nextRevision,hash('sha256','currency-race-origin'),$dec,$changeDuringGet),'company_currency_catalog_changed','catalog is revalidated under reservation lock after external account checks');
essentialCheck(count(array_filter($calls,fn($x)=>$x[1]==='POST'))===$beforePost&&!$db->query("SELECT id FROM {$p}asaas_payment_origins WHERE invoice_id=?",[$nextInvoice])->row(),'late catalog change leaves no remote POST or reserved origin');$db->query("UPDATE {$p}currencies SET name='BRL' WHERE id=1");
$unit=$db->query("SELECT source_hash FROM {$p}company_currency_units WHERE currency_id=1")->row()->source_hash;$db->query("UPDATE {$p}company_currency_units SET source_hash=? WHERE currency_id=1",[str_repeat('0',64)]);$beforeCalls=count($calls);
essentialThrows(fn()=>$asaas->query($b,7,$currencyOrigin,$dec,$mock),'company_currency_snapshot_invalid','invalid immutable ISO evidence is rejected before provider access');essentialCheck(count($calls)===$beforeCalls,'invalid unit proof causes no network');
essentialThrows(fn()=>$asaas->query($a,7,$currencyOrigin,$dec,$mock),'asaas_company_origin_denied','cross-company owner boundary precedes immutable currency hydration');$db->query("UPDATE {$p}company_currency_units SET source_hash=? WHERE currency_id=1",[$unit]);
essentialCheck($db->query("SELECT * FROM {$p}invoices WHERE id=42")->result_array()===$legacyBefore,'currency guard leaves NULL-owner legacy invoice unchanged');
echo 'Company Asaas immutable currency: '.($GLOBALS['essentialChecks']-$unitsStart).' added checks; '.$GLOBALS['essentialChecks'].' cumulative passed ('.$db->driver().'). Provider simulated.'.PHP_EOL;
