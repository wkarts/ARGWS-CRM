<?php
declare(strict_types=1);

// The setup web request invokes this fixed CLI entrypoint after creating the
// persistent application configuration. Keep the route independent from argv.
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "O executor de migrations precisa rodar em PHP CLI." . PHP_EOL);
    exit(70);
}

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');
fwrite(STDERR, '[ARGWS CRM setup] Adaptador de migrations iniciado (SAPI=' . PHP_SAPI
    . '; token interno=' . (getenv('ARGWS_SETUP_MIGRATION_TOKEN') !== false ? 'presente' : 'ausente')
    . '; config=' . (is_file('/app/application/config/app-config.php') ? 'presente' : 'ausente') . ').' . PHP_EOL);
register_shutdown_function(static function () {
    $error = error_get_last();
    if (is_array($error) && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        fwrite(STDERR, '[ARGWS CRM setup] Encerramento após erro fatal no bootstrap (tipo=' . (int) $error['type']
            . '; arquivo=' . basename((string) ($error['file'] ?? 'desconhecido')) . ').' . PHP_EOL);
    }
});

$_SERVER['argv'] = ['/app/index.php', 'argws_provisioning', 'apply_migrations'];
$_SERVER['argc'] = count($_SERVER['argv']);
$_SERVER['SCRIPT_FILENAME'] = '/app/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

// CodeIgniter's front controller accepts explicit routing overrides. Supplying
// them here avoids route ambiguity when the index is loaded as an adapter.
$routing = [
    'controller' => 'Argws_provisioning',
    'function' => 'apply_migrations',
];

chdir('/app');
require '/app/index.php';
