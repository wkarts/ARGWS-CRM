<?php
/** Read-only bounded state census. Never prints rows, configuration values or filenames. */
if (PHP_SAPI !== 'cli' || getenv('ISSUERS_BROWSER_DB_NAME') !== 'argws_upgrade_ci') { exit(2); }
set_error_handler(static function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
define('FCPATH', dirname(__DIR__, 2) . '/');
define('BASEPATH', dirname(__DIR__, 2) . '/system/');
define('APPPATH', dirname(__DIR__, 2) . '/application/');
require APPPATH . 'config/app-config.php';
if (APP_DB_NAME !== 'argws_upgrade_ci' || !preg_match('/^argws-crm-provision-db-[0-9]+$/D', APP_DB_HOSTNAME)) { exit(2); }
$pdo = new PDO('mysql:host=' . APP_DB_HOSTNAME . ';dbname=' . APP_DB_NAME . ';charset=utf8mb4', APP_DB_USERNAME, APP_DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$pdo->beginTransaction();
$tables = [];
$structure = [];
foreach ([
    'tables' => 'SELECT TABLE_NAME,ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME',
    'indexes' => 'SELECT TABLE_NAME,INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME,COLLATION,SUB_PART,INDEX_TYPE,IS_VISIBLE,EXPRESSION FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX',
    'keys' => 'SELECT TABLE_NAME,CONSTRAINT_NAME,COLUMN_NAME,ORDINAL_POSITION,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION',
    'checks' => 'SELECT CONSTRAINT_NAME,CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() ORDER BY CONSTRAINT_NAME',
    'references' => 'SELECT TABLE_NAME,CONSTRAINT_NAME,UPDATE_RULE,DELETE_RULE,REFERENCED_TABLE_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() ORDER BY TABLE_NAME,CONSTRAINT_NAME',
    'views' => 'SELECT TABLE_NAME,VIEW_DEFINITION,CHECK_OPTION,IS_UPDATABLE,SECURITY_TYPE FROM information_schema.VIEWS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME',
    'triggers' => 'SELECT TRIGGER_NAME,EVENT_MANIPULATION,EVENT_OBJECT_TABLE,ACTION_ORDER,ACTION_CONDITION,ACTION_STATEMENT,ACTION_ORIENTATION,ACTION_TIMING FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() ORDER BY TRIGGER_NAME',
] as $kind => $sql) {
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $structure[$kind] = ['count' => count($rows), 'sha256' => hash('sha256', serialize($rows))];
}

foreach ($pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN) as $table) {
    if (!preg_match('/^tbl[a-zA-Z0-9_]+$/D', $table)) { throw new RuntimeException('Unexpected synthetic table identifier'); }
    $rows = []; $query = $pdo->query('SELECT * FROM `' . $table . '`');
    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        if (count($rows) >= 100000) { throw new RuntimeException('Synthetic table census bound exceeded'); }
        ksort($row);
        // Binary BLOBs and invalid UTF-8 stay opaque, with order-independent row fingerprints.
        $rows[] = hash('sha256', serialize($row));
    }
    sort($rows, SORT_STRING);
    $columns = $pdo->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,CHARACTER_SET_NAME,COLLATION_NAME,COLUMN_COMMENT,GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
    $columns->execute([$table]);
    $tables[$table] = ['count' => count($rows), 'sha256' => hash('sha256', implode("\n", $rows)), 'schema' => hash('sha256', serialize($columns->fetchAll(PDO::FETCH_ASSOC)))];
}
$pdo->commit();
$files = [];
// Private uploads and config must not be lost in a hypothetical DB-only checkpoint.
foreach (['uploads' => '/app/uploads', 'config' => '/var/lib/argws-crm/config'] as $label => $root) {
    if (!is_dir($root)) { throw new RuntimeException('Required synthetic filesystem root missing'); }
    $entries = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isLink()) { throw new RuntimeException('Unexpected symlink in synthetic data root'); }
        if (!$file->isFile()) { continue; }
        if (count($entries) >= 20000 || $file->getSize() > 33554432) { throw new RuntimeException('Synthetic file census bound exceeded'); }
        $relative = substr($file->getPathname(), strlen($root) + 1);
        $entries[hash('sha256', $relative)] = ['mode' => ($file->getPerms() & 07777), 'uid' => $file->getOwner(), 'gid' => $file->getGroup(), 'bytes' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getPathname())];
    }
    ksort($entries);
    $files[$label] = $entries;
}
echo json_encode(['runtime' => ['php' => PHP_VERSION, 'mysql' => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION)], 'structure' => $structure, 'tables' => $tables, 'files' => $files], JSON_THROW_ON_ERROR) . PHP_EOL;
