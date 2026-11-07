<?php
const BASEPATH=__DIR__;
require __DIR__.'/../modules/asaas/libraries/Asaas/CompanyClient.php';
require __DIR__.'/../modules/asaas/libraries/Asaas/CompanyReportEngine.php';
$n=0;function reportAssert($ok,$message){global $n;$n++;if(!$ok)throw new RuntimeException($message);}
$scope=['issuer_id'=>2,'account_row'=>3,'account_id'=>'wallet_synthetic','environment'=>'sandbox'];$calls=[];
$transport=function($env,$method,$path,$payload,$secret)use(&$calls){reportAssert($env==='sandbox'&&$method==='GET'&&$payload===null&&$secret==='fixture','all reports are scoped bodyless GET');$calls[]=$path;return $GLOBALS['reportResponse'];};
$client=new \Asaas\CompanyClient('sandbox','fixture',$transport);
$engine=new \Asaas\CompanyReportEngine(fn($kind,$path,$query)=>$client->report($kind,$path,$query),$scope);
$catalog=\Asaas\AsaasReportCatalog::definitions();reportAssert(count($catalog)===20,'reference1.5.8 twenty report definitions preserved');
foreach($catalog as $kind=>$definition){
 $record=['id'=>'fixture'];foreach($definition['columns'] as $column){$value=match($column['type']){'money','number'=>'10.01','integer'=>1,'boolean'=>false,'date'=>'2026-11-07',default=>'safe-field'};$parts=explode('.',$column['key']);$ref=&$record;foreach($parts as $part){if(!isset($ref[$part]))$ref[$part]=[];$ref=&$ref[$part];}$ref=$value;unset($ref);}$record['id']='fixture';$record['creditCard']=['creditCardToken'=>'private-token'];$record['payload']='private-qr';
 $GLOBALS['reportResponse']=$definition['kind']==='summary'?$record:['data'=>[$record],'hasMore'=>false,'totalCount'=>1];
 $filters=[];foreach($definition['filters'] as $field)if($field['required']??false)$filters[$field['key']]='pay_fixture';
 $page=$engine->page($kind,$filters)['report'];reportAssert($page['scope']===$scope&&count($page['rows'])===1,$kind.' scoped page');
 reportAssert(!str_contains(json_encode($page),'private-token')&&!str_contains(json_encode($page),'private-qr'),$kind.' DTO excludes private extras');
 $endpoint=substr(str_replace('{id}','pay_fixture',$definition['endpoint']),4);reportAssert(strtok(end($calls),'?')===$endpoint,$kind.' fixed official resource');
 $file=$engine->export($kind,$filters);$csv=stream_get_contents($file->stream());$file->close();reportAssert(str_contains($csv,'wallet_synthetic')&&str_contains($file->filename,'-2-3-'),$kind.' export carries company/account');
 reportAssert(!str_contains($csv,'private-token')&&!str_contains($csv,'private-qr'),$kind.' CSV excludes private extras');
}
foreach([['balance',[],['host'=>'evil']],['refunds',['id'=>'../escape'],[]],['payments',[],['limit'=>101]],['unknown',[],[]]] as [$kind,$path,$query]){try{$client->report($kind,$path,$query);throw new LogicException('invalid request accepted');}catch(DomainException $e){reportAssert(true,'invalid report transport blocked');}}
reportAssert(str_starts_with(\Asaas\CompanyReportEngine::csvText(' =HYPERLINK(1)'),"'"),'CSV formulas neutralized');
$callsBefore=count($calls);
try{$engine->page('balance',['host'=>'evil']);throw new LogicException('filter accepted');}catch(InvalidArgumentException $expected){reportAssert(count($calls)===$callsBefore,'invalid filter makes no HTTP call');}
$GLOBALS['reportResponse']=['balance'=>'999999999999.99'];reportAssert($engine->page('balance')['report']['rows'][0]['balance']==='999999999999.99','exact decimal preserved at upper boundary');
$GLOBALS['reportResponse']=['balance'=>'-0.01'];reportAssert($engine->page('balance')['report']['rows'][0]['balance']==='-0.01','negative exact decimal preserved');
foreach(['repeat','changed','failure'] as $mode){$reads=0;$e=new \Asaas\CompanyReportEngine(function($kind,$path,$query)use(&$reads,$mode){$reads++;if($reads===2&&$mode==='failure')throw new RuntimeException('asaas_company_transport_unconfirmed');return ['data'=>[['id'=>$mode==='repeat'?'same':'txn_'.$reads,'value'=>'1.01']],'hasMore'=>$reads===1,'totalCount'=>$mode==='changed'&&$reads===2?3:2];},$scope);try{$e->export('statement');throw new LogicException('incomplete export accepted');}catch(\Asaas\AsaasReportException|RuntimeException $expected){reportAssert($reads===2,'failed/repeated/changed page discards complete export');}}
$t=0;$expired=new \Asaas\CompanyReportEngine(function(){throw new LogicException('budget should prevent reader');},$scope,function()use(&$t){$t+=46;return $t;});try{$expired->export('balance');throw new LogicException('budget ignored');}catch(\Asaas\AsaasReportException $expected){reportAssert($expected->reason==='export_limit','time budget enforced before request');}
echo "Account reports: $n contract assertions; twenty catalog resources, simulated transport.\n";
