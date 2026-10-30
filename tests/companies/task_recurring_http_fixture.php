<?php
/** Read-only recurrence proof on the disposable native HTTP fixture. */
if (PHP_SAPI!=='cli' || getenv('ISSUERS_BROWSER_DB_NAME')!=='argws_upgrade_ci') { exit(2); }
define('FCPATH',dirname(__DIR__,2).'/'); define('BASEPATH',FCPATH.'system/'); define('APPPATH',FCPATH.'application/');
require APPPATH.'config/app-config.php';
if (APP_DB_NAME!=='argws_upgrade_ci' || !preg_match('/^(127\.0\.0\.1|argws-crm-provision-db-[0-9]+)(:[0-9]+)?$/D',APP_DB_HOSTNAME)) { exit(2); }
require dirname(__DIR__).'/support/BbPdoDatabase.php'; require APPPATH.'services/CompanyTaskRecurring.php';
function db_prefix() { return 'tbl'; }
$pdo=new PDO('mysql:host='.str_replace(':',';port=',APP_DB_HOSTNAME).';dbname='.APP_DB_NAME.';charset=utf8mb4',APP_DB_USERNAME,APP_DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db=new BbPdoDatabase($pdo);
$identity=$db->query('SELECT master_staff_id,primary_issuer_id,source FROM tblissuers_installation_identity WHERE id=1')->row_array();
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

if ((int)$identity['master_staff_id']!==17 || !in_array($identity['source'],['installer','legacy_designation','owner_attestation'],true)) { throw new RuntimeException('Existing installer master required'); }
$issuer=IssuerPolicy::id($argv[1]??null); $kind=$argv[2]??''; $id=IssuerPolicy::id($argv[3]??null);
$service=new CompanyTaskRecurring($db,'tbl'); $detail=$service->get($issuer,17,$kind,$id);
echo json_encode($detail,JSON_THROW_ON_ERROR).PHP_EOL;
