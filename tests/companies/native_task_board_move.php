<?php
require __DIR__.'/native_task_form_writer.php';
// Board movements must preserve native metadata and enforce the existing DAG fences.
$boardInput=array_replace($input,['status'=>'todo','checklist'=>[]]);
$boardFields=array_replace($fields,['workflow_status'=>1,'date_finished'=>null]);
$boardPre=$tasks->createNative($a,7,$boardInput,$boardFields,$key('board-pre'));
$boardDep=$tasks->createNative($a,7,$boardInput,$boardFields,$key('board-dep'));
$boardExtras=new CompanyProjectExtras($db,$p);
$boardExtras->dependencies($a,7,$boardDep,1,[$boardPre],$key('board-dependency'));
essentialThrows(fn()=>$tasks->moveFromBoard($a,7,$boardDep,2,'done',$key('board-denied')),'company_project_dependencies_open','Board cannot complete native task before prerequisite');
$tasks->moveFromBoard($a,7,$boardPre,1,'done',$key('board-pre-done'));
$tasks->moveFromBoard($a,7,$boardDep,2,'done',$key('board-dep-done'));
$boardDone=$tasks->get($a,7,$boardDep);
essentialCheck($boardDone['status']==='done'&&json_decode($boardDone['native_fields_json'],true)['date_finished']!==null,'Board completes canonical task and native finish metadata');
essentialThrows(fn()=>$tasks->moveFromBoard($a,7,$boardPre,2,'todo',$key('board-reopen')),'company_project_completed_dependents','Board cannot reopen prerequisite of completed task');
essentialThrows(fn()=>$tasks->moveFromBoard($a,7,$boardDep,2,'todo',$key('board-stale')),'company_project_stale','Board rejects stale revision');
essentialThrows(fn()=>$tasks->moveFromBoard($b,7,$boardDep,3,'todo',$key('board-cross')),'company_project_access_denied','Board preserves issuer isolation');
echo "Native task board regression passed.\n";
