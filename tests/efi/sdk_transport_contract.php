<?php
/** Bundled SDK execution through its supported requester seam; never opens a socket. */
declare(strict_types=1);
error_reporting(E_ALL);
set_error_handler(static function($level,$message,$file,$line){throw new ErrorException($message,0,$level,$file,$line);});
define('BASEPATH',dirname(__DIR__,2).'/system/');
require dirname(__DIR__,2).'/modules/gerencianet_gateway/services/EfiSdkClient.php';
$checks=0;function sdkCheck($ok,$name){++$GLOBALS['checks'];if(!$ok)throw new RuntimeException($name);}
foreach(['Gerencianet\\ApiRequest','Gerencianet\\Auth','Gerencianet\\Request'] as $class)sdkCheck(class_exists($class),'real SDK class loads without warnings: '.$class);
$requester=new class {public array $calls=[];function send($method,$route,$body){$this->calls[]=[$method,$route,$body];return ['synthetic'=>true];}};
$options=['client_id'=>'synthetic-client','client_secret'=>'synthetic-secret','sandbox'=>true,'debug'=>false];
$sdk=new EfiSdkClient($options,$requester);
$body=['items'=>[['name'=>'Synthetic','amount'=>1,'value'=>1000]],'payment'=>['credit_card'=>['payment_token'=>'synthetic-token']]];
$sdk->createOneStepCharge([],$body);
$sdk->createCharge([],['items'=>$body['items']]);
$sdk->definePayMethod(['id'=>'987'],['payment'=>['banking_billet'=>['expire_at'=>'2099-01-01']]]);
$sdk->detailCharge(['id'=>'987']);
sdkCheck($requester->calls[0]===['post','/v1/charge/one-step',$body],'real one-step method and payload, not nonexistent oneStep alias');
sdkCheck($requester->calls[1][0]==='post'&&$requester->calls[1][1]==='/v1/charge','charge create route');
sdkCheck($requester->calls[2][0]==='post'&&$requester->calls[2][1]==='/v1/charge/987/pay','define payment route');
sdkCheck($requester->calls[3]===['get','/v1/charge/987',[]],'authenticated detail route');
$pix=new EfiSdkClient($options,$requester);$txid=str_repeat('a',26);
$pix->pixCreateImmediateCharge([],['valor'=>['original'=>'10.00']]);
$pix->pixDetailCharge(['txid'=>$txid]);$pix->pixGenerateQRCode(['id'=>'123']);
sdkCheck($requester->calls[4]===['post','/v2/cob',['valor'=>['original'=>'10.00']]],'Pix create route');
sdkCheck($requester->calls[5]===['get','/v2/cob/'.$txid,[]],'Pix detail route');
sdkCheck($requester->calls[6]===['get','/v2/loc/123/qrcode',[]],'Pix QR route');
sdkCheck(count($requester->calls)===7,'all seven operations use injected transport only');
$largeId='99999999999999999999';
$sdk->definePayMethod(['id'=>$largeId],['payment'=>['banking_billet'=>['expire_at'=>'2099-01-01']]]);
$sdk->detailCharge(['id'=>$largeId]);
sdkCheck($requester->calls[7][1]==='/v1/charge/'.$largeId.'/pay'&&$requester->calls[8][1]==='/v1/charge/'.$largeId,'real SDK preserves canonical decimal ID beyond PHP_INT_MAX in POST and GET routes');
sdkCheck(!str_contains($requester->calls[7][1],(string)PHP_INT_MAX)&&!str_contains($requester->calls[8][1],(string)PHP_INT_MAX),'zero dispatch to saturated divergent identifier');

// Exercise the actual bundled ApiRequest retry branch, with only its transport/auth replaced.
$reflection=new ReflectionClass(\Gerencianet\ApiRequest::class);
foreach(['post','put','patch','delete','get'] as $method) {
 $request=$reflection->newInstanceWithoutConstructor();
 $auth=new class {public int $expires=PHP_INT_MAX,$refreshes=0;public string $accessToken='synthetic';function authorize(){++$this->refreshes;$this->accessToken='refreshed-synthetic';}};
 $transport=new class {public int $calls=0;function send($method,$route,$options){if(++$this->calls===1)throw new \Gerencianet\Exception\AuthorizationException(401,'synthetic');if($options['headers']['Authorization']!=='Bearer refreshed-synthetic')throw new RuntimeException('stale auth header');return ['ok'=>true];}};
 foreach(['auth'=>$auth,'request'=>$transport,'options'=>['timeout'=>30]] as $property=>$value){$reflection->getProperty($property)->setValue($request,$value);}
 $failed=false;try{$request->send($method,'/synthetic',[]);}catch(\Gerencianet\Exception\AuthorizationException $error){$failed=true;}
 sdkCheck($method==='get'?(!$failed&&$transport->calls===2&&$auth->refreshes===1):($failed&&$transport->calls===1&&$auth->refreshes===0),'mutation never automatically replayed: '.$method);
}
echo "Efí bundled SDK fake requester: {$checks} checks PASS; no HTTP/provider call.\n";
