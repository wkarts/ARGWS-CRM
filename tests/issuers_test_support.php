<?php

declare(strict_types=1);

final class IssuerTestResult
{
    private array $rows;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function result_array(): array { return $this->rows; }
}
final class IssuerTestDb
{
    public PDO $pdo;
    public string $driver;
    public ?string $failOn = null;
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    public function query(string $sql, array $params = [])
    {
        if ($this->failOn !== null && strpos($sql, $this->failOn) !== false) {
            throw new RuntimeException('injected_storage_failure');
        }
        if ($this->driver === 'sqlite') {
            $metadata = issuerSqliteIntegrationColumn($this->pdo, $sql, $params);
            if ($metadata !== null) { return new IssuerTestResult($metadata); }
            $sql = str_replace(' FOR UPDATE', '', $sql);
            $sql = preg_replace('/ALTER TABLE `([^`]+)` ADD INDEX `([^`]+)` \(([^)]+)\)/','CREATE INDEX `$2` ON `$1` ($3)',$sql);
            $sql = str_replace(' CHARACTER SET ascii COLLATE ascii_bin','',$sql);
            $sql = preg_replace('/(?:int|bigint) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY/', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
            $sql = str_replace('INSERT IGNORE','INSERT OR IGNORE',$sql);
            $sql = preg_replace('/ ENGINE=InnoDB.*$/s','',$sql);
            $sql = preg_replace('/UNIQUE KEY `[^`]+` \(/','UNIQUE (',$sql);
            $sql = preg_replace('/,?\s*KEY `[^`]+` \([^)]*\)/','',$sql);
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return new IssuerTestResult($statement->columnCount() ? $statement->fetchAll(PDO::FETCH_ASSOC) : []);
    }
    public function table_exists($table): bool
    {
        if($this->driver==='sqlite') { return count($this->query("SELECT name FROM sqlite_master WHERE type='table' AND name=?",[$table])->result_array())===1; }
        return count($this->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->result_array())===1;
    }
    public function field_exists($field, $table): bool
    {
        if ($this->driver === 'sqlite') {
            return in_array($field, array_column($this->query('PRAGMA table_info(' . $table . ')')->result_array(), 'name'), true);
        }
        return count($this->query('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?', [$table, $field])->result_array()) === 1;
    }
    public function trans_begin(): bool { return $this->pdo->beginTransaction(); }
    public function trans_commit(): bool { return $this->pdo->commit(); }
    public function trans_rollback(): bool { return $this->pdo->inTransaction() ? $this->pdo->rollBack() : true; }
    public function trans_status(): bool { return true; }
    public function insert_id(): int { return (int) $this->pdo->lastInsertId(); }
}

/** Explicit SQLite adapter for the MySQL schema metadata query; not MySQL proof. */
function issuerSqliteIntegrationColumn(PDO $pdo, string $sql, array $params): ?array
{
    $table=$params[0]??'';
    if(!preg_match('/^[A-Za-z0-9_]+$/D',(string)$table)) { return null; }
    if(str_starts_with($sql,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLLATION_NAME FROM information_schema.COLUMNS')) {
        $rows=$pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC);
        return array_map(static fn($row)=>['COLUMN_NAME'=>$row['name'],'COLUMN_TYPE'=>strtolower($row['type']),'IS_NULLABLE'=>($row['notnull']||$row['pk'])?'NO':'YES','COLLATION_NAME'=>in_array($row['name'],['feature','capability'],true)?'ascii_bin':null],$rows);
    }
    if(str_starts_with($sql,'SELECT INDEX_NAME,NON_UNIQUE,COLUMN_NAME,SEQ_IN_INDEX,SUB_PART FROM information_schema.STATISTICS')) {
        $out=[];
        foreach($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC) as$row){if($row['pk'])$out[]=['INDEX_NAME'=>'PRIMARY','NON_UNIQUE'=>0,'COLUMN_NAME'=>$row['name'],'SEQ_IN_INDEX'=>$row['pk'],'SUB_PART'=>null];}
        foreach($pdo->query('PRAGMA index_list('.$table.')')->fetchAll(PDO::FETCH_ASSOC) as$index){
            if(($index['origin']??'')==='pk')continue;
            $columns=$pdo->query('PRAGMA index_info("'.str_replace('"','""',$index['name']).'")')->fetchAll(PDO::FETCH_ASSOC);
            $name=$index['name'];
            // SQLite translates named UNIQUE KEY into an unnamed UNIQUE constraint.
            if(array_column($columns,'name')===['issuer_id','series','number']&&$index['unique'])$name='issuer_series_number';
            foreach($columns as$row)$out[]=['INDEX_NAME'=>$name,'NON_UNIQUE'=>$index['unique']?0:1,'COLUMN_NAME'=>$row['name'],'SEQ_IN_INDEX'=>$row['seqno']+1,'SUB_PART'=>null];
        }
        return $out;
    }
    if(str_starts_with($sql,'SELECT COLUMN_NAME FROM information_schema.STATISTICS')) {
        $rows=array_filter($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC),static fn($row)=>(int)$row['pk']>0);
        usort($rows,static fn($a,$b)=>$a['pk']<=>$b['pk']);
        return array_map(static fn($row)=>['COLUMN_NAME'=>$row['name']],$rows);
    }
    if(str_starts_with($sql,'SELECT ENGINE FROM information_schema.TABLES')) {
        $statement=$pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");$statement->execute([$table]);
        return $statement->fetch()? [['ENGINE'=>'InnoDB']] : []; // Explicit emulation, never MySQL engine proof.
    }
    if(str_starts_with($sql,'SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME,ORDINAL_POSITION FROM information_schema.KEY_COLUMN_USAGE')) {
        $rows=$pdo->query('PRAGMA foreign_key_list('.$table.')')->fetchAll(PDO::FETCH_ASSOC);
        usort($rows,static fn($a,$b)=>[$a['id'],$a['seq']]<=>[$b['id'],$b['seq']]);
        return array_map(static fn($row)=>['CONSTRAINT_NAME'=>'fk'.$row['id'],'COLUMN_NAME'=>$row['from'],'REFERENCED_TABLE_NAME'=>$row['table'],'REFERENCED_COLUMN_NAME'=>$row['to'],'ORDINAL_POSITION'=>$row['seq']+1],$rows);
    }
    if (!str_starts_with($sql, 'SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS')) { return null; }
    $rows = $pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC);
    $found = array_values(array_filter($rows, static fn($row) => $row['name'] === $params[1]));
    return array_map(static fn($row) => ['COLUMN_TYPE'=>$row['type'], 'IS_NULLABLE'=>$row['notnull'] ? 'NO' : 'YES', 'COLUMN_DEFAULT'=>strtoupper((string)$row['dflt_value'])==='NULL'?null:$row['dflt_value']], $found);
}
