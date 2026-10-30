<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyWorkAssignmentsSchema.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';

/** Membership changes do not grant issuer access, alter billing, or infer membership from following. */
final class NativeCompanyWorkAssignments
{
    private const MAP=['project'=>['company_project_members','company_projects','project_id','projects'],
        'task'=>['company_task_assignees','company_tasks','task_id','tasks'],
        'standalone'=>['company_standalone_assignees','company_standalone_tasks','task_id','tasks']];
    public function __construct(private $db,private string $p)
    {if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function map(string $kind):array{return self::MAP[$kind]??throw new DomainException('native_assignment_kind_invalid');}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function authorize(int $issuer,int $actor,string $kind,string $cap):void
    {
        $feature=$this->map($kind)[3];$acl=new IssuerResourceAccess($this->db,$this->p);
        if(!$acl->can($issuer,$actor,$feature,'view')||!$acl->can($issuer,$actor,$feature,$cap)||($kind==='task'&&!$acl->can($issuer,$actor,'projects','view')))throw new DomainException('native_assignment_denied');
    }
    private static function fingerprint(array $rows):string{return hash('sha256',json_encode(array_map(static fn($r)=>array_map('strval',$r),$rows),JSON_THROW_ON_ERROR));}
    public function read($issuer,$actor,string $kind,$id):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$this->authorize($issuer,$actor,$kind,'view');
        [$table,$parent,$column]=$this->map($kind);NativeCompanyWorkAssignmentsSchema::assertValid($this->db,$this->p);
        if(!$this->rows('SELECT id FROM '.$this->p.$parent.' WHERE issuer_id=? AND id=?',[$issuer,$id]))throw new DomainException('native_assignment_denied');
        $rows=$this->rows('SELECT staff_id,active,revision,origin FROM '.$this->p.$table.' WHERE issuer_id=? AND '.$column.'=? ORDER BY staff_id',[$issuer,$id]);$members=[];
        foreach($rows as $row){if((int)$row['active']!==1)continue;try{$this->authorize($issuer,(int)$row['staff_id'],$kind,'view');}catch(DomainException $e){continue;}
            $staff=$this->rows('SELECT staffid,firstname,lastname FROM '.$this->p.'staff WHERE staffid=? AND active=1',[$row['staff_id']])[0]??null;if($staff)$members[]=$staff;
        }
        $this->authorize($issuer,$actor,$kind,'view');return ['members'=>$members,'revision'=>self::fingerprint($rows)];
    }
    public function tableNames(string$kind):array
    {
        [$table,$parent]=$this->map($kind);return['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','issuers_audit',$table,$parent];
    }
    /** Exact removal preserves every other recorded active member; hidden/revoked rows are never silently dropped. */
    public function remove($issuer,$actor,string$kind,$id,string$expected,$staff):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$staff=IssuerPolicy::id($staff);$this->authorize($issuer,$actor,$kind,'edit');
        return IssuerTransaction::run($this->db,$this->p,$this->tableNames($kind),function()use($issuer,$actor,$kind,$id,$expected,$staff){
            (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,$kind,'edit');[$table,$parent,$column]=$this->map($kind);
            $rows=$this->rows('SELECT staff_id FROM '.$this->p.$table.' WHERE issuer_id=? AND '.$column.'=? AND active=1 ORDER BY staff_id FOR UPDATE',[$issuer,$id]);
            $keep=array_values(array_filter(array_map('intval',array_column($rows,'staff_id')),static fn($candidate)=>$candidate!==$staff));
            return$this->write($issuer,$actor,$kind,$id,$expected,$keep,true,null);
        });
    }
    public function replace($issuer,$actor,string $kind,$id,string $expected,array $staffIds):array
    {return$this->write($issuer,$actor,$kind,$id,$expected,$staffIds,false,null);}
    public function replaceInOwnedTransaction($issuer,$actor,string$kind,$id,string$expected,array$staffIds):array
    {return$this->write($issuer,$actor,$kind,$id,$expected,$staffIds,true,null);}
    public function createProjectMembersInOwnedTransaction(int$issuer,int$actor,int$project,array$staffIds,object$proof):array
    {
        CompanyProjects::assertNativeCreationProof($proof,$this->db,$this->p,$issuer,$actor,$project);
        return$this->write($issuer,$actor,'project',$project,self::fingerprint([]),$staffIds,true,$proof);
    }
    public function createTaskMembersInOwnedTransaction(int$issuer,int$actor,string$kind,int$id,array$staffIds,object$proof):array
    {
        if(!in_array($kind,['task','standalone'],true))throw new DomainException('native_assignment_denied');
        $this->assertCreationProof($kind,$proof,$issuer,$actor,$id);
        return$this->write($issuer,$actor,$kind,$id,self::fingerprint([]),$staffIds,true,$proof);
    }
    private function assertCreationProof(string$kind,object$proof,int$issuer,int$actor,int$id):void
    {
        if($kind==='project')CompanyProjects::assertNativeCreationProof($proof,$this->db,$this->p,$issuer,$actor,$id);
        elseif($kind==='task'){require_once __DIR__.'/CompanyTasks.php';CompanyTasks::assertNativeTaskCreationProof($proof,$this->db,$this->p,$issuer,$actor,$id);}
        elseif($kind==='standalone'){require_once __DIR__.'/CompanyStandaloneTasks.php';CompanyStandaloneTasks::assertNativeTaskCreationProof($proof,$this->db,$this->p,$issuer,$actor,$id);}
        else throw new DomainException('native_assignment_denied');
    }
    private function write($issuer,$actor,string$kind,$id,string$expected,array$staffIds,bool$inOwned,?object$proof):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);
        if(!preg_match('/^[a-f0-9]{64}$/D',$expected)||!array_is_list($staffIds)||count($staffIds)>500)throw new DomainException('native_assignment_invalid');
        $staffIds=array_map([IssuerPolicy::class,'id'],$staffIds);if(count(array_unique($staffIds))!==count($staffIds))throw new DomainException('native_assignment_invalid');sort($staffIds,SORT_NUMERIC);
        $authorize=function()use($issuer,$actor,$kind,$id,$proof):void{if($proof!==null){$this->assertCreationProof($kind,$proof,$issuer,$actor,$id);$this->authorize($issuer,$actor,$kind,'create');}else$this->authorize($issuer,$actor,$kind,'edit');};$authorize();[$table,$parent,$column]=$this->map($kind);NativeCompanyWorkAssignmentsSchema::assertValid($this->db,$this->p);
        $tables=$this->tableNames($kind);
        $work=function()use($issuer,$actor,$kind,$id,$expected,$staffIds,$table,$parent,$column,$authorize):array{
            (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$authorize();
            if(!$this->rows('SELECT id FROM '.$this->p.$parent.' WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$id]))throw new DomainException('native_assignment_denied');
            $old=$this->rows('SELECT staff_id,active,revision,origin FROM '.$this->p.$table.' WHERE issuer_id=? AND '.$column.'=? ORDER BY staff_id FOR UPDATE',[$issuer,$id]);
            if(!hash_equals($expected,self::fingerprint($old)))throw new DomainException('native_assignment_stale');
            foreach($staffIds as $staff){(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$staff);$this->authorize($issuer,$staff,$kind,'view');}
            $existing=[];foreach($old as $row){$staff=(int)$row['staff_id'];$existing[$staff]=true;$active=in_array($staff,$staffIds,true)?1:0;
                if((int)$row['active']!==$active)IssuerStorage::query($this->db,'UPDATE '.$this->p.$table.' SET active=?,revision=revision+1,origin=? WHERE issuer_id=? AND '.$column.'=? AND staff_id=? AND revision=?',[$active,'explicit_membership',$issuer,$id,$staff,$row['revision']]);}
            foreach($staffIds as $staff)if(!isset($existing[$staff]))IssuerStorage::query($this->db,'INSERT INTO '.$this->p.$table.'(issuer_id,'.$column.',staff_id,active,revision,origin) VALUES(?,?,?,1,1,?)',[$issuer,$id,$staff,'explicit_membership']);
            $active=array_map('intval',array_column($this->rows('SELECT staff_id FROM '.$this->p.$table.' WHERE issuer_id=? AND '.$column.'=? AND active=1 ORDER BY staff_id',[$issuer,$id]),'staff_id'));
            if($active!==$staffIds)throw new RuntimeException('native_assignment_write_unconfirmed');
            $audit=['actor_id'=>(string)$actor,'event'=>'native_'.$kind.'_members_updated','issuer_id'=>(string)$issuer,'subject_id'=>(string)$id,'created_at'=>date('Y-m-d H:i:s'),'metadata_json'=>json_encode(['member_ids'=>$staffIds,'before_revision'=>$expected,'operation_nonce'=>bin2hex(random_bytes(16))],JSON_THROW_ON_ERROR)];
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',array_values($audit));
            $auditId=(int)$this->db->insert_id();$receipt=$this->rows('SELECT actor_id,event,issuer_id,subject_id,created_at,metadata_json FROM '.$this->p.'issuers_audit WHERE id=?',[$auditId])[0]??[];
            if($auditId<1||array_map('strval',$receipt)!==$audit)throw new RuntimeException('native_assignment_audit_unconfirmed');
            $authorize();foreach($staffIds as $staff)$this->authorize($issuer,$staff,$kind,'view');return $this->read($issuer,$actor,$kind,$id);
        };
        if(!$inOwned)return IssuerTransaction::run($this->db,$this->p,$tables,$work);
        IssuerTransaction::assertOwned($this->db,$this->p,$tables);return$work();
    }
}
