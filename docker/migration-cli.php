<?php
declare(strict_types=1);

// The setup web request invokes this fixed CLI entrypoint after creating the
// persistent application configuration. Do not depend on forwarded argv.
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "O executor de migrations precisa rodar em PHP CLI." . PHP_EOL);
    exit(70);
}

$_SERVER['argv'] = ['/app/index.php', 'argws_provisioning', 'apply_migrations'];
$_SERVER['argc'] = count($_SERVER['argv']);
$_SERVER['SCRIPT_FILENAME'] = '/app/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

chdir('/app');
require '/app/index.php';
