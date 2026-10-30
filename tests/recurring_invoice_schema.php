<?php

declare(strict_types=1);

define('BASEPATH', dirname(__DIR__) . '/system/');
function db_prefix(): string { return 'test_'; }

class CI_Migration
{
    public $db;
}
final class SchemaProbe
{
    public bool $present = false;
    public array $queries = [];
    public function field_exists(string $field, string $table): bool
    {
        if ($field !== 'recurring_due_mode' || $table !== 'test_invoices') {
            throw new RuntimeException('Unexpected schema target.');
        }
        return $this->present;
    }
    public function query(string $sql): void
    {
        $this->queries[] = $sql;
        $this->present = true;
    }
}

require dirname(__DIR__) . '/application/migrations/381_version_381.php';
$migration = new Migration_Version_381();
$migration->db = new SchemaProbe();
$migration->up();
$migration->up();
$migration->down();
$expected = "ALTER TABLE `test_invoices` ADD `recurring_due_mode` VARCHAR(16) NOT NULL DEFAULT 'days'";
if ($migration->db->queries !== [$expected] || !$migration->db->present) {
    throw new RuntimeException('Migration must add once and retain choices on rollback.');
}
$schema = file_get_contents(dirname(__DIR__) . '/install/database.sql');
if (!str_contains($schema, "`recurring_due_mode` varchar(16) NOT NULL DEFAULT 'days'")) {
    throw new RuntimeException('Fresh installer default must match migration.');
}
echo 'Recurring invoice schema: additive, repeat-safe migration and installer defaults passed (mock database).' . PHP_EOL;
