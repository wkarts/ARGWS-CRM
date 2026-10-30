<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyPortalTaskWriteStorage.php';
/** Staff-only provenance display; no authority to change billed state or contact identity. */
final class CompanyPortalTaskAuthorship extends CompanyPortalTaskWriteStorage
{
 public function get($issuer,$actor,$project,$task):array
 {
  $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$project=IssuerPolicy::id($project);$task=IssuerPolicy::id($task);return $this->tx(function()use($issuer,$actor,$project,$task):array{$authorize=function()use($issuer,$actor):void{$acl=new IssuerResourceAccess($this->db,$this->p);$acl->lockFinancialIdentity($issuer,$actor);if(!$acl->can($issuer,$actor,'projects','view')||!$acl->can($issuer,$actor,'tasks','view'))throw new DomainException('company_project_portal_access_denied');};$authorize();if(!$this->rows('SELECT id FROM '.$this->p.'company_projects WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$project]))throw new DomainException('company_project_portal_unavailable');$this->task($issuer,$project,$task);$origin=$this->rows('SELECT contact_id,author_name,billing_state,created_at FROM '.$this->p.'company_portal_task_origins WHERE issuer_id=? AND project_id=? AND task_id=?',[$issuer,$project,$task])[0]??null;$comments=$this->rows('SELECT comment_id,author_name,created_at FROM '.$this->p.'company_portal_task_comments WHERE issuer_id=? AND project_id=? AND task_id=? ORDER BY comment_id LIMIT 500',[$issuer,$project,$task]);$files=$this->rows('SELECT file_key,author_name,created_at FROM '.$this->p.'company_portal_task_files WHERE issuer_id=? AND project_id=? AND task_id=? ORDER BY file_key LIMIT 20',[$issuer,$project,$task]);$authorize();return ['origin'=>$origin,'comments'=>$comments,'files'=>$files];});
 }
}
