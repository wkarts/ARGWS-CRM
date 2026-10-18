<?php

declare(strict_types=1);
require_once __DIR__ . '/../modules/issuers/services/IssuerRegistry.php';
require_once __DIR__ . '/../modules/issuers/services/IssuerSchema.php';
require_once __DIR__ . '/issuers_test_support.php';

$dsn = getenv('ISSUERS_TEST_DSN');
if (!$dsn || strpos($dsn, 'mysql:') !== 0) {
    fwrite(STDERR, "MySQL DSN is required for the concurrency test.\n");
    exit(2);
}
function connection(): IssuerTestDb
{
    return new IssuerTestDb(new PDO(getenv('ISSUERS_TEST_DSN'), getenv('ISSUERS_TEST_USER'), getenv('ISSUERS_TEST_PASSWORD')));
}
if (($argv[1] ?? '') === 'worker') {
    $registry = new IssuerRegistry(connection(), $argv[2]);
    while (microtime(true) < (float) $argv[3]) { usleep(1000); }
    try {
        $registry->saveIssuer(1, ['legal_name'=>'Synthetic concurrent issuer','tax_id'=>'12345678000195']);
        echo 'created';
    } catch (DomainException $exception) {
        if ($exception->getMessage() !== 'issuer_tax_conflict') { throw $exception; }
        echo 'conflict';
    }
    exit(0);
}
$db = connection();
$prefix = 'test_race_' . bin2hex(random_bytes(4)) . '_';
$db->query("CREATE TABLE {$prefix}staff (staffid INT PRIMARY KEY, admin INT, active INT)");
$db->query("INSERT INTO {$prefix}staff VALUES (1,1,1)");
foreach (IssuerSchema::statements($prefix) as $sql) { $db->query($sql); }
$start = (string) (microtime(true) + 1.5);
$workers = [];
for ($i = 0; $i < 2; $i++) {
    $pipes = [];
    $process = proc_open([PHP_BINARY, __FILE__, 'worker', $prefix, $start], [1=>['pipe','w'],2=>['pipe','w']], $pipes);
    if (!is_resource($process)) { throw new RuntimeException('Failed to start concurrent worker'); }
    $workers[] = [$process,$pipes];
}
$outputs = [];
foreach ($workers as [$process,$pipes]) {
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0 || $error !== '') { throw new RuntimeException('Concurrency worker failed: ' . $error); }
    $outputs[] = $output;
}
sort($outputs);
if ($outputs !== ['conflict','created']) { throw new RuntimeException('Concurrent duplicate was not serialized: '.json_encode($outputs)); }
$rows = $db->query("SELECT COUNT(*) AS total FROM {$prefix}issuers_registry")->result_array();
if ((int) $rows[0]['total'] !== 1) { throw new RuntimeException('Concurrent issuer duplication'); }
echo "Issuer concurrency: two MySQL writers, exactly one created and one rejected.\n";
