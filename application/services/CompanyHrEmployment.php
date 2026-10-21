<?php

declare(strict_types=1);

trait CompanyHrEmployment
{
    private function staffReference(int $i,int $staff):array
    {
        $this->acl->lockFinancialIdentity($i,$staff);if(!$this->acl->member($i,$staff))throw new DomainException('issuer_hr_reference_denied');
        $r=$this->rows('SELECT staffid,firstname,lastname,email,active FROM '.$this->p.'staff WHERE staffid=?',[$staff])[0]??null;if(!$r)throw new DomainException('issuer_hr_reference_denied');
        $this->remember('staff',['staffid'=>$staff],$r);$this->validators[]=function()use($i,$staff):void{if(!$this->acl->member($i,$staff))throw new DomainException('issuer_hr_reference_denied');};return $r;
    }
    private function employee(int $i,int $id,bool $active=false):array
    {
        $r=$this->row($i,'employee',$id);$this->boundProof($i,'employee',$id,$r);if($active){if(!in_array($r['state'],['active','on_leave'],true))throw new DomainException('issuer_hr_employment_inactive');$this->staffReference($i,(int)$r['staff_id']);}
        return $r;
    }
    private function ownEmployee(int $i,int $actor):array
    {
        $r=$this->rows('SELECT id FROM '.$this->p.'company_hr_employees WHERE issuer_id=? AND staff_id=?',[$i,$actor])[0]??null;
        if(!$r)throw new DomainException('issuer_hr_access_denied');return $this->employee($i,(int)$r['id'],true);
    }
    private function publicEmployee(array $r):array
    {
        $identity=$this->rows('SELECT firstname,lastname,email FROM '.$this->p.'staff WHERE staffid=?',[$r['staff_id']])[0]??null;
        if(!$identity)throw new DomainException('issuer_hr_reference_denied');$this->remember('staff',['staffid'=>$r['staff_id']],$identity);return $r+['name'=>trim($identity['firstname'].' '.$identity['lastname']),'email'=>$identity['email']];
    }
    private function employeeInput(array $input,bool $create):array
    {
        self::fields($input,array_merge(['employee_code','unit_id','position_id','workplace_id','manager_id','joined_on','left_on','state'],$create?['staff_id']:[]));
        $r=['employee_code'=>self::text($input['employee_code']??null,32,true),'unit_id'=>self::integer($input['unit_id']??null,1),'position_id'=>self::integer($input['position_id']??null,1),'workplace_id'=>self::integer($input['workplace_id']??null,1),'manager_id'=>empty($input['manager_id'])?null:self::integer($input['manager_id'],1),'joined_on'=>self::date($input['joined_on']??null),'left_on'=>self::date($input['left_on']??null,true),'state'=>$input['state']??'active'];
        if(!in_array($r['state'],['active','on_leave','terminated'],true)||($r['state']==='terminated')!==($r['left_on']!==null)||($r['left_on']!==null&&$r['left_on']<$r['joined_on']))throw new DomainException('issuer_hr_invalid');
        if($create)$r=['staff_id'=>self::integer($input['staff_id']??null,1)]+$r;return $r;
    }
    private function employeeRefs(int $i,?int $id,array $r):void
    {
        $this->staffReference($i,(int)$r['staff_id']);$this->activeReference($i,'unit',(int)$r['unit_id']);$position=$this->activeReference($i,'position',(int)$r['position_id']);$this->activeReference($i,'workplace',(int)$r['workplace_id']);
        if((int)$position['unit_id']!==(int)$r['unit_id'])throw new DomainException('issuer_hr_reference_denied');
        $parent=$r['manager_id'];$seen=[];while($parent!==null){$parent=(int)$parent;if($parent===$id||isset($seen[$parent])||count($seen)>=64)throw new DomainException('issuer_hr_cycle');$seen[$parent]=true;$manager=$this->employee($i,$parent,true);$parent=$manager['manager_id'];}
        if($id!==null&&$r['state']==='terminated'){
            if($this->observedRows('SELECT id FROM '.$this->p.'company_hr_employees WHERE issuer_id=? AND manager_id=? AND state<>? LIMIT 1',[$i,$id,'terminated']))throw new DomainException('issuer_hr_reference_in_use');
            if($this->observedRows('SELECT id FROM '.$this->p.'company_hr_contracts WHERE issuer_id=? AND employee_id=? AND state=? LIMIT 1',[$i,$id,'active']))throw new DomainException('issuer_hr_reference_in_use');
        }
    }
    private function createEmployeeRow(int $i,int $a,array $r):int
    {
        $this->limit($i,'employee',1000);$this->employeeRefs($i,null,$r);$id=$this->insert('company_hr_employees',['issuer_id'=>$i]+$r+['revision'=>1,'created_at'=>self::now(),'updated_at'=>self::now()]);
        $json=self::json($this->profileInput([]));$this->insert('company_hr_profiles',['issuer_id'=>$i,'employee_id'=>$id,'data_json'=>$json,'data_hash'=>hash('sha256',$json),'revision'=>1,'updated_at'=>self::now()],'employee_id');
        $this->validators[]=function()use($i,$id,$r):void{$this->employeeRefs($i,$id,$r);};return $id;
    }
    public function saveEmployee($issuer,$actor,?int $id,$revision,array $input,string $key):int
    {
        $r=$this->employeeInput($input,$id===null);
        return $this->request($issuer,$actor,self::permissions('hr',$id===null?'create':'edit'),'employee_saved',$key,[$id,$revision,$r],function(int $i,int $a)use($id,$revision,$r):array{
            if($id===null)$id=$this->createEmployeeRow($i,$a,$r);
            else{$old=$this->employee($i,$id);$r=['staff_id'=>(int)$old['staff_id']]+$r;$this->employeeRefs($i,$id,$r);$this->updateRow('company_hr_employees',$old,$r+['revision'=>$this->revision($old,$revision),'updated_at'=>self::now()]);$this->validators[]=function()use($i,$id,$r):void{$this->employeeRefs($i,$id,$r);};}
            return ['employee',$id,$id];
        });
    }
    public function getEmployee($issuer,$actor,$id):array
    {
        $id=IssuerPolicy::id($id);return $this->transaction($issuer,$actor,self::permissions(),'employee_read',null,function(int $i)use($id):array{return $this->publicEmployee($this->employee($i,$id));});
    }
    public function employees($issuer,$actor,$after=0,int $limit=50):array
    {
        $after=self::integer($after);if($limit<1||$limit>100)throw new DomainException('issuer_hr_invalid');
        return $this->transaction($issuer,$actor,self::permissions(),'employees',null,function(int $i)use($after,$limit):array{
            $rows=$this->observedRows('SELECT id FROM '.$this->p.'company_hr_employees WHERE issuer_id=? AND id>? ORDER BY id LIMIT '.($limit+1),[$i,$after]);$more=count($rows)>$limit;$out=[];foreach(array_slice($rows,0,$limit) as $r)$out[]=$this->publicEmployee($this->employee($i,(int)$r['id']));return ['items'=>$out,'next'=>$more?(int)end($out)['id']:null];
        });
    }
    /** New links can select only active identities already authorized in this company. */
    public function staffChoices($issuer,$actor):array
    {
        return $this->transaction($issuer,$actor,self::permissions('hr','create'),'staff_choices',null,function(int $i):array{
            $rows=$this->observedRows('SELECT s.staffid,s.firstname,s.lastname,s.email FROM '.$this->p.'staff s WHERE s.active=1 AND (EXISTS(SELECT 1 FROM '.$this->p.'issuers_staff m WHERE m.issuer_id=? AND m.staff_id=s.staffid AND m.can_view=1 FOR UPDATE) OR EXISTS(SELECT 1 FROM '.$this->p.'issuers_installation_identity m WHERE m.id=1 AND m.master_staff_id=s.staffid AND s.admin=1 FOR UPDATE)) AND NOT EXISTS(SELECT 1 FROM '.$this->p.'company_hr_employees e WHERE e.issuer_id=? AND e.staff_id=s.staffid FOR UPDATE) ORDER BY s.staffid LIMIT 1001',[$i,$i]);if(count($rows)>1000)throw new DomainException('issuer_hr_limit');return $rows;
        });
    }
    private function profileInput(array $input):array
    {
        self::fields($input,['salary_minor','currency','bank_reference','tax_identifier','birth_date','health','notes']);$currency=$input['currency']??'BRL';if(!in_array($currency,['BRL','USD','EUR'],true))throw new DomainException('issuer_hr_invalid');
        return ['salary_minor'=>self::integer($input['salary_minor']??0,0,2000000000),'currency'=>$currency,'bank_reference'=>self::text($input['bank_reference']??'',250),'tax_identifier'=>self::text($input['tax_identifier']??'',64),'birth_date'=>self::date($input['birth_date']??null,true),'health'=>self::text($input['health']??'',4000,false,true),'notes'=>self::text($input['notes']??'',4000,false,true)];
    }
    private function profile(int $i,int $employee):array
    {
        $r=$this->rows('SELECT * FROM '.$this->p.'company_hr_profiles WHERE issuer_id=? AND employee_id=? FOR UPDATE',[$i,$employee])[0]??null;if(!$r)throw new RuntimeException('issuer_hr_integrity_failed');$this->remember('company_hr_profiles',['employee_id'=>$employee,'issuer_id'=>$i],$r);$data=self::data($r,'data_json','data_hash');if($this->profileInput($data)!==$data)throw new RuntimeException('issuer_hr_integrity_failed');return $r;
    }
    public function getProfile($issuer,$actor,$employee):array
    {
        $employee=IssuerPolicy::id($employee);return $this->transaction($issuer,$actor,self::permissions('hr_sensitive','view'),'profile_read',null,function(int $i)use($employee):array{$this->employee($i,$employee);$r=$this->profile($i,$employee);return ['employee_id'=>$employee,'revision'=>(int)$r['revision'],'data'=>self::data($r,'data_json','data_hash')];});
    }
    public function saveProfile($issuer,$actor,$employee,$revision,array $input,string $key):int
    {
        $employee=IssuerPolicy::id($employee);$r=$this->profileInput($input);$perms=array_merge(self::permissions('hr_sensitive','view'),[['hr_sensitive','edit']]);
        return $this->request($issuer,$actor,$perms,'profile_saved',$key,[$employee,$revision,$r],function(int $i)use($employee,$revision,$r):array{
            $this->employee($i,$employee,true);$old=$this->profile($i,$employee);$json=self::json($r);$this->updateRow('company_hr_profiles',$old,['data_json'=>$json,'data_hash'=>hash('sha256',$json),'revision'=>$this->revision($old,$revision),'updated_at'=>self::now()],'employee_id');return ['employee',$employee,$employee];
        });
    }
}
