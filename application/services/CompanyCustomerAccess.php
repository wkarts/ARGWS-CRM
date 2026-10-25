<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
require_once __DIR__.'/CompanyCustomerAdminSchema.php';
require_once __DIR__.'/NativeCompanyCustomerAudit.php';
final class CompanyCustomerAccess
{
    private IssuerResourceAccess $acl;
    public function __construct(private $db,private string$p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');$this->acl=new IssuerResourceAccess($db,$p);}
    private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function exists():bool{$c=$this->db->pdo??$this->db->conn_id??null;return(bool)$this->rows($c instanceof PDO&&$c->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?"SELECT name FROM sqlite_master WHERE type='table' AND name=?":'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$this->p.'company_customer_admins']);}
    public function can(int$issuer,int$actor,int$client,string$cap):bool
    {
        if(!in_array($cap,['view','edit','create_contact','delete_contact'],true))throw new InvalidArgumentException('Invalid customer capability');
        $global=['create_contact'=>'create','delete_contact'=>'delete'][$cap]??$cap;
        if(!$this->acl->member($issuer,$actor)||!$this->rows('SELECT client_id FROM '.$this->p.'issuers_clients WHERE client_id=? AND owner_issuer_id=?',[$client,$issuer]))return false;
        if($this->acl->can($issuer,$actor,'customers',$global))return true;
        return $this->exists()&&(bool)$this->rows('SELECT staff_id FROM '.$this->p.'company_customer_admins WHERE issuer_id=? AND client_id=? AND staff_id=?',[$issuer,$client,$actor]);
    }
    /** Read-only historical customer authority, restricted to the persisted installation principal. */
    public function canReadHistorical(int$issuer,int$actor,int$client):bool
    {
        if($this->acl->primaryId()!==$issuer||!$this->acl->member($issuer,$actor)||!$this->rows('SELECT c.userid FROM '.$this->p.'clients c LEFT JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid WHERE c.userid=? AND o.client_id IS NULL',[$client]))return false;
        if($this->acl->can($issuer,$actor,'customers','view'))return true;
        return(bool)$this->rows('SELECT staff_id FROM '.$this->p.'customer_admins WHERE customer_id=? AND staff_id=?',[$client,$actor]);
    }
    public function canReadDirectory(int$issuer,int$actor):bool
    {
        if(!$this->acl->member($issuer,$actor))return false;if($this->acl->can($issuer,$actor,'customers','view'))return true;
        return$this->exists()&&(bool)$this->rows('SELECT a.client_id FROM '.$this->p.'company_customer_admins a JOIN '.$this->p.'issuers_clients o ON o.client_id=a.client_id AND o.owner_issuer_id=a.issuer_id WHERE a.issuer_id=? AND a.staff_id=? LIMIT 1',[$issuer,$actor]);
    }
    public function assignedClientIds(int$issuer,int$actor):array
    {
        if(!$this->acl->member($issuer,$actor)||!$this->exists())return[];
        return array_map('intval',array_column($this->rows('SELECT a.client_id FROM '.$this->p.'company_customer_admins a JOIN '.$this->p.'issuers_clients o ON o.client_id=a.client_id AND o.owner_issuer_id=a.issuer_id WHERE a.issuer_id=? AND a.staff_id=? ORDER BY a.client_id',[$issuer,$actor]),'client_id'));
    }
    private function manage(int$issuer,int$actor,int$client):void
    {
        if((!$this->acl->can($issuer,$actor,'customers','create')&&!$this->acl->can($issuer,$actor,'customers','edit'))||!$this->rows('SELECT client_id FROM '.$this->p.'issuers_clients WHERE client_id=? AND owner_issuer_id=?',[$client,$issuer]))throw new DomainException('company_client_access_denied');
    }
    public function assignments(int$issuer,int$actor,int$client):array
    {
        $this->manage($issuer,$actor,$client);CompanyCustomerAdminSchema::assertValid($this->db,$this->p);$rows=$this->rows('SELECT a.staff_id,a.date_assigned,s.firstname,s.lastname FROM '.$this->p.'company_customer_admins a JOIN '.$this->p.'staff s ON s.staffid=a.staff_id WHERE a.issuer_id=? AND a.client_id=? ORDER BY a.staff_id',[$issuer,$client]);$this->manage($issuer,$actor,$client);return['rows'=>$rows,'revision'=>hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR))];
    }
    public function candidates(int$issuer,int$actor,int$client):array
    {
        $this->manage($issuer,$actor,$client);$rows=$this->rows('SELECT s.staffid,s.firstname,s.lastname FROM '.$this->p.'staff s JOIN '.$this->p.'issuers_staff m ON m.staff_id=s.staffid WHERE m.issuer_id=? AND m.can_view=1 AND s.active=1 ORDER BY s.firstname,s.lastname,s.staffid LIMIT 1001',[$issuer]);
        if(count($rows)>1000)throw new DomainException('company_client_invalid_page');$this->manage($issuer,$actor,$client);return$rows;
    }
    public function assign(int$issuer,int$actor,int$client,array$staff,string$revision):void
    {
        if(count($staff)>1000)throw new DomainException('company_client_invalid_input');$staff=array_values(array_unique(array_map([IssuerPolicy::class,'id'],$staff)));sort($staff,SORT_NUMERIC);
        IssuerTransaction::run($this->db,$this->p,['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','issuers_clients','issuers_audit','staff','clients','company_customer_admins'],function()use($issuer,$actor,$client,$staff,$revision):void{
            $old=$this->assignments($issuer,$actor,$client);if(!hash_equals($old['revision'],$revision))throw new DomainException('company_client_stale');foreach($staff as$id)if(!$this->acl->member($issuer,$id))throw new DomainException('company_client_access_denied');
            $existing=array_map('intval',array_column($old['rows'],'staff_id'));foreach(array_diff($existing,$staff)as$id)IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'company_customer_admins WHERE issuer_id=? AND client_id=? AND staff_id=?',[$issuer,$client,$id]);
            foreach(array_diff($staff,$existing)as$id)IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_customer_admins(issuer_id,client_id,staff_id,date_assigned) VALUES(?,?,?,?)',[$issuer,$client,$id,gmdate('Y-m-d H:i:s')]);
            if(array_map('intval',array_column($this->assignments($issuer,$actor,$client)['rows'],'staff_id'))!==$staff)throw new RuntimeException('issuer_client_write_unconfirmed');
            NativeCompanyCustomerAudit::record($this->db,$this->p,$issuer,$actor,$client,'native_customer_admins_assigned');foreach($staff as$id)if(!$this->acl->member($issuer,$id))throw new DomainException('company_client_access_denied');
        });
    }
}
