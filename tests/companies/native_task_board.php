<?php
require __DIR__.'/native_task_original_actions.php';
require_once APPPATH.'services/NativeCompanyTaskBoard.php';
$board=new NativeCompanyTaskBoard($db,$p);$checks=$GLOBALS['essentialChecks'];
\NativeTaskHttpTest\CompanyRequestScope::$issuer=$a;\NativeTaskHttpTest\CompanyRequestScope::$actor=7;
$preference=$board->preference($a,7);$snapshot=$board->read($a,7);
essentialCheck($preference['view']==='table'&&in_array($canonical,array_column($snapshot['items'],'id'),true),'Board reads the same canonical collection as the task table');
$controller->input->body=['board_revision'=>$preference['revision'],'native_request_key'=>$key('board-view'),'view'=>'board','_native_company_csrf'=>'synthetic-csrf'];
$controller->switch_kanban();$view=$board->preference($a,7);
essentialCheck($controller->output->status===303&&in_array('Location: https://crm.invalid/sub/admin/tasks',$controller->output->headers,true)&&$view['view']==='board','Original switch POST commits preference and actual 303 location');
$controller->switch_kanban();essentialCheck($board->preference($a,7)===$view,'Exact view replay does not duplicate or reverse preference');
$controller->input->body['native_request_key']=$key('board-view-stale');try{$controller->switch_kanban();throw new RuntimeException('Old board revision accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 409','Board old revision conflicts');}
$ids=array_column($snapshot['items'],'id');$other=array_values(array_filter($ids,fn($id)=>$id!==$canonical))[0];
$controller->input->body=['board_revision'=>$view['revision'],'native_request_key'=>$key('board-move'),'task'=>$canonical,'before'=>$other,'_native_company_csrf'=>'synthetic-csrf'];$controller->update_order();
$order=$board->preference($a,7);essentialCheck(array_search($canonical,$order['order'],true)+1===array_search($other,$order['order'],true),'Original ordering action persists the requested relative position');
$controller->update_order();essentialCheck($board->preference($a,7)===$order,'Ordering replay preserves one shared order');
$viewOnly=$board->preference($a,92);essentialCheck($viewOnly['order']===$order['order']&&$viewOnly['view']==='table','Task order is shared within company while view preference belongs to actor');
essentialThrows(fn()=>$board->save($a,92,$viewOnly['revision'],$key('board-restricted'),['action'=>'move','task'=>$canonical,'before'=>$other]),'native_task_board_denied','View/create-only member cannot reorder shared tasks');
essentialCheck($board->preference($b,7)['order']===[],'Company B does not read company A order');
essentialThrows(fn()=>$board->save($b,7,$board->preference($b,7)['revision'],$key('board-cross'),['action'=>'move','task'=>$canonical,'before'=>'']),'native_task_board_denied','Known foreign ID cannot enter another board');
$before=$board->preference($a,7);$audits=$db->query('SELECT * FROM '.$p.'issuers_audit ORDER BY id')->result_array();$db->failOn='INSERT INTO '.$p.'issuers_audit';
try{$board->save($a,7,$before['revision'],$key('board-rollback'),['action'=>'view','view'=>'table']);throw new LogicException('Ignored audit accepted');}catch(RuntimeException$e){essentialCheck(true,'Audit failure is propagated');}finally{$db->failOn=null;}
essentialCheck($board->preference($a,7)===$before&&$db->query('SELECT * FROM '.$p.'issuers_audit ORDER BY id')->result_array()===$audits,'Preference rolls back together with audit');
$controller->input->body=['board_revision'=>$before['revision'],'native_request_key'=>$key('board-missing-csrf'),'view'=>'table'];try{$controller->switch_kanban();throw new RuntimeException('CSRF missing accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 403','Original board action keeps CSRF');}
$db->query('UPDATE '.$p.'issuers_staff SET can_view=0 WHERE issuer_id=? AND staff_id=92',[$a]);essentialThrows(fn()=>$board->preference($a,92),'native_task_board_denied','Revoked membership cannot read preferences');
echo 'Original task board: '.($GLOBALS['essentialChecks']-$checks).' additional checks passed ('.$db->driver().").\n";
