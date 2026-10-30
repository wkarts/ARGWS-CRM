<?php
/** Reconstructed 439 contracts. Pure protocol tests, injected HTTP only, no bank access. */
declare(strict_types=1);
define('BASEPATH', dirname(__DIR__).'/system/');
require_once dirname(__DIR__).'/application/services/InvoicePaymentWriter.php';
$bb439Lib=dirname(__DIR__).'/modules/banco_brasil/libraries/BancoBrasil/';
foreach(['ApiAbatement','ApiAddress','ApiDiscountAmendment','ApiClient','ModuleRelease'] as $class) require_once $bb439Lib.$class.'.php';
use BancoBrasil\ApiAbatement;
use BancoBrasil\ApiAddress;
use BancoBrasil\ApiDiscountAmendment;
use BancoBrasil\ApiClient;
$bb439Checks=0;
function bb439Check(bool $value,string $label):void { ++$GLOBALS['bb439Checks']; if(!$value) throw new RuntimeException($label); }
function bb439Reject(callable $work,string $expected):void { $actual=null;try{$work();}catch(Throwable $error){$actual=$error->getMessage();}bb439Check($actual===$expected,'expected '.$expected.', got '.var_export($actual,true)); }
function bb439OneFlag(array $wire,string $flag):void { foreach($wire as $name=>$value)if(str_starts_with($name,'indicador'))bb439Check($value===($name===$flag?'S':'N'),'exact operational flag '.$name); }
bb439Check(BancoBrasil\ModuleRelease::VERSION==='1.1.9'&&BancoBrasil\ModuleRelease::MIGRATION===119&&BancoBrasil\ModuleRelease::MINIMUM_CORE==='4.3.9','native119 constants exact');
$origin=['payload_hash'=>hash('sha256','synthetic original')];$snapshot=['api_stage'=>'homologation','profile_hash'=>hash('sha256','synthetic profile'),'accepted_contract'=>'1234'];
$ack=['numeroContratoCobranca'=>'1234','dataAtualizacao'=>'20.10.2026','horarioAtualizacao'=>'14:21:05'];
$abat=['action'=>'include','amount'=>'10.1','contract_confirmed'=>'1','reference'=>' Synthetic independent contract ','reason'=>' Synthetic operational adjustment '];
$abatCanonical=ApiAbatement::input($abat);
bb439Check($abatCanonical['amount']==='10.10'&&$abatCanonical['reference']==='Synthetic independent contract','abatement canonical money and evidence');
foreach(['include'=>'indicadorIncluirAbatimento','alter'=>'indicadorAlterarAbatimento'] as $action=>$flag){$input=array_replace($abat,['action'=>$action]);$bytes=ApiAbatement::payload('1234567',$input);$wire=ApiClient::decodeExact($bytes);bb439OneFlag($wire,$flag);$section=$action==='include'?'abatimento':'alteracaoAbatimento';$field=$action==='include'?'valorAbatimento':'novoValorAbatimento';bb439Check($wire['numeroConvenio']==='1234567'&&$wire[$section][$field]==='10.10'&&str_contains($bytes,'"'.$field.'":10.10'),'abatement exact numeric leaf and agreement');}
foreach(['0','-1.00','1e2','1,00','01.00',' 1.00','1.001',1,1.1,null] as $value)bb439Reject(fn()=>ApiAbatement::input(array_replace($abat,['amount'=>$value])),'bb_api_abatement_invalid');
foreach([['action'=>'remove'],['contract_confirmed'=>'0'],['extra'=>'x']] as $bad)bb439Reject(fn()=>ApiAbatement::input(array_replace($abat,$bad)),'bb_api_abatement_contract_required');
foreach(['reference','reason'] as $field)foreach(['',"bad\nvalue",str_repeat('x',$field==='reference'?192:501)] as $bad)bb439Reject(fn()=>ApiAbatement::input(array_replace($abat,[$field=>$bad])),'bb_api_abatement_invalid');
bb439Check(ApiAbatement::observed([])===null&&ApiAbatement::observed(['valorAbatimentoTituloCobranca'=>'0'])==='0.00','missing observation never invents a received reduction');
bb439Reject(fn()=>ApiAbatement::observed(['valorAbatimentoTituloCobranca'=>'bad']),'bb_api_abatement_query_requires_review');
$abatFp=ApiAbatement::fingerprint($origin,$snapshot,$abat);bb439Check($abatFp===ApiAbatement::fingerprint($origin,$snapshot,$abatCanonical),'equivalent abatement reuses immutable fingerprint');
bb439Check($abatFp!==ApiAbatement::fingerprint($origin,array_replace($snapshot,['profile_hash'=>hash('sha256','different profile')]),$abat),'profile rotation changes abatement fingerprint');
bb439Check(ApiAbatement::acknowledged($ack,$snapshot,$abat,$abatFp)['bank_state']==='abatement_requested','PATCH acknowledgement is request only');
bb439Reject(fn()=>ApiAbatement::acknowledged(array_replace($ack,['numeroContratoCobranca'=>'9999']),$snapshot,$abat,$abatFp),'bb_api_abatement_response_invalid');

