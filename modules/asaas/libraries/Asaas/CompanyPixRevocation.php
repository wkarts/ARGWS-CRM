<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyService.php';
/** Local withdrawal is durable before any remote cancellation. Never deletes financial history. */
final class CompanyPixRevocation
{
 private CompanyService $service;
 public function __construct(private $db,private string $p){$this->service=new CompanyService($db,$p);}
 private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
 private function run(callable $work){return \IssuerTransaction::run($this->db,$this->p,array_merge($this->service->tablesForRun(),['contacts','contact_permissions']),$work);}
 private static function hash(array $value):string{return hash('sha256',json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));}
 private function ready():void{$this->service->module();$v=$this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0';if(version_compare($v,'1.9.7','<'))throw new \DomainException('asaas_module_197_required');}
 private function proof(int $origin,string $operation):?array
 {
  $rows=$this->rows('SELECT * FROM '.$this->p.'asaas_company_proofs WHERE origin_id=? AND operation=? ORDER BY id DESC FOR UPDATE',[$origin,$operation]);if(count($rows)>1)throw new \DomainException('asaas_pix_revocation_conflict');if(!$rows)return null;
  $row=$rows[0];$value=json_decode($row['result_json'],true,32,JSON_THROW_ON_ERROR);if(!is_array($value)||self::hash($value)!==$row['evidence_hash'])throw new \DomainException('asaas_pix_revocation_conflict');return $row+['value'=>$value];
 }
 private function append(int $issuer,int $actor,int $origin,string $revision,string $operation,array $value,string $state):int
 {
  $json=json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$hash=self::hash($value);$now=gmdate('Y-m-d H:i:s');
  \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_proofs(origin_id,account_revision,actor_id,operation,result_json,evidence_hash,state,created_at) VALUES(?,?,?,?,?,?,?,?)',[$origin,$revision,$actor,$operation,$json,$hash,$state,$now]);$id=(int)$this->db->insert_id();
  \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,metadata_json,created_at) VALUES(?,?,?,?,?,?)',[$actor,'company_asaas_'.$operation,$issuer,$origin,json_encode(['proof_id'=>$id,'evidence_hash'=>$hash],JSON_THROW_ON_ERROR),$now]);
  if(($this->rows('SELECT evidence_hash FROM '.$this->p.'asaas_company_proofs WHERE id=? AND origin_id=?',[$id,$origin])[0]['evidence_hash']??null)!==$hash)throw new \RuntimeException('issuer_asaas_pix_revocation_unconfirmed');return $id;
 }
 /** Independent of old staff/account availability: a payer can withdraw after the invoice is paid. */
 private function portalSource(\CompanyPortal $portal,array $session,int $issuer,int $invoice):array
 {
  $this->ready();$portal->assertStorageBinding($this->db,$this->p);$who=$portal->invoiceIdentity($session,$issuer,true);
  if(!$who||!$this->rows('SELECT id FROM '.$this->p.'contacts WHERE id=? AND email_verified_at IS NOT NULL FOR UPDATE',[$who['contact_id']])||count($this->rows('SELECT id FROM '.$this->p.'contact_permissions WHERE userid=? AND permission_id=1 FOR UPDATE',[$who['contact_id']]))!==1)throw new \DomainException('asaas_pix_portal_denied');
  $rows=$this->rows('SELECT o.id,m.account_row FROM '.$this->p.'asaas_payment_origins o JOIN '.$this->p.'asaas_company_origin_meta m ON m.origin_id=o.id JOIN '.$this->p.'invoices i ON i.id=o.invoice_id JOIN '.$this->p.'issuers_clients c ON c.client_id=i.clientid WHERE i.id=? AND i.issuer_id=? AND i.clientid=? AND c.owner_issuer_id=? AND o.issuer_id=? AND o.channel=? AND o.active_slot=1 FOR UPDATE',[$invoice,$issuer,$who['client_id'],$issuer,$issuer,'pix_mandate']);
  if(count($rows)!==1)throw new \DomainException('asaas_pix_portal_denied');$origin=(int)$rows[0]['id'];$consent=$this->proof($origin,'pix_consent');$value=$consent['value']??null;
  if(!$value||($value['issuer_id']??null)!==$issuer||($value['invoice_id']??null)!==$invoice||($value['origin_id']??null)!==$origin||($value['client_id']??null)!==$who['client_id']||($value['contact_id']??null)!==$who['contact_id'])throw new \DomainException('asaas_pix_portal_denied');return [$origin,$consent,$who,(int)$rows[0]['account_row']];
 }
 public function page(\CompanyPortal $portal,array $session,int $issuer,int $invoice):array
 {
  return $this->run(function()use($portal,$session,$issuer,$invoice){[$origin,$consent]=$this->portalSource($portal,$session,$issuer,$invoice);return ['revision'=>$consent['evidence_hash'],'revoked'=>$this->proof($origin,'pix_revoke')!==null,'cancel_confirmed'=>$this->proof($origin,'pix_cancelled')!==null];});
 }
 public function withdraw(\CompanyPortal $portal,array $session,int $issuer,int $invoice,string $revision,bool $confirmed):void
 {
  if(!$confirmed)throw new \DomainException('asaas_pix_confirmation_required');$this->run(function()use($portal,$session,$issuer,$invoice,$revision){[$origin,$consent,$who,$account]=$this->portalSource($portal,$session,$issuer,$invoice);if(!hash_equals($consent['evidence_hash'],$revision))throw new \DomainException('asaas_pix_consent_stale');if($this->proof($origin,'pix_revoke'))return;
   $this->append($issuer,0,$origin,$consent['account_revision'],'pix_revoke',['issuer_id'=>$issuer,'origin_id'=>$origin,'account_row'=>$account,'contact_id'=>$who['contact_id'],'consent_hash'=>$revision,'reason'=>'payer_withdrawal'],'locally_revoked');
  });
 }
 private function staffSource(int $issuer,int $actor,int $origin):array
 {
  $this->ready();$this->service->authorize($issuer,$actor,'remote_remove');return $this->service->pixConsentSourceInOwnedTransaction($issuer,$actor,$origin);
 }
 /** Explicit staff cancellation; once claimed, an uncertain DELETE is only recovered with GET. */
 public function cancel(int $issuer,int $actor,int $origin,string $accountRevision,bool $confirmed,callable $decrypt,?callable $transport=null):array
 {
  if(!$confirmed)throw new \DomainException('asaas_pix_confirmation_required');
  $read=function()use($issuer,$actor,$origin,$accountRevision){$value=$this->staffSource($issuer,$actor,$origin);if(!hash_equals($value[2]['revision'],$accountRevision))throw new \DomainException('asaas_company_stale');return $value;};
  $this->run(function()use($read,$issuer,$actor,$origin){$source=$read();if(!$this->proof($origin,'pix_revoke'))$this->append($issuer,$actor,$origin,$source[2]['revision'],'pix_revoke',['issuer_id'=>$issuer,'origin_id'=>$origin,'account_row'=>(int)$source[2]['id'],'reason'=>'staff_cancellation'],'locally_revoked');});
  try{$observed=$this->service->recoverPixAuthorization($issuer,$actor,$origin,'',$decrypt,$transport);}catch(\DomainException $error){if(!in_array($error->getMessage(),['asaas_pix_recovery_not_found','asaas_pix_recovery_incomplete'],true))throw $error;return ['state'=>'locally_revoked_remote_unknown'];}
  $finish=function(array $safe)use($read,$issuer,$actor,$origin):array{return $this->run(function()use($read,$issuer,$actor,$origin,$safe){$source=$read();if($safe['status']==='CANCELLED'){if(!$this->proof($origin,'pix_cancelled'))$this->append($issuer,$actor,$origin,$source[2]['revision'],'pix_cancelled',['remote_id'=>$safe['remote_id'],'status'=>'CANCELLED'],'cancellation_observed');return ['state'=>'cancelled'];}return ['state'=>'locally_revoked_remote_'.$safe['status']];});};
  if($observed['status']!=='ACTIVE')return $finish($observed);
  [$source,$claimed]=$this->run(function()use($read,$issuer,$actor,$origin,$observed){$source=$read();$old=$this->proof($origin,'pix_cancel_send');if($old){if(($old['value']['remote_id']??null)!==$observed['remote_id']||$old['account_revision']!==$source[2]['revision'])throw new \DomainException('asaas_pix_revocation_conflict');return [$source,false];}
   $this->append($issuer,$actor,$origin,$source[2]['revision'],'pix_cancel_send',['remote_id'=>$observed['remote_id'],'account_row'=>(int)$source[2]['id'],'issuer_id'=>$issuer],'cancel_claimed');return [$source,true];});
  if(!$claimed)return ['state'=>'cancellation_unconfirmed'];
  try{$secret=$decrypt($source[2]['secret_cipher']);if(!is_string($secret))throw new \DomainException('asaas_company_credentials_required');$client=new CompanyClient($source[2]['environment'],$secret,$transport);$this->run($read);$client->cancelPixAuthorization($observed['remote_id']);}
  catch(\Throwable $error){return ['state'=>'cancellation_unconfirmed'];}
  // A DELETE response is not evidence of cancellation; re-read the exact account/contract.
  return $finish($this->service->recoverPixAuthorization($issuer,$actor,$origin,$observed['remote_id'],$decrypt,$transport));
 }
}
