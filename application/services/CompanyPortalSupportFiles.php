<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyPortalSupportStorage.php';require_once __DIR__.'/CompanyPortalSupportFilesSchema.php';require_once __DIR__.'/CompanyOperationFileContent.php';
/** Native contact support uploads; author+team audience until an explicit staff publication. */
final class CompanyPortalSupportFiles extends CompanyPortalSupportStorage
{
 protected function extraTables():array{CompanyPortalSupportFilesSchema::assertValid($this->db,$this->p);return array_merge(parent::extraTables(),array_keys(CompanyPortalSupportFilesSchema::definitions($this->p)));}
 private function proof(array $who,int $issuer,int $id,string $file):array
 {
  $row=$this->rows('SELECT f.*,o.contact_id,o.client_id,o.author_name FROM '.$this->p.'company_ticket_files f JOIN '.$this->p.'company_portal_support_files o ON o.issuer_id=f.issuer_id AND o.ticket_id=f.ticket_id AND o.file_key=f.file_key WHERE f.issuer_id=? AND f.ticket_id=? AND f.file_key=? AND f.actor_id=0 AND o.client_id=? AND o.contact_id=? FOR UPDATE',[$issuer,$id,$file,$who['client_id'],$who['contact_id']])[0]??null;if(!$row)throw new DomainException('company_portal_operation_unavailable');if((int)$row['byte_size']!==strlen($row['content'])||!hash_equals($row['content_hash'],hash('sha256',$row['content'])))throw new RuntimeException('issuer_operation_file_corrupt');return $row;
 }
 private function editable(array $who,int $issuer,int $id,int $revision):array{$row=$this->owned($who,$issuer,$id);if($row['status']!=='open')throw new DomainException('company_portal_support_closed');if((int)$row['revision']!==$revision)throw new DomainException('company_portal_support_stale');return $row;}
 private function bump(array $who,int $issuer,int $id,int $revision):void{IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_tickets SET revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[gmdate('Y-m-d H:i:s'),$issuer,$id,$revision]);if((int)$this->owned($who,$issuer,$id)['revision']!==$revision+1)throw new RuntimeException('issuer_portal_operation_unconfirmed');}
 public function upload(array $session,$issuer,$id,$revision,string $key,string $name,string $bytes):int
 {
  $issuer=IssuerPolicy::id($issuer);$id=IssuerPolicy::id($id);$revision=IssuerPolicy::id($revision);if($revision>2147483646)throw new DomainException('company_portal_support_stale');[$name,$mime,$bytes]=CompanyOperationFileContent::normalize($name,$bytes);$hash=hash('sha256',$bytes);
  $fileKey=static fn(array $who)=>hash('sha256','contact-support-file|'.$issuer.'|'.$who['contact_id'].'|'.$key);
  return $this->operation($session,$issuer,'upload',$key,[$id,$revision,$name,$mime,$hash],function(array $who)use($issuer,$id,$revision,$name,$mime,$bytes,$hash,$fileKey):array{
   $this->editable($who,$issuer,$id,$revision);$usage=$this->rows('SELECT COUNT(*) n,COALESCE(SUM(byte_size),0) bytes FROM '.$this->p.'company_ticket_files WHERE issuer_id=? AND ticket_id=?',[$issuer,$id])[0];CompanyOperationFileContent::quota((int)$usage['n'],(int)$usage['bytes'],strlen($bytes));$file=$fileKey($who);$now=gmdate('Y-m-d H:i:s');$author=trim($who['firstname'].' '.$who['lastname']);
   IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_ticket_files(file_key,issuer_id,ticket_id,actor_id,filename,mime,content_hash,byte_size,content,active,created_at) VALUES(?,?,?,0,?,?,?,?,?,1,?)',[$file,$issuer,$id,$name,$mime,$hash,strlen($bytes),$bytes,$now]);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_portal_support_files(file_key,issuer_id,ticket_id,client_id,contact_id,author_name,created_at) VALUES(?,?,?,?,?,?,?)',[$file,$issuer,$id,$who['client_id'],$who['contact_id'],$author,$now]);$row=$this->proof($who,$issuer,$id,$file);if($row['filename']!==$name||$row['mime']!==$mime||$row['content_hash']!==$hash||(int)$row['active']!==1||$row['author_name']!==$author)throw new RuntimeException('issuer_portal_operation_unconfirmed');$this->bump($who,$issuer,$id,$revision);return [$id,null];
  },function(array $who,array $receipt)use($issuer,$id,$name,$mime,$hash,$fileKey):void{$row=$this->proof($who,$issuer,$id,$fileKey($who));if($row['filename']!==$name||$row['mime']!==$mime||$row['content_hash']!==$hash)throw new RuntimeException('issuer_portal_operation_unconfirmed');});
 }
 public function hide(array $session,$issuer,$id,$revision,string $file,string $key):int
 {
  $issuer=IssuerPolicy::id($issuer);$id=IssuerPolicy::id($id);$revision=IssuerPolicy::id($revision);if($revision>2147483646||!preg_match('/^[a-f0-9]{64}$/D',$file))throw new DomainException('company_portal_support_invalid');
  return $this->operation($session,$issuer,'hide',$key,[$id,$revision,$file],function(array $who)use($issuer,$id,$revision,$file):array{$this->editable($who,$issuer,$id,$revision);$row=$this->proof($who,$issuer,$id,$file);if((int)$row['active']!==1)throw new DomainException('company_portal_operation_unavailable');IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_ticket_files SET active=0 WHERE issuer_id=? AND ticket_id=? AND file_key=? AND active=1',[$issuer,$id,$file]);if((int)$this->proof($who,$issuer,$id,$file)['active']!==0)throw new RuntimeException('issuer_portal_operation_unconfirmed');$this->bump($who,$issuer,$id,$revision);return [$id,null];},function(array $who,array $receipt)use($issuer,$id,$file):void{if((int)$this->proof($who,$issuer,$id,$file)['active']!==0)throw new RuntimeException('issuer_portal_operation_unconfirmed');});
 }
}
