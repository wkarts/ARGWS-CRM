<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyInvoiceDraftProjectionSchema.php';
require_once __DIR__.'/CompanyInvoiceDraftsInput.php';
require_once __DIR__.'/CompanyInvoiceLines.php';
/** Rebuildable derived data. Original private document and its audit receipt remain authoritative. */
final class NativeCompanyInvoiceDraftProjection
{
 public function __construct(private $db,private string $p){NativeCompanyInvoiceDraftProjectionSchema::definitions($p);}
 public static function tables():array{return ['company_invoice_draft_projection','company_invoice_drafts','company_invoice_draft_events','issuers_audit','company_invoice_requests','invoices','issuers_invoice_documents'];}
 private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
 private static function fail():never{throw new RuntimeException('issuer_invoice_draft_projection_unconfirmed');}
 private function source(int$id):array
 {
  IssuerTransaction::assertOwned($this->db,$this->p,self::tables());
  $row=$this->rows('SELECT * FROM '.$this->p.'company_invoice_drafts WHERE id=? FOR UPDATE',[$id])[0]??null;if(!$row)self::fail();
  foreach(['id','issuer_id','created_by','revision','client_id','currency_id','invoice_id','issue_actor','issue_revision']as$k)$row[$k]=$row[$k]===null?null:(int)$row[$k];
  $event=$this->rows('SELECT * FROM '.$this->p.'company_invoice_draft_events WHERE request_key=? AND issuer_id=? AND draft_id=? FOR UPDATE',[$row['last_event_key'],$row['issuer_id'],$id])[0]??null;
  if(!$event||!hash_equals(hash('sha256',$event['data_json']),$event['data_hash'])||CompanyInvoiceDraftsInput::decode($event['data_json'])!==['header'=>$row])self::fail();
  $metadata=CompanyInvoiceDraftsInput::json(['request_key'=>$event['request_key'],'operation'=>$event['operation'],'input_hash'=>$event['input_hash'],'data_hash'=>$event['data_hash']]);
  $audit=$this->rows('SELECT issuer_id,actor_id,subject_id,event,metadata_json FROM '.$this->p.'issuers_audit WHERE id=? FOR UPDATE',[$event['audit_id']])[0]??[];
  if((int)($audit['issuer_id']??0)!==$row['issuer_id']||(int)($audit['actor_id']??0)!==(int)$event['actor_id']||(int)($audit['subject_id']??0)!==$id||($audit['event']??null)!=='company_invoice_draft_'.$event['operation']||($audit['metadata_json']??null)!==$metadata)self::fail();
  if(($row['source_json']===null)!==($row['source_hash']===null)||($row['source_json']!==null&&!hash_equals(hash('sha256',$row['source_json']),$row['source_hash'])))self::fail();
  if($row['state']==='issued'){
   if($row['invoice_id']===null||$row['issue_actor']===null||$row['issue_revision']===null||$row['issue_key']===null||$row['issue_json']===null||$row['issue_hash']===null||!hash_equals(hash('sha256',$row['issue_json']),$row['issue_hash']))self::fail();
   $issue=CompanyInvoiceDraftsInput::decode($row['issue_json']);$receipt=$this->rows('SELECT issuer_id,actor_id,invoice_id,input_hash FROM '.$this->p.'company_invoice_requests WHERE request_key=? FOR UPDATE',[hash('sha256','company-invoice-draft-issued:'.$row['issuer_id'].':'.$id)])[0]??[];
   if((int)($receipt['issuer_id']??0)!==$row['issuer_id']||(int)($receipt['actor_id']??0)!==$row['issue_actor']||(int)($receipt['invoice_id']??0)!==$row['invoice_id']||($issue['invoice_id']??null)!==$row['invoice_id']||($issue['draft_id']??null)!==$id||($issue['payload_hash']??null)!==$row['payload_hash']||($receipt['input_hash']??null)!==CompanyInvoiceCopiesInput::hash(CompanyInvoiceCopiesInput::canonical(CompanyInvoiceDraftsInput::decode($row['payload_json']))))self::fail();
   $document=$this->rows('SELECT d.formatted_number FROM '.$this->p.'invoices i JOIN '.$this->p.'issuers_invoice_documents d ON d.invoice_id=i.id AND d.issuer_id=i.issuer_id WHERE i.id=? AND i.issuer_id=?',[$row['invoice_id'],$row['issuer_id']])[0]??null;if(!$document||($issue['number']??null)!==$document['formatted_number'])self::fail();
  }elseif($row['invoice_id']!==null||$row['issue_actor']!==null||$row['issue_revision']!==null||$row['issue_key']!==null||$row['issue_json']!==null||$row['issue_hash']!==null)self::fail();
  return$row;
 }
 private static function derive(array$r):array
 {
  if($r['revision']<1||!in_array($r['state'],['open','cancelled','issued'],true)||!hash_equals(hash('sha256',$r['payload_json']),$r['payload_hash'])||!hash_equals(hash('sha256',$r['context_json']),$r['context_hash']))self::fail();
  $payload=CompanyInvoiceDraftsInput::decode($r['payload_json']);$context=CompanyInvoiceDraftsInput::decode($r['context_json']);
  if(CompanyInvoiceDraftsInput::normalize($payload)!==$payload||$payload['client_id']!==$r['client_id']||$payload['currency']!==$r['currency_id']||($context['issuer_id']??null)!==$r['issuer_id']||($context['recipient']['client_id']??null)!==$r['client_id']||!is_string($context['recipient']['name']??null))self::fail();
  $money=CompanyInvoiceLines::calculate($payload['items'],array_intersect_key($payload,array_flip(['discount_mode','discount_value','discount_type','adjustment'])));
  return ['id'=>$r['id'],'issuer_id'=>$r['issuer_id'],'revision'=>$r['revision'],'source_hash'=>hash('sha256',CompanyInvoiceDraftsInput::json($r)),'state'=>$r['state'],'clientid'=>$r['client_id'],'sale_agent'=>$payload['native_fields']['sale_agent']??0,'project_id'=>$payload['native_project']['source_id']??0,'currency'=>$r['currency_id'],'date'=>$payload['date'],'duedate'=>$payload['due_date'],'customer'=>$context['recipient']['name'],'subtotal'=>$money['subtotal'],'total'=>$money['total'],'total_tax'=>$money['tax'],'discount_total'=>$money['discount_total'],'adjustment'=>$money['adjustment']];
 }
 private static function normalized(array$r):array
 {
  foreach(['id','issuer_id','revision','clientid','sale_agent','project_id','currency']as$key)$r[$key]=(int)$r[$key];
  foreach(['subtotal','total','total_tax','discount_total','adjustment']as$key)$r[$key]=CompanyInvoiceAmounts::decimal(CompanyInvoiceAmounts::minor((string)$r[$key],true));return$r;
 }
 public function syncInOwned(int$id):void
 {
  NativeCompanyInvoiceDraftProjectionSchema::assertValid($this->db,$this->p);
  $source=$this->source($id);$derived=self::derive($source);$table=$this->p.NativeCompanyInvoiceDraftProjectionSchema::TABLE;
  $existing=$this->rows('SELECT * FROM '.$table.' WHERE id=? FOR UPDATE',[$id]);
  if($existing){$fields=$derived;unset($fields['id']);IssuerStorage::query($this->db,'UPDATE '.$table.' SET '.implode(',',array_map(static fn($k)=>'`'.$k.'`=?',array_keys($fields))).' WHERE id=?',array_merge(array_values($fields),[$id]));}
  else{IssuerStorage::query($this->db,'INSERT INTO '.$table.' (`'.implode('`,`',array_keys($derived)).'`) VALUES('.implode(',',array_fill(0,count($derived),'?')).')',array_values($derived));}
  $this->assertInOwned($id);if($this->source($id)!==$source)self::fail();
 }
 public function assertInOwned(int$id):array
 {
  $source=$this->source($id);$derived=self::derive($source);$actual=$this->rows('SELECT * FROM '.$this->p.NativeCompanyInvoiceDraftProjectionSchema::TABLE.' WHERE id=?',[$id])[0]??null;
  if(!$actual||self::normalized($actual)!==$derived)self::fail();return$derived;
 }
 /** Verify the complete private-open frame so corrupt off-page projections cannot skew counts. */
 public function frame(int$issuer,int$actor):array
 {
  require_once __DIR__.'/CompanyInvoiceDrafts.php';$drafts=new CompanyInvoiceDrafts($this->db,$this->p);
  NativeCompanyInvoiceDraftProjectionSchema::assertValid($this->db,$this->p);
  return IssuerTransaction::run($this->db,$this->p,$drafts->tables(),function()use($issuer,$actor,$drafts){
   $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($issuer,$actor,'invoices','view'))throw new DomainException('company_invoice_access_denied');
   $ids=$this->rows('SELECT id FROM '.$this->p.'company_invoice_drafts WHERE issuer_id=? AND state=? ORDER BY id FOR UPDATE',[$issuer,'open']);$out=[];
   $projectionIds=$this->rows('SELECT id FROM '.$this->p.NativeCompanyInvoiceDraftProjectionSchema::TABLE.' WHERE issuer_id=? AND state=? ORDER BY id FOR UPDATE',[$issuer,'open']);
   if($projectionIds!==$ids)self::fail();
   foreach($ids as$r){$drafts->documentInOwned($issuer,$actor,(int)$r['id']);$out[]=$this->assertInOwned((int)$r['id']);}
   if($this->rows('SELECT id FROM '.$this->p.NativeCompanyInvoiceDraftProjectionSchema::TABLE.' WHERE issuer_id=? AND state=? ORDER BY id',[$issuer,'open'])!==$projectionIds)self::fail();
   if(!$acl->can($issuer,$actor,'invoices','view')||$ids!==$this->rows('SELECT id FROM '.$this->p.'company_invoice_drafts WHERE issuer_id=? AND state=? ORDER BY id',[$issuer,'open']))self::fail();return$out;
  });
 }
 /** Migration-only all-or-fail backfill. DDL is separate; any corrupt source rolls back all projection writes. */
 public function backfill():void
 {
  NativeCompanyInvoiceDraftProjectionSchema::assertValid($this->db,$this->p);
  IssuerTransaction::run($this->db,$this->p,self::tables(),function(){
   $ids=$this->rows('SELECT id FROM '.$this->p.'company_invoice_drafts ORDER BY id FOR UPDATE');
   foreach($ids as$r)$this->syncInOwned((int)$r['id']);
   $orphans=$this->rows('SELECT p.id FROM '.$this->p.NativeCompanyInvoiceDraftProjectionSchema::TABLE.' p LEFT JOIN '.$this->p.'company_invoice_drafts d ON d.id=p.id WHERE d.id IS NULL');if($orphans)self::fail();
   if($this->rows('SELECT id FROM '.$this->p.'company_invoice_drafts ORDER BY id')!==$ids)self::fail();
  });
 }
}
