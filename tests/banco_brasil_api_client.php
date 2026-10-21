<?php
/** Contract-level simulated HTTP. Never contacts the bank or obtains real tokens. */
declare(strict_types=1);
require_once __DIR__.'/../modules/banco_brasil/libraries/BancoBrasil/ApiClient.php';
$n=0;function apiCheck($value,string $label):void{global $n;$n++;if(!$value)throw new RuntimeException($label);}
function apiReject(callable $work,string $message):void{$seen=null;try{$work();}catch(Throwable $e){$seen=$e->getMessage();}apiCheck($seen===$message,'expected '.$message.', got '.var_export($seen,true));}
$credentials=['client_id'=>'synthetic+id','client_secret'=>'synthetic:secret','application_key'=>'synthetic-key'];$calls=[];
$client=new \BancoBrasil\ApiClient(static function($method,$url,$headers,$body)use(&$calls):array{
    $calls[]=compact('method','url','headers','body');
    if(str_contains($url,'oauth/token'))return ['status'=>200,'body'=>'{"access_token":"synthetic-token","token_type":"Bearer","expires_in":600}'];
    return ['status'=>200,'body'=>'{"valor":9999999999999.99,"id":999999999999999999999,"texto":"123","boolean":true}'];
});
$token=$client->token('homologation',$credentials,'query');apiCheck($token==='synthetic-token','valid token returned only to internal caller');
apiCheck($calls[0]['url']==='https://oauth.hm.bb.com.br/oauth/token','homologation token server follows official spec');
parse_str($calls[0]['body'],$form);apiCheck($form===['grant_type'=>'client_credentials','scope'=>'cobrancas.boletos-info'],'query requests least scope');
apiCheck(in_array('Authorization: Basic '.base64_encode('synthetic%2Bid:synthetic%3Asecret'),$calls[0]['headers'],true),'OAuth client credentials form encoding before Basic per RFC6749');
$r=$client->execute('homologation',$credentials,'query','00012345670000000100','1234567',$token);
apiCheck($calls[1]['method']==='GET'&&$calls[1]['body']===''&&str_starts_with($calls[1]['url'],'https://api.hm.bb.com.br/cobrancas/v2/boletos/00012345670000000100?'),'query uses immutable supplied number and fixed target');
parse_str(parse_url($calls[1]['url'],PHP_URL_QUERY),$query);apiCheck($query===['gw-dev-app-key'=>'synthetic-key','numeroConvenio'=>'1234567'],'AppKey and agreement use official query contract');
apiCheck($r['data']['valor']==='9999999999999.99'&&$r['data']['id']==='999999999999999999999'&&$r['data']['boolean']===true,'money and identifiers never lose decimal precision');
apiReject(fn()=>\BancoBrasil\ApiClient::decodeExact('{"id":1,"id":2}'),'bb_api_duplicate_key');
apiReject(fn()=>\BancoBrasil\ApiClient::decodeExact('{"n":{1:2}}'),'Syntax error');
apiReject(fn()=>$client->token('sandbox',$credentials,'invented_operation'),'bb_api_operation_invalid');
apiReject(fn()=>$client->execute('production',$credentials,'query','00099999990000000100','1234567',$token),'bb_api_origin_invalid');
apiReject(fn()=>\BancoBrasil\ApiClient::curl('POST','https://api.bb.com.br.evil.invalid/cobrancas/v2/boletos',[],''),'bb_api_transport_target_denied');
apiReject(fn()=>\BancoBrasil\ApiClient::curl('POST','http://api.bb.com.br/cobrancas/v2/boletos',[],''),'bb_api_transport_target_denied');
$broken=new \BancoBrasil\ApiClient(static fn()=>['status'=>503,'body'=>'synthetic client_secret and bearer must never reach an exception']);
try{$broken->execute('sandbox',$credentials,'cancel','00012345670000000100','1234567',$token);throw new LogicException('failure expected');}catch(\BancoBrasil\ApiFailure $e){apiCheck($e->uncertain&&$e->httpStatus===503&&$e->getMessage()==='bb_api_provider_unconfirmed','5xx write result is uncertain and raw diagnostic is redacted');}
$redirect=new \BancoBrasil\ApiClient(static fn()=>['status'=>302,'body'=>'https://evil.invalid']);
try{$redirect->execute('sandbox',$credentials,'cancel','00012345670000000100','1234567',$token);throw new LogicException('failure expected');}catch(\BancoBrasil\ApiFailure $e){apiCheck($e->uncertain&&$e->httpStatus===302,'redirect is never treated as bank success');}
$wrongToken=new \BancoBrasil\ApiClient(static fn()=>['status'=>200,'body'=>'{"access_token":"bad token\n","token_type":"Bearer","expires_in":600}']);
apiReject(fn()=>$wrongToken->token('sandbox',$credentials,'issue'),'bb_api_authentication_response_invalid');
$notJson=new \BancoBrasil\ApiClient(static fn()=>['status'=>200,'body'=>'<html>failure</html>']);
apiReject(fn()=>$notJson->execute('sandbox',$credentials,'query','00012345670000000100','1234567',$token),'bb_api_response_invalid');
$timeout=new \BancoBrasil\ApiClient(static function(){throw new RuntimeException('synthetic secret/url diagnostic');});
apiReject(fn()=>$timeout->token('sandbox',$credentials,'cancel'),'bb_api_transport_unconfirmed');
// Real account-filter construction and exact-match proof; the provider remains simulated.
define('BASEPATH',dirname(__DIR__).'/system/');
require_once __DIR__.'/../application/services/InvoicePaymentWriter.php';
require_once __DIR__.'/../modules/banco_brasil/libraries/BancoBrasil/ApiPayload.php';
$agreement=new \BancoBrasil\Agreement(new \BancoBrasil\Scope(2,1,'001:00452-0:000000123873-0','sandbox','1234567'),'11222333000181','SYNTHETIC BENEFICIARY','019');
$snapshot=['document'=>['recipient'=>['tax_id'=>'11144477735']],'invoice'=>['duedate'=>'2026-11-19','total'=>'105.53']];
$detail=['codigoEstadoTituloCobranca'=>'1','numeroContratoCobranca'=>'1234'];$listCalls=[];
$found=['numeroBoletoBB'=>'00012345670000000100','dataVencimento'=>'19.11.2026','valorOriginal'=>105.53,'carteiraConvenio'=>17,'variacaoCarteiraConvenio'=>19,'contrato'=>1234,'codigoEstadoTituloCobranca'=>1];
$list=new \BancoBrasil\ApiClient(static function($method,$url)use(&$listCalls,$found){$listCalls[]=$url;return ['status'=>200,'body'=>json_encode(count($listCalls)===1?['indicadorContinuidade'=>'s','proximoIndice'=>300,'boletos'=>[]]:[['indicadorContinuidade'=>'N','boletos'=>[$found]]],JSON_THROW_ON_ERROR)];});
$proof=$list->confirmAccount('homologation',$credentials,$agreement,$found['numeroBoletoBB'],$token,$snapshot,$detail);
apiCheck($proof['account_scope_confirmed']==='1'&&strlen($proof['account_evidence_hash'])===64&&count($listCalls)===2,'account-bound list paginates and confirms only the exact number');
parse_str(parse_url($listCalls[1],PHP_URL_QUERY),$filters);apiCheck($filters['agenciaBeneficiario']==='452'&&$filters['contaBeneficiario']==='123873'&&$filters['cpfPagador']==='111444777'&&$filters['digitoCPFPagador']==='35'&&$filters['indice']==='300','query uses immutable account, payer and progressive official cursor');
$missing=new \BancoBrasil\ApiClient(static fn()=>['status'=>200,'body'=>'{"indicadorContinuidade":"N","boletos":[]}']);
apiReject(fn()=>$missing->confirmAccount('sandbox',$credentials,$agreement,$found['numeroBoletoBB'],$token,$snapshot,$detail),'bb_api_account_scope_unconfirmed');
$repeated=new \BancoBrasil\ApiClient(static fn()=>['status'=>200,'body'=>'{"indicadorContinuidade":"S","proximoIndice":300,"boletos":[]}']);
apiReject(fn()=>$repeated->confirmAccount('sandbox',$credentials,$agreement,$found['numeroBoletoBB'],$token,$snapshot,$detail),'bb_api_account_scope_unconfirmed');
$wrong=new \BancoBrasil\ApiClient(static fn()=>['status'=>200,'body'=>json_encode(['boletos'=>[array_replace($found,['contrato'=>999])]],JSON_THROW_ON_ERROR)]);
apiReject(fn()=>$wrong->confirmAccount('sandbox',$credentials,$agreement,$found['numeroBoletoBB'],$token,$snapshot,$detail),'bb_api_account_scope_unconfirmed');
$badAmount=new \BancoBrasil\ApiClient(static fn()=>['status'=>200,'body'=>json_encode(['boletos'=>[array_replace($found,['valorOriginal'=>105.54])]],JSON_THROW_ON_ERROR)]);
apiReject(fn()=>$badAmount->confirmAccount('sandbox',$credentials,$agreement,$found['numeroBoletoBB'],$token,$snapshot,$detail),'bb_api_account_scope_unconfirmed');
echo "BB API client: {$n} simulated HTTP/exact JSON checks; no external requests.\n";
