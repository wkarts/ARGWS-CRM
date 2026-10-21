<?php
require __DIR__.'/task_recurring_fixture.php';require_once APPPATH.'services/CompanyOperationsApi.php';
$api=new CompanyOperationsApi($db,$p);$tokens=new CompanyApiTokens($db,$p);$issued=[];$plans=[];$bodies=[];
foreach([$a,$b,$primary]as$i){
    $issued[$i]=$tokens->issue($i,7,'Synthetic recurrence API '.$i,['tasks:read','tasks:write'],1,$key('rr-api-token-'.$i));
    foreach(['task_schedules'=>'project','standalone_schedules'=>'standalone']as$domain=>$kind){
        $body=['action'=>'create','input'=>['source_id'=>$sources[$kind][$i],'source_revision'=>5,'calendar'=>rrCalendar(['cycles'=>2])]];$bodies[$domain][$i]=$body;$request=$key('rr-api-create-'.$domain.'-'.$i);$secret=$issued[$i]['secret'];
        $id=$api->write($secret,$domain,null,$body,$request)['id'];$plans[$domain][$i]=$id;$record=$api->read($secret,$domain,$id)['record'];
        rrCheck($record['issuer_id']===$i&&$record['api_origin']===['token_id'=>$issued[$i]['token_id'],'scope'=>'tasks:write'],'API origin is persisted token identity and scope for all six positive contexts');
        rrCheck($api->write($secret,$domain,null,$body,$request)['id']===$id,'same bearer request returns same schedule');
        rrCheck(in_array($id,array_column($api->read($secret,$domain)['items'],'id'),true),'positive native API schedule list');
        $api->write($secret,$domain,$id,['action'=>'pause','revision'=>1],$key('rr-api-pause-'.$id));rrCheck($api->read($secret,$domain,$id)['record']['state']==='paused','API pause persists own schedule');
        $api->write($secret,$domain,$id,['action'=>'resume','revision'=>2],$key('rr-api-resume-'.$id));$child=$rr->run($i,7,$id,'2026-11-02 00:00:00',0);rrCheck(is_int($child),'CLI uses valid persisted API origin without bearer secret');
        $api->write($secret,$domain,$id,['action'=>'cancel','revision'=>4],$key('rr-api-cancel-'.$id));rrCheck($api->read($secret,$domain,$id)['record']['state']==='cancelled'&&$rr->run($i,7,$id,'2026-11-02 00:00:00',1)===null,'API cancel keeps history and stops generation');
    }
}
foreach(['task_schedules'=>'project','standalone_schedules'=>'standalone']as$domain=>$kind){
    rrThrows(fn()=>$api->read($issued[$b]['secret'],$domain,$plans[$domain][$a]),'company_task_recurring_access_denied','cross-owner schedule read forbidden');
    rrThrows(fn()=>$api->write($issued[$b]['secret'],$domain,null,$bodies[$domain][$a],$key('rr-api-cross-'.$domain)),'company_project_access_denied','cross-company source never adopted');
    $bad=$bodies[$domain][$a];$bad['input']['api_origin']=['token_id'=>$issued[$b]['token_id'],'scope'=>'tasks:write'];rrThrows(fn()=>$api->write($issued[$a]['secret'],$domain,null,$bad,$key('rr-api-overpost-'.$domain)),'company_api_invalid_input','body cannot set persisted token origin');
    $bad=$bodies[$domain][$a];$bad['issuer_id']=$b;rrThrows(fn()=>$api->write($issued[$a]['secret'],$domain,null,$bad,$key('rr-api-company-'.$domain)),'company_api_invalid_input','body issuer is rejected');
}
$read=$tokens->issue($a,7,'Read schedules only',['tasks:read'],1,$key('rr-api-read'));
rrCheck($api->read($read['secret'],'standalone_schedules',$plans['standalone_schedules'][$a])['record']['issuer_id']===$a,'read scope has actual positive directory/get access');
rrThrows(fn()=>$api->write($read['secret'],'standalone_schedules',null,$bodies['standalone_schedules'][$a],$key('rr-api-denied-create')),'company_api_scope_denied','read scope cannot schedule jobs');
$foreign=$tokens->issue($a,7,'Other scope',['projects:read'],1,$key('rr-api-other-scope'));rrThrows(fn()=>$api->read($foreign['secret'],'task_schedules'),'company_api_scope_denied','an unrelated valid token has no task scope');
rrThrows(fn()=>$tokens->guardOriginInOwnedTransaction($issued[$a]['token_id'],'tasks:write',$a,7),'issuer_owned_transaction_required','persisted origin ID is never stand-alone authentication');
foreach(['revoked','expired','removed_scope','owner_mismatch','actor_mismatch']as$mode){
    $token=$tokens->issue($b,7,'Origin veto '.$mode,['tasks:read','tasks:write'],1,$key('rr-api-origin-'.$mode));$body=$bodies['standalone_schedules'][$b];$id=$api->write($token['secret'],'standalone_schedules',null,$body,$key('rr-api-origin-plan-'.$mode))['id'];
    $old=$db->query('SELECT * FROM '.$p.'company_api_tokens WHERE token_id=?',[$token['token_id']])->row_array();
    if($mode==='revoked')$tokens->revoke($b,7,$token['token_id']);
    elseif($mode==='expired')$db->query("UPDATE {$p}company_api_tokens SET expires_at='2000-01-01 00:00:00' WHERE token_id=?",[$token['token_id']]);
    elseif($mode==='removed_scope')$db->query('UPDATE '.$p.'company_api_tokens SET scopes_json=? WHERE token_id=?',['["tasks:read"]',$token['token_id']]);
    elseif($mode==='owner_mismatch')$db->query('UPDATE '.$p.'company_api_tokens SET issuer_id=? WHERE token_id=?',[$a,$token['token_id']]);
    else $db->query('UPDATE '.$p.'company_api_tokens SET actor_id=12 WHERE token_id=?',[$token['token_id']]);
    $before=rrSnapshot();rrThrows(fn()=>$rr->run($b,7,$id,'2026-11-02 00:00:00',0),'company_api_scope_denied','worker revalidates origin '.$mode);rrCheck(rrSnapshot()===$before,'invalid origin leaves exact child/calendar/receipt/audit rows unchanged');
    $rr->transition($b,7,'standalone',$id,1,'pause',$key('rr-origin-pause-'.$mode));rrThrows(fn()=>$rr->transition($b,7,'standalone',$id,2,'resume',$key('rr-origin-resume-'.$mode)),'company_api_scope_denied','native GUI cannot resume invalid API origin');
    rrCheck($rr->get($b,7,'standalone',$id)['state']==='paused','read and pause remain available for an origin needing review');
    $rr->transition($b,7,'standalone',$id,2,'cancel',$key('rr-origin-cancel-'.$mode));
    $db->query('UPDATE '.$p.'company_api_tokens SET issuer_id=?,actor_id=?,active=?,expires_at=?,scopes_json=? WHERE token_id=?',[$old['issuer_id'],$old['actor_id'],$old['active'],$old['expires_at'],$old['scopes_json'],$old['token_id']]);
}
$old=$tokens->issue($b,7,'Rotation original',['tasks:read','tasks:write'],1,$key('rr-old-rotate'));$body=$bodies['standalone_schedules'][$b];$idem=$key('rr-rotation-plan');$id=$api->write($old['secret'],'standalone_schedules',null,$body,$idem)['id'];$next=$tokens->issue($b,7,'Rotation next',['tasks:read','tasks:write'],1,$key('rr-new-rotate'),$old['token_id']);
rrThrows(fn()=>$rr->run($b,7,$id,'2026-11-02 00:00:00',0),'company_api_scope_denied','credential rotation does not transfer old job authority');
rrThrows(fn()=>$api->write($next['secret'],'standalone_schedules',null,$body,$idem),'company_task_recurring_request_conflict','same actor/new token cannot adopt old creation key');
$api->write($next['secret'],'standalone_schedules',$id,['action'=>'pause','revision'=>1],$key('rr-rotation-pause'));rrThrows(fn()=>$api->write($next['secret'],'standalone_schedules',$id,['action'=>'resume','revision'=>2],$key('rr-rotation-resume')),'company_api_scope_denied','new token does not cure revoked original scope');
rrCheck($api->read($next['secret'],'standalone_schedules',$id)['record']['state']==='paused','valid replacement credential can inspect the blocked plan without adopting its execution origin');
rrThrows(fn()=>$api->read($old['secret'],'standalone_schedules',$id),'company_api_unauthenticated','revoked bearer has no read authentication');
$token=$tokens->issue($a,7,'Late token veto',['tasks:read','tasks:write'],1,$key('rr-late-token'));$id=$api->write($token['secret'],'standalone_schedules',null,$bodies['standalone_schedules'][$a],$key('rr-late-plan'))['id'];$calls=0;$late=new CompanyTaskRecurring($db,$p,function()use(&$calls,$db,$p,$token){if(++$calls===2)$db->query('UPDATE '.$p.'company_api_tokens SET active=0 WHERE token_id=?',[$token['token_id']]);});$before=rrSnapshot();rrThrows(fn()=>$late->run($a,7,$id,'2026-11-02 00:00:00',0),'company_api_scope_denied','post-writer origin revocation vetoes all pending effects');rrCheck(rrSnapshot()===$before&&(int)$db->query('SELECT active FROM '.$p.'company_api_tokens WHERE token_id=?',[$token['token_id']])->row()->active===1,'late veto rolls back both child and synthetic token mutation');
rrCheck($financialBefore===$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()&&hooks()->trace===[],'API tasks never alter finance or external hooks');
echo 'Task recurrence rebuilt API/origin: '.($GLOBALS['essentialChecks']-$recurringStart).' NEW assertions; '.$GLOBALS['essentialChecks'].' cumulative; '.PHP_VERSION.'.'.PHP_EOL;
