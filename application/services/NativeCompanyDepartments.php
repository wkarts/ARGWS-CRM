<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyDepartmentSchema.php';
require_once __DIR__.'/NativeCompanyResourceId.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
/** One department catalog for native staff, support and feed audiences. */
final class NativeCompanyDepartments
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function access(int $issuer,int $actor,bool $admin=false):IssuerResourceAccess
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->member($issuer,$actor)||($admin&&!$acl->administrative($issuer,$actor)))throw new DomainException('native_department_denied');return$acl;
    }
    private function row(array $row,bool $owned):array
    {
        $id=$row[$owned?'id':'departmentid'];$source=$owned?'company_ticket_department':'ticket_department';$out=['departmentid'=>NativeCompanyResourceId::encode($source,$id),'source_kind'=>$source,'source_id'=>(int)$id,'name'=>$row['name'],'hidefromclient'=>(int)$row['hidefromclient']];return$out;
    }
    /** Public catalog metadata for authorized staff only; never returns mail credentials or config. */
    public function catalog(int $issuer,int $actor):array
    {
        $acl=$this->access($issuer,$actor);$primary=$acl->primaryId();if(!$acl->administrative($issuer,$actor)&&!$acl->can($issuer,$actor,'tickets','view'))throw new DomainException('native_department_denied');$out=[];
        foreach($this->rows('SELECT id,name,hidefromclient FROM '.$this->p.NativeCompanyDepartmentSchema::DEPARTMENTS.' WHERE issuer_id=? ORDER BY name,id',[$issuer])as$r)$out[]=$this->row($r,true);
        if($primary===$issuer)foreach($this->rows('SELECT departmentid,name,hidefromclient FROM '.$this->p.'departments ORDER BY name,departmentid')as$r)$out[]=$this->row($r,false);
        usort($out,static fn($a,$b)=>[$a['name'],$a['departmentid']]<=>[$b['name'],$b['departmentid']]);$this->access($issuer,$actor);if($acl->primaryId()!==$primary||(!$acl->administrative($issuer,$actor)&&!$acl->can($issuer,$actor,'tickets','view')))throw new DomainException('native_department_denied');return$out;
    }
    public function get(int $issuer,int $actor,$id,bool $admin=false):array
    {
        $acl=$this->access($issuer,$actor,$admin);$primary=$acl->primaryId();$d=NativeCompanyResourceId::decode('ticket_department',$id);$owned=$d['source_kind']==='company_ticket_department';
        if(!$owned&&$primary!==$issuer)throw new DomainException('native_department_denied');
        if(!$admin&&!$acl->administrative($issuer,$actor)&&!$acl->can($issuer,$actor,'tickets','view'))throw new DomainException('native_department_denied');
        $cols=$admin?'*':($owned?'id,name,hidefromclient':'departmentid,name,hidefromclient');
        $row=$this->rows('SELECT '.$cols.' FROM '.$this->p.($owned?NativeCompanyDepartmentSchema::DEPARTMENTS:'departments').' WHERE '.($owned?'issuer_id=? AND id=?':'departmentid=?'),$owned?[$issuer,$d['source_id']]:[$d['source_id']])[0]??null;
        if(!$row)throw new DomainException('native_department_denied');$out=$this->row($row,$owned);
        if($admin){$snapshot=$row;ksort($snapshot);$out['revision_token']=hash('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));foreach(['imap_username','email','email_from_header','host','encryption','folder','delete_after_import','calendar_id']as$key)$out[$key]=$row[$key]??'';$out['password']='';$out['has_password']=($row[$owned?'password_cipher':'password']??'')!=='';}
        $this->access($issuer,$actor,$admin);if($acl->primaryId()!==$primary||(!$admin&&!$acl->administrative($issuer,$actor)&&!$acl->can($issuer,$actor,'tickets','view')))throw new DomainException('native_department_denied');return$out;
    }
    private static function form(array $input):array
    {
        $text=['name'=>100,'imap_username'=>191,'email'=>100,'host'=>150,'encryption'=>3,'folder'=>191,'calendar_id'=>65535,'password'=>4096];
        if(array_diff(array_keys($input),array_merge(array_keys($text),['hidefromclient','delete_after_import','email_from_header'])))throw new DomainException('native_department_input_invalid');$out=[];
        foreach($text as$key=>$max){$v=$input[$key]??($key==='folder'?'INBOX':'');if(!is_string($v)||strlen($v)>$max||preg_match('//u',$v)!==1||preg_match('/[\x00-\x1f\x7f]/',$v))throw new DomainException('native_department_input_invalid');$out[$key]=$v;}
        if(trim($out['name'])===''||($out['email']!==''&&!filter_var($out['email'],FILTER_VALIDATE_EMAIL))||!in_array($out['encryption'],['','ssl','tls'],true)||$out['folder']==='')throw new DomainException('native_department_input_invalid');
        foreach(['hidefromclient','delete_after_import','email_from_header']as$key){if(isset($input[$key])&&!in_array($input[$key],['1','on',1,'0',0],true))throw new DomainException('native_department_input_invalid');$out[$key]=isset($input[$key])&&!in_array($input[$key],['0',0],true)?1:0;}
        return$out;
    }
    private function tables(bool $primary):array
    {
        $tables=['issuers_lock','issuers_registry','issuers_staff','staff','issuers_installation_identity','issuers_resource_permissions','issuers_audit',NativeCompanyDepartmentSchema::DEPARTMENTS,NativeCompanyDepartmentSchema::MEMBERS];if($primary)$tables=array_merge($tables,['departments','staff_departments']);return$tables;
    }
    /** Infrastructure encryption is called only after current company administrative authority is locked. */
    public function save(int $issuer,int $actor,array $input,$cipher,$id=null,?string $revision=null):array
    {
        $acl=$this->access($issuer,$actor,true);$data=self::form($input);if(!is_object($cipher)||!method_exists($cipher,'encrypt'))throw new LogicException('native_department_encryption_unavailable');
        if($id!==null&&($revision===null||!preg_match('/^[a-f0-9]{64}$/D',$revision)))throw new DomainException('native_department_revision_invalid');
        return IssuerTransaction::run($this->db,$this->p,$this->tables($acl->primaryId()===$issuer),function()use($issuer,$actor,$data,$cipher,$id,$revision){
            $acl=$this->access($issuer,$actor,true);$acl->lockFinancialIdentity($issuer,$actor);$old=$id===null?null:$this->get($issuer,$actor,$id,true);if($old!==null&&!hash_equals($old['revision_token'],$revision))throw new DomainException('native_department_stale');
            if($old!==null&&$old['has_password']&&$data['password']==='')foreach(['host','imap_username','email','encryption']as$field)if((string)$old[$field]!==$data[$field])throw new DomainException('native_department_credential_retarget_denied');
            foreach($this->catalog($issuer,$actor)as$row){$candidate=$this->get($issuer,$actor,$row['departmentid'],true);if($id!==null&&$candidate['departmentid']===(string)$id)continue;if($candidate['name']===$data['name']||($data['email']!==''&&strcasecmp($candidate['email'],$data['email'])===0))throw new DomainException('native_department_conflict');}
            $owned=$old===null||$old['source_kind']==='company_ticket_department';$source=$old['source_id']??null;$table=$owned?NativeCompanyDepartmentSchema::DEPARTMENTS:'departments';$key=$owned?'id':'departmentid';$write=$data;unset($write['password']);$now=date('Y-m-d H:i:s');
            if($owned){$write['actor_id']=$actor;$write['updated_at']=$now;if($old===null){$write['issuer_id']=$issuer;$write['revision']=1;$write['created_at']=$now;$write['password_cipher']='';}else{$raw=$this->rows('SELECT revision FROM '.$this->p.$table.' WHERE issuer_id=? AND id=?',[$issuer,$source])[0]??null;if(!$raw||(int)$raw['revision']>=2147483647)throw new DomainException('native_department_revision_invalid');$write['revision']=(int)$raw['revision']+1;}}
            if($old===null){$keys=array_keys($write);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.$table.' ('.implode(',',$keys).') VALUES('.implode(',',array_fill(0,count($keys),'?')).')',array_values($write));$source=(int)$this->db->insert_id();if($source<1)throw new RuntimeException('native_department_write_unconfirmed');$id=NativeCompanyResourceId::encode('company_ticket_department',$source);}
            else{$sets=array_map(static fn($key)=>$key.'=?',array_keys($write));$args=array_values($write);$args[]=$source;$where=$key.'=?';if($owned){$where.=' AND issuer_id=?';$args[]=$issuer;}IssuerStorage::query($this->db,'UPDATE '.$this->p.$table.' SET '.implode(',',$sets).' WHERE '.$where,$args);}
            if($data['password']!==''){$clear=$owned?json_encode(['v'=>1,'issuer_id'=>$issuer,'department_id'=>$source,'password'=>$data['password']],JSON_THROW_ON_ERROR):$data['password'];$encrypted=$cipher->encrypt($clear);if(!is_string($encrypted)||$encrypted==='')throw new RuntimeException('native_department_encryption_failed');$column=$owned?'password_cipher':'password';IssuerStorage::query($this->db,'UPDATE '.$this->p.$table.' SET '.$column.'=? WHERE '.$key.'=?'.($owned?' AND issuer_id=?':''),$owned?[$encrypted,$source,$issuer]:[$encrypted,$source]);$savedCipher=$this->rows('SELECT '.$column.' FROM '.$this->p.$table.' WHERE '.$key.'=?'.($owned?' AND issuer_id=?':''),$owned?[$source,$issuer]:[$source])[0][$column]??null;if(!is_string($savedCipher)||!hash_equals($encrypted,$savedCipher))throw new RuntimeException('native_department_write_unconfirmed');}
            $record=$this->get($issuer,$actor,$id,true);foreach($data as$k=>$v){if($k==='password')continue;if((string)($record[$k]??null)!==(string)$v)throw new RuntimeException('native_department_write_unconfirmed');}
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit (actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,?,?)',[$actor,'native_department_saved',$issuer,$source,$now]);$this->access($issuer,$actor,true);return$record;
        });
    }
    public function delete(int $issuer,int $actor,$id,string $revision):void
    {
        $acl=$this->access($issuer,$actor,true);$tables=array_merge($this->tables($acl->primaryId()===$issuer),[NativeCompanyDepartmentSchema::TICKETS,NativeCompanyDepartmentSchema::LEGACY_TICKETS]);if($acl->primaryId()===$issuer)$tables[]='tickets';
        IssuerTransaction::run($this->db,$this->p,$tables,function()use($issuer,$actor,$id,$revision){
            $acl=$this->access($issuer,$actor,true);$acl->lockFinancialIdentity($issuer,$actor);NativeCompanyDepartmentSchema::assertValid($this->db,$this->p);$record=$this->get($issuer,$actor,$id,true);if(!hash_equals($record['revision_token'],$revision))throw new DomainException('native_department_stale');$args=[$issuer,$record['source_kind'],$record['source_id']];
            foreach([NativeCompanyDepartmentSchema::MEMBERS,NativeCompanyDepartmentSchema::TICKETS,NativeCompanyDepartmentSchema::LEGACY_TICKETS]as$table)if($this->rows('SELECT id FROM '.$this->p.$table.' WHERE issuer_id=? AND department_source_kind=? AND department_source_id=? LIMIT 1',$args))throw new DomainException('native_department_referenced');
            $owned=$record['source_kind']==='company_ticket_department';if(!$owned){if($this->rows('SELECT ticketid FROM '.$this->p.'tickets WHERE department=? LIMIT 1',[$record['source_id']])||$this->rows('SELECT staffid FROM '.$this->p.'staff_departments WHERE departmentid=? LIMIT 1',[$record['source_id']]))throw new DomainException('native_department_referenced');}
            $table=$owned?NativeCompanyDepartmentSchema::DEPARTMENTS:'departments';$key=$owned?'id':'departmentid';$where=$key.'=?'.($owned?' AND issuer_id=?':'');$whereArgs=$owned?[$record['source_id'],$issuer]:[$record['source_id']];IssuerStorage::query($this->db,'DELETE FROM '.$this->p.$table.' WHERE '.$where,$whereArgs);if($this->rows('SELECT '.$key.' FROM '.$this->p.$table.' WHERE '.$where,$whereArgs))throw new RuntimeException('native_department_write_unconfirmed');
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,?,?)',[$actor,'native_department_deleted',$issuer,$record['source_id'],date('Y-m-d H:i:s')]);$this->access($issuer,$actor,true);
        });
    }
    /** Explicit staff command only: never run IMAP while rendering or loading another company. */
    public function probe(int $issuer,int $actor,array $input,$cipher,callable $factory,bool $folders):array
    {
        $this->access($issuer,$actor,true);if(array_diff(array_keys($input),['id','revision','email','username','password','host','encryption','folder']))throw new DomainException('native_department_input_invalid');
        $id=($input['id']??'')!==''?$input['id']:null;$saved=$id===null?null:$this->get($issuer,$actor,$id,true);$revision=$input['revision']??'';
        if($saved!==null&&(!is_string($revision)||!hash_equals($saved['revision_token'],$revision)))throw new DomainException('native_department_stale');
        $data=self::form(['name'=>'explicit-imap-probe','email'=>$input['email']??'','imap_username'=>$input['username']??'','password'=>$input['password']??'','host'=>$input['host']??'','encryption'=>$input['encryption']??'','folder'=>$input['folder']??'INBOX']);
        if(!in_array($data['encryption'],['ssl','tls'],true)||!preg_match('/^(?=.{1,150}$)[A-Za-z0-9](?:[A-Za-z0-9.-]*[A-Za-z0-9])?$/D',$data['host'])||str_contains($data['host'],'..'))throw new DomainException('native_department_secure_imap_required');
        $password=$data['password'];if($password===''&&$saved!==null&&$saved['has_password']){
            foreach(['host','imap_username','email','encryption']as$field)if((string)$saved[$field]!==$data[$field])throw new DomainException('native_department_credential_retarget_denied');
            $owned=$saved['source_kind']==='company_ticket_department';$column=$owned?'password_cipher':'password';$table=$owned?NativeCompanyDepartmentSchema::DEPARTMENTS:'departments';$key=$owned?'id':'departmentid';$raw=$this->rows('SELECT '.$column.' FROM '.$this->p.$table.' WHERE '.$key.'=?'.($owned?' AND issuer_id=?':''),$owned?[$saved['source_id'],$issuer]:[$saved['source_id']])[0][$column]??null;
            $this->access($issuer,$actor,true);if(!is_object($cipher)||!method_exists($cipher,'decrypt')||!is_string($raw))throw new DomainException('native_department_credential_invalid');$clear=$cipher->decrypt($raw);if(!is_string($clear))throw new DomainException('native_department_credential_invalid');
            if($owned){try{$envelope=json_decode($clear,true,8,JSON_THROW_ON_ERROR);}catch(Throwable$e){throw new DomainException('native_department_credential_invalid');}if(!is_array($envelope)||array_keys($envelope)!==['v','issuer_id','department_id','password']||$envelope['v']!==1||$envelope['issuer_id']!==$issuer||$envelope['department_id']!==$saved['source_id']||!is_string($envelope['password']))throw new DomainException('native_department_credential_invalid');$password=$envelope['password'];}else$password=$clear;
        }
        $username=$data['imap_username']!==''?$data['imap_username']:$data['email'];if($password===''||$username==='')throw new DomainException('native_department_credential_required');
        $current=function()use($issuer,$actor,$saved,$revision){$this->access($issuer,$actor,true);if($saved!==null&&!hash_equals($this->get($issuer,$actor,$saved['departmentid'],true)['revision_token'],$revision))throw new DomainException('native_department_stale');};$current();
        $imap=$factory($username,$password,$data['host'],$data['encryption']);$current();$connection=$imap->testConnection();$current();
        if(!$folders){$connection->getMailbox($data['folder']);$current();return['ok'=>true];}
        $boxes=$connection->getMailboxes();$current();$names=[];if(count($boxes)>500)throw new DomainException('native_department_folders_limit');foreach($boxes as$box){$attributes=$box->getAttributes();if(defined('LATT_NOSELECT')&&($attributes&LATT_NOSELECT))continue;$name=$box->getName();if(!is_string($name)||strlen($name)>191||preg_match('//u',$name)!==1||str_contains($name,"\0"))throw new DomainException('native_department_folder_invalid');$names[]=$name;}$current();return$names;
    }
    /** Actual active actor memberships for feed visibility; no inheritance into a secondary company. */
    /** Calendar-only read: no ticket grant and no IMAP/private department fields. */
    public function calendarSources(int $issuer,int $actor):array
    {
        $acl=$this->access($issuer,$actor);if(!$acl->can($issuer,$actor,'calendar','view'))throw new DomainException('native_department_denied');
        if($acl->primaryId()===$issuer)throw new DomainException('native_calendar_primary_uses_historical_model');
        $admin=$acl->administrative($issuer,$actor);$where='d.issuer_id=?';$args=[$issuer];
        if(!$admin){$where.=' AND EXISTS(SELECT 1 FROM '.$this->p.NativeCompanyDepartmentSchema::MEMBERS.' m WHERE m.issuer_id=d.issuer_id AND m.department_source_kind=? AND m.department_source_id=d.id AND m.staff_id=?)';$args[]='company_ticket_department';$args[]=$actor;}
        $sql='SELECT d.id,d.calendar_id FROM '.$this->p.NativeCompanyDepartmentSchema::DEPARTMENTS.' d WHERE '.$where.' ORDER BY d.id';$rows=$this->rows($sql,$args);$ids=[];
        foreach($rows as$row){$id=$row['calendar_id'];if(!is_string($id)||strlen($id)>65535||preg_match('/[\x00-\x20\x7f]/',$id))throw new DomainException('native_calendar_google_invalid');if($id!=='')$ids[]=$id;}
        $this->access($issuer,$actor);if(!$acl->can($issuer,$actor,'calendar','view')||$admin!==$acl->administrative($issuer,$actor)||$rows!==$this->rows($sql,$args))throw new DomainException('native_department_denied');
        return array_values(array_unique($ids));
    }
    public function forStaff(int $issuer,int $actor):array
    {
        $acl=$this->access($issuer,$actor);$primary=$acl->primaryId();$out=[];
        foreach($this->rows('SELECT d.id,d.name,d.hidefromclient FROM '.$this->p.NativeCompanyDepartmentSchema::MEMBERS.' m JOIN '.$this->p.NativeCompanyDepartmentSchema::DEPARTMENTS.' d ON d.issuer_id=m.issuer_id AND d.id=m.department_source_id WHERE m.issuer_id=? AND m.staff_id=? AND m.department_source_kind=? ORDER BY d.name,d.id',[$issuer,$actor,'company_ticket_department'])as$r)$out[]=$this->row($r,true);
        if($primary===$issuer){
            foreach($this->rows('SELECT d.departmentid,d.name,d.hidefromclient FROM '.$this->p.'staff_departments m JOIN '.$this->p.'departments d ON d.departmentid=m.departmentid WHERE m.staffid=? ORDER BY d.name,d.departmentid',[$actor])as$r)$out[]=$this->row($r,false);
            foreach($this->rows('SELECT d.departmentid,d.name,d.hidefromclient FROM '.$this->p.NativeCompanyDepartmentSchema::MEMBERS.' m JOIN '.$this->p.'departments d ON d.departmentid=m.department_source_id WHERE m.issuer_id=? AND m.staff_id=? AND m.department_source_kind=? ORDER BY d.name,d.departmentid',[$issuer,$actor,'ticket_department'])as$r)$out[]=$this->row($r,false);
        }
        $unique=[];foreach($out as$r)$unique[$r['departmentid']]=$r;$this->access($issuer,$actor);if($acl->primaryId()!==$primary)throw new DomainException('native_department_denied');return array_values($unique);
    }
}
