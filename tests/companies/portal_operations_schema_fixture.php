<?php
/** Additive fixture preparation: all production schema validators run unchanged on MySQL. */
require_once APPPATH.'services/CompanyPortalOperations.php';require_once APPPATH.'services/CompanyOperationPublication.php';require_once APPPATH.'services/CompanyContactOperationAccess.php';require_once APPPATH.'services/CompanyContacts.php';require_once APPPATH.'services/CompanyProjects.php';require_once APPPATH.'services/CompanyProjectExtras.php';require_once APPPATH.'services/CompanyOperationFiles.php';
require_once APPPATH.'services/NativeCompanyTicketCatalogSchema.php';require_once APPPATH.'services/NativeCompanyDepartmentSchema.php';require_once APPPATH.'services/NativeCompanyTicketReplySchema.php';
foreach(['CompanyContractsSchema','CompanyProjectsSchema','CompanyProjectExtrasSchema','CompanyStandaloneTasksSchema','CompanyOperationFilesSchema','NativeCompanyTicketCatalogSchema','NativeCompanyDepartmentSchema','NativeCompanyTicketReplySchema'] as $schema){if($db->driver()==='mysql')$schema::ensure($db,$p);else foreach($schema::definitions($p) as $name=>$parts){if($db->table_exists($p.$name))continue;$db->query(preg_replace('/ CHARACTER SET [a-z0-9_]+ COLLATE [a-z0-9_]+/','','CREATE TABLE `'.$p.$name.'` ('.implode(',',$parts).') ENGINE=InnoDB'));}}
$native=file_get_contents(dirname(__DIR__,2).'/install/database.sql');foreach(['contacts','contact_permissions'] as $name){if($db->table_exists($p.$name))continue;preg_match('/CREATE TABLE `tbl'.preg_quote($name,'/').'` \(.*?\) ENGINE=InnoDB[^;]*;/s',$native,$match);$sql=str_replace('`tbl'.$name.'`','`'.$p.$name.'`',$match[0]);$sql=str_replace('`id` int NOT NULL,','`id` '.($db->driver()==='mysql'?'int NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT').',',$sql);if($db->driver()==='sqlite'){$sql=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$sql);$sql=preg_replace('/enum\([^)]*\)/','varchar(50)',$sql);}$db->query($sql);}
if(!$db->table_exists($p.'company_portal_tokens'))$db->query('CREATE TABLE '.$p.'company_portal_tokens(token_hash varchar(64) PRIMARY KEY,issuer_id int unsigned NOT NULL,contact_id int NOT NULL,client_id int NOT NULL,identity_hash varchar(64) NOT NULL,expires_at datetime NOT NULL,created_at datetime NOT NULL)');
unset($db->data_cache['table_names']);if($db->driver()==='mysql'){CompanyPortalOperationsSchema::ensure($db,$p);CompanyPortalOperationsSchema::ensure($db,$p);}else{
 foreach(CompanyPortalOperationsSchema::TARGETS as $t)$db->query('CREATE UNIQUE INDEX IF NOT EXISTS '.$p.$t['files'].'_portal_origin ON '.$p.$t['files'].'(issuer_id,'.$t['field'].',file_key)');$db->query('CREATE UNIQUE INDEX IF NOT EXISTS '.$p.'reply_portal_origin ON '.$p.'company_ticket_replies(issuer_id,ticket_id,id)');
 foreach(CompanyPortalOperationsSchema::definitions($p) as $name=>$parts){if($db->table_exists($p.$name))continue;$db->query(preg_replace('/ CHARACTER SET [a-z0-9_]+ COLLATE [a-z0-9_]+/','','CREATE TABLE `'.$p.$name.'` ('.implode(',',$parts).') ENGINE=InnoDB'));}
}unset($db->data_cache['table_names']);

require_once APPPATH.'services/CompanyPortalSupportWriteSchema.php';
if($db->driver()==='mysql')CompanyPortalSupportWriteSchema::ensure($db,$p);else{
 $db->query('CREATE UNIQUE INDEX IF NOT EXISTS '.$p.'portal_support_client ON '.$p.'company_tickets(issuer_id,id,client_id)');$db->query('CREATE UNIQUE INDEX IF NOT EXISTS '.$p.'portal_support_contact ON '.$p.'contacts(userid,id)');
 foreach(CompanyPortalSupportWriteSchema::definitions($p) as $name=>$parts){if($db->table_exists($p.$name))continue;$db->query(preg_replace('/ CHARACTER SET [a-z0-9_]+ COLLATE [a-z0-9_]+/','','CREATE TABLE `'.$p.$name.'` ('.implode(',',$parts).') ENGINE=InnoDB'));}
}unset($db->data_cache['table_names']);

