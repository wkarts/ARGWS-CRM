<?php
/** Fixed in-process adapter contract. The full framework/MySQL HTTP path is covered by Docker CI. */
declare(strict_types=1);
require __DIR__ . '/../application/services/InstallationMigrationBootstrap.php';
$checks = 0;
function check($yes, $label) { global $checks; $checks++; if (!$yes) { throw new RuntimeException($label); } }
$root = sys_get_temp_dir() . '/issuer-inline-' . bin2hex(random_bytes(5));
mkdir($root); mkdir($root . '/install');
file_put_contents($root . '/install/index.php', '<?php // synthetic installer entry');
file_put_contents($root . '/index.php', <<<'FIXTURE'
<?php
if (!InstallationMigrationBootstrap::isRunning() || ARGWS_INSTALLER_CREATED_STAFF_ID !== 17) { throw new RuntimeException('internal identity absent'); }
if ($_POST !== [] || $_GET !== [] || $_REQUEST !== [] || $routing !== ['directory'=>'','controller'=>'Argws_provisioning','function'=>'complete_installation']) { throw new RuntimeException('credentials leaked or dispatch not fixed'); }
if ($assign_to_config['csrf_protection'] !== false) { throw new RuntimeException('native nested request incorrectly requires second CSRF'); }
if ($_SERVER['REQUEST_URI'] !== '/argws_provisioning/complete_installation' || $_SERVER['QUERY_STRING'] !== '') { throw new RuntimeException('request route was not isolated'); }
$CFG=(object)['nativeGlobal'=>true];
if (empty($GLOBALS['CFG']->nativeGlobal)) { throw new RuntimeException('CI/MX configuration global missing'); }
define('BASEPATH', __DIR__);
function &get_instance() { return $GLOBALS['bootstrapFixtureCi']; }
$GLOBALS['bootstrapFixtureCi']=(object)['installationMigrationResult'=>['success'=>true]];
echo 'synthetic child output must not leak';
FIXTURE
);
try {
    $_SERVER['SCRIPT_FILENAME'] = $root . '/install/index.php';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    check(!InstallationMigrationBootstrap::run($root,17),'GET never bootstraps migrations');
    $_SERVER['REQUEST_METHOD'] = 'POST';
    check(!InstallationMigrationBootstrap::run($root,0),'unconfirmed inserted ID rejected');
    $_SERVER['SCRIPT_FILENAME'] = __FILE__;
    check(!InstallationMigrationBootstrap::run($root,17),'direct external entrypoint rejected');
    $_SERVER['SCRIPT_FILENAME'] = $root . '/install/index.php';
    $_POST = ['admin_password'=>'synthetic-not-a-secret','database_password'=>'synthetic-only'];
    $_REQUEST = $_POST;
    $_GET = ['route'=>'synthetic'];
    $_SERVER['REQUEST_URI'] = '/install/index.php?route=synthetic';
    $server = $_SERVER;
    $cwd=getcwd(); http_response_code(200); ob_start();
    $result=InstallationMigrationBootstrap::run($root,17); $output=ob_get_clean();
    check(http_response_code()===200,'successful buffer cleanup does not emit a failure status');
    check($result,'server-created ID 17 reaches fixed native bootstrap in process');
    check($output==='','bootstrap output never leaks credentials/SQL into finish response');
    check($_POST['admin_password']==='synthetic-not-a-secret' && $_REQUEST===$_POST && getcwd()===$cwd,'outer request context restored');
    check($_GET===['route'=>'synthetic'] && $_SERVER===$server,'outer URI and query restored after fixed internal dispatch');
    check(!InstallationMigrationBootstrap::isRunning(),'bootstrap authority does not survive completion');
    check(!InstallationMigrationBootstrap::run($root,17),'an already bootstrapped request cannot reenter installation');
    echo "Issuer inline installer: {$checks} checks passed (adapter contract; full HTTP/MySQL runs in Docker CI).\n";
} finally {
    unlink($root . '/index.php'); unlink($root . '/install/index.php'); rmdir($root . '/install'); rmdir($root);
}
