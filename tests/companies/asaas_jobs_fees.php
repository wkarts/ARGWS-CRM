<?php
/** Actual canonical jobs and expense writer; simulated provider and no external money movement. */
require __DIR__.'/asaas_positive.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyReconciliation.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyFeeExpense.php';
$jobsStart=$GLOBALS['essentialChecks'];$worker=new \Asaas\CompanyReconciliation($db,$p);$fees=new \Asaas\CompanyFeeExpense($db,$p);$expenseService=new CompanyExpenses($db,$p);
$categoryA=$expenseService->saveCategory($a,7,'Tarifas Asaas',hash('sha256','asaas-tariff-A'));$categoryB=$expenseService->saveCategory($b,7,'Tarifas Asaas',hash('sha256','asaas-tariff-B'));
// Both owners receive a separately confirmed, opt-in automation policy.
foreach([$a,$b] as $issuer){$config=$asaas->accounts($issuer,7)[0];if(!$config['webhook_configured'])$asaas->rotateWebhook($issuer,7,$accounts[$issuer],$config['revision']);$config=$asaas->accounts($issuer,7)[0];$asaas->configureAutomation($issuer,7,$accounts[$issuer],$config['revision'],true);}
// Existing A adverse event from base fixture is deliberately reviewed, never reapplied.
$worker->runOnce($dec,$mock);
function asaasAsyncEvent(int $issuer,int $client,string $customer,string $tag):array
{
    global $asaas,$accounts,$db,$p,$responses;
    [$invoice,$origin,$proof,$remote]=asaasFreshCredit($issuer,$client,$customer,$tag);
    $responses[$remote]['netValue']='104.53';
    $config=$asaas->accounts($issuer,7)[0];$hook=$asaas->rotateWebhook($issuer,7,$accounts[$issuer],$config['revision']);
    $event='evt_async_'.str_replace('-','_',$tag);$headers=['Content-Type'=>'application/json','asaas-access-token'=>$hook['one_time_token']];
    $body=json_encode(['id'=>$event,'event'=>'PAYMENT_RECEIVED','payment'=>['id'=>$remote]],JSON_THROW_ON_ERROR);
    essentialCheck($asaas->receive($hook['public_id'],$headers,$body)['status']===200,'authenticated received event and persisted-origin job commit together');
    $job=$db->query("SELECT j.* FROM {$p}asaas_company_jobs j JOIN {$p}asaas_company_events e ON e.id=j.event_id WHERE e.event_id=?",[$event])->row_array();
    essentialCheck((int)$job['issuer_id']===$issuer&&(int)$job['origin_id']===$origin&&(int)$job['actor_id']===7,'job stores exact company, origin and authorized actor');
    return [$invoice,$origin,(int)$job['event_id'],$remote,$headers,$hook];
}
$normal=[];
foreach([$a=>[$ca,'cus_a',$categoryA],$b=>[$customerB,'cus_b',$categoryB]] as $issuer=>[$client,$customer,$category]){
    // A base fixture contains adverse events; new title still has independent canonical identity.
    [$invoice,$origin,$job,$remote]=asaasAsyncEvent($issuer,$client,$customer,'positive-'.$issuer);$normal[$issuer]=[$invoice,$origin,$job,$remote];
    $result=$worker->runOnce($dec,$mock);essentialCheck($result['completed']===1&&$db->query("SELECT state FROM {$p}asaas_company_jobs WHERE event_id=?",[$job])->row()->state==='completed','background worker performs fresh canonical query and receipt through one writer');
    essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$invoice])->row()->n===1,'one nominal payment exists after asynchronous completion');
    essentialCheck($worker->runOnce($dec,$mock)['completed']===0,'completed job is not dispatched again');
    $proof=(int)$db->query("SELECT MAX(id) id FROM {$p}asaas_company_proofs WHERE origin_id=?",[$origin])->row()->id;
    $posted=$fees->post($issuer,7,$origin,$proof,$category);
    essentialCheck($fees->post($issuer,7,$origin,$proof,$category)===$posted,'same canonical tariff links one expense on retry');
    $expense=$expenseService->get($issuer,7,$posted);
    essentialCheck((int)$expense['amount_cents']===100&&(int)$expense['tax_cents']===0&&(int)$expense['total_cents']===100&&$expense['currency']==='BRL','native expense records exact proven tariff without tax or nominal-payment modification');
    essentialThrows(fn()=>$expenseService->change($issuer,7,$posted,$expense['revision_token'],hash('sha256','forged-fee-change-'.$issuer),null),'company_expense_provider_source_immutable','linked provider expense cannot be silently canceled or edited');
    $ownOverview=$asaas->overview($issuer,7);$ownRow=array_values(array_filter($ownOverview['origins'],fn($r)=>(int)$r['id']===$origin))[0];essentialCheck($ownRow['proof']['fee_posting_status']==='posted'&&$ownRow['proof']['fee_expense_id']===$posted,'UI metadata distinguishes a genuinely posted expense from observed tariff');
}
[$retryInvoice,$retryOrigin,$retryJob,$retryRemote]=asaasAsyncEvent($b,$customerB,'cus_b','transport-retry');
$failTransport=fn()=>throw new RuntimeException('synthetic secret-bearing transport details');$failed=$worker->runOnce($dec,$failTransport);
essentialCheck($failed['retry']===1&&$db->query("SELECT state FROM {$p}asaas_company_jobs WHERE event_id=?",[$retryJob])->row()->state==='pending','temporary provider failure preserves retryable origin-bound job');
$beforeCalls=count($calls);essentialCheck($worker->runOnce($dec,$mock)['completed']===0&&count($calls)===$beforeCalls,'backoff avoids immediate repeated API requests');
$db->query("UPDATE {$p}asaas_company_jobs SET next_attempt_at='2000-01-01 00:00:00' WHERE event_id=?",[$retryJob]);essentialCheck($worker->runOnce($dec,$mock)['completed']===1,'scheduled retry recovers without creating a second remote charge');
// Lost process after receipt, before job acknowledgment: expired lease resumes idempotently.
$beforePayments=(int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$retryInvoice])->row()->n;
$db->query("UPDATE {$p}asaas_company_jobs SET state='processing',lease_until='2000-01-01 00:00:00',lease_token=? WHERE event_id=?",[str_repeat('a',64),$retryJob]);
essentialCheck($worker->runOnce($dec,$mock)['completed']===1&&(int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$retryInvoice])->row()->n===$beforePayments,'expired lease after committed receipt recovers one canonical payment');
[$adverseInvoice,$adverseOrigin,$adverseJob,$adverseRemote,$adverseHeaders,$adverseHook]=asaasAsyncEvent($b,$customerB,'cus_b','adverse-before-worker');
$asaas->receive($adverseHook['public_id'],$adverseHeaders,json_encode(['id'=>'evt_async_adverse','event'=>'PAYMENT_REFUNDED','payment'=>['id'=>$adverseRemote]],JSON_THROW_ON_ERROR));
essentialCheck($worker->runOnce($dec,$mock)['review']===1&&(int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$adverseInvoice])->row()->n===0,'adverse event before dispatch keeps queued reconciliation in review without receipt');
[$revokeInvoice,$revokeOrigin,$revokeJob]=asaasAsyncEvent($b,$customerB,'cus_b','revocation-before-worker');$beforeCalls=count($calls);$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");
essentialCheck($worker->runOnce($dec,$mock)['review']===1&&count($calls)===$beforeCalls,'revoked persisted actor is denied before provider or financial effects');$db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");
[$policyInvoice,$policyOrigin,$policyJob]=asaasAsyncEvent($b,$customerB,'cus_b','disabled-policy');$config=$asaas->accounts($b,7)[0];$asaas->configureAutomation($b,7,$accounts[$b],$config['revision'],false);$beforeCalls=count($calls);
essentialCheck($worker->runOnce($dec,$mock)['review']===1&&count($calls)===$beforeCalls,'disabling account automation revokes a pending job before network');
// Posting failure after native expense creation must roll back the complete composite transaction.
$retryProof=$asaas->query($b,7,$retryOrigin,$dec,$mock);
$beforeExpenses=(int)$db->query("SELECT COUNT(*) n FROM {$p}company_expenses")->row()->n;$db->failOn='INSERT INTO '.$p.'asaas_company_fee_expenses';
essentialThrows(fn()=>$fees->post($b,7,$retryOrigin,$retryProof,$categoryB),'issuer_storage_failed','late fee-link failure propagates after native expense writer');$db->failOn=null;
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}company_expenses")->row()->n===$beforeExpenses&&!$db->query("SELECT origin_id FROM {$p}asaas_company_fee_expenses WHERE origin_id=?",[$retryOrigin])->row(),'failed link leaves neither expense nor false fee receipt');
$postedRetry=$fees->post($b,7,$retryOrigin,$retryProof,$categoryB);essentialCheck($postedRetry>0,'same operation can resume safely after full rollback');
essentialThrows(fn()=>$fees->post($a,7,$retryOrigin,$retryProof,$categoryA),'asaas_company_origin_denied','cross-company tariff cannot create or adopt another company expense');
// The writer itself must reject a previously claimed lease after the policy is disabled.
[$leaseInvoice,$leaseOrigin,$leaseProof,$leaseRemote]=asaasFreshCredit($b,$customerB,'cus_b','writer-lease-policy');
$db->query("INSERT INTO {$p}asaas_company_events(account_row,event_id,event_type,remote_id,payload_hash,state,created_at) VALUES(?,'evt_writer_policy_claim','PAYMENT_RECEIVED',?,?,'observed',?)",[$accounts[$b],$leaseRemote,hash('sha256','synthetic-event'),gmdate('Y-m-d H:i:s')]);$eventRow=(int)$db->insert_id();
$db->query("INSERT INTO {$p}asaas_company_jobs(event_id,issuer_id,origin_id,actor_id,state,attempts,lease_token,lease_until,next_attempt_at,last_code,created_at,updated_at) VALUES(?,?,?,7,'processing',1,?,?,?,'synthetic_claim',?,?)",[$eventRow,$b,$leaseOrigin,str_repeat('c',64),gmdate('Y-m-d H:i:s',time()+300),gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]);
essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyReceivedReceipt($leaseOrigin,$leaseProof,(int)$eventRow,str_repeat('c',64))),'asaas_company_worker_lease_lost','source revalidates disabled account policy inside the core receipt transaction');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$leaseInvoice])->row()->n===0,'policy revocation cannot slip through a gap before the financial writer');
echo 'Company Asaas async/fee expense: '.($GLOBALS['essentialChecks']-$jobsStart).' added checks; '.$GLOBALS['essentialChecks'].' cumulative passed ('.$db->driver().'). No real provider calls.'.PHP_EOL;
