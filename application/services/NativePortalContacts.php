<?php
declare(strict_types=1);
require_once __DIR__.'/NativePortalProfile.php';
require_once __DIR__.'/NativeCompanyContactPreferences.php';
require_once __DIR__.'/NativeCompanyContactRetention.php';
/** The original Contacts actions use the primary contact's own client, never a staff identity. */
final class NativePortalContacts
{
    private ?int $mutating=null;
    private function __clone(){}
    public function __serialize():array{throw new LogicException('native_portal_contact_runtime_only');}
    public function __debugInfo():array{return[];}
    public function __construct(private $db,private string $p,private string $secret,private int $issuer,private array $binding,private ?int $target=null)
    {if(!preg_match('/^[A-Za-z0-9_]+$/D',$p)||strlen($secret)<32||$issuer<1||($target!==null&&$target<1))throw new DomainException('native_portal_contacts_denied');}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public function tables():array{return array_values(array_unique(array_merge((new NativePortalProfile($this->db,$this->p,$this->secret,$this->issuer,$this->binding))->tables(),['issuers_audit'])));}
    public function manager():array
    {
        $identity=(new CompanyPortal($this->db,$this->p,$this->secret))->invoiceIdentity($this->binding,$this->issuer,true);
        if(!$identity)throw new DomainException('native_portal_contacts_denied');
        $row=$this->rows('SELECT is_primary,active FROM '.$this->p.'contacts WHERE id=? AND userid=?',[$identity['contact_id'],$identity['client_id']])[0]??[];
        $store=new CompanySettingsStorage($this->db,$this->p);
        if((int)($row['is_primary']??0)!==1||(int)($row['active']??0)!==1||$store->get($this->issuer,'allow_primary_contact_to_manage_other_contacts')!=='1')throw new DomainException('native_portal_contacts_denied');
        return $identity;
    }
    public function assertCreationAuthority($db,string $p,int $issuer):void
    {if($db!==$this->db||$p!==$this->p||$issuer!==$this->issuer||$this->target!==null)throw new DomainException('native_portal_contacts_denied');$this->manager();}
    public function reference($db,string $p,int $issuer,string $fieldto,bool $write=false):array
    {
        if($db!==$this->db||$p!==$this->p||$issuer!==$this->issuer||!in_array($fieldto,['customers','contacts'],true)||($write&&$fieldto!=='contacts'))throw new DomainException('native_portal_contacts_denied');
        $id=$this->manager();if($fieldto==='customers')return ['source_kind'=>'client','source_id'=>$id['client_id']];
        if($this->target===null||$this->target===$id['contact_id'])throw new DomainException('native_portal_contacts_denied');
        $row=$this->rows('SELECT id,is_primary FROM '.$p.'contacts WHERE id=? AND userid=?',[$this->target,$id['client_id']])[0]??null;
        if(!$row||(int)$row['is_primary']!==0)throw new DomainException('native_portal_contacts_denied');
        $again=$this->manager();if($again!==$id)throw new DomainException('native_portal_contacts_denied');return ['source_kind'=>'contact','source_id'=>$this->target];
    }
    public function assertMutationInOwned($db,string $p,int $issuer,string $fieldto):void
    {
        if($fieldto!=='contacts'||$this->target===null||$this->mutating!==$this->target)throw new DomainException('native_portal_contacts_denied');
        IssuerTransaction::assertOwned($db,$p,$this->tables());$this->reference($db,$p,$issuer,$fieldto,true);
    }
    public function actorContactInOwned($db,string $p,int $issuer):int{$this->assertMutationInOwned($db,$p,$issuer,'contacts');return(int)$this->manager()['contact_id'];}
    public function mailAuthorityInOwned($db,string$p,int$issuer):array
    {$this->assertMutationInOwned($db,$p,$issuer,'contacts');return $this->binding;}
    private function raw():array
    {
        $ref=$this->reference($this->db,$this->p,$this->issuer,'contacts');$id=$this->manager();$row=$this->rows('SELECT * FROM '.$this->p.'contacts WHERE id=? AND userid=?',[$ref['source_id'],$id['client_id']])[0]??null;if(!$row)throw new DomainException('native_portal_contacts_denied');return$row;
    }
    public function read():array
    {
        $row=$this->raw();$cf=new NativeCompanyCustomFields($this->db,$this->p);$defs=$cf->definitionsForPortal($this->issuer,'contacts',$this);$values=$cf->valuesForPortal($this->issuer,'contacts',$this);
        $permissions=array_map('intval',array_column($this->rows('SELECT permission_id FROM '.$this->p.'contact_permissions WHERE userid=? ORDER BY permission_id',[$row['id']]),'permission_id'));
        $revision=hash_hmac('sha256',json_encode([$this->issuer,$row,$permissions,$defs,$values],JSON_THROW_ON_ERROR),$this->secret);
        $safe=array_intersect_key($row,array_flip(['id','userid','firstname','lastname','email','phonenumber','title','direction','profile_image','last_login','last_password_change','is_primary','active','invoice_emails','credit_note_emails','estimate_emails','contract_emails','ticket_emails','project_emails','task_emails']));
        $this->reference($this->db,$this->p,$this->issuer,'contacts');return ['contact'=>$safe,'permissions'=>$permissions,'custom_fields'=>$defs,'custom_field_values'=>$values,'revision'=>$revision];
    }
    public function page():array
    {
        $id=$this->manager();$out=[];foreach($this->rows('SELECT id FROM '.$this->p.'contacts WHERE userid=? AND active=1 AND is_primary=0 AND id<>? ORDER BY firstname,lastname,id',[$id['client_id'],$id['contact_id']])as$r)$out[]=(new self($this->db,$this->p,$this->secret,$this->issuer,$this->binding,(int)$r['id']))->read();$this->manager();return$out;
    }
    public function emailAvailable(string $email):bool
    {
        $this->manager();if($this->target!==null)$this->reference($this->db,$this->p,$this->issuer,'contacts');
        if(strlen($email)>100||!filter_var($email,FILTER_VALIDATE_EMAIL))return false;
        $found=$this->rows('SELECT c.id FROM '.$this->p.'contacts c LEFT JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid LEFT JOIN '.$this->p.'issuers_installation_identity i ON i.id=1 WHERE LOWER(c.email)=? AND c.id<>? AND (o.owner_issuer_id=? OR (o.client_id IS NULL AND i.primary_issuer_id=?))',[strtolower(trim($email)),$this->target??0,$this->issuer,$this->issuer]);
        $this->manager();if($this->target!==null)$this->reference($this->db,$this->p,$this->issuer,'contacts');return!$found;
    }
    private function defaultPermissions():array{$store=new CompanySettingsStorage($this->db,$this->p);return NativeCompanyContactPreferences::permissions($store->exists($this->issuer,NativeCompanyContactPreferences::PERMISSIONS)?$store->get($this->issuer,NativeCompanyContactPreferences::PERMISSIONS):NativeCompanyContactPreferences::DEFAULT_PERMISSIONS);}
    public function creation():array
    {
        $this->assertCreationAuthority($this->db,$this->p,$this->issuer);$store=new CompanySettingsStorage($this->db,$this->p);$encoded=$store->exists($this->issuer,NativeCompanyContactPreferences::PERMISSIONS)?$store->get($this->issuer,NativeCompanyContactPreferences::PERMISSIONS):NativeCompanyContactPreferences::DEFAULT_PERMISSIONS;
        $permissions=NativeCompanyContactPreferences::permissions($encoded);$defs=(new NativeCompanyCustomFields($this->db,$this->p))->definitionsForManagedContactCreation($this->issuer,$this);$this->assertCreationAuthority($this->db,$this->p,$this->issuer);return['permissions'=>$permissions,'custom_fields'=>$defs,'custom_field_values'=>[]];
    }
    private function audit(int $target,string $event):void
    {
        $actor=(int)$this->manager()['contact_id'];$meta=json_encode(['actor_kind'=>'contact','contact_id'=>$actor,'nonce'=>bin2hex(random_bytes(32))],JSON_THROW_ON_ERROR);
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,metadata_json,created_at) VALUES(0,?,?,?,?,?)',[$event,$this->issuer,$target,$meta,gmdate('Y-m-d H:i:s')]);
        if(count($this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE event=? AND issuer_id=? AND subject_id=? AND metadata_json=?',[$event,$this->issuer,$target,$meta]))!==1)throw new RuntimeException('issuer_portal_contacts_audit_unconfirmed');
    }
    /** The callback is an internal transactional mail/verification participant, never supplied by a request. */
    public function save(?string $revision,array $input,callable $hash,callable $verify,callable $lifecycle,array $participantTables=[],?string $avatarBytes=null):array
    {
        $flags=['invoice_emails'=>1,'credit_note_emails'=>1,'estimate_emails'=>2,'contract_emails'=>3,'ticket_emails'=>5,'project_emails'=>6,'task_emails'=>6];
        $limits=['firstname'=>191,'lastname'=>191,'email'=>100,'phonenumber'=>100,'title'=>100,'direction'=>3];
        if(array_diff(array_keys($input),array_merge(array_keys($limits),array_keys($flags),['password','custom_fields','send_set_password_email','donotsendwelcomeemail'])))throw new DomainException('native_portal_contacts_invalid');
        $values=[];foreach($limits as$key=>$max){$v=$input[$key]??'';if(!is_string($v)||strlen($v)>$max||preg_match('//u',$v)!==1||preg_match('/[\x00-\x1f\x7f]/',$v))throw new DomainException('native_portal_contacts_invalid');$values[$key]=trim($v);}
        $values['email']=strtolower($values['email']);if($values['firstname']===''||$values['lastname']===''||!filter_var($values['email'],FILTER_VALIDATE_EMAIL)||!in_array($values['direction'],['','ltr','rtl'],true))throw new DomainException('native_portal_contacts_invalid');
        foreach(array_merge(array_keys($flags),['send_set_password_email','donotsendwelcomeemail'])as$key)if(isset($input[$key])&&!in_array($input[$key],['on','1',1,true,'0',0,false],true))throw new DomainException('native_portal_contacts_invalid');
        $custom=$input['custom_fields']??[];$password=$input['password']??'';if(!is_array($custom)||!is_string($password)||strlen($password)>4096||str_contains($password,"\0"))throw new DomainException('native_portal_contacts_invalid');
        $creation=$this->target===null;if($creation&&($revision!==null||($password===''&&empty($input['send_set_password_email']))))throw new DomainException('native_portal_contacts_invalid');
        $encoded=null;if($password!==''){if(strlen($password)<8)throw new DomainException('native_portal_password_invalid');$encoded=$hash($password);if(!is_string($encoded)||strlen($encoded)>255||!$verify($password,$encoded))throw new RuntimeException('native_portal_password_hash_invalid');}
        elseif($creation){$random=bin2hex(random_bytes(32));$encoded=$hash($random);if(!is_string($encoded)||strlen($encoded)>255||!$verify($random,$encoded))throw new RuntimeException('native_portal_password_hash_invalid');}
        $avatar=$avatarBytes===null?null:NativeCompanyContactImage::normalize($avatarBytes);$original=$this->target;
        try{return IssuerTransaction::run($this->db,$this->p,array_values(array_unique(array_merge($this->tables(),$participantTables))),function()use($revision,$input,$values,$flags,$custom,$encoded,$creation,$lifecycle,$avatar){
            $manager=$this->manager();$client=(int)$manager['client_id'];$p=$this->p;$state=$creation?$this->creation():$this->read();
            if(!$creation&&($revision===null||!hash_equals($state['revision'],$revision)))throw new DomainException('native_portal_contacts_stale');
            if(!$this->emailAvailable($values['email']))throw new DomainException('native_portal_contacts_email_conflict');
            $emailChanged=!$creation&&$state['contact']['email']!==$values['email'];$data=$values;
            foreach($flags as$key=>$permission)$data[$key]=in_array($permission,$state['permissions'],true)?(!empty($input[$key])?1:0):($creation?0:(int)$state['contact'][$key]);
            if($encoded!==null){$data['password']=$encoded;if(!$creation){$data['last_password_change']=gmdate('Y-m-d H:i:s');$data['new_pass_key']=null;$data['new_pass_key_requested']=null;}}
            if($creation||$emailChanged){$data['email_verified_at']=null;$data['email_verification_key']=null;$data['email_verification_sent_at']=null;}
            if($creation){
                $data+=['userid'=>$client,'active'=>1,'is_primary'=>0,'datecreated'=>gmdate('Y-m-d H:i:s')];
                IssuerStorage::query($this->db,'INSERT INTO '.$p.'contacts('.implode(',',array_keys($data)).') VALUES('.implode(',',array_fill(0,count($data),'?')).')',array_values($data));
                $pdo=$this->db->pdo??$this->db->conn_id??null;$affected=method_exists($this->db,'affected_rows')?(int)$this->db->affected_rows():($pdo instanceof PDO&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?(int)$pdo->query('SELECT changes()')->fetchColumn():-1);if($affected!==1)throw new RuntimeException('issuer_portal_contacts_unconfirmed');$this->target=(int)$this->db->insert_id();if($this->target<1)throw new RuntimeException('issuer_portal_contacts_unconfirmed');
                foreach($state['permissions']as$permission)IssuerStorage::query($this->db,'INSERT INTO '.$p.'contact_permissions(userid,permission_id) VALUES(?,?)',[$this->target,$permission]);
            }else IssuerStorage::query($this->db,'UPDATE '.$p.'contacts SET '.implode(',',array_map(fn($key)=>$key.'=?',array_keys($data))).' WHERE id=? AND userid=?',[...array_values($data),$this->target,$client]);
            $raw=$this->raw();foreach($data as$key=>$value)if(!array_key_exists($key,$raw)||($value===null?$raw[$key]!==null:(string)$raw[$key] !==(string)$value))throw new RuntimeException('issuer_portal_contacts_unconfirmed');
            $actualPermissions=array_map('intval',array_column($this->rows('SELECT permission_id FROM '.$p.'contact_permissions WHERE userid=? ORDER BY permission_id',[$this->target]),'permission_id'));$expected=$state['permissions'];sort($expected);if($actualPermissions!==$expected)throw new RuntimeException('issuer_portal_contacts_unconfirmed');
            $this->mutating=$this->target;(new NativeCompanyCustomFields($this->db,$p))->savePortalInOwnedTransaction($this->issuer,'contacts',$custom,$this);
            if($avatar!==null)(new NativeCompanyContactImage($this->db,$p))->writePortalInOwned($this->issuer,$this,$avatar);
            if($emailChanged||$encoded!==null){IssuerStorage::query($this->db,'DELETE FROM '.$p.'company_portal_tokens WHERE contact_id=? AND issuer_id=?',[$this->target,$this->issuer]);IssuerStorage::query($this->db,'DELETE FROM '.$p.'user_auto_login WHERE user_id=? AND staff=0',[$this->target]);IssuerStorage::query($this->db,'UPDATE '.$p.'company_native_portal_remember SET revoked=1,revision=revision+1 WHERE contact_id=? AND issuer_id=? AND revoked=0',[$this->target,$this->issuer]);}
            $lifecycle($this->issuer,$this->target,$this,['created'=>$creation,'email_changed'=>$emailChanged,'set_password'=>!empty($input['send_set_password_email']),'welcome'=>$creation&&empty($input['donotsendwelcomeemail'])]);
            $this->audit($this->target,$creation?'native_portal_contact_created':'native_portal_contact_updated');$this->manager();if($creation){$latest=$this->defaultPermissions();sort($latest);if($latest!==$expected)throw new DomainException('native_portal_contacts_stale');}return$this->read();
        });}catch(Throwable $e){$this->target=$original;throw$e;}finally{$this->mutating=null;}
    }
    public function avatar():?array{return(new NativeCompanyContactImage($this->db,$this->p))->readPortal($this->issuer,$this);}
    public function removeAvatar(string $revision):array
    {
        try{return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($revision){$state=$this->read();if(!hash_equals($state['revision'],$revision))throw new DomainException('native_portal_contacts_stale');$this->mutating=$this->target;(new NativeCompanyContactImage($this->db,$this->p))->removePortalInOwned($this->issuer,$this);$this->audit($this->target,'native_portal_contact_avatar_removed');return$this->read();});}finally{$this->mutating=null;}
    }

