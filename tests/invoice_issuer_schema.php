<?php
/** Native forward schema test. SQLite exercises data preservation, not MySQL ALTER semantics. */
require __DIR__.'/issuers_core_support.php';
class CI_Migration { public $db; }
require APPPATH.'migrations/404_version_404.php';
$f=essentialFixture('1.0.2',1);$db=$f->db;$pdo=$db->pdo;$prefix=$f->prefix;
$before=essentialLegacy($f);
$migration=new Migration_Version_404();$migration->db=$db;
$migration->up();$migration->up();$migration->down();
essentialCheck(IssuerCore::ready($db,$prefix),'native core migration reconciles complete company schema');
essentialCheck(essentialLegacy($f)===$before,'core upgrade preserves existing finance and options');
essentialCheck($db->field_exists('issuer_id',$prefix.'invoices'),'owner field exists');
essentialCheck($db->query('SELECT issuer_id FROM '.$prefix.'invoices WHERE id=42')->row()->issuer_id===null,'legacy ownership remains unresolved rather than invented');
essentialCheck($db->table_exists($prefix.'issuers_invoice_documents') && $db->table_exists($prefix.'issuers_sequences'),'immutable document and sequence schema exists');
$db->query('UPDATE '.$prefix.'invoices SET issuer_id=17 WHERE id=42');
$migration->down();
essentialCheck((int)$db->query('SELECT issuer_id FROM '.$prefix.'invoices WHERE id=42')->row()->issuer_id===17,'rollback never erases ownership');
echo 'Native company invoice schema: '.$GLOBALS['essentialChecks'].' checks passed ('.$db->driver().').'.PHP_EOL;
