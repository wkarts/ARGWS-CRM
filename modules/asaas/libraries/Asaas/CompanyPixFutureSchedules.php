<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyPixConsent.php';
require_once APPPATH.'services/CompanyInvoiceRecurring.php';
/** Reviewed link to a canonical schedule. This preparation alone never authorizes transport. */
final class CompanyPixFutureSchedules
{
 private CompanyService $service;
 private \CompanyInvoiceRecurring $schedules;
 public function __construct(private $db,private string $p){$this->service=new CompanyService($db,$p);$this->schedules=new \CompanyInvoiceRecurring($db,$p);}
 public function tables():array{return array_values(array_unique(array_merge($this->service->tablesForRun(),$this->schedules->tables(),['contacts','contact_permissions'])));}
 private function run(callable $work){return \IssuerTransaction::run($this->db,$this->p,$this->tables(),$work);}
 private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
 private static function hash(array $value):string{return hash('sha256',json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));}
 private function ready():void{$this->service->module();$v=$this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0';if(version_compare($v,'1.9.8','<'))throw new \DomainException('asaas_module_198_required');\CompanyInvoiceRecurringSchema::assertValid($this->db,$this->p);}
 private function proof(int $origin,string $operation):?array
 {
  $rows=$this->rows('SELECT * FROM '.$this->p.'asaas_company_proofs WHERE origin_id=? AND operation=? ORDER BY id DESC FOR UPDATE',[$origin,$operation]);if(!$rows)return null;
  if($operation!=='pix_recover'&&count($rows)!==1)throw new \DomainException('asaas_pix_schedule_conflict');$row=$rows[0];$value=json_decode($row['result_json'],true,32,JSON_THROW_ON_ERROR);
  if(!is_array($value)||self::hash($value)!==$row['evidence_hash'])throw new \DomainException('asaas_pix_schedule_conflict');return $row+['value'=>$value];
 }
 private function source(int $issuer,int $actor,int $origin,int $schedule,bool $needActive=true):array
 {
  $this->ready();$this->service->authorize($issuer,$actor,'configure');
  [$row,$meta,$account,$parent,$source]=$this->service->pixConsentSourceInOwnedTransaction($issuer,$actor,$origin);
  if($this->proof($origin,'pix_revoke'))throw new \DomainException('asaas_pix_consent_revoked');
  $consent=$this->proof($origin,'pix_consent');$terms=$consent['value']??[];
  if(($terms['version']??null)!==CompanyPixConsent::VERSION||($terms['issuer_id']??null)!==$issuer||($terms['origin_id']??null)!==$origin||($terms['invoice_id']??null)!==(int)$row['invoice_id']||($terms['client_id']??null)!==(int)$parent['clientid']||($terms['account_revision']??null)!==$account['revision']||($terms['source_hash']??null)!==self::hash($source))throw new \DomainException('asaas_pix_explicit_consent_required');
  $contact=$this->rows('SELECT c.id FROM '.$this->p.'contacts c JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid WHERE c.id=? AND c.userid=? AND o.owner_issuer_id=? AND c.active=1 AND c.email_verified_at IS NOT NULL AND (SELECT COUNT(*) FROM '.$this->p.'contact_permissions n WHERE n.userid=c.id AND n.permission_id=1)=1 FOR UPDATE',[$terms['contact_id'],$terms['client_id'],$issuer]);
  if(count($contact)!==1)throw new \DomainException('asaas_pix_consent_contact_unavailable');
  $remote=$this->proof($origin,'pix_recover');if(!$remote||$remote['account_revision']!==$account['revision']||($needActive&&($remote['value']['status']??null)!=='ACTIVE'))throw new \DomainException('asaas_pix_authorization_not_active');
  $initial=$this->rows('SELECT r.payment_id,p.invoiceid FROM '.$this->p.'asaas_company_receipts r JOIN '.$this->p.'invoicepaymentrecords p ON p.id=r.payment_id WHERE r.origin_id=? AND r.issuer_id=? FOR UPDATE',[$origin,$issuer]);
  if(count($initial)!==1||(int)$initial[0]['invoiceid']!==(int)$row['invoice_id'])throw new \DomainException('asaas_pix_initial_receipt_required');
  $document=$this->schedules->documentInOwnedTransaction($issuer,$actor,$schedule);$payload=$document['future_payload'];$config=$document['config'];
  if((int)$document['actor_id']!==$actor||!in_array($document['state'],['active','processing','completed'],true)||$document['mode']['mode']!=='issue'||(int)$payload['client_id']!==(int)$parent['clientid']||(int)$payload['currency']!==(int)$parent['currency'])throw new \DomainException('asaas_pix_schedule_denied');
  $frequency=match($config['unit'].':'.$config['interval']){'week:1'=>'WEEKLY','month:1'=>'MONTHLY','month:3'=>'QUARTERLY','month:6'=>'SEMIANNUALLY','month:12','year:1'=>'ANNUALLY',default=>null};
  if($frequency!==$source['frequency'])throw new \DomainException('asaas_pix_schedule_frequency_conflict');
  $amounts=\CompanyInvoiceLines::calculate($payload['items'],array_intersect_key($payload,array_flip(['discount_mode','discount_value','discount_type','adjustment','calculation_contract'])));
  if(\CompanyInvoiceAmounts::minor($amounts['total'])!==\CompanyInvoiceAmounts::minor($source['amount']))throw new \DomainException('asaas_pix_schedule_amount_review');
  return [$row,$account,$source,$consent,$remote,$document];
 }
 private function proposed(int $issuer,int $actor,int $origin,int $schedule,array $holidays):array
 {
  if(!array_is_list($holidays)||count($holidays)>100)throw new \DomainException('asaas_pix_calendar_invalid');
  foreach($holidays as $date){if(!is_string($date))throw new \DomainException('asaas_pix_calendar_invalid');\CompanyInvoiceRecurringDates::date($date);}sort($holidays,SORT_STRING);$holidays=array_values(array_unique($holidays));
  [$row,$account,$source,$consent,$remote,$document]=$this->source($issuer,$actor,$origin,$schedule);
  if($this->proof($origin,'pix_series_stop'))throw new \DomainException('asaas_pix_schedule_stopped');
  $old=$this->proof($origin,'pix_series');
  $cutoff=$old['value']['cutoff_invoice_id']??(int)($this->rows('SELECT MAX(id) n FROM '.$this->p.'invoices WHERE issuer_id=?',[$issuer])[0]['n']??0);
  return ['issuer_id'=>$issuer,'actor_id'=>$actor,'root_origin'=>$origin,'schedule_id'=>$schedule,'account_row'=>(int)$account['id'],'account_revision'=>$account['revision'],'authorization_id'=>$remote['value']['remote_id'],'consent_hash'=>$consent['evidence_hash'],'client_id'=>(int)$document['payload']['client_id'],'currency'=>(int)$document['payload']['currency'],'frequency'=>$source['frequency'],'amount'=>$source['amount'],'schedule_contract'=>self::hash([$document['payload'],$document['config'],$document['context'],$document['mode']]),'holidays'=>$holidays,'bank_timezone'=>'America/Sao_Paulo','cutoff_invoice_id'=>$cutoff];
 }
 public function review(int $issuer,int $actor,int $origin,int $schedule,array $holidays,callable $decrypt,?callable $transport=null):array
 {
  // Resolve local scope before any account lookup; refresh ACTIVE without changing consent.
  $this->run(fn()=>$this->source($issuer,$actor,$origin,$schedule,false));
  $this->service->recoverPixAuthorization($issuer,$actor,$origin,'',$decrypt,$transport);
  return $this->run(function()use($issuer,$actor,$origin,$schedule,$holidays){$value=$this->proposed($issuer,$actor,$origin,$schedule,$holidays);$hash=self::hash($value);\IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_proofs(origin_id,account_revision,actor_id,operation,result_json,evidence_hash,state,created_at) VALUES(?,?,?,?,?,?,?,?)',[$origin,$value['account_revision'],$actor,'pix_sreview',json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$hash,'reviewed',gmdate('Y-m-d H:i:s')]);return ['binding'=>$value,'revision'=>$hash];});
 }
 public function confirm(int $issuer,int $actor,int $origin,int $schedule,array $holidays,string $revision,bool $confirmed):int
 {
  if(!$confirmed)throw new \DomainException('asaas_company_confirmation_required');
  return $this->run(fn()=>$this->confirmInOwnedTransaction($issuer,$actor,$origin,$schedule,$holidays,$revision,true));
 }
 public function confirmInOwnedTransaction(int $issuer,int $actor,int $origin,int $schedule,array $holidays,string $revision,bool $confirmed):int
 {
  \IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());if(!$confirmed)throw new \DomainException('asaas_company_confirmation_required');$value=$this->proposed($issuer,$actor,$origin,$schedule,$holidays);$hash=self::hash($value);if(!hash_equals($hash,$revision))throw new \DomainException('asaas_pix_schedule_stale');$old=$this->proof($origin,'pix_series');if($old){if($old['evidence_hash']!==$hash)throw new \DomainException('asaas_pix_schedule_conflict');return (int)$old['id'];}
   $review=$this->rows('SELECT evidence_hash,created_at FROM '.$this->p.'asaas_company_proofs WHERE origin_id=? AND operation=? AND actor_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE',[$origin,'pix_sreview',$actor])[0]??null;if(!$review||$review['evidence_hash']!==$hash||$review['created_at']<gmdate('Y-m-d H:i:s',time()-300))throw new \DomainException('asaas_pix_schedule_stale');
   foreach($this->rows('SELECT p.origin_id FROM '.$this->p.'asaas_company_proofs p JOIN '.$this->p.'asaas_payment_origins o ON o.id=p.origin_id WHERE o.issuer_id=? AND p.operation=? FOR UPDATE',[$issuer,'pix_series']) as $candidate){$bound=$this->proof((int)$candidate['origin_id'],'pix_series');if(($bound['value']['schedule_id']??null)===$schedule&&!$this->proof((int)$candidate['origin_id'],'pix_series_stop'))throw new \DomainException('asaas_pix_schedule_already_bound');}
   $json=json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$now=gmdate('Y-m-d H:i:s');
   \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_proofs(origin_id,account_revision,actor_id,operation,result_json,evidence_hash,state,created_at) VALUES(?,?,?,?,?,?,?,?)',[$origin,$value['account_revision'],$actor,'pix_series',$json,$hash,'series_prepared',$now]);$id=(int)$this->db->insert_id();
   \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,metadata_json,created_at) VALUES(?,?,?,?,?,?)',[$actor,'company_asaas_pix_series_prepared',$issuer,$origin,json_encode(['proof_id'=>$id,'evidence_hash'=>$hash],JSON_THROW_ON_ERROR),$now]);if((int)($this->proof($origin,'pix_series')['id']??0)!==$id)throw new \RuntimeException('issuer_asaas_pix_series_unconfirmed');return $id;
 }
 public function status(int $issuer,int $actor,int $origin):?array
 {
  return $this->run(function()use($issuer,$actor,$origin){$this->ready();$this->service->authorize($issuer,$actor,'configuration');$this->service->authorize($issuer,$actor,'query');if(!$this->rows('SELECT id FROM '.$this->p.'asaas_payment_origins WHERE id=? AND issuer_id=? AND channel=?',[$origin,$issuer,'pix_mandate']))throw new \DomainException('asaas_pix_schedule_denied');$proof=$this->proof($origin,'pix_series');if(!$proof)return null;return ['binding'=>$proof['value'],'revision'=>$proof['evidence_hash'],'stopped'=>$this->proof($origin,'pix_series_stop')!==null];});
 }
 public function stop(int $issuer,int $actor,int $origin,string $revision,bool $confirmed):void
 {
  if(!$confirmed)throw new \DomainException('asaas_company_confirmation_required');$this->run(fn()=>$this->stopInOwnedTransaction($issuer,$actor,$origin,$revision,true));
 }
 public function stopInOwnedTransaction(int $issuer,int $actor,int $origin,string $revision,bool $confirmed):void
 {
  \IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());if(!$confirmed)throw new \DomainException('asaas_company_confirmation_required');$this->ready();$this->service->authorize($issuer,$actor,'configure');$this->service->authorize($issuer,$actor,'issue');if(class_exists('CompanySettingsRuntime',false)&&\CompanySettingsRuntime::active()&&(\CompanySettingsRuntime::issuerId()!==$issuer||\CompanySettingsRuntime::staffId()!==$actor))throw new \DomainException('asaas_company_access_denied');
   if(!$this->rows('SELECT id FROM '.$this->p.'asaas_payment_origins WHERE id=? AND issuer_id=? AND channel=?',[$origin,$issuer,'pix_mandate']))throw new \DomainException('asaas_pix_schedule_denied');$bound=$this->proof($origin,'pix_series');if(!$bound||!hash_equals($bound['evidence_hash'],$revision))throw new \DomainException('asaas_pix_schedule_stale');if($this->proof($origin,'pix_series_stop'))return;
   $value=['binding_hash'=>$revision,'issuer_id'=>$issuer,'root_origin'=>$origin];$hash=self::hash($value);$now=gmdate('Y-m-d H:i:s');\IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_proofs(origin_id,account_revision,actor_id,operation,result_json,evidence_hash,state,created_at) VALUES(?,?,?,?,?,?,?,?)',[$origin,$bound['account_revision'],$actor,'pix_series_stop',json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$hash,'stopped',$now]);
   \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,metadata_json,created_at) VALUES(?,?,?,?,?,?)',[$actor,'company_asaas_pix_series_stopped',$issuer,$origin,json_encode(['binding_hash'=>$revision],JSON_THROW_ON_ERROR),$now]);if(($this->proof($origin,'pix_series_stop')['evidence_hash']??null)!==$hash)throw new \RuntimeException('issuer_asaas_pix_series_unconfirmed');
 }
 /** Private composition for a cycle writer owning the complete native schedule boundary. */
 public function bindingInOwnedTransaction(int $issuer,int $actor,int $origin):array
 {
  \IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$this->ready();$bound=$this->proof($origin,'pix_series');if(!$bound)throw new \DomainException('asaas_pix_schedule_unprepared');
  $value=$bound['value'];$current=$this->proposed($issuer,$actor,$origin,(int)$value['schedule_id'],$value['holidays']);if(self::hash($current)!==$bound['evidence_hash'])throw new \DomainException('asaas_pix_schedule_stale');
  return [$bound,$this->source($issuer,$actor,$origin,(int)$value['schedule_id'])];
 }

}