    public function delete(string $revision):void
    {
        $refs=NativeCompanyContactRetention::existing($this->db,$this->p);$optional=[];foreach(['company_native_contact_images','company_custom_field_values','user_meta']as$table)if(NativeCompanyContactRetention::present($this->db,$this->p,$table))$optional[]=$table;
        IssuerTransaction::run($this->db,$this->p,array_values(array_unique(array_merge($this->tables(),$refs,$optional))),function()use($revision,$refs,$optional){
            $state=$this->read();if(!hash_equals($state['revision'],$revision))throw new DomainException('native_portal_contacts_stale');$id=(int)$state['contact']['id'];$client=(int)$state['contact']['userid'];
            if(NativeCompanyContactRetention::existing($this->db,$this->p)!==$refs)throw new RuntimeException('issuer_contact_schema_changed');NativeCompanyContactRetention::assertUnreferenced($this->db,$this->p,$id);
            if(!empty($state['contact']['profile_image'])&&!str_starts_with($state['contact']['profile_image'],'native:'))throw new DomainException('company_contact_legacy_image_retained');
            foreach($optional as$table){
                if($table==='company_native_contact_images'){
                    if($this->rows('SELECT contact_id FROM '.$this->p.$table.' WHERE contact_id=? AND (issuer_id<>? OR client_id<>?)',[$id,$this->issuer,$client]))throw new DomainException('company_contact_history_retained');
                    IssuerStorage::query($this->db,'DELETE FROM '.$this->p.$table.' WHERE contact_id=? AND issuer_id=? AND client_id=?',[$id,$this->issuer,$client]);
                }elseif($table==='company_custom_field_values'){
                    if($this->rows("SELECT source_id FROM ".$this->p.$table." WHERE source_kind='contact' AND source_id=? AND issuer_id<>?",[$id,$this->issuer]))throw new DomainException('company_contact_history_retained');
                    IssuerStorage::query($this->db,"DELETE FROM ".$this->p.$table." WHERE source_kind='contact' AND source_id=? AND issuer_id=?",[$id,$this->issuer]);
                }else IssuerStorage::query($this->db,'DELETE FROM '.$this->p.$table.' WHERE contact_id=?',[$id]);
            }
            $this->audit($id,'native_portal_contact_deleted');$this->reference($this->db,$this->p,$this->issuer,'contacts',true);
            IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'company_portal_tokens WHERE contact_id=? AND issuer_id=?',[$id,$this->issuer]);IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'user_auto_login WHERE user_id=? AND staff=0',[$id]);IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'contact_permissions WHERE userid=?',[$id]);IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'contacts WHERE id=? AND userid=? AND is_primary=0',[$id,$client]);
            if($this->rows('SELECT id FROM '.$this->p.'contacts WHERE id=?',[$id]))throw new RuntimeException('issuer_portal_contacts_unconfirmed');$this->manager();
        });
    }

}
