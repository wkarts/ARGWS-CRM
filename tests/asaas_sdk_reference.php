<?php
declare(strict_types=1);
require dirname(__DIR__).'/modules/asaas/vendor/autoload.php';
use Asaas\Sdk\Http\{Client,Environment,RetryMiddleware};
use Asaas\Sdk\Service\PaymentService;
use GuzzleHttp\{Client as Guzzle,HandlerStack,Middleware};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
function sdk156Check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);}
$root=dirname(__DIR__).'/modules/asaas/';$lock=json_decode(file_get_contents($root.'composer.lock'),true,512,JSON_THROW_ON_ERROR);
$package=array_values(array_filter($lock['packages'],fn($p)=>$p['name']==='argws/asaas-sdk-php'))[0];
sdk156Check($package['version']==='v0.3.0'&&$package['source']['reference']==='d0ede713d67d4a94d4edde66c0ff19fa4e2a4a7c','confirmed reference lock');
sdk156Check(Composer\InstalledVersions::getReference('argws/asaas-sdk-php')===$package['source']['reference'],'installed metadata matches lock');
sdk156Check(class_exists(Asaas\Sdk\Webhook\WebhookToken::class),'new webhook class autoloads');
foreach(['payWithCard','payWithCreditCard']as$operation){
 $history=[];$mock=new MockHandler([new Response(500,[],json_encode(['errors'=>[['description'=>'synthetic error']]])),new Response(200,[],'{}')]);
 $stack=HandlerStack::create($mock);$stack->push(RetryMiddleware::create(3,0));$stack->push(Middleware::history($history));
 $client=new Client('synthetic-test-key',Environment::Sandbox,'isolated-test',30,10,null,new Guzzle(['handler'=>$stack]));$payments=new PaymentService($client);
 $payload=$operation==='payWithCard'?['cardType'=>'CREDIT','cardToken'=>'synthetic-opaque-token']:['creditCardToken'=>'synthetic-opaque-token'];
 try{if($operation==='payWithCard')$payments->payAChargeWithCard(['id'=>'pay_synthetic'],[],[],$payload);else $payments->payAChargeWithCreditCard(['id'=>'pay_synthetic'],[],[],$payload);throw new RuntimeException('500 must not succeed');}catch(Asaas\Sdk\Exception\ApiException $expected){}
 sdk156Check(count($history)===1,'card500 never repeats capture');$request=$history[0]['request'];
 sdk156Check($request->getMethod()==='POST'&&$request->getUri()->getPath()==='/v3/payments/pay_synthetic/'.$operation,'exact source card endpoint');
 sdk156Check(json_decode((string)$request->getBody(),true)===$payload,'card contracts remain distinct');
}
echo "Confirmed SDK0.3.0 lock/autoload and two real Guzzle mock card transports: no retry on500; no external request.\n";
