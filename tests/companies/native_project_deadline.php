<?php
require __DIR__.'/projects_operations.php';
require_once APPPATH.'services/NativeCompanyProjectDeadlineSchema.php';
require_once APPPATH.'services/CompanyProjectExtras.php';
$old=$db->query('SELECT * FROM '.$p.'company_projects ORDER BY id')->result_array();
if($db->driver()==='sqlite'){
    // SQLite validates consumers on the exact evolved shape; it does NOT execute/prove MySQL ALTER.
    essentialThrows(fn()=>NativeCompanyProjectDeadlineSchema::ensure($db,$p),'native_project_deadline_mysql_migration_required','SQLite never pretends to execute the production migration');
    $table=$p.'company_projects';$ddl=$db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name=?",[$table])->row_array()['sql'];
    $ddl=str_replace('`due_date` date NOT NULL','`due_date` date NULL',$ddl);$ddl=str_replace('`'.$table.'`','`'.$table.'_nullable_fixture`',$ddl);
    $db->query('PRAGMA foreign_keys=OFF');$db->query($ddl);$db->query('INSERT INTO '.$table.'_nullable_fixture SELECT * FROM '.$table);$db->query('DROP TABLE '.$table);$db->query('ALTER TABLE '.$table.'_nullable_fixture RENAME TO '.$table);$db->query('PRAGMA foreign_keys=ON');
    essentialCheck($db->query('PRAGMA foreign_key_check')->result_array()===[],'Fixture schema keeps actual references intact');
}else NativeCompanyProjectDeadlineSchema::ensure($db,$p);
NativeCompanyProjectDeadlineSchema::ensure($db,$p);NativeCompanyProjectDeadlineSchema::assertValid($db,$p);
essentialCheck($db->query('SELECT * FROM '.$p.'company_projects ORDER BY id')->result_array()===$old,'Existing recorded project dates and all fields remain byte-identical');
$open=$ps->create($a,7,array_replace($input,['name'=>'Native no deadline','due_date'=>null]),$key('native-no-deadline'));
essentialCheck($ps->get($a,7,$open)['due_date']===null,'Native optional deadline stays SQL NULL, never a sentinel date');
$task=$ts->create($a,7,['project_id'=>$open,'assignee_id'=>7,'name'=>'Dated task in open-ended project','description'=>'','due_date'=>'2028-01-20','status'=>'todo','checklist'=>[]],$key('native-no-deadline-task'));
essentialCheck($ts->get($a,7,$task)['due_date']==='2028-01-20','A real dated task has no invented parent upper bound');
essentialThrows(fn()=>$ts->create($a,7,['project_id'=>$open,'assignee_id'=>7,'name'=>'Too early','description'=>'','due_date'=>'2025-01-20','status'=>'todo','checklist'=>[]],$key('native-before-start')),'company_project_invalid_input','Start date bound is preserved');
$extras=new CompanyProjectExtras($db,$p);$extras->milestone($a,7,$open,1,$key('native-open-milestone'),['name'=>'Future milestone','due_date'=>'2028-02-20','done'=>0],null);
$record=$ps->get($a,7,$open);$rev=(int)$record['revision'];
essentialThrows(fn()=>$ps->update($a,7,$open,$rev,array_replace($input,['due_date'=>'2027-01-01']),$key('native-constrain-existing-task')),'company_project_invalid_input','A newly declared deadline cannot silently invalidate existing tasks/milestones');
$ps->update($a,7,$open,$rev,array_replace($input,['due_date'=>'2029-01-01']),$key('native-constrain-valid'));
essentialCheck($ps->get($a,7,$open)['due_date']==='2029-01-01','Explicit valid deadline can be declared later');
$ps->update($a,7,$open,$rev+1,array_replace($input,['due_date'=>null]),$key('native-remove-deadline'));
essentialCheck($ps->get($a,7,$open)['due_date']===null,'Removing a deadline does not delete dated children');
essentialCheck(count($db->query('SELECT id FROM '.$p.'company_tasks WHERE issuer_id=? AND project_id=?',[$a,$open])->result_array())===1,'Child task unchanged by deadline edits');
if($db->driver()==='mysql'){
    $db->query('ALTER TABLE '.$p."company_projects MODIFY due_date DATE NULL DEFAULT '2000-01-01'");
    essentialThrows(fn()=>NativeCompanyProjectDeadlineSchema::assertValid($db,$p),'issuer_project_schema_invalid','Unexpected default is not accepted as nullable migration');
    $db->query('ALTER TABLE '.$p.'company_projects MODIFY due_date DATE NULL');NativeCompanyProjectDeadlineSchema::assertValid($db,$p);
}
echo 'Native project optional deadline: '.$GLOBALS['essentialChecks'].' cumulative checks passed ('.$db->driver().").\n";
