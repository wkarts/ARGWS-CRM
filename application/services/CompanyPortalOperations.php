<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyPortalOperationsStorage.php';require_once __DIR__.'/CompanyPortalSupportWriteSchema.php';require_once __DIR__.'/CompanyPortalSupportFilesSchema.php';require_once __DIR__.'/CompanyContractDiscussions.php';require_once __DIR__.'/CompanyPortal.php';
/** Native3/5/6, explicit publication and private contact/client/issuer ownership before hydration. */
require_once __DIR__.'/CompanyTicketPreferences.php';
final class CompanyPortalOperations extends CompanyPortalOperationsStorage
{
 public function __construct($db,string $p,private CompanyPortal $portal){parent::__construct($db,$p);}
 protected function extraTables():array{CompanyPortalSupportWriteSchema::assertValid($this->db,$this->p);CompanyPortalSupportFilesSchema::assertValid($this->db,$this->p);CompanyContractDiscussionsSchema::assertValid($this->db,$this->p);return array_merge(array_keys(CompanyPortalSupportWriteSchema::definitions($this->p)),array_keys(CompanyPortalSupportFilesSchema::definitions($this->p)),array_keys(CompanyContractDiscussionsSchema::definitions($this->p)));}
 private function identity(array $session,int $issuer,string $kind):?array
 {
  $t=self::target($kind);$who=$this->portal->invoiceIdentity($session,$issuer,true);if(!$who)return null;
  if(!$this->rows('SELECT client_id FROM '.$this->p.'issuers_clients WHERE owner_issuer_id=? AND client_id=? FOR UPDATE',[$issuer,$who['client_id']]))return null;
  $grants=$this->rows('SELECT id FROM '.$this->p.'contact_permissions WHERE userid=? AND permission_id=? FOR UPDATE',[$who['contact_id'],$t['permission']]);return count($grants)===1?$who:null;
 }
 private function stateSql(string $kind):string{return match($kind){'contracts'=>"p.number IS NOT NULL AND p.status IN ('active','cancelled')",'tickets'=>"p.status IN ('open','resolved','closed') AND ".NativeCompanyTicketVisibility::predicate($this->db,$this->p,'p.issuer_id','p.id'),'projects'=>"p.status IN ('active','completed','cancelled')",default=>throw new DomainException('company_portal_operation_unavailable')};}
 private function owned(array $session,int $issuer,string $kind,int $id):array
 {
  $t=self::target($kind);$who=$this->identity($session,$issuer,$kind);if(!$who)throw new DomainException('company_portal_operation_unavailable');$row=$this->rows('SELECT p.* FROM '.$this->p.$t['parent'].' p JOIN '.$this->p.'company_portal_'.$kind.' v ON v.issuer_id=p.issuer_id AND v.'.$t['field'].'=p.id AND v.visible=1 WHERE p.issuer_id=? AND p.client_id=? AND p.id=? AND '.$this->stateSql($kind).' FOR UPDATE',[$issuer,$who['client_id'],$id])[0]??null;if(!$row)throw new DomainException('company_portal_operation_unavailable');return [$row,$who];
 }
 private function files(int $issuer,string $kind,int $id,int $contact):array
 {
  if($kind==='tickets')return $this->rows('SELECT f.file_key,f.filename,f.mime,f.byte_size,CASE WHEN o.contact_id=? THEN 1 ELSE 0 END own_upload FROM '.$this->p.'company_ticket_files f LEFT JOIN '.$this->p.'company_portal_tickets_files v ON v.issuer_id=f.issuer_id AND v.ticket_id=f.ticket_id AND v.file_key=f.file_key LEFT JOIN '.$this->p.'company_portal_support_files o ON o.issuer_id=f.issuer_id AND o.ticket_id=f.ticket_id AND o.file_key=f.file_key AND o.contact_id=? WHERE f.issuer_id=? AND f.ticket_id=? AND f.active=1 AND (v.visible=1 OR o.contact_id=?) ORDER BY f.created_at,f.file_key LIMIT 20',[$contact,$contact,$issuer,$id,$contact]);
  $t=self::target($kind);return $this->rows('SELECT f.file_key,f.filename,f.mime,f.byte_size FROM '.$this->p.$t['files'].' f JOIN '.$this->p.'company_portal_'.$kind.'_files v ON v.issuer_id=f.issuer_id AND v.'.$t['field'].'=f.'.$t['field'].' AND v.file_key=f.file_key AND v.visible=1 WHERE f.issuer_id=? AND f.'.$t['field'].'=? AND f.active=1'.($kind==='projects'?' AND f.task_id IS NULL':'').' ORDER BY f.created_at,f.file_key LIMIT 20',[$issuer,$id]);
 }
 public function page(array $session,$issuer,string $kind,$after=0):?array
 {
  $issuer=IssuerPolicy::id($issuer);$after=$after===0||$after==='0'?0:IssuerPolicy::id($after);$t=self::target($kind);
  return $this->tx(function()use($session,$issuer,$kind,$after,$t):?array{$who=$this->identity($session,$issuer,$kind);if(!$who)return null;$fields=$kind==='tickets'?'p.id,p.subject name,p.status,p.priority,p.created_at':($kind==='contracts'?'p.id,p.number,p.name,p.status,p.start_date,p.due_date':'p.id,p.name,p.status,p.start_date,p.due_date');$rows=$this->rows('SELECT '.$fields.' FROM '.$this->p.$t['parent'].' p JOIN '.$this->p.'company_portal_'.$kind.' v ON v.issuer_id=p.issuer_id AND v.'.$t['field'].'=p.id AND v.visible=1 WHERE p.issuer_id=? AND p.client_id=? AND p.id>? AND '.$this->stateSql($kind).' ORDER BY p.id LIMIT 51',[$issuer,$who['client_id'],$after]);$next=count($rows)>50?(int)$rows[49]['id']:null;$rows=array_slice($rows,0,50);foreach($rows as &$r)$r['files']=$this->files($issuer,$kind,(int)$r['id'],$who['contact_id']);unset($r);if(!$this->identity($session,$issuer,$kind))throw new DomainException('company_portal_operation_unavailable');return ['items'=>$rows,'next'=>$next];});
 }
 private function ticketReplies(int $issuer,int $id,string $order):array
 {
  if(!in_array($order,['ASC','DESC'],true))throw new LogicException('Invalid ticket reply order');
  return $this->rows('SELECT r.id,r.body,r.created_at,r.actor_id,o.author_name contact_author FROM '.$this->p.'company_ticket_replies r LEFT JOIN '.$this->p.'company_portal_support_replies o ON o.issuer_id=r.issuer_id AND o.ticket_id=r.ticket_id AND o.reply_id=r.id JOIN '.$this->p.'company_portal_ticket_replies v ON v.issuer_id=r.issuer_id AND v.ticket_id=r.ticket_id AND v.reply_id=r.id AND v.visible=1 WHERE r.issuer_id=? AND r.ticket_id=? AND '.NativeCompanyTicketVisibility::predicate($this->db,$this->p,'r.issuer_id','r.id',true).' ORDER BY r.id '.$order.' LIMIT 500',[$issuer,$id]);
 }
 public function detail(array $session,$issuer,string $kind,$id):array
 {
  $issuer=IssuerPolicy::id($issuer);$id=IssuerPolicy::id($id);self::target($kind);
  return $this->tx(function()use($session,$issuer,$kind,$id):array{[$row,$who]=$this->owned($session,$issuer,$kind,$id);
   if($kind==='contracts'){$snapshot=json_decode($row['snapshot_json'],true,32,JSON_THROW_ON_ERROR);if(!is_array($snapshot)||($snapshot['issuer_id']??null)!==$issuer||($snapshot['client']['id']??null)!==$who['client_id']||($snapshot['number']??null)!==(int)$row['number'])throw new DomainException('company_portal_operation_unavailable');$record=['id'=>$id,'issuer_id'=>$issuer,'client_id'=>$who['client_id'],'number'=>(int)$row['number'],'status'=>$row['status'],'revision'=>(int)$row['revision'],'snapshot_json'=>$row['snapshot_json'],'name'=>$snapshot['name'],'body'=>$snapshot['body'],'start_date'=>$snapshot['start_date'],'due_date'=>$snapshot['due_date']];}
   elseif($kind==='tickets'){$preferences=CompanyTicketPreferences::readInOwnedTransaction($this->db,$this->p,$issuer);$order=$preferences[CompanyTicketPreferences::KEY]==='desc'?'DESC':'ASC';$record=['id'=>$id,'name'=>$row['subject'],'body'=>$row['body'],'status'=>$row['status'],'priority'=>$row['priority'],'revision'=>(int)$row['revision']];$record['replies']=$this->ticketReplies($issuer,$id,$order);}
   else $record=['id'=>$id,'name'=>$row['name'],'description'=>$row['description'],'status'=>$row['status'],'start_date'=>$row['start_date'],'due_date'=>$row['due_date'],'revision'=>(int)$row['revision']];
   $record['files']=$this->files($issuer,$kind,$id,$who['contact_id']);if($kind==='contracts')$record['discussion']=(new CompanyContractDiscussions($this->db,$this->p,$this->portal))->publicInOwnedTransaction($session,$issuer,$id,$record['revision']);$this->audit($issuer,0,$id,'company_portal_operation_viewed',['kind'=>$kind,'contact_id'=>$who['contact_id'],'client_id'=>$who['client_id'],'revision'=>$record['revision']]);$this->owned($session,$issuer,$kind,$id);if($kind==='tickets'){CompanyTicketPreferences::confirmInOwnedTransaction($this->db,$this->p,$issuer,$preferences);if($record['replies']!==$this->ticketReplies($issuer,$id,$order))throw new RuntimeException('issuer_portal_operation_unconfirmed');}return $record;
  });
 }
 public function file(array $session,$issuer,string $kind,$id,string $key):array
 {
  $issuer=IssuerPolicy::id($issuer);$id=IssuerPolicy::id($id);$t=self::target($kind);if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_portal_operation_unavailable');
  return $this->tx(function()use($session,$issuer,$kind,$id,$key,$t):array{[$parent,$who]=$this->owned($session,$issuer,$kind,$id);if($kind==='tickets')$row=$this->rows('SELECT f.filename,f.mime,f.byte_size,f.content_hash,f.content FROM '.$this->p.'company_ticket_files f LEFT JOIN '.$this->p.'company_portal_tickets_files v ON v.issuer_id=f.issuer_id AND v.ticket_id=f.ticket_id AND v.file_key=f.file_key LEFT JOIN '.$this->p.'company_portal_support_files o ON o.issuer_id=f.issuer_id AND o.ticket_id=f.ticket_id AND o.file_key=f.file_key AND o.contact_id=? WHERE f.issuer_id=? AND f.ticket_id=? AND f.file_key=? AND f.active=1 AND (v.visible=1 OR o.contact_id=?) FOR UPDATE',[$who['contact_id'],$issuer,$id,$key,$who['contact_id']])[0]??null;else $row=$this->rows('SELECT f.filename,f.mime,f.byte_size,f.content_hash,f.content FROM '.$this->p.$t['files'].' f JOIN '.$this->p.'company_portal_'.$kind.'_files v ON v.issuer_id=f.issuer_id AND v.'.$t['field'].'=f.'.$t['field'].' AND v.file_key=f.file_key AND v.visible=1 WHERE f.issuer_id=? AND f.'.$t['field'].'=? AND f.file_key=? AND f.active=1'.($kind==='projects'?' AND f.task_id IS NULL':'').' FOR UPDATE',[$issuer,$id,$key])[0]??null;
   if(!$row)throw new DomainException('company_portal_operation_unavailable');$allowed=$kind==='projects'?['application/pdf','image/png','image/jpeg','text/plain','application/octet-stream']:['application/pdf','image/png'];$minimum=$kind==='projects'?1:8;$maximum=$kind==='projects'?20971520:2097152;if(!in_array($row['mime'],$allowed,true)||(int)$row['byte_size']<$minimum||(int)$row['byte_size']>$maximum||strlen($row['content'])!==(int)$row['byte_size']||!hash_equals($row['content_hash'],hash('sha256',$row['content'])))throw new RuntimeException('issuer_operation_file_corrupt');$this->audit($issuer,0,$id,'company_portal_operation_file_downloaded',['kind'=>$kind,'contact_id'=>$who['contact_id'],'client_id'=>$who['client_id'],'file_key'=>$key,'sha256'=>$row['content_hash']]);$this->owned($session,$issuer,$kind,$id);return $row;
  });
 }
 public function confirmPdf(array $session,$issuer,$id,$revision,string $snapshotHash,string $bytesHash):void
 {
  $issuer=IssuerPolicy::id($issuer);$id=IssuerPolicy::id($id);$revision=IssuerPolicy::id($revision);if(!preg_match('/^[a-f0-9]{64}$/D',$snapshotHash)||!preg_match('/^[a-f0-9]{64}$/D',$bytesHash))throw new DomainException('company_portal_operation_unavailable');
  $this->tx(function()use($session,$issuer,$id,$revision,$snapshotHash,$bytesHash):void{[$r,$who]=$this->owned($session,$issuer,'contracts',$id);if((int)$r['revision']!==$revision||!hash_equals(hash('sha256',$r['snapshot_json']),$snapshotHash))throw new DomainException('company_portal_operation_unavailable');$this->audit($issuer,0,$id,'company_portal_contract_pdf_downloaded',['contact_id'=>$who['contact_id'],'client_id'=>$who['client_id'],'sha256'=>$bytesHash,'snapshot_sha256'=>$snapshotHash]);$this->owned($session,$issuer,'contracts',$id);});
 }
}
