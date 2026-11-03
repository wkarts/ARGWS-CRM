<?php
require __DIR__.'/operations_resume_439_billing.php';
$db->query('INSERT INTO '.$p.'staff(staffid,active,admin) VALUES(94,1,0)');$db->query('INSERT INTO '.$p.'issuers_staff(issuer_id,staff_id,can_view) VALUES(?,94,1)',[$a]);foreach(['tasks','projects','customers','invoices']as$feature)$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,94,?,?)',[$a,$feature,'view']);
foreach([[$resumeTimes,'company_time_billing_batches'],[$resumeBatch,'company_task_time_batches']]as[$reader,$table]){
 $column='origin_id';$row=$db->query('SELECT '.$column.' id FROM '.$p.$table.' WHERE issuer_id=? AND state=? ORDER BY '.$column.' LIMIT 1',[$a,'converted'])->row_array();if(!$row)throw new RuntimeException('Actual converted time fixture missing');
 $start=count($db->queries);$read=$reader->readStatus($a,94,(int)$row['id']);$queries=array_slice($db->queries,$start);
 essentialCheck($read['state']==='converted'&&$read['invoice_id']>0&&is_array($read['invoice_current']),'Time read-only evidence uses actual canonical invoice and confirmed claim sources');
 essentialCheck(!isset($read['can_release'],$read['can_disable'],$read['can_prepare_more'])&&!array_filter($queries,fn($q)=>preg_match('/^\s*(?:INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|DROP)\b/i',$q[0])),'Time financial read performs no DML and offers no mutation capability');
 essentialThrows(fn()=>$reader->readStatus($b,94,(int)$row['id']),'company_task_billing_read_denied','Time read requires exact issuer grants');
}
echo 'Native project selected/multi-batch read-only proof: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
$selectedProject=$db->query('SELECT project_id FROM '.$p.'company_time_billing_batches WHERE issuer_id=? ORDER BY origin_id LIMIT 1',[$a])->row_array()['project_id'];
$begin=count($db->queries);$choices=$resumeTimes->readChoices($a,94,$selectedProject);$calls=array_slice($db->queries,$begin);
essentialCheck($choices['project']['id']===(int)$selectedProject&&count($choices['timers'])>0,'Native time selector reads actual stopped timers and project source');
essentialCheck(!array_filter($calls,fn($q)=>preg_match('/^\s*(?:INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|DROP)\b/i',$q[0])),'Reading candidates reserves no timer or invoice origin');
essentialCheck(count(array_filter($choices['timers'],fn($t)=>$t['allocation_state']!==null))>0,'Previously allocated timer remains fenced in native selector');
essentialThrows(fn()=>$resumeTimes->choices($a,94,$selectedProject),'company_task_billing_denied','Original privileged selector retains its authority contract');
$db->query('DELETE FROM '.$p.'issuers_resource_permissions WHERE issuer_id=? AND staff_id=94 AND feature=?',[$a,'invoices']);essentialThrows(fn()=>$resumeTimes->readChoices($a,94,$selectedProject),'company_task_billing_read_denied','Native selection requires invoice read grant even before preparing origin');
echo 'Native project read-only timer choices: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
