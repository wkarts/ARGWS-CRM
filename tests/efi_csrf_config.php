<?php
/** Actual config in child PHP processes with a disposable synthetic app-config, never deployment secrets. */
declare(strict_types=1);
if (isset($argv[1]) && $argv[1] === '--child') {
    define('BASEPATH', dirname(__DIR__) . '/system/');
    define('APPPATH', $argv[2] . '/');
    define('APP_MODULES_PATH', dirname(__DIR__) . '/modules/');
    define('ENVIRONMENT', 'testing');
    $_SERVER['REQUEST_URI'] = $argv[3];
    require dirname(__DIR__) . '/application/config/config.php';
    if ($config['csrf_protection'] !== true) { throw new RuntimeException('Efí inherited broad CSRF bypass'); }
    $exclusions = array_values(array_filter($config['csrf_exclude_uris'], fn($x) => str_contains($x, 'gerencia_net')));
    if ($exclusions !== ['gateways/gerencia_net/callback_boleto','gateways/gerencia_net/callback_cartao','gerencianet_gateway/gerencia_net/callback_boleto','gerencianet_gateway/gerencia_net/callback_cartao']) {
        throw new RuntimeException('Unexpected Efí CSRF exclusions');
    }
    echo "PASS\n"; exit;
}
$root = sys_get_temp_dir() . '/efi-csrf-' . bin2hex(random_bytes(8));
mkdir($root,0700); mkdir($root.'/config',0700);
file_put_contents($root.'/config/app-config.php', "<?php\ndefine('APP_CSRF_PROTECTION',true);\ndefine('APP_BASE_URL','https://synthetic.invalid/');\ndefine('APP_ENC_KEY','synthetic-only');\ndefine('SESS_DRIVER','files');\ndefine('SESS_SAVE_PATH',sys_get_temp_dir());\n");
try {
    foreach (['/gateways/gerencia_net/converterCertificado','/subdir/gateways/gerencia_net/invoice/17/hash','/gerencianet_gateway/gerencia_net/gerar_pix_por_javascript/17/hash','/gateways/gerencia_net/invoice/17/hash?next=gateways/other','/gateways/%67erencia_net/invoice/17/hash'] as $uri) {
        $pipes=[]; $process=proc_open([PHP_BINARY,__FILE__,'--child',$root,$uri],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if (!is_resource($process)) { throw new RuntimeException('PHP child unavailable'); }
        $out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        if (proc_close($process)!==0 || trim($out)!=='PASS' || $error!=='') { throw new RuntimeException('Synthetic config validation failed: '.$error); }
    }
    echo "Efí actual CSRF config: 5 isolated cases PASS\n";
} finally { unlink($root.'/config/app-config.php');rmdir($root.'/config');rmdir($root); }
