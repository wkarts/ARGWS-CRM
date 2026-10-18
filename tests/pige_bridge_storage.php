<?php
// Adapter-level tests execute the production model, including atomic insert and duplicate comparison.
define('BASEPATH', __DIR__);
function db_prefix() { return 'test_'; }
class App_Model { public $db; }
require_once __DIR__.'/../modules/pige_bridge/models/Pige_bridge_model.php';
require_once __DIR__.'/../modules/pige_bridge/libraries/PigeBridgeProtocol.php';
class ReceiptDb
{
 public $rows=[]; public $pending; public $fail=false; public $queries=[]; private $filters=[];
 public function insert_string($table,$row) { $this->pending=$row; return 'INSERT INTO '.$table.' (...) VALUES (...)'; }
 public function query($sql) {
  $this->queries[]=$sql;
  if ($this->fail) return false;
  if (substr($sql,-29)!=='ON DUPLICATE KEY UPDATE id=id') {
   if (strpos($sql,'ON DUPLICATE KEY UPDATE id=id')===false) throw new RuntimeException('missing atomic dedupe');
  }
  $key=$this->pending['connection_key'].'/'.$this->pending['event_id'];
  foreach ($this->rows as $existing) {
   if ($existing['scope_sha256']===$this->pending['scope_sha256'] && $existing['charge_id']===$this->pending['charge_id']) return true;
  }
  if (!isset($this->rows[$key])) $this->rows[$key]=$this->pending;
  return true;
 }
 public function where($key,$value) { $this->filters[$key]=$value; return $this; }
 public function get($table) { return $this; }
 public function row_array() { $filters=$this->filters; $this->filters=[]; foreach($this->rows as $row) { $matches=true; foreach($filters as $key=>$value) if($row[$key]!==$value)$matches=false; if($matches)return $row; } return []; }
}
$checks=0;
function check($yes,$message) { global $checks; $checks++; if (!$yes) throw new RuntimeException($message); }
$event=['event_id'=>'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','scope'=>['school_id'=>'11111111-1111-4111-8111-111111111111','issuer_id'=>'7','account_id'=>'account','environment'=>'sandbox'],'data'=>['charge_id'=>'22222222-2222-4222-8222-222222222222','amount_cents'=>100,'due_date'=>'2028-02-29','description'=>'not stored']];
$model=new Pige_bridge_model(); $model->db=new ReceiptDb();
$body=json_encode($event);
$first=$model->accept_request('school1',$event,$body);
check(strlen($first['request_id'])===36,'opaque request id');
check(!isset($first['description']) && !isset($first['secret']) && !isset($first['payload']),'no PII description/credential/raw payload persistence');
for ($i=0;$i<25;$i++) check($model->accept_request('school1',$event,$body)===$first,'stable duplicate receipt');
check(count($model->db->rows)===1,'one row after retries');
$conflict=false;
try {$model->accept_request('school1',$event,$body.' ');} catch(DomainException $e) {$conflict=$e->getMessage()==='idempotency_conflict';}
check($conflict,'different body with same id conflicts');
check($model->db->rows['school1/'.$event['event_id']]===$first,'conflict cannot rewrite stored origin');
$cross=false;try {$model->accept_request('school2',$event,$body);}catch(DomainException $e){$cross=true;}check($cross,'connection rename cannot duplicate same scoped charge');
$event['scope']['school_id']='33333333-3333-4333-8333-333333333333';$body=json_encode($event);
$other=$model->accept_request('school2',$event,$body);
check($other['request_id']!==$first['request_id'],'connection scope isolation');
check(count($model->db->rows)===2,'independent connections');
$otherEvent=$event;$otherEvent['event_id']='bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';$conflict=false;try{$model->accept_request('school2',$otherEvent,json_encode($otherEvent));}catch(DomainException $e){$conflict=true;}check($conflict,'new event cannot duplicate scoped charge');
$model->db->fail=true;
$failed=false; try {$model->accept_request('school1',$event,$body);} catch(RuntimeException $e) {$failed=true;}
check($failed,'DB failure is not a success receipt');
echo "PIGE bridge storage: {$checks} checks passed (in-memory DB adapter, not MySQL)\n";
