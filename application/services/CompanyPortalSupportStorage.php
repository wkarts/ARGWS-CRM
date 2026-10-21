<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyPortalOperationsStorage.php';require_once __DIR__.'/CompanyPortalSupportWriteSchema.php';require_once __DIR__.'/CompanyPortal.php';
/** Contact-authored support, without a staff session or an impersonated staff actor. */
abstract class CompanyPortalSupportStorage extends CompanyPortalOperationsStorage
{
 public function __construct($db,string $p,protected CompanyPortal $portal){parent::__construct($db,$p);}
 protected function extraTables():array{CompanyPortalSupportWriteSchema::assertValid($this->db,$this->p);return array_keys(CompanyPortalSupportWriteSchema::definitions($this->p));}
 protected function identity(array $session,int $issuer):array
 {
  $who=$this->portal->invoiceIdentity($session,$issuer,true);
  if(!$who||!$this->rows('SELECT client_id FROM '.$this->p.'issuers_clients WHERE owner_issuer_id=? AND client_id=? FOR UPDATE',[$issuer,$who['client_id']])||count($this->rows('SELECT id FROM '.$this->p.'contact_permissions WHERE userid=? AND permission_id=5 FOR UPDATE',[$who['contact_id']]))!==1)throw new DomainException('company_portal_operation_unavailable');return $who;
 }
 protected static function text($v,int $max):string{if(!is_string($v)||strlen($v)>$max||trim($v)===''||preg_match('//u',$v)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$v))throw new DomainException('company_portal_support_invalid');return trim($v);}
 protected function owned(array $who,int $issuer,int $id):array
 {
  $row=$this->rows('SELECT p.* FROM '.$this->p.'company_tickets p JOIN '.$this->p.'company_portal_tickets v ON v.issuer_id=p.issuer_id AND v.ticket_id=p.id AND v.visible=1 WHERE p.issuer_id=? AND p.id=? AND p.client_id=? FOR UPDATE',[$issuer,$id,$who['client_id']])[0]??null;if(!$row)throw new DomainException('company_portal_operation_unavailable');return $row;
 }
 protected function operation(array $session,int $issuer,string $operation,string $key,array $payload,callable $work,?callable $replay=null):int
 {
  if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_portal_support_invalid');$hash=hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR));
  return $this->tx(function()use($session,$issuer,$operation,$key,$hash,$work,$replay):int{
   $who=$this->identity($session,$issuer);$old=$this->rows('SELECT * FROM '.$this->p.'company_portal_support_requests WHERE request_key=? FOR UPDATE',[$key])[0]??null;
   if($old){if((int)$old['issuer_id']!==$issuer||(int)$old['client_id']!==$who['client_id']||(int)$old['contact_id']!==$who['contact_id']||$old['operation']!==$operation||!hash_equals($old['input_hash'],$hash))throw new DomainException('company_portal_support_request_conflict');$id=(int)$old['ticket_id'];$this->owned($who,$issuer,$id);if($old['reply_id']!==null&&!$this->rows('SELECT reply_id FROM '.$this->p.'company_portal_support_replies WHERE issuer_id=? AND ticket_id=? AND client_id=? AND contact_id=? AND reply_id=?',[$issuer,$id,$who['client_id'],$who['contact_id'],$old['reply_id']]))throw new RuntimeException('issuer_portal_operation_unconfirmed');if($replay!==null)$replay($who,$old);$this->identity($session,$issuer);return $id;}
   [$id,$reply]=$work($who);$now=gmdate('Y-m-d H:i:s');$this->audit($issuer,0,$id,'company_portal_support_'.$operation,['contact_id'=>$who['contact_id'],'client_id'=>$who['client_id'],'reply_id'=>$reply,'request_key'=>$key]);
   IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_portal_support_requests(request_key,issuer_id,client_id,contact_id,operation,input_hash,ticket_id,reply_id,created_at) VALUES(?,?,?,?,?,?,?,?,?)',[$key,$issuer,$who['client_id'],$who['contact_id'],$operation,$hash,$id,$reply,$now]);
   if(!$this->rows('SELECT request_key FROM '.$this->p.'company_portal_support_requests WHERE request_key=? AND issuer_id=? AND client_id=? AND contact_id=? AND operation=? AND input_hash=? AND ticket_id=?',[$key,$issuer,$who['client_id'],$who['contact_id'],$operation,$hash,$id]))throw new RuntimeException('issuer_portal_operation_unconfirmed');$this->owned($who,$issuer,$id);$this->identity($session,$issuer);return $id;
  });
 }
}
