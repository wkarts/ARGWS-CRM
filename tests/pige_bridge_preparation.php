<?php
require __DIR__.'/pige_bridge_discovery.php';
$start=$checks;$remote=null;$puts=0;$mode='normal';$active=true;$secret=str_repeat('synthetic-hmac-',3);
$scope=['school_id'=>$school,'issuer_id'=>'2','account_id'=>'account:2','environment'=>'sandbox'];
$prepare=new PigeBridgeDiscovery(function()use(&$active){if(!$active)throw new DomainException('authority_revoked');},function($url,$credential,$host,$ip,$method,$payload)use(&$remote,&$puts,&$mode,&$active,$secret,$jwt){
 ok($credential===$jwt&&$credential!==$secret,'JWT authorization is distinct from transferred HMAC');
 if($method==='GET'){
  if($mode==='revoke_get')$active=false;
  return ['status'=>200,'body'=>json_encode(['configuration'=>$remote])];
 }
 $puts++;ok($method==='PUT'&&$payload['version']===null&&$payload['enabled']===false&&$payload['secret']===$secret,'new remote connection is disabled with absent expected revision');
 ok(array_keys($payload)===['version','enabled','base_url','issuer_id','account_id','environment','secret'],'verified bilateral fields only, no financial grants');
 $remote=['id'=>'server-generated-id','version'=>1,'enabled'=>false,'base_url'=>$payload['base_url'],'issuer_id'=>$payload['issuer_id'],'account_id'=>$payload['account_id'],'environment'=>$payload['environment'],'secret_configured'=>true];
 if($mode==='timeout')throw new RuntimeException('must not leak secret '.$secret);
 if($mode==='conflict')return ['status'=>409,'body'=>'private conflict'];
 if($mode==='revoke_put')$active=false;
 $out=$remote;if($mode==='enabled')$out['enabled']=true;
 return ['status'=>200,'body'=>json_encode($out)];
},fn()=>['8.8.8.8']);
$result=$prepare->prepareRemote('https://pige.example',$jwt,$scope,$secret,'https://crm.example');
ok($result===['state'=>'prepared_disabled','remote_revision'=>1,'authenticated'=>false],'preparation never claims HMAC authentication');ok(!str_contains(json_encode($result),$secret),'secret never returned to browser');
denied(fn()=>$prepare->prepareRemote('https://pige.example',$jwt,$scope,$secret,'https://crm.example'),'pige_preparation_already_configured');ok($puts===1,'repeat never overwrites an existing remote connection');
foreach(['timeout'=>'pige_discovery_transport','conflict'=>'pige_discovery_response','enabled'=>'pige_preparation_unconfirmed','revoke_put'=>'authority_revoked'] as $failure=>$code){$remote=null;$active=true;$mode=$failure;$before=$puts;denied(fn()=>$prepare->prepareRemote('https://pige.example',$jwt,$scope,$secret,'https://crm.example'),$code);ok($puts===$before+1,'no retry of failed or ambiguous PUT');}
$remote=null;$active=true;$mode='revoke_get';$before=$puts;denied(fn()=>$prepare->prepareRemote('https://pige.example',$jwt,$scope,$secret,'https://crm.example'),'authority_revoked');ok($puts===$before,'revocation after preview prevents secret transfer');
$active=true;$mode='normal';denied(fn()=>$prepare->prepareRemote('http://pige.example',$jwt,$scope,$secret,'https://crm.example'),'pige_discovery_destination');
echo 'PIGE remote preparation: '.($checks-$start).' additional contract checks; transport simulated.'.PHP_EOL;
