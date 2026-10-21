<?php
/** Asaas151 hosted single card: actual final source/writer/worker/expense, simulated provider only. */
require __DIR__.'/asaas_checkout_payers.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyReconciliation.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyFeeExpense.php';
$settlementStart=$GLOBALS['essentialChecks'];$legacyAtStart=$db->query("SELECT * FROM {$p}invoices WHERE id=42")->result_array();
function asaasFreshHostedSettlement(int $issuer,string $tag,array $changes=[]):array
{
    global $asaas,$s,$input,$ca,$customerB,$accounts,$registered,$dec,$checkoutTransport,$responses,$db,$p;
    $invoice=$s->create($issuer,7,array_replace($input,['client_id'=>$issuer===$GLOBALS['a']?$ca:$customerB,'date'=>'2026-10-01']),hash('sha256','card-settle-invoice-'.$tag));
    $origin=asaasFixtureHosted($asaas,$issuer,7,$invoice,$accounts[$issuer],$registered[$issuer]['customer_id'],$s->revision($issuer,7,$invoice),hash('sha256','card-settle-origin-'.$tag),$dec,$checkoutTransport);
    $remote=$db->query("SELECT remote_id FROM {$p}asaas_payment_origins WHERE id=?",[$origin])->row()->remote_id;
    $responses[$remote]=array_replace($responses[$remote],['status'=>'RECEIVED','paymentDate'=>'2026-10-18','creditDate'=>'2026-10-19','netValue'=>'104.53','anticipated'=>false,'deleted'=>false],$changes);
    $proof=$asaas->query($issuer,7,$origin,$dec,$checkoutTransport);return [$invoice,$origin,$proof,$remote];
}
// The shape and canonical evidence of PIX/BOLETO149 remain byte-for-byte compatible.
foreach($origins as $issuer=>$origin){
    $proof=$db->query("SELECT result_json FROM {$p}asaas_company_proofs WHERE origin_id=? ORDER BY id DESC LIMIT 1",[$origin])->row_array();$safe=json_decode($proof['result_json'],true);
    essentialCheck(!array_key_exists('card_settlement',$safe),'149 PIX proof adds no card policy fields');
    $receipt=$db->query("SELECT * FROM {$p}asaas_company_receipts WHERE origin_id=?",[$origin])->row_array();
    if($receipt){$meta=$db->query("SELECT snapshot_hash FROM {$p}asaas_company_origin_meta WHERE origin_id=?",[$origin])->row_array();$old=hash('sha256',json_encode([$meta['snapshot_hash'],$safe['value'],$safe['net_value'],$safe['fee'],$safe['payment_date'],$safe['billing_type'],$safe['id']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));essentialCheck($receipt['evidence_hash']===$old,'PIX receipt retains 149 canonical evidence formula');}
}
$fees=new \Asaas\CompanyFeeExpense($db,$p);$expenseService=new CompanyExpenses($db,$p);$cardCategories=[];
foreach([$a,$b] as $issuer){
    [$invoice,$origin,$proof,$remote]=asaasFreshHostedSettlement($issuer,'positive-'.$issuer);
    essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($issuer===$a?$b:$a,7,new \Asaas\CompanyReceivedReceipt($origin,$proof)),'asaas_company_origin_denied','A/B cannot adopt another company card settlement');
    $payment=$writer->recordCompanyProviderReceipt($issuer,7,new \Asaas\CompanyReceivedReceipt($origin,$proof));$row=$db->query("SELECT * FROM {$p}invoicepaymentrecords WHERE id=?",[$payment])->row_array();
    essentialCheck((int)$row['invoiceid']===$invoice&&$row['date']==='2026-10-19'&&\app\services\InvoicePaymentWriter::minorUnits($row['amount'])===10553,'A/B records gross nominal at actual creditDate, not earlier paymentDate or net');
    essentialCheck($writer->recordCompanyProviderReceipt($issuer,7,new \Asaas\CompanyReceivedReceipt($origin,$proof))===$payment,'card settlement retry retains canonical key and payment');
    $proofAgain=$asaas->query($issuer,7,$origin,$dec,$checkoutTransport);essentialCheck($writer->recordCompanyProviderReceipt($issuer,7,new \Asaas\CompanyReceivedReceipt($origin,$proofAgain))===$payment,'fresh same-account proof converges to original card receipt');
    $category=$expenseService->saveCategory($issuer,7,'Tarifa cartão avulso',hash('sha256','single-card-fee-'.$issuer));$cardCategories[$issuer]=$category;$expense=$fees->post($issuer,7,$origin,$proofAgain,$category);$cost=$expenseService->get($issuer,7,$expense);
    essentialCheck($cost['expense_date']==='2026-10-19'&&(int)$cost['total_cents']===100&&$fees->post($issuer,7,$origin,$proofAgain,$category)===$expense,'card fee uses explicit native expense at creditDate and retries once');
    $safe=json_decode($db->query("SELECT result_json FROM {$p}asaas_company_proofs WHERE id=?",[$proofAgain])->row()->result_json,true);essentialCheck($safe['card_settlement']===['profile'=>'hosted_single_v1','single_charge'=>true,'unanticipated'=>true,'not_deleted'=>true,'unencumbered'=>true],'safe card proof contains only bounded settlement profile facts');
}
$cases=[
 'confirmed'=>['status'=>'CONFIRMED'], 'authorized'=>['status'=>'AUTHORIZED'],
 'missing-credit'=>['creditDate'=>null], 'empty-credit'=>['creditDate'=>''],
 'estimated-only'=>['creditDate'=>'','estimatedCreditDate'=>'2026-10-19'],
 'future-credit'=>['creditDate'=>'2099-12-31'], 'before-payment'=>['creditDate'=>'2026-10-17'],
 'impossible-credit'=>['creditDate'=>'2026-02-30'], 'future-payment'=>['paymentDate'=>'2099-12-31'],
 'missing-payment'=>['paymentDate'=>null], 'before-invoice'=>['paymentDate'=>'2026-09-30','creditDate'=>'2026-10-19'],
 'installment'=>['installment'=>'inst_123'], 'subscription'=>['subscription'=>'sub_123'],
 'installment-number'=>['installmentNumber'=>1], 'installment-number-false'=>['installmentNumber'=>false],
 'installment-count'=>['installmentCount'=>1], 'installment-value'=>['installmentValue'=>'105.53'],
 'total-value'=>['totalValue'=>'105.53'], 'anticipation'=>['anticipated'=>true],
 'anticipation-unknown'=>['anticipated'=>null], 'anticipation-string'=>['anticipated'=>'false'],
 'escrow'=>['escrow'=>['status'=>'ACTIVE']], 'custody'=>['custody'=>['status'=>'ACTIVE']],
 'gross-net-conflict'=>['netValue'=>'105.54'], 'gross-mismatch'=>['value'=>'105.54']];
