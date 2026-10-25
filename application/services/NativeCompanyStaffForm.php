<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyStaffRoles.php';
require_once __DIR__.'/NativeCompanyDepartmentSchema.php';
require_once __DIR__.'/NativeCompanyCustomFields.php';
/** Concrete participant for the ORIGINAL staff form; no callback/hook can add writes to this unit. */
final class NativeCompanyStaffForm
{
    private ?int $created=null;
    private ?CompanyStaff $writer=null;
    public function __construct(private $db,private string $p,private int $issuer,private int $actor,private array $departments,private ?int $role,private string $roleRevision,private array $customFields,private ?string $revision=null)
    {
        if(($revision!==null&&!preg_match('/^[a-f0-9]{64}$/D',$revision))||!preg_match('/^[A-Za-z0-9_]+$/D',$p)||count($departments)>1000||count($departments)!==count(array_unique($departments))||!preg_match('/^[a-f0-9]{64}$/D',$roleRevision))throw new DomainException('issuer_staff_invalid');
        foreach($departments as$id){$ref=NativeCompanyResourceId::decode('ticket_department',$id);if($ref['source_kind']!=='company_ticket_department')throw new DomainException('issuer_staff_denied');}
    }
    public function assertFor($db,string $p,int $issuer,int $actor):void{if($db!==$this->db||$p!==$this->p||$issuer!==$this->issuer||$actor!==$this->actor)throw new DomainException('issuer_staff_denied');}
    public function tables():array{return array_values(array_unique(array_merge((new NativeCompanyStaffRoles($this->db,$this->p))->tables(),(new NativeCompanyCustomFields($this->db,$this->p))->tableNames(),['company_departments','company_department_members'])));}
    public function input():array{return['departments'=>$this->departments,'role'=>$this->role,'role_revision'=>$this->roleRevision,'custom_fields'=>$this->customFields,'revision'=>$this->revision];}
    private function context():IssuerResourceAccess{$acl=new IssuerResourceAccess($this->db,$this->p);if($acl->primaryId()===$this->issuer||!$acl->can($this->issuer,$this->actor,'staff','view'))throw new DomainException('issuer_staff_denied');return$acl;}
    public function state(int $staff):array
    {
        $this->context();$roles=(new NativeCompanyStaffRoles($this->db,$this->p))->templatesForStaff($this->issuer,$this->actor);$departments=IssuerStorage::query($this->db,'SELECT department_source_kind,department_source_id FROM '.$this->p.'company_department_members WHERE issuer_id=? AND staff_id=? ORDER BY department_source_kind,department_source_id',[$this->issuer,$staff])->result_array();$values=IssuerStorage::query($this->db,'SELECT field_source_kind,field_id,value,revision FROM '.$this->p.'company_custom_field_values WHERE issuer_id=? AND source_kind=? AND source_id=? ORDER BY field_source_kind,field_id',[$this->issuer,'staff',$staff])->result_array();return['departments'=>$departments,'role'=>$roles['assignments'][$staff]??null,'custom_fields'=>$values];
    }
    public function apply(int $staff,bool $created,CompanyStaff $writer):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$acl=$this->context();$cap=$created?'create':'edit';if(!$acl->can($this->issuer,$this->actor,'staff',$cap)||!$acl->member($this->issuer,$staff))throw new DomainException('issuer_staff_denied');
        if($created?($this->revision!==null):($this->revision===null||!hash_equals($this->revision,hash('sha256',json_encode($this->state($staff),JSON_THROW_ON_ERROR)))))throw new DomainException('issuer_staff_stale');
        $expected=[];foreach($this->departments as$wire){$ref=NativeCompanyResourceId::decode('ticket_department',$wire);$row=IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.'company_departments WHERE issuer_id=? AND id=?',[$this->issuer,$ref['source_id']])->row_array();if(!$row)throw new DomainException('issuer_staff_denied');$expected[]=['department_source_kind'=>'company_ticket_department','department_source_id'=>$ref['source_id']];}usort($expected,fn($x,$y)=>$x['department_source_id']<=>$y['department_source_id']);
        $remaining=[];foreach($expected as$r)$remaining[$r['department_source_id']]=$r;
        $old=IssuerStorage::query($this->db,'SELECT id,department_source_kind,department_source_id FROM '.$this->p.'company_department_members WHERE issuer_id=? AND staff_id=? ORDER BY id',[$this->issuer,$staff])->result_array();foreach($old as$r){if($r['department_source_kind']!=='company_ticket_department'||!IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.'company_departments WHERE issuer_id=? AND id=?',[$this->issuer,$r['department_source_id']])->row_array())throw new DomainException('issuer_staff_denied');if(isset($remaining[$r['department_source_id']]))unset($remaining[$r['department_source_id']]);else IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'company_department_members WHERE id=? AND issuer_id=? AND staff_id=?',[$r['id'],$this->issuer,$staff]);}
        $now=gmdate('Y-m-d H:i:s');foreach($remaining as$r)IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_department_members(issuer_id,department_source_kind,department_source_id,staff_id,revision,actor_id,created_at,updated_at) VALUES(?,?,?,?,1,?,?,?)',[$this->issuer,$r['department_source_kind'],$r['department_source_id'],$staff,$this->actor,$now,$now]);
        $actual=$this->state($staff)['departments'];if(array_map(fn($r)=>[(string)$r['department_source_kind'],(string)$r['department_source_id']],$actual)!==array_map(fn($r)=>[(string)$r['department_source_kind'],(string)$r['department_source_id']],$expected))throw new RuntimeException('issuer_staff_write_unconfirmed');
        $roles=new NativeCompanyStaffRoles($this->db,$this->p);$roles->assignInOwnedTransaction($this->issuer,$this->actor,$staff,$this->role,$this->roleRevision,$created?$writer:null);
        $fields=new NativeCompanyCustomFields($this->db,$this->p);if($created){$writer->assertCreatedInOwned($this->db,$this->p,$this->issuer,$this->actor,'staff',$staff);$this->created=$staff;$this->writer=$writer;try{$fields->saveCreatedInOwnedTransaction($this->issuer,$this->actor,'staff',$staff,$this->customFields,$this);}finally{$this->created=null;$this->writer=null;}}else $fields->saveInOwnedTransaction($this->issuer,$this->actor,'staff',$staff,$this->customFields);
        foreach($expected as$r)if(!IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.'company_departments WHERE issuer_id=? AND id=?',[$this->issuer,$r['department_source_id']])->row_array())throw new DomainException('issuer_staff_denied');if(!$acl->can($this->issuer,$this->actor,'staff',$cap)||!$acl->member($this->issuer,$staff))throw new DomainException('issuer_staff_denied');return$this->state($staff);
    }
    public function assertCreatedInOwned($db,string $p,int $issuer,int $actor,string $source,int $staff):void
    {
        if($db!==$this->db||$p!==$this->p||$issuer!==$this->issuer||$actor!==$this->actor||$source!=='staff'||$staff!==$this->created)throw new DomainException('native_custom_field_creation_denied');IssuerTransaction::assertOwned($db,$p,$this->tables());if($this->writer===null)throw new DomainException('native_custom_field_creation_denied');$this->writer->assertCreatedInOwned($db,$p,$issuer,$actor,$source,$staff);if(!(new IssuerResourceAccess($db,$p))->member($issuer,$staff))throw new DomainException('native_custom_field_creation_denied');
    }
}
