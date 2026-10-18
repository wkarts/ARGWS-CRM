<?php
require_once __DIR__ . '/../modules/pige_bridge/libraries/PigeBridgeProtocol.php';
$checks = 0;
function check($condition, $message) { global $checks; $checks++; if (!$condition) throw new RuntimeException($message); }
function rejects(callable $fn, string $message) { try { $fn(); } catch (Throwable $e) { check(true, $message); return; } check(false, $message); }
$event = [
 'schema_version'=>1, 'event_id'=>'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'event_type'=>'charge.requested',
 'scope'=>['school_id'=>'11111111-1111-4111-8111-111111111111','issuer_id'=>'7','account_id'=>'wallet_test','environment'=>'sandbox'],
 'data'=>['charge_id'=>'22222222-2222-4222-8222-222222222222','amount_cents'=>12345,'currency'=>'BRL','due_date'=>'2028-02-29','description'=>'Mensalidade de teste'],
];
$body = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$secret = str_repeat('test-only-', 4);
$connections = ['school-test'=>['enabled'=>true,'scope'=>$event['scope'],'secret'=>$secret]];
$headers = ['timestamp'=>'1800000000','event_id'=>$event['event_id'],'signature'=>PigeBridgeProtocol::signature($secret,'1800000000',$event['event_id'],'POST',PigeBridgeProtocol::REQUEST_PATH,$body)];
check(PigeBridgeProtocol::decode($body) === $event, 'decode exact contract');
check(PigeBridgeProtocol::authenticate($event,$body,$headers,$connections,1800000000) === 'school-test','valid signature');
foreach ([-300,300] as $delta) check(PigeBridgeProtocol::authenticate($event,$body,$headers,$connections,1800000000+$delta)==='school-test','clock boundary');
foreach ([-301,301] as $delta) rejects(fn()=>PigeBridgeProtocol::authenticate($event,$body,$headers,$connections,1800000000+$delta),'expired/future rejected');
foreach (['timestamp'=>'01800000000','event_id'=>'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb','signature'=>'sha256='.str_repeat('0',64)] as $key=>$value) {
 $changed=$headers; $changed[$key]=$value; rejects(fn()=>PigeBridgeProtocol::authenticate($event,$body,$changed,$connections,1800000000),'header tamper');
}
foreach (['GET','POST'] as $method) {
 $changed=$headers; $changed['signature']=PigeBridgeProtocol::signature($secret,$headers['timestamp'],$event['event_id'],$method,'/wrong-path',$body);
 rejects(fn()=>PigeBridgeProtocol::authenticate($event,$body,$changed,$connections,1800000000),'method/path substitution');
}
rejects(fn()=>PigeBridgeProtocol::authenticate($event,$body.' ',$headers,$connections,1800000000),'raw body tamper');
rejects(fn()=>PigeBridgeProtocol::authenticate($event,$body,$headers,[],1800000000),'no fallback configuration');
$duplicate=$connections; $duplicate['ambiguous']=$connections['school-test'];
rejects(fn()=>PigeBridgeProtocol::authenticate($event,$body,$headers,$duplicate,1800000000),'ambiguous scopes fail closed');
foreach (['school_id','issuer_id','account_id','environment'] as $field) {
 $changed=$connections; $changed['school-test']['scope'][$field]='other';
 rejects(fn()=>PigeBridgeProtocol::authenticate($event,$body,$headers,$changed,1800000000),'cross scope '.$field);
}
foreach ([false,0,1,'true'] as $enabled) {
 $changed=$connections; $changed['school-test']['enabled']=$enabled;
 rejects(fn()=>PigeBridgeProtocol::authenticate($event,$body,$headers,$changed,1800000000),'strict enabled bool');
}
foreach ([null,'short'] as $key) {
 $changed=$connections; $changed['school-test']['secret']=$key;
 rejects(fn()=>PigeBridgeProtocol::authenticate($event,$body,$headers,$changed,1800000000),'weak configuration');
}
foreach (['amount_cents'=>[0,-1,1.5,'100',1000000000000], 'due_date'=>['2027-02-29','2028-2-29','2028-04-31'], 'currency'=>['USD'], 'charge_id'=>['7','../../x'], 'description'=>['',str_repeat('x',501),"bad\0text"]] as $field=>$values) {
 foreach ($values as $value) { $changed=$event; $changed['data'][$field]=$value; rejects(fn()=>PigeBridgeProtocol::decode(json_encode($changed)),'invalid '.$field); }
}
foreach (['school_id'=>['7','',strtoupper($event['event_id'])], 'issuer_id'=>['0',7,'07','-1'], 'environment'=>['live','SANDBOX'], 'account_id'=>['','a/b',str_repeat('x',129)]] as $field=>$values) {
 foreach ($values as $value) { $changed=$event; $changed['scope'][$field]=$value; rejects(fn()=>PigeBridgeProtocol::decode(json_encode($changed)),'invalid scope '.$field); }
}
rejects(fn()=>PigeBridgeProtocol::decode(str_repeat(' ',16385)),'oversized body');
rejects(fn()=>PigeBridgeProtocol::decode('{'),'malformed JSON');
rejects(fn()=>PigeBridgeProtocol::decode($body.' '),'canonical body required');
rejects(fn()=>PigeBridgeProtocol::decode(str_replace('{"schema_version":1', '{"schema_version":1,"schema_version":1', $body)),'duplicate key rejected');
foreach (['confirmed','received','pending','cancelled'] as $state) {
 $receipt=PigeBridgeProtocol::receipt(['event_id'=>$event['event_id'],'request_id'=>'req_test','status'=>$state]);
 check($receipt['status']==='blocked' && $receipt['capabilities']['issue_charge']===false,'no accidental financial execution');
}
foreach (['你好', str_repeat('😀', 500), "line\u{2028}separator"] as $description) {
 $changed=$event;$changed['data']['description']=$description;
 check(PigeBridgeProtocol::decode(json_encode($changed, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_LINE_TERMINATORS))['data']['description']===$description,'Unicode cross-language accepted');
}
foreach (["\u{00a0}", "\u{2003}", "\u{0085}"] as $description) {
 $changed=$event;$changed['data']['description']=$description;
 rejects(fn()=>PigeBridgeProtocol::decode(json_encode($changed, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_LINE_TERMINATORS)),'Unicode whitespace rejected like Python');
}
$fixturePath=__DIR__.'/fixtures/pige-crm-v1.json';
if (is_file($fixturePath)) {
 $fixture=json_decode(file_get_contents($fixturePath),true,512,JSON_THROW_ON_ERROR);
 check(PigeBridgeProtocol::signature($fixture['secret'],$fixture['timestamp'],$fixture['event_id'],($fixture['method'] ?? 'POST'),$fixture['path'],$fixture['raw_body'])===$fixture['signature'],'shared Python/PHP HMAC fixture');
 check(PigeBridgeProtocol::decode($fixture['raw_body'])['event_id']===$fixture['event_id'],'shared envelope');
}
echo "PIGE bridge protocol: {$checks} checks passed\n";
