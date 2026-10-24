<?php
/** Real disposable MySQL DDL, atomic receipt+marker, literal history and replay. */
declare(strict_types=1);
require __DIR__ . '/legacy_financial_module_fixture.php';
class CI_Migration { public $db; }
require APPPATH . 'libraries/App_module_migration.php';
require APPPATH . 'migrations/473_version_473.php';
require APPPATH . 'services/EfiVersionAdoption.php';
use app\services\EfiVersionAdoption as Adoption;
$prefix = 'efi515_' . bin2hex(random_bytes(8)) . '_';
$GLOBALS['moduleReaderPrefix'] = $prefix;
$db->set_dbprefix($prefix);
$modules = $prefix . 'modules'; $audit = $prefix . 'module_version_adoptions';
$created = false; $checks = 0;
function efiCheck(bool $ok, string $name): void { ++$GLOBALS['checks']; if (!$ok) { throw new RuntimeException($name); } }
function efiFailure(callable $run, string $name): void {
    try { $run(); } catch (Throwable $error) { efiCheck(true, $name); return; }
    throw new RuntimeException('Expected rejection: ' . $name);
}
try {
    $created = $db->query("CREATE TABLE `{$modules}` (id INT PRIMARY KEY AUTO_INCREMENT,module_name VARCHAR(64) UNIQUE,installed_version VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,active INT NOT NULL) ENGINE=InnoDB") !== false;
    efiCheck($created, 'owned modules fixture');
    $migration = new Migration_Version_473(); $migration->db = $db;
    $migration->up(); $migration->up();
    $scopedAccounts=$prefix.'efi_company_accounts';$scopedOrigins=$prefix.'efi_company_origins';
    $db->query("INSERT INTO `{$scopedAccounts}` (issuer_id,environment,account_hash,credentials_cipher,certificate_cipher,revision,enabled,created_by,updated_by,created_at,updated_at) VALUES (1,'sandbox',REPEAT('a',64),'synthetic ciphertext',NULL,REPEAT('b',64),1,17,17,'2026-01-01 00:00:00','2026-01-01 00:00:00')");
    $beforeScoped=$db->query("SELECT * FROM `{$scopedAccounts}`")->result_array();$migration->up();
    efiCheck($beforeScoped===$db->query("SELECT * FROM `{$scopedAccounts}`")->result_array(),'scoped configuration preserved on migration replay');
    efiFailure(fn()=>$db->query("INSERT INTO `{$scopedAccounts}` (issuer_id,environment,account_hash,credentials_cipher,revision,enabled,created_by,updated_by,created_at,updated_at) VALUES (2,'sandbox',REPEAT('a',64),'synthetic',REPEAT('c',64),1,17,17,UTC_TIMESTAMP(),UTC_TIMESTAMP())"),'account identity cannot be shared by companies in same environment');
    $db->query("ALTER TABLE `{$scopedOrigins}` DROP INDEX invoice_origin");
    efiFailure(fn()=>$migration->up(),'scoped schema rejects missing invoice reservation uniqueness');
    $db->query("ALTER TABLE `{$scopedOrigins}` ADD UNIQUE KEY invoice_origin(invoice_id)");$migration->up();
    $bindings = $prefix . 'efi_charge_bindings';
    $db->query("INSERT INTO `{$bindings}` (provider_id,kind,sandbox,account_hash,invoice_id,issuer_id,amount_minor,currency,payment_id,created_at) VALUES ('synthetic','pix',1,REPEAT('a',64),17,NULL,1234,'BRL',NULL,'2026-01-01 00:00:00')");
    $bindingBefore = $db->query("SELECT * FROM `{$bindings}`")->result_array();
    $migration->up();
    efiCheck($db->query("SELECT * FROM `{$bindings}`")->result_array() === $bindingBefore, 'binding records preserved on schema replay');
    efiFailure(fn()=>$db->query("INSERT INTO `{$bindings}` (provider_id,kind,sandbox,account_hash,invoice_id,amount_minor,currency,created_at) VALUES ('synthetic','pix',1,REPEAT('a',64),99,1234,'BRL',UTC_TIMESTAMP())"), 'same application provider identity cannot be rebound');
    $db->query("INSERT INTO `{$bindings}` (provider_id,kind,sandbox,account_hash,invoice_id,amount_minor,currency,created_at) VALUES ('synthetic','pix',1,REPEAT('b',64),18,1234,'BRL',UTC_TIMESTAMP())");
    efiCheck(count($db->query("SELECT * FROM `{$bindings}`")->result_array()) === 2, 'different applications retain separate identities');

    foreach (Adoption::SOURCES as $index => $source) {
        $db->query("DELETE FROM `{$audit}`"); $db->query("DELETE FROM `{$modules}`");
        $db->query("INSERT INTO `{$modules}` (module_name,installed_version,active) VALUES (?,?,?)", [Adoption::MODULE,$source,$index]);
        $db->query("INSERT INTO `{$modules}` (module_name,installed_version,active) VALUES ('untouched','opaque old label',1)");
        if ($index === 0) {
            $db->trans_begin();
            efiFailure(fn()=>Adoption::adopt($db,$prefix,17,Adoption::TARGET), 'outer CI transaction rejected');
            efiCheck($db->conn_id->inTransaction(), 'caller transaction is not committed or rolled back');
            $db->trans_rollback();
            $db->conn_id->beginTransaction();
            efiFailure(fn()=>Adoption::adopt($db,$prefix,17,Adoption::TARGET), 'raw PDO transaction rejected');
            efiCheck($db->conn_id->inTransaction(), 'raw caller transaction preserved');
            $db->conn_id->rollBack();

            // A second actual connection holds the registry row: no audit write can escape it.
            $other = new PDO((string)getenv('ISSUERS_TEST_DSN'), (string)getenv('ISSUERS_TEST_USER'), (string)getenv('ISSUERS_TEST_PASSWORD'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
            $timeout = (int)$db->query('SELECT @@SESSION.innodb_lock_wait_timeout AS seconds')->row()->seconds;
            $db->query('SET SESSION innodb_lock_wait_timeout=1');
            try {
                $other->beginTransaction();
                $lock = $other->prepare("SELECT id FROM `{$modules}` WHERE module_name=? FOR UPDATE"); $lock->execute([Adoption::MODULE]);
                efiFailure(fn()=>Adoption::adopt($db,$prefix,17,Adoption::TARGET), 'concurrent registry lock rejects without partial receipt');
                efiCheck(!$db->query("SELECT * FROM `{$audit}`")->result_array(), 'concurrent attempt creates no history');
            } finally {
                if ($other->inTransaction()) { $other->rollBack(); }
                $db->query('SET SESSION innodb_lock_wait_timeout=' . $timeout);
            }
        }
        Adoption::adopt($db,$prefix,17,Adoption::TARGET);
        $row = $db->query("SELECT * FROM `{$modules}` WHERE module_name=?",[Adoption::MODULE])->row_array();
        $receipt = $db->query("SELECT * FROM `{$audit}`")->row_array();
        efiCheck($row['installed_version'] === '5.1.5' && (int)$row['active'] === $index, 'target+activation preserved');
        efiCheck($receipt['source_version'] === $source && (int)$receipt['actor_id'] === 17, 'literal source+actor');
        Adoption::adopt($db,$prefix,18,Adoption::TARGET,$source);
        efiFailure(fn()=>Adoption::adopt($db,$prefix,18,Adoption::TARGET,'wrong confirmation'), 'replay source confirmation');
        efiCheck($db->query("SELECT * FROM `{$audit}`")->result_array() === [$receipt], 'replay immutable receipt');
        efiCheck($db->query("SELECT installed_version FROM `{$modules}` WHERE module_name='untouched'")->row()->installed_version === 'opaque old label', 'other module unchanged');
    }
    foreach (['5.1.4','5.1.4 sdk','5.1.4 sdk f ','5.1.4 SDK FINAL','5.1.6'] as $source) {
        $db->query("DELETE FROM `{$audit}`");
        $db->query("UPDATE `{$modules}` SET installed_version=? WHERE module_name=?",[$source,Adoption::MODULE]);
        efiFailure(fn()=>Adoption::adopt($db,$prefix,17,Adoption::TARGET), 'ambiguous source');
        efiCheck(!$db->query("SELECT * FROM `{$audit}`")->result_array(), 'no receipt for rejected source');
    }
    $db->query("UPDATE `{$modules}` SET installed_version='5.1.4 sdk f' WHERE module_name=?",[Adoption::MODULE]);
    $auditTrigger = $prefix . 'reject_audit';
    $db->query("CREATE TRIGGER `{$auditTrigger}` BEFORE INSERT ON `{$audit}` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic audit failure'");
    efiFailure(fn()=>Adoption::adopt($db,$prefix,17,Adoption::TARGET), 'audit write failure');
    efiCheck($db->query("SELECT installed_version FROM `{$modules}` WHERE module_name=?",[Adoption::MODULE])->row()->installed_version === '5.1.4 sdk f', 'audit failure preserves marker');
    $db->query("DROP TRIGGER `{$auditTrigger}`");
    // Real SQL failure after audit INSERT: an owned synthetic trigger aborts marker UPDATE.
    $trigger = $prefix . 'reject_marker';
    $db->query("CREATE TRIGGER `{$trigger}` BEFORE UPDATE ON `{$modules}` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic marker failure'");
    efiFailure(fn()=>Adoption::adopt($db,$prefix,17,Adoption::TARGET), 'tracking write failure');
    efiCheck(!$db->query("SELECT * FROM `{$audit}`")->result_array(), 'receipt rolled back on marker failure');
    efiCheck($db->query("SELECT installed_version FROM `{$modules}` WHERE module_name=?",[Adoption::MODULE])->row()->installed_version === '5.1.4 sdk f', 'marker preserved on failure');
    $db->query("DROP TRIGGER `{$trigger}`");
    Adoption::adopt($db,$prefix,17,Adoption::TARGET);
    $db->query("UPDATE `{$audit}` SET source_header_sha256=REPEAT('0',64)");
    efiFailure(fn()=>Adoption::adopt($db,$prefix,17,Adoption::TARGET), 'conflicting receipt rejected');
    $db->query("ALTER TABLE `{$bindings}` DROP INDEX provider_identity");
    efiFailure(fn()=>$migration->up(), 'schema rejects missing binding uniqueness');
    $db->query("ALTER TABLE `{$bindings}` ADD UNIQUE KEY provider_identity(kind,sandbox,account_hash,provider_id)");
    $migration->up();
    $db->query("ALTER TABLE `{$audit}` ENGINE=MyISAM");
    efiFailure(fn()=>$migration->up(), 'metadata rejects nontransactional history');
    echo "Efí adoption MySQL: {$checks} checks PASS\n";
} finally {
    foreach (['receipts','proofs','origins','accounts'] as $suffix) { $db->query('DROP TABLE IF EXISTS `'.$prefix.'efi_company_'.$suffix.'`'); }
    // Only random owned fixture tables; CI database teardown handles process termination.
    $db->query("DROP TABLE IF EXISTS `{$prefix}efi_charge_intents`");
    $db->query("DROP TABLE IF EXISTS `{$prefix}efi_charge_bindings`");
    $db->query("DROP TABLE IF EXISTS `{$audit}`");
    if ($created) { $db->query("DROP TABLE `{$modules}`"); }
}
