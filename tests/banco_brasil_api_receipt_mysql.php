<?php
/** Actual native writer + real disposable DB. Only the BB HTTP transport is simulated. */
require __DIR__.'/banco_brasil_api_mysql.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ApiCreditedReceipt.php';
function creditedOrigin(int $issuer,int $profile,int $client):array
{
 global $s,$api,$apiInput,$address,$titles,$db,$p;
 $input=array_replace($apiInput,['client_id'=>$client]);$invoice=$s->create($issuer,7,$input,bin2hex(random_bytes(32)));
 $origin=$api->issue($issuer,7,$profile,$invoice,$address,bin2hex(random_bytes(32)));
 $number=$db->query("SELECT remote_id FROM {$p}bb_origins WHERE id=?",[$origin])->row()->remote_id;
 $titles[$number]['state']='16';$today=(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format('d.m.Y');
 $titles[$number]['credit']=['valorOriginalTituloCobranca'=>105.53,'valorPagoSacado'=>105.53,'valorCreditoCedente'=>105.53,'valorPagamentoParcialTitulo'=>0,'valorImpostoSobreOprFinanceirasRecebidoTitulo'=>0,'valorAbatimentoTotal'=>0,'valorJuroMoraRecebido'=>0,'valorDescontoUtilizado'=>0,'valorMultaRecebido'=>0,'valorReajuste'=>0,'valorOutroRecebido'=>0,'dataCreditoLiquidacao'=>$today,'codigoNaturezaRecebimento'=>1,'codigoTipoLiquidacao'=>2];
 $operation=$api->query($issuer,7,$origin,bin2hex(random_bytes(32)));
 return [$invoice,$origin,$operation,$number];
}
$writer=new \app\services\InvoicePaymentWriter($db);
[$receiptInvoice,$receiptOrigin,$receiptQuery,$receiptNumber]=creditedOrigin($a,$profile,$ca);
$source=new \BancoBrasil\ApiCreditedReceipt($receiptOrigin,$receiptQuery);
essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($b,7,$source),'bb_api_origin_unavailable','API receipt cannot use A origin under B context');
$httpBefore=count($http);$db->failOn='INSERT INTO '.$p.'bb_api_receipts';
essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($a,7,$source),'issuer_storage_failed','receipt provenance failure rolls back native payment and paid status');$db->failOn=null;
essentialCheck($s->payments($a,7,$receiptInvoice)===[]&&$s->document($a,7,$receiptInvoice)->data()['status']===1,'failed API credit leaves no payment or false paid status');
$db->failOn='INSERT INTO '.$p.'bb_audit';essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($a,7,$source),'issuer_storage_failed','BB audit failure rolls back the entire credit');$db->failOn=null;
$payment=$writer->recordCompanyProviderReceipt($a,7,$source);
essentialCheck($writer->recordCompanyProviderReceipt($a,7,new \BancoBrasil\ApiCreditedReceipt($receiptOrigin,$receiptQuery))===$payment,'same API evidence repeats one native payment');
essentialCheck(count($s->payments($a,7,$receiptInvoice))===1&&$s->document($a,7,$receiptInvoice)->data()['status']===2,'explicit full credited receipt settles only its own invoice');
essentialCheck(count($http)===$httpBefore,'financial confirmation performs no HTTP while SQL transaction is held');
$newQuery=$api->query($a,7,$receiptOrigin,bin2hex(random_bytes(32)));
essentialCheck($writer->recordCompanyProviderReceipt($a,7,new \BancoBrasil\ApiCreditedReceipt($receiptOrigin,$newQuery))===$payment,'later identical query/another operation ID does not create another payment');
essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($a,7,$source),'bb_api_credit_query_stale','older editor is rejected after newer query');
// Each rejected semantic uses a freshly authenticated + account-proven simulated query.
foreach([
 ['state'=>'6','error'=>'bb_api_credit_state_requires_review'],
 ['state'=>'18','error'=>'bb_api_credit_state_requires_review'],
 ['state'=>'17','error'=>'bb_api_credit_state_requires_review'],
 ['fields'=>['valorJuroMoraRecebido'=>0.01],'error'=>'bb_api_credit_adjustments_require_review'],
 ['fields'=>['valorCreditoCedente'=>105.52],'error'=>'bb_api_credit_amount_divergence'],
 ['fields'=>['codigoTipoLiquidacao'=>5],'error'=>'bb_api_credit_state_requires_review'],
 ['fields'=>['codigoNaturezaRecebimento'=>4],'error'=>'bb_api_credit_state_requires_review'],
 ['fields'=>['dataCreditoLiquidacao'=>'31.02.2026'],'error'=>'bb_api_credit_date_requires_review'],
 ['fields'=>['dataCreditoLiquidacao'=>'19.10.2099'],'error'=>'bb_api_credit_date_requires_review'],
] as $case){
 [$testInvoice,$testOrigin,$testQuery,$testNumber]=creditedOrigin($a,$profile,$ca);
 if(isset($case['state']))$titles[$testNumber]['state']=$case['state'];
 if(isset($case['fields']))$titles[$testNumber]['credit']=array_replace($titles[$testNumber]['credit'],$case['fields']);
 $testQuery=$api->query($a,7,$testOrigin,bin2hex(random_bytes(32)));
 essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($a,7,new \BancoBrasil\ApiCreditedReceipt($testOrigin,$testQuery)),$case['error'],'insufficient or conflicting bank evidence cannot enter writer');
 essentialCheck($s->payments($a,7,$testInvoice)===[],'denied API evidence creates no native payment');
}
[$staleInvoice,$staleOrigin,$staleQuery]=creditedOrigin($a,$profile,$ca);
$db->query("UPDATE {$p}bb_api_operations SET updated_at=? WHERE id=?",[gmdate('Y-m-d H:i:s',time()-301),$staleQuery]);
essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($a,7,new \BancoBrasil\ApiCreditedReceipt($staleOrigin,$staleQuery)),'bb_api_credit_query_stale','first application requires query within five minutes');
// Missing monetary proof stays in review, even with state16 and a successful HTTP status.
[$missingInvoice,$missingOrigin,$missingQuery,$missingNumber]=creditedOrigin($a,$profile,$ca);
unset($titles[$missingNumber]['credit']['valorOutroRecebido']);$missingQuery=$api->query($a,7,$missingOrigin,bin2hex(random_bytes(32)));
essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($a,7,new \BancoBrasil\ApiCreditedReceipt($missingOrigin,$missingQuery)),'bb_api_credit_adjustments_require_review','missing proof is not silently interpreted as zero');
// An adverse later query never reverses a financial receipt implicitly.
$titles[$receiptNumber]['state']='7';$adverseQuery=$api->query($a,7,$receiptOrigin,bin2hex(random_bytes(32)));
essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($a,7,new \BancoBrasil\ApiCreditedReceipt($receiptOrigin,$adverseQuery)),'bb_api_credit_state_requires_review','conflicting later bank state requires review');
essentialCheck(count($s->payments($a,7,$receiptInvoice))===1&&$s->document($a,7,$receiptInvoice)->data()['status']===2,'bank observation does not erase/reverse existing financial history');
$titles[$receiptNumber]['state']='16';$contradictory=$api->query($a,7,$receiptOrigin,bin2hex(random_bytes(32)));
essentialCheck($db->query("SELECT state FROM {$p}bb_origins WHERE id=?",[$receiptOrigin])->row()->state==='api_conflict_review','later state16 cannot erase contradictory prior cancellation/credit history');
essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($a,7,new \BancoBrasil\ApiCreditedReceipt($receiptOrigin,$contradictory)),'bb_api_credit_state_requires_review','contradictory history stays in specialist review');
// B owns a different account/agreement/profile and can execute the same complete positive path.
[$connectionB]=bbSyntheticConnection($b,'7654321','001:00452-3:000000223873-X','52998224725','Invoice B');
$profileB=$api->declareProfile($b,7,$connectionB,$profileInput);$api->saveCredentials($b,7,$profileB,$secret);$providerAccount=223873;
$bankClientB=(new CompanyClients($db,$p))->create($b,7,['company'=>'Synthetic API B payer','vat'=>'52998224725']);
[$bInvoice,$bOrigin,$bQuery]=creditedOrigin($b,$profileB,$bankClientB);$providerAccount=123873;
$bPayment=$writer->recordCompanyProviderReceipt($b,7,new \BancoBrasil\ApiCreditedReceipt($bOrigin,$bQuery));
essentialCheck($bPayment!==$payment&&$s->document($b,7,$bInvoice)->data()['status']===2,'B account/profile credit is independently applied');
essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($a,7,new \BancoBrasil\ApiCreditedReceipt($bOrigin,$bQuery)),'bb_api_origin_unavailable','B evidence cannot be reused in A');
echo 'API credited receipts: '.$GLOBALS['essentialChecks'].' cumulative checks; actual SQL/writer, simulated bank HTTP only.'.PHP_EOL;

\BancoBrasil\ApiReceiptSchema::ensure($db,$p,false);
$db->query("ALTER TABLE {$p}bb_api_receipts DROP INDEX canonical_receipt");
essentialThrows(fn()=>\BancoBrasil\ApiReceiptSchema::ensure($db,$p,false),'bb_api_receipt_schema_invalid','missing canonical uniqueness fails closed, no DDL repair during receipt');
$db->query("ALTER TABLE {$p}bb_api_receipts ADD UNIQUE KEY canonical_receipt(canonical_key)");
$db->query("ALTER TABLE {$p}bb_api_receipts ALTER issuer_id SET DEFAULT 0");
essentialThrows(fn()=>\BancoBrasil\ApiReceiptSchema::ensure($db,$p,false),'bb_api_receipt_schema_invalid','metadata DEFAULT0 is not accepted as missing origin');
$db->query("ALTER TABLE {$p}bb_api_receipts ALTER issuer_id DROP DEFAULT");\BancoBrasil\ApiReceiptSchema::ensure($db,$p,false);
echo 'API credit schema/immutable conflict: '.$GLOBALS['essentialChecks'].' cumulative actual DB checks.'.PHP_EOL;
