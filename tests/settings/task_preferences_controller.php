<?php
/** Original Tasks::task/mark_as controllers and real form/SQL; only framework I/O is synthetic. */
require dirname(__DIR__).'/companies/native_task_status_controller.php';
require_once APPPATH.'services/CompanyTaskPreferences.php';
require_once APPPATH.'services/NativeCompanyProjectMetadataSchema.php';
NativeCompanyProjectMetadataSchema::ensure($db,$p);
require_once APPPATH.'services/CompanyProjectPortalRulesSchema.php';
require_once APPPATH.'services/CompanyPortalOperationsSchema.php';
foreach(CompanyPortalOperationsSchema::definitions($p)as$table=>$parts)if($table==='company_portal_projects'&&!$db->table_exists($p.$table)){
    if($db->driver()==='sqlite')$parts=array_map(static fn($part)=>preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$part),$parts);
    $db->query('CREATE TABLE '.$p.$table.'('.implode(',',$parts).') ENGINE=InnoDB');
}
if($db->driver()==='sqlite')foreach(CompanyProjectPortalRulesSchema::definitions($p)as$table=>$parts)if(!$db->table_exists($p.$table)){
    $db->query('CREATE TABLE '.$p.$table.'('.preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',implode(',',$parts)).')');
}
unset($db->data_cache['table_names']);
$controllerStart=$GLOBALS['essentialChecks'];
$preferences=new CompanyTaskPreferences($db,$p);
$primary=(new IssuerResourceAccess($db,$p))->primaryId();
// The principal uses the original custom-field catalog, even when it is empty.
$nativeSeed=file_get_contents(dirname(__DIR__,2).'/install/database.sql');
foreach(['customfields','customfieldsvalues','contact_permissions']as$table)if(!$db->table_exists($p.$table)){
    if(!preg_match('/CREATE TABLE `tbl'.preg_quote($table,'/').'` \\(.*?\\) ENGINE=InnoDB[^;]*;/s',$nativeSeed,$definition))throw new RuntimeException('native task fixture catalog missing');
    $sql=str_replace('`tbl'.$table.'`','`'.$p.$table.'`',$definition[0]);
    $sql=str_replace('`id` int NOT NULL,','`id` '.($db->driver()==='mysql'?'int NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT').',',$sql);
    if($db->driver()==='sqlite')$sql=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$sql);
    $db->query($sql);
}
unset($db->data_cache['table_names']);
$cp=(new CompanyClients($db,$p))->create($primary,7,['company'=>'Own principal task preferences']);
$preferenceProjects=[];
function preferenceTaskDom(int $owner,array $query=[],string $id=''):DOMXPath {
    global $controller;
    \NativeTaskHttpTest\CompanyRequestScope::$issuer=$owner;
    \NativeTaskHttpTest\CompanyRequestScope::$actor=7;
    $controller->input->verb='GET';$controller->input->query=$query;$controller->input->body=[];
    $controller->task($id);$dom=new DOMDocument();@$dom->loadHTML($controller->output->body);return new DOMXPath($dom);
}
function preferenceTaskWire(int $owner,string $kind,string $tag):array {
    global $wire,$key,$a,$tasks,$preferenceProjects;
    $post=array_replace($wire,['name'=>'Task policy <script>literal</script> '.$tag,'assignees'=>['7'],'followers'=>[],
        'priority'=>'2','startdate'=>'25/10/2026','duedate'=>'','tags'=>'','native_request_key'=>$key($tag),
        '_native_company_csrf'=>'synthetic-csrf','_company_context'=>'synthetic-context']);
    if($owner!==$a)$post['custom_fields']=[];
    if($kind==='project'){
        $project=$preferenceProjects[$owner];$post['rel_type']='project';$post['rel_id']=NativeCompanyResourceId::encode('company_project',$project);
        $post['native_milestone_revision']=$tasks->nativeMilestoneSelection($owner,7,$project)['revision'];
    }else{$post['rel_type']='';$post['rel_id']='';unset($post['native_milestone_revision']);}
    return $post;
}
function preferenceTaskSave(int $owner,array $post,string $id=''):array {
    global $controller;
    \NativeTaskHttpTest\CompanyRequestScope::$issuer=$owner;\NativeTaskHttpTest\CompanyRequestScope::$actor=7;
    $controller->input->verb='POST';$controller->input->query=[];$controller->input->body=$post;
    $controller->task($id);$result=json_decode($controller->output->body,true,512,JSON_THROW_ON_ERROR);
    essentialCheck($result['success']===true,'original task form returns a successful canonical write');return NativeCompanyResourceId::decode('task',$result['id']);
}
foreach([$a=>$ca,$b=>$cb,$primary=>$cp]as$owner=>$client){
    (new CompanySettingsStorage($db,$p))->mutate($owner,7,'update','dateformat','d/m/Y|%d/%m/%Y');
    $before=$preferences->page($owner,7);$preferences->save($owner,7,$before['revision'],['default_task_status'=>'in_progress','new_task_auto_assign_current_member'=>'0']);
    $preferenceProjects[$owner]=$ps->create($owner,7,['client_id'=>$client,'responsible_id'=>7,'name'=>'Task policy project '.$owner,'description'=>'','start_date'=>'2026-10-01','due_date'=>'2026-12-01'],$key('policy-project-'.$owner));
    foreach(['project'=>$tasks,'standalone'=>$standalone]as$kind=>$writer){
        $post=preferenceTaskWire($owner,$kind,'policy-create-'.$owner.'-'.$kind);
        $query=$kind==='project'?['rel_type'=>'project','rel_id'=>$post['rel_id']]:[];
        $xp=preferenceTaskDom($owner,$query);
        essentialCheck($xp->query('//form[@id="task-form"]')->length===1&&$xp->query('//select[@name="assignees[]"]/option[@selected]')->length===0,'principal/A/B original task modal honors disabled automatic assignment '.$kind);
        $projection=(new NativeCompanyTaskForm($db,$p))->newForm($owner,7,$query,'2026-10-29');
        essentialCheck($projection['native_task_form']['options']['default_task_status']===4,'original task projection carries this company status policy');
        $identity=preferenceTaskSave($owner,$post);$record=$writer->get($owner,7,$identity['source_id']);
        essentialCheck($record['status']==='in_progress'&&$record['timers']===[],'principal/A/B original writer uses own status without implicit time');
        $controller->input->body=['native_revision'=>(string)$record['revision'],'native_fields_revision'=>(string)$record['native_fields_revision'],'native_request_key'=>$key('policy-override-'.$owner.'-'.$kind),'_native_company_csrf'=>'synthetic-csrf','_company_context'=>'synthetic-context'];
        $controller->mark_as('1',$identity['canonical_id']);$result=json_decode($controller->output->body,true,512,JSON_THROW_ON_ERROR);
        essentialCheck($result['success']===true&&$writer->get($owner,7,$identity['source_id'])['status']==='todo','explicit original status action overrides the creation policy');
        $invalid=$post;$invalid['assignees']=[''];$invalid['native_request_key']=$key('policy-invalid-assignee-'.$owner.'-'.$kind);
        try{preferenceTaskSave($owner,$invalid);throw new RuntimeException('invalid assignee accepted');}catch(LogicException$error){essentialCheck($error->getMessage()==='HTTP 422','original form rejects malformed explicit assignment');}
    }
}
$before=$preferences->page($b,7);$preferences->save($b,7,$before['revision'],['default_task_status'=>'todo','new_task_auto_assign_current_member'=>'1']);
$xp=preferenceTaskDom($b);essentialCheck($xp->query('//select[@name="assignees[]"]/option[@value="7" and @selected]')->length===1,'enabled assignment preselects the actual actor in the original modal');
echo 'Own task defaults original controllers/forms: '.($GLOBALS['essentialChecks']-$controllerStart).' added / '.$GLOBALS['essentialChecks'].' cumulative ('.$db->driver().').'.PHP_EOL;
