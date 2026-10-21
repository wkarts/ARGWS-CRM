<?php
/** Read-only hashes of the complete, disposable native HTTP database. No data values are emitted. */
if (PHP_SAPI !== 'cli' || !in_array(getenv('ISSUERS_BROWSER_DB_NAME'), ['argws_upgrade_ci','argws_legacy_ci'], true)) { exit(2); }
define('FCPATH', dirname(__DIR__, 2).'/');
define('BASEPATH', FCPATH.'system/');
define('APPPATH', FCPATH.'application/');
require APPPATH.'config/app-config.php';
if (APP_DB_NAME !== getenv('ISSUERS_BROWSER_DB_NAME') || !preg_match('/^(127\.0\.0\.1|argws-crm-provision-db-[0-9]+)(:[0-9]+)?$/D', APP_DB_HOSTNAME)) { exit(2); }
$pdo = new PDO('mysql:host='.str_replace(':', ';port=', APP_DB_HOSTNAME).';dbname='.APP_DB_NAME.';charset=utf8mb4', APP_DB_USERNAME, APP_DB_PASSWORD, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('SET SESSION TRANSACTION READ ONLY');
$pdo->beginTransaction();
register_shutdown_function(static function () use ($pdo) { if ($pdo->inTransaction()) { $pdo->rollBack(); } });
$version = (int)$pdo->query('SELECT version FROM tblmigrations')->fetchColumn();
if (!in_array($version, [452, 453], true)) { throw new RuntimeException('Genuine452 or453 fixture required'); }
function native453HttpHash(array $rows): string
{
    $rows = array_map('serialize', $rows);
    sort($rows, SORT_STRING);
    return hash('sha256', serialize($rows));
}
$result = ['version'=>$version, 'read_only'=>true, 'memory_limit'=>ini_get('memory_limit'), 'tables'=>[]];
$identity = $pdo->query('SELECT master_staff_id,primary_issuer_id,source FROM tblissuers_installation_identity WHERE id=1')->fetch(PDO::FETCH_ASSOC);
if (!$identity || $identity['source'] !== 'installer' || (int)$identity['master_staff_id'] !== 17 || (int)$identity['primary_issuer_id'] !== 1) { throw new RuntimeException('Expected existing native installer identity'); }
$result['identity'] = $identity;
$result['qualified_changes'] = ['tblstaff'=>['last_ip','last_login','last_activity'], 'tbloptions'=>['upgraded_from_version','AUTO_INCREMENT'], 'tblmigrations'=>['version'], 'tblactivity_log'=>['exact native successful login of fixture master17 from127.0.0.1','AUTO_INCREMENT']];
$tables = $pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME')->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    if (!preg_match('/^tbl[a-zA-Z0-9_]+$/D', $table)) { throw new RuntimeException('Unexpected fixture table'); }
    $metadata = $pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_ASSOC)['Create Table'];
    $rows = $pdo->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);
    if ($table === 'tblstaff') {
        foreach ($rows as &$row) { unset($row['last_ip'], $row['last_login'], $row['last_activity']); }
        unset($row);
    }
    if ($table === 'tbloptions') {
        $metadata = preg_replace('/ AUTO_INCREMENT=[0-9]+/', ' AUTO_INCREMENT=<native-option-tracking>', $metadata);
        $rows = array_values(array_filter($rows, static fn($row) => $row['name'] !== 'upgraded_from_version'));
    }
    if ($table === 'tblactivity_log') {
        // This fixture starts with an empty activity log. Authenticate through
        // the real login controller; account for only its exact native event.
        $metadata = preg_replace('/ AUTO_INCREMENT=[0-9]+/', '', $metadata);
        $result['native_login_events'] = 0;
        $rows = array_values(array_filter($rows, static function ($row) use (&$result) {
            if (($row['description'] ?? '') === 'User Successfully Logged In [User Id: 17, Is Staff Member: Yes, IP: 127.0.0.1]') {
                ++$result['native_login_events']; return false;
            }
            return true;
        }));
    }
    $result['tables'][$table] = ['metadata_sha256'=>hash('sha256', $metadata), 'rows_sha256'=>native453HttpHash($rows), 'row_count'=>count($rows)];
}
$result['upgraded_from_version'] = $pdo->query("SELECT value FROM tbloptions WHERE name='upgraded_from_version'")->fetchColumn();
$result['cursor'] = $version === 453 ? $pdo->query('SELECT * FROM tblcompany_task_schedule_cursor')->fetchAll(PDO::FETCH_ASSOC) : null;
echo json_encode($result, JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT).PHP_EOL;
