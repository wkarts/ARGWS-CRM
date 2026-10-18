<?php
// Real maintenance entry points and SQL, stopping approved legacy deletion at its first external hook.
ob_start(); require __DIR__.'/invoice_issuer_scope.php'; ob_end_clean();
if(!defined('APPPATH'))define('APPPATH',dirname(__DIR__).'/application/');
require __DIR__.'/../application/models/Staff_model.php';
require_once __DIR__.'/../application/services/ClientIssuerScope.php';
require_once __DIR__.'/../application/services/CustomerRelationBoundary.php';
require __DIR__.'/../application/models/Projects_model.php';
require __DIR__.'/../application/services/CustomerProfileBadges.php';
class MaintenanceDb extends CoreIssuerDb {
    public function count_all_results($table): int { return parent::count_all_results(str_starts_with($table,db_prefix())?$table:db_prefix().$table); }
}
class MaintenanceStaff extends Staff_model { public function __construct() {} }
class MaintenanceProjects extends Projects_model { public function __construct() {} }
class MaintenanceHookReached extends RuntimeException {}
class MaintenanceHooks extends CoreIssuerHooks {
    public array $actions=[]; public bool $stop=true; public $during=null;
    public function do_action($name,...$args) { $this->actions[]=$name;if($this->during){($this->during)();return;}if($this->stop)throw new MaintenanceHookReached($name); }
}
$db=new MaintenanceDb($pdo);$GLOBALS['db']=$db;$GLOBALS['instance']->db=$db;
$db->query("ALTER TABLE {$prefix}projects ADD clientid INT");
$db->query("INSERT INTO {$prefix}clients VALUES(500,'Legacy maintenance')");
$db->query("INSERT INTO {$prefix}projects(id,name,clientid) VALUES(11,'Legacy',500),(12,'Legacy hook',500),(22,'Named X',500),(33,'Named Y',500),(44,'Invalid zero',500)");
$db->query("ALTER TABLE {$prefix}invoices ADD addedfrom INT");
$db->query("ALTER TABLE {$prefix}invoices ADD sale_agent INT");
$db->query("CREATE TABLE {$prefix}credits(invoice_id INT,staff_id INT)");
$db->query("INSERT INTO {$prefix}invoices(id,issuer_id,clientid,project_id,addedfrom,sale_agent,status) VALUES (101,NULL,500,11,51,51,1),(102,101,500,22,42,0,1),(103,202,500,33,0,43,1),(104,0,500,44,44,0,1)");
$db->query("INSERT INTO {$prefix}credits VALUES(103,45)");
$staff=new MaintenanceStaff();$staff->db=$db;$projects=new MaintenanceProjects();$projects->db=$db;$checks=0;
foreach([42,43,44,45] as $staffId){
 $GLOBALS['hooks']=new MaintenanceHooks();
 same(false,$staff->delete($staffId,60),'named invoice attribution blocks staff transfer');
 same([],$GLOBALS['hooks']->actions,'staff denial before hooks or unrelated writes');
}
foreach([22,33,44] as $projectId){
 $GLOBALS['hooks']=new MaintenanceHooks();
 same(false,$projects->delete($projectId),'named invoice blocks project deletion');
 same([],$GLOBALS['hooks']->actions,'project denial before hooks or unrelated deletion');
}
function reachesMaintenanceHook($call,$hook){try{$call();throw new RuntimeException('missing approved hook');}catch(MaintenanceHookReached $e){same($hook,$e->getMessage(),'legacy maintenance retains existing entry');}}
$GLOBALS['hooks']=new MaintenanceHooks();reachesMaintenanceHook(fn()=>$staff->delete(51,60),'before_delete_staff_member');
$GLOBALS['hooks']=new MaintenanceHooks();reachesMaintenanceHook(fn()=>$projects->delete(11),'before_project_deleted');
// A hook that creates a new named reference cannot smuggle it into the later bulk update.
$GLOBALS['hooks']=new MaintenanceHooks();$GLOBALS['hooks']->during=fn()=>$db->query("UPDATE {$prefix}invoices SET addedfrom=52 WHERE id=102");
same(false,$staff->delete(52,60),'staff rechecks new reference after hook');
$GLOBALS['hooks']=new MaintenanceHooks();$GLOBALS['hooks']->during=fn()=>$db->query("UPDATE {$prefix}invoices SET project_id=12 WHERE id=102");
same(false,$projects->delete(12),'project rechecks new reference after hook');
// Badges are data disclosures too: counts must not include X/Y/invalid zero.
$badge=new app\services\CustomerProfileBadges(500);
same(1,$badge->invoices(),'invoice badge count legacy only');
foreach(['estimates'=>'id INT,clientid INT','contracts'=>'id INT,client INT','tickets'=>'ticketid INT,userid INT','expenses'=>'id INT,clientid INT','proposals'=>'id INT,rel_id INT,rel_type VARCHAR(20)'] as $table=>$columns)$db->query("CREATE TABLE {$prefix}{$table}({$columns})");

$db->query("CREATE TABLE {$prefix}tasks(id INT,rel_id INT,rel_type VARCHAR(20),datefinished VARCHAR(20))");
$db->query("INSERT INTO {$prefix}tasks VALUES(1,101,'invoice',NULL),(2,102,'invoice',NULL),(3,103,'invoice',NULL),(4,104,'invoice',NULL),(5,999,'invoice',NULL)");
same(1,$badge->tasks(),'task badge excludes named, invalid and orphan invoices');
$db->query("ALTER TABLE {$prefix}invoices DROP COLUMN issuer_id");
$GLOBALS['hooks']=new MaintenanceHooks();reachesMaintenanceHook(fn()=>$staff->delete(43,60),'before_delete_staff_member');
$GLOBALS['hooks']=new MaintenanceHooks();reachesMaintenanceHook(fn()=>$projects->delete(33),'before_project_deleted');
same(4,$badge->invoices(),'pre-migration invoice badge preserved');
same(4,$badge->tasks(),'pre-migration task badge preserved');
echo 'Invoice maintenance and badge boundaries: '.$checks.' checks passed ('.$pdo->getAttribute(PDO::ATTR_DRIVER_NAME).").\n";
