<?php
/** Project copies with all actual native436–439 billing participants already installed. */
register_shutdown_function(static function ():void {
    if(!isset($GLOBALS['db'],$GLOBALS['p']))return;
    foreach(['company_project_copy_snapshots','company_project_copy_requests']as$t)$GLOBALS['db']->query('DROP TABLE IF EXISTS '.$GLOBALS['p'].$t);
});
require __DIR__.'/native_resume_439.php';
require_once APPPATH.'services/CompanyProjectCopiesSchema.php';
CompanyProjectCopiesSchema::ensure($db,$p);
$implementation=getenv('NATIVE_COPY_IMPLEMENTATION')?:APPPATH.'services/CompanyProjectCopies.php';
require_once $implementation;
$copies=new CompanyProjectCopies($db,$p);
$start=$GLOBALS['essentialChecks'];
$schemas=[CompanyTaskBillingSchema::class,CompanyProjectFixedBillingSchema::class,CompanyTaskTimeBillingSchema::class,CompanyTaskTimeBatchSchema::class];
foreach($schemas as$schema)$schema::assertValid($db,$p);

$financial=[];foreach($schemas as$schema)foreach(array_keys($schema::definitions())as$t)$financial[$t]=$db->query('SELECT * FROM '.$p.$t)->result_array();
$results=[];
foreach($parents433 as$issuer=>$project){
    $preview=$copies->preview($issuer,7,$project);$map=[];foreach($preview['source']['tasks']as$t)$map[$t['id']]=7;
    $review=['source_revision'=>$preview['revision'],'name'=>'Native participants copy '.$issuer,'start_date'=>'2026-10-19','due_date'=>'2026-11-19','responsible_id'=>7,'copy_tasks'=>1,'copy_milestones'=>0,'copy_dependencies'=>0,'assignees'=>$map];
    $request=hash('sha256','native-participants-copy-'.$issuer);
    $result=$copies->create($issuer,7,$project,$review,$request);
    essentialCheck($result['project_id']!==$project&&count($result['task_map'])===count($map),'A/B real native-schema composition creates fresh project and tasks');
    essentialCheck($copies->create($issuer,7,$project,$review,$request)===$result,'all installed financial fences preserve idempotent copy replay');
    essentialCheck($copies->preview($issuer,7,$project)['source']===$preview['source'],'all installed financial fences preserve source bytes');
    $results[$issuer]=$result;
}
foreach($schemas as$schema)foreach(array_keys($schema::definitions())as$t)essentialCheck(in_array($t,$copies->tables(),true),'copy owned participants include native '.$t);
essentialCheck(in_array('company_invoice_requests',$copies->tables(),true),'copy owned participants retain canonical invoice receipt fence');
foreach(CompanyTaskAutoStop::tables($db,$p)as$t)essentialCheck(in_array($t,$copies->tables(),true),'copy includes complete task-writer owned participant '.$t);
foreach($financial as$t=>$rows)essentialCheck($db->query('SELECT * FROM '.$p.$t)->result_array()===$rows,'copy never adopts or changes native financial origin/claims '.$t);
echo 'Project copy actual native436–439 participants: '.($GLOBALS['essentialChecks']-$start).' added / '.$GLOBALS['essentialChecks'].' cumulative checks passed.'.PHP_EOL;
