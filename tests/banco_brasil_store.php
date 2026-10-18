<?php
declare(strict_types=1);
require __DIR__.'/banco_brasil_cnab240.php'; // Synthetic fixture helpers; also executes codec regression.
require_once __DIR__.'/../modules/banco_brasil/libraries/BancoBrasil/ModuleStore.php';
use BancoBrasil\{ModuleStore,Transaction};
class BbResult {
 function __construct(private array $rows){}
 function row_array(){return $this->rows[0]??[];}function result_array(){return $this->rows;}
}
class BbDb {
 protected $_trans_depth=0;public $conn_id=null;public $tables=[];public $lastId=0;private $where=[];private $snapshot=[];
 public $db_debug=true;public $save_queries=true;public $failTable='';public $failCommit=false;public $engine='InnoDB';public $autocommit='1';public $completion='0';public $rawTransaction=false;public $rollbacks=0;public $commits=0;
 function where($k,$v){$this->where[$k]=$v;return $this;}function select($v){return $this;}function order_by(...$a){return $this;}function limit($n){return $this;}
 function get($table){$w=$this->where;$this->where=[];return new BbResult(array_values(array_filter($this->tables[$table]??[],fn($r)=>!array_diff_assoc($w,$r))));}
 function query($sql,$bind=[]){
  if(str_starts_with($sql,'SELECT @@'))return new BbResult([['autocommit'=>$this->autocommit,'completion_type'=>$this->completion]]);
  if(str_contains($sql,'information_schema'))return new BbResult([['ENGINE'=>$this->engine]]);
  if(str_starts_with($sql,'SET TRANSACTION'))return !$this->rawTransaction;
  if(str_contains($sql,'WHERE identity_hash='))return $this->where('identity_hash',$bind[0])->get('t_bb_connections');
  if(str_contains($sql,'WHERE id=? AND issuer_id=?'))return $this->where('id',$bind[0])->where('issuer_id',$bind[1])->get('t_bb_connections');
  throw new RuntimeException('unexpected SQL');
 }
 function insert($table,$row){if($this->failTable===$table)return false;$row['id']=count($this->tables[$table]??[])+1;$this->lastId=$row['id'];$this->tables[$table][]=$row;return true;}
 function insert_id(){return $this->lastId;}function trans_begin(){$this->snapshot=$this->tables;$this->_trans_depth=1;return true;}
 function trans_status(){return true;}function trans_commit(){if($this->failCommit)return false;$this->_trans_depth=0;$this->commits++;return true;}
 function trans_rollback(){$this->tables=$this->snapshot;$this->_trans_depth=0;$this->rollbacks++;return true;}
 function outer(){ $this->_trans_depth=1; }
}
$n=0;$db=new BbDb();$store=new ModuleStore($db,'t_');$issuer=['id'=>1,'tax_id'=>'12ABC34501DE35','legal_name'=>'EMPRESA SINTETICA'];
$data=['account_id'=>'001:00452-3:000000123873-X','environment'=>'sandbox','agreement_number'=>'1234567','wallet'=>'17','variation'=>'019'];
$id=$store->createConnection(1,9,$issuer,$data);check($id===1,'connection stored');check($db->commits===1,'commit before success');check($db->db_debug===true&&$db->save_queries===true,'caller query diagnostics restored after success');
check($db->tables['t_bb_connections'][0]['lifecycle']==='pending_verification','no fake verification');
check($db->tables['t_bb_audit'][0]['actor_id']===9,'audit actor');
check($store->createConnection(1,9,$issuer,$data)===1&&count($db->tables['t_bb_connections'])===1,'idempotent registration');
$changed=$issuer;$changed['tax_id']='12BBC34501DE35';rejects(fn()=>$store->createConnection(1,9,$changed,$data),'no reassignment on tax identity change');
$changed=$data;$changed['account_id']='001:00000-0:000000000000-0';rejects(fn()=>$store->createConnection(1,9,$issuer,$changed),'zero account rejected before persistence');
$changed=$data;$changed['client_secret']='synthetic-forbidden';rejects(fn()=>$store->createConnection(1,9,$issuer,$changed),'no credential intake');
rejects(fn()=>$store->createConnection(2,9,$issuer,$data),'issuer mismatch');rejects(fn()=>$store->createConnection(1,0,$issuer,$data),'actor required');
rejects(fn()=>$store->connection(1,2),'cross issuer read');check(count($store->connections(2))===0,'cross issuer list empty');
$c=$store->connection(1,1);$agreement=ModuleStore::agreement($c);$g2=new \BancoBrasil\Cnab240Generator();$l2=explode("\r\n",rtrim($g2->generate($agreement,1,'2026-10-18',[$title]),"\r\n"));$r2=returnBytes($agreement,$l2);
$stale=$c;$stale['variation']='020';$staleAgreement=ModuleStore::agreement($stale);
$staleLines=explode("\r\n",rtrim($g2->generate($staleAgreement,1,'2026-10-18',[$title]),"\r\n"));
rejects(fn()=>$store->importReturn($stale,9,returnBytes($staleAgreement,$staleLines)),'stale mutable profile rejected after lock');
check(!isset($db->tables['t_bb_return_files']),'stale profile creates no return records');
$import=$store->importReturn($c,9,$r2);check($import['financial_effect']==='not_applied'&&$import['events']===1,'observations only');
check(count($db->tables['t_bb_return_events'])===1&&count($db->tables['t_bb_return_files'])===1,'file and event durable');
check(!isset($db->tables['t_invoices'])&&!isset($db->tables['t_invoicepaymentrecords']),'no accounting side effect');
check(!str_contains(json_encode($db->tables),$r2),'raw return not stored');
$again=$store->importReturn($c,9,$r2);check($again['duplicate']===true&&count($db->tables['t_bb_return_files'])===1,'same file dedup');
$store->importReturn($c,9,mutateReturn($r2,0,158,'000002'));check(count($db->tables['t_bb_return_files'])===2&&count($db->tables['t_bb_return_events'])===1,'same event repackaged dedup');
check(count($db->tables['t_bb_return_file_events'])===2,'each file keeps provenance for deduplicated event');
check($db->tables['t_bb_return_file_events'][1]['return_event_id']===1&&$db->tables['t_bb_return_file_events'][1]['return_file_id']===2&&$db->tables['t_bb_return_file_events'][1]['lot_number']===1&&$db->tables['t_bb_return_file_events'][1]['record_sequence']===1,'file event and position retained');
$before=$db->tables;
foreach([108,123] as $position){rejects(fn()=>$store->importReturn($c,9,mutateReturn($r2,3,$position,'000000000000001')),'conflicting financial extras not ignored');check($db->tables===$before,'extras conflict rolls back');}
$db->failTable='t_bb_return_file_events';rejects(fn()=>$store->importReturn($c,9,mutateReturn($r2,0,158,'000007')),'provenance write required');check($db->tables===$before,'provenance failure rolls back');$db->failTable='';
$conflict=mutateReturn(mutateReturn($r2,0,158,'000003'),2,82,'000000000020001');
rejects(fn()=>$store->importReturn($c,9,$conflict),'same identity conflicting nominal amount');check($db->tables===$before,'whole conflicting import rolled back');
$db->failTable='t_bb_audit';rejects(fn()=>$store->importReturn($c,9,mutateReturn($r2,0,158,'000004')),'audit required');check($db->tables===$before,'audit failure rolls back file');$db->failTable='';
$db->failCommit=true;rejects(fn()=>$store->importReturn($c,9,mutateReturn($r2,0,158,'000005')),'commit failure no success');check($db->tables===$before,'commit failure rolled back');$db->failCommit=false;check($db->db_debug===true&&$db->save_queries===true,'caller query diagnostics restored after failure');
check(count($store->recentReturns(1))===2&&count($store->recentEvents(1))===1,'review queue visible');
check(!isset($store->recentEvents(1)[0]['observation_json']),'UI projection not raw payload');
foreach(['engine'=>'MyISAM','autocommit'=>'0','completion'=>'1','rawTransaction'=>true] as $key=>$value){$d=new BbDb();$d->$key=$value;rejects(fn()=>Transaction::run($d,'t_',['bb_audit'],fn()=>true),'own transaction guard '.$key);check($d->rollbacks===0,'no alien rollback '.$key);}
$d=new BbDb();$d->outer();rejects(fn()=>Transaction::run($d,'t_',['bb_audit'],fn()=>true),'CI nested transaction refused');check($d->rollbacks===0,'nested transaction untouched');
echo "OK {$n} Banco do Brasil store/transaction checks\n";
