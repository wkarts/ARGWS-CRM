<?php
declare(strict_types=1);
/** Real DB decorator: explicit ignored writes, failure and post-query mutation, never SQLite. */
final class PurchaseTestDb
{
    public PDO $pdo; public array $data_cache=[]; public ?string $ignore=null; public ?string $fail=null; public $observer=null;
    public bool $db_debug=false; public bool $save_queries=false; private int $lastInsertId=0;
    public function __construct(public EssentialTestDb $inner) { $this->pdo=$inner->pdo; $this->data_cache=&$inner->data_cache; }
    public function query($sql,$args=[]) {
        if ($this->fail!==null&&str_contains($sql,$this->fail)) { return false; }
        if ($this->ignore!==null&&str_contains($sql,$this->ignore)) { return new EssentialTestResult([]); }
        $r=$this->inner->query($sql,$args); $this->lastInsertId=(int)$this->inner->insert_id(); $this->inner->queries=[];
        if ($this->observer!==null) { ($this->observer)($sql,$args,$this); }
        return $r;
    }
    // An injected AFTER-query write must not replace the original INSERT's ID.
    // Native SQL AFTER triggers preserve that ID for the caller as well.
    public function insert_id(): int { return $this->lastInsertId; }
    public function __call($name,$args) { return $this->inner->$name(...$args); }
}
