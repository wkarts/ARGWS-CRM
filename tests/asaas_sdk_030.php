<?php
declare(strict_types=1);
/** Locked release dependency compatibility; real SDK/Guzzle with an offline handler. */
require __DIR__.'/../modules/asaas/vendor/autoload.php';
$checks=0;$check=function(bool $ok,string $message)use(&$checks){$checks++;if(!$ok)throw new RuntimeException($message);};
$check(Composer\InstalledVersions::getPrettyVersion('argws/asaas-sdk-php')==='v0.3.0','distributed SDK version');
$check(Composer\InstalledVersions::getReference('argws/asaas-sdk-php')==='d0ede713d67d4a94d4edde66c0ff19fa4e2a4a7c','distributed SDK immutable reference');
$map=require __DIR__.'/../modules/asaas/vendor/composer/autoload_classmap.php';
foreach($map as $class=>$path)$check(is_file($path),'classmap references missing file: '.$class);
foreach([Asaas\Sdk\Http\Environment::Sandbox,Asaas\Sdk\Http\Environment::Production] as $environment){
 $history=[];$stack=GuzzleHttp\HandlerStack::create(new GuzzleHttp\Handler\MockHandler([new GuzzleHttp\Psr7\Response(200,['Content-Type'=>'application/json'],'{"data":[],"hasMore":false,"totalCount":0}') ]));
 $stack->push(GuzzleHttp\Middleware::history($history));$transport=new GuzzleHttp\Client(['handler'=>$stack,'base_uri'=>$environment->value,'allow_redirects'=>false]);
 $client=new Asaas\Sdk\Http\Client('synthetic-sdk-compatibility',$environment,'OfflineCompatibility',1.0,1.0,null,$transport);
 $service=new Asaas\Sdk\Service\AutomaticPixService($client);$response=$service->listAutomaticPixAuthorizations(pathParams:[],query:['limit'=>10,'offset'=>0],payload:null);
 $check($response['data']===[]&&count($history)===1,'read-only response and single transport dispatch');$request=$history[0]['request'];
 $check($request->getMethod()==='GET'&&(string)$request->getBody()==='','authorization listing is a bodyless GET');
 $check($request->getUri()->getPath()==='/v3/pix/automatic/authorizations','fixed API path');
 $check($request->getUri()->getHost()===parse_url($environment->value,PHP_URL_HOST),'environment cannot fall back to another host');
 $check($request->getHeaderLine('access_token')==='synthetic-sdk-compatibility','credential passed only through transport header');
 parse_str($request->getUri()->getQuery(),$query);$check($query===['limit'=>'10','offset'=>'0'],'named query compatibility');
}
echo "SDK0.3.0 compatibility: {$checks} assertions; no external requests or feature activation.\n";
