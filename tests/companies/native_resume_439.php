<?php
/** Recreated proof: actual recovered437–439 schema bodies, never historical logs. */
declare(strict_types=1);
$resumeSchemas = [
    437 => ['CompanyProjectDiscussionsSchema', 'CompanyMicrosoftMailOAuthSchema', 'CompanyAsaasRefundSchema', 'CompanyStandaloneBillingSchema'],
    438 => ['CompanyProjectDiscussionExtrasSchema', 'CompanyTaskTimeBillingSchema', 'CompanyInvoiceRecurringSchema', 'CompanyConnectApiSchema', 'CompanyConnectInstanceOperationsSchema'],
    439 => ['CompanyFinancialApiSchema', 'CompanyInvoiceCopiesSchema', 'CompanyPostRefundSchema', 'CompanyPostRefundManualSchema', 'CompanyTaskTimeBatchSchema', 'CompanyConnectPairingSchema', 'CompanyPusherSchema'],
];
register_shutdown_function(static function () use ($resumeSchemas): void {
    if (!isset($GLOBALS['db'], $GLOBALS['p']) || !preg_match('/^essential_[a-f0-9]{10}_$/D', $GLOBALS['p'])) return;
    $tables = [];
    foreach ($resumeSchemas as $schemas) foreach ($schemas as $schema) {
        if (!class_exists($schema)) continue;
        $method = new ReflectionMethod($schema, 'definitions');
        $definitions = $method->getNumberOfRequiredParameters() ? $schema::definitions($GLOBALS['p']) : $schema::definitions();
        $tables = array_merge($tables, array_keys($definitions));
    }
    foreach (array_reverse($tables) as $table) $GLOBALS['db']->query('DROP TABLE IF EXISTS `'.$GLOBALS['p'].$table.'`');
    $GLOBALS['db']->query('DROP TABLE IF EXISTS `'.$GLOBALS['p'].'migrations`');
    unset($GLOBALS['db']->data_cache['table_names']);
});
require __DIR__.'/native_436.php';

if (($argv[1] ?? '') === '--tracking') {
    foreach ($resumeSchemas as $schemas) foreach ($schemas as $schema) require_once APPPATH.'services/'.$schema.'.php';
    $trackingPipes = [];
    $trackingProcess = proc_open([PHP_BINARY, __DIR__.'/native_resume_439_tracking.php', $p], [0=>['pipe','r'], 1=>['pipe','w'], 2=>['file','php://stderr','w']], $trackingPipes);
    if (!is_resource($trackingProcess)) throw new RuntimeException('resume439_tracking_process_failed');
    fclose($trackingPipes[0]); $trackingOutput = stream_get_contents($trackingPipes[1]); fclose($trackingPipes[1]);
    $trackingStatus = proc_close($trackingProcess); echo $trackingOutput;
    essentialCheck($trackingStatus === 0 && str_contains($trackingOutput, 'Native439 actual executor confirmed:'), 'actual native executor437–439 tracking, failures, retry and replay');
    // Load schema classes solely so child-first shutdown can enumerate their real ledgers.
    foreach ($resumeSchemas as $schemas) foreach ($schemas as $schema) require_once APPPATH.'services/'.$schema.'.php';
    exit(0);
}

$snap = static function (string $table) use ($db): array {
    $rows = array_map('serialize', $db->query('SELECT * FROM `'.$table.'`')->result_array());
    sort($rows, SORT_STRING); return $rows;
};
foreach ($resumeSchemas as $version => $schemas) {
    $beforeTables = array_column($db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,?)=?', [strlen($p), $p])->result_array(), 'TABLE_NAME');
    $before = []; foreach ($beforeTables as $table) $before[$table] = $snap($table);
    require_once APPPATH.'migrations/'.$version.'_version_'.$version.'.php';
    $class = 'Migration_Version_'.$version; $migration = new $class(); $migration->db = $db;
    $migration->up(); $migration->up(); unset($db->data_cache['table_names']);
    $expected = [];
    foreach ($schemas as $schema) {
        $method = new ReflectionMethod($schema, 'definitions');
        $definitions = $method->getNumberOfRequiredParameters() ? $schema::definitions($p) : $schema::definitions();
        $expected = array_merge($expected, array_map(static fn($name) => $p.$name, array_keys($definitions)));
        if (method_exists($schema, 'assertValid')) $schema::assertValid($db, $p); else $schema::ensure($db, $p, false);
    }
    $afterTables = array_column($db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,?)=?', [strlen($p), $p])->result_array(), 'TABLE_NAME');
    $added = array_values(array_diff($afterTables, $beforeTables)); sort($added); sort($expected);
    essentialCheck($added === $expected, 'recreated'.$version.' exact new tables and native replay');
    foreach ($expected as $table) essentialCheck((int)$db->query('SELECT COUNT(*) n FROM `'.$table.'`')->row()->n === 0, 'recreated'.$version.' no backfill into '.$table);
    foreach ($before as $table => $rows) essentialCheck($rows === $snap($table), 'recreated'.$version.' unchanged historical bytes '.$table);
    echo 'Recreated native'.$version.': '.count($expected).' additive tables, exact metadata, replay and historical rows PASS.'.PHP_EOL;
}
echo 'Recreated native437–439 body checks: '.$GLOBALS['essentialChecks'].' cumulative; actual runner tracking tested separately.'.PHP_EOL;