$address=['address'=>'Rua Sintetica 10','district'=>'Centro','city'=>'Salvador','state'=>'BA','zip'=>'04000000','contract_confirmed'=>'1','reference'=>'Synthetic address contract','reason'=>'Synthetic address change'];
$addressCanonical=ApiAddress::input($address);$addressWire=ApiClient::decodeExact(ApiAddress::payload('1234567',$address));bb439OneFlag($addressWire,'indicadorAlterarEnderecoPagador');
bb439Check($addressWire['alteracaoEndereco']===['enderecoPagador'=>'Rua Sintetica 10','bairroPagador'=>'Centro','cidadePagador'=>'Salvador','UFPagador'=>'BA','CEPPagador'=>'4000000'],'address payload preserves official field names and numeric ZIP');
$addressDetail=['textoEnderecoSacadoCobranca'=>'Rua Sintetica 10','nomeBairroSacadoCobranca'=>'Centro','nomeMunicipioSacadoCobranca'=>'Salvador','siglaUnidadeFederacaoSacadoCobranca'=>'BA','numeroCepSacadoCobranca'=>'4000000'];
bb439Check(ApiAddress::observed($addressDetail)===array_intersect_key($addressCanonical,array_flip(['address','district','city','state','zip'])),'account detail restores ZIP leading zero only with exact complete address');
bb439Check(ApiAddress::observed(array_diff_key($addressDetail,['siglaUnidadeFederacaoSacadoCobranca'=>true]))===null,'partial address cannot confirm');
foreach([['address'=>' bad'],['district'=>''],['city'=>"bad\ncity"],['state'=>'ZZ'],['state'=>'ba'],['zip'=>'00000000'],['zip'=>'4000-000'],['zip'=>4000000],['address'=>str_repeat('x',61)]] as $bad)bb439Reject(fn()=>ApiAddress::input(array_replace($address,$bad)),'bb_api_address_invalid');
bb439Reject(fn()=>ApiAddress::input(array_replace($address,['contract_confirmed'=>true])),'bb_api_address_contract_required');
$addressFp=ApiAddress::fingerprint($origin,$snapshot,$address);bb439Check(ApiAddress::acknowledged($ack,$snapshot,$address,$addressFp)['bank_state']==='address_requested','address ACK never claims confirmed fiscal address');
bb439Reject(fn()=>ApiAddress::acknowledged(array_replace($ack,['horarioAtualizacao'=>'24:00:00']),$snapshot,$address,$addressFp),'bb_api_address_response_invalid');

