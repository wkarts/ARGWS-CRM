<?php
/** Actual canonical writer + manual policy; all remote setup uses synthetic transport. */
require __DIR__.'/asaas_origin_policy.php';
$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.5.8','asaas']);
$beforePayments=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords')->row()->n;
$beforeCalls=count($calls);$guardCount=0;
$explicitGuard=function()use(&$guardCount,$db,$p){IssuerTransaction::assertOwned($db,$p,['asaas_company_origin_policy']);$guardCount++;};
essentialThrows(fn()=>$asaas->reconcileExplicit($b,7,$origin,$proof,str_repeat('f',64),$explicitGuard),'asaas_origin_policy_conflict','wrong bridge origin reference cannot reconcile');
$lateCalls=0;$lateGuard=function()use(&$lateCalls){if(++$lateCalls===4)throw new DomainException('synthetic_late_guard_denied');};
essentialThrows(fn()=>$asaas->reconcileExplicit($b,7,$origin,$proof,$tag,$lateGuard),'synthetic_late_guard_denied','late guard rejection rolls back native payment and Asaas receipt');
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords')->row()->n===$beforePayments&&!$db->query('SELECT origin_id FROM '.$p.'asaas_company_receipts WHERE origin_id=?',[$origin])->row(),'no orphan payment/provider receipt after late veto');
$payment=$asaas->reconcileExplicit($b,7,$origin,$proof,$tag,$explicitGuard);
essentialCheck($payment>0&&$asaas->reconcileExplicit($b,7,$origin,$proof,$tag,$explicitGuard)===$payment,'same explicit proof retry returns canonical original payment');
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords')->row()->n===$beforePayments+1&&count($calls)===$beforeCalls,'explicit reconciliation writes exactly once and makes zero HTTP calls');
essentialCheck($guardCount>=8&&\Asaas\CompanyOriginPolicy::isManual($db,$p,$origin),'guard runs before/after and persistent manual policy survives reconciliation');
essentialThrows(fn()=>$asaas->assertAutomaticAllowed($origin),'asaas_origin_manual_reconciliation_required','cron remains denied after explicit receipt');
essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyReceivedReceipt($origin,$proof)),'asaas_origin_manual_reconciliation_required','default receipt path stays closed');
// Revoked identity denies even idempotent retries; callback cannot grant permission.
$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=7');essentialThrows(fn()=>$asaas->reconcileExplicit($b,7,$origin,$proof,$tag,$explicitGuard),'asaas_company_access_denied','actor revocation precedes replay');$db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=7');
// A different manual origin exercises stale/nonintegral/adverse states without changing the paid origin.
$freshInvoice=$s->create($b,7,array_replace($input,['client_id'=>$customerB]),hash('sha256','explicit-negative-invoice'));$freshTag=hash('sha256','explicit-negative-reference');
$freshOrigin=$asaas->issue($b,7,$freshInvoice,$accounts[$b],'cus_b','PIX',$s->revision($b,7,$freshInvoice),hash('sha256','explicit-negative-issue'),$dec,$mock,null,$explicitGuard,[],true,$freshTag);
$freshRemote=$db->query('SELECT remote_id FROM '.$p.'asaas_payment_origins WHERE id=?',[$freshOrigin])->row()->remote_id;
$pendingProof=$asaas->observe($b,7,$freshOrigin,$dec,$mock,'',$explicitGuard);
essentialThrows(fn()=>$asaas->reconcileExplicit($b,7,$freshOrigin,$pendingProof,$freshTag,$explicitGuard),'asaas_company_receipt_requires_review','PENDING is not received');
$responses[$freshRemote]['status']='RECEIVED';$responses[$freshRemote]['paymentDate']=$input['date'];$responses[$freshRemote]['creditDate']=$input['date'];
$freshProof=$asaas->observe($b,7,$freshOrigin,$dec,$mock,'',$explicitGuard);
$db->query("UPDATE {$p}asaas_company_proofs SET created_at='2000-01-01 00:00:00' WHERE id=?",[$freshProof]);
essentialThrows(fn()=>$asaas->reconcileExplicit($b,7,$freshOrigin,$freshProof,$freshTag,$explicitGuard),'asaas_company_stale','first explicit application preserves native five-minute freshness gate');
$responses[$freshRemote]['interestValue']='1.00';$adverseProof=$asaas->observe($b,7,$freshOrigin,$dec,$mock,'',$explicitGuard);
essentialThrows(fn()=>$asaas->reconcileExplicit($b,7,$freshOrigin,$adverseProof,$freshTag,$explicitGuard),'asaas_company_receipt_requires_review','interest never becomes an integral nominal receipt');
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords')->row()->n===$beforePayments+1,'negative proof cases cannot add another payment');
$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.5.7','asaas']);essentialThrows(fn()=>$asaas->reconcileExplicit($b,7,$origin,$proof,$tag,$explicitGuard),'asaas_module_158_required','157 cannot activate explicit158 semantics');
echo "Asaas158 explicit reconciliation: same native payment on retry, late rollback, no HTTP, no automation enable.\n";
