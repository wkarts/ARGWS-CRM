<?php
/** Rebuilt composition proof: all original individual copy assertions after native440. */
register_shutdown_function(static function(){global$db,$p;if(isset($db,$p))$db->query('DROP TABLE IF EXISTS '.$p.'company_task_copy_requests');});
require __DIR__.'/project_copy_native_billing_participants_440.php';
require_once APPPATH.'services/CompanyTaskCopies.php';
CompanyTaskCopiesSchema::ensure($db,$p);$start=$GLOBALS['essentialChecks'];$individual=new CompanyTaskCopies($db,$p);$standalone=new CompanyStandaloneTasks($db,$p);
$nativeFinancial=[];foreach([CompanyTaskBillingSchema::class,CompanyProjectFixedBillingSchema::class,CompanyTaskTimeBillingSchema::class,CompanyTaskTimeBatchSchema::class,CompanyStandaloneBillingSchema::class]as$schema)foreach(array_keys($schema::definitions())as$t){$schema::assertValid($db,$p);$nativeFinancial[$t]=$db->query('SELECT * FROM '.$p.$t)->result_array();essentialCheck(in_array($t,$individual->tables(),true),'individual composition includes installed native participant '.$t);}
foreach($taskIds433 as$issuer=>$task){
    $preview=$individual->preview($issuer,7,'project',$task);$r=['source_revision'=>$preview['revision'],'name'=>'Native individual copy '.$issuer,'due_date'=>'2026-11-01','assignee_id'=>7,'target_project_id'=>$parents433[$issuer]];$request=hash('sha256','native-individual-copy-'.$issuer);$result=$individual->create($issuer,7,'project',$task,$r,$request);
    essentialCheck($result['task_id']!==$task&&$individual->create($issuer,7,'project',$task,$r,$request)===$result,'A/B project-task copy succeeds/replays with all native billing schemas installed');
    essentialCheck($individual->preview($issuer,7,'project',$task)['source']===$preview['source'],'full native project task copy preserves source');
    $source=$standalone->create($issuer,7,['assignee_id'=>7,'name'=>'Native standalone origin '.$issuer,'description'=>'Own only','due_date'=>'2026-11-01','checklist'=>[['text'=>'Reset','done'=>0]]],hash('sha256','native-standalone-source-'.$issuer));
    $preview=$individual->preview($issuer,7,'standalone',$source);$r=['source_revision'=>$preview['revision'],'name'=>'Native standalone copy '.$issuer,'due_date'=>'2026-11-01','assignee_id'=>7,'target_project_id'=>0];$result=$individual->create($issuer,7,'standalone',$source,$r,hash('sha256','native-standalone-copy-'.$issuer));
    $new=$standalone->get($issuer,7,$result['task_id']);essentialCheck($result['task_id']!==$source&&$new['status']==='todo'&&$new['timers']===[]&&$new['comments']===[]&&$new['billing_state']==='none','A/B standalone canonical copier stays unbilled under all native fences');
}
foreach($nativeFinancial as$t=>$rows)essentialCheck($db->query('SELECT * FROM '.$p.$t)->result_array()===$rows,'individual copies do not modify native financial rows '.$t);
echo 'Individual copies actual native436–439 participants: '.($GLOBALS['essentialChecks']-$start).' added / '.$GLOBALS['essentialChecks'].' cumulative checks.'.PHP_EOL;

foreach($otherCopy440 as $nativeTable440=>$nativeRows440)essentialCheck($snapshot440($nativeTable440)===$nativeRows440,'individual copies preserve unrelated native440 rows '.$nativeTable440);
echo 'Rebuilt task-copy full440 composition: '.$GLOBALS['essentialChecks'].' cumulative checks passed.'.PHP_EOL;