$discount=['type'=>'1','value'=>'10.1','until'=>'2026-11-10','contract_confirmed'=>'1','reference'=>'Synthetic discount contract','reason'=>'Synthetic discount adjustment'];
foreach(['1'=>'10.10','2'=>'10.10'] as $type=>$expected){$input=array_replace($discount,['type'=>(string)$type]);$bytes=ApiDiscountAmendment::payload('1234567',$input);$wire=ApiClient::decodeExact($bytes);bb439OneFlag($wire,'indicadorAlterarDesconto');$field=(string)$type==='1'?'novoValorPrimeiroDesconto':'novoPercentualPrimeiroDesconto';bb439Check($wire['alteracaoDesconto'][$field]===$expected&&$wire['alteracaoDesconto']['novaDataLimitePrimeiroDesconto']==='10.11.2026'&&str_contains($bytes,'"'.$field.'":'.$expected),'first-discount precise operational PATCH');}
foreach([['type'=>'0'],['type'=>1],['contract_confirmed'=>'0'],['extra'=>'x']] as $bad)bb439Reject(fn()=>ApiDiscountAmendment::input(array_replace($discount,$bad)),'bb_api_discount_amend_contract_required');
foreach(['0','-1','1.001','NaN',1,null] as $value)bb439Reject(fn()=>ApiDiscountAmendment::input(array_replace($discount,['value'=>$value])),'bb_api_discount_invalid');
$discountDetail=['codigoDescontoTitulo'=>'1','valorDescontoTitulo'=>'10.10','percentualDescontoTitulo'=>'0','dataDescontoTitulo'=>'10.11.2026','codigoSegundoDescontoTitulo'=>'0','valorSegundoDescontoTitulo'=>'0','percentualSegundoDescontoTitulo'=>'0','codigoTerceiroDescontoTitulo'=>'0','valorTerceiroDescontoTitulo'=>'0','percentualTerceiroDescontoTitulo'=>'0'];
bb439Check(ApiDiscountAmendment::matches($discountDetail,$discount),'exact first-tier detail confirms operational offer');
foreach([['valorDescontoTitulo'=>'10.11'],['codigoSegundoDescontoTitulo'=>'1'],['percentualTerceiroDescontoTitulo'=>'1'],['dataDescontoTitulo'=>'11.11.2026']] as $bad)bb439Check(!ApiDiscountAmendment::matches(array_replace($discountDetail,$bad),$discount),'different amount/tier/date cannot confirm offer');
bb439Check(!ApiDiscountAmendment::matches(array_diff_key($discountDetail,['codigoTerceiroDescontoTitulo'=>true]),$discount),'missing third-tier evidence cannot confirm');
$projection=BancoBrasil\ApiFixedDiscount::projection(['desconto'=>ApiDiscountAmendment::offer($discount)])+['bank_discount_offer_hash'=>ApiDiscountAmendment::hash($discount),'bank_discount_type'=>'1'];
bb439Check(ApiDiscountAmendment::projectedMatches($projection,$discount),'exact projected offer hash matches');bb439Check(!ApiDiscountAmendment::projectedMatches(array_replace($projection,['bank_discount_offer_hash'=>str_repeat('0',64)]),$discount),'tampered projection hash denied');
$discountFp=ApiDiscountAmendment::fingerprint($origin,$snapshot,$discount);bb439Check(ApiDiscountAmendment::acknowledged($ack,$snapshot,$discount,$discountFp)['bank_state']==='discount_requested','discount ACK remains unconfirmed request');
foreach([ApiAbatement::class,ApiAddress::class,ApiDiscountAmendment::class] as $class)bb439Reject(fn()=>$class::payload('123456',$class===ApiAbatement::class?$abat:($class===ApiAddress::class?$address:$discount)),'bb_api_origin_invalid');

$credentials=['client_id'=>'synthetic-id','client_secret'=>'synthetic-secret','application_key'=>'synthetic-key'];$calls=[];
$client=new ApiClient(static function(string $method,string $url,array $headers,string $body)use(&$calls):array{$calls[]=compact('method','url','headers','body');return str_contains($url,'oauth/token')?['status'=>200,'body'=>'{"access_token":"synthetic-token","token_type":"Bearer","expires_in":600}']:['status'=>200,'body'=>'{"numeroContratoCobranca":1234,"dataAtualizacao":"20.10.2026","horarioAtualizacao":"14:21:05"}'];});
foreach(['amend_abat'=>ApiAbatement::payload('1234567',$abat),'amend_addr'=>ApiAddress::payload('1234567',$address),'amend_disc'=>ApiDiscountAmendment::payload('1234567',$discount)] as $operation=>$body){$token=$client->token('homologation',$credentials,$operation);parse_str(end($calls)['body'],$form);bb439Check($form['scope']==='cobrancas.boletos-requisicao','new instruction requests exact write scope');$response=$client->execute('homologation',$credentials,$operation,'00012345670000000100','1234567',$token,$body);$last=end($calls);bb439Check($last['method']==='PATCH'&&str_starts_with($last['url'],'https://api.hm.bb.com.br/cobrancas/v2/boletos/00012345670000000100?')&&$last['body']===$body&&$response['status']===200,'official immutable PATCH path and body');bb439Reject(fn()=>$client->execute('homologation',$credentials,$operation,'00012345670000000100','1234567',$token,str_replace('1234567','7654321',$body)),'bb_api_payload_invalid');}
$timeout=new ApiClient(static function(){throw new RuntimeException('sensitive synthetic detail');});bb439Reject(fn()=>$timeout->execute('homologation',$credentials,'amend_abat','00012345670000000100','1234567','synthetic-token',ApiAbatement::payload('1234567',$abat)),'bb_api_transport_unconfirmed');
bb439Check(count($calls)===6,'all new instructions used injected transport only');
echo 'BB439 reconstructed amendment contracts/protocol: '.$bb439Checks.' checks passed; transport injected, no banking homologation.'.PHP_EOL;
