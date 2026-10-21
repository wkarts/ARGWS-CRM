<?php
/** Native company Asaas services + real writer, disposable PDO. All remote traffic is simulated. */
require __DIR__.'/invoices.php';
if(!defined('FCPATH'))define('FCPATH',dirname(__DIR__,2).'/');
require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyService.php';
require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyReceivedReceipt.php';
require_once __DIR__.'/asaas_token_fixture.php';
$start=$GLOBALS['essentialChecks'];
$db->query("INSERT INTO {$p}modules(module_name,installed_version,active) VALUES('asaas','1.5.0',1)");
if(!$db->table_exists($p.'asaas_payment_origins')){
    $db->query("CREATE TABLE {$p}asaas_payment_origins(id ".($db->driver()==='sqlite'?'INTEGER PRIMARY KEY AUTOINCREMENT':'INT NOT NULL AUTO_INCREMENT PRIMARY KEY').",invoice_id INT NOT NULL UNIQUE,invoice_hash VARCHAR(64) NOT NULL,invoice_total DECIMAL(15,2) NOT NULL,invoice_due_date DATE NULL,issuer_id INT NULL,provider VARCHAR(32) NOT NULL,account_id VARCHAR(128) NOT NULL,environment VARCHAR(16) NOT NULL,channel VARCHAR(16) NOT NULL,remote_id VARCHAR(64) NULL,batch_id VARCHAR(128) NULL,state VARCHAR(32) NOT NULL,last_code VARCHAR(64) NOT NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,UNIQUE(provider,account_id,environment,remote_id))".($db->driver()==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin':''));
}
require_once APPPATH.'services/CompanyExpenseSchema.php';
if($db->driver()==='mysql')CompanyExpenseSchema::ensure($db,$p);
else foreach(CompanyExpenseSchema::definitions() as $table=>$fields){$parts=[];foreach($fields as $name=>$type)$parts[]='`'.$name.'` '.(str_contains($type,'AUTO_INCREMENT')?'INTEGER PRIMARY KEY AUTOINCREMENT':$type.' NOT NULL');if($table==='company_expense_requests')$parts[]='PRIMARY KEY(request_key)';$db->query('CREATE TABLE '.$p.$table.'('.implode(',',$parts).')');}
if(!$db->table_exists($p.'company_projects'))$db->query('CREATE TABLE '.$p.'company_projects(id int unsigned PRIMARY KEY,issuer_id int unsigned,name varchar(191),client_id int,status varchar(20))');
\Asaas\CompanySchema::ensure($db,$p);\Asaas\CompanySchema::ensure($db,$p);\Asaas\CompanyRemoteOperationsSchema::ensure($db,$p);unset($db->data_cache['table_names']);
(new CompanyCurrencyUnits($db,$p))->captureObservedUsed();
// Exercise the native cipher against an ephemeral key, never application credentials.
require_once BASEPATH.'libraries/Encryption.php';$nativeWebhookCipher=new CI_Encryption(['driver'=>'openssl','key'=>random_bytes(32)]);
if(!$db->table_exists($p.'asaas_webhook_integrations'))$db->query('CREATE TABLE '.$p.'asaas_webhook_integrations(id int PRIMARY KEY)');
\Asaas\WebhookTokenSchema::ensure($db,$p);\Asaas\WebhookTokenSchema::ensure($db,$p);
$webhookCipher=new \Asaas\WebhookTokenCipher(fn($v)=>$nativeWebhookCipher->encrypt($v),fn($v)=>$nativeWebhookCipher->decrypt($v));
$asaas=new \Asaas\CompanyService($db,$p,$webhookCipher);$enc=fn($value)=>'synthetic:'.base64_encode($value);$dec=fn($value)=>str_starts_with($value,'synthetic:')?base64_decode(substr($value,10),true):false;
$zero=str_repeat('0',64);$calls=[];$responses=[];$customerTax=['cus_a'=>'11144477735','cus_b'=>'12345678909'];
$mock=function($environment,$method,$path,$payload,$secret)use(&$calls,&$responses,&$customerTax){
    $calls[]=[$environment,$method,$path,$payload,$secret];
    $letter=str_contains($secret,'key-A')?'A':'B';
    if($path==='wallets/')return ['data'=>[['id'=>'wallet-'.$letter]],'hasMore'=>false];
    if(str_starts_with($path,'customers/')){$id=substr($path,10);return ['id'=>$id,'cpfCnpj'=>$customerTax[$id]??'52998224725','deleted'=>false];}
    if($method==='POST'){$id='pay_'.count($responses);$responses[$id]=$payload+['id'=>$id,'status'=>'PENDING','netValue'=>$payload['value'],'paymentDate'=>'','creditDate'=>'','deleted'=>false];return $responses[$id];}
    return $responses[substr($path,9)]??[];
};
$accounts=[];
foreach([$a=>'A',$b=>'B'] as $issuer=>$letter){
    $idAccount=$asaas->saveAccount($issuer,7,'sandbox','wallet-'.$letter,'key-'.$letter,$zero,$enc);$accounts[$issuer]=$idAccount;
    $dto=$asaas->accounts($issuer,7)[0];essentialCheck(!array_key_exists('secret_cipher',$dto)&&!array_key_exists('token_hash',$dto),'account DTO has no encrypted or hashed secrets');
    essentialCheck($dto['verified_at']===null,'saving credentials never invents bank verification');
    $asaas->verifyAccount($issuer,7,$idAccount,$dto['revision'],$dec,$mock);
    essentialCheck($asaas->accounts($issuer,7)[0]['verified_at']!==null,'actual service verifies wallet through simulated canonical GET');
}
$beforeCalls=count($calls);essentialThrows(fn()=>$asaas->verifyAccount($b,7,$accounts[$a],$asaas->accounts($a,7)[0]['revision'],$dec,$mock),'asaas_company_access_denied','cross-company account ID denied before HTTP');essentialCheck(count($calls)===$beforeCalls,'cross-account failure makes no provider request');
$dtoA=$asaas->accounts($a,7)[0];essentialThrows(fn()=>$asaas->saveAccount($a,7,'sandbox','other-wallet','key-A',$dtoA['revision'],$enc),'asaas_company_account_immutable','an account with fixed wallet is never silently replaced');
$before=$db->query("SELECT * FROM {$p}asaas_company_accounts ORDER BY id")->result_array();$db->failOn='INSERT INTO '.$p.'issuers_audit';
essentialThrows(fn()=>$asaas->saveAccount($a,7,'sandbox','wallet-A','rotated-key-A',$dtoA['revision'],$enc),'issuer_storage_failed','account audit failure rolls credential write back');$db->failOn=null;
essentialCheck($db->query("SELECT * FROM {$p}asaas_company_accounts ORDER BY id")->result_array()===$before,'no partial credential or revision after audit failure');
$customerB=$customers->create($b,7,['company'=>'Asaas private B','vat'=>'12345678909']);
$invoiceA=$s->create($a,7,$input,hash('sha256','asaas-invoice-A'));$invoiceB=$s->create($b,7,array_replace($input,['client_id'=>$customerB]),hash('sha256','asaas-invoice-B'));
$origins=[];$proofs=[];$remote=[];
foreach([$a=>[$invoiceA,'cus_a'],$b=>[$invoiceB,'cus_b']] as $issuer=>[$invoice,$customer]){
    $key=hash('sha256','asaas-origin-'.$issuer);$revision=$s->revision($issuer,7,$invoice);
    $origin=$asaas->issue($issuer,7,$invoice,$accounts[$issuer],$customer,'PIX',$revision,$key,$dec,$mock);$origins[$issuer]=$origin;
    $oldCount=count(array_filter($calls,fn($r)=>$r[1]==='POST'));
    essentialCheck($asaas->issue($issuer,7,$invoice,$accounts[$issuer],$customer,'PIX',$revision,$key,$dec,$mock)===$origin,'same issuance submission returns reserved origin');
    essentialCheck(count(array_filter($calls,fn($r)=>$r[1]==='POST'))===$oldCount,'retry does not repeat remote POST');
    $record=$db->query("SELECT * FROM {$p}asaas_payment_origins WHERE id=?",[$origin])->row_array();$remote[$issuer]=$record['remote_id'];
    essentialCheck((int)$record['issuer_id']===$issuer&&$record['account_id']==='wallet-'.($issuer===$a?'A':'B')&&$record['environment']==='sandbox','origin stores immutable company, account and environment');
    essentialThrows(fn()=>(new \app\services\InvoicePaymentWriter($db))->recordCompanyManual($issuer,7,$invoice,['amount'=>'1.00','date'=>'2026-10-19','reference'=>'forged-manual'],hash('sha256','manual-asaas-'.$issuer)),'company_payment_provider_review_required','provider origin blocks manual receipt path');
    $responses[$record['remote_id']]['status']='RECEIVED';$responses[$record['remote_id']]['paymentDate']='2026-10-19';$responses[$record['remote_id']]['creditDate']='2026-10-19';$responses[$record['remote_id']]['netValue']='104.53';
    $proofs[$issuer]=$asaas->query($issuer,7,$origin,$dec,$mock);
    $ov=$asaas->overview($issuer,7);essentialCheck(count($ov['origins'])===1&&$ov['origins'][0]['proof']['fee']==='1.00'&&$ov['origins'][0]['proof']['fee_posting_status']==='not_posted','canonical proof keeps gross/net/fee and distinguishes unposted tariff');
    essentialCheck(count($ov['accounts'])===1&&$ov['accounts'][0]['account_id']==='wallet-'.($issuer===$a?'A':'B'),'overview filters company before hydration');
}
$beforeCalls=count($calls);essentialThrows(fn()=>$asaas->query($b,7,$origins[$a],$dec,$mock),'asaas_company_origin_denied','cross-company query denied before provider access');essentialCheck(count($calls)===$beforeCalls,'cross-origin read does not call API');
$source=new \Asaas\CompanyReceivedReceipt($origins[$a],$proofs[$a]);
$writer=new \app\services\InvoicePaymentWriter($db);
$paymentA=$writer->recordCompanyProviderReceipt($a,7,$source);
essentialCheck($writer->recordCompanyProviderReceipt($a,7,new \Asaas\CompanyReceivedReceipt($origins[$a],$proofs[$a]))===$paymentA,'financial retry is canonical and idempotent');
$paymentB=$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyReceivedReceipt($origins[$b],$proofs[$b]));
essentialCheck($paymentA!==$paymentB&&$db->query("SELECT amount FROM {$p}invoicepaymentrecords WHERE id=?",[$paymentA])->row()->amount==105.53,'positive A/B settlements go through one native writer at nominal gross amount');
essentialCheck($db->query("SELECT * FROM {$p}invoices WHERE id=42")->result_array()===$legacyBefore,'named-company settlement preserves authorized legacy NULL owner invoice');
// Receive canonical inbox events without a financial write, then test rotation/replay/conflict/adverse order.
$historicalTokenVersion=$db->query('SELECT installed_version FROM '.$p.'modules WHERE module_name=?',['asaas'])->row()->installed_version;
$dto=$asaas->accounts($a,7)[0];$hook=asaasFixtureRotateWebhook($asaas,$a,7,$accounts[$a],$dto['revision']);$dto=$asaas->accounts($a,7)[0];
essentialCheck($db->query('SELECT installed_version FROM '.$p.'modules WHERE module_name=?',['asaas'])->row()->installed_version===$historicalTokenVersion,'synthetic token preparation restores historical financial tracking');
$headers=['Content-Type'=>'application/json','asaas-access-token'=>$hook['one_time_token']];$payload=['id'=>'evt-company-A','event'=>'PAYMENT_RECEIVED','payment'=>['id'=>$remote[$a],'creditCardToken'=>'must-not-persist']];$body=json_encode($payload);$paymentsBefore=(int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords")->row()->n;
essentialCheck($asaas->receive($hook['public_id'],$headers,$body)['status']===200,'origin-specific webhook is authenticated and persisted');
essentialCheck($asaas->receive($hook['public_id'],$headers,$body)['duplicate']===true,'same callback event is idempotent');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords")->row()->n===$paymentsBefore,'callback does not imply financial settlement');
essentialCheck(!str_contains(json_encode($db->query("SELECT * FROM {$p}asaas_company_events")->result_array()),'must-not-persist'),'webhook storage never keeps card/customer raw payload');
$changed=$payload;$changed['payment']['creditCardToken']='changed';essentialCheck($asaas->receive($hook['public_id'],$headers,json_encode($changed))['status']===409,'same event identity with differing payload becomes durable conflict');
$rotated=asaasFixtureRotateWebhook($asaas,$a,7,$accounts[$a],$dto['revision']);essentialCheck($asaas->receive($hook['public_id'],$headers,$body)['status']===401,'rotating token invalidates its predecessor');
$headers['asaas-access-token']=$rotated['one_time_token'];$payload=['id'=>'evt-adverse-A','event'=>'PAYMENT_REFUNDED','payment'=>['id'=>$remote[$a]]];essentialCheck($asaas->receive($hook['public_id'],$headers,json_encode($payload))['status']===200,'authenticated adverse event persists for review');
$nextProof=$asaas->query($a,7,$origins[$a],$dec,$mock);essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($a,7,new \Asaas\CompanyReceivedReceipt($origins[$a],$nextProof)),'asaas_company_receipt_requires_review','later RECEIVED cannot clear earlier adverse history');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords")->row()->n===$paymentsBefore,'adverse event never performs an uncontracted financial reversal');
// Timeout reserves once and recovery validates all immutable fields before binding the remote ID.
$invoiceTimeout=$s->create($b,7,array_replace($input,['client_id'=>$customerB]),hash('sha256','asaas-timeout-invoice'));
$timeoutCalls=0;$uncertainPayload=null;$timeoutMock=function($environment,$method,$path,$payload,$secret)use($mock,&$timeoutCalls,&$uncertainPayload){if($method==='POST'){$timeoutCalls++;$uncertainPayload=$payload;throw new RuntimeException('synthetic ambiguous transport');}return $mock($environment,$method,$path,$payload,$secret);};
$timeoutKey=hash('sha256','asaas-timeout-origin');$revision=$s->revision($b,7,$invoiceTimeout);
essentialThrows(fn()=>$asaas->issue($b,7,$invoiceTimeout,$accounts[$b],'cus_b','BOLETO',$revision,$timeoutKey,$dec,$timeoutMock),'asaas_company_post_unconfirmed','ambiguous remote POST is not reported as failure to create or safe to retry');
$timeoutOrigin=(int)$db->query("SELECT id FROM {$p}asaas_payment_origins WHERE invoice_id=?",[$invoiceTimeout])->row()->id;
essentialCheck($asaas->issue($b,7,$invoiceTimeout,$accounts[$b],'cus_b','BOLETO',$revision,$timeoutKey,$dec,$timeoutMock)===$timeoutOrigin&&$timeoutCalls===1,'uncertain retry returns reservation without a second POST');
$responses['pay_recovered']=$uncertainPayload+['id'=>'pay_recovered','status'=>'PENDING','netValue'=>$uncertainPayload['value'],'paymentDate'=>'','creditDate'=>'','deleted'=>false];
$asaas->query($b,7,$timeoutOrigin,$dec,$mock,'pay_recovered');essentialCheck($db->query("SELECT remote_id FROM {$p}asaas_payment_origins WHERE id=?",[$timeoutOrigin])->row()->remote_id==='pay_recovered','canonical GET safely binds an uncertain response to the reserved origin');

function asaasFreshCredit(int $issuer,int $client,string $customer,string $tag,string $status='RECEIVED'):array
{
    global $s,$input,$asaas,$accounts,$dec,$mock,$responses;
    $invoice=$s->create($issuer,7,array_replace($input,['client_id'=>$client]),hash('sha256','asaas-fresh-invoice-'.$tag));
    $origin=$asaas->issue($issuer,7,$invoice,$accounts[$issuer],$customer,'PIX',$s->revision($issuer,7,$invoice),hash('sha256','asaas-fresh-origin-'.$tag),$dec,$mock);
    $row=$GLOBALS['db']->query('SELECT remote_id FROM '.$GLOBALS['p'].'asaas_payment_origins WHERE id=?',[$origin])->row_array();$remote=$row['remote_id'];
    $responses[$remote]['status']=$status;$responses[$remote]['paymentDate']='2026-10-19';$responses[$remote]['creditDate']='2026-10-19';
    $proof=$asaas->query($issuer,7,$origin,$dec,$mock);return [$invoice,$origin,$proof,$remote];
}
[$failureInvoice,$failureOrigin,$failureProof,$failureRemote]=asaasFreshCredit($b,$customerB,'cus_b','audit-failure');
$beforePayments=(int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords")->row()->n;
$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyReceivedReceipt($failureOrigin,$failureProof)),'issuer_storage_failed','late financial audit failure rolls back native payment, status and provider receipt');$db->failOn=null;
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords")->row()->n===$beforePayments&&!$db->query("SELECT origin_id FROM {$p}asaas_company_receipts WHERE origin_id=?",[$failureOrigin])->row(),'late failure leaves no orphan payment or provider receipt');
$beforeApi=count($calls);$failurePayment=$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyReceivedReceipt($failureOrigin,$failureProof));essentialCheck(count($calls)===$beforeApi,'financial source performs no provider HTTP inside the writer transaction');
$newEquivalent=$asaas->query($b,7,$failureOrigin,$dec,$mock);essentialCheck($writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyReceivedReceipt($failureOrigin,$newEquivalent))===$failurePayment,'later equivalent query uses the same canonical financial receipt');
[$confirmedInvoice,$confirmedOrigin,$confirmedProof]=asaasFreshCredit($b,$customerB,'cus_b','confirmed-only','CONFIRMED');
essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyReceivedReceipt($confirmedOrigin,$confirmedProof)),'asaas_company_receipt_requires_review','CONFIRMED does not mean available received funds');
[$staleInvoice,$staleOrigin,$staleProof]=asaasFreshCredit($b,$customerB,'cus_b','stale-proof');$db->query("UPDATE {$p}asaas_company_proofs SET created_at='2000-01-01 00:00:00' WHERE id=?",[$staleProof]);
essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyReceivedReceipt($staleOrigin,$staleProof)),'asaas_company_stale','first application requires a fresh account-proven query');
[$conflictInvoice,$conflictOrigin,$conflictProof,$conflictRemote]=asaasFreshCredit($b,$customerB,'cus_b','nominal-conflict');$responses[$conflictRemote]['value']='1.00';
essentialThrows(fn()=>$asaas->query($b,7,$conflictOrigin,$dec,$mock),'asaas_company_response_conflict','canonical amount mismatch is explicit');
essentialCheck($db->query("SELECT state FROM {$p}asaas_payment_origins WHERE id=?",[$conflictOrigin])->row()->state==='review_required','a divergent later query commits durable review before reporting conflict');
essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyReceivedReceipt($conflictOrigin,$conflictProof)),'asaas_company_receipt_requires_review','older valid proof cannot bypass a later canonical conflict');
[$wrongInvoice,$wrongOrigin,$wrongProof]=asaasFreshCredit($b,$customerB,'cus_b','wallet-conflict');$walletWrong=function($environment,$method,$path,$payload,$secret)use($mock){if($path==='wallets/')return ['data'=>[['id'=>'wallet-other']],'hasMore'=>false];return $mock($environment,$method,$path,$payload,$secret);};
essentialThrows(fn()=>$asaas->query($b,7,$wrongOrigin,$dec,$walletWrong),'asaas_company_account_unverified','wrong wallet is rejected under the exact persisted account');
essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyReceivedReceipt($wrongOrigin,$wrongProof)),'asaas_company_receipt_requires_review','failed account revalidation invalidates previously eligible receipt state');
$view=json_encode($asaas->overview($b,7),JSON_THROW_ON_ERROR);essentialCheck(!str_contains($view,'key-B')&&!str_contains($view,'synthetic:')&&!str_contains($view,'token_hash'),'overview exports only nonsensitive configuration and canonical metadata');
$db->query("UPDATE {$p}modules SET active=0 WHERE module_name='asaas'");essentialThrows(fn()=>$asaas->overview($b,7),'asaas_company_module_required','deactivated module cannot operate named-company adapter');$db->query("UPDATE {$p}modules SET active=1 WHERE module_name='asaas'");
$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");essentialThrows(fn()=>$asaas->accounts($b,7),'asaas_company_access_denied','staff revocation takes effect before account DTO hydration');$db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");
echo 'Company Asaas positive origin/receipt/inbox: '.($GLOBALS['essentialChecks']-$start).' added checks, '.$GLOBALS['essentialChecks'].' cumulative checks passed ('.$db->driver().'). Remote API simulated.'.PHP_EOL;
