<?php
require_once __DIR__.'/../application/services/NativeCompanyWorkStatusCatalog.php';
$n=0;
function statusCheck($condition,string $message):void{global$n;if(!$condition)throw new RuntimeException($message);$n++;}
function statusReject(callable$fn):void{try{$fn();}catch(DomainException$e){statusCheck(true,'denied');return;}throw new RuntimeException('Expected status rejection');}
$expected=['project'=>['active','completed','cancelled','not_started','in_progress','on_hold'],'task'=>['todo','in_progress','done','awaiting_feedback','testing'],'ticket'=>['open','resolved','closed']];
foreach($expected as$type=>$states){
    $rows=NativeCompanyWorkStatusCatalog::owned($type,static fn($key)=>$key);
    statusCheck(array_column($rows,'state')===$states,'No state approximation');
    foreach($rows as$i=>$row){
        statusCheck($row['id']===NativeCompanyWorkStatusCatalog::id($type,$states[$i]),'Stable catalogue identity');
        statusCheck(NativeCompanyWorkStatusCatalog::predicate($type,$row['id'])===['lineage'=>'owned','state'=>$states[$i]],'Lossless owned predicate');
        statusCheck($row['id']!==(string)($i+1),'No legacy ID collision');
        statusReject(static fn()=>NativeCompanyWorkStatusCatalog::predicate($type,$i+1));
        statusCheck(NativeCompanyWorkStatusCatalog::predicate($type,$i+1,[$i+1])===['lineage'=>'legacy','state'=>$i+1],'Legacy predicate explicitly admitted');
    }
    statusReject(static fn()=>NativeCompanyWorkStatusCatalog::predicate($type,NativeCompanyResourceId::encode('company_'.$type.'_status',99)));
    statusReject(static fn()=>NativeCompanyWorkStatusCatalog::predicate($type,1,['1']));
    statusReject(static fn()=>NativeCompanyWorkStatusCatalog::id($type,'made_up'));
}
$taskRows=NativeCompanyWorkStatusCatalog::owned('task',static fn($key)=>$key);
$taskContracts=[1=>['todo','task_status_1'],2=>['in_progress','task_status_4'],3=>['done','task_status_5'],4=>['awaiting_feedback','task_status_2'],5=>['testing','task_status_3']];
foreach($taskContracts as$sourceId=>[$state,$label]){
    $row=$taskRows[$sourceId-1];statusCheck($row['source_id']===$sourceId&&$row['id']===NativeCompanyResourceId::encode('company_task_status',$sourceId),'Historical transport IDs1/2/3 preserved; distinct native states append4/5');
    statusCheck($row['state']===$state&&$row['name']===$label,'Exact lifecycle and original native label, no approximation');
}
statusReject(static fn()=>NativeCompanyWorkStatusCatalog::id('task','awaiting-feedback'));
statusReject(static fn()=>NativeCompanyWorkStatusCatalog::id('task','answered'));
statusReject(static fn()=>NativeCompanyWorkStatusCatalog::predicate('project',NativeCompanyWorkStatusCatalog::id('task','todo')));
statusReject(static fn()=>NativeCompanyWorkStatusCatalog::id('project','testing'));
statusReject(static fn()=>NativeCompanyWorkStatusCatalog::id('ticket','answered'));
statusReject(static fn()=>NativeCompanyWorkStatusCatalog::owned('staff',static fn($v)=>$v));
foreach(['portuguese','english']as$locale){$lang=[];require __DIR__.'/../application/language/'.$locale.'/'.$locale.'_lang.php';foreach($expected as$type=>$states){$rows=NativeCompanyWorkStatusCatalog::owned($type,static fn($key)=>$lang[$key]??null);foreach($rows as$row)statusCheck(is_string($row['name'])&&$row['name']!=='','Actual language provides label');}}

statusCheck(NativeCompanyWorkStatusCatalog::projectState('active',null)==='active','Historical active remains distinct');
statusCheck(NativeCompanyWorkStatusCatalog::projectState('active',2)==='in_progress','Explicit declaration provides phase');
statusReject(fn()=>NativeCompanyWorkStatusCatalog::projectState('completed',2));
statusCheck(NativeCompanyWorkStatusCatalog::projectWorkflow(NativeCompanyWorkStatusCatalog::id('project','active'))===0,'Actual historical active is not assigned a native phase');
statusCheck(NativeCompanyWorkStatusCatalog::projectWorkflow(NativeCompanyWorkStatusCatalog::id('project','in_progress'))===2,'Native editor preserves declared progress');
statusReject(fn()=>NativeCompanyWorkStatusCatalog::projectWorkflow(2));
echo "Native work status catalogue: {$n} checks passed; explicit lineages, no aliased states.\n";
