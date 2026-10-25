<?php
declare(strict_types=1);
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/NativeCompanyResourceId.php';
/** Existing Misc tickets_contacts capability, not customer-profile or portal authorization. */
final class NativeCompanyTicketContacts
{
    private const OPTION='staff_members_open_tickets_to_all_contacts';
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public function tables():array{return['issuers_lock','issuers_registry','issuers_staff','issuers_installation_identity','issuers_resource_permissions','staff','options','clients','contacts','issuers_clients','company_customer_admins','customer_admins'];}
    private function frame(int $issuer,int $actor):array
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($issuer,$actor,'tickets','view')||!$acl->can($issuer,$actor,'tickets','create'))throw new DomainException('native_ticket_contact_denied');
        $store=new CompanySettingsStorage($this->db,$this->p);$value=$store->exists($issuer,self::OPTION)?$store->get($issuer,self::OPTION):'1';
        if(!in_array($value,['0','1'],true))throw new DomainException('native_ticket_contact_policy_invalid');
        $nonstaff=$store->exists($issuer,'access_tickets_to_none_staff_members')?$store->get($issuer,'access_tickets_to_none_staff_members'):'0';if(!in_array($nonstaff,['0','1'],true))throw new DomainException('native_ticket_contact_policy_invalid');
        $staff=IssuerStorage::query($this->db,'SELECT active,is_not_staff FROM '.$this->p.'staff WHERE staffid=?',[$actor])->row_array();if(!$staff||(int)$staff['active']!==1||($nonstaff==='0'&&(int)$staff['is_not_staff']!==0))throw new DomainException('native_ticket_contact_denied');
        return['nonstaff'=>$nonstaff,'staff_type'=>(int)$staff['is_not_staff'],'primary'=>$acl->primaryId(),'all'=>$value==='1','customer_view'=>$acl->can($issuer,$actor,'customers','view')];
    }
    /** Parameterized ownership predicate precedes all private contact hydration. No email-to-company inference. */
    private function query(int $issuer,int $actor,array $frame,string $q,$selected,$client):array
    {
        $where='c.active=1 AND t.active=1 AND (EXISTS (SELECT 1 FROM '.$this->p.'issuers_clients o WHERE o.client_id=c.userid AND o.owner_issuer_id=?)';$params=[$issuer];
        if($frame['primary']===$issuer)$where.=' OR NOT EXISTS (SELECT 1 FROM '.$this->p.'issuers_clients o WHERE o.client_id=c.userid)';$where.=')';
        if(!$frame['all']&&!$frame['customer_view']){
            $where.=' AND (EXISTS (SELECT 1 FROM '.$this->p.'company_customer_admins a JOIN '.$this->p.'issuers_clients o ON o.client_id=a.client_id AND o.owner_issuer_id=a.issuer_id WHERE a.client_id=c.userid AND a.issuer_id=? AND a.staff_id=?)';array_push($params,$issuer,$actor);
            if($frame['primary']===$issuer){$where.=' OR (NOT EXISTS (SELECT 1 FROM '.$this->p.'issuers_clients o WHERE o.client_id=c.userid) AND EXISTS (SELECT 1 FROM '.$this->p.'customer_admins a WHERE a.customer_id=c.userid AND a.staff_id=?))';$params[]=$actor;}$where.=')';
        }
        if($selected!==null){$where.=' AND t.id=?';$params[]=$selected;}
        if($client!==null){$where.=' AND t.userid=?';$params[]=$client;}
        if($q!==''&&$selected===null){$where.=" AND (CONCAT(t.firstname,' ',t.lastname) LIKE ? ESCAPE '!' OR t.email LIKE ? ESCAPE '!')";$needle='%'.str_replace(['!','%','_'],['!!','!%','!_'],$q).'%';array_push($params,$needle,$needle);}
        return IssuerStorage::query($this->db,'SELECT t.id,t.userid,t.firstname,t.lastname,t.email,t.ticket_emails FROM '.$this->p.'contacts t JOIN '.$this->p.'clients c ON c.userid=t.userid WHERE '.$where.' ORDER BY t.firstname,t.lastname,t.id LIMIT 51',$params)->result_array();
    }
    public function readInOwnedTransaction(int $issuer,int $actor,string $q='', $selected=null,$client=null):array
    {
        if(strlen($q)>200||preg_match('//u',$q)!==1||preg_match('/[\x00-\x1f\x7f]/',$q))throw new DomainException('native_ticket_contact_input_invalid');
        $selected=$selected===null||$selected===''?null:NativeCompanyResourceId::decode('contact',$selected)['source_id'];$client=$client===null||$client===''?null:NativeCompanyResourceId::decode('client',$client)['source_id'];
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$frame=$this->frame($issuer,$actor);$rows=$this->query($issuer,$actor,$frame,$q,$selected,$client);
        if($selected!==null&&count($rows)!==1)throw new DomainException('native_ticket_contact_denied');
        foreach($rows as$row)if(!in_array($row['ticket_emails']??null,[0,1,'0','1'],true))throw new DomainException('native_ticket_contact_policy_invalid');
        if($frame!==$this->frame($issuer,$actor)||$rows!==$this->query($issuer,$actor,$frame,$q,$selected,$client))throw new DomainException('native_ticket_contact_denied');return array_slice($rows,0,50);
    }
    public function options(int $issuer,int $actor,string $q='', $selected=null,$client=null):array
    {
        $this->frame($issuer,$actor);return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($issuer,$actor,$q,$selected,$client){return array_map(static fn($row)=>['id'=>NativeCompanyResourceId::encode('contact',(int)$row['id']),'name'=>trim($row['firstname'].' '.$row['lastname']),'subtext'=>$row['email']],$this->readInOwnedTransaction($issuer,$actor,$q,$selected,$client));});
    }
    /** Creation uses this inside its own transaction; returned email is registered data, not proof of mailbox possession. */
    public function getInOwnedTransaction(int $issuer,int $actor,$id,$client=null):array{return$this->readInOwnedTransaction($issuer,$actor,'',$id,$client)[0]??throw new DomainException('native_ticket_contact_denied');}
}
