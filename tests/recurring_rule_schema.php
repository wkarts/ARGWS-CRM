<?php
declare(strict_types=1);
define('BASEPATH', dirname(__DIR__) . '/system/');
function db_prefix(): string { return 'probe_'; }
class CI_Migration { public $db; }
require dirname(__DIR__) . '/application/migrations/383_version_383.php';
$probe = new class {
    public array $queries = [];
    public function query($sql): bool { $this->queries[] = trim($sql); return true; }
};
$migration = new Migration_Version_383(); $migration->db = $probe; $migration->up(); $first = $probe->queries; $migration->up(); $migration->down();
if (count($probe->queries) !== 8 || array_slice($probe->queries, 4) !== $first) throw new RuntimeException('Repeat-safe additive migration expected.');
$installer = file_get_contents(dirname(__DIR__) . '/install/database.sql');
foreach ($first as $sql) {
    if (!str_starts_with($sql, 'CREATE TABLE IF NOT EXISTS') || !str_contains($sql, 'ENGINE=InnoDB')) throw new RuntimeException('Only additive InnoDB tables are allowed.');
    if (!str_contains($installer, str_replace('`probe_', '`tbl', $sql))) throw new RuntimeException('Installer and migration schema must agree exactly.');
}
if (!str_contains($first[3], 'UNIQUE KEY `recurring_cycle` (`rule_id`,`cycle_date`)')) throw new RuntimeException('Cycle uniqueness is required.');
echo "Independent recurring schema: additive repeat-safe tables, InnoDB, cycle uniqueness and installer parity passed (mock database).\n";
