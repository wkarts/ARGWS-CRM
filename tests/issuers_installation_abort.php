<?php
/** Actual abort before the inline bridge returns; output must stay sanitized. */
declare(strict_types=1);
require __DIR__.'/../application/services/InstallationMigrationBootstrap.php';
$root=sys_get_temp_dir().'/issuer-abort-'.bin2hex(random_bytes(5));
mkdir($root);mkdir($root.'/install');
file_put_contents($root.'/install/index.php','<?php // synthetic installer');
file_put_contents($root.'/index.php','<?php echo "SYNTHETIC_SQL_AND_CREDENTIALS_MUST_NOT_LEAK"; exit;');
register_shutdown_function(static function()use($root){unlink($root.'/index.php');unlink($root.'/install/index.php');rmdir($root.'/install');rmdir($root);});
// Outside the production buffer: inspect the response after a real exit.
ob_start(static function($output){
 if(http_response_code()!==503 || strpos($output,'SYNTHETIC_SQL_AND_CREDENTIALS')!==false || strpos($output,'Não foi possível concluir a instalação')===false){throw new RuntimeException('inline abort leaked diagnostics or claimed success');}
 return "Native installer abort: safe failure response, no diagnostic/credential leak.\n";
});
$_SERVER['REQUEST_METHOD']='POST';$_SERVER['SCRIPT_FILENAME']=$root.'/install/index.php';
InstallationMigrationBootstrap::run($root,17);
throw new RuntimeException('synthetic exit unexpectedly returned');
