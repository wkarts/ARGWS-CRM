<?php
require __DIR__.'/native_task_index_projection.php';
require_once APPPATH.'services/NativeCompanyTaskDetailRead.php';
$decoded=NativeCompanyResourceId::decode('task',$new);$id=$decoded['source_id'];
$db->query('INSERT INTO '.$p.'company_standalone_comments(issuer_id,task_id,actor_id,body,created_at) VALUES(?,?,?,?,?)',[$a,$id,7,'Scoped note','2026-10-25 08:00:00']);
$db->query('INSERT INTO '.$p.'company_standalone_timers(issuer_id,task_id,actor_id,started_at,stopped_at,seconds) VALUES(?,?,?,?,?,?)',[$a,$id,7,'2026-10-25 08:00:00','2026-10-25 08:01:00',60]);
$reader=new NativeCompanyTaskDetailRead($db,$p);$detail=$reader->read($a,7,$new);
essentialCheck($detail['item']['id']===$new&&$detail['comments'][0]['body']==='Scoped note'&&count($detail['timers'])===1,'Exact task detail reads its own comments and timers');
essentialThrows(fn()=>$reader->read($b,7,$new),'native_company_task_denied','Other company cannot read task detail');
$db->query('UPDATE '.$p.'issuers_staff SET can_view=1 WHERE issuer_id=? AND staff_id=92',[$a]);
$db->query('DELETE FROM '.$p.'issuers_resource_permissions WHERE issuer_id=? AND staff_id=92 AND feature=? AND capability=?',[$a,'tasks','edit']);
$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,92,?,?)',[$a,'tickets','view']);
$db->query('INSERT INTO '.$p.'company_standalone_timers(issuer_id,task_id,actor_id,started_at,stopped_at,seconds) VALUES(?,?,?,?,?,?)',[$a,$id,92,'2026-10-25 08:02:00','2026-10-25 08:03:00',60]);
$limited=$reader->read($a,92,$new);
essentialCheck(!$limited['permissions']['edit']&&count($limited['timers'])===1&&(int)$limited['timers'][0]['actor_id']===92,'View-only detail excludes other staff timesheets');
essentialCheck(!array_key_exists('timers',$limited['item']['row'])&&!array_key_exists('comments',$limited['item']['row']),'Unfiltered writer hydration is not exposed through the detail item');
$db->query('UPDATE '.$p.'issuers_staff SET can_view=0 WHERE issuer_id=? AND staff_id=92',[$a]);
essentialThrows(fn()=>$reader->read($a,92,$new),'native_company_task_denied','Revoked membership cannot read task detail');
echo 'Scoped task detail facts: '.$GLOBALS['essentialChecks']." cumulative checks passed; original modal/actions remain uncomposed.\n";