require_once APPPATH.'services/CompanyPortalSupportFilesSchema.php';if($db->driver()==='mysql')CompanyPortalSupportFilesSchema::ensure($db,$p);else foreach(CompanyPortalSupportFilesSchema::definitions($p) as $name=>$parts){if($db->table_exists($p.$name))continue;$db->query(preg_replace('/ CHARACTER SET [a-z0-9_]+ COLLATE [a-z0-9_]+/','','CREATE TABLE `'.$p.$name.'` ('.implode(',',$parts).') ENGINE=InnoDB'));}unset($db->data_cache['table_names']);

require_once APPPATH.'services/CompanyContractDiscussionsSchema.php';if($db->driver()==='mysql')CompanyContractDiscussionsSchema::ensure($db,$p);else{$db->query('CREATE UNIQUE INDEX IF NOT EXISTS '.$p.'portal_contract_client ON '.$p.'company_contracts(issuer_id,id,client_id)');foreach(CompanyContractDiscussionsSchema::definitions($p) as $name=>$parts){if($db->table_exists($p.$name))continue;$db->query(preg_replace('/ CHARACTER SET [a-z0-9_]+ COLLATE [a-z0-9_]+/','','CREATE TABLE `'.$p.$name.'` ('.implode(',',$parts).') ENGINE=InnoDB'));}}unset($db->data_cache['table_names']);

require_once APPPATH.'services/CompanyProjectPortalRulesSchema.php';if($db->driver()==='mysql')CompanyProjectPortalRulesSchema::ensure($db,$p);else{$db->query('CREATE UNIQUE INDEX IF NOT EXISTS '.$p.'portal_task_origin ON '.$p.'company_tasks(issuer_id,project_id,id)');foreach(CompanyProjectPortalRulesSchema::definitions($p) as $name=>$parts){if($db->table_exists($p.$name))continue;$db->query(preg_replace('/ CHARACTER SET [a-z0-9_]+ COLLATE [a-z0-9_]+/','','CREATE TABLE `'.$p.$name.'` ('.implode(',',$parts).') ENGINE=InnoDB'));}}unset($db->data_cache['table_names']);

require_once APPPATH.'services/CompanyPortalTaskWriteSchema.php';if($db->driver()==='mysql')CompanyPortalTaskWriteSchema::ensure($db,$p);else{foreach(['company_task_comments'=>['portal_comment_origin'=>'issuer_id,task_id,id'],'company_project_files'=>['portal_task_file_origin'=>'issuer_id,project_id,task_id,file_key']] as $table=>$indexes)foreach($indexes as $name=>$cols)$db->query('CREATE UNIQUE INDEX IF NOT EXISTS '.$p.$name.' ON '.$p.$table.'('.$cols.')');foreach(CompanyPortalTaskWriteSchema::definitions($p) as $name=>$parts){if($db->table_exists($p.$name))continue;$db->query(preg_replace('/ CHARACTER SET [a-z0-9_]+ COLLATE [a-z0-9_]+/','','CREATE TABLE `'.$p.$name.'` ('.implode(',',$parts).') ENGINE=InnoDB'));}}unset($db->data_cache['table_names']);


// New436 portal readers remain opt-in; controllers still require their installed schema.
foreach(['CompanyProjectMilestonesSchema','CompanyProjectTimesheetsSchema','CompanyProjectGanttSchema','CompanyProjectContactFilesSchema'] as $schema){
 require_once APPPATH.'services/'.$schema.'.php';
 if($db->driver()==='mysql')$schema::ensure($db,$p);else{
  if($schema==='CompanyProjectMilestonesSchema')$db->query('CREATE UNIQUE INDEX IF NOT EXISTS '.$p.'portal_milestone_origin ON '.$p.'company_project_milestones(issuer_id,project_id,id)');
  foreach($schema::definitions($p) as $name=>$parts){if($db->table_exists($p.$name))continue;$db->query(preg_replace('/ CHARACTER SET [a-z0-9_]+ COLLATE [a-z0-9_]+/','','CREATE TABLE `'.$p.$name.'` ('.implode(',',$parts).') ENGINE=InnoDB'));}
 }
 unset($db->data_cache['table_names']);
}

// Rebuilt recovery fixture: the current project GET also validates native437/438 schemas,
// even when the new discussion capabilities retain their default disabled rules.
foreach(['CompanyProjectDiscussionsSchema','CompanyProjectDiscussionExtrasSchema'] as $schema){
 require_once APPPATH.'services/'.$schema.'.php';
 if($db->driver()==='mysql'){$schema::ensure($db,$p);$schema::ensure($db,$p);}else{
  foreach($schema::definitions($p) as $name=>$parts){if($db->table_exists($p.$name))continue;$db->query(preg_replace('/ CHARACTER SET [a-z0-9_]+ COLLATE [a-z0-9_]+/','','CREATE TABLE `'.$p.$name.'` ('.implode(',',$parts).') ENGINE=InnoDB'));}
 }
 unset($db->data_cache['table_names']);
}
