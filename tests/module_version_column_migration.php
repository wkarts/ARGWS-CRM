<?php
declare(strict_types=1);
define('BASEPATH', __DIR__);
function db_prefix() { return 'fixture_'; }
class CI_Migration { public $db; }
require dirname(__DIR__) . '/application/migrations/472_version_472.php';
final class VersionColumnDb
{
    public array $queries = [];
    public bool $fail = false;
    public object $column;
    public function __construct(int $width = 11)
    {
        $this->column = (object) ['Type' => "varchar($width)", 'Collation' => 'utf8mb4_unicode_ci', 'Null' => 'NO', 'Default' => null, 'Extra' => '', 'Comment' => 'legacy'];
    }
    public function query($sql)
    {
        $this->queries[] = $sql;
        if (str_starts_with($sql, 'SHOW FULL COLUMNS')) {
            return new class($this->column) { public function __construct(private object $column) {} public function row() { return $this->column; } };
        }
        if ($this->fail) { return false; }
        $this->column->Type = 'varchar(64)';
        return true;
    }
    public function escape($value) { return "'" . str_replace("'", "''", $value) . "'"; }
}
$checks = 0;
function checkColumn($ok, $label) { ++$GLOBALS['checks']; if (!$ok) { throw new RuntimeException($label); } }
$migration = new Migration_Version_472();
$db = $migration->db = new VersionColumnDb();
$migration->up();
checkColumn(count($db->queries) === 2, 'one metadata inspection and one DDL');
checkColumn(str_contains($db->queries[1], 'VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT'), 'preserve collation, nullability and comment');
checkColumn(!preg_match('/\b(UPDATE|INSERT|DELETE)\b/', implode(';', $db->queries)), 'no rewriting of existing version strings');
$migration->up(); checkColumn(count($db->queries) === 3, 'replay does not repeat ALTER');
$migration->down(); checkColumn(count($db->queries) === 3, 'rollback never truncates');
foreach ([64, 128] as $width) {
    $db = $migration->db = new VersionColumnDb($width); $migration->up();
    checkColumn(count($db->queries) === 1, 'never narrow existing capacity');
}
$db = $migration->db = new VersionColumnDb(); $db->column->Null = 'YES'; $db->column->Default = 'legacy'; $migration->up();
checkColumn(str_contains($db->queries[1], " NULL DEFAULT 'legacy'"), 'preserve nullable default');
$db = $migration->db = new VersionColumnDb(); $db->fail = true;
try { $migration->up(); throw new LogicException('failure accepted'); } catch (RuntimeException $e) { checkColumn($e->getMessage() === 'module_version_column_widen_failed', 'DDL failure blocks migration'); }
$db = $migration->db = new VersionColumnDb(); $db->column->Type = 'text';
try { $migration->up(); throw new LogicException('unknown type accepted'); } catch (RuntimeException $e) { checkColumn($e->getMessage() === 'module_version_column_unrecognized', 'unknown schema blocks migration'); }
echo "OK: $checks module version column checks\n";
