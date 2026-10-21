<?php
/** Test-only adapter: inject a committed change between real owned transactions. */
final class CalendarHookDatabase
{
    public PDO $pdo;
    public string $dbdriver='pdo',$subdriver;
    public bool $db_debug=true;
    public array $data_cache=[];
    public $afterCommit=null;
    public int $commits=0;
    public function __construct(private EssentialTestDb $inner){$this->pdo=$inner->pdo;$this->subdriver=$inner->subdriver;$this->data_cache=&$inner->data_cache;}
    public function __call($name,$args){return $this->inner->$name(...$args);}
    public function trans_commit():bool{$ok=$this->inner->trans_commit();if($ok){$this->commits++;if($this->afterCommit)($this->afterCommit)($this->commits);}return$ok;}
}
