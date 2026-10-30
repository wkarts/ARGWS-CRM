<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');

require_once __DIR__ . '/provisioner.php';

try {
    provision($argv);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Erro no provisionamento: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
