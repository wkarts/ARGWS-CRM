<?php
/** The reference gateway's global-account restriction must not replace ARGWS ownership. */
require __DIR__.'/asaas_legacy_binding.php';
$start=$GLOBALS['essentialChecks'];
$principal=(new IssuerResourceAccess($db,$p))->primaryId();if($principal===null)throw new RuntimeException('Principal fixture required');
$installation=$db->query('SELECT * FROM '.$p.'issuers_installation_identity')->result_array();
$otherAccounts=$db->query('SELECT * FROM '.$p.'asaas_company_accounts ORDER BY id')->result_array();
$settings->saveIdentity($principal,7,['legal_name'=>'Synthetic principal legacy company','tax_id'=>'93541134780','profile'=>['invoice_company_address'=>'Principal street','invoice_company_city'=>'Principal city','company_state'=>'BA','invoice_company_country_code'=>'BR','invoice_company_postal_code'=>'11111-111','invoice_company_phonenumber'=>'']]);
$principalClient=$customers->create($principal,7,['company'=>'Synthetic principal payer','vat'=>'39053344705']);
$principalInvoice=$s->create($principal,7,array_replace($input,['client_id'=>$principalClient]),hash('sha256','legacy-primary-invoice'));
$principalResponse=array_replace($originalResponse,['id'=>'pay_primary_legacy','externalReference'=>$db->query('SELECT hash FROM '.$p.'invoices WHERE id=?',[$principalInvoice])->row()->hash,'customer'=>'cus_primary_legacy']);
$primaryCalls=0;
$primaryTransport=function($environment,$method,$path,$payload,$secret)use(&$primaryCalls,&$principalResponse){
 $primaryCalls++;if($environment!=='sandbox'||$secret!=='key-primary-legacy'||$method!=='GET'||$payload!==null)throw new RuntimeException('Unexpected primary transport identity');
 if($path==='wallets/')return ['data'=>[['id'=>'wallet-primary-legacy']],'hasMore'=>false];
 if($path==='customers/cus_primary_legacy')return ['id'=>'cus_primary_legacy','cpfCnpj'=>'39053344705','deleted'=>false];
 if(str_starts_with($path,'payments?externalReference='))return ['data'=>[$principalResponse],'hasMore'=>false,'totalCount'=>1];
 if($path==='payments/pay_primary_legacy')return $principalResponse;
 throw new RuntimeException('Unexpected primary read');
};
$principalAccount=$asaas->saveAccount($principal,7,'sandbox','wallet-primary-legacy','key-primary-legacy',str_repeat('0',64),$enc);
$accountPage=$asaas->accounts($principal,7)[0];$asaas->verifyAccount($principal,7,$principalAccount,$accountPage['revision'],$dec,$primaryTransport);
$review=$asaas->reviewLegacyBinding($principal,7,$principalInvoice,$principalAccount,'pay_primary_legacy',$dec,$primaryTransport);
essentialCheck($review['account_id']==='wallet-primary-legacy'&&$review['issuer_id']===$principal,'primary company discovers through its own distinct account');
$before=$primaryCalls;
essentialThrows(fn()=>$asaas->reviewLegacyBinding($b,7,$principalInvoice,$accounts[$b],'pay_primary_legacy',$dec,$primaryTransport),'asaas_company_invoice_denied','secondary company cannot discover a primary invoice');
essentialThrows(fn()=>$asaas->reviewLegacyBinding($principal,7,$principalInvoice,$accounts[$b],'pay_primary_legacy',$dec,$primaryTransport),'asaas_company_access_denied','primary company cannot borrow secondary credentials');
essentialCheck($primaryCalls===$before,'cross-company failures happen before provider reads');
$bound=$asaas->reviewLegacyBinding($principal,7,$principalInvoice,$principalAccount,'pay_primary_legacy',$dec,$primaryTransport,$review['review_hash']);
$receipt=$batch->item($principal,7,$bound['origin_id'],true,$dec,$primaryTransport);
essentialCheck($receipt['payment_id']>0&&$s->position($principal,7,$principalInvoice)['received']==='40.00','primary explicit partial receipt uses native writer and its own scope');
essentialCheck($db->query('SELECT * FROM '.$p.'asaas_company_accounts WHERE id<>? ORDER BY id',[$principalAccount])->result_array()===$otherAccounts,'principal workflow preserves both secondary accounts byte for byte');
essentialCheck($db->query('SELECT * FROM '.$p.'issuers_installation_identity')->result_array()===$installation,'primary workflow does not replace installation identity');
echo 'Primary/secondary legacy scope: '.($GLOBALS['essentialChecks']-$start).' additional MySQL checks; separate synthetic wallet, no external requests.'.PHP_EOL;
