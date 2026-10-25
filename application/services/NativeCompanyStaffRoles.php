<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyStaff.php';
/** Original role templates and membership assignments, explicitly scoped; never staff.role/global grants. */
final class NativeCompanyStaffRoles
{
    private const NODE='native_staff_roles';
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public function tables():array{return IssuerStaffAccess::TABLES;}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function access(int $i,int $a,string $cap):IssuerResourceAccess{$acl=new IssuerResourceAccess($this->db,$this->p);if($acl->primaryId()===$i||!$acl->can($i,$a,'roles','view')||!$acl->can($i,$a,'roles',$cap))throw new DomainException('native_staff_role_denied');return$acl;}
    private static function hash($v):string{return hash('sha256',json_encode($v,JSON_THROW_ON_ERROR));}
    private function document(int $i):object{$row=$this->rows('SELECT settings_json FROM '.$this->p.'issuers_registry WHERE id=?',[$i])[0]??null;if(!$row)throw new DomainException('native_staff_role_denied');$doc=json_decode($row['settings_json'],false,64,JSON_THROW_ON_ERROR);if(!$doc instanceof stdClass)throw new DomainException('native_staff_role_invalid');return$doc;}
    private function node(object $doc):object
    {
        $n=$doc->{self::NODE}??(object)['format'=>1,'next_id'=>1,'roles'=>(object)[],'assignments'=>(object)[]];
        if(!$n instanceof stdClass||array_keys((array)$n)!==['format','next_id','roles','assignments']||$n->format!==1||!is_int($n->next_id)||$n->next_id<1||$n->next_id>4294967295||!$n->roles instanceof stdClass||!$n->assignments instanceof stdClass)throw new DomainException('native_staff_role_invalid');
        if(count((array)$n->roles)>1000||count((array)$n->assignments)>10000)throw new DomainException('native_staff_role_invalid');
        foreach($n->roles as$id=>$r){if(!preg_match('/^[1-9][0-9]*$/D',(string)$id)||(int)$id>=$n->next_id||!$r instanceof stdClass||array_keys((array)$r)!==['name','permissions','revision']||!is_string($r->name)||trim($r->name)===''||mb_strlen($r->name)>150||!$r->permissions instanceof stdClass||!is_int($r->revision)||$r->revision<1)throw new DomainException('native_staff_role_invalid');foreach($r->permissions as$f=>$caps){IssuerResourceAccess::key($f);if(!is_array($caps)||count($caps)!==count(array_unique($caps)))throw new DomainException('native_staff_role_invalid');foreach($caps as$c)IssuerResourceAccess::key($c);}}
        foreach($n->assignments as$staff=>$role)if(!preg_match('/^[1-9][0-9]*$/D',(string)$staff)||!is_int($role)||!isset($n->roles->{(string)$role}))throw new DomainException('native_staff_role_invalid');return$n;
    }
    private function grants(int $i,int $a,array $input):object
    {
        $catalog=CompanyResourceCatalog::definitions();$acl=new IssuerResourceAccess($this->db,$this->p);$out=[];
        foreach($input as$f=>$caps){if(!isset($catalog[$f])||!is_array($caps))throw new DomainException('native_staff_role_invalid');foreach($caps as$c){if(!is_string($c)||!isset($catalog[$f]['capabilities'][$c])||!$acl->can($i,$a,$f,$c))throw new DomainException('issuer_staff_grant_denied');$out[$f][$c]=true;}}
        ksort($out);foreach($out as&$caps){ksort($caps);$caps=array_keys($caps);}unset($caps);return(object)$out;
    }
    public function page(int $i,int $a):array{$this->access($i,$a,'view');$doc=$this->document($i);$n=$this->node($doc);$this->access($i,$a,'view');if(self::hash($n)!==self::hash($this->node($this->document($i))))throw new DomainException('native_staff_role_stale');return['roles'=>(array)$n->roles,'assignments'=>(array)$n->assignments,'revision'=>self::hash($n)];}
    public function templatesForStaff(int $i,int $a):array
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if($acl->primaryId()===$i||!$acl->can($i,$a,'staff','view')||(!$acl->can($i,$a,'staff','create')&&!$acl->can($i,$a,'staff','edit')))throw new DomainException('native_staff_role_denied');$n=$this->node($this->document($i));return['roles'=>(array)$n->roles,'assignments'=>(array)$n->assignments,'revision'=>self::hash($n)];
    }
    private function persist(int $i,int $a,object $doc,object $n,string $event,int $id):void
    {
        $doc->{self::NODE}=$n;$json=json_encode($doc,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);IssuerStorage::query($this->db,'UPDATE '.$this->p.'issuers_registry SET settings_json=? WHERE id=?',[$json,$i]);if(self::hash($this->document($i))!==self::hash($doc))throw new RuntimeException('native_staff_role_unconfirmed');
        $nonce=bin2hex(random_bytes(32));$meta=json_encode(['source_id'=>$id,'state_hash'=>self::hash($n),'nonce'=>$nonce],JSON_THROW_ON_ERROR);$now=gmdate('Y-m-d H:i:s');IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$a,$event,$i,$id,$now,$meta]);$audit=(int)$this->db->insert_id();$r=$this->rows('SELECT actor_id,event,issuer_id,subject_id,created_at,metadata_json FROM '.$this->p.'issuers_audit WHERE id=?',[$audit])[0]??[];if(array_map('strval',array_values($r))!==array_map('strval',[$a,$event,$i,$id,$now,$meta]))throw new RuntimeException('native_staff_role_unconfirmed');
    }
    public function save(int $i,int $a,?int $id,string $revision,string $name,array $permissions,bool $updateMembers=false):int
    {
        $cap=$id===null?'create':'edit';$this->access($i,$a,$cap);$name=trim($name);if($name===''||mb_strlen($name)>150||preg_match('/[\x00-\x1f\x7f]/',$name)||!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('native_staff_role_invalid');
        return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($i,$a,$id,$revision,$name,$permissions,$updateMembers,$cap){$acl=$this->access($i,$a,$cap);$acl->lockFinancialIdentity($i,$a);$doc=$this->document($i);$n=$this->node($doc);if(!hash_equals(self::hash($n),$revision))throw new DomainException('native_staff_role_stale');if($id!==null&&!isset($n->roles->{(string)$id}))throw new DomainException('native_staff_role_denied');$grants=$this->grants($i,$a,$permissions);$old=$id===null?null:$n->roles->{(string)$id};if($id===null){if($n->next_id>=4294967295)throw new DomainException('native_staff_role_invalid');$id=$n->next_id++;}if($old===null&&count((array)$n->roles)>=1000)throw new DomainException('native_staff_role_invalid');$n->roles->{(string)$id}=(object)['name'=>$name,'permissions'=>$grants,'revision'=>($old->revision??0)+1];
            if($updateMembers){if(!$acl->can($i,$a,'staff','edit'))throw new DomainException('native_staff_role_denied');foreach($n->assignments as$staff=>$role){if($role!==$id)continue;$staff=(int)$staff;if(!$acl->member($i,$staff)||$acl->isInstallationMaster($staff))throw new DomainException('native_staff_role_denied');$preserve=$this->rows('SELECT feature,capability FROM '.$this->p.'issuers_resource_permissions WHERE issuer_id=? AND staff_id=? AND feature=?',[$i,$staff,'core_legacy_administration']);IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'issuers_resource_permissions WHERE issuer_id=? AND staff_id=? AND feature<>?',[$i,$staff,'core_legacy_administration']);foreach($grants as$f=>$caps)foreach($caps as$c)IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,?,?,?)',[$i,$staff,$f,$c]);$expected=$preserve;foreach($grants as$f=>$caps)foreach($caps as$c)$expected[]=['feature'=>$f,'capability'=>$c];usort($expected,fn($x,$y)=>[$x['feature'],$x['capability']]<=>[$y['feature'],$y['capability']]);if($expected!==$this->rows('SELECT feature,capability FROM '.$this->p.'issuers_resource_permissions WHERE issuer_id=? AND staff_id=? ORDER BY feature,capability',[$i,$staff]))throw new RuntimeException('native_staff_role_unconfirmed');}}
            $this->persist($i,$a,$doc,$n,'native_staff_role_saved',$id);$this->access($i,$a,$cap);return$id;
        });
    }
    /** Called only by the native staff writer in its already-owned transaction. Role selection does not mutate global identity. */
    public function assignInOwnedTransaction(int $i,int $a,int $staff,?int $role,string $revision,?CompanyStaff $createdBy=null):void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());if($createdBy!==null)$createdBy->assertCreatedInOwned($this->db,$this->p,$i,$a,'staff',$staff);$cap=$createdBy!==null?'create':'edit';$acl=new IssuerResourceAccess($this->db,$this->p);if($acl->primaryId()===$i||!$acl->can($i,$a,'staff',$cap)||!$acl->member($i,$staff)||$acl->isInstallationMaster($staff))throw new DomainException('native_staff_role_denied');
        $doc=$this->document($i);$n=$this->node($doc);if(!hash_equals(self::hash($n),$revision))throw new DomainException('native_staff_role_stale');
        if($role!==null){$this->templatesForStaff($i,$a);if(!isset($n->roles->{(string)$role}))throw new DomainException('native_staff_role_denied');$this->grants($i,$a,(array)$n->roles->{(string)$role}->permissions);$n->assignments->{(string)$staff}=$role;}else unset($n->assignments->{(string)$staff});
        $this->persist($i,$a,$doc,$n,'native_staff_role_assigned',$staff);if(!$acl->can($i,$a,'staff',$cap)||!$acl->member($i,$staff))throw new DomainException('native_staff_role_denied');
    }
    public function delete(int $i,int $a,int $id,string $revision):void
    {
        $this->access($i,$a,'delete');IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($i,$a,$id,$revision){$this->access($i,$a,'delete')->lockFinancialIdentity($i,$a);$doc=$this->document($i);$n=$this->node($doc);if(!hash_equals(self::hash($n),$revision))throw new DomainException('native_staff_role_stale');if(!isset($n->roles->{(string)$id}))throw new DomainException('native_staff_role_denied');if(in_array($id,(array)$n->assignments,true))throw new DomainException('native_staff_role_referenced');unset($n->roles->{(string)$id});$this->persist($i,$a,$doc,$n,'native_staff_role_deleted',$id);$this->access($i,$a,'delete');});
    }
}
