<?php
/** Original task form and priority action, actual SQL and DOM; no browser claim. */
require __DIR__.'/task_preferences_controller.php';
require_once APPPATH.'services/CompanyTaskOperationPreferences.php';
$httpOpsStart=$GLOBALS['essentialChecks'];$httpOpsPolicy=new CompanyTaskOperationPreferences($db,$p);
foreach([$a=>'4',$b=>'3',$primary=>'1']as$owner=>$ownPriority){
    $before=$httpOpsPolicy->page($owner,7);$httpOpsPolicy->save($owner,7,$before['revision'],['default_task_priority'=>$ownPriority,'new_task_auto_follower_current_member'=>'1']);
    foreach(['project'=>$tasks,'standalone'=>$standalone]as$kind=>$writer){
        $post=preferenceTaskWire($owner,$kind,'priority-create-'.$owner.'-'.$kind);
        $query=$kind==='project'?['rel_type'=>'project','rel_id'=>$post['rel_id']]:[];
        $xp=preferenceTaskDom($owner,$query);
        essentialCheck($xp->query('//select[@id="priority"]/option')->length===4&&$xp->query('//select[@id="priority"]/option[@value="'.$ownPriority.'" and @selected]')->length===1,'original form has the own closed priority default '.$owner.'/'.$kind);
        $identity=preferenceTaskSave($owner,$post);$record=$writer->get($owner,7,$identity['source_id']);
        essentialCheck($record['priority']===2&&$record['timers']===[],'explicit original priority overrides own default without time/finance');
        $xp=preferenceTaskDom($owner,[],$identity['canonical_id']);
        essentialCheck($xp->query('//select[@id="priority"]/option[@value="2" and @selected]')->length===1,'original edit form reflects persisted priority');
        essentialCheck(str_contains($controller->output->body,'&lt;script&gt;literal&lt;/script&gt;')&&!str_contains($controller->output->body,'<script>literal</script>'),'original form escapes task text');
        $controller->input->verb='POST';$controller->input->query=[];
        $controller->input->body=['native_revision'=>(string)$record['revision'],'native_fields_revision'=>(string)$record['native_fields_revision'],'native_request_key'=>$key('priority-update-'.$owner.'-'.$kind),'_native_company_csrf'=>'synthetic-csrf','_company_context'=>'synthetic-context'];
        $controller->change_priority('4',$identity['canonical_id']);$response=json_decode($controller->output->body,true,512,JSON_THROW_ON_ERROR);
        essentialCheck($response['success']===true&&$writer->get($owner,7,$identity['source_id'])['priority']===4,'original priority action commits the revision-bound change');
        $invalid=$post;$invalid['priority']='99';$invalid['native_request_key']=$key('priority-invalid-'.$owner.'-'.$kind);
        try{preferenceTaskSave($owner,$invalid);throw new RuntimeException('invalid priority accepted');}catch(LogicException$error){essentialCheck($error->getMessage()==='HTTP 422','original form rejects an invalid priority');}
    }
}
echo 'Own priority original task controllers/forms: '.($GLOBALS['essentialChecks']-$httpOpsStart).' added / '.$GLOBALS['essentialChecks'].' cumulative ('.$db->driver().').'.PHP_EOL;
