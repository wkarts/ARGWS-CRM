<?php
require __DIR__.'/asaas_checkout_payers.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyCardPayments.php';
\Asaas\CompanyOriginPolicy::ensure($db,$p,true);\Asaas\CompanyCardPaymentsSchema::ensure($db,$p);unset($db->data_cache['table_names']);
$db->query("UPDATE {$p}modules SET installed_version='1.6.2' WHERE module_name='asaas'");
$card=new \Asaas\CompanyCardPayments($db,$p);
$cardCalls=[];$cardMode='success';$cardTransport=static function($environment,$method,$path,$payload,$secret)use(&$cardCalls,$checkoutTransport,&$responses,&$cardMode){
 if($method==='POST'&&str_ends_with($path,'/payWithCreditCard')){$cardCalls[]=[$environment,$path,array_keys($payload)];if($cardMode==='timeout')throw new RuntimeException('synthetic timeout');$id=explode('/',$path)[1];$responses[$id]['status']='CONFIRMED';return ['id'=>$id,'status'=>'CONFIRMED'];}
 return $checkoutTransport($environment,$method,$path,$payload,$secret);
};

$invoice=$s->create($a,7,$input,hash('sha256','direct-token-invoice'));
$origin=asaasFixtureHosted($asaas,$a,7,$invoice,$accounts[$a],$registered[$a]['customer_id'],$s->revision($a,7,$invoice),hash('sha256','direct-token-origin'),$dec,$checkoutTransport);
$key=hash('sha256','direct-token-command');$token=['creditCardToken'=>'synthetic-token-not-stored'];
$paid=$card->pay($a,7,$origin,'payWithCreditCard',$token,$key,$dec,$cardTransport);
essentialCheck($paid['state']==='observed'&&count($cardCalls)===1,'one direct API token payment observed through canonical GET');
essentialCheck($card->pay($a,7,$origin,'payWithCreditCard',$token,$key,$dec,$cardTransport)===$paid&&count($cardCalls)===1,'retry never repeats nonidempotent POST');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$invoice])->row()->n===0,'CONFIRMED never writes received funds');
essentialThrows(fn()=>$card->pay($b,7,$origin,'payWithCreditCard',$token,$key,$dec,$cardTransport),'asaas_company_origin_denied','cross company payment denied');
essentialThrows(fn()=>$card->pay($a,7,$origin,'payWithCreditCard',$token,hash('sha256','other-attempt'),$dec,$cardTransport),'asaas_card_attempt_requires_review','new key cannot bypass dispatch fence');
essentialCheck(!str_contains(json_encode($db->query("SELECT * FROM {$p}asaas_company_card_payments")->result_array()),$token['creditCardToken']),'persistent attempt stores no plaintext token');
$invoice2=$s->create($a,7,$input,hash('sha256','direct-token-timeout-invoice'));
$origin2=asaasFixtureHosted($asaas,$a,7,$invoice2,$accounts[$a],$registered[$a]['customer_id'],$s->revision($a,7,$invoice2),hash('sha256','direct-token-timeout-origin'),$dec,$checkoutTransport);
$cardMode='timeout';$key2=hash('sha256','direct-token-timeout-command');
$unknown=$card->pay($a,7,$origin2,'payWithCreditCard',$token,$key2,$dec,$cardTransport);
essentialCheck($unknown['state']==='uncertain','timeout remains unknown');
$card->pay($a,7,$origin2,'payWithCreditCard',$token,$key2,$dec,$cardTransport);
essentialCheck(count($cardCalls)===2,'unknown retry does not pay again');
essentialCheck($card->refresh($a,7,$origin2,$dec,$cardTransport)['state']==='review_required','pending canonical GET does not claim payment or permit automatic resend');
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyRemoteOperations.php';
$remove=new \Asaas\CompanyRemoteOperations($db,$p);
essentialThrows(fn()=>$remove->remove($a,7,$origin2,hash('sha256','delete-during-uncertain-card'),$dec,$cardTransport),'asaas_card_attempt_requires_review','uncertain payment prevents racing remote deletion');
essentialThrows(fn()=>$writer->recordCompanyManual($a,7,$invoice2,['amount'=>'1.00','date'=>'2026-11-06','reference'=>'manual-after-card'],hash('sha256','manual-after-card')),'company_payment_provider_review_required','manual receipt cannot race active provider origin');
$invoice3=$s->create($a,7,$input,hash('sha256','direct-token-revoked-invoice'));
$origin3=asaasFixtureHosted($asaas,$a,7,$invoice3,$accounts[$a],$registered[$a]['customer_id'],$s->revision($a,7,$invoice3),hash('sha256','direct-token-revoked-origin'),$dec,$checkoutTransport);
$key3=hash('sha256','direct-token-revoked-command');$cardMode='success';
$revokingTransport=static function($env,$method,$path,$payload,$secret)use($cardTransport,$db,$p){$result=$cardTransport($env,$method,$path,$payload,$secret);if($method==='POST')$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");return $result;};
essentialThrows(fn()=>$card->pay($a,7,$origin3,'payWithCreditCard',$token,$key3,$dec,$revokingTransport),'asaas_company_access_denied','revocation in flight blocks subsequent authenticated query');
essentialCheck($db->query("SELECT state FROM {$p}asaas_company_card_payments WHERE origin_id=?",[$origin3])->row()->state==='awaiting_query','technical HTTP observation survives revoked permission');
$db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");$beforeRetry=count($cardCalls);
essentialCheck($card->refresh($a,7,$origin3,$dec,$cardTransport)['state']==='observed'&&count($cardCalls)===$beforeRetry,'authorized recovery queries without resending card payment');
$db->trans_begin();essentialThrows(fn()=>\Asaas\CompanyCardPaymentsSchema::ensure($db,$p),'asaas_card_schema_outer_transaction_denied','card migration refuses implicit commit in caller transaction');$db->trans_rollback();
echo 'Direct card token payments: '.$GLOBALS['essentialChecks'].' cumulative checks; simulated provider only.'.PHP_EOL;
