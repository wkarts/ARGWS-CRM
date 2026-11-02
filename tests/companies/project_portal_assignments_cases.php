<?php
/** Reader regression cases; invoked by project_portal_rules.php in its disposable PDO fixture. */
$assignmentOwner=$primary;$assignmentProject=$portalParents['projects'][$primary];$assignmentTask=$projectTaskIds[$primary];
$assignmentStaff=$db->query('SELECT firstname,lastname FROM '.$p.'staff WHERE staffid=7')->row();$assignmentName=trim($assignmentStaff->firstname.' '.$assignmentStaff->lastname);
$assignmentRead=fn()=>$taskPortal->detail($sessions[$assignmentOwner],$assignmentOwner,$assignmentProject,$assignmentTask);
essentialCheck($assignmentRead()['assignee_name']===$assignmentName,'historical assigned staff remains readable behind team flag');
essentialCheck($db->table_exists($p.'company_task_assignees'),'native assignment schema is present in the existing fixture');
$db->query('DELETE FROM '.$p.'company_task_assignees WHERE issuer_id=? AND task_id=?',[$assignmentOwner,$assignmentTask]);
essentialCheck(in_array('company_task_assignees',$taskPortal->tables(),true),'native assignment table participates in the existing read transaction');
essentialCheck($assignmentRead()['assignee_name']===$assignmentName,'empty native assignment history retains historical source');
$db->query('UPDATE '.$p.'company_tasks SET assignee_id=NULL WHERE issuer_id=? AND id=?',[$assignmentOwner,$assignmentTask]);
$db->query('INSERT INTO '.$p.'company_task_assignees(issuer_id,task_id,staff_id,active,revision,origin) VALUES(?,?,7,1,1,?)',[$assignmentOwner,$assignmentTask,'native']);
essentialCheck($assignmentRead()['assignee_name']===$assignmentName,'native assignment supplies authorized staff when historical slot is empty');
$db->query('UPDATE '.$p.'company_tasks SET assignee_id=7 WHERE issuer_id=? AND id=?',[$assignmentOwner,$assignmentTask]);
$db->query('UPDATE '.$p.'company_task_assignees SET active=0 WHERE issuer_id=? AND task_id=?',[$assignmentOwner,$assignmentTask]);
essentialCheck(!isset($assignmentRead()['assignee_name']),'native removal never resurrects historical responsible staff');
$db->query('DELETE FROM '.$p.'company_task_assignees WHERE issuer_id=? AND task_id=?',[$a,$projectTaskIds[$a]]);
$db->query('INSERT INTO '.$p.'company_task_assignees(issuer_id,task_id,staff_id,active,revision,origin) VALUES(?,?,7,1,1,?)',[$a,$projectTaskIds[$a],'native']);
essentialCheck(!isset($assignmentRead()['assignee_name']),'other issuer assignment cannot supply a private task member');
$db->query('UPDATE '.$p.'company_task_assignees SET active=1 WHERE issuer_id=? AND task_id=?',[$assignmentOwner,$assignmentTask]);
$assignmentFlags=$all433;$assignmentFlags['view_team_members']='0';$save433($assignmentOwner,$assignmentFlags,'native-assignment-team-disabled');
essentialCheck(!isset($assignmentRead()['assignee_name']),'team flag suppresses canonical member names too');
$save433($assignmentOwner,$all433,'native-assignment-team-restored');

// Historical removals do not consume the active-team result limit.
for($historyStaff=10000;$historyStaff<10501;$historyStaff++)$db->query('INSERT INTO '.$p.'company_task_assignees(issuer_id,task_id,staff_id,active,revision,origin) VALUES(?,?,?,0,1,?)',[$assignmentOwner,$assignmentTask,$historyStaff,'explicit_membership']);
essentialCheck($assignmentRead()['assignee_name']===$assignmentName,'501 inactive historical assignments do not block one active authorized member');
$db->query('DELETE FROM '.$p.'company_task_assignees WHERE issuer_id=? AND task_id=? AND staff_id>=10000',[$assignmentOwner,$assignmentTask]);
