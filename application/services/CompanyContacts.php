<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Contacts belong only to the private customer owner; identity-sharing grants never expose them. */
final class CompanyContacts
{
    public function __construct(private $db,private string $prefix)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('Invalid table prefix');
    }
    private function rows(string $sql,array $params=[]):array{return IssuerStorage::query($this->db,$sql,$params)->result_array();}
    private function authorize(int $issuer,int $actor,int $client,string $capability):void
    {
        if(!(new IssuerResourceAccess($this->db,$this->prefix))->can($issuer,$actor,'customers',$capability)||!$this->rows('SELECT o.client_id FROM '.$this->prefix.'issuers_clients o JOIN '.$this->prefix.'clients c ON c.userid=o.client_id WHERE o.client_id=? AND o.owner_issuer_id=? AND c.active=1',[$client,$issuer]))throw new DomainException('company_client_access_denied');
    }
    public function page($issuer,$actor,$client):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$client=IssuerPolicy::id($client);$this->authorize($issuer,$actor,$client,'view');
        $rows=$this->rows('SELECT id,firstname,lastname,email,active FROM '.$this->prefix.'contacts WHERE userid=? ORDER BY id LIMIT 101',[$client]);
        if(count($rows)>100)throw new DomainException('company_contact_page_limit');
        $this->authorize($issuer,$actor,$client,'view');
        foreach($rows as &$row){$row['id']=(int)$row['id'];$row['active']=(int)$row['active'];$row['revision']=hash('sha256',json_encode($row,JSON_THROW_ON_ERROR));}
        return $rows;
    }
    public function save($issuer,$actor,$client,array $input,$contact=null,?string $revision=null):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$client=IssuerPolicy::id($client);$contact=$contact===null?null:IssuerPolicy::id($contact);
        if(array_diff(array_keys($input),['firstname','lastname','email']))throw new DomainException('company_contact_invalid_input');
        foreach(['firstname','lastname','email'] as $field){if(!isset($input[$field])||!is_string($input[$field])||strlen($input[$field])>($field==='email'?100:191)||preg_match('/[\x00-\x1f]/',$input[$field]))throw new DomainException('company_contact_invalid_input');$input[$field]=trim($input[$field]);}
        $input['email']=strtolower($input['email']);
        if($input['firstname']===''||!filter_var($input['email'],FILTER_VALIDATE_EMAIL))throw new DomainException('company_contact_invalid_input');
        return IssuerTransaction::run($this->db,$this->prefix,['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','issuers_clients','issuers_audit','staff','clients','contacts','company_portal_tokens'],function()use($issuer,$actor,$client,$input,$contact,$revision):int{
            $this->authorize($issuer,$actor,$client,'edit');
            $duplicate=$this->rows('SELECT p.id,p.userid,p.firstname,p.lastname FROM '.$this->prefix.'contacts p LEFT JOIN '.$this->prefix.'issuers_clients o ON o.client_id=p.userid LEFT JOIN '.$this->prefix.'issuers_installation_identity i ON i.id=1 WHERE (CASE WHEN o.client_id IS NULL THEN i.primary_issuer_id ELSE o.owner_issuer_id END)=CAST(? AS SIGNED) AND LOWER(p.email)=? AND p.id<>? FOR UPDATE',[$issuer,$input['email'],$contact??0]);
            if($duplicate){
                if($contact===null&&count($duplicate)===1&&(int)$duplicate[0]['userid']===$client&&$duplicate[0]['firstname']===$input['firstname']&&$duplicate[0]['lastname']===$input['lastname'])return (int)$duplicate[0]['id'];
                throw new DomainException('company_contact_email_conflict');
            }
            if($contact!==null){
                $row=$this->rows('SELECT id,firstname,lastname,email,active FROM '.$this->prefix.'contacts WHERE id=? AND userid=? FOR UPDATE',[$contact,$client])[0]??null;
                if(!$row)throw new DomainException('company_client_access_denied');
                $row['id']=(int)$row['id'];$row['active']=(int)$row['active'];
                if($revision===null||!hash_equals(hash('sha256',json_encode($row,JSON_THROW_ON_ERROR)),$revision))throw new DomainException('company_client_stale');
                IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'contacts SET firstname=?,lastname=?,email=? WHERE id=? AND userid=?',[$input['firstname'],$input['lastname'],$input['email'],$contact,$client]);
                if($row['email']!==$input['email'])IssuerStorage::query($this->db,'DELETE FROM '.$this->prefix.'company_portal_tokens WHERE contact_id=?',[$contact]);
            }else{
                IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'contacts(userid,firstname,lastname,email,phonenumber,datecreated,active,is_primary,invoice_emails,estimate_emails,credit_note_emails,contract_emails,task_emails,project_emails,ticket_emails) VALUES(?,?,?,?,?,?,1,0,0,0,0,0,0,0,0)',[$client,$input['firstname'],$input['lastname'],$input['email'],'',date('Y-m-d H:i:s')]);
                $contact=(int)$this->db->insert_id();if($contact<1)throw new RuntimeException('issuer_contact_write_unconfirmed');
            }
            $row=$this->rows('SELECT firstname,lastname,email FROM '.$this->prefix.'contacts WHERE id=? AND userid=?',[$contact,$client])[0]??null;
            foreach($input as $name=>$value)if(($row[$name]??null)!==$value)throw new RuntimeException('issuer_contact_write_unconfirmed');
            IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,?,?)',[$actor,'company_contact_saved',$issuer,$contact,date('Y-m-d H:i:s')]);
            $this->authorize($issuer,$actor,$client,'edit');return $contact;
        });
    }
}
