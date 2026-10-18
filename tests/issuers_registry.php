<?php

declare(strict_types=1);

require_once __DIR__ . '/../application/services/issuers/IssuerRegistry.php';
require_once __DIR__ . '/../application/services/issuers/IssuerSchema.php';

require_once __DIR__ . '/issuers_test_support.php';

$checks = 0;
function same($expected, $actual, string $label): void
{
    global $checks;
    $checks++;
    if ($expected !== $actual) { throw new RuntimeException($label . ': ' . var_export($actual, true)); }
}
function rejects(callable $callback, string $code): void
{
    try { $callback(); } catch (DomainException $exception) { same($code, $exception->getMessage(), $code); return; }
    throw new RuntimeException('Expected rejection: ' . $code);
}
function syntheticCnpj(string $base): string
{
    for ($p = 12; $p < 14; $p++) {
        $sum = 0;
        for ($i = 0; $i < $p; $i++) { $sum += ((int) $base[$i]) * (($p - 1 - $i) % 8 + 2); }
        $base .= $sum % 11 < 2 ? '0' : (string) (11 - $sum % 11);
    }
    return $base;
}

$dsn = getenv('ISSUERS_TEST_DSN') ?: 'sqlite::memory:';
$pdo = new PDO($dsn, getenv('ISSUERS_TEST_USER') ?: null, getenv('ISSUERS_TEST_PASSWORD') ?: null);
$db = new IssuerTestDb($pdo);
$prefix = 'test_' . bin2hex(random_bytes(4)) . '_';
$p = $prefix . 'issuers_';
if ($db->driver === 'sqlite') { $pdo->exec('PRAGMA foreign_keys = ON'); }
$pdo->exec("CREATE TABLE {$prefix}staff (staffid INTEGER PRIMARY KEY, admin INTEGER, active INTEGER, firstname VARCHAR(50), lastname VARCHAR(50))");
$pdo->exec("CREATE TABLE {$prefix}clients (userid INTEGER PRIMARY KEY, company VARCHAR(191), vat VARCHAR(50), active INTEGER)");
$pdo->exec("CREATE TABLE {$prefix}invoices (id INTEGER PRIMARY KEY, clientid INTEGER, amount INTEGER)");
$pdo->exec("INSERT INTO {$prefix}invoices VALUES (1,101,5000)");
foreach ([1,2,3,4] as $id) { $db->query("INSERT INTO {$prefix}staff VALUES (?,?,1,?,?)", [$id, $id === 1 ? 1 : 0, 'Synthetic', (string) $id]); }
foreach (array_merge(IssuerSchema::statements($prefix), IssuerSchema::statements($prefix)) as $sql) {
    if ($db->driver === 'sqlite') {
        $sql = preg_replace('/(?:int|bigint) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY/', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
        $sql = str_replace('INSERT IGNORE', 'INSERT OR IGNORE', $sql);
        $sql = preg_replace('/ ENGINE=InnoDB.*$/s', '', $sql);
        $sql = preg_replace('/UNIQUE KEY `[^`]+` \(/', 'UNIQUE (', $sql);
        $sql = preg_replace('/,?\s*KEY `[^`]+` \([^)]*\)/', '', $sql);
    }
    $pdo->exec($sql);
}
IssuerSchema::ensureIntegrationPermissions($db, $prefix);
$r = new IssuerRegistry($db, $prefix);
$taxes = array_map('syntheticCnpj', ['123450000001','123450000002','123450000003','123450000004','123450000005']);
same('52998224725', IssuerPolicy::taxId('529.982.247-25'), 'CPF normalization/checksum');
same($taxes[0], IssuerPolicy::taxId($taxes[0]), 'CNPJ checksum');
same('12ABC34501DE35', IssuerPolicy::taxId('12.ABC.345/01DE-35'), 'Receita official alpha CNPJ example');
same('12ABC34501DE35', IssuerPolicy::taxId('12.abc.345/01de-35'), 'alpha CNPJ normalizes uppercase');
rejects(fn () => IssuerPolicy::taxId('12ABC34501DE36'), 'issuer_invalid_tax_id');
rejects(fn () => IssuerPolicy::taxId('12ABC34501DE3A'), 'issuer_invalid_tax_id');
same(null, IssuerPolicy::taxId('', false), 'private client optional tax');
foreach (['11111111111','00000000000000','52998224724','123','abc52998224725','52998224725x','',null,[]] as $bad) {
    rejects(fn () => IssuerPolicy::taxId($bad), 'issuer_invalid_tax_id');
}
foreach ([0,-1,'0','01','1e2',1.1,true,[],null,'2147483648','1 OR 1=1'] as $bad) {
    rejects(fn () => IssuerPolicy::id($bad), 'issuer_invalid_id');
}
foreach (['1',1] as $good) { same(1, IssuerPolicy::id($good), 'strict positive id'); }
rejects(fn () => $r->saveIssuer(2, ['legal_name'=>'Denied','tax_id'=>$taxes[0]]), 'issuer_access_denied');
$a = $r->saveIssuer(1, ['legal_name'=>'Synthetic A','tax_id'=>$taxes[0],'settings_json'=>'{"copied":"no"}']);
$b = $r->saveIssuer(1, ['legal_name'=>'Synthetic B','tax_id'=>$taxes[1]]);
$c = $r->saveIssuer(1, ['legal_name'=>'Synthetic C','tax_id'=>$taxes[2]]);
same('{}', $db->query("SELECT settings_json FROM {$p}registry WHERE id=?",[$a])->result_array()[0]['settings_json'], 'new issuer has independent empty config');
same(false,array_key_exists('settings_json',$r->catalog(1)[0]),'catalog never exposes private settings');
same(3, count($r->catalog(1)), 'catalog admin');
rejects(fn () => $r->catalog(2), 'issuer_access_denied');
same(false, $r->canAccess($a,1), 'admin has no implicit membership');
same(false, $r->canAccess($a,1,'finance'), 'financial capability is never granted');
same(false, $r->canAccess($a,1,'manage_integrations'), 'administrator has no implicit integration grant');
rejects(fn () => $r->get($a,1), 'issuer_access_denied');
rejects(fn () => $r->saveIssuer(1,['legal_name'=>'Duplicate','tax_id'=>$taxes[0]]), 'issuer_tax_conflict');
rejects(fn () => $r->saveIssuer(1,['legal_name'=>' ','tax_id'=>$taxes[3]]), 'issuer_invalid_name');
rejects(fn () => $r->saveIssuer(1,['legal_name'=>'Invalid','tax_id'=>$taxes[3],'active'=>'yes']), 'issuer_invalid_status');
foreach ([$a,$b,$c] as $issuer) { rejects(fn () => IssuerPolicy::assertOperationalScope($issuer), 'issuer_rollout_blocked'); }

// Historical generic flags are deliberately retained as data, never authority.
foreach ([$a,$b,$c] as $issuer) {
    $db->query("INSERT INTO {$p}staff(issuer_id,staff_id,can_view,can_manage_clients,can_share_clients,can_manage_integrations) VALUES(?,1,1,1,1,1)",[$issuer]);
}
foreach ([$a,$b] as $issuer) {
    $db->query("INSERT INTO {$p}staff(issuer_id,staff_id,can_view,can_manage_clients,can_share_clients,can_manage_integrations) VALUES(?,2,1,1,1,1)",[$issuer]);
}
$db->query("INSERT INTO {$p}staff(issuer_id,staff_id,can_view,can_manage_clients,can_share_clients,can_manage_integrations) VALUES(?,3,1,1,1,1)",[$a]);
foreach (['manage_clients','share_clients','manage_integrations'] as $capability) {
    same(false,$r->canAccess($a,1,$capability),'old generic flag cannot authorize '.$capability);
}
same(true,$r->canAccess($a,2),'explicit membership permits identity lookup');
same(false,$r->canAccess($c,2),'identity never crosses companies');
same(2,count($r->available(2)),'only authorized active companies');
rejects(fn()=>$r->get($c,2),'issuer_access_denied');
same($a,(int)$r->get($a,3)['id'],'identity from authorized registry');
same(null,$r->defaultForStaff(2),'no guessed default');
$r->setDefault($a,2);same($a,$r->defaultForStaff(2),'valid default');
rejects(fn()=>$r->setDefault($c,2),'issuer_access_denied');
same($a,$r->defaultForStaff(2),'failed switch preserves default');
$db->query("UPDATE {$p}staff SET can_view=0 WHERE issuer_id=? AND staff_id=2",[$a]);
same(null,$r->defaultForStaff(2),'revoked membership default invalid immediately');
$db->query("UPDATE {$p}staff SET can_view=1 WHERE issuer_id=? AND staff_id=2",[$a]);
$r->setDefault($b,2);
$r->saveIssuer(1,['legal_name'=>'Synthetic B','tax_id'=>$taxes[1],'active'=>'0'],$b);
same(null,$r->defaultForStaff(2),'inactive company clears default');
same(false,$r->canAccess($b,2),'inactive company denied');
$r->saveIssuer(1,['legal_name'=>'Synthetic B','tax_id'=>$taxes[1],'active'=>'1'],$b);
same(null,$r->defaultForStaff(2),'reactivation never guesses default');
$r->setDefault($a,2);
$db->query("UPDATE {$prefix}staff SET active=0 WHERE staffid=2");
same(null,$r->defaultForStaff(2),'inactive staff default invalid');
same(false,$r->canAccess($a,2),'inactive staff denied');
$db->query("UPDATE {$prefix}staff SET active=1 WHERE staffid=2");
foreach ([1,2,3] as $actor) {
    rejects(fn()=>$r->registerClient($a,101,$actor),'issuer_client_adoption_blocked');
    rejects(fn()=>$r->shareClient($a,101,[$b],$actor),'issuer_client_adoption_blocked');
}
same(0,(int)$pdo->query("SELECT COUNT(*) FROM {$p}clients")->fetchColumn(),'blocked adoption writes no owner');
same(0,(int)$pdo->query("SELECT COUNT(*) FROM {$p}client_links")->fetchColumn(),'blocked sharing writes no link');
same(5000,(int)$pdo->query("SELECT amount FROM {$prefix}invoices WHERE id=1")->fetchColumn(),'finance preserved');
$db->trans_begin();
try {$r->saveIssuer(1,['legal_name'=>'Nested denied','tax_id'=>$taxes[3]]);throw new LogicException('outer accepted');}
catch(RuntimeException $error){same('issuer_outer_transaction_not_allowed',$error->getMessage(),'registry owns transaction');}
same(true,$pdo->inTransaction(),'registry leaves caller transaction intact');$db->trans_rollback();
$db->failOn="INSERT INTO {$p}audit";
try {$r->saveIssuer(1,['legal_name'=>'Rollback','tax_id'=>$taxes[3]]);throw new LogicException('failure missing');}
catch(RuntimeException $error){same('issuer_storage_failed',$error->getMessage(),'audit failure sanitized');}
$db->failOn=null;same(3,count($r->catalog(1)),'failed audit rolls back registry');
try {new IssuerRegistry($db,'x; DROP TABLE clients;');throw new LogicException('prefix accepted');}
catch(InvalidArgumentException $error){same(true,true,'prefix guarded');}
echo "Core company registry: {$checks} checks passed ({$db->driver}); synthetic data only.\n";
