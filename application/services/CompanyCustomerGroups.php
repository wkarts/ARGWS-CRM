<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyCustomerAccess.php';
require_once __DIR__.'/NativeCompanyCustomerAudit.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
/** Native IDs and native membership rows; explicit ownership of new group definitions. */
final class CompanyCustomerGroups
{
    public const TABLES=['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','issuers_audit','staff','clients','issuers_clients','customers_groups','customer_groups','company_customer_group_owners'];
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function authorize(int $issuer,int $actor,bool $write=false,?int $client=null):void
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if(!$write&&$client!==null&&(new CompanyCustomerAccess($this->db,$this->p))->can($issuer,$actor,$client,'view'))return;
        if(($write?!$acl->can($issuer,$actor,'customers','edit'):(!$acl->can($issuer,$actor,'customers','view')&&!$acl->can($issuer,$actor,'customers','create')&&!$acl->can($issuer,$actor,'customers','edit')))||($write&&!$acl->can($issuer,$actor,'customers','manage_groups')))throw new DomainException('company_client_access_denied');
    }
    public function all(int $issuer,int $actor,?int $client=null):array
    {
        $this->authorize($issuer,$actor,false,$client);
        $rows=$this->rows('SELECT g.id,g.name FROM '.$this->p.'customers_groups g JOIN '.$this->p.'company_customer_group_owners o ON o.group_id=g.id WHERE o.issuer_id=? ORDER BY g.name,g.id',[$issuer]);
        foreach($rows as &$row){$row['id']=(int)$row['id'];$row['revision']=self::revision($row);}unset($row);
        $this->authorize($issuer,$actor,false,$client);return $rows;
    }
    private static function revision(array $row):string{return hash('sha256',json_encode([(int)$row['id'],$row['name']],JSON_THROW_ON_ERROR));}
    public function save(int $issuer,int $actor,string $name,?int $id=null,?string $revision=null):int
    {
        $name=trim($name);if($name===''||strlen($name)>191||preg_match('//u',$name)!==1||preg_match('/[\x00-\x1f\x7f]/',$name))throw new DomainException('company_client_invalid_input');
        return IssuerTransaction::run($this->db,$this->p,self::TABLES,function()use($issuer,$actor,$name,$id,$revision):int{
            $this->authorize($issuer,$actor,true);
            if($id!==null){
                $old=$this->rows('SELECT g.id,g.name FROM '.$this->p.'customers_groups g JOIN '.$this->p.'company_customer_group_owners o ON o.group_id=g.id WHERE g.id=? AND o.issuer_id=? FOR UPDATE',[$id,$issuer])[0]??null;
                if(!$old)throw new DomainException('company_client_access_denied');
                if($revision===null||!hash_equals(self::revision($old),$revision))throw new DomainException('company_client_stale');
                IssuerStorage::query($this->db,'UPDATE '.$this->p.'customers_groups SET name=? WHERE id=?',[$name,$id]);
            }else{
                // A repeated create of the exact same owner/name is a safe idempotent catalog operation.
                $found=$this->rows('SELECT g.id FROM '.$this->p.'customers_groups g JOIN '.$this->p.'company_customer_group_owners o ON o.group_id=g.id WHERE o.issuer_id=? AND g.name=?',[$issuer,$name]);
                if($found)return (int)$found[0]['id'];
                IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'customers_groups(name) VALUES(?)',[$name]);$id=(int)$this->db->insert_id();
                if($id<1)throw new RuntimeException('issuer_client_write_unconfirmed');
                IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_customer_group_owners(group_id,issuer_id) VALUES(?,?)',[$id,$issuer]);
            }
            $actual=$this->rows('SELECT g.name FROM '.$this->p.'customers_groups g JOIN '.$this->p.'company_customer_group_owners o ON o.group_id=g.id WHERE g.id=? AND o.issuer_id=?',[$id,$issuer])[0]??[];
            if(($actual['name']??null)!==$name)throw new RuntimeException('issuer_client_write_unconfirmed');
            NativeCompanyCustomerAudit::record($this->db,$this->p,$issuer,$actor,$id,'native_customer_group_saved');
            $this->authorize($issuer,$actor,true);return $id;
        });
    }
    public function delete(int $issuer,int $actor,int $id,string $revision):void
    {
        IssuerTransaction::run($this->db,$this->p,self::TABLES,function()use($issuer,$actor,$id,$revision):void{
            $this->authorize($issuer,$actor,true);
            $old=$this->rows('SELECT g.id,g.name FROM '.$this->p.'customers_groups g JOIN '.$this->p.'company_customer_group_owners o ON o.group_id=g.id WHERE g.id=? AND o.issuer_id=? FOR UPDATE',[$id,$issuer])[0]??null;
            if(!$old)throw new DomainException('company_client_access_denied');
            if(!hash_equals(self::revision($old),$revision))throw new DomainException('company_client_stale');
            if($this->rows('SELECT m.id FROM '.$this->p.'customer_groups m LEFT JOIN '.$this->p.'issuers_clients c ON c.client_id=m.customer_id WHERE m.groupid=? AND (c.owner_issuer_id IS NULL OR c.owner_issuer_id<>?)',[$id,$issuer]))throw new DomainException('company_client_group_owner_mismatch');
            IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'customer_groups WHERE groupid=?',[$id]);
            IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'company_customer_group_owners WHERE group_id=? AND issuer_id=?',[$id,$issuer]);
            IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'customers_groups WHERE id=?',[$id]);
            if($this->rows('SELECT id FROM '.$this->p.'customers_groups WHERE id=?',[$id]))throw new RuntimeException('issuer_client_write_unconfirmed');
            NativeCompanyCustomerAudit::record($this->db,$this->p,$issuer,$actor,$id,'native_customer_group_deleted');
            $this->authorize($issuer,$actor,true);
        });
    }
    public function memberships(int $issuer,int $actor,int $client):array
    {
        $this->authorize($issuer,$actor,false,$client);
        if(!$this->rows('SELECT client_id FROM '.$this->p.'issuers_clients WHERE client_id=? AND owner_issuer_id=?',[$client,$issuer]))throw new DomainException('company_client_access_denied');
        $rows=$this->rows('SELECT m.id,m.customer_id,m.groupid FROM '.$this->p.'customer_groups m JOIN '.$this->p.'company_customer_group_owners o ON o.group_id=m.groupid WHERE m.customer_id=? AND o.issuer_id=? ORDER BY m.groupid',[$client,$issuer]);
        $this->authorize($issuer,$actor,false,$client);return $rows;
    }
    public function syncOwned(int $issuer,int $actor,int $client,array $ids,string $cap='edit'):void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,self::TABLES);
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if(!in_array($cap,['create','edit'],true)||!($acl->can($issuer,$actor,'customers',$cap)||($cap==='edit'&&(new CompanyCustomerAccess($this->db,$this->p))->can($issuer,$actor,$client,'edit')))||!$this->rows('SELECT client_id FROM '.$this->p.'issuers_clients WHERE client_id=? AND owner_issuer_id=?',[$client,$issuer]))throw new DomainException('company_client_access_denied');
        if(count($ids)>100)throw new DomainException('company_client_invalid_input');$ids=array_values(array_unique(array_map([IssuerPolicy::class,'id'],$ids)));sort($ids);
        foreach($ids as $id)if(!$this->rows('SELECT group_id FROM '.$this->p.'company_customer_group_owners WHERE group_id=? AND issuer_id=?',[$id,$issuer]))throw new DomainException('company_client_access_denied');
        // Reject a corrupt foreign membership rather than deleting another owner's data.
        if($this->rows('SELECT m.id FROM '.$this->p.'customer_groups m LEFT JOIN '.$this->p.'company_customer_group_owners o ON o.group_id=m.groupid WHERE m.customer_id=? AND (o.issuer_id IS NULL OR o.issuer_id<>?)',[$client,$issuer]))throw new DomainException('company_client_group_owner_mismatch');
        IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'customer_groups WHERE customer_id=?',[$client]);
        foreach($ids as $id)IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'customer_groups(customer_id,groupid) VALUES(?,?)',[$client,$id]);
        $actual=array_map('intval',array_column($this->rows('SELECT groupid FROM '.$this->p.'customer_groups WHERE customer_id=? ORDER BY groupid',[$client]),'groupid'));
        if($actual!==$ids)throw new RuntimeException('issuer_client_write_unconfirmed');
        if(!($acl->can($issuer,$actor,'customers',$cap)||($cap==='edit'&&(new CompanyCustomerAccess($this->db,$this->p))->can($issuer,$actor,$client,'edit'))))throw new DomainException('company_client_access_denied');
    }
}
