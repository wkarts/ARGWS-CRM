<?php
declare(strict_types=1);
require __DIR__.'/../modules/pige_bridge/libraries/PigeBridgeDiscovery.php';
$checks=0;
function ok(bool $value,string $label):void{global $checks;if(!$value)throw new RuntimeException($label);$checks++;}
function denied(callable $work,string $reason):void{try{$work();}catch(DomainException $error){ok($error->getMessage()===$reason,'bounded rejection '.$reason);return;}throw new RuntimeException('expected rejection '.$reason);}
$school='12345678-1234-1234-1234-123456789abc';$jwt='synthetic.header.signature';$calls=[];$authority=0;
$response=['status'=>200,'body'=>json_encode([['id'=>$school,'name'=>'Escola <literal>','active'=>true,'private_extra'=>'not returned']],JSON_THROW_ON_ERROR)];
$transport=function($url,$token,$host,$ip)use(&$calls,&$response,$jwt){ok($token===$jwt,'only explicitly supplied admin credential sent');$calls[]=[$url,$host,$ip];return $response;};
$service=new PigeBridgeDiscovery(function()use(&$authority){$authority++;},$transport,fn($host)=>['8.8.8.8']);
$rows=$service->schools('https://PIGE.example/',$jwt);ok($rows===[['id'=>$school,'name'=>'Escola <literal>']],'minimal school projection');ok($authority===2&&$calls===[['https://pige.example/api/v1/schools','pige.example','8.8.8.8']],'verified API path and pinned address; authority before/after');
foreach(['http://pige.example','https://localhost','https://127.0.0.1','https://[::1]','https://user@pige.example','https://pige.example:444','https://pige.example/api','https://pige.example/?token=x','https://pige.example/#fragment','https://2130706433','https://pige.example.']as$url)denied(fn()=>$service->schools($url,$jwt),'pige_discovery_destination');
foreach(['127.0.0.1','10.0.0.1','172.16.1.1','192.168.1.1','169.254.169.254','100.64.0.1','192.0.0.1','192.0.2.1','198.18.0.1','198.51.100.1','203.0.113.1','224.0.0.1','240.0.0.1','0.0.0.0','::1','::ffff:8.8.8.8']as$ip)ok(!PigeBridgeDiscovery::publicAddress($ip),'nonpublic address denied');
$before=count($calls);$mixed=new PigeBridgeDiscovery(fn()=>null,$transport,fn()=>['8.8.8.8','127.0.0.1']);denied(fn()=>$mixed->schools('https://pige.example',$jwt),'pige_discovery_destination');ok(count($calls)===$before,'mixed DNS rejected before transport');
denied(fn()=>$service->schools('https://pige.example',str_repeat('hmac-financial-',4)),'pige_discovery_admin_credential');denied(fn()=>$service->schools('https://pige.example',$jwt."\r\nX-Test: injected"),'pige_discovery_admin_credential');
foreach([401,403]as$status){$response=['status'=>$status,'body'=>'private remote error'];denied(fn()=>$service->schools('https://pige.example',$jwt),'pige_discovery_access');}
foreach([301,302,307,308,429,500]as$status){$response=['status'=>$status,'body'=>'private remote error'];denied(fn()=>$service->schools('https://pige.example',$jwt),'pige_discovery_response');}
foreach(['invalid',str_repeat('x',262145),'{}','[{"id":"invalid"}]']as$body){$response=['status'=>200,'body'=>$body];denied(fn()=>$service->schools('https://pige.example',$jwt),'pige_discovery_response');}
$remote=['id'=>'unrelated-remote-record','version'=>3,'enabled'=>true,'base_url'=>'https://crm.example/','issuer_id'=>'2','account_id'=>'account:2','environment'=>'sandbox','secret_configured'=>true,'secret'=>'must-never-return'];
$response=['status'=>200,'body'=>json_encode(['configuration'=>$remote])];$data=$service->configuration('https://pige.example',$jwt,$school,2,'https://crm.example');
ok($data===['school_id'=>$school,'issuer_id'=>'2','account_id'=>'account:2','environment'=>'sandbox','remote_revision'=>3,'secret_configured'=>true],'only public scope/revision returned; enabled/secret/id excluded');ok(end($calls)[0]==='https://pige.example/api/v1/schools/'.$school.'/crm-bridge/configuration','verified school configuration route');
denied(fn()=>$service->configuration('https://pige.example',$jwt,$school,3,'https://crm.example'),'pige_discovery_scope');denied(fn()=>$service->configuration('https://pige.example',$jwt,$school,2,'https://other-crm.example'),'pige_discovery_scope');
$response=['status'=>200,'body'=>'{"configuration":null}'];denied(fn()=>$service->configuration('https://pige.example',$jwt,$school,2,'https://crm.example'),'pige_discovery_not_configured');
$response=['status'=>200,'body'=>json_encode(['configuration'=>array_diff_key($remote,['account_id'=>true])])];denied(fn()=>$service->configuration('https://pige.example',$jwt,$school,2,'https://crm.example'),'pige_discovery_response');
$timeout=new PigeBridgeDiscovery(fn()=>null,fn()=>throw new RuntimeException('sensitive remote body'),fn()=>['8.8.8.8']);denied(fn()=>$timeout->schools('https://pige.example',$jwt),'pige_discovery_transport');
$active=true;$revoked=new PigeBridgeDiscovery(function()use(&$active){if(!$active)throw new DomainException('authority_revoked');},function()use(&$active){$active=false;return ['status'=>200,'body'=>'[]'];},fn()=>['8.8.8.8']);denied(fn()=>$revoked->schools('https://pige.example',$jwt),'authority_revoked');
echo "PIGE discovery: {$checks} contract/security checks passed; transport/DNS are explicit spies, no remote configuration or financial effects.\n";
