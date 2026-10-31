<?php
require __DIR__.'/native_task_index_projection.php';
class CI_Migration {public $db;}
require APPPATH.'migrations/476_version_476.php';
require_once APPPATH.'services/NativeCompanyTaskItemSchema.php';
$fileFixture=!$db->table_exists($p.'company_ticket_files');if($fileFixture)$db->query('CREATE TABLE `'.$p.'company_ticket_files` (`file_key` varchar(64) '.($db->driver()==='mysql'?'CHARACTER SET ascii COLLATE ascii_bin ':'').'NOT NULL PRIMARY KEY)'.($db->driver()==='mysql'?' ENGINE=InnoDB':''));
register_shutdown_function(static function()use($db,$p,$fileFixture){if($db->driver()==='mysql'){foreach(array_merge(['company_ticket_reply_files'],array_reverse(array_keys(NativeCompanyTaskItemSchema::definitions($p))),$fileFixture?['company_ticket_files']:[])as$table)$db->pdo->exec('DROP TABLE IF EXISTS '.$p.$table);}});
$before=$db->query('SELECT issuer_id,id,checklist_json FROM '.$p.'company_tasks ORDER BY id')->result_array();
$beforeStandalone=$db->query('SELECT issuer_id,id,checklist_json FROM '.$p.'company_standalone_tasks ORDER BY id')->result_array();
$migration=new Migration_Version_476();$migration->db=$db;$migration->up();$migration->up();
essentialCheck($db->query('SELECT issuer_id,id,checklist_json FROM '.$p.'company_tasks ORDER BY id')->result_array()===$before&&$db->query('SELECT issuer_id,id,checklist_json FROM '.$p.'company_standalone_tasks ORDER BY id')->result_array()===$beforeStandalone,'Migration replay preserves all preexisting checklist JSON');
foreach(NativeCompanyTaskItemSchema::definitions($p)as$table=>$definition)essentialCheck($db->table_exists($p.$table),'Checklist table exists after migration and replay');
try{$migration->down();throw new RuntimeException('Unsafe downgrade accepted');}catch(RuntimeException$e){essentialCheck($e->getMessage()==='native_company_schema_downgrade_requires_review','Downgrade cannot delete checklist history');}
$db->query('ALTER TABLE '.$p.'company_project_checklist_state ADD COLUMN unexpected int NULL');
essentialThrows(fn()=>$migration->up(),'native_task_item_schema_invalid','Replay rejects an unexpected existing checklist schema');
echo 'Checklist migration 476 replay: '.$GLOBALS['essentialChecks']." cumulative checks passed (".$db->driver()."); no checklist projection or UI claim.\n";
