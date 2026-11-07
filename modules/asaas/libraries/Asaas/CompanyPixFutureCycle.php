<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyPixFutureSchedules.php';
require_once __DIR__.'/CompanyPixBankCalendar.php';
/** Closed composition: exact canonical occurrence, reviewed mandate and one durable origin per cycle. */
final class CompanyPixFutureCycle
{
 private CompanyService $service;
 private CompanyPixFutureSchedules $bindings;
 private ?array $frozen=null;
 private bool $remoteChecked=false;
 public function __construct(private $db,private string $p,private int $issuer,private int $actor,private int $root,private int $invoice,private $clock=null){$this->service=new CompanyService($db,$p);$this->bindings=new CompanyPixFutureSchedules($db,$p);}
 public function tables():array{return $this->bindings->tables();}
 private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
 private static function hash(array $value):string{return hash('sha256',json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));}
 private function run(callable $work){return \IssuerTransaction::run($this->db,$this->p,$this->tables(),$work);}
 private function current():array
 {
  \IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());[$proof,$context]=$this->bindings->bindingInOwnedTransaction($this->issuer,$this->actor,$this->root);[$root,$account,$source,,,$document]=$context;$binding=$proof['value'];
  $run=array_values(array_filter($document['runs'],fn($run)=>$run['state']==='completed'&&(int)$run['canonical_invoice_id']===$this->invoice));
  if(count($run)!==1||$this->invoice<=$binding['cutoff_invoice_id']||$this->invoice===(int)$root['invoice_id'])throw new \DomainException('asaas_pix_cycle_not_future');
  $invoice=$this->rows('SELECT i.id,i.hash,i.clientid,i.currency,i.duedate,i.total FROM '.$this->p.'invoices i JOIN '.$this->p.'issuers_invoice_documents d ON d.invoice_id=i.id AND d.issuer_id=i.issuer_id WHERE i.id=? AND i.issuer_id=? FOR UPDATE',[$this->invoice,$this->issuer])[0]??null;
  if(!$invoice||(int)$invoice['clientid']!==$binding['client_id']||(int)$invoice['currency']!==$binding['currency']||\CompanyInvoiceAmounts::minor($invoice['total'])!==\CompanyInvoiceAmounts::minor($binding['amount']))throw new \DomainException('asaas_pix_cycle_invoice_conflict');
  $calendar=new CompanyPixBankCalendar($binding['holidays']);$today=$this->clock?($this->clock)():(new \DateTimeImmutable('now',new \DateTimeZone($binding['bank_timezone'])))->format('Y-m-d');$days=$calendar->businessDays($today,$invoice['duedate']);
  if($days<2||$days>10)throw new \DomainException('asaas_pix_cycle_outside_window');
  $cycle=$calendar->cycle($invoice['duedate'],$binding['frequency'],$source['start_date']);if(in_array($cycle,['w0','m0'],true))throw new \DomainException('asaas_pix_cycle_initial_period');
  if($this->db->table_exists($this->p.'asaas_company_pix_events')&&$this->rows('SELECT id FROM '.$this->p.'asaas_company_pix_events WHERE issuer_id=? AND account_row=? AND (remote_id=? OR authorization_id=?) AND (state=? OR remote_status IN (?,?,?)) LIMIT 1 FOR UPDATE',[$this->issuer,$account['id'],$binding['authorization_id'],$binding['authorization_id'],'conflict','CANCELLED','EXPIRED','REFUSED']))throw new \DomainException('asaas_pix_cycle_adverse_event');
  $value=['issuer_id'=>$this->issuer,'actor_id'=>$this->actor,'root_origin'=>$this->root,'invoice_id'=>$this->invoice,'invoice_hash'=>$invoice['hash'],'account_row'=>(int)$account['id'],'account_revision'=>$account['revision'],'binding_hash'=>$proof['evidence_hash'],'binding_proof_id'=>(int)$proof['id'],'authorization_id'=>$binding['authorization_id'],'customer_id'=>$source['customer_id'],'cycle'=>$cycle,'run_date'=>$run[0]['run_date'],'due_date'=>$invoice['duedate'],'amount'=>$binding['amount'],'invoice_revision'=>(new \CompanyInvoices($this->db,$this->p))->revision($this->issuer,$this->actor,$this->invoice)];
  $value['request_key']=self::hash([$this->issuer,$this->root,$proof['evidence_hash'],$cycle,$this->invoice]);return $value;
 }
 public function definition():array{$this->frozen=$this->run(fn()=>$this->current());return $this->frozen;}
 public function dispatchDefinition():array{if(!$this->remoteChecked||$this->frozen===null)throw new \DomainException('asaas_pix_cycle_refresh_required');return $this->frozen;}
 public function assertCurrent():void{if($this->frozen===null||$this->current()!==$this->frozen)throw new \DomainException('asaas_pix_cycle_stale');}
 public function capture(int $origin):void
 {
  $this->assertCurrent();$value=$this->frozen+['origin_id'=>$origin];$hash=self::hash($value);
  foreach($this->rows('SELECT result_json,evidence_hash FROM '.$this->p.'asaas_company_proofs WHERE origin_id=? AND operation=? FOR UPDATE',[$this->root,'pix_cycle']) as $row){$old=json_decode($row['result_json'],true,32,JSON_THROW_ON_ERROR);if(!is_array($old)||self::hash($old)!==$row['evidence_hash'])throw new \DomainException('asaas_pix_cycle_conflict');if($old['cycle']===$value['cycle']){if($old!==$value)throw new \DomainException('asaas_pix_cycle_already_reserved');return;}}
  foreach([[$this->root,'pix_cycle'],[$origin,'pix_future']] as [$id,$operation])\IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_proofs(origin_id,account_revision,actor_id,operation,result_json,evidence_hash,state,created_at) VALUES(?,?,?,?,?,?,?,?)',[$id,$value['account_revision'],$this->actor,$operation,json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$hash,'cycle_reserved',gmdate('Y-m-d H:i:s')]);
  self::metadata($this->db,$this->p,['id'=>$origin,'issuer_id'=>$this->issuer,'invoice_id'=>$this->invoice],['account_row'=>$value['account_row']]);
 }
 /** Historical identity survives withdrawal; it proves a prior charge, not authority to send another. */
 public static function metadata($db,string $p,array $origin,array $meta):array
 {
  $rows=\IssuerStorage::query($db,'SELECT * FROM '.$p.'asaas_company_proofs WHERE origin_id=? AND operation=? FOR UPDATE',[$origin['id'],'pix_future'])->result_array();if(!$rows)return $meta;if(count($rows)!==1)throw new \DomainException('asaas_pix_cycle_conflict');$proof=$rows[0];$value=json_decode($proof['result_json'],true,32,JSON_THROW_ON_ERROR);
  if(!is_array($value)||self::hash($value)!==$proof['evidence_hash']||$value['origin_id']!==(int)$origin['id']||$value['issuer_id']!==(int)$origin['issuer_id']||$value['invoice_id']!==(int)$origin['invoice_id']||$value['account_row']!==(int)$meta['account_row'])throw new \DomainException('asaas_pix_cycle_conflict');
  $binding=\IssuerStorage::query($db,'SELECT evidence_hash,result_json FROM '.$p.'asaas_company_proofs WHERE id=? AND origin_id=? AND operation=?',[$value['binding_proof_id'],$value['root_origin'],'pix_series'])->row_array();$bound=$binding?json_decode($binding['result_json'],true,32,JSON_THROW_ON_ERROR):null;
  if(!$bound||self::hash($bound)!==$value['binding_hash']||$binding['evidence_hash']!==$value['binding_hash']||$bound['authorization_id']!==$value['authorization_id'])throw new \DomainException('asaas_pix_cycle_conflict');
  $peer=\IssuerStorage::query($db,'SELECT evidence_hash,result_json FROM '.$p.'asaas_company_proofs WHERE origin_id=? AND operation=? AND evidence_hash=?',[$value['root_origin'],'pix_cycle',$proof['evidence_hash']])->result_array();if(count($peer)!==1||$peer[0]['result_json']!==$proof['result_json'])throw new \DomainException('asaas_pix_cycle_conflict');
  return $meta+['pix_authorization_id'=>$value['authorization_id'],'pix_cycle_reference'=>$value['request_key'],'pix_root_origin'=>$value['root_origin']];
 }
 private function historicalSource():array
 {
  \IssuerTransaction::assertOwned($this->db,$this->p,$this->service->tablesForRun());
  if(class_exists('CompanySettingsRuntime',false)&&\CompanySettingsRuntime::active()&&(\CompanySettingsRuntime::issuerId()!==$this->issuer||\CompanySettingsRuntime::staffId()!==$this->actor))throw new \DomainException('asaas_company_access_denied');
  $origin=$this->rows('SELECT id FROM '.$this->p.'asaas_payment_origins WHERE invoice_id=? AND issuer_id=? AND channel=? AND active_slot=1 FOR UPDATE',[$this->invoice,$this->issuer,'api'])[0]??null;
  if(!$origin)throw new \DomainException('asaas_pix_cycle_not_sent');$source=$this->service->manualSourceInOwnedTransaction($this->issuer,$this->actor,(int)$origin['id']);
  if(($source[1]['pix_root_origin']??null)!==$this->root)throw new \DomainException('asaas_pix_cycle_conflict');return $source;
 }
 public function observe(callable $decrypt,?callable $transport=null):int
 {
  $read=fn()=>$this->historicalSource();$transaction=fn($work)=>\IssuerTransaction::run($this->db,$this->p,$this->service->tablesForRun(),$work);[$origin,$meta,$account,$invoice]=$transaction($read);
  $remote=$origin['remote_id'];
  if($remote===null){$secret=$decrypt($account['secret_cipher']);if(!is_string($secret))throw new \DomainException('asaas_company_credentials_required');$client=new CompanyClient($account['environment'],$secret,$transport);$wallet=$client->request('GET','wallets/');$transaction($read);
   if(!is_array($wallet['data']??null)||count($wallet['data'])!==1||($wallet['hasMore']??null)!==false||($wallet['data'][0]['id']??null)!==$account['account_id'])throw new \DomainException('asaas_company_account_unverified');
   $list=$client->request('GET','payments?externalReference='.$invoice['hash'].'&limit=100&offset=0');$transaction($read);
   if(!is_array($list['data']??null)||!array_is_list($list['data'])||count($list['data'])!==1||($list['hasMore']??null)!==false||($list['data'][0]['externalReference']??null)!==$invoice['hash']||!is_string($list['data'][0]['id']??null))throw new \DomainException('asaas_pix_cycle_recovery_required');$remote=$list['data'][0]['id'];
  }
  return $this->service->observe($this->issuer,$this->actor,(int)$origin['id'],$decrypt,$transport,$remote,$read,$this->service->tablesForRun());
 }
 public function settle(int $proof):int
 {
  require_once __DIR__.'/CompanyReceivedReceipt.php';$read=fn()=>$this->historicalSource();[$origin,$meta]=\IssuerTransaction::run($this->db,$this->p,$this->service->tablesForRun(),$read);
  return (new \app\services\InvoicePaymentWriter($this->db))->recordCompanyProviderReceipt($this->issuer,$this->actor,CompanyReceivedReceipt::explicit((int)$origin['id'],$proof,$meta['pix_cycle_reference'],$read,$this->service->tablesForRun()));
 }
 public function dispatch(callable $decrypt,?callable $transport=null):int
 {
  $this->definition();$this->service->recoverPixAuthorization($this->issuer,$this->actor,$this->root,'',$decrypt,$transport);$this->run(fn()=>$this->assertCurrent());
  $this->remoteChecked=true;return $this->service->issuePixFutureCycle($this,$decrypt,$transport);
 }
}
