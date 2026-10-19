<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';require_once __DIR__.'/CompanyPortalOperationsSchema.php';

/** Native contract3/support5/project6 grants. Private contact ownership remains mandatory. */
final class CompanyContactOperationAccess
{
    public function __construct(private $db,private string $p,private string $kind)
    {
        CompanyPortalOperationsSchema::target($kind);if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');
    }
    private function rows(string $sql,array $params=[]):array{return IssuerStorage::query($this->db,$sql,$params)->result_array();}
    private function transaction(callable $work)
    {
        return IssuerTransaction::run($this->db,$this->p,['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','issuers_clients','issuers_audit','clients','contacts','contact_permissions'],$work);
    }
    private function authorize(int $issuer,int $actor,int $client,string $cap):void
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);$acl->lockFinancialIdentity($issuer,$actor);
        if(!$acl->can($issuer,$actor,'customers',$cap)||!$acl->can($issuer,$actor,$this->kind,'view')||!$this->rows('SELECT o.client_id FROM '.$this->p.'issuers_clients o JOIN '.$this->p.'clients c ON c.userid=o.client_id WHERE o.client_id=? AND o.owner_issuer_id=? AND c.active=1 FOR UPDATE',[$client,$issuer]))throw new DomainException('company_client_access_denied');
    }
    private function projection(array $contact):array
    {
        $permissions=$this->rows('SELECT id FROM '.$this->p.'contact_permissions WHERE userid=? AND permission_id='.CompanyPortalOperationsSchema::target($this->kind)['permission'].' ORDER BY id FOR UPDATE',[$contact['id']]);
        return ['allowed'=>count($permissions)===1,'count'=>count($permissions),'revision'=>hash('sha256',json_encode([$contact,array_column($permissions,'id')],JSON_THROW_ON_ERROR))];
    }
    public function page($issuer,$actor,$client):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$client=IssuerPolicy::id($client);
        return $this->transaction(function()use($issuer,$actor,$client):array{
            $this->authorize($issuer,$actor,$client,'view');$out=[];
            foreach($this->rows('SELECT id,userid,active,email,firstname,lastname FROM '.$this->p.'contacts WHERE userid=? ORDER BY id LIMIT 101 FOR UPDATE',[$client]) as $row)$out[(int)$row['id']]=$this->projection($row)+['name'=>trim($row['firstname'].' '.$row['lastname'])];
            if(count($out)>100)throw new DomainException('company_contact_page_limit');return $out;
        });
    }
    public function set($issuer,$actor,$client,$contact,string $revision,string $allowed):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$client=IssuerPolicy::id($client);$contact=IssuerPolicy::id($contact);
        if(!in_array($allowed,['0','1'],true)||!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('company_contact_invalid_input');
        $this->transaction(function()use($issuer,$actor,$client,$contact,$revision,$allowed):void{
            $this->authorize($issuer,$actor,$client,'edit');$row=$this->rows('SELECT id,userid,active,email,firstname,lastname FROM '.$this->p.'contacts WHERE id=? AND userid=? FOR UPDATE',[$contact,$client])[0]??null;
            if(!$row||($allowed==='1'&&(int)$row['active']!==1))throw new DomainException('company_client_access_denied');$before=$this->projection($row);
            if(!hash_equals($before['revision'],$revision))throw new DomainException('company_client_stale');
            IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'contact_permissions WHERE userid=? AND permission_id='.CompanyPortalOperationsSchema::target($this->kind)['permission'].'',[$contact]);
            if($allowed==='1')IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'contact_permissions(permission_id,userid) VALUES('.CompanyPortalOperationsSchema::target($this->kind)['permission'].',?)',[$contact]);
            $after=$this->projection($row);if($after['count']!==($allowed==='1'?1:0))throw new RuntimeException('issuer_contact_permission_unconfirmed');
            $event=$allowed==='1'?'company_contact_operation_granted':'company_contact_operation_revoked';
            $metadata=json_encode(['client_id'=>$client,'permission_id'=>CompanyPortalOperationsSchema::target($this->kind)['permission'],'receipt'=>bin2hex(random_bytes(16))],JSON_THROW_ON_ERROR);
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$actor,$event,$issuer,$contact,date('Y-m-d H:i:s'),$metadata]);
            $saved=$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND event=? AND subject_id=? AND metadata_json=?',[(int)$this->db->insert_id(),$event,$contact,$metadata]);if(count($saved)!==1)throw new RuntimeException('issuer_contact_permission_unconfirmed');
        });
    }
}
