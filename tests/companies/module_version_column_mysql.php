<?php
/** Actual migration SQL through CI3 PDO MySQL/MariaDB, in a dedicated disposable database.
 * The migration dispatch shell alone is adapted; schema, metadata and values are real SQL.
 */
declare(strict_types=1);
require __DIR__ . '/legacy_financial_module_fixture.php';
class CI_Migration { public $db; }
require APPPATH . 'migrations/472_version_472.php';
$prefix = 'version472_' . bin2hex(random_bytes(4)) . '_';
$GLOBALS['moduleReaderPrefix'] = $prefix;
$db->set_dbprefix($prefix);
$table = $prefix . 'modules';
$migration = new Migration_Version_472();
$migration->db = $db;
$checks = 0;
function versionColumnCheck(bool $ok, string $message): void
{
    ++$GLOBALS['checks'];
    if (!$ok) { throw new RuntimeException($message); }
}
function versionColumnMetadata($db, string $table): array
{
    return $db->query("SHOW FULL COLUMNS FROM `{$table}` LIKE 'installed_version'")->row_array();
}
function versionColumnSnapshot($db, string $table): array
{
    return $db->query("SELECT module_name, HEX(installed_version) AS version_bytes, active FROM `{$table}` ORDER BY module_name")->result_array();
}
try {
    // A temporary table prevents collisions and is discarded even if PHP is interrupted.
    $db->query("CREATE TEMPORARY TABLE `{$table}` (module_name VARCHAR(55) PRIMARY KEY,
        installed_version VARCHAR(11) COLLATE utf8mb4_bin NOT NULL DEFAULT '1.0.0' COMMENT 'legacy version label',
        active TINYINT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    foreach ([['gerencianet_gateway', '5.1.4 sdk f', 1], ['banco_brasil', '1.2.3', 0], ['ideal', '1.0.1', 0]] as [$name, $version, $active]) {
        $db->query("INSERT INTO `{$table}` VALUES (?, ?, ?)", [$name, $version, $active]);
    }
    $before = versionColumnSnapshot($db, $table);
    $metadata = versionColumnMetadata($db, $table);
    $migration->up();
    $after = versionColumnMetadata($db, $table);
    versionColumnCheck(strtolower($after['Type']) === 'varchar(64)', 'actual column widened to64');
    foreach (['Collation', 'Null', 'Default', 'Extra', 'Comment'] as $key) {
        versionColumnCheck($after[$key] === $metadata[$key], 'metadata preserved: ' . $key);
    }
    versionColumnCheck(versionColumnSnapshot($db, $table) === $before, 'every existing byte and activation state preserved');
    versionColumnCheck($db->query("SELECT installed_version FROM `{$table}` WHERE module_name='gerencianet_gateway'")->row()->installed_version === '5.1.4 sdk f', 'truncated legacy version is not silently reconstructed');
    $db->query("INSERT INTO `{$table}` VALUES (?, ?, ?)", ['synthetic_long_version', '5.1.4 sdk final', 0]);
    versionColumnCheck($db->query("SELECT installed_version FROM `{$table}` WHERE module_name='synthetic_long_version'")->row()->installed_version === '5.1.4 sdk final', 'new long label fits without truncation');
    $snapshot = versionColumnSnapshot($db, $table);
    $queryOffset = count($db->queries);
    $migration->up();
    versionColumnCheck(!array_filter(array_slice($db->queries, $queryOffset), fn($sql) => preg_match('/^\s*ALTER\b/i', $sql)), 'replay issues no ALTER');
    versionColumnCheck(versionColumnSnapshot($db, $table) === $snapshot, 'replay preserves real rows');
    $migration->down();
    versionColumnCheck(versionColumnMetadata($db, $table) === $after, 'rollback never narrows or changes metadata');
    $db->query("DROP TEMPORARY TABLE `{$table}`");

    $db->query("CREATE TEMPORARY TABLE `{$table}` (module_name VARCHAR(55) PRIMARY KEY,
        installed_version VARCHAR(11) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'nullable legacy', active TINYINT NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("INSERT INTO `{$table}` VALUES ('null_marker', NULL, 0)");
    $migration->up();
    $nullable = versionColumnMetadata($db, $table);
    versionColumnCheck($nullable['Null'] === 'YES' && $nullable['Default'] === null && $nullable['Comment'] === 'nullable legacy', 'nullable default and comment preserved');
    versionColumnCheck($db->query("SELECT installed_version FROM `{$table}` WHERE module_name='null_marker'")->row()->installed_version === null, 'existing NULL is preserved');
    $db->query("ALTER TABLE `{$table}` MODIFY installed_version VARCHAR(128) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'already wide'");
    $db->query("INSERT INTO `{$table}` VALUES (?, ?, 0)", ['already_wide', str_repeat('v', 90)]);
    $wide = versionColumnMetadata($db, $table); $wideRows = versionColumnSnapshot($db, $table);
    $migration->up();
    versionColumnCheck(versionColumnMetadata($db, $table) === $wide && versionColumnSnapshot($db, $table) === $wideRows, 'pre-existing wider schema and90-byte value remain unchanged');
    echo json_encode(['runtime' => PHP_VERSION, 'engine' => $db->query('SELECT VERSION() AS version')->row()->version,
        'checks' => $checks, 'passed' => $checks, 'acceptance' => 'PASS',
        'scope' => 'Real migration472 SQL on temporary tables; no production, activation, financial calls or module installers. Native release dispatcher not exercised.'], JSON_PRETTY_PRINT) . "\n";
} finally {
    $db->query("DROP TEMPORARY TABLE IF EXISTS `{$table}`");
}
