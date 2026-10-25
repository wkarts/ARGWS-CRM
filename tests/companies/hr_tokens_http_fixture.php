<?php
/** Read-only witnesses for native HR credential GUI on explicit disposable fixtures. */
if (PHP_SAPI !== 'cli' || !in_array(getenv('ISSUERS_BROWSER_DB_NAME'), ['argws_upgrade_ci', 'argws_fresh_ci', 'argws_crm_ci', 'argws_hr_ci'], true)) exit(2);
define('FCPATH', dirname(__DIR__, 2) . '/');
define('BASEPATH', FCPATH . 'system/');
define('APPPATH', FCPATH . 'application/');
require APPPATH . 'config/app-config.php';
if (APP_DB_NAME !== getenv('ISSUERS_BROWSER_DB_NAME') || !preg_match('/^(127\.0\.0\.1|argws-crm-provision-db-[0-9]+)(:[0-9]+)?$/D', APP_DB_HOSTNAME)) exit(2);
require dirname(__DIR__) . '/support/BbPdoDatabase.php';
require APPPATH . 'services/CompanyHrApi.php';
function db_prefix() { return 'tbl'; }
$pdo = new PDO('mysql:host=' . str_replace(':', ';port=', APP_DB_HOSTNAME) . ';dbname=' . APP_DB_NAME . ';charset=utf8mb4', APP_DB_USERNAME, APP_DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db = new BbPdoDatabase($pdo);
$identity = $db->query('SELECT master_staff_id,primary_issuer_id,source FROM tblissuers_installation_identity WHERE id=1')->row_array();
// BEGIN exact upgraded-fixture owner proof (test-only, read-only).
if (($identity['source'] ?? null) === 'owner_attestation') {
    $audit = $db->query("SELECT actor_id,subject_id,issuer_id,metadata_json FROM tblissuers_audit WHERE event='original_admin_attested'")->result_array();
    $meta = count($audit) === 1 ? json_decode($audit[0]['metadata_json'], true) : null;
    if (getenv('ISSUERS_BROWSER_DB_NAME') !== 'argws_upgrade_ci'
        || (int)($identity['master_staff_id'] ?? 0) !== 17
        || count($audit) !== 1 || (string)$audit[0]['actor_id'] !== '0'
        || (int)$audit[0]['subject_id'] !== 17 || $audit[0]['issuer_id'] !== null
        || !is_array($meta) || ($meta['proof'] ?? null) !== 'installation_owner_attestation'
        || ($meta['confirmation_reference'] ?? null) !== 'test:explicit-owner-confirmation'
        || (int)($meta['primary_issuer_id'] ?? 0) !== (int)$identity['primary_issuer_id']
        || (int)$identity['primary_issuer_id'] !== 1) {
        throw new RuntimeException('Exact upgraded fixture owner attestation and audit required');
    }
}
// END exact upgraded-fixture owner proof.

$version = (int) $db->query('SELECT version FROM tblmigrations')->row()->version;
$candidate = getenv('ISSUERS_HR_CANDIDATE') === '1' && APP_DB_NAME === 'argws_hr_ci';
if ((int) $identity['master_staff_id'] !== 17 || !in_array($identity['source'], ['installer', 'legacy_designation', 'owner_attestation'], true) || ($candidate ? $version !== 453 : $version < 456)) throw new RuntimeException('Native master17 and explicit HR fixture version required');
CompanyHrSchema::assertValid($db, 'tbl');
$mode = $argv[1] ?? '';
$snapshot = static function (array $rows): array {
    usort($rows, static fn($a, $b) => json_encode($a, JSON_THROW_ON_ERROR) <=> json_encode($b, JSON_THROW_ON_ERROR));
    return ['count' => count($rows), 'sha256' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR))];
};
if ($mode === 'context') {
    $owners = [];
    foreach (['a' => '11222333000181', 'b' => '52998224725'] as $label => $tax) {
        $rows = $db->query('SELECT id,active,identity_status FROM tblissuers_registry WHERE tax_id=?', [$tax])->result_array();
        if (count($rows) !== 1 || (int) $rows[0]['active'] !== 1 || $rows[0]['identity_status'] !== 'complete') throw new RuntimeException('Existing synthetic company identity required');
        $owners[$label] = (int) $rows[0]['id'];
    }
    $owners['principal'] = (int) $identity['primary_issuer_id'];
    if (count(array_unique($owners)) !== 3) throw new RuntimeException('Distinct existing companies required');
    $config = file_get_contents(APPPATH . 'config/config.php');
    if (!preg_match('/\$config\[\x27csrf_cookie_name\x27\]\s*=\s*defined\(\x27APP_CSRF_COOKIE_NAME\x27\)\s*\?\s*APP_CSRF_COOKIE_NAME\s*:\s*\x27csrf_cookie_name\x27\s*;/', $config)) throw new RuntimeException('Expected native CSRF cookie configuration required');
    $cookieName = (defined('APP_COOKIE_PREFIX') ? APP_COOKIE_PREFIX : '') . (defined('APP_CSRF_COOKIE_NAME') ? APP_CSRF_COOKIE_NAME : 'csrf_cookie_name');
    if (!is_string($cookieName) || !preg_match('/^[a-zA-Z0-9_]+$/D', $cookieName)) throw new RuntimeException('Explicit native CSRF cookie name required');
    $value = ['owners' => $owners, 'master' => 17, 'identity' => $identity, 'native_version' => $version, 'candidate_only' => $candidate, 'scopes' => array_keys(CompanyHrApi::SCOPES), 'csrf_cookie_name' => $cookieName];
} elseif ($mode === 'sessions') {
    $value = $snapshot($db->query('SELECT * FROM tblsessions')->result_array());
} elseif ($mode === 'token') {
    $id = $argv[2] ?? '';
    if (!preg_match('/^[a-f0-9]{32}$/D', $id)) throw new RuntimeException('Canonical token identifier required');
    $rows = $db->query('SELECT issuer_id,actor_id,active,scopes_json FROM tblcompany_api_tokens WHERE token_id=?', [$id])->result_array();
    if (count($rows) !== 1) throw new RuntimeException('Exactly one native credential required');
    $row = $rows[0];
    $value = ['issuer_id' => (int) $row['issuer_id'], 'actor_id' => (int) $row['actor_id'], 'active' => (int) $row['active'], 'scopes' => json_decode($row['scopes_json'], true, 16, JSON_THROW_ON_ERROR)];
} elseif ($mode === 'preservation') {
    $value = ['global_identity' => $snapshot($db->query('SELECT staffid,email,firstname,lastname,password,admin,active,role FROM tblstaff')->result_array())];
    $tables = ['issuers_registry', 'issuers_installation_identity', 'invoices', 'invoicepaymentrecords', 'creditnotes', 'expenses', 'company_financial_events', 'hr_profile', 'hr_staff_contract', 'hr_salary_form', 'hr_allowance_type', 'hr_dependent_person', 'hr_job_position', 'hr_unit_name', 'hr_training_program'];
    foreach ($db->query('SELECT TABLE_NAME name FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->result_array() as $row) {
        if (preg_match('/^tblcompany_hr_[a-z_]+$/D', $row['name'])) $tables[] = substr($row['name'], 3);
    }
    foreach (array_unique($tables) as $table) {
        if (!$db->table_exists('tbl' . $table)) { $value[$table] = ['exists' => false]; continue; }
        $value[$table] = ['exists' => true] + $snapshot($db->query('SELECT * FROM tbl' . $table)->result_array());
    }
    ksort($value);
} elseif ($mode === 'sources') {
    $paths = array_merge(glob(APPPATH . 'services/CompanyHr*.php'), [
        APPPATH . 'controllers/admin/Company_hr.php', APPPATH . 'controllers/Company_hr_access.php',
        APPPATH . 'views/admin/companies/hr.php', APPPATH . 'views/admin/companies/hr_mine.php', APPPATH . 'views/admin/companies/hr_records.php', APPPATH . 'views/hr_access.php',
        APPPATH . 'views/admin/companies/api_tokens.php', APPPATH . 'controllers/admin/Company_api_tokens.php', APPPATH . 'services/CompanyApiTokens.php', __FILE__,
    ]);
    $value = [];
    foreach ($paths as $path) $value[substr($path, strlen(FCPATH))] = hash_file('sha256', $path);
    ksort($value);
} else throw new RuntimeException('Unknown read-only HR token witness');
echo json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . PHP_EOL;
