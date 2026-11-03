<?php
/** Migration repair and actual composite FK creation on disposable MariaDB/MySQL. */
register_shutdown_function(static function():void{if(!class_exists('CompanyProjectContactFilesSchema',false)||!isset($GLOBALS['db'],$GLOBALS['p'])||!preg_match('/^essential_[a-f0-9]{10}_$/D',$GLOBALS['p']))return;foreach(array_reverse(array_keys(CompanyProjectContactFilesSchema::definitions($GLOBALS['p']))) as$table)$GLOBALS['db']->query('DROP TABLE IF EXISTS '.$GLOBALS['p'].$table);});
require __DIR__.'/native_project_gantt.php';
if($db->driver()!=='mysql')throw new RuntimeException('MySQL fixture required');
require_once APPPATH.'services/CompanyProjectContactFilesSchema.php';
if(!class_exists('CI_Migration',false)){class CI_Migration{public $db;}}
require APPPATH.'migrations/478_version_478.php';$migration=new Migration_Version_478();$migration->db=$db;
$old=$p.'old_';
try{
    CompanyProjectContactFilesSchema::ensureParentIndexes($db,$old);CompanyProjectContactFilesSchema::ensureParentIndexes($db,$old);
    $db->query('CREATE TABLE '.$old.'contacts(id INT PRIMARY KEY,userid INT) ENGINE=InnoDB');
    CompanyProjectContactFilesSchema::ensureParentIndexes($db,$old);
    $db->query('CREATE TABLE '.$old.'company_project_contact_files(file_key VARCHAR(64)) ENGINE=InnoDB');
    essentialThrows(fn()=>CompanyProjectContactFilesSchema::ensureParentIndexes($db,$old),'issuer_project_contact_files_schema_invalid','partial historic extension with missing parent fails closed');
}finally{$db->query('DROP TABLE IF EXISTS '.$old.'company_project_contact_files');$db->query('DROP TABLE IF EXISTS '.$old.'contacts');}
$before=$db->query('SELECT * FROM '.$p.'company_project_files ORDER BY file_key')->result_array();
$db->query('ALTER TABLE '.$p.'company_project_files ADD UNIQUE KEY project_contact_file_origin (issuer_id,file_key,project_id)');
essentialThrows(fn()=>$migration->up(),'issuer_project_contact_files_schema_invalid','migration rejects an incompatible pre-existing named index');
$db->query('ALTER TABLE '.$p.'company_project_files DROP INDEX project_contact_file_origin');
$migration->up();$migration->up();
$index=$db->query('SELECT COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? ORDER BY SEQ_IN_INDEX',[$p.'company_project_files','project_contact_file_origin'])->result_array();
essentialCheck(array_column($index,'COLUMN_NAME')===['issuer_id','project_id','file_key']&&count(array_filter($index,fn($r)=>(int)$r['NON_UNIQUE']!==0||$r['SUB_PART']!==null))===0,'repair creates exact unique parent key');
essentialCheck($db->query('SELECT * FROM '.$p.'company_project_files ORDER BY file_key')->result_array()===$before,'migration replay preserves project file data');
CompanyProjectContactFilesSchema::ensure($db,$p);CompanyProjectContactFilesSchema::assertValid($db,$p);
$fks=$db->query('SELECT COLUMN_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME=? ORDER BY ORDINAL_POSITION',[$p.'company_project_contact_files',$p.'company_project_files'])->result_array();
essentialCheck(array_column($fks,'COLUMN_NAME')===['issuer_id','project_id','file_key']&&array_column($fks,'REFERENCED_COLUMN_NAME')===['issuer_id','project_id','file_key'],'composite child foreign key actually exists on InnoDB');
essentialThrows(fn()=>$migration->down(),'native_company_schema_downgrade_requires_review','automatic downgrade is forbidden');
echo "Project contact composite parent indexes and migration replay passed on MySQL.\n";
