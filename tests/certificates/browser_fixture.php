<?php
/** In-memory synthetic upload only; called inside the disposable browser fixture. */
if (PHP_SAPI!=='cli'||getenv('ISSUERS_BROWSER_DB_NAME')!=='argws_upgrade_ci') { exit(2); }
require __DIR__.'/support.php';
$tax=$argv[1]??'';
if ($tax!==''&&!preg_match('/^[A-Z0-9]{12}[0-9]{2}$/D',$tax)) { exit(2); }
$fixture=a1fixture($tax===''?null:$tax);
echo json_encode(['pfx'=>base64_encode($fixture['pfx']),'password'=>$fixture['password']],JSON_THROW_ON_ERROR);
