<?php

declare(strict_types=1);

trait CompanyHrContracts
{
    private static function contractPermissions(string $cap='view'):array{return array_merge(self::permissions('hr_contracts','view'),[['hr_sensitive','view']],$cap==='view'?[]:[['hr_contracts',$cap]]);}
    private function contractInput(array $input):array
    {
        self::fields($input,['employee_id','type_id','starts_on','ends_on','terms']);$r=['employee_id'=>self::integer($input['employee_id']??null,1),'type_id'=>self::integer($input['type_id']??null,1),'starts_on'=>self::date($input['starts_on']??null),'ends_on'=>self::date($input['ends_on']??null,true)];
        if($r['ends_on']!==null&&$r['ends_on']<$r['starts_on'])throw new DomainException('issuer_hr_invalid');$t=$input['terms']??null;if(!is_array($t))throw new DomainException('issuer_hr_invalid');self::fields($t,['salary_minor','currency','hours_per_week','job_description','text']);
        $t=['salary_minor'=>self::integer($t['salary_minor']??null,0,2000000000),'currency'=>self::text($t['currency']??null,3,true),'hours_per_week'=>self::integer($t['hours_per_week']??null,0,168),'job_description'=>self::text($t['job_description']??null,4000,true,true),'text'=>self::text($t['text']??null,10000,true,true)];if(!in_array($t['currency'],['BRL','USD','EUR'],true))throw new DomainException('issuer_hr_invalid');$r['terms']=$t;return $r;
    }
    private function contractRefs(int $i,array $r):array
    {
        $e=$this->employee($i,(int)$r['employee_id'],true);$type=$this->activeReference($i,'contract_type',(int)$r['type_id']);if($r['starts_on']<$e['joined_on']||($e['left_on']!==null&&($r['ends_on']===null||$r['ends_on']>$e['left_on'])))throw new DomainException('issuer_hr_invalid');return [$e,$type];
    }
    private function contract(int $i,int $id,bool $activeEmployment=false):array
    {
        $r=$this->row($i,'contract',$id);$this->boundProof($i,'contract',$id,$r);$this->employee($i,(int)$r['employee_id'],$activeEmployment);$terms=self::data($r,'terms_json','terms_hash');$snapshot=self::data($r,'snapshot_json','snapshot_hash');
        if($this->contractInput(['employee_id'=>(int)$r['employee_id'],'type_id'=>(int)$r['type_id'],'starts_on'=>$r['starts_on'],'ends_on'=>$r['ends_on'],'terms'=>$terms])['terms']!==$terms)throw new RuntimeException('issuer_hr_integrity_failed');
        if($r['state']!=='draft'&&(!$snapshot||($snapshot['issuer_id']??null)!==$i||($snapshot['employee_id']??null)!==(int)$r['employee_id']||($snapshot['contract_id']??null)!==$id||($snapshot['terms']??null)!==$terms))throw new RuntimeException('issuer_hr_integrity_failed');return $r;
    }
    public function saveContract($issuer,$actor,?int $id,$revision,array $input,string $key):int
    {
        $r=$this->contractInput($input);return $this->request($issuer,$actor,self::contractPermissions($id===null?'create':'edit'),'contract_saved',$key,[$id,$revision,$r],function(int $i)use($id,$revision,$r):array{
            $this->contractRefs($i,$r);$terms=self::json($r['terms']);$values=$r;unset($values['terms']);$values+=['terms_json'=>$terms,'terms_hash'=>hash('sha256',$terms),'updated_at'=>self::now()];
            if($id===null){$this->limit($i,'contract',100,(int)$r['employee_id']);$counter=$this->rows('SELECT * FROM '.$this->p.'company_hr_contract_counters WHERE issuer_id=? FOR UPDATE',[$i])[0]??null;
                if(!$counter){$this->insert('company_hr_contract_counters',['issuer_id'=>$i,'next_number'=>2,'revision'=>1],'issuer_id');$number=1;}
                else{$number=self::integer($counter['next_number'],1,2147483646);$this->remember('company_hr_contract_counters',['issuer_id'=>$i],$counter);$this->updateRow('company_hr_contract_counters',$counter,['next_number'=>$number+1,'revision'=>$this->revision($counter,$counter['revision'])],'issuer_id');}
                $empty=self::json([]);$id=$this->insert('company_hr_contracts',['issuer_id'=>$i,'number'=>$number,'state'=>'draft','snapshot_json'=>$empty,'snapshot_hash'=>hash('sha256',$empty),'acknowledged_at'=>null,'acknowledged_by'=>null,'revision'=>1,'created_at'=>self::now()]+$values);
            }else{$old=$this->contract($i,$id);if($old['state']!=='draft'||(int)$old['employee_id']!==$r['employee_id'])throw new DomainException('issuer_hr_state');$this->updateRow('company_hr_contracts',$old,$values+['revision'=>$this->revision($old,$revision)]);}
            $this->validators[]=function()use($i,$id):void{$this->contract($i,$id);};return ['contract',$id,$id];
        });
    }
    public function contractState($issuer,$actor,$id,$revision,string $state,string $key):int
    {
        $id=IssuerPolicy::id($id);if(!in_array($state,['active','cancelled'],true))throw new DomainException('issuer_hr_invalid');
        return $this->request($issuer,$actor,self::contractPermissions('edit'),'contract_state',$key,[$id,$revision,$state],function(int $i)use($id,$revision,$state):array{
            $r=$this->contract($i,$id,true);if($r['state']==='cancelled'||($state==='active'&&$r['state']!=='draft'))throw new DomainException('issuer_hr_state');$changes=['state'=>$state,'revision'=>$this->revision($r,$revision),'updated_at'=>self::now()];
            if($state==='active'){
                [$employee,$type]=$this->contractRefs($i,$r);if($this->observedRows('SELECT id FROM '.$this->p.'company_hr_contracts WHERE issuer_id=? AND employee_id=? AND id<>? AND state=? AND starts_on<=? AND (ends_on IS NULL OR ends_on>=?) LIMIT 1',[$i,$r['employee_id'],$id,'active',$r['ends_on']??'2199-12-31',$r['starts_on']]))throw new DomainException('issuer_hr_contract_overlap');
                $identity=$this->staffReference($i,(int)$employee['staff_id']);$company=$this->rows('SELECT id,legal_name,tax_id FROM '.$this->p.'issuers_registry WHERE id=?',[$i])[0]??null;if(!$company)throw new DomainException('issuer_hr_reference_denied');$this->remember('issuers_registry',['id'=>$i],$company);
                $snapshot=['issuer_id'=>$i,'employee_id'=>(int)$r['employee_id'],'contract_id'=>$id,'number'=>(int)$r['number'],'company'=>$company,'employee'=>['employee_code'=>$employee['employee_code'],'name'=>trim($identity['firstname'].' '.$identity['lastname']),'staff_id'=>(int)$identity['staffid']],'type'=>$type['name'],'starts_on'=>$r['starts_on'],'ends_on'=>$r['ends_on'],'terms'=>self::data($r,'terms_json','terms_hash')];$json=self::json($snapshot);$changes+=['snapshot_json'=>$json,'snapshot_hash'=>hash('sha256',$json)];
            }elseif($r['state']==='draft'){
                // A cancelled draft retains an explicit non-operative snapshot.
                $snapshot=['issuer_id'=>$i,'employee_id'=>(int)$r['employee_id'],'contract_id'=>$id,'cancelled_draft'=>true,'terms'=>self::data($r,'terms_json','terms_hash')];$json=self::json($snapshot);$changes+=['snapshot_json'=>$json,'snapshot_hash'=>hash('sha256',$json)];
            }
            $this->updateRow('company_hr_contracts',$r,$changes);$this->validators[]=function()use($i,$id):void{$this->contract($i,$id,true);};return ['contract',$id,$id];
        });
    }
    public function getContract($issuer,$actor,$id):array
    {
        $id=IssuerPolicy::id($id);return $this->transaction($issuer,$actor,self::contractPermissions(),'contract_read',null,function(int $i)use($id):array{$r=$this->contract($i,$id);$r['terms']=self::data($r,'terms_json','terms_hash');$r['snapshot']=self::data($r,'snapshot_json','snapshot_hash');unset($r['terms_json'],$r['terms_hash'],$r['snapshot_json'],$r['snapshot_hash']);return $r;});
    }
    public function contracts($issuer,$actor,$employee):array
    {
        $employee=IssuerPolicy::id($employee);return $this->transaction($issuer,$actor,self::contractPermissions(),'contracts',null,function(int $i)use($employee):array{$this->employee($i,$employee);$ids=$this->observedRows('SELECT id FROM '.$this->p.'company_hr_contracts WHERE issuer_id=? AND employee_id=? ORDER BY id LIMIT 101',[$i,$employee]);if(count($ids)>100)throw new DomainException('issuer_hr_limit');$out=[];foreach($ids as $id){$r=$this->contract($i,(int)$id['id']);$r['terms']=self::data($r,'terms_json','terms_hash');unset($r['terms_json'],$r['terms_hash'],$r['snapshot_json'],$r['snapshot_hash']);$out[]=$r;}return $out;});
    }
    public function contractDocument($issuer,$actor,$id):array
    {
        $id=IssuerPolicy::id($id);return $this->transaction($issuer,$actor,array_merge(self::contractPermissions(),[['hr','export']]),'contract_document',null,function(int $i)use($id):array{$r=$this->contract($i,$id,true);if($r['state']!=='active')throw new DomainException('issuer_hr_state');return ['number'=>(int)$r['number'],'snapshot'=>self::data($r,'snapshot_json','snapshot_hash'),'acknowledged_at'=>$r['acknowledged_at'],'acknowledgment_label'=>'Ciência registrada; não constitui assinatura digital.'];});
    }
}
