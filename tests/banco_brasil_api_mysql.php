<?php
/** Actual native schema/encryption/transactions + deterministic simulated provider. Never calls Banco do Brasil. */
require __DIR__.'/banco_brasil_origination_mysql.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ApiService.php';
require_once BASEPATH.'libraries/Encryption.php';
$nativeKey=random_bytes(32);
$nativeCipher=new CI_Encryption(['driver'=>'openssl','cipher'=>'aes-256','mode'=>'cbc','key'=>$nativeKey]);
$encrypt=fn($plain)=>$nativeCipher->encrypt($plain);$decrypt=fn($cipher)=>$nativeCipher->decrypt($cipher);
$http=[];$titles=[];$behavior='normal';$providerAccount=123873;$afterHttp=null;
function apiBarcode(string $number,string $amount,string $due):array
{
    // Independent manual positions; fake provider, not an assertion of bank homologation.
    $factor=1000+(int)(new DateTimeImmutable('2025-02-22'))->diff(new DateTimeImmutable($due))->format('%r%a');
    $barcode='00190'.str_pad((string)$factor,4,'0',STR_PAD_LEFT).str_pad((string)\BancoBrasil\Fields::cents($amount),10,'0',STR_PAD_LEFT).'000000'.substr($number,3).'17';
    $raw=substr($barcode,0,4).substr($barcode,5);$sum=0;$weight=2;
    foreach(array_reverse(str_split($raw)) as $digit){$sum+=(int)$digit*$weight;$weight=$weight===9?2:$weight+1;}$dv=11-$sum%11;if($dv===0||$dv===10||$dv===11)$dv=1;$barcode[4]=(string)$dv;
    $one=substr($barcode,0,4).substr($barcode,19,5);$two=substr($barcode,24,10);$three=substr($barcode,34,10);
    $check=static function($field){$sum=0;$weight=2;foreach(array_reverse(str_split($field)) as $digit){$product=(int)$digit*$weight;$sum+=array_sum(array_map('intval',str_split((string)$product)));$weight=3-$weight;}return (string)((10-$sum%10)%10);};
    return [$barcode,$one.$check($one).$two.$check($two).$three.$check($three).$barcode[4].substr($barcode,5,14)];
}
$transport=function(string $method,string $url,array $headers,string $body)use(&$http,&$titles,&$behavior,&$afterHttp,&$providerAccount,$db):array{
    essentialCheck(!$db->pdo->inTransaction(),'OAuth/API HTTP happens outside every SQL transaction');
    $http[]=compact('method','url','headers','body');
    if(str_contains($url,'oauth/token')){
        if($behavior==='auth-failure')return ['status'=>401,'body'=>'synthetic secret diagnostic must not be stored'];
        if($afterHttp){$hook=$afterHttp;$afterHttp=null;$hook();}
        return ['status'=>200,'body'=>'{"access_token":"synthetic-token","token_type":"Bearer","expires_in":600}'];
    }
    parse_str(parse_url($url,PHP_URL_QUERY),$query);$number=preg_match('~/boletos/([0-9]{20})~',$url,$match)?$match[1]:null;
    if($method==='POST'&&str_contains($url,'/boletos?')){
        $payload=\BancoBrasil\ApiClient::decodeExact($body);$number=$payload['numeroTituloCliente'];$titles[$number]=$payload+['state'=>'1','account'=>$behavior==='wrong-account'?999:$providerAccount];
        if($behavior==='timeout-after-post')throw new RuntimeException('synthetic transport secret detail');
        $due=DateTimeImmutable::createFromFormat('!d.m.Y',$payload['dataVencimento'])->format('Y-m-d');[$barcode,$line]=apiBarcode($number,$payload['valorOriginal'],$due);
        $response=['numero'=>$number,'numeroCarteira'=>17,'numeroVariacaoCarteira'=>19,'numeroContratoCobranca'=>1234,'codigoBarraNumerico'=>$barcode,'linhaDigitavel'=>$line,'beneficiario'=>['agencia'=>452,'contaCorrente'=>$behavior==='wrong-account'?999:$providerAccount]];
    }elseif($method==='GET'&&!$number){
        $list=[];foreach($titles as $nn=>$t)if((string)$t['account']===($query['contaBeneficiario']??'')&&(string)$t['state']===($query['codigoEstadoTituloCobranca']??''))$list[]=['numeroBoletoBB'=>$nn,'dataVencimento'=>$t['dataVencimento'],'valorOriginal'=>(float)$t['valorOriginal'],'carteiraConvenio'=>17,'variacaoCarteiraConvenio'=>19,'contrato'=>1234,'codigoEstadoTituloCobranca'=>(int)$t['state']];
        $response=['indicadorContinuidade'=>'N','proximoIndice'=>0,'quantidadeRegistros'=>count($list),'boletos'=>$list];
    }elseif($method==='GET'){
        if(!isset($titles[$number]))return ['status'=>404,'body'=>'{"erros":[{"codigo":"synthetic-not-found"}]}'];$t=$titles[$number];
        $response=['numeroContratoCobranca'=>1234,'numeroTituloCedenteCobranca'=>$t['numeroTituloBeneficiario'],'textoCampoUtilizacaoCedente'=>$t['campoUtilizacaoBeneficiario'],'numeroInscricaoSacadoCobranca'=>$t['pagador']['numeroInscricao'],'codigoTipoInscricaoSacado'=>(int)$t['pagador']['tipoInscricao'],'numeroCarteiraCobranca'=>17,'numeroVariacaoCarteiraCobranca'=>19,'codigoModalidadeTitulo'=>1,'codigoIndicadorEconomico'=>9,'valorOriginalTituloCobranca'=>(float)$t['valorOriginal'],'codigoEstadoTituloCobranca'=>(int)$t['state'],'dataEmissaoTituloCobranca'=>$t['dataEmissao'],'dataVencimentoTituloCobranca'=>$t['dataVencimento']];
        $response=array_merge($response,$t['credit']??[]);
    }else{
        essentialCheck(str_contains($url,'/baixar?'),'only official cancellation path is used');$titles[$number]['state']='7';
        $response=['numeroContratoCobranca'=>'1234','dataBaixa'=>date('d.m.Y'),'horarioBaixa'=>'10:11:12'];
    }
    if($afterHttp){$hook=$afterHttp;$afterHttp=null;$hook();}
    return ['status'=>$method==='POST'&&str_contains($url,'/boletos?')?201:200,'body'=>json_encode($response,JSON_THROW_ON_ERROR)];
};
$api=new \BancoBrasil\ApiService($db,$p,$encrypt,$decrypt,$transport);
$profileInput=['api_stage'=>'homologation','first_number'=>'100','last_number'=>'999','first_file'=>'1','reference'=>'SYNTHETIC API CONTRACT','interest_exempt'=>'1','exclusive_range'=>'1'];
$profile=$api->declareProfile($a,7,$connection,$profileInput);
essentialCheck($api->declareProfile($a,7,$connection,$profileInput)===$profile,'same API declaration idempotent and shared CNAB counter cannot reset');
essentialThrows(fn()=>$api->declareProfile($a,7,$connection,array_replace($profileInput,['api_stage'=>'production'])),'bb_api_environment_invalid','production cannot borrow a test connection');
essentialThrows(fn()=>$api->declareProfile($a,7,$connection,array_replace($profileInput,['first_number'=>'1'])),'bb_number_range_conflict','API cannot restart existing CNAB Nosso Numero range');
$secret=['client_id'=>'SYNTHETIC-CLIENT-A','client_secret'=>'SYNTHETIC-SECRET-A','application_key'=>'SYNTHETIC-KEY-A'];$credential=$api->saveCredentials($a,7,$profile,$secret);
$stored=$db->query("SELECT ciphertext FROM {$p}bb_api_credentials WHERE id=?",[$credential])->row()->ciphertext;
essentialCheck(!str_contains($stored,$secret['client_secret'])&&!str_contains($stored,$secret['client_id'])&&is_string($nativeCipher->decrypt($stored)),'actual native authenticated encryption protects credentials');
essentialCheck(count($http)===0,'configuring profile/credentials never authenticates or contacts provider');
$apiInput=$input;$apiInput['date']=(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');$apiInput['due_date']=(new DateTimeImmutable($apiInput['date']))->modify('+30 days')->format('Y-m-d');
$address=['payer_address'=>'Synthetic street','payer_district'=>'Centro','payer_zip'=>'04000000','payer_city'=>'Salvador','payer_state'=>'BA','species'=>'02','acceptance'=>'N'];
$invoice=$s->create($a,7,$apiInput,str_repeat('2',63).'1');$originId=$api->issue($a,7,$profile,$invoice,$address,str_repeat('1',64));
$issued=$db->query("SELECT * FROM {$p}bb_origins WHERE id=?",[$originId])->row_array();
essentialCheck($issued['remote_id']==='00012345670000000103'&&$issued['state']==='api_registered','API continues shared counter and validates registration number/account/barcode');
$last=end($http);$json=\BancoBrasil\ApiClient::decodeExact($last['body']);essentialCheck($json['pagador']['numeroInscricao']==='11144477735'&&$json['valorOriginal']==='105.53'&&str_contains($last['body'],'"valorOriginal":105.53'),'payer comes from immutable invoice and JSON money is exact numeric lexeme');
essentialCheck($api->issue($a,7,$profile,$invoice,array_reverse($address,true),str_repeat('1',64))===$originId&&count($http)===2,'retry with reordered equivalent fields does not send another OAuth or POST');
essentialThrows(fn()=>$api->query($b,7,$originId,str_repeat('2',64)),'bb_api_origin_unavailable','direct A API origin ID is unavailable to B');
essentialThrows(fn()=>$api->cancel($a,7,$originId),'bb_api_cancel_requires_fresh_normal_query','cancellation needs fresh source-bound normal-state query');
$api->query($a,7,$originId,str_repeat('2',64));$before=count($http);$api->cancel($a,7,$originId);$api->cancel($a,7,$originId);
essentialCheck(count($http)===$before+2&&$db->query("SELECT state FROM {$p}bb_origins WHERE id=?",[$originId])->row()->state==='api_cancel_requested','cancellation POST once; its reply means request, not final bank cancellation');
$api->query($a,7,$originId,str_repeat('3',64));essentialCheck($db->query("SELECT active_slot FROM {$p}bb_origins WHERE id=?",[$originId])->row()->active_slot===null,'verified bank query code7 releases only the old active origin slot');
essentialCheck($s->document($a,7,$invoice)->data()['status']===1,'bank cancellation does not cancel or pay native invoice locally');
$again=$api->issue($a,7,$profile,$invoice,$address,str_repeat('4',64));essentialCheck($again!==$originId&&$db->query("SELECT remote_id FROM {$p}bb_origins WHERE id=?",[$again])->row()->remote_id==='00012345670000000104','explicit reissue after confirmed cancellation keeps old history and allocates new number');
$behavior='timeout-after-post';$unknownInvoice=$s->create($a,7,$apiInput,str_repeat('2',63).'2');
essentialThrows(fn()=>$api->issue($a,7,$profile,$unknownInvoice,$address,str_repeat('5',64)),'bb_api_result_unconfirmed_query_origin','POST timeout is not falsely reported as rejection or registration');
$unknown=$db->query("SELECT id,state FROM {$p}bb_origins WHERE invoice_id=?",[$unknownInvoice])->row_array();$before=count($http);$api->resumeIssue($a,7,$unknown['id']);
essentialCheck($unknown['state']==='api_unknown'&&count($http)===$before,'unknown POST is never automatically resent, including explicit retry');
$behavior='normal';$api->query($a,7,$unknown['id'],str_repeat('6',64));essentialCheck($db->query("SELECT state FROM {$p}bb_origins WHERE id=?",[$unknown['id']])->row()->state==='api_query_observed','authenticated query can observe a registration whose POST reply was lost');
$behavior='auth-failure';$authInvoice=$s->create($a,7,$apiInput,str_repeat('2',63).'3');
essentialThrows(fn()=>$api->issue($a,7,$profile,$authInvoice,$address,str_repeat('7',64)),'bb_api_authentication_not_submitted','failed OAuth never submits a charge');
$authOrigin=(int)$db->query("SELECT id FROM {$p}bb_origins WHERE invoice_id=?",[$authInvoice])->row()->id;$behavior='normal';$api->resumeIssue($a,7,$authOrigin);
essentialCheck($db->query("SELECT state FROM {$p}bb_origins WHERE id=?",[$authOrigin])->row()->state==='api_registered','authentication-only failure can resume original reserved number safely');
$behavior='wrong-account';$wrongInvoice=$s->create($a,7,$apiInput,str_repeat('2',63).'4');
essentialThrows(fn()=>$api->issue($a,7,$profile,$wrongInvoice,$address,str_repeat('8',64)),'bb_api_result_unconfirmed_query_origin','201 belonging to another beneficiary account is not bank confirmation for this invoice');$behavior='normal';
$before=count($http);$wrongOrigin=(int)$db->query("SELECT id FROM {$p}bb_origins WHERE invoice_id=?",[$wrongInvoice])->row()->id;
essentialThrows(fn()=>$api->query($a,7,$wrongOrigin,str_repeat('9',64)),'bb_api_result_unconfirmed_query_origin','detail lookup alone cannot confirm a title whose account list excludes this origin');
essentialCheck($db->query("SELECT state FROM {$p}bb_origins WHERE id=?",[$wrongOrigin])->row()->state==='api_unknown','wrong-account query does not promote unknown origin');
// Copying encrypted bytes or moving their nonce cannot rebind them to a company/profile.
$db->query("UPDATE {$p}bb_api_credentials SET context_nonce=? WHERE id=?",[str_repeat('0',64),$credential]);$before=count($http);
essentialThrows(fn()=>$api->query($a,7,$again,str_repeat('a',64)),'bb_api_authentication_not_submitted','encrypted credential context is verified before OAuth');
essentialCheck(count($http)===$before,'cipher context mismatch causes no external request');
$envelope=json_decode($nativeCipher->decrypt($stored),true,16,JSON_THROW_ON_ERROR);$db->query("UPDATE {$p}bb_api_credentials SET context_nonce=? WHERE id=?",[$envelope['nonce'],$credential]);
$rotated=['client_id'=>'SYNTHETIC-ROTATED-ID','client_secret'=>'SYNTHETIC-ROTATED-SECRET','application_key'=>'SYNTHETIC-ROTATED-KEY'];
$api->saveCredentials($a,7,$profile,$rotated);$before=count($http);$api->query($a,7,$again,str_repeat('b',64));
essentialCheck(in_array('Authorization: Basic '.base64_encode($rotated['client_id'].':'.$rotated['client_secret']),$http[$before]['headers'],true)&&str_contains($http[$before+1]['url'],'SYNTHETIC-ROTATED-KEY'),'credential rotation applies only within the immutable original API profile');
$revokedInvoice=$s->create($a,7,$apiInput,str_repeat('2',63).'5');$afterHttp=fn()=>$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");$before=count($http);
essentialThrows(fn()=>$api->issue($a,7,$profile,$revokedInvoice,$address,str_repeat('c',64)),'bb_api_authentication_not_submitted','authorization revoked during OAuth is rechecked before charge HTTP');
essentialCheck(count($http)===$before+1,'revoked actor causes OAuth only, never POST charge');$db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");
$revokedOrigin=(int)$db->query("SELECT id FROM {$p}bb_origins WHERE invoice_id=?",[$revokedInvoice])->row()->id;$api->resumeIssue($a,7,$revokedOrigin);
essentialCheck($db->query("SELECT state FROM {$p}bb_origins WHERE id=?",[$revokedOrigin])->row()->state==='api_registered','new authorized retry after OAuth-only revocation keeps original reserved number');
$plainAudit=json_encode($db->query("SELECT * FROM {$p}bb_audit")->result_array());$plainOps=json_encode($db->query("SELECT result_json,failure_category FROM {$p}bb_api_operations")->result_array());
essentialCheck(!str_contains($plainAudit,$secret['client_secret'])&&!str_contains($plainOps,$secret['client_secret'])&&!str_contains($plainOps,'synthetic transport secret detail'),'audit and API diagnostics contain neither credentials nor raw provider/transport errors');
$before=count($http);essentialThrows(fn()=>$api->cancel($a,7,(int)$origin['id']),'bb_api_origin_unavailable','CNAB batch/foreign origin is never redirected through API');essentialCheck(count($http)===$before,'invalid source causes no HTTP');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$invoice])->row()->n===0,'API query/registration/cancellation never fabricates financial settlement');
echo 'BB API native MySQL/encryption/outbox: '.$GLOBALS['essentialChecks'].' cumulative checks; simulated provider, no real tokens/charges.'.PHP_EOL;
