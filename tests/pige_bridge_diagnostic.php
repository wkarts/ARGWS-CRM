<?php
require __DIR__.'/pige_bridge_preparation.php';
$start=$checks;$calls=[];$mode='ok';$active=true;
$remote=['id'=>'remote-connection','version'=>3,'enabled'=>true,'secret_configured'=>true,'base_url'=>'https://crm.example','issuer_id'=>'2','account_id'=>'account:2','environment'=>'sandbox'];
$diagnostic=new PigeBridgeDiscovery(function()use(&$active){if(!$active)throw new DomainException('authority_revoked');},function($url,$credential,$host,$ip,$method,$payload)use(&$calls,&$mode,&$remote,&$active,$jwt){
 $calls[]=$method;ok($credential===$jwt,'only administrative JWT sent');
 if($method==='POST'){
  ok(str_ends_with($url,'/connection-test')&&$payload===[],'existing diagnostic route with no financial or credential payload');
  if($mode==='timeout')throw new RuntimeException('private remote failure');
  if($mode==='revoke')$active=false;
  if($mode==='change')$remote['version']++;
  return ['status'=>200,'body'=>json_encode(['reachable'=>true,'authenticated'=>$mode!=='bad_hmac','charge_created'=>$mode==='charge','message'=>'<script>remote</script>','capabilities'=>['grant'=>true]])];
 }
 return ['status'=>200,'body'=>json_encode(['configuration'=>$remote])];
},fn()=>['8.8.8.8']);
$result=$diagnostic->testConnection('https://pige.example',$jwt,$scope,'https://crm.example');
ok($result===['authenticated'=>true,'charge_created'=>false,'remote_revision'=>3]&&$calls===['GET','POST','GET'],'diagnostic confirms only stable existing scope with bounded result');
foreach(['enabled'=>false,'secret_configured'=>false,'issuer_id'=>'3','account_id'=>'foreign','environment'=>'production','base_url'=>'https://foreign.example'] as $field=>$value){$saved=$remote;$remote[$field]=$value;$calls=[];denied(fn()=>$diagnostic->testConnection('https://pige.example',$jwt,$scope,'https://crm.example'),'pige_diagnostic_scope');ok($calls===['GET'],'incompatible remote never receives diagnostic');$remote=$saved;}
foreach(['bad_hmac'=>'pige_diagnostic_unconfirmed','charge'=>'pige_diagnostic_unconfirmed','change'=>'pige_diagnostic_changed','timeout'=>'pige_discovery_transport','revoke'=>'authority_revoked']as$failure=>$error){$calls=[];$mode=$failure;$active=true;denied(fn()=>$diagnostic->testConnection('https://pige.example',$jwt,$scope,'https://crm.example'),$error);ok(count(array_filter($calls,fn($v)=>$v==='POST'))===1,'no diagnostic retry on failure');}
echo 'PIGE enabled diagnostic: '.($checks-$start).' additional contract checks; remote transport simulated.'.PHP_EOL;
