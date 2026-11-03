<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyCustomerAccess.php';
require_once __DIR__.'/NativeCompanyCustomerAudit.php';
require_once __DIR__.'/CompanyContacts.php';
/** Full native contact fields, with issuer identity checked before every projection and mutation. */
final class CompanyNativeContacts
{
    private ?array $activeCreation=null;
    private function __clone(){}
    public function __serialize():array{throw new LogicException('native_creation_writer_runtime_only');}
    public function assertCreatedInOwned($db,string $p,int $issuer,int $actor,string $kind,int $id):void
    {
        if($db!==$this->db||$p!==$this->p||$kind!=='contact'||$this->activeCreation!==[$issuer,$actor,$id])throw new DomainException('native_creation_attestation_invalid');
        IssuerTransaction::assertOwned($db,$p,self::TABLES);
        $row=$this->rows('SELECT userid FROM '.$p.'contacts WHERE id=?',[$id])[0]??null;if(!$row)throw new DomainException('native_creation_attestation_invalid');$this->authorize($issuer,$actor,(int)$row['userid'],'create');
    }
    public const TEXT=['firstname'=>191,'lastname'=>191,'email'=>100,'title'=>100,'phonenumber'=>100,'direction'=>3];
    public const FLAGS=['is_primary','invoice_emails','estimate_emails','credit_note_emails','contract_emails','task_emails','project_emails','ticket_emails'];
    public const PERMISSIONS=[1=>'invoices',2=>'estimates',3=>'contracts',4=>'proposals',5=>'tickets',6=>'projects'];
    public const TABLES=['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','issuers_clients','issuers_audit','staff','clients','contacts','contact_permissions','company_portal_tokens','user_auto_login'];
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function authorize(int $issuer,int $actor,int $client,string $cap):void
    {
        if(!(new CompanyCustomerAccess($this->db,$this->p))->can($issuer,$actor,$client,['create'=>'create_contact','delete'=>'delete_contact'][$cap]??$cap)||!$this->rows('SELECT o.client_id FROM '.$this->p.'issuers_clients o JOIN '.$this->p.'clients c ON c.userid=o.client_id WHERE o.client_id=? AND o.owner_issuer_id=?'.($cap==='view'?'':' AND c.active=1'),[$client,$issuer]))throw new DomainException('company_client_access_denied');
    }
    private function raw(int $client,int $id,bool $lock=false):?array
    {
        return $this->rows('SELECT id,userid,'.implode(',',array_keys(self::TEXT)).','.implode(',',self::FLAGS).',active,datecreated,last_login,last_password_change,profile_image,password FROM '.$this->p.'contacts WHERE userid=? AND id=?'.($lock?' FOR UPDATE':''),[$client,$id])[0]??null;
    }
    private function permissions(int $contact):array{return array_map('intval',array_column($this->rows('SELECT permission_id FROM '.$this->p.'contact_permissions WHERE userid=? ORDER BY permission_id',[$contact]),'permission_id'));}
    private function revision(array $row,array $permissions):string{return hash('sha256',json_encode([$row,$permissions],JSON_THROW_ON_ERROR));}
    public function get(int $issuer,int $actor,int $client,int $id):array
    {
        $this->authorize($issuer,$actor,$client,'view');$row=$this->raw($client,$id);if(!$row)throw new DomainException('company_client_access_denied');
        $permissions=$this->permissions($id);$revision=$this->revision($row,$permissions);unset($row['password']);$this->authorize($issuer,$actor,$client,'view');
        return ['contact'=>$row,'permissions'=>$permissions,'revision'=>$revision];
    }
    public function metadata(int $issuer,int $actor,int $client,string $cap='view'):array
    {
        if(!in_array($cap,['view','create','edit'],true))throw new DomainException('company_client_access_denied');
        $this->authorize($issuer,$actor,$client,$cap);
        $row=$this->rows('SELECT company,country,registration_confirmed,active FROM '.$this->p.'clients WHERE userid=?',[$client])[0]??null;
        if(!$row)throw new DomainException('company_client_access_denied');
        $counts=$this->rows('SELECT COUNT(*) total,COALESCE(SUM(is_primary=1),0) primary_count FROM '.$this->p.'contacts WHERE userid=?',[$client])[0];
        $this->authorize($issuer,$actor,$client,$cap);return $row+array_map('intval',$counts);
    }
    public function emailAvailable(int $issuer,int $actor,int $client,string $email,?int $contact=null):bool
    {
        $this->authorize($issuer,$actor,$client,$contact===null?'create':'edit');
        if($contact!==null&&!$this->raw($client,$contact))throw new DomainException('company_client_access_denied');
        if(strlen($email)>100||!filter_var($email,FILTER_VALIDATE_EMAIL))return false;
        $available=!$this->rows('SELECT c.id FROM '.$this->p.'contacts c JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid WHERE o.owner_issuer_id=? AND LOWER(c.email)=? AND c.id<>?',[$issuer,strtolower(trim($email)),$contact??0]);
        $this->authorize($issuer,$actor,$client,$contact===null?'create':'edit');
        if($contact!==null&&!$this->raw($client,$contact))throw new DomainException('company_client_access_denied');
        return $available;
    }
    public function consentEnabled(int$issuer,int$actor,int$client):bool
    {
        $this->authorize($issuer,$actor,$client,'view');require_once __DIR__.'/CompanySettingsStorage.php';$store=new CompanySettingsStorage($this->db,$this->p);$enabled=$store->get($issuer,'enable_gdpr')==='1'&&$store->get($issuer,'gdpr_enable_consent_for_contacts')==='1';$this->authorize($issuer,$actor,$client,'view');return$enabled;
    }
    public function page(int $issuer,int $actor,int $client,array $input,bool $withCustomFields=false):array
    {
        $this->authorize($issuer,$actor,$client,'view');require_once __DIR__.'/NativeCompanyClients.php';
        $definitions=[];if($withCustomFields){require_once __DIR__.'/NativeCompanyCustomFields.php';$definitions=(new NativeCompanyCustomFields($this->db,$this->p))->definitionsForClientContacts($issuer,$actor,$client,['show_on_table'=>1]);}
        if(isset($input['order'])&&(!is_array($input['order'])||(isset($input['order'][0])&&!is_array($input['order'][0]))))throw new DomainException('company_client_invalid_page');
        $pageInput=$input;$pageInput['order'][0]['column']=0;$q=NativeCompanyClients::request($pageInput);
        $total=(int)$this->rows('SELECT COUNT(*) n FROM '.$this->p.'contacts WHERE userid=?',[$client])[0]['n'];
        $needle='%'.str_replace(['!','%','_'],['!!','!%','!_'],trim($q['search'])).'%';
        $where=" WHERE userid=? AND (firstname LIKE ? ESCAPE '!' OR lastname LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!')";$args=[$client,$needle,$needle,$needle];
        $count=(int)$this->rows('SELECT COUNT(*) n FROM '.$this->p.'contacts'.$where,$args)[0]['n'];
        $columns=['firstname','email','title','phonenumber','active','last_login'];$column=$input['order'][0]['column']??0;
        if((!is_int($column)&&!is_string($column))||!preg_match('/^(0|[1-9][0-9]{0,3})$/D',(string)$column)||(int)$column>=count($columns)+count($definitions))throw new DomainException('company_client_invalid_page');
        $orderArgs=[];
        if((int)$column<6)$order=$columns[(int)$column];else{
            $ref=NativeCompanyResourceId::decode('custom_field',$definitions[(int)$column-6]['id']);if($ref['source_kind']!=='company_custom_field')throw new DomainException('native_custom_field_denied');
            $order="(SELECT v.value FROM ".$this->p."company_custom_field_values v WHERE v.issuer_id=? AND v.field_source_kind='company_custom_field' AND v.field_id=? AND v.source_kind='contact' AND v.source_id=".$this->p."contacts.id)";$orderArgs=[$issuer,$ref['source_id']];
        }
        $rows=$this->rows('SELECT id,userid,firstname,lastname,email,title,phonenumber,active,last_login,is_primary,profile_image FROM '.$this->p.'contacts'.$where.' ORDER BY '.$order.' '.$q['direction'].',id ASC LIMIT '.$q['length'].' OFFSET '.$q['start'],array_merge($args,$orderArgs));
        foreach($rows as &$row){$raw=$this->raw($client,(int)$row['id']);$row['revision']=$this->revision($raw,$this->permissions((int)$row['id']));}unset($row);
        $this->authorize($issuer,$actor,$client,'view');return ['draw'=>$q['draw'],'recordsTotal'=>$total,'recordsFiltered'=>$count,'rows'=>$rows,'custom_fields'=>$definitions];
    }
    public function setActive(int $issuer,int $actor,int $client,int $id,bool $active,string $revision):void
    {
        $this->authorize($issuer,$actor,$client,'edit');
        IssuerTransaction::run($this->db,$this->p,self::TABLES,function()use($issuer,$actor,$client,$id,$active,$revision):void{
            $this->authorize($issuer,$actor,$client,'edit');$old=$this->raw($client,$id,true);
            if(!$old)throw new DomainException('company_client_access_denied');
            if(!hash_equals($this->revision($old,$this->permissions($id)),$revision))throw new DomainException('company_client_stale');
            $registered=$this->rows('SELECT registration_confirmed FROM '.$this->p.'clients WHERE userid=?',[$client])[0]??[];
            if((int)($registered['registration_confirmed']??0)!==1)throw new DomainException('company_contact_registration_required');
            if((int)$old['active']===(int)$active)return;
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'contacts SET active=? WHERE id=? AND userid=?',[(int)$active,$id,$client]);
            IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'company_portal_tokens WHERE contact_id=?',[$id]);
            IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'user_auto_login WHERE user_id=? AND staff=0',[$id]);
            if((int)($this->raw($client,$id)['active']??-1)!==(int)$active)throw new RuntimeException('issuer_contact_write_unconfirmed');
            NativeCompanyCustomerAudit::record($this->db,$this->p,$issuer,$actor,$id,$active?'native_customer_contact_enabled':'native_customer_contact_disabled');
            $this->authorize($issuer,$actor,$client,'edit');
        });
    }
    /** Delete only an unreferenced owned contact. Historical authorship is never cascaded or reassigned. */
    public function delete(int$issuer,int$actor,int$client,int$id,string$revision):void
    {
        require_once __DIR__.'/NativeCompanyContactRetention.php';
        $refs=NativeCompanyContactRetention::existing($this->db,$this->p);$optional=[];
        foreach(['company_native_contact_images','company_custom_field_values','user_meta']as$table)if(NativeCompanyContactRetention::present($this->db,$this->p,$table))$optional[]=$table;
        IssuerTransaction::run($this->db,$this->p,array_merge(self::TABLES,$refs,$optional),function()use($issuer,$actor,$client,$id,$revision,$refs,$optional):void{
            $this->authorize($issuer,$actor,$client,'delete');$old=$this->raw($client,$id,true);if(!$old)throw new DomainException('company_client_access_denied');
            if(!hash_equals($this->revision($old,$this->permissions($id)),$revision))throw new DomainException('company_client_stale');
            if(NativeCompanyContactRetention::existing($this->db,$this->p)!==$refs)throw new RuntimeException('issuer_contact_schema_changed');
            if((int)$old['is_primary']===1&&(int)$this->rows('SELECT COUNT(*) n FROM '.$this->p.'contacts WHERE userid=?',[$client])[0]['n']>1)throw new DomainException('company_contact_primary_required');
            NativeCompanyContactRetention::assertUnreferenced($this->db,$this->p,$id);
            if($old['profile_image']!==null&&$old['profile_image']!==''&&!str_starts_with((string)$old['profile_image'],'native:'))throw new DomainException('company_contact_legacy_image_retained');
            foreach($optional as$table){
                if($table==='company_native_contact_images'&&$this->rows('SELECT contact_id FROM '.$this->p.$table.' WHERE contact_id=? AND (issuer_id<>? OR client_id<>?)',[$id,$issuer,$client]))throw new DomainException('company_contact_history_retained');
                if($table==='company_custom_field_values'&&$this->rows("SELECT source_id FROM ".$this->p.$table." WHERE source_kind='contact' AND source_id=? AND issuer_id<>?",[$id,$issuer]))throw new DomainException('company_contact_history_retained');
                if($table==='company_native_contact_images')IssuerStorage::query($this->db,'DELETE FROM '.$this->p.$table.' WHERE issuer_id=? AND client_id=? AND contact_id=?',[$issuer,$client,$id]);
                elseif($table==='company_custom_field_values')IssuerStorage::query($this->db,"DELETE FROM ".$this->p.$table." WHERE issuer_id=? AND source_kind='contact' AND source_id=?",[$issuer,$id]);
                else IssuerStorage::query($this->db,'DELETE FROM '.$this->p.$table.' WHERE contact_id=?',[$id]);
            }
            IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'company_portal_tokens WHERE contact_id=?',[$id]);
            IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'user_auto_login WHERE user_id=? AND staff=0',[$id]);
            IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'contact_permissions WHERE userid=?',[$id]);
            IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'contacts WHERE id=? AND userid=?',[$id,$client]);
            if($this->raw($client,$id))throw new RuntimeException('issuer_contact_write_unconfirmed');
            NativeCompanyCustomerAudit::record($this->db,$this->p,$issuer,$actor,$id,'native_customer_contact_deleted');$this->authorize($issuer,$actor,$client,'delete');
        });
    }
    public static function normalize(array $input):array
    {
        if(array_diff(array_keys($input),array_merge(array_keys(self::TEXT),self::FLAGS,['permissions','password'])))throw new DomainException('company_contact_invalid_input');
        $out=[];
        foreach(self::TEXT as $key=>$limit){$value=$input[$key]??'';if(!is_string($value)||strlen($value)>$limit||preg_match('//u',$value)!==1||preg_match('/[\x00-\x1f\x7f]/',$value))throw new DomainException('company_contact_invalid_input');$out[$key]=trim($value);}
        $out['email']=strtolower($out['email']);if($out['firstname']===''||!filter_var($out['email'],FILTER_VALIDATE_EMAIL)||!in_array($out['direction'],['','ltr','rtl'],true))throw new DomainException('company_contact_invalid_input');
        foreach(self::FLAGS as $key){$value=$input[$key]??0;if(!in_array($value,[0,1,'0','1','on',$key],true))throw new DomainException('company_contact_invalid_input');$out[$key]=in_array($value,[0,'0'],true)?0:1;}
        $permissions=$input['permissions']??[];if(!is_array($permissions)||count($permissions)>6)throw new DomainException('company_contact_invalid_input');
        $permissions=array_values(array_unique(array_map([IssuerPolicy::class,'id'],$permissions)));sort($permissions,SORT_NUMERIC);if(array_diff($permissions,array_keys(self::PERMISSIONS)))throw new DomainException('company_contact_invalid_input');
        foreach(['invoice_emails'=>1,'credit_note_emails'=>1,'estimate_emails'=>2,'contract_emails'=>3,'ticket_emails'=>5,'task_emails'=>6,'project_emails'=>6] as $flag=>$permission)if($out[$flag]===1&&!in_array($permission,$permissions,true))throw new DomainException('company_contact_permission_denied');
        $password=$input['password']??'';if(!is_string($password)||($password!==''&&(strlen($password)<12||strlen($password)>4096||str_contains($password,"\0"))))throw new DomainException('company_contact_invalid_input');
        return [$out,$permissions,$password];
    }
    public function save(int $issuer,int $actor,int $client,array $input,callable $hashPassword,?int $id=null,?string $revision=null,?callable $afterWrite=null,array $participantTables=[]):int
    {
        [$values,$permissions,$password]=self::normalize($input);
        $this->authorize($issuer,$actor,$client,$id===null?'create':'edit');
        $hash=null;if($password!==''){$hash=$hashPassword($password);if(!is_string($hash)||strlen($hash)<20||strlen($hash)>255)throw new RuntimeException('issuer_portal_hash_failed');}
        return IssuerTransaction::run($this->db,$this->p,array_values(array_unique(array_merge(self::TABLES,$participantTables))),function()use($issuer,$actor,$client,$values,$permissions,$hash,$id,$revision,$afterWrite):int{
            $cap=$id===null?'create':'edit';$this->authorize($issuer,$actor,$client,$cap);$acl=new IssuerResourceAccess($this->db,$this->p);$acl->lockFinancialIdentity($issuer,$actor);
            foreach($permissions as $permission)if(!$acl->can($issuer,$actor,self::PERMISSIONS[$permission],'view'))throw new DomainException('company_contact_permission_denied');
            $creating=$id===null;$old=$id===null?null:$this->raw($client,$id,true);
            if($id!==null&&(!$old||$revision===null||!hash_equals($this->revision($old,$this->permissions($id)),$revision)))throw new DomainException($old?'company_client_stale':'company_client_access_denied');
            if($this->rows('SELECT p.id FROM '.$this->p.'contacts p JOIN '.$this->p.'issuers_clients o ON o.client_id=p.userid WHERE o.owner_issuer_id=? AND LOWER(p.email)=? AND p.id<>? FOR UPDATE',[$issuer,$values['email'],$id??0]))throw new DomainException('company_contact_email_conflict');
            if($values['is_primary']===1)IssuerStorage::query($this->db,'UPDATE '.$this->p.'contacts SET is_primary=0 WHERE userid=?',[$client]);
            elseif($old&&(int)$old['is_primary']===1)throw new DomainException('company_contact_primary_required');
            if($id===null){
                $data=$values+['userid'=>$client,'datecreated'=>date('Y-m-d H:i:s'),'active'=>1];if($hash!==null)$data['password']=$hash;
                IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'contacts('.implode(',',array_keys($data)).') VALUES('.implode(',',array_fill(0,count($data),'?')).')',array_values($data));$id=(int)$this->db->insert_id();if($id<1)throw new RuntimeException('issuer_contact_write_unconfirmed');
            }else{
                $data=$values;if($hash!==null){$data['password']=$hash;$data['last_password_change']=date('Y-m-d H:i:s');$data['new_pass_key']=null;$data['new_pass_key_requested']=null;}
                $set=array_map(static fn($key)=>$key.'=?',array_keys($data));IssuerStorage::query($this->db,'UPDATE '.$this->p.'contacts SET '.implode(',',$set).' WHERE id=? AND userid=?',[...array_values($data),$id,$client]);
            }
            IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'contact_permissions WHERE userid=?',[$id]);
            foreach($permissions as $permission)IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'contact_permissions(userid,permission_id) VALUES(?,?)',[$id,$permission]);
            // Email/password changes invalidate all outstanding recovery/autologin material.
            if($hash!==null||($old&&$old['email']!==$values['email'])){
                IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'company_portal_tokens WHERE contact_id=?',[$id]);
                IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'user_auto_login WHERE user_id=? AND staff=0',[$id]);
            }
            $actual=$this->raw($client,$id);foreach($values as $key=>$value)if((string)($actual[$key]??'')!==(string)$value)throw new RuntimeException('issuer_contact_write_unconfirmed');
            if(($hash!==null&&$actual['password']!==$hash)||$this->permissions($id)!==$permissions)throw new RuntimeException('issuer_contact_write_unconfirmed');
            NativeCompanyCustomerAudit::record($this->db,$this->p,$issuer,$actor,$id,'native_customer_contact_saved');
            if($afterWrite!==null){$this->activeCreation=$creating?[$issuer,$actor,$id]:null;try{$afterWrite($id,$cap,$this);}finally{$this->activeCreation=null;}}
            $this->authorize($issuer,$actor,$client,$cap);foreach($permissions as $permission)if(!$acl->can($issuer,$actor,self::PERMISSIONS[$permission],'view'))throw new DomainException('company_contact_permission_denied');return $id;
        });
    }
}
