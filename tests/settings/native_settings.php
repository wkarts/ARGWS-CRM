<?php
/** Synthetic PDO integration. No external service, credentials, customer or production data. */
require dirname(__DIR__) . '/issuers_core_support.php';
require APPPATH . 'services/CompanySettingsRuntime.php';
require_once APPPATH . 'services/CompanySettingsIdentity.php';

function settingThrows(callable $operation, string $expected, string $label): void
{
    $message = null;
    try { $operation(); } catch (Throwable $error) { $message = $error->getMessage(); }
    essentialCheck($message === $expected, $label . ' (' . var_export($message, true) . ')');
}
$f = essentialFixture('1.0.2', 1); $db = $f->db; $p = $f->prefix;
IssuerCore::reconcile($db, $p);
IssuerCore::bindInstallationMaster($db, $p, 7);
$primary = (new IssuerResourceAccess($db, $p))->primaryId();
$db->query("INSERT INTO {$p}issuers_registry(id,legal_name,tax_id,active,settings_json,created_by,created_at,identity_status) VALUES(41,'Synthetic A','12345678000195',1,'{}',7,'2026-10-18 00:00:00','complete'),(82,'Synthetic B','52998224725',1,'{}',7,'2026-10-18 00:00:00','complete')");
$db->query("INSERT INTO {$p}staff VALUES(12,1,0,NULL),(13,1,1,NULL),(14,1,0,NULL)");
$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(41,12,1),(82,12,1),(41,14,1)");
foreach ([41,82] as $issuer) { foreach (['view','edit','delete'] as $cap) { $db->query("INSERT INTO {$p}issuers_resource_permissions VALUES(?,12,'settings',?)", [$issuer,$cap]); } }
$store = new CompanySettingsStorage($db, $p);
$identity = new CompanySettingsIdentity($db, $p);
$before = essentialLegacy($f);
essentialCheck(!CompanySettingsRuntime::active(), 'no implicit context');
settingThrows(fn() => CompanySettingsRuntime::get('smtp_password'), 'company_settings_context_required', 'unbound runtime cannot infer installation');
foreach ([41,82] as $issuer) {
    foreach (['smtp_password','smtp_host','payment_account','connect_token','unknown_provider_secret'] as $key) {
        essentialCheck($store->get($issuer,$key) === '', 'new company does not inherit ' . $key);
    }
}
CompanySettingsRuntime::run($db,$p,41,12,function () {
    essentialCheck(CompanySettingsRuntime::issuerId() === 41 && CompanySettingsRuntime::staffId() === 12, 'explicit context visible');
    essentialCheck(CompanySettingsRuntime::can('edit'), 'company ACL does not AND global staff settings permission');
    essentialCheck(CompanySettingsRuntime::add('smtp_password','encrypted-a-test'), 'A creates own SMTP value');
    essentialCheck(CompanySettingsRuntime::add('connect_token','connect-a-test'), 'A creates own Connect value');
    essentialCheck(CompanySettingsRuntime::add('payment_account','bank-a-test'), 'A creates own bank value');
    essentialCheck(!CompanySettingsRuntime::add('smtp_password','replace-denied'), 'add never overwrites existing private option');
    essentialCheck(CompanySettingsRuntime::exists('smtp_password'), 'A private exists');
    settingThrows(fn() => CompanySettingsRuntime::run($GLOBALS['CI']->db,db_prefix(),82,12,fn() => null), 'company_settings_scope_change_denied', 'nested scope cannot switch companies');
    essentialCheck(CompanySettingsRuntime::get('smtp_password') === 'encrypted-a-test', 'scope unchanged after rejected nesting');
});
essentialCheck(!CompanySettingsRuntime::active(), 'scope restored after callback');
CompanySettingsRuntime::run($db,$p,82,12,function () {
    essentialCheck(CompanySettingsRuntime::get('smtp_password') === '', 'B cannot see A SMTP value');
    essentialCheck(CompanySettingsRuntime::update('smtp_password','encrypted-b-test'), 'B creates independent SMTP');
    essentialCheck(CompanySettingsRuntime::update('connect_token','connect-b-test'), 'B creates independent Connect');
    essentialCheck(CompanySettingsRuntime::update('payment_account','bank-b-test'), 'B creates independent bank');
    essentialCheck(CompanySettingsRuntime::update('smtp_password',''), 'explicit empty value clears B SMTP');
    essentialCheck(CompanySettingsRuntime::get('smtp_password') === '', 'empty never falls back');
    essentialCheck(CompanySettingsRuntime::exists('smtp_password'), 'empty key still exists');
    essentialCheck(CompanySettingsRuntime::delete('smtp_password'), 'B deletes own option');
    essentialCheck(!CompanySettingsRuntime::exists('smtp_password'), 'B absent after delete');
    essentialCheck(CompanySettingsRuntime::get('smtp_password') === '', 'deleted value never falls back');
    essentialCheck(!CompanySettingsRuntime::delete('smtp_password'), 'delete is idempotent');
    settingThrows(fn() => CompanySettingsRuntime::update('last_cron_run','wrong-company'), 'company_settings_infrastructure_write_denied', 'secondary cannot change installation infrastructure');
});
essentialCheck($store->get(41,'smtp_password') === 'encrypted-a-test', 'B writes did not change A');
essentialCheck(essentialLegacy($f) === $before, 'A/B operations never mutate legacy global data');
CompanySettingsRuntime::run($db,$p,$primary,7,function () {
    essentialCheck(CompanySettingsRuntime::get('smtp_password') === 'synthetic-test-only', 'explicit principal retains legacy SMTP');
    CompanySettingsRuntime::update('smtp_password','primary-encrypted-test');
    essentialCheck(CompanySettingsRuntime::get('smtp_password') === 'primary-encrypted-test', 'principal read-after-write avoids stale shared app cache');
    CompanySettingsRuntime::update('last_cron_run','synthetic-timestamp');
});
essentialCheck($store->get(41,'smtp_password') === 'encrypted-a-test' && $store->get(82,'smtp_password') === '', 'principal write cannot leak to A/B');
essentialCheck($store->get(41,'last_cron_run') === 'synthetic-timestamp', 'only named installation infrastructure is shared');
settingThrows(fn() => CompanySettingsRuntime::run($db,$p,41,13,fn() => null), 'company_settings_access_denied', 'generic global admin is not inferred master/member');
CompanySettingsRuntime::run($db,$p,41,14,function () {
    essentialCheck(!CompanySettingsRuntime::can('view'), 'membership does not grant settings view');
    settingThrows(fn() => CompanySettingsRuntime::update('smtp_password','unauthorized'), 'company_settings_access_denied', 'membership does not grant settings write');
});
$release = CompanySettingsRuntime::enterRequest($db,$p,41,12);
$release(); $release();
essentialCheck(!CompanySettingsRuntime::active(), 'lease release idempotent');
$release2 = CompanySettingsRuntime::enterRequest($db,$p,82,12); $release();
essentialCheck(CompanySettingsRuntime::issuerId() === 82, 'old lease cannot close newer request'); $release2();
settingThrows(fn() => CompanySettingsRuntime::run($db,$p,41,12,fn() => throw new RuntimeException('synthetic_failure')), 'synthetic_failure', 'operation failure propagated');
essentialCheck(!CompanySettingsRuntime::active(), 'finally closes failing operation');
CompanySettingsRuntime::run($db,$p,41,12,function () use ($db,$p) {
    $db->query("UPDATE {$p}issuers_staff SET can_view=0 WHERE issuer_id=41 AND staff_id=12");
    settingThrows(fn() => CompanySettingsRuntime::get('smtp_password'), 'company_settings_access_denied', 'revocation during request fails closed');
    settingThrows(fn() => CompanySettingsRuntime::update('smtp_password','revoked'), 'company_settings_access_denied', 'revoked context never falls back to legacy');
});
$db->query("UPDATE {$p}issuers_staff SET can_view=1 WHERE issuer_id=41 AND staff_id=12");
CompanySettingsRuntime::run($db,$p,41,12,function () use ($db,$p) {
    $db->failOn = 'INSERT INTO ' . $p . 'issuers_audit';
    settingThrows(fn() => CompanySettingsRuntime::update('smtp_password','rollback-test'), 'issuer_storage_failed', 'audit failure denies mutation');
    $db->failOn = null;
    essentialCheck(CompanySettingsRuntime::get('smtp_password') === 'encrypted-a-test', 'failed audit rolled back secret update');
});
$db->trans_begin();
settingThrows(fn() => $store->mutate(41,12,'update','smtp_password','outer-tx'), 'issuer_outer_transaction_not_allowed', 'cannot commit caller transaction');
essentialCheck($db->pdo->inTransaction(), 'caller transaction remains owned by caller'); $db->trans_rollback();

