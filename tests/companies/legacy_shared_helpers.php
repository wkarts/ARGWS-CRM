<?php
// Actual native helper functions with SQLite query execution, no HTTP/production access.
define('BASEPATH',__DIR__);
require __DIR__.'/../../application/services/ClientIssuerScope.php';
require __DIR__.'/../../application/helpers/database_helper.php';
require __DIR__.'/../../application/helpers/datatables_helper.php';
function db_prefix(){return 'q_';}
function &get_instance(){return $GLOBALS['ci'];}
function startsWith($s,$prefix){return str_starts_with($s,$prefix);}
function hooks(){return new class {function apply_filters($name,$value,...$args){return $value;}};}
class SharedResult {
    public function __construct(private array $rows){}
    public function row(){return isset($this->rows[0])?(object)$this->rows[0]:null;}
    public function result_array(){return $this->rows;}
}
class SharedDb {
    private array $where=[],$args=[];private string $select='*',$from='';
    public function __construct(public PDO $pdo){}
    public function table_exists($table){$q=$this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");$q->execute([$table]);return(bool)$q->fetch();}
    public function query($sql,$args=[]){$q=$this->pdo->prepare($sql);$q->execute($args);return new SharedResult($q->fetchAll(PDO::FETCH_ASSOC));}
    public function where($key,$value=null,$escape=null){if(is_array($key)){foreach($key as$k=>$v)$this->where($k,$v);return$this;}$this->condition($key.($value===null?'':'=?'));if($value!==null)$this->args[]=$value;return$this;}
    private function condition($v){if($this->where && end($this->where)!=='(')$this->where[]='AND';$this->where[]=$v;}
    public function group_start(){$this->condition('(');return$this;}
    public function group_end(){$this->where[]=')';return$this;}
    public function select_sum($field){$this->select="SUM($field) AS $field";return$this;}
    public function from($table){$this->from=$table;return$this;}
    public function count_all_results($table){$this->select='COUNT(*) AS n';return$this->get($table)->row()->n;}
    public function get($table=''){$sql='SELECT '.$this->select.' FROM '.($table?:$this->from).($this->where?' WHERE '.implode(' ',$this->where):'');$args=$this->args;$this->where=$this->args=[];$this->select='*';$this->from='';return$this->query($sql,$args);}
}
$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE q_clients(userid INT,company TEXT,active INT,balance INT); INSERT INTO q_clients VALUES(1,'Legacy',1,10),(2,'Private A',1,500),(3,'Private B',1,600); CREATE TABLE q_contacts(id INT,userid INT); INSERT INTO q_contacts VALUES(10,1),(20,2); CREATE TABLE q_issuers_clients(client_id INT,owner_issuer_id INT); INSERT INTO q_issuers_clients VALUES(2,41),(3,82); CREATE TABLE q_issuers_client_links(client_id INT,issuer_id INT); INSERT INTO q_issuers_client_links VALUES(2,82)");
$ci=(object)['db'=>new SharedDb($pdo),'input'=>new class {function post($key=null){$data=['draw'=>1,'start'=>null,'length'=>-1,'order'=>null,'search'=>['value'=>''],'columns'=>[]];return$key===null?$data:($data[$key]??null);}}];
$n=0;function verify($condition,$message){global$n;$n++;if(!$condition)throw new RuntimeException($message);}
verify((int)total_rows('q_clients',['active'=>1])===1,'generic client count excludes owned identities');
verify((int)total_rows('q_contacts')===1,'generic contact count excludes private relationships');
verify((int)sum_from_table('q_clients',['field'=>'balance'])===10,'generic customer sums do not aggregate other companies');
verify((int)total_rows('q_clients AS customer')===1,'safe explicit aliases are covered');
verify(\app\services\ClientIssuerScope::legacyTableSql($ci->db,'q_','q_other')===null,'unrelated tables retain previous behavior');
$table=data_tables_init(['userid','company'],'userid','q_clients');
verify(count($table['rResult'])===1 && (int)$table['rResult'][0]['userid']===1,'native DataTables returns legacy rows only');
verify((int)$table['output']['iTotalRecords']===1 && (int)$table['output']['iTotalDisplayRecords']===1,'DataTables total and filtered count both scoped');
$table=data_tables_init(['userid','company'],'userid','q_clients',[],['AND userid=2 OR userid=1']);
verify(count($table['rResult'])===1 && (int)$table['rResult'][0]['userid']===1,'OR predicates cannot escape DataTables privacy boundary');
verify((int)total_rows('q_clients','userid=2 OR userid=1')===1,'OR predicates cannot escape generic counts');
verify((int)sum_from_table('q_clients',['field'=>'balance','where'=>['userid=2 OR userid=1']])===10,'OR predicates cannot escape aggregate privacy boundary');
$table=data_tables_init(['id','userid'],'id','q_contacts');
verify(count($table['rResult'])===1 && (int)$table['rResult'][0]['id']===10,'contacts DataTables inherits customer identity boundary');
$table=data_tables_init(['userid','company'],'userid','q_clients AS customer');
verify(count($table['rResult'])===1,'aliased DataTables query remains scoped');
$pdo->exec('DROP TABLE q_issuers_clients');
verify((int)total_rows('q_clients')===0,'partially missing ownership schema fails closed');
$pdo->exec('DROP TABLE q_issuers_client_links');
verify((int)total_rows('q_clients')===3,'pre-company installation preserves legacy count semantics');
echo "Shared legacy customer helpers: $n checks passed.\n";
