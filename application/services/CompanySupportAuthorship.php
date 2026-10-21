<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyPortalOperationsStorage.php';require_once __DIR__.'/CompanyPortalSupportWriteSchema.php';require_once __DIR__.'/CompanyPortalSupportFilesSchema.php';
/** Own staff metadata reader; a contact origin never becomes a staff principal. */
final class CompanySupportAuthorship extends CompanyPortalOperationsStorage
{
 protected function extraTables():array{CompanyPortalSupportWriteSchema::assertValid($this->db,$this->p);CompanyPortalSupportFilesSchema::assertValid($this->db,$this->p);return array_merge(array_keys(CompanyPortalSupportWriteSchema::definitions($this->p)),array_keys(CompanyPortalSupportFilesSchema::definitions($this->p)));}
 public function get($issuer,$actor,$id):array
 {
  $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);return $this->tx(function()use($issuer,$actor,$id):array{$acl=new IssuerResourceAccess($this->db,$this->p);$acl->lockFinancialIdentity($issuer,$actor);if(!$acl->can($issuer,$actor,'tickets','view'))throw new DomainException('company_operation_access_denied');$row=$this->parent($issuer,'tickets',$id);$origin=$this->rows('SELECT contact_id,author_name FROM '.$this->p.'company_portal_support_origins WHERE issuer_id=? AND ticket_id=? AND client_id=?',[$issuer,$id,$row['client_id']])[0]??null;$replies=[];foreach($this->rows('SELECT reply_id,contact_id,author_name FROM '.$this->p.'company_portal_support_replies WHERE issuer_id=? AND ticket_id=? AND client_id=? ORDER BY reply_id LIMIT 500',[$issuer,$id,$row['client_id']]) as $r)$replies[(int)$r['reply_id']]=$r;$files=[];foreach($this->rows('SELECT file_key,contact_id,author_name FROM '.$this->p.'company_portal_support_files WHERE issuer_id=? AND ticket_id=? AND client_id=? ORDER BY file_key LIMIT 20',[$issuer,$id,$row['client_id']]) as $file)$files[$file['file_key']]=$file;if(!$acl->can($issuer,$actor,'tickets','view'))throw new DomainException('company_operation_access_denied');return ['files'=>$files,'origin'=>$origin,'replies'=>$replies,'revision'=>(int)$row['revision']];});
 }
}
