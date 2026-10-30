<?php
/** CI-shaped adapter for tests against real MySQL; no production bootstrap/credentials. */
class BbPdoDatabase
{
    public string $dbdriver='pdo';public string $subdriver='mysql';public bool $db_debug=false;public bool $save_queries=false;public array $data_cache=[];
    protected $_trans_depth=0;
    private array $where=[];private string $columns='*';private string $order='';private string $limit='';
    private bool $ok=true;
    public function __construct(public PDO $conn_id){}
    public function where($key,$value){if(!preg_match('/^[a-z_]+$/iD',$key))throw new RuntimeException('column');$this->where[$key]=$value;return $this;}
    public function select($columns){if(!preg_match('/^[a-z_,*]+$/iD',$columns))throw new RuntimeException('columns');$this->columns=$columns;return $this;}
    public function order_by($column,$direction){if(!preg_match('/^[a-z_]+$/iD',$column)||!in_array($direction,['ASC','DESC'],true))throw new RuntimeException('order');$this->order=" ORDER BY `$column` $direction";return $this;}
    public function limit($n){$this->limit=' LIMIT '.(int)$n;return $this;}
    private function clause(){return $this->where?' WHERE '.implode(' AND ',array_map(fn($k)=>"`$k`=?",array_keys($this->where))):'';}
    private function reset(){ $this->where=[];$this->columns='*';$this->order='';$this->limit=''; }
    public function get($table){$sql="SELECT {$this->columns} FROM `$table`".$this->clause().$this->order.$this->limit;$b=array_values($this->where);$this->reset();return $this->query($sql,$b);}
    public function get_where($table,$where){foreach($where as $key=>$value)$this->where($key,$value);return $this->get($table);}
    public function update($table,$values){$sql="UPDATE `$table` SET ".implode(',',array_map(fn($k)=>"`$k`=?",array_keys($values))).$this->clause();$b=array_merge(array_values($values),array_values($this->where));$this->reset();return $this->query($sql,$b)!==false;}
    public function insert($table,$values){$keys=array_keys($values);return $this->query("INSERT INTO `$table` (`".implode('`,`',$keys)."`) VALUES (".implode(',',array_fill(0,count($keys),'?')).")",array_values($values))!==false;}
    public function table_exists($table){return $this->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->row_array()!==[];}
    public function field_exists($field,$table){return $this->query('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',[$table,$field])->row_array()!==[];}
    public function insert_id(){return $this->conn_id->lastInsertId();}
    public function query($sql,$bind=[]){try{$s=$this->conn_id->prepare($sql);$s->execute($bind);return new class($s){private array $rows;function __construct(PDOStatement $s){$this->rows=$s->columnCount()?$s->fetchAll(PDO::FETCH_ASSOC):[];}function row_array(){return $this->rows[0]??[];}function row(){return isset($this->rows[0])?(object)$this->rows[0]:null;}function result_array(){return $this->rows;}};}catch(PDOException $e){$this->ok=false;return false;}}
    public function trans_begin(){if($this->_trans_depth)throw new RuntimeException('nested');$this->ok=true;$this->_trans_depth=1;return $this->conn_id->beginTransaction();}
    public function trans_status(){return $this->ok;}public function trans_commit(){$v=$this->conn_id->commit();$this->_trans_depth=0;return $v;}
    public function trans_rollback(){$v=$this->conn_id->rollBack();$this->_trans_depth=0;return $v;}
}
