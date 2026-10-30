<?php
/** Actual service/native writer against disposable PDO; provider transport is synthetic only. */
require __DIR__.'/asaas_positive.php';
require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyReconciliation.php';
\Asaas\CompanyOriginPolicy::ensure($db,$p,true);\Asaas\CompanyOriginPolicy::ensure($db,$p,true);$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.5.7','asaas']);
$tag=hash('sha256','bridge-origin-policy-fixture');$invoice=$s->create($b,7,array_replace($input,['client_id'=>$customerB]),hash('sha256','bridge-invoice-fixture'));$revision=$s->revision($b,7,$invoice);$key=hash('sha256','bridge-issue-fixture');$guardCalls=0;
$guard=function()use(&$guardCalls,$db,$p){IssuerTransaction::assertOwned($db,$p,['asaas_company_origin_policy']);$guardCalls++;};
$before=count($calls);essentialThrows(fn()=>$asaas->issue($b,7,$invoice,$accounts[$b],'cus_b','PIX',$revision,$key,$dec,$mock,null,null,[],true,$tag),'asaas_origin_guard_required','manual policy cannot be selected without trusted callback');essentialCheck(count($calls)===$before,'missing guard refuses before transport');
$deny=function(){throw new DomainException('synthetic_origin_revoked');};essentialThrows(fn()=>$asaas->issue($b,7,$invoice,$accounts[$b],'cus_b','PIX',$revision,$key,$dec,$mock,null,$deny,[],true,$tag),'synthetic_origin_revoked','guard veto denies before HTTP');essentialCheck(count($calls)===$before,'guard denies without provider calls');
$origin=$asaas->issue($b,7,$invoice,$accounts[$b],'cus_b','PIX',$revision,$key,$dec,$mock,null,$guard,[],true,$tag);
essentialCheck($guardCalls>=10,'guard checks every transaction boundary including reserve/pre-send/proof');
essentialCheck(\Asaas\CompanyOriginPolicy::isManual($db,$p,$origin),'manual policy persisted atomically with origin');
$before=count($calls);essentialCheck($asaas->issue($b,7,$invoice,$accounts[$b],'cus_b','PIX',$revision,$key,$dec,$mock,null,$guard,[],true,$tag)===$origin&&count($calls)===$before,'same immutable origin replays without POST');
essentialThrows(fn()=>$asaas->issue($b,7,$invoice,$accounts[$b],'cus_b','PIX',$revision,$key,$dec,$mock,null,$guard,[],true,str_repeat('c',64)),'asaas_origin_policy_conflict','different bridge reference cannot adopt old origin');
$remote=$db->query('SELECT remote_id FROM '.$p.'asaas_payment_origins WHERE id=?',[$origin])->row()->remote_id;$responses[$remote]['status']='RECEIVED';$responses[$remote]['paymentDate']=$input['date'];$responses[$remote]['creditDate']=$input['date'];
$payments=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords')->row()->n;$jobs=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'asaas_company_jobs')->row()->n;
$proof=$asaas->observe($b,7,$origin,$dec,$mock,'',$guard);essentialCheck($proof>0&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'asaas_company_jobs')->row()->n===$jobs,'explicit observation never enqueues');
essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyReceivedReceipt($origin,$proof)),'asaas_origin_manual_reconciliation_required','default canonical receipt cannot turn observation into payment');
$db->query('UPDATE '.$p.'asaas_company_accounts SET automation_actor=7 WHERE id=?',[$accounts[$b]]);
$event='manual_policy_fixture_event';$db->query('INSERT INTO '.$p.'asaas_company_events(account_row,event_id,event_type,remote_id,payload_hash,state,created_at) VALUES(?,?,?,?,?,?,?)',[$accounts[$b],$event,'PAYMENT_RECEIVED',$remote,str_repeat('b',64),'observed',gmdate('Y-m-d H:i:s')]);$eventId=(int)$db->insert_id();
IssuerTransaction::run($db,$p,array_merge(\Asaas\CompanyService::tables(),\Asaas\CompanyOriginPolicy::tables($db,$p)),fn()=>$asaas->enqueueObservedJobsInOwnedTransaction());
essentialCheck(!$db->query('SELECT event_id FROM '.$p.'asaas_company_jobs WHERE event_id=?',[$eventId])->row(),'later account automation cannot enqueue protected origin');
$now=gmdate('Y-m-d H:i:s');$db->query('INSERT INTO '.$p.'asaas_company_jobs(event_id,issuer_id,origin_id,actor_id,state,attempts,lease_token,lease_until,next_attempt_at,last_code,created_at,updated_at) VALUES(?,?,?,?,?,0,NULL,NULL,?,?,?,?)',[$eventId,$b,$origin,7,'pending',$now,'synthetic-existing-job',$now,$now]);
$method=new ReflectionMethod(\Asaas\CompanyReconciliation::class,'policy');essentialThrows(fn()=>$method->invoke(new \Asaas\CompanyReconciliation($db,$p),['issuer_id'=>$b,'actor_id'=>7,'origin_id'=>$origin]),'asaas_origin_manual_reconciliation_required','already queued worker job denied before provider query');
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords')->row()->n===$payments,'observation and denied worker leave all payments unchanged');
echo "Asaas157 manual origin policy and trusted guard fixture; no real HTTP.\n";
