<?php
/** Native MySQL/writer; provider transport inherited below is exclusively synthetic. */
require __DIR__.'/banco_brasil_api_mysql.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ApiCreditedReceipt.php';
$behavior='normal';$providerAccount=123873;$afterHttp=null;
$writer=new \app\services\InvoicePaymentWriter($db);
$bridgeReference=hash('sha256','synthetic bridge125 origin');$allowed=true;$guardCalls=0;
$guard=function()use(&$allowed,&$guardCalls,$db,$p):void{++$guardCalls;\IssuerTransaction::assertOwned($db,$p,['bb_api_origin_policy']);if(!$allowed)throw new DomainException('synthetic_bridge_revoked');};
$bridgeInvoice=$s->create($a,7,$apiInput,bin2hex(random_bytes(32)));$bridgeKey=bin2hex(random_bytes(32));
$before=count($http);$allowed=false;
essentialThrows(fn()=>$api->issue($a,7,$profile,$bridgeInvoice,$address,$bridgeKey,$guard,[],$bridgeReference),'synthetic_bridge_revoked','revoked before reserve');
essentialCheck(count($http)===$before&&!(int)$db->query("SELECT COUNT(*) n FROM {$p}bb_origins WHERE invoice_id=?",[$bridgeInvoice])->row()->n,'preflight veto writes no origin and sends no HTTP');
$allowed=true;$bridgeOrigin=$api->issue($a,7,$profile,$bridgeInvoice,$address,$bridgeKey,$guard,[],$bridgeReference);
$policy=$db->query("SELECT * FROM {$p}bb_api_origin_policy WHERE origin_id=?",[$bridgeOrigin])->row_array();
essentialCheck($policy['mode']==='bridge_manual'&&$policy['origin_reference']===$bridgeReference&&(int)$policy['issuer_id']===$a,'policy committed with immutable origin');
$before=count($http);
foreach([fn()=>$api->resumeIssue($a,7,$bridgeOrigin),fn()=>$api->cancel($a,7,$bridgeOrigin),fn()=>$api->query($a,7,$bridgeOrigin,bin2hex(random_bytes(32))),fn()=>$api->issue($a,7,$profile,$bridgeInvoice,$address,$bridgeKey)] as $unsafe)essentialThrows($unsafe,'bb_api_bridge_guard_required','native UI/cron cannot reuse bridge origin');
essentialCheck(count($http)===$before,'unguarded commands never authenticate');
essentialCheck($api->issue($a,7,$profile,$bridgeInvoice,$address,$bridgeKey,$guard,[],$bridgeReference)===$bridgeOrigin&&count($http)===$before,'guarded issue replay preserves number without HTTP');
$number=$db->query("SELECT remote_id FROM {$p}bb_origins WHERE id=?",[$bridgeOrigin])->row()->remote_id;
$titles[$number]['state']='16';$today=(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format('d.m.Y');
$titles[$number]['credit']=['valorOriginalTituloCobranca'=>105.53,'valorPagoSacado'=>105.53,'valorCreditoCedente'=>105.53,'valorPagamentoParcialTitulo'=>0,'valorImpostoSobreOprFinanceirasRecebidoTitulo'=>0,'valorAbatimentoTotal'=>0,'valorJuroMoraRecebido'=>0,'valorDescontoUtilizado'=>0,'valorMultaRecebido'=>0,'valorReajuste'=>0,'valorOutroRecebido'=>0,'dataCreditoLiquidacao'=>$today,'codigoNaturezaRecebimento'=>1,'codigoTipoLiquidacao'=>2];
$titles[$number]['credit']['valorJuroMoraRecebido']=1;
$adjustedProof=$api->query($a,7,$bridgeOrigin,bin2hex(random_bytes(32)),$guard,[],$bridgeReference);
essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($a,7,\BancoBrasil\ApiCreditedReceipt::explicit($bridgeOrigin,$adjustedProof,$bridgeReference,$guard)),'bb_api_credit_adjustments_require_review','bridge receipt refuses interest/adjustments');
$titles[$number]['credit']['valorJuroMoraRecebido']=0;
$proof=$api->query($a,7,$bridgeOrigin,bin2hex(random_bytes(32)),$guard,[],$bridgeReference);
$before=count($http);
essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($a,7,new \BancoBrasil\ApiCreditedReceipt($bridgeOrigin,$proof)),'bb_api_bridge_guard_required','default receipt cannot settle bridge');
$explicit=fn()=>\BancoBrasil\ApiCreditedReceipt::explicit($bridgeOrigin,$proof,$bridgeReference,$guard);
$allowed=false;essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($a,7,$explicit()),'synthetic_bridge_revoked','reconcile checks current consent');$allowed=true;
// Veto after canonical writer inserted the payment must roll back the entire owned transaction.
$late=function()use($db,$p,$bridgeInvoice):void{if((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$bridgeInvoice])->row()->n)throw new DomainException('synthetic_bridge_late_veto');};
essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($a,7,\BancoBrasil\ApiCreditedReceipt::explicit($bridgeOrigin,$proof,$bridgeReference,$late)),'synthetic_bridge_late_veto','late guard veto rolls back canonical payment');
essentialCheck(!(int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$bridgeInvoice])->row()->n,'payment rolled back with late veto');
$payment=$writer->recordCompanyProviderReceipt($a,7,$explicit());
essentialCheck($payment>0&&$writer->recordCompanyProviderReceipt($a,7,$explicit())===$payment,'same immutable proof returns same payment after invoice closed');
essentialCheck(count($http)===$before,'explicit reconciliation is completely HTTP-free');
essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($a,7,new \BancoBrasil\ApiCreditedReceipt($bridgeOrigin,$proof)),'bb_api_bridge_guard_required','policy remains after reconciliation');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$bridgeInvoice])->row()->n===1,'exactly one canonical payment');
// A consent change after registration response cannot authorize proof persistence.
$uncertainInvoice=$s->create($a,7,$apiInput,bin2hex(random_bytes(32)));$uncertainReference=hash('sha256','synthetic bridge125 after dispatch');$uncertainKey=bin2hex(random_bytes(32));$returnedHash=null;
$wrapped=function($method,$url,$headers,$body)use($transport,&$allowed,&$returnedHash){$result=$transport($method,$url,$headers,$body);if($method==='POST'&&str_contains($url,'/boletos?')){$allowed=false;$returnedHash=hash('sha256',$result['body']);}return $result;};
$guardedApi=new \BancoBrasil\ApiService($db,$p,$encrypt,$decrypt,$wrapped);
try{$guardedApi->issue($a,7,$profile,$uncertainInvoice,$address,$uncertainKey,$guard,[],$uncertainReference);throw new LogicException('late revoked proof accepted');}catch(RuntimeException $error){essentialCheck($error->getMessage()==='bb_api_result_unconfirmed_query_origin','post-dispatch guard veto surfaced as unconfirmed');}
$uncertain=$db->query("SELECT id,state FROM {$p}bb_origins WHERE invoice_id=?",[$uncertainInvoice])->row_array();
essentialCheck($uncertain['state']==='api_unknown','failure compensation preserves uncertainty without revoked guard');
essentialCheck(!(int)$db->query("SELECT COUNT(*) n FROM {$p}bb_api_operations WHERE origin_id=? AND status='validated'",[$uncertain['id']])->row()->n,'revoked response not recorded as proof');$allowed=true;
$quarantine=$db->query("SELECT * FROM {$p}bb_api_operations WHERE origin_id=? AND operation='issue'",[$uncertain['id']])->row_array();$technical=json_decode($quarantine['result_json'],true,16,JSON_THROW_ON_ERROR);
essentialCheck($quarantine['status']==='unknown'&&(int)$quarantine['http_status']===201&&$quarantine['response_hash']===$returnedHash&&$quarantine['failure_category']==='technical_result_quarantined','returned response hash/status retained under original unknown operation claim');
essentialCheck($technical['technical_only']===true&&$technical['projection']['bank_state']==='registration_accepted'&&preg_match('/^[0-9]{20}$/D',$technical['projection']['numero']),'bounded canonical projection retained, not financially validated');
essentialCheck(!str_contains($quarantine['result_json'],'access_token')&&!str_contains($quarantine['result_json'],'client_secret')&&!str_contains($quarantine['result_json'],'pagador'),'no token or raw payer response persisted');
$before=count($http);$same=$guardedApi->issue($a,7,$profile,$uncertainInvoice,$address,$uncertainKey,$guard,[],$uncertainReference);
essentialCheck($same===(int)$uncertain['id']&&count($http)===$before,'quarantined successful response cannot cause a second POST');
essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($a,7,\BancoBrasil\ApiCreditedReceipt::explicit((int)$uncertain['id'],(int)$quarantine['id'],$uncertainReference,$guard)),'bb_api_credit_state_requires_review','technical response cannot settle invoice');
essentialCheck(!(int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$uncertainInvoice])->row()->n,'technical evidence has zero financial effect');
echo 'BB125 bridge explicit guard/policy/writer fixture: '.$GLOBALS['essentialChecks'].' cumulative checks; synthetic HTTP only.'.PHP_EOL;
