<?php
require __DIR__.'/native_task_project_data.php';require_once APPPATH.'services/NativeCompanyTasksRead.php';require_once APPPATH.'services/NativeCompanyProjectRead.php';
$taskRead=new NativeCompanyTasksRead($db,$p);$projectRead=new NativeCompanyProjectRead($db,$p);
$db->query('UPDATE '.$p.'staff SET firstname=?,lastname=? WHERE staffid=92',['Literal <staff>','Member']);
$people=$taskRead->mentionMembers($a,7,$task);essentialCheck(array_column($people,'id')===[7,92]&&$people[1]['name']==='Literal &lt;staff&gt; Member','Task mention picker includes only current exact-task authorized staff and preserves original escaping');
$projectPeople=$projectRead->mentionMembers($a,7,$projectCanonical);essentialCheck(array_column($projectPeople,'id')===[7],'Project mention picker is actual membership/responsible lineage, not whole company directory');
essentialThrows(fn()=>$taskRead->mentionMembers($b,7,$task),'native_resource_reference_denied','B cannot read A task mention directory');essentialThrows(fn()=>$projectRead->mentionMembers($b,7,$projectCanonical),'native_resource_reference_denied','B cannot read A project members');
$db->query('DELETE FROM '.$p.'issuers_resource_permissions WHERE issuer_id=? AND staff_id=92 AND feature=? AND capability=?',[$a,'projects','view']);$db->queries=[];$people=$taskRead->mentionMembers($a,7,$task);$queries=$db->queries;
essentialCheck(array_column($people,'id')===[7],'Revoked project access removes candidate from project-task mentions');$hydrated=false;foreach($queries as[$sql,$args])if(str_starts_with($sql,'SELECT staffid,firstname,lastname FROM ')&&$args===[92])$hydrated=true;essentialCheck(!$hydrated,'Candidate names are not hydrated before exact task authorization');
$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,92,?,?)',[$a,'projects','view']);
class_alias(NativeCompanyTasksRead::class,'NativeTaskHttpTest\\NativeCompanyTasksRead');class_alias(NativeCompanyProjectRead::class,'NativeTaskHttpTest\\NativeCompanyProjectRead');
$controller->input->verb='GET';$controller->get_staff_names_for_mentions($task);essentialCheck(json_decode($controller->output->body,true,512,JSON_THROW_ON_ERROR)===$taskRead->mentionMembers($a,7,$task),'Original Tasks mention GET calls real guarded reader');
$projectsController->get_staff_names_for_mentions($projectCanonical);essentialCheck(json_decode($projectsController->output->body,true,512,JSON_THROW_ON_ERROR)===$projectPeople,'Original Projects mention GET calls actual membership reader');
$controller->input->verb='POST';try{$controller->get_staff_names_for_mentions($task);throw new RuntimeException('Mention POST accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 405','Original mention reader is explicitly read-only');}
echo 'Original work mention endpoints: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
