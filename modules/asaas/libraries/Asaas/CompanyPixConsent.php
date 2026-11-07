<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyService.php';
require_once APPPATH.'services/CompanyPortal.php';
/** Explicit private-portal consent. Neither remote ACTIVE nor a staff reservation is consent. */
final class CompanyPixConsent
{
 public const VERSION='2026-11-crm-pix-v1';
 private CompanyService $service;
 public function __construct(private $db,private string $p){$this->service=new CompanyService($db,$p);}
 private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
 private function run(callable $work){return \IssuerTransaction::run($this->db,$this->p,array_merge($this->service->tablesForRun(),['contacts','contact_permissions']),$work);}
 private static function hash(array $value):string{return hash('sha256',json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));}
 private function source(\CompanyPortal $portal,array $session,int $issuer,int $invoice):array
 {
  $portal->assertStorageBinding($this->db,$this->p);$who=$portal->invoiceIdentity($session,$issuer,true);
  if(!$who||!$this->rows('SELECT id FROM '.$this->p.'contacts WHERE id=? AND email_verified_at IS NOT NULL FOR UPDATE',[$who['contact_id']])||count($this->rows('SELECT id FROM '.$this->p.'contact_permissions WHERE userid=? AND permission_id=1 FOR UPDATE',[$who['contact_id']]))!==1)throw new \DomainException('asaas_pix_portal_denied');
  if(!$this->rows('SELECT i.id FROM '.$this->p.'invoices i JOIN '.$this->p.'issuers_clients o ON o.client_id=i.clientid WHERE i.id=? AND i.issuer_id=? AND i.clientid=? AND o.owner_issuer_id=? AND i.status IN (1,4) FOR UPDATE',[$invoice,$issuer,$who['client_id'],$issuer]))throw new \DomainException('asaas_pix_portal_denied');
  $row=$this->rows('SELECT o.id,m.created_by FROM '.$this->p.'asaas_payment_origins o JOIN '.$this->p.'asaas_company_origin_meta m ON m.origin_id=o.id WHERE o.invoice_id=? AND o.issuer_id=? AND o.channel=? AND o.active_slot=1 FOR UPDATE',[$invoice,$issuer,'pix_mandate'])[0]??null;
  if(!$row)throw new \DomainException('asaas_pix_portal_denied');
  [$origin,$meta,$account,$parent,$source]=$this->service->pixConsentSourceInOwnedTransaction($issuer,(int)$row['created_by'],(int)$row['id']);
  $terms=['version'=>self::VERSION,'issuer_id'=>$issuer,'invoice_id'=>$invoice,'origin_id'=>(int)$origin['id'],'contact_id'=>$who['contact_id'],'client_id'=>$who['client_id'],'account_revision'=>$account['revision'],'source_hash'=>self::hash($source),'amount'=>$source['amount'],'frequency'=>$source['frequency'],'start_date'=>$source['start_date'],'text'=>'Autorizo a adesão ao Pix Automático para esta fatura e cobranças futuras desta empresa na periodicidade indicada. A autorização bancária depende da confirmação no aplicativo do banco. Este aceite não comprova pagamento nem altera faturas anteriores.'];
  return [$terms,self::hash($terms),$account,$source,(int)$row['created_by'],$parent];
 }
 public function review(\CompanyPortal $portal,array $session,int $issuer,int $invoice):array
 {
  return $this->run(function()use($portal,$session,$issuer,$invoice){[$terms,$revision]=$this->source($portal,$session,$issuer,$invoice);return ['terms'=>$terms,'revision'=>$revision];});
 }
 public function accept(\CompanyPortal $portal,array $session,int $issuer,int $invoice,string $revision,$accepted):int
 {
  if($accepted!==true||!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new \DomainException('asaas_pix_explicit_consent_required');
  return $this->run(function()use($portal,$session,$issuer,$invoice,$revision){[$terms,$current,$account]=$this->source($portal,$session,$issuer,$invoice);if(!hash_equals($current,$revision))throw new \DomainException('asaas_pix_consent_stale');
   $old=$this->rows('SELECT id,result_json,evidence_hash FROM '.$this->p.'asaas_company_proofs WHERE origin_id=? AND operation=? FOR UPDATE',[$terms['origin_id'],'pix_consent']);
   if($old){if(count($old)!==1||$old[0]['evidence_hash']!==$current||self::hash(json_decode($old[0]['result_json'],true,32,JSON_THROW_ON_ERROR))!==$current)throw new \DomainException('asaas_pix_consent_conflict');return (int)$old[0]['id'];}
   $json=json_encode($terms,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$now=gmdate('Y-m-d H:i:s');
   \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_proofs(origin_id,account_revision,actor_id,operation,result_json,evidence_hash,state,created_at) VALUES(?,?,?,?,?,?,?,?)',[$terms['origin_id'],$account['revision'],0,'pix_consent',$json,$current,'consent_recorded',$now]);$id=(int)$this->db->insert_id();
   \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,metadata_json,created_at) VALUES(0,?,?,?,?,?)',['company_asaas_pix_portal_consent',$issuer,$terms['origin_id'],json_encode(['proof_id'=>$id,'evidence_hash'=>$current,'contact_id'=>$terms['contact_id']],JSON_THROW_ON_ERROR),$now]);
   if(($this->rows('SELECT evidence_hash FROM '.$this->p.'asaas_company_proofs WHERE id=? AND origin_id=?',[$id,$terms['origin_id']])[0]['evidence_hash']??null)!==$current)throw new \RuntimeException('issuer_asaas_pix_consent_unconfirmed');return $id;
  });
 }
 /** Claim once immediately before transport. A lost response requires GET recovery, never another claim. */
 public function claim(\CompanyPortal $portal,array $session,int $issuer,int $invoice,string $revision):array
 {
  return $this->run(function()use($portal,$session,$issuer,$invoice,$revision){
   [$terms,$current,$account]=$this->source($portal,$session,$issuer,$invoice);if(!hash_equals($current,$revision))throw new \DomainException('asaas_pix_consent_stale');
   $proofs=$this->rows('SELECT id,result_json,evidence_hash FROM '.$this->p.'asaas_company_proofs WHERE origin_id=? AND operation=? FOR UPDATE',[$terms['origin_id'],'pix_consent']);
   if(count($proofs)!==1||$proofs[0]['evidence_hash']!==$current||self::hash(json_decode($proofs[0]['result_json'],true,32,JSON_THROW_ON_ERROR))!==$current)throw new \DomainException('asaas_pix_explicit_consent_required');
   $recovered=$this->rows('SELECT id FROM '.$this->p.'asaas_company_proofs WHERE origin_id=? AND operation=? ORDER BY id DESC LIMIT 1 FOR UPDATE',[$terms['origin_id'],'pix_recover']);
   if($recovered)return ['claimed'=>false,'proof_id'=>(int)$recovered[0]['id'],'state'=>'authorization_observed'];
   $old=$this->rows('SELECT id FROM '.$this->p.'asaas_company_proofs WHERE origin_id=? AND operation=? FOR UPDATE',[$terms['origin_id'],'pix_send']);
   if($old){if(count($old)!==1)throw new \DomainException('asaas_pix_consent_conflict');return ['claimed'=>false,'proof_id'=>(int)$old[0]['id'],'state'=>'recovery_required'];}
   $row=$this->rows('SELECT state FROM '.$this->p.'asaas_payment_origins WHERE id=? AND issuer_id=? FOR UPDATE',[$terms['origin_id'],$issuer])[0]??null;if(($row['state']??null)!=='reserved')throw new \DomainException('asaas_pix_consent_conflict');
   $claim=['consent_proof_id'=>(int)$proofs[0]['id'],'consent_hash'=>$current,'contact_id'=>$terms['contact_id'],'source_hash'=>$terms['source_hash'],'issuer_id'=>$issuer,'account_row'=>(int)$account['id'],'account_revision'=>$account['revision']];$hash=self::hash($claim);$now=gmdate('Y-m-d H:i:s');
   \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_proofs(origin_id,account_revision,actor_id,operation,result_json,evidence_hash,state,created_at) VALUES(?,?,?,?,?,?,?,?)',[$terms['origin_id'],$account['revision'],0,'pix_send',json_encode($claim,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$hash,'send_claimed',$now]);$id=(int)$this->db->insert_id();
   \IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_payment_origins SET state=?,last_code=?,updated_at=? WHERE id=? AND issuer_id=?',['sending','pix_authorization_send_claimed',$now,$terms['origin_id'],$issuer]);
   \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,metadata_json,created_at) VALUES(0,?,?,?,?,?)',['company_asaas_pix_send_claimed',$issuer,$terms['origin_id'],json_encode(['proof_id'=>$id,'evidence_hash'=>$hash],JSON_THROW_ON_ERROR),$now]);
   $saved=$this->rows('SELECT evidence_hash FROM '.$this->p.'asaas_company_proofs WHERE id=? AND origin_id=?',[$id,$terms['origin_id']])[0]??null;$state=$this->rows('SELECT state FROM '.$this->p.'asaas_payment_origins WHERE id=? AND issuer_id=?',[$terms['origin_id'],$issuer])[0]['state']??null;
   if(($saved['evidence_hash']??null)!==$hash||$state!=='sending')throw new \RuntimeException('issuer_asaas_pix_claim_unconfirmed');
   return ['claimed'=>true,'proof_id'=>$id,'state'=>'sending'];
  });
 }

 /** Server-side enrollment transport; no QR or financial posting is released by this method. */
 public function dispatch(\CompanyPortal $portal,array $session,int $issuer,int $invoice,string $revision,callable $decrypt,?callable $transport=null):array
 {
  $read=function()use($portal,$session,$issuer,$invoice,$revision){$value=$this->source($portal,$session,$issuer,$invoice);if(!hash_equals($revision,$value[1]))throw new \DomainException('asaas_pix_consent_stale');return $value;};
  [$terms,,$account,$source,$actor,$parent]=$this->run($read);try{$secret=$decrypt($account['secret_cipher']);}catch(\Throwable $error){throw new \DomainException('asaas_company_credentials_required');}if(!is_string($secret))throw new \DomainException('asaas_company_credentials_required');$client=new CompanyClient($account['environment'],$secret,$transport);
  $wallet=$client->request('GET','wallets/');if(!is_array($wallet['data']??null)||count($wallet['data'])!==1||($wallet['data'][0]['id']??null)!==$account['account_id']||($wallet['hasMore']??null)!==false)throw new \DomainException('asaas_company_account_unverified');$this->run($read);
  $customer=$client->request('GET','customers/'.$source['customer_id']);$recipient=json_decode($parent['recipient_identity_json'],true,8,JSON_THROW_ON_ERROR);if(($customer['id']??null)!==$source['customer_id']||($customer['deleted']??false)!==false||\IssuerPolicy::taxId($customer['cpfCnpj']??null)!==\IssuerPolicy::taxId($recipient['tax_id']??null))throw new \DomainException('asaas_company_customer_unverified');$this->run($read);
  $claim=$this->claim($portal,$session,$issuer,$invoice,$revision);if(!$claim['claimed'])return ['state'=>$claim['state'],'proof_id'=>$claim['proof_id']];
  try{$remote=CompanyPixAuthorizationContract::response($source,$client->createPixAuthorization($source));unset($remote['qr']);$result=['state'=>'authorization_response','authorization'=>$remote];}
  catch(\Throwable $error){$result=['state'=>'uncertain','authorization'=>null];}
  // Persist the outcome of an already authorized send even if identity is revoked in flight.
  $this->run(function()use($terms,$issuer,$account,$claim,$result,$revision){
   $proof=$this->rows('SELECT * FROM '.$this->p.'asaas_company_proofs WHERE id=? AND origin_id=? AND operation=? FOR UPDATE',[$claim['proof_id'],$terms['origin_id'],'pix_send'])[0]??null;
   $saved=$proof?json_decode($proof['result_json'],true,32,JSON_THROW_ON_ERROR):null;
   if(!$proof||!is_array($saved)||self::hash($saved)!==$proof['evidence_hash']||($saved['consent_hash']??null)!==$revision||($saved['source_hash']??null)!==$terms['source_hash']||($saved['issuer_id']??null)!==$issuer||($saved['account_row']??null)!==(int)$account['id']||($saved['account_revision']??null)!==$account['revision'])throw new \DomainException('asaas_pix_send_intent_conflict');
   $row=$this->rows('SELECT state FROM '.$this->p.'asaas_payment_origins WHERE id=? AND issuer_id=? AND channel=? FOR UPDATE',[$terms['origin_id'],$issuer,'pix_mandate'])[0]??null;if(($row['state']??null)!=='sending')throw new \DomainException('asaas_pix_send_intent_conflict');
   $json=json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$hash=self::hash($result);$now=gmdate('Y-m-d H:i:s');
   \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_proofs(origin_id,account_revision,actor_id,operation,result_json,evidence_hash,state,created_at) VALUES(?,?,?,?,?,?,?,?)',[$terms['origin_id'],$account['revision'],0,'pix_result',$json,$hash,$result['state'],$now]);$id=(int)$this->db->insert_id();
   \IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_payment_origins SET state=?,last_code=?,updated_at=? WHERE id=? AND issuer_id=?',[$result['state']==='uncertain'?'uncertain':'sending',$result['state']==='uncertain'?'pix_post_unconfirmed':'pix_response_observed',$now,$terms['origin_id'],$issuer]);
   \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,metadata_json,created_at) VALUES(0,?,?,?,?,?)',['company_asaas_pix_send_observed',$issuer,$terms['origin_id'],json_encode(['proof_id'=>$id,'evidence_hash'=>$hash],JSON_THROW_ON_ERROR),$now]);
   if(($this->rows('SELECT evidence_hash FROM '.$this->p.'asaas_company_proofs WHERE id=? AND origin_id=?',[$id,$terms['origin_id']])[0]['evidence_hash']??null)!==$hash)throw new \RuntimeException('issuer_asaas_pix_result_unconfirmed');
  });
  $this->run($read);
  if($result['state']==='uncertain')return ['state'=>'uncertain'];
  $observed=$this->service->recoverPixAuthorization($issuer,$actor,$terms['origin_id'],$result['authorization']['remote_id'],$decrypt,$transport);$this->run($read);
  return ['state'=>'authorization_observed','authorization'=>$observed];
 }

}
