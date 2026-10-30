<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyInvoiceIdentity.php';
require_once __DIR__.'/ClientIssuerScope.php';
require_once __DIR__.'/NativeCompanyResourceReferences.php';
require_once __DIR__.'/NativeCompanyInvoiceCommandSchema.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
/** Existing invoice sent/reminder metadata. Never sends mail or changes a financial document. */
final class NativeCompanyInvoiceCommunicationState
{
 public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
 /** Native form source capability only: no delivery/cron claim and no global SMS fallback. */
 public function reminderSources(int $i,int $a):array {
  $authorize=function()use($i,$a):void{$acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($i,$a,'invoices','view')||(!$acl->can($i,$a,'invoices','create')&&!$acl->can($i,$a,'invoices','edit')))throw new DomainException('native_invoice_communication_denied');};
  $authorize();require_once __DIR__.'/CompanyEmailTemplates.php';
  return IssuerTransaction::run($this->db,$this->p,['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','emailtemplates'],function()use($i,$a,$authorize):array{
   $authorize();$scope=CompanyEmailTemplates::scopeSql($this->db,$this->p,'t',$i);
   $row=$this->rows('SELECT COUNT(*) AS total FROM '.$this->p.'emailtemplates t WHERE t.slug=? AND t.active=1 AND '.$scope,['invoice-overdue-notice'])[0]??null;
   if(!$row||!isset($row['total'])||!preg_match('/^[0-9]+$/D',(string)$row['total']))throw new RuntimeException('native_invoice_communication_unconfirmed');
   $authorize();$email=(int)$row['total']>0;
   return ['enabled'=>$email,'email_template_active'=>$email,'sms'=>['supported'=>false,'reason'=>'issuer_sms_adapter_required'],'delivery_ready'=>false];
  });
 }
 public function tables():array{return ['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_clients','clients','invoices','issuers_audit',NativeCompanyInvoiceCommandSchema::TABLE];}
 private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
 private function capture(int $i,int $a,int $id,string $cap='view'):array{
  IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());
  (new NativeCompanyInvoiceIdentity($this->db,$this->p))->resolve($i,$a,$id,$cap);
  NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'invoice',$id,$cap);
  $row=$this->rows('SELECT id,issuer_id,clientid,status,sent,datesend,cancel_overdue_reminders FROM '.$this->p.'invoices WHERE id=? FOR UPDATE',[$id])[0]??null;
  if(!$row||!in_array((string)$row['sent'],['0','1'],true)||!in_array((string)$row['cancel_overdue_reminders'],['0','1'],true))throw new DomainException('native_invoice_communication_invalid');
  if($row['issuer_id']!==null){$owner=$this->rows('SELECT owner_issuer_id FROM '.$this->p.'issuers_clients WHERE client_id=?',[$row['clientid']])[0]??null;if(!$owner||(int)$owner['owner_issuer_id']!==$i)throw new DomainException('native_invoice_communication_denied');}
  $last=$this->rows("SELECT id FROM ".$this->p."issuers_audit WHERE issuer_id=? AND subject_id=? AND event='native_invoice_communication_updated' ORDER BY id DESC LIMIT 1",[$i,$id]);
  return ['invoice_id'=>$id,'sent'=>(int)$row['sent'],'datesend'=>$row['datesend'],'cancel_overdue_reminders'=>(int)$row['cancel_overdue_reminders'],'status'=>(int)$row['status'],'revision'=>hash('sha256',json_encode([$i,$row,$last],JSON_THROW_ON_ERROR))];
 }
 /** Create-only participant: the concrete invoice writer must prove this INSERT in this owned transaction. */
 public function setCreatedPreferenceInOwned(int $i,int $a,int $id,int $paused,CompanyInvoices $source):void {
  if(!in_array($paused,[0,1],true))throw new DomainException('native_invoice_communication_invalid');
  IssuerTransaction::assertOwned($this->db,$this->p,['invoices','issuers_audit','issuers_lock']);
  $source->assertCreatedInOwned($this->db,$this->p,$i,$a,'invoice',$id);
  if(!(new IssuerResourceAccess($this->db,$this->p))->can($i,$a,'invoices','create'))throw new DomainException('native_invoice_communication_denied');
  IssuerStorage::query($this->db,'UPDATE '.$this->p.'invoices SET cancel_overdue_reminders=? WHERE id=? AND issuer_id=?',[$paused,$id,$i]);
  $row=$this->rows('SELECT cancel_overdue_reminders FROM '.$this->p.'invoices WHERE id=? AND issuer_id=?',[$id,$i])[0]??null;
  if(!$row||(int)$row['cancel_overdue_reminders']!==$paused)throw new RuntimeException('issuer_native_invoice_communication_unconfirmed');
  $source->assertCreatedInOwned($this->db,$this->p,$i,$a,'invoice',$id);
 }
 public function state(int $i,int $a,int $id):array{
  NativeCompanyInvoiceCommandSchema::assertValid($this->db,$this->p);
  return IssuerTransaction::run($this->db,$this->p,$this->tables(),fn()=>$this->capture($i,$a,$id));
 }
 public function change(int $i,int $a,int $id,string $operation,string $revision,string $key):array{
  if(!in_array($operation,['pause_overdue_reminders','resume_overdue_reminders','mark_as_sent'],true)||!preg_match('/^[a-f0-9]{64}$/D',$revision)||!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('native_invoice_communication_invalid');
  NativeCompanyInvoiceCommandSchema::assertValid($this->db,$this->p);
  return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($i,$a,$id,$operation,$revision,$key){
   $before=$this->capture($i,$a,$id,'edit');$hash=hash('sha256',json_encode([$i,$a,$id,$operation,$revision],JSON_THROW_ON_ERROR));
   $old=$this->rows('SELECT * FROM '.$this->p.NativeCompanyInvoiceCommandSchema::TABLE.' WHERE request_key=? FOR UPDATE',[$key])[0]??null;
   if($old){if((int)$old['issuer_id']!==$i||(int)$old['actor_id']!==$a||$old['operation']!==$operation||!hash_equals($old['input_hash'],$hash)||!is_string($old['result_json'])||!is_string($old['result_hash'])||!hash_equals($old['result_hash'],hash('sha256',$old['result_json'])))throw new DomainException('native_invoice_communication_conflict');$audit=$this->rows("SELECT id,metadata_json FROM ".$this->p."issuers_audit WHERE id=? AND actor_id=? AND issuer_id=? AND subject_id=? AND event='native_invoice_communication_updated'",[(int)$old['audit_id'],$a,$i,$id])[0]??null;
    $meta=$audit?json_decode($audit['metadata_json'],true,512,JSON_THROW_ON_ERROR):null;if(!is_array($meta)||($meta['request_key']??null)!==$key||($meta['operation']??null)!==$operation||($meta['before_revision']??null)!==$revision)throw new DomainException('native_invoice_communication_conflict');
    return ['state'=>$before,'replayed'=>true];}
   if(!hash_equals($before['revision'],$revision))throw new DomainException('native_invoice_communication_stale');
   if($before['status']===6)throw new DomainException('native_invoice_draft_issue_required');
   if($operation==='mark_as_sent'){
    if($before['status']===5)throw new DomainException('native_invoice_communication_denied');
    if($before['sent']===0)IssuerStorage::query($this->db,'UPDATE '.$this->p.'invoices SET sent=1,datesend=? WHERE id=?',[date('Y-m-d H:i:s'),$id]);
   }else IssuerStorage::query($this->db,'UPDATE '.$this->p.'invoices SET cancel_overdue_reminders=? WHERE id=?',[$operation==='pause_overdue_reminders'?1:0,$id]);
   $verify=$this->capture($i,$a,$id,'edit');if(($operation==='mark_as_sent'&&$verify['sent']!==1)||($operation!=='mark_as_sent'&&$verify['cancel_overdue_reminders']!==($operation==='pause_overdue_reminders'?1:0)))throw new RuntimeException('issuer_native_invoice_communication_unconfirmed');
   $meta=json_encode(['operation'=>$operation,'request_key'=>$key,'before_revision'=>$revision,'write_nonce'=>bin2hex(random_bytes(16))],JSON_THROW_ON_ERROR);
   IssuerStorage::query($this->db,"INSERT INTO ".$this->p."issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,'native_invoice_communication_updated',?,?,?,?)",[$a,$i,$id,date('Y-m-d H:i:s'),$meta]);$audit=(int)$this->db->insert_id();
   if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND actor_id=? AND issuer_id=? AND subject_id=? AND metadata_json=?',[$audit,$a,$i,$id,$meta]))throw new RuntimeException('issuer_native_invoice_communication_unconfirmed');
   $after=$this->capture($i,$a,$id,'edit');$json=json_encode($after,JSON_THROW_ON_ERROR);$resultHash=hash('sha256',$json);
   IssuerStorage::query($this->db,'INSERT INTO '.$this->p.NativeCompanyInvoiceCommandSchema::TABLE.'(request_key,issuer_id,actor_id,operation,input_hash,result_json,result_hash,audit_id) VALUES(?,?,?,?,?,?,?,?)',[$key,$i,$a,$operation,$hash,$json,$resultHash,$audit]);
   $receipt=$this->rows('SELECT result_json,result_hash,audit_id FROM '.$this->p.NativeCompanyInvoiceCommandSchema::TABLE.' WHERE request_key=?',[$key])[0]??null;
   if(!$receipt||$receipt['result_json']!==$json||$receipt['result_hash']!==$resultHash||(int)$receipt['audit_id']!==$audit)throw new RuntimeException('issuer_native_invoice_communication_unconfirmed');
   $this->capture($i,$a,$id,'edit');return ['state'=>$after,'replayed'=>false];
  });
 }
}
