<?php
/** Composed recurrence + native companies rehearsal on a disposable MySQL database. */
require __DIR__.'/issuers_core_support.php';
class CI_Migration { public $db; }
require APPPATH.'migrations/395_version_395.php';
require APPPATH.'migrations/404_version_404.php';
$f=essentialFixture('1.0.2',1);$db=$f->db;$pdo=$db->pdo;$prefix=$f->prefix;
if ($db->driver() !== 'mysql') {
    fwrite(STDERR, "Not executed: recurrence 395 -> native companies 404 rehearsal requires MySQL.\n");
    exit(2);
}
$before=essentialLegacy($f);
$recurrence=new Migration_Version_395();$recurrence->db=$db;$recurrence->up();
$db->query("INSERT INTO {$prefix}recurring_rule_batches(token_hash,staff_id,source_type,action,payload_json,created_at,expires_at) VALUES('synthetic-token',7,'rules','pause','{}','2026-10-18 00:00:00','2026-10-19 00:00:00')");
$batch=$db->query('SELECT * FROM '.$prefix.'recurring_rule_batches')->result_array();
$scope=new Migration_Version_404();$scope->db=$db;$scope->up();
$recurrence->up();$scope->up();
essentialCheck(essentialLegacy($f)===$before,'legacy finance, clients and options unchanged');
essentialCheck($db->query('SELECT issuer_id FROM '.$prefix.'invoices WHERE id=42')->row()->issuer_id===null,'legacy ownership never guessed');
essentialCheck($db->query('SELECT * FROM '.$prefix.'recurring_rule_batches')->result_array()===$batch,'recurrence idempotency payload preserved');
foreach(['recurring_rules','recurring_rule_batches','recurring_rule_audit','recurring_rule_runs'] as $table) {
    essentialCheck($db->table_exists($prefix.$table),'recurrence table remains available: '.$table);
}
$indexes=$db->query('SHOW INDEX FROM `'.$prefix.'invoices` WHERE Key_name = ?', ['issuer_scope'])->result_array();
essentialCheck(count($indexes)===2,'two-column issuer index');
$recurrence->down();$scope->down();
essentialCheck($db->query('SELECT * FROM '.$prefix.'recurring_rule_batches')->result_array()===$batch && $db->field_exists('issuer_id',$prefix.'invoices'),'downgrade preserves financial metadata');
// A failed forward migration must remain retryable and must not claim readiness.
$failure=essentialFixture('1.0.2',1);$failure->db->failOn='CREATE TABLE';
$scope->db=$failure->db;
essentialThrows(fn()=>$scope->up(),'issuer_storage_failed','DDL failure is visible');
$failure->db->failOn=null;$scope->up();
essentialCheck(IssuerCore::ready($failure->db,$failure->prefix),'retry completes after explicit storage recovery');
echo 'Invoice issuer composed upgrade: '.$GLOBALS['essentialChecks']." checks passed (MySQL).\n";
