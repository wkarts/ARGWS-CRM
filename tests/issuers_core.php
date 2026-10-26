<?php
/** Native core absorption on synthetic PDO data; no module activation or external transport. */
require __DIR__.'/issuers_core_support.php';
foreach([
 ['','','pending'],['Legacy label','malformed-CNPJ','pending'],['Fiscal synthetic','12.345.678/0001-95','complete'],[str_repeat('x',200),'52998224725','pending'],
] as[$name,$vat,$status]) {
 $f=essentialFixture('1.0.2',1);$p=$f->prefix;$db=$f->db;
 $db->query("UPDATE {$p}options SET value=? WHERE name='companyname'",[$name]);$db->query("INSERT INTO {$p}options VALUES('invoice_company_name',?),('company_vat',?)",[$name,$vat]);$before=essentialLegacy($f);$db->queries=[];
 essentialCheck(!IssuerCore::ready($db,$p),'pending core returns false');essentialCheck($db->writes()===[],'readiness never performs DDL/writes');
 IssuerCore::reconcile($db,$p);essentialCheck(IssuerCore::ready($db,$p),'controlled native reconcile completes');
 $identity=$db->query("SELECT * FROM {$p}issuers_installation_identity WHERE id=1")->row();$id=(int)$identity->primary_issuer_id;
 $company=$db->query("SELECT * FROM {$p}issuers_registry WHERE id=?",[$id])->row();
 essentialCheck($company->identity_status===$status,'principal explicit complete/pending state');essentialCheck($identity->master_staff_id===null,'ambiguous legacy master never inferred from administrator7');
 essentialCheck(json_decode($identity->legacy_identity_json,true)['company_vat']===$vat,'raw malformed/empty VAT preserved without invention');
 essentialCheck(!str_contains($identity->legacy_identity_json,'smtp')&&!str_contains($identity->legacy_identity_json,'legacy-test-only'),'principal identity snapshot excludes provider/SMTP settings');
 essentialCheck($company->settings_json==='{}','new company does not copy global options');essentialCheck(essentialLegacy($f)===$before,'all legacy configuration, clients and financial rows preserved');
 essentialCheck((int)essentialRow($f)->active===0&&essentialRow($f)->installed_version==='1.0.2','retired module marker preserved and deactivated without activation hooks');
 $audit=(int)$db->query("SELECT COUNT(*) AS n FROM {$p}issuers_audit")->row()->n;
 IssuerCore::reconcile($db,$p);essentialCheck((int)$db->query("SELECT primary_issuer_id FROM {$p}issuers_installation_identity WHERE id=1")->row()->primary_issuer_id===$id,'principal ID stable on replay');essentialCheck((int)$db->query("SELECT COUNT(*) AS n FROM {$p}issuers_audit")->row()->n===$audit,'replay does not duplicate principal audit');
 IssuerCore::bindInstallationMaster($db,$p,7);essentialCheck((new IssuerResourceAccess($db,$p))->isMaster(7),'installer-proven actual ID becomes master');essentialCheck((int)$db->query("SELECT primary_issuer_id FROM {$p}issuers_installation_identity WHERE id=1")->row()->primary_issuer_id===$id,'master binding preserves principal');
 $db->queries=[];essentialCheck(IssuerCore::ready($db,$p),'readiness remains true');essentialCheck($db->writes()===[],'ready on complete schema remains read-only');
}
$f=essentialFixture('1.0.2',1);$p=$f->prefix;$db=$f->db;
// Existing registry identity must retain its actual ID instead of creating a duplicate.
essentialSeedSchema($f);$db->query("INSERT INTO {$p}issuers_registry(id,legal_name,tax_id,active,settings_json,created_by,created_at) VALUES(41,'Already registered','12345678000195',1,'{}',7,'2026-10-18 00:00:00')");$db->query("INSERT INTO {$p}options VALUES('company_vat','12.345.678/0001-95')");
IssuerCore::reconcile($db,$p);essentialCheck((int)$db->query("SELECT primary_issuer_id FROM {$p}issuers_installation_identity WHERE id=1")->row()->primary_issuer_id===41,'valid unique tax identity reuses existing issuer ID');essentialCheck((int)$db->query("SELECT COUNT(*) AS n FROM {$p}issuers_registry")->row()->n===1,'no duplicate principal');
// Corrupt pre-existing contextual storage must remain an explicit failure, not be silently repaired.
$db->query("ALTER TABLE {$p}issuers_resource_permissions RENAME TO {$p}saved_resource_permissions");$db->query("CREATE TABLE {$p}issuers_resource_permissions(issuer_id int unsigned NOT NULL,staff_id int NOT NULL,feature varchar(100) NOT NULL,capability varchar(100) NOT NULL)");IssuerCore::invalidate($db);$db->queries=[];
essentialCheck(!IssuerCore::ready($db,$p),'incompatible schema fails readiness');essentialCheck($db->writes()===[],'failed readiness is still read-only');essentialThrows(fn()=>IssuerCore::reconcile($db,$p),'issuer_access_primary_key_invalid','reconcile does not repair incompatible ACL storage');
$db->query("DROP TABLE {$p}issuers_resource_permissions");$db->query("ALTER TABLE {$p}saved_resource_permissions RENAME TO {$p}issuers_resource_permissions");IssuerCore::invalidate($db);IssuerCore::reconcile($db,$p);essentialCheck(IssuerCore::ready($db,$p),'explicit fixture repair permits retry');
$db->trans_begin();essentialThrows(fn()=>IssuerCore::reconcile($db,$p),'issuer_outer_transaction_not_allowed','core DDL never commits caller transaction');essentialCheck($db->pdo->inTransaction(),'caller transaction preserved');$db->trans_rollback();
if($db->driver()==='mysql') {
 $db->query("ALTER TABLE {$p}invoices ALTER COLUMN issuer_id SET DEFAULT 0");IssuerCore::invalidate($db);
 essentialCheck(!IssuerCore::ready($db,$p),'numeric default cannot silently adopt future legacy financial rows');
 essentialThrows(fn()=>IssuerFinancialSchema::assertValid($db,$p),'issuer_financial_schema_invalid','SQL NULL compatibility never permits default0');
 $db->query("ALTER TABLE {$p}invoices ALTER COLUMN issuer_id SET DEFAULT NULL");IssuerCore::invalidate($db);
 essentialCheck(IssuerCore::ready($db,$p),'actual MySQL/MariaDB SQL NULL default accepted without changing legacy owners');
 $old=essentialFixture('1.0.2',1);$op=$old->prefix;essentialSeedSchema($old,static fn($sql)=>str_replace('`tax_id` varchar(14) DEFAULT NULL,','`tax_id` varchar(14) NOT NULL,',$sql));
 IssuerCore::reconcile($old->db,$op);essentialCheck(IssuerCore::ready($old->db,$op),'real MySQL absorbs historical NOT NULL registry with empty legacy VAT');
} else { echo "Historical MySQL ALTER NULL upgrade awaits MySQL CI; SQLite fixture does not prove that operation.\n"; }
echo 'Core companies absorption: '.$GLOBALS['essentialChecks'].' checks passed ('.$db->driver().').'.PHP_EOL;
