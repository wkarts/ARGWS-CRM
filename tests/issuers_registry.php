<?php

declare(strict_types=1);

require_once __DIR__ . '/../modules/issuers/services/IssuerRegistry.php';
require_once __DIR__ . '/../modules/issuers/services/IssuerSchema.php';

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
same('{}', $r->catalog(1)[0]['settings_json'], 'new issuer has independent empty config');
same(3, count($r->catalog(1)), 'catalog admin');
rejects(fn () => $r->catalog(2), 'issuer_access_denied');
same(false, $r->canAccess($a,1), 'admin has no implicit membership');
same(false, $r->canAccess($a,1,'finance'), 'financial capability is never granted');
rejects(fn () => $r->get($a,1), 'issuer_access_denied');
rejects(fn () => $r->saveIssuer(1,['legal_name'=>'Duplicate','tax_id'=>$taxes[0]]), 'issuer_tax_conflict');
rejects(fn () => $r->saveIssuer(1,['legal_name'=>' ','tax_id'=>$taxes[3]]), 'issuer_invalid_name');
rejects(fn () => $r->saveIssuer(1,['legal_name'=>'Invalid','tax_id'=>$taxes[3],'active'=>'yes']), 'issuer_invalid_status');
foreach ([$a,$b,$c] as $issuer) { rejects(fn () => IssuerPolicy::assertOperationalScope($issuer), 'issuer_rollout_blocked'); }
rejects(fn () => $r->assignMember($a,1,['manage_clients'],1), 'issuer_view_required');
rejects(fn () => $r->assignMember($a,1,['view','share_clients'],1), 'issuer_manage_required');
rejects(fn () => $r->assignMember($a,1,['view','finance'],1), 'issuer_invalid_permission');
rejects(fn () => $r->assignMember($a,2,['view'],2), 'issuer_access_denied');
rejects(fn () => $r->assignMember($a,999,['view'],1), 'issuer_access_denied');
foreach ([$a,$b,$c] as $issuer) { $r->assignMember($issuer,1,IssuerPolicy::CAPABILITIES,1); }
$r->assignMember($a,2,['view','manage_clients','share_clients'],1);
$r->assignMember($b,2,['view','manage_clients'],1);
$r->assignMember($a,3,['view'],1);
same(true, $r->canAccess($a,2,'share_clients'), 'company-specific share');
same(false, $r->canAccess($b,2,'share_clients'), 'no permission inheritance');
same(false, $r->canAccess($c,2), 'cross-company denied');
same(2,count($r->available(2)), 'only member companies listed');
rejects(fn () => $r->get($c,2), 'issuer_access_denied');
same(null,$r->defaultForStaff(2),'no guessed default');
$r->setDefault($a,2);
same($a,$r->defaultForStaff(2),'valid default');
rejects(fn () => $r->setDefault($c,2),'issuer_access_denied');
same($a,$r->defaultForStaff(2),'failed switch retains default');
$r->assignMember($a,2,[],1);
same(null,$r->defaultForStaff(2),'revoked view removes default');
$r->assignMember($a,2,IssuerPolicy::CAPABILITIES,1);
$r->setDefault($a,2);
$r->removeMember($a,2,1);
same(null,$r->defaultForStaff(2),'removed membership removes default');
$r->assignMember($a,2,IssuerPolicy::CAPABILITIES,1);
$r->setDefault($b,2);
$r->saveIssuer(1,['legal_name'=>'Synthetic B','tax_id'=>$taxes[1],'active'=>'0'],$b);
same(null,$r->defaultForStaff(2),'inactive issuer removes default');
same(false,$r->canAccess($b,2),'inactive issuer denied');
$r->saveIssuer(1,['legal_name'=>'Synthetic B','tax_id'=>$taxes[1],'active'=>'1'],$b);
same(null,$r->defaultForStaff(2),'reactivation never guesses default');
$r->setDefault($a,2);
$db->query("UPDATE {$prefix}staff SET active=0 WHERE staffid=2");
same(null,$r->defaultForStaff(2),'inactive user default never returned');
same(false,$r->canAccess($a,2),'inactive user denied');
$db->query("UPDATE {$prefix}staff SET active=1 WHERE staffid=2");
$db->query("INSERT INTO {$prefix}clients VALUES (101,'Synthetic customer A',?,1)",[$taxes[3]]);
$db->query("INSERT INTO {$prefix}clients VALUES (102,'Synthetic customer B',?,1)",[$taxes[4]]);
$db->query("INSERT INTO {$prefix}clients VALUES (103,'No document','',1)");
rejects(fn () => $r->registerClient($a,101,2),'issuer_access_denied');
$r->registerClient($a,101,1);
same(1,count($r->clients($a,2)),'private owner access');
same(0,count($r->clients($b,2)),'private excludes other company');
rejects(fn () => $r->clients($c,2),'issuer_access_denied');
rejects(fn () => $r->registerClient($b,101,1),'issuer_client_already_registered');
rejects(fn () => $r->registerClient($a,999,1),'issuer_client_unavailable');
rejects(fn () => $r->shareClient($a,101,[$b],3),'issuer_access_denied');
rejects(fn () => $r->shareClient($b,101,[$a],2),'issuer_access_denied');
rejects(fn () => $r->shareClient($a,101,[$c],2),'issuer_access_denied');
same(0,count($r->clients($b,2)),'rejected share has no partial effects');
$r->shareClient($a,101,[$b,$b],2);
same(1,count($r->clients($b,2)),'explicit sharing');
same(101,(int)$r->clients($b,2)[0]['userid'],'same canonical client, no duplicate');
same([$a,$b],$r->clientTargets($a,101,2),'deduplicated targets');
$r->saveIssuer(1,['legal_name'=>'Synthetic B','tax_id'=>$taxes[1],'active'=>'0'],$b);
$r->shareClient($a,101,[],2);
same([$a],$r->clientTargets($a,101,2),'owner revokes inactive destination');
$r->saveIssuer(1,['legal_name'=>'Synthetic B','tax_id'=>$taxes[1],'active'=>'1'],$b);
$r->shareClient($a,101,[$b],2);
$r->removeMember($b,2,1);
$r->shareClient($a,101,[],2);
same([$a],$r->clientTargets($a,101,2),'owner revokes after losing target membership');
$r->assignMember($b,2,['view','manage_clients'],1);
$r->shareClient($a,101,[$b],2);
$r->shareClient($a,101,[],2);
same(0,count($r->clients($b,2)),'revoke returns private');
same([$a],$r->clientTargets($a,101,2),'owner cannot be removed');
$r->registerClient($a,103,1);
rejects(fn () => $r->shareClient($a,103,[$b],2),'issuer_invalid_tax_id');
$db->query("INSERT INTO {$prefix}clients VALUES (104,'Conflicting legacy customer',?,1)",[$taxes[3]]);
rejects(fn () => $r->shareClient($a,101,[$b],2),'issuer_client_tax_conflict');
rejects(fn () => $r->registerClient($b,104,1),'issuer_client_tax_conflict');
same(0,count($r->clients($b,2)),'conflict blocked before links');
$db->query("DELETE FROM {$prefix}clients WHERE userid=104");
$db->query("UPDATE {$prefix}clients SET vat=? WHERE userid=101",[syntheticCnpj('123450000006')]);
rejects(fn () => $r->shareClient($a,101,[$b],2),'issuer_client_identity_changed');
$db->query("UPDATE {$prefix}clients SET vat=? WHERE userid=101",[$taxes[3]]);
$db->failOn = "INSERT INTO {$p}audit";
try { $r->shareClient($a,101,[$b],2); throw new LogicException('Failure not injected'); } catch (RuntimeException $error) { same('injected_storage_failure',$error->getMessage(),'injected failure'); }
$db->failOn = null;
same([$a],$r->clientTargets($a,101,2),'failed transaction restored previous links');
same(3,(int)$pdo->query("SELECT COUNT(*) FROM {$prefix}clients")->fetchColumn(),'no client duplicate created');
same(5000,(int)$pdo->query("SELECT amount FROM {$prefix}invoices WHERE id=1")->fetchColumn(),'finances unchanged');
same(1,(int)$pdo->query("SELECT COUNT(*) FROM {$prefix}invoices")->fetchColumn(),'finances not copied');
same(true,(int)$pdo->query("SELECT COUNT(*) FROM {$p}audit")->fetchColumn() > 0,'audit records present');
foreach ($pdo->query("SELECT event FROM {$p}audit")->fetchAll(PDO::FETCH_COLUMN) as $event) {
    same(false,strpos($event,$taxes[3]) !== false,'audit excludes tax values');
}
$db->query("INSERT INTO {$prefix}clients VALUES (105,'Synthetic alpha','12.ABC.345/01DE-35',1)");
$r->registerClient($a,105,1);
$db->query("INSERT INTO {$prefix}clients VALUES (106,'Conflicting alpha','12abc34501de35',1)");
rejects(fn () => $r->shareClient($a,105,[$b],2),'issuer_client_tax_conflict');
$db->query("DELETE FROM {$prefix}clients WHERE userid=106");
$r->shareClient($a,105,[$b],2);
same(105,(int)$r->clients($b,2)[0]['userid'],'alpha CNPJ shared without truncation');
$db->query("UPDATE {$prefix}clients SET vat='invalid' WHERE userid=105");
$r->shareClient($a,105,[],2);
same([$a],$r->clientTargets($a,105,2),'privacy revocation remains possible after identity corruption');
// Untrusted prefix must never become SQL.
try { new IssuerRegistry($db,'x; DROP TABLE clients;'); throw new LogicException('Bad prefix accepted'); } catch (InvalidArgumentException $error) { same(true,true,'prefix guarded'); }
echo "Issuer registry: {$checks} checks passed ({$db->driver}); synthetic data only.\n";