// Canonical identity is shared with the registry and preserves legacy pending data.
essentialCheck($identity->read(41)['legal_name'] === 'Synthetic A', 'identity reads registry');
CompanySettingsRuntime::run($db,$p,41,12,function () {
    CompanySettingsRuntime::update('invoice_company_name','Synthetic A Updated');
    essentialCheck(CompanySettingsRuntime::get('companyname') === 'Synthetic A Updated', 'company name aliases share canonical registry value');
    CompanySettingsRuntime::update('invoice_company_city','Private city A');
    essentialCheck(CompanySettingsRuntime::get('invoice_company_city') === 'Private city A', 'profile write uses selected company');
    settingThrows(fn() => CompanySettingsRuntime::update('company_vat','invalid'), 'issuer_invalid_tax_id', 'canonical document validated');
    settingThrows(fn() => CompanySettingsRuntime::update('company_vat','52998224725'), 'issuer_tax_conflict', 'duplicate canonical document denied');
    settingThrows(fn() => CompanySettingsRuntime::delete('company_vat'), 'company_settings_identity_delete_denied', 'identity cannot be erased via option endpoint');
});
essentialCheck($identity->read(41)['profile']['invoice_company_city'] === 'Private city A', 'wizard adapter sees Settings profile');
essentialCheck($identity->read(82)['profile']['invoice_company_city'] === '', 'B profile never inherits A');
$identity->save(41,12,['legal_name'=>'Wizard A','profile'=>['invoice_company_city'=>'Wizard city A']]);
essentialCheck($store->get(41,'invoice_company_name') === 'Wizard A' && $store->get(41,'invoice_company_city') === 'Wizard city A', 'Settings adapter sees wizard changes');
$legacyIdentity = $db->query("SELECT legacy_identity_json FROM {$p}issuers_installation_identity WHERE id=1")->row()->legacy_identity_json;
essentialCheck($identity->read($primary)['identity_status'] === 'pending', 'invalid legacy principal is still pending');
CompanySettingsRuntime::run($db,$p,$primary,7,fn() => CompanySettingsRuntime::saveIdentity(['legal_name'=>'Corrected principal','tax_id'=>'11144477735']));
essentialCheck(CompanySettingsIdentity::legacyPrincipalOption($db,$p,'companyname') === 'Corrected principal', 'unscoped legacy reader uses canonical principal');
essentialCheck(CompanySettingsIdentity::legacyPrincipalOption($db,$p,'company_vat') === '11144477735', 'legacy reader sees canonical corrected document');
essentialCheck($identity->read($primary)['identity_status'] === 'complete', 'explicit valid correction completes identity');
essentialCheck($db->query("SELECT legacy_identity_json FROM {$p}issuers_installation_identity WHERE id=1")->row()->legacy_identity_json === $legacyIdentity, 'raw legacy identity snapshot is immutable');
$auditJson = json_encode($db->query("SELECT * FROM {$p}issuers_audit")->result_array());
essentialCheck(!str_contains($auditJson,'encrypted-') && !str_contains($auditJson,'smtp_password') && !str_contains($auditJson,'bank-'), 'audit contains neither credential names nor values');
$storedA = $db->query("SELECT settings_json FROM {$p}issuers_registry WHERE id=41")->row()->settings_json;
$db->query("UPDATE {$p}issuers_registry SET settings_json='[]' WHERE id=41");
settingThrows(fn() => $store->get(41,'smtp_password'), 'company_settings_corrupt_storage', 'corrupt company storage fails closed');
$db->query("UPDATE {$p}issuers_registry SET settings_json=? WHERE id=41", [$storedA]);
$db->query("UPDATE {$p}issuers_registry SET active=0 WHERE id=82");
settingThrows(fn() => $store->get(82,'smtp_password'), 'company_settings_issuer_unavailable', 'inactive company job cannot read credentials');
settingThrows(fn() => CompanySettingsRuntime::run($db,$p,82,12,fn() => null), 'company_settings_access_denied', 'inactive request denied before entering');
essentialCheck(!CompanySettingsRuntime::active(), 'failed enter leaves no usable implicit context');
// Principal identity defects stay explicit until a valid, intentional correction.
$pending = essentialFixture('1.0.2',1); $pp = $pending->prefix;
$pending->db->query("UPDATE {$pp}options SET value=? WHERE name='companyname'", [str_repeat('x',200)]);
$pending->db->query("INSERT INTO {$pp}options VALUES('company_vat','malformed-document')");
IssuerCore::reconcile($pending->db,$pp); IssuerCore::bindInstallationMaster($pending->db,$pp,7);
$pendingId = (new IssuerResourceAccess($pending->db,$pp))->primaryId();
$pendingIdentity = new CompanySettingsIdentity($pending->db,$pp);
essentialCheck($pendingIdentity->read($pendingId)['tax_id'] === 'malformed-document', 'pending principal preserves raw invalid legacy document');
essentialCheck($pendingIdentity->read($pendingId)['legal_name'] === str_repeat('x',200), 'pending principal does not expose invented placeholder as fiscal identity');
$pendingIdentity->save($pendingId,7,['tax_id'=>'11144477735']);
essentialCheck($pendingIdentity->read($pendingId)['identity_status'] === 'pending', 'valid document alone cannot validate an invalid legacy name');
$pendingIdentity->save($pendingId,7,['legal_name'=>'Explicit valid name']);
essentialCheck($pendingIdentity->read($pendingId)['identity_status'] === 'complete', 'explicit name correction completes previously corrected document');
$pending->db->query("UPDATE {$pp}issuers_installation_identity SET primary_issuer_id=NULL WHERE id=1");
settingThrows(fn() => (new CompanySettingsStorage($pending->db,$pp))->get($pendingId,'smtp_password'), 'company_settings_primary_unresolved', 'unresolved principal cannot imply an arbitrary ID');
settingThrows(fn() => new CompanySettingsStorage($db,'bad;prefix'), 'Invalid table prefix', 'SQL identifiers reject injection');
essentialUse($f);
echo 'Company settings isolation: ' . $GLOBALS['essentialChecks'] . ' checks passed (' . $db->driver() . ').' . PHP_EOL;
