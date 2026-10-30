<?php
/** Native SQL and simulated BB API; daily-interest issuance is distinct from posting received charges. */
require __DIR__.'/banco_brasil_api_mysql.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ApiCreditedReceipt.php';
$interestMismatch=false;
$interestTransport=function($method,$url,$headers,$body)use($transport,&$titles,&$interestMismatch):array{
    $response=$transport($method,$url,$headers,$body);
    if($method==='GET'&&preg_match('~/boletos/([0-9]{20})~',$url,$m)&&($titles[$m[1]]['jurosMora']['tipo']??null)==='1'){
        $data=json_decode($response['body'],true,32,JSON_THROW_ON_ERROR);$data['codigoTipoJuroMora']=1;$data['valorJuroMoraTitulo']=$interestMismatch?0.11:0.10;$data['percentualJuroMoraTitulo']=0;$response['body']=json_encode($data,JSON_THROW_ON_ERROR);
    }
    return $response;
};
$interestApi=new \BancoBrasil\ApiService($db,$p,$encrypt,$decrypt,$interestTransport);
$interestDeclaration=$profileInput;unset($interestDeclaration['interest_exempt']);$interestDeclaration+=['interest_policy'=>'daily_fixed','interest_policy_confirmed'=>'1'];
$interestProfile=$interestApi->declareProfile($a,7,$connection,$interestDeclaration);essentialCheck($interestProfile!==$profile,'daily fixed profile is a distinct immutable contract, not a mutation of the exempt profile');
$interestApi->saveCredentials($a,7,$interestProfile,$secret);
$interestInvoice=$s->create($a,7,$apiInput,hash('sha256','daily-interest-invoice'));$interestTitle=$address+['daily_interest'=>'0.10'];$key=hash('sha256','daily-interest-issue');
$origin=$interestApi->issue($a,7,$interestProfile,$interestInvoice,$interestTitle,$key);$number=$db->query("SELECT remote_id FROM {$p}bb_origins WHERE id=?",[$origin])->row()->remote_id;
$stored=$db->query("SELECT payload_json,snapshot_json FROM {$p}bb_api_origins WHERE origin_id=?",[$origin])->row_array();
essentialCheck(str_contains($stored['payload_json'],'"jurosMora":{"tipo":1,"valor":0.10}')&&$titles[$number]['jurosMora']['valor']==='0.10','exact numeric daily fixed BRL leaf sent without float conversion');
$before=count($http);essentialCheck($interestApi->issue($a,7,$interestProfile,$interestInvoice,array_replace($interestTitle,['daily_interest'=>'0.1']),$key)===$origin&&count($http)===$before,'canonical daily amount retry does not duplicate origin or HTTP');
$interestApi->query($a,7,$origin,hash('sha256','daily-interest-query'));
$interestMismatch=true;essentialThrows(fn()=>$interestApi->query($a,7,$origin,hash('sha256','daily-interest-mismatch')),'bb_api_result_unconfirmed_query_origin','wrong detail interest amount is not account-confirmed state');$interestMismatch=false;
$otherInvoice=$s->create($a,7,$apiInput,hash('sha256','daily-interest-old-profile'));$before=(int)$db->query("SELECT next_number FROM {$p}bb_number_reservations")->row()->next_number;
essentialThrows(fn()=>$interestApi->issue($a,7,$profile,$otherInvoice,$interestTitle,hash('sha256','daily-interest-old-key')),'bb_api_interest_profile_denied','exempt profile cannot inherit or accept daily interest');
essentialCheck((int)$db->query("SELECT next_number FROM {$p}bb_number_reservations")->row()->next_number===$before,'denied interest does not consume or reuse a bank number');
$db->query("INSERT INTO {$p}staff(staffid,active,admin) VALUES(14,1,0)");$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(?,14,1)",[$a]);foreach([['banco_brasil','view'],['banco_brasil','api_issue'],['invoices','view']] as [$feature,$cap])$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,14,?,?)",[$a,$feature,$cap]);
essentialThrows(fn()=>$interestApi->issue($a,14,$interestProfile,$otherInvoice,$interestTitle,hash('sha256','daily-interest-no-grant')),'bb_api_access_denied','old API issue grant does not authorize newly introduced daily-interest capability');
$titles[$number]['state']='16';$titles[$number]['credit']=['valorOriginalTituloCobranca'=>105.53,'valorPagoSacado'=>106.53,'valorCreditoCedente'=>106.53,'valorPagamentoParcialTitulo'=>0,'valorImpostoSobreOprFinanceirasRecebidoTitulo'=>0,'valorAbatimentoTotal'=>0,'valorJuroMoraRecebido'=>1,'valorDescontoUtilizado'=>0,'valorMultaRecebido'=>0,'valorReajuste'=>0,'valorOutroRecebido'=>0,'dataCreditoLiquidacao'=>(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format('d.m.Y'),'codigoNaturezaRecebimento'=>1,'codigoTipoLiquidacao'=>2];$creditQuery=$interestApi->query($a,7,$origin,hash('sha256','daily-interest-credit-query'));
essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($a,7,new \BancoBrasil\ApiCreditedReceipt($origin,$creditQuery)),'bb_api_credit_adjustments_require_review','received charges are not ignored or falsely booked against invoice nominal');
essentialCheck($s->payments($a,7,$interestInvoice)===[]&&$s->document($a,7,$interestInvoice)->data()['totals']['total']==='105.53','interest-bearing title preserves invoice nominal and pending-charge accounting remains explicit');
echo 'BB daily interest native: '.$GLOBALS['essentialChecks'].' cumulative SQL checks; simulated provider, no bank homologation.'.PHP_EOL;
