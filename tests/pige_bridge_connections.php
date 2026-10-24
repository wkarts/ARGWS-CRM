<?php
/** Pure configuration/legacy fallback checks; no production database or secrets. */
require_once __DIR__.'/../modules/pige_bridge/libraries/PigeBridgeConnections.php';
$checks=0;function connectionOk($ok){global $checks;$checks++;if(!$ok)throw new RuntimeException('connection check '.$checks);}function connectionReject(callable $fn){try{$fn();}catch(Throwable $e){connectionOk(true);return;}connectionOk(false);}
$scope=['school_id'=>'11111111-1111-4111-8111-111111111111','issuer_id'=>'7','account_id'=>'synthetic-account','environment'=>'sandbox'];
connectionOk(PigeBridgeConnections::scope(array_reverse($scope,true))===$scope);
foreach([['issuer_id'=>7],['issuer_id'=>'01'],['issuer_id'=>'2147483648'],['school_id'=>'invalid'],['environment'=>'prod'],['account_id'=>"account\n"],['client_id'=>42]] as $change)connectionReject(fn()=>PigeBridgeConnections::scope(array_replace($scope,$change)));
$mapping=['enabled'=>true,'actor_id'=>7,'currency_id'=>1];connectionOk(PigeBridgeConnections::validateMapping($mapping)===$mapping);
foreach([['client_id'=>42],['enabled'=>'true'],['actor_id'=>'7'],['actor_id'=>0],['currency_id'=>true]] as $change)connectionReject(fn()=>PigeBridgeConnections::validateMapping(array_replace($mapping,$change)));
$db=new class {public function table_exists($table){return false;}public function query(...$args){throw new RuntimeException('unexpected database');}};
$cipher=new class {public function decrypt($value){throw new RuntimeException('unexpected decrypt');}};
$service=new PigeBridgeConnections($db,$cipher,'fixture_');$one=['enabled'=>true,'scope'=>$scope,'secret'=>str_repeat('synthetic-secret-',3)];
connectionOk($service->active(['school'=>$one])===['school'=>$one]);connectionOk($service->active([])===[]);
connectionReject(fn()=>$service->active(['school'=>$one,'duplicate'=>$one]));
$other=$one;$other['scope']['issuer_id']='8';connectionReject(fn()=>$service->active(['school'=>$one,'reused-secret'=>$other]));
$other=$one;$other['secret']=str_repeat('different-synthetic-',3);connectionReject(fn()=>$service->active(['school'=>$one,'reused-scope'=>$other]));
$other['scope']['issuer_id']='8';connectionOk(count($service->active(['school'=>$one,'other'=>$other]))===2);
$off=$one;$off['enabled']=false;connectionOk($service->active(['off'=>$off])===[]);
connectionReject(fn()=>$service->active(['off'=>$off,'other'=>$one])); // Revoked credentials cannot be silently recycled.
echo "PIGE connections: {$checks} pure checks, no database integration asserted\n";
