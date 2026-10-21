<?php
/** Real native schema/origin composition; TLS variables/certificate and bank transport remain synthetic. */
require __DIR__.'/banco_brasil_api_amendment.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ApiWebhook.php';
\BancoBrasil\ApiWebhookSchema::ensure($db,$p);
$webhook=new \BancoBrasil\ApiWebhook($db,$p);
$key=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);$csr=openssl_csr_new(['commonName'=>'SYNTHETIC BB CLIENT'],$key,['digest_alg'=>'sha256']);$cert=openssl_csr_sign($csr,null,$key,1,['digest_alg'=>'sha256']);openssl_x509_export($cert,$pem);
$tls=\BancoBrasil\MtlsEvidence::fromNativeTls(['HTTPS'=>'on','SSL_CLIENT_VERIFY'=>'SUCCESS','SSL_CLIENT_CERT'=>$pem],true);$receiver=['fingerprint'=>$tls->fingerprint,'reference'=>'SYNTHETIC CONTRACT ONLY','enabled'=>'1'];
$webhook->configure($a,7,$profile,$receiver);$webhook->configure($a,7,$profile,$receiver);
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}bb_api_webhook_receivers WHERE profile_id=?",[$profile])->row()->n===1,'local receiver configuration/retry does not create duplicate or register remote webhook');
essentialThrows(fn()=>$webhook->configure($b,7,$profile,$receiver),'bb_webhook_profile_unavailable','B cannot activate A receiver profile');
$number=$db->query("SELECT remote_id FROM {$p}bb_origins WHERE id=?",[$amendOrigin])->row()->remote_id;
$event=['id'=>$number,'numeroConvenio'=>1234567,'numeroOperacao'=>1001,'carteiraConvenio'=>17,'variacaoCarteiraConvenio'=>19,'codigoEstadoBaixaOperacional'=>1,'codigoModalidadeBoleto'=>1,'canalLiquidacao'=>3,'formaPagamento'=>2,'dataRegistro'=>'19.10.2026','dataVencimento'=>(new DateTimeImmutable($apiInput['due_date']))->format('d.m.Y'),'dataCredito'=>'20.10.2026','dataLiquidacao'=>'19/10/2026 12:20:30','valorOriginal'=>105.53,'valorPagoSacado'=>105.53,'nomePortador'=>'DO NOT PERSIST SYNTHETIC PII'];$body=json_encode($event,JSON_THROW_ON_ERROR);
$id=$webhook->receive($tls,$body);essentialCheck($webhook->receive($tls,json_encode(array_reverse($event,true)))===$id,'duplicate notification same canonical event persisted once');
$native=$db->query("SELECT status FROM {$p}invoices WHERE id=?",[$amendInvoice])->row()->status;
essentialCheck((int)$native===1&&(int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$amendInvoice])->row()->n===0,'mTLS operational notification never posts financial credit or changes invoice status');
$stored=$db->query("SELECT projection_json FROM {$p}bb_api_notifications WHERE id=?",[$id])->row()->projection_json;essentialCheck(!str_contains($stored,'SYNTHETIC PII'),'event stores minimum normalized projection without payer name');
essentialThrows(fn()=>$webhook->receive($tls,json_encode(array_replace($event,['valorPagoSacado'=>100.00]))),'bb_webhook_event_conflict','same movement identity with divergent content conflicts');
$webhook->configure($a,7,$profile,array_replace($receiver,['enabled'=>'0']));essentialThrows(fn()=>$webhook->receive($tls,$body),'bb_webhook_origin_unavailable','revoked pin takes effect on next callback including replay');
$webhook->configure($a,7,$profile,$receiver);$cancel=json_encode(array_replace($event,['codigoEstadoBaixaOperacional'=>10]));$cancelId=$webhook->receive($tls,$cancel);essentialCheck($cancelId!==$id,'out-of-order cancellation preserved independently rather than undoing prior observation');
essentialCheck($webhook->overview($b,7)['events']===[],'B notification GUI overview excludes A events');
$db->failOn='INSERT INTO '.$p.'bb_api_notifications';essentialThrows(fn()=>$webhook->receive($tls,json_encode(array_replace($event,['numeroOperacao'=>1002]))),'issuer_storage_failed','notification persistence failure is not acknowledged');$db->failOn=null;
$webhook->receive($tls,json_encode(array_replace($event,['numeroOperacao'=>1002])));essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}bb_api_notifications WHERE origin_id=?",[$amendOrigin])->row()->n===3,'failed receipt can retry without partial notification');
// A later account-proven credit does not erase an earlier adverse operational event.
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ApiCreditedReceipt.php';
$titles[$number]['state']='16';$titles[$number]['credit']=['valorOriginalTituloCobranca'=>105.53,'valorPagoSacado'=>105.53,'valorCreditoCedente'=>105.53,'valorPagamentoParcialTitulo'=>0,'valorImpostoSobreOprFinanceirasRecebidoTitulo'=>0,'valorAbatimentoTotal'=>0,'valorJuroMoraRecebido'=>0,'valorDescontoUtilizado'=>0,'valorMultaRecebido'=>0,'valorReajuste'=>0,'valorOutroRecebido'=>0,'dataCreditoLiquidacao'=>(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format('d.m.Y'),'codigoNaturezaRecebimento'=>1,'codigoTipoLiquidacao'=>2];
$creditQuery=$amendApi->query($a,7,$amendOrigin,hash('sha256','webhook-adverse-credit'));
essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($a,7,new \BancoBrasil\ApiCreditedReceipt($amendOrigin,$creditQuery)),'bb_api_credit_adverse_history','mTLS operational cancellation vetoes later full-credit query without silently reversing/posting funds');
// B has a separate account/agreement/profile and independently receives its own callback.
[$webhookConnectionB]=bbSyntheticConnection($b,'7654321','001:00452-3:000000223873-X','52998224725','Invoice B');$webhookProfileB=$amendApi->declareProfile($b,7,$webhookConnectionB,$profileInput);$amendApi->saveCredentials($b,7,$webhookProfileB,$secret);$providerAccount=223873;
$webhookClientB=(new CompanyClients($db,$p))->create($b,7,['company'=>'Synthetic callback B payer','vat'=>'52998224725']);$webhookInvoiceB=$s->create($b,7,array_replace($apiInput,['client_id'=>$webhookClientB]),hash('sha256','webhook-B-invoice'));$webhookOriginB=$amendApi->issue($b,7,$webhookProfileB,$webhookInvoiceB,$address,hash('sha256','webhook-B-issue'));$providerAccount=123873;
$webhook->configure($b,7,$webhookProfileB,$receiver);$webhookNumberB=$db->query("SELECT remote_id FROM {$p}bb_origins WHERE id=?",[$webhookOriginB])->row()->remote_id;
$eventB=array_replace($event,['id'=>$webhookNumberB,'numeroConvenio'=>7654321]);$eventIdB=$webhook->receive($tls,json_encode($eventB));essentialCheck($eventIdB!==$id&&count($webhook->overview($b,7)['events'])===1&&count($webhook->overview($a,7)['events'])===3,'positive B callback has independent origin and GUI history');
essentialCheck($db->query("SELECT * FROM {$p}invoices WHERE id=42")->result_array()===$legacyBefore,'all callback/amendment operations preserve NULL legacy invoice byte values');
echo 'BB operational webhook native: '.$GLOBALS['essentialChecks'].' cumulative checks; synthetic TLS and bank, no external homologation.'.PHP_EOL;
