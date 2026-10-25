<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTicketVisibility.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';require_once __DIR__.'/issuers/IssuerResourceAccess.php';require_once __DIR__.'/CompanyPortalOperationsSchema.php';require_once __DIR__.'/CompanyContractsSchema.php';require_once __DIR__.'/CompanyProjectsSchema.php';require_once __DIR__.'/CompanyProjectExtrasSchema.php';require_once __DIR__.'/CompanyOperationFilesSchema.php';
/** Closed operational portal participants, with no token/session/default-company writer authority. */
abstract class CompanyPortalOperationsStorage
{
 public function __construct(protected $db,protected string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');}
 protected function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
 protected function extraTables():array{return [];}
 protected function tx(callable $work){CompanyContractsSchema::assertValid($this->db,$this->p);CompanyProjectsSchema::assertValid($this->db,$this->p);CompanyProjectExtrasSchema::assertValid($this->db,$this->p);CompanyOperationFilesSchema::assertValid($this->db,$this->p);CompanyPortalOperationsSchema::assertValid($this->db,$this->p);return IssuerTransaction::run($this->db,$this->p,array_merge(['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_clients','clients','contacts','contact_permissions','issuers_audit','company_contracts','company_tickets','company_ticket_replies','company_projects','company_contract_files','company_ticket_files','company_project_files'],array_keys(CompanyPortalOperationsSchema::definitions($this->p)),$this->extraTables(),NativeCompanyTicketVisibility::tables($this->db,$this->p)),$work);}
 protected static function target(string $kind):array{return CompanyPortalOperationsSchema::target($kind);}
 protected function audit(int $issuer,int $actor,int $id,string $event,array $metadata):void{$json=json_encode($metadata,JSON_THROW_ON_ERROR);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$actor,$event,$issuer,$id,gmdate('Y-m-d H:i:s'),$json]);if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND actor_id=? AND issuer_id=? AND subject_id=? AND event=? AND metadata_json=?',[(int)$this->db->insert_id(),$actor,$issuer,$id,$event,$json]))throw new RuntimeException('issuer_portal_operation_unconfirmed');}
 protected function parent(int $issuer,string $kind,int $id):array{$t=self::target($kind);if($kind==='tickets')NativeCompanyTicketVisibility::assertTicket($this->db,$this->p,$issuer,$id);$r=$this->rows('SELECT * FROM '.$this->p.$t['parent'].' WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$id])[0]??null;if(!$r)throw new DomainException('company_portal_operation_unavailable');return $r;}
 protected function eligible(array $row,string $kind):bool{return $kind!=='contracts'||($row['number']!==null&&in_array($row['status'],['active','cancelled'],true));}
}