foreach($cases as $tag=>$changes){
    $before=(int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords")->row()->n;
    try{[$invoice,$origin,$proof]=asaasFreshHostedSettlement($b,$tag,$changes);$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyReceivedReceipt($origin,$proof));throw new LogicException('invalid card proof accepted: '.$tag);}
    catch(DomainException $error){essentialCheck(in_array($error->getMessage(),['asaas_company_receipt_requires_review','asaas_company_card_settlement_requires_review','asaas_company_response_conflict'],true),'invalid card settlement profile/date/status/value blocked: '.$tag);}
    essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords")->row()->n===$before,'rejected profile leaves no nominal receipt: '.$tag);
}
// Missing old card profile cannot be grandfathered into a financial claim; a fresh GET is needed.
[$invoice,$origin,$proof,$remote]=asaasFreshHostedSettlement($b,'pre-hardening-proof');$stored=$db->query("SELECT result_json FROM {$p}asaas_company_proofs WHERE id=?",[$proof])->row()->result_json;$safe=json_decode($stored,true);unset($safe['card_settlement']);$json=json_encode($safe,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$db->query("UPDATE {$p}asaas_company_proofs SET result_json=?,evidence_hash=? WHERE id=?",[$json,hash('sha256',json_encode($safe,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)),$proof]);
essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyReceivedReceipt($origin,$proof)),'asaas_company_card_settlement_requires_review','unversioned old card proof requires fresh canonical observation');
$proof=$asaas->query($b,7,$origin,$dec,$checkoutTransport);essentialCheck($writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyReceivedReceipt($origin,$proof))>0,'fresh GET supplies required bounded card policy facts');
// A received callback is only an inbox entry; a canonical CONFIRMED response cannot post.
$worker=new \Asaas\CompanyReconciliation($db,$p);$config=$asaas->accounts($b,7)[0];if(!$config['webhook_configured'])asaasFixtureRotateWebhook($asaas,$b,7,$accounts[$b],$config['revision']);$config=$asaas->accounts($b,7)[0];$asaas->configureAutomation($b,7,$accounts[$b],$config['revision'],true);$worker->runOnce($dec,$checkoutTransport);
foreach(['confirmed','received'] as $mode){
    [$invoice,$origin,$proof,$remote]=asaasFreshHostedSettlement($b,'async-'.$mode,['status'=>$mode==='confirmed'?'CONFIRMED':'RECEIVED']);$config=$asaas->accounts($b,7)[0];$hook=asaasFixtureRotateWebhook($asaas,$b,7,$accounts[$b],$config['revision']);$headers=['Content-Type'=>'application/json','asaas-access-token'=>$hook['one_time_token']];$body=json_encode(['id'=>'evt_single_card_'.$mode,'event'=>'PAYMENT_RECEIVED','payment'=>['id'=>$remote,'status'=>'RECEIVED','creditDate'=>'2026-10-19']],JSON_THROW_ON_ERROR);
    essentialCheck($asaas->receive($hook['public_id'],$headers,$body)['status']===200,'received card callback persists authenticated origin-bound inbox');essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$invoice])->row()->n===0,'card callback alone cannot write nominal money');
    $result=$worker->runOnce($dec,$checkoutTransport);$job=$db->query("SELECT j.* FROM {$p}asaas_company_jobs j JOIN {$p}asaas_company_events e ON e.id=j.event_id WHERE e.event_id=?",['evt_single_card_'.$mode])->row_array();
    essentialCheck($job['state']===($mode==='confirmed'?'review_required':'completed'),'worker uses canonical status/profile rather than webhook claim');$count=(int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$invoice])->row()->n;essentialCheck($count===($mode==='confirmed'?0:1),'worker writes nominal card receipt only for actual canonical RECEIVED');
    essentialCheck($asaas->receive($hook['public_id'],$headers,$body)['duplicate']===true&&$worker->runOnce($dec,$checkoutTransport)['completed']===0,'callback duplicate never adds a second card job/payment');
}
[$invoice,$origin,$proof,$remote]=asaasFreshHostedSettlement($b,'rotation');$config=$asaas->accounts($b,7)[0];$asaas->saveAccount($b,7,'sandbox','wallet-B','rotation-card-key-B',$config['revision'],$enc);
essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyReceivedReceipt($origin,$proof)),'asaas_company_account_unverified','credential rotation invalidates a previously valid card proof before money');
$asaas->verifyAccount($b,7,$accounts[$b],$asaas->accounts($b,7)[0]['revision'],$dec,$checkoutTransport);$proof=$asaas->query($b,7,$origin,$dec,$checkoutTransport);$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");
essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyReceivedReceipt($origin,$proof)),'asaas_company_access_denied','actor revocation is rechecked inside canonical financial write');$db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");
essentialCheck($db->query("SELECT * FROM {$p}invoices WHERE id=42")->result_array()===$legacyAtStart,'card policy preserves legacy NULL-owner invoice bytes');
echo 'Company Asaas151 canonical card settlement: '.($GLOBALS['essentialChecks']-$settlementStart).' added checks; '.$GLOBALS['essentialChecks'].' cumulative passed ('.$db->driver().'). Provider simulated; no external homologation.'.PHP_EOL;
