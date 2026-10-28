<?php
/** SQLite validates existing schema without MySQL-specific index ALTER statements. */
require __DIR__.'/native_project_form_state.php';
if($db->driver()!=='sqlite')throw new RuntimeException('SQLite fixture required');
if(!class_exists('CI_Migration',false)){class CI_Migration{public $db;}}
require APPPATH.'migrations/478_version_478.php';$migration=new Migration_Version_478();$migration->db=$db;
$before=$db->query('SELECT * FROM '.$p.'company_project_files ORDER BY file_key')->result_array();
$migration->up();$migration->up();
essentialCheck($db->query('SELECT * FROM '.$p.'company_project_files ORDER BY file_key')->result_array()===$before,'migration replay preserves existing project files');
$empty=new EssentialTestDb(new PDO('sqlite::memory:'),'old_');
CompanyProjectContactFilesSchema::ensureParentIndexes($empty,'old_');CompanyProjectContactFilesSchema::ensureParentIndexes($empty,'old_');
$empty->query('CREATE TABLE old_contacts(id INT PRIMARY KEY,userid INT)');
CompanyProjectContactFilesSchema::ensureParentIndexes($empty,'old_');
essentialCheck(true,'historic installation without optional project-file schema upgrades and replays');
$empty->query('CREATE TABLE old_company_project_contact_files(file_key TEXT)');
essentialThrows(fn()=>CompanyProjectContactFilesSchema::ensureParentIndexes($empty,'old_'),'issuer_project_contact_files_schema_invalid','partial extension with missing parent fails closed');
essentialThrows(fn()=>$migration->down(),'native_company_schema_downgrade_requires_review','automatic downgrade is forbidden');
echo "Project contact index migration SQLite replay passed; MySQL index DDL tested separately.\n";
