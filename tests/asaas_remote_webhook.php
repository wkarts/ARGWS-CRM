<?php
declare(strict_types=1);
require __DIR__.'/../modules/asaas/libraries/Asaas/RemoteWebhook.php';
require __DIR__.'/../modules/asaas/libraries/Asaas/CompanyClient.php';
function check($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function rejects($fn,$code){try{$fn();}catch(Throwable $e){check($e->getMessage()===$code,$code);return;}throw new RuntimeException('missing rejection '.$code);}
$url='https://crm.example.test/subdir/index.php/company_asaas_callback/integration/'.str_repeat('a',32);
$token=str_repeat('b',64);$rows=[['id'=>'foreign','url'=>'https://other.example.test/webhook']];$calls=[];$fail=false;
$transport=function($method,$path,$payload)use(&$rows,&$calls,&$fail){
    $calls[]=[$method,$path,$payload];
    if($method==='GET'){
        // Put the owned webhook on page two to expose first-row and first-page mistakes.
        return str_ends_with($path,'offset=0')?['data'=>array_slice($rows,0,1),'hasMore'=>count($rows)>1]:['data'=>array_slice($rows,1),'hasMore'=>false];
    }
    if($fail)throw new RuntimeException('synthetic timeout with secret');
    if($method==='POST'){$row=$payload+['id'=>'hook_own'];$rows[]=$row;return $row;}
    check($path==='webhooks/hook_own','mutation targets only exact owned destination');
    if($method==='DELETE'){$rows=array_slice($rows,0,1);return ['id'=>'hook_own','deleted'=>true];}
    $rows[1]=array_replace($rows[1],$payload);return $rows[1];
};
$api=new Asaas\RemoteWebhook($transport,$url,$token);
$input=['name'=>'CRM','email'=>'ops@example.test','events'=>['PAYMENT_RECEIVED','PAYMENT_CONFIRMED'],'enabled'=>'1'];
check($api->execute('inspect')['webhook']===null,'unrelated receiver is never selected');
$created=$api->execute('create',$input);check($created['webhook']['id']==='hook_own','create works with unrelated webhook already present');
check(!str_contains(json_encode($created),$token),'authToken never appears in presentation');
check($rows[1]['authToken']===$token&&$rows[1]['apiVersion']===3&&$rows[1]['sendType']==='SEQUENTIALLY','saved token and official v3 payload');
$writes=fn()=>count(array_filter($calls,fn($c)=>$c[0]!=='GET'));
$before=$writes();rejects(fn()=>$api->execute('create',$input),'asaas_webhook_already_exists');check($writes()===$before,'repeated creation does not POST again');
check($api->execute('inspect')['webhook']['id']==='hook_own','pagination finds exact callback');
$rows[1]['interrupted']=true;$updated=$api->execute('update',array_replace($input,['name'=>'Changed','enabled'=>'0']));
check($updated['webhook']['name']==='Changed'&&!$updated['webhook']['enabled']&&$updated['webhook']['interrupted'],'update preserves interrupted queue');
check(!$api->execute('resume')['webhook']['interrupted'],'resume is separate explicit operation');
$fail=true;$before=count($calls);rejects(fn()=>$api->execute('update',$input),'synthetic timeout with secret');
check(count($calls)===$before+3,'two GET pages and exactly one failed PUT; no mutation retry');
check(!str_contains(Asaas\RemoteWebhook::message(new RuntimeException('synthetic timeout with secret')),'secret'),'error display never exposes provider details');$fail=false;
$rows[]=$rows[1];$rows[2]['id']='duplicate';rejects(fn()=>$api->execute('delete'),'asaas_webhook_duplicate_destination');array_pop($rows);
check($api->execute('delete')['webhook']===null&&count($rows)===1,'delete preserves unrelated webhook');
rejects(fn()=>$api->execute('update',$input),'asaas_webhook_not_found');
foreach([['events'=>[]],['events'=>['UNKNOWN']],['email'=>'bad'],['enabled'=>'true']] as $bad)rejects(fn()=>$api->execute('create',array_replace($input,$bad)),'asaas_webhook_invalid_configuration');
rejects(fn()=>new Asaas\RemoteWebhook($transport,'http://insecure.test',$token),'asaas_webhook_invalid_configuration');
rejects(fn()=>new Asaas\RemoteWebhook($transport,$url,"a\n".str_repeat('b',64)),'asaas_webhook_invalid_configuration');
$clientCalls=[];$client=new Asaas\CompanyClient('sandbox','synthetic-key',function(...$args)use(&$clientCalls){$clientCalls[]=$args;return ['ok'=>true];});
foreach([['GET','webhooks?limit=100&offset=0',null],['POST','webhooks',[]],['PUT','webhooks/hook_own',[]],['DELETE','webhooks/hook_own',null]] as $call)$client->request(...$call);
check(count($clientCalls)===4,'bounded company transport permits official webhook verbs');
foreach([['PUT','payments/pay_1',[]],['PUT','webhooks/../wallets',[]],['GET','https://attacker.test',null],['GET','webhooks?limit=100&offset=1000',null]] as $call)rejects(fn()=>$client->request(...$call),'asaas_company_request_invalid');
echo "Remote webhook protocol tests complete; no external requests.\n";
