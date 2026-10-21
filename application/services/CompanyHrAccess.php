<?php

declare(strict_types=1);

/** Short-lived, purpose-bound recipient access. This is acknowledgment, never legal signing. */
trait CompanyHrAccess
{
    private static function accessPermissions(string $purpose):array
    {
        return array_merge(match($purpose){'training'=>self::permissions('hr_training','assign'),'contract'=>self::contractPermissions('edit'),default=>throw new DomainException('issuer_hr_invalid')},[['hr_portal','manage']]);
    }
    private function accessObject(int $i,string $purpose,int $object):array
    {
        if($purpose==='training'){$r=$this->assignment($i,$object);if($r['state']==='cancelled'||$r['due_at']<=self::now())throw new DomainException('issuer_hr_state');return $r;}
        if($purpose==='contract'){$r=$this->contract($i,$object,true);if($r['state']!=='active')throw new DomainException('issuer_hr_state');return $r;}
        throw new DomainException('issuer_hr_invalid');
    }
    private function accessRow(int $i,int $id,bool $mustActive=true):array
    {
        $r=$this->row($i,'access',$id);$this->boundProof($i,'access',$id,$r);$c=self::data($r,'contract_json','contract_hash');
        if(($c['issuer_id']??null)!==$i||($c['employee_id']??null)!==(int)$r['employee_id']||($c['actor_id']??null)!==(int)$r['actor_id']||($c['purpose']??null)!==$r['purpose']||($c['object_id']??null)!==(int)$r['object_id']||($c['expires_at']??null)!==$r['expires_at'])throw new RuntimeException('issuer_hr_integrity_failed');
        if($mustActive&&((int)$r['active']!==1||$r['expires_at']<=self::now()))throw new DomainException('issuer_hr_access_denied');
        if($mustActive){$employee=$this->employee($i,(int)$r['employee_id'],true);if(($c['recipient_id']??null)!==(int)$employee['staff_id'])throw new RuntimeException('issuer_hr_integrity_failed');$issuerActor=(int)$r['actor_id'];$this->acl->lockFinancialIdentity($i,$issuerActor);foreach(self::accessPermissions($r['purpose']) as [$feature,$cap])if(!$this->acl->can($i,$issuerActor,$feature,$cap))throw new DomainException('issuer_hr_access_denied');$this->validateOrigin($c['api_origin']??null,$i,$issuerActor);$object=$this->accessObject($i,$r['purpose'],(int)$r['object_id']);if((int)$object['employee_id']!==(int)$r['employee_id'])throw new RuntimeException('issuer_hr_integrity_failed');}
        return $r;
    }
    public function createAccess($issuer,$actor,string $purpose,$object,int $hours,string $key):array
    {
        $object=IssuerPolicy::id($object);if($hours<1||$hours>($purpose==='training'?168:72))throw new DomainException('issuer_hr_invalid');$secret='hrco_'.bin2hex(random_bytes(32));$hash=hash('sha256',$secret);$created=false;
        $value=$this->request($issuer,$actor,self::accessPermissions($purpose),'access_created',$key,[$purpose,$object,$hours],function(int $i,int $a)use($purpose,$object,$hours,$hash,&$created):array{
            $r=$this->accessObject($i,$purpose,$object);if($purpose==='training'&&$r['state']!=='assigned')throw new DomainException('issuer_hr_state');$employee=$this->employee($i,(int)$r['employee_id'],true);$this->limit($i,'access',100,(int)$employee['id']);if((int)$this->rows('SELECT COUNT(*) n FROM '.$this->p.'company_hr_access WHERE issuer_id=? AND employee_id=? AND active=1 AND expires_at>?',[$i,$employee['id'],self::now()])[0]['n']>=20)throw new DomainException('issuer_hr_limit');$expires=gmdate('Y-m-d H:i:s',time()+$hours*3600);if($purpose==='training'&&$expires>$r['due_at'])$expires=$r['due_at'];
            $contract=['issuer_id'=>$i,'employee_id'=>(int)$employee['id'],'actor_id'=>$a,'recipient_id'=>(int)$employee['staff_id'],'purpose'=>$purpose,'object_id'=>$object,'expires_at'=>$expires,'api_origin'=>$this->origin()];$json=self::json($contract);$id=$this->insert('company_hr_access',['issuer_id'=>$i,'employee_id'=>(int)$employee['id'],'actor_id'=>$a,'purpose'=>$purpose,'object_id'=>$object,'secret_hash'=>$hash,'contract_json'=>$json,'contract_hash'=>hash('sha256',$json),'active'=>1,'expires_at'=>$expires,'revision'=>1,'created_at'=>self::now(),'updated_at'=>self::now()]);$created=true;$this->validators[]=function()use($i,$id):void{$this->accessRow($i,$id);};return ['access',$id,['id'=>$id,'expires_at'=>$expires]];
        });return $value+['secret'=>$created?$secret:null];
    }
    public function revokeAccess($issuer,$actor,$id,$revision,string $key):int
    {
        $id=IssuerPolicy::id($id);return $this->request($issuer,$actor,self::permissions('hr_portal','manage'),'access_revoked',$key,[$id,$revision],function(int $i)use($id,$revision):array{$r=$this->accessRow($i,$id,false);$this->updateRow('company_hr_access',$r,['active'=>0,'revision'=>$this->revision($r,$revision),'updated_at'=>self::now()]);return ['access',$id,$id];});
    }
    public function accesses($issuer,$actor,$employee):array
    {
        $employee=IssuerPolicy::id($employee);return $this->transaction($issuer,$actor,self::permissions('hr_portal','manage'),'accesses',null,function(int $i)use($employee):array{$this->employee($i,$employee);$rows=$this->observedRows('SELECT id FROM '.$this->p.'company_hr_access WHERE issuer_id=? AND employee_id=? ORDER BY id LIMIT 101',[$i,$employee]);if(count($rows)>100)throw new DomainException('issuer_hr_limit');$out=[];foreach($rows as $v){$r=$this->accessRow($i,(int)$v['id'],false);unset($r['secret_hash'],$r['contract_json'],$r['contract_hash']);$out[]=$r;}return $out;});
    }
    private function resolveAccess(string $secret):array
    {
        if(!preg_match('/^hrco_[a-f0-9]{64}$/D',$secret))throw new DomainException('issuer_hr_access_denied');$r=$this->rows('SELECT issuer_id,id,employee_id FROM '.$this->p.'company_hr_access WHERE secret_hash=?',[hash('sha256',$secret)])[0]??null;if(!$r)throw new DomainException('issuer_hr_access_denied');$e=$this->rows('SELECT staff_id FROM '.$this->p.'company_hr_employees WHERE issuer_id=? AND id=?',[$r['issuer_id'],$r['employee_id']])[0]??null;if(!$e)throw new DomainException('issuer_hr_access_denied');return [(int)$r['issuer_id'],(int)$e['staff_id'],(int)$r['id']];
    }
    private function currentAccess(int $i,int $a,int $id,string $secret):array
    {
        $r=$this->accessRow($i,$id);if(!hash_equals($r['secret_hash'],hash('sha256',$secret))||(int)$this->employee($i,(int)$r['employee_id'],true)['staff_id']!==$a)throw new DomainException('issuer_hr_access_denied');$this->validators[]=function()use($i,$id):void{$this->accessRow($i,$id);};return $r;
    }
    public function accessView(string $secret):array
    {
        [$i,$a,$id]=$this->resolveAccess($secret);return $this->transaction($i,$a,[],'access_read',null,function()use($i,$a,$id,$secret):array{$r=$this->currentAccess($i,$a,$id,$secret);$object=$this->accessObject($i,$r['purpose'],(int)$r['object_id']);return ['purpose'=>$r['purpose'],'expires_at'=>$r['expires_at'],'revision'=>(int)$object['revision'],'object'=>$r['purpose']==='training'?$this->assignmentView($object,true):['snapshot'=>self::data($object,'snapshot_json','snapshot_hash'),'acknowledged_at'=>$object['acknowledged_at']]];});
    }
    public function accessSubmit(string $secret,$revision,array $answers,string $key):array
    {
        [$i,$a,$id]=$this->resolveAccess($secret);return $this->request($i,$a,[],'access_submitted',$key,[$id,$revision,$answers],function()use($i,$a,$id,$secret,$revision,$answers):array{
            $r=$this->currentAccess($i,$a,$id,$secret);if($r['purpose']==='training')return $this->submit($i,$a,(int)$r['object_id'],$revision,$answers);
            if($answers!==['acknowledge'=>1])throw new DomainException('issuer_hr_invalid');$contract=$this->contract($i,(int)$r['object_id'],true);if($contract['state']!=='active'||$contract['acknowledged_at']!==null)throw new DomainException('issuer_hr_state');$next=$this->revision($contract,$revision);$this->updateRow('company_hr_contracts',$contract,['acknowledged_at'=>self::now(),'acknowledged_by'=>$a,'revision'=>$next,'updated_at'=>self::now()]);return ['contract',(int)$contract['id'],['acknowledged'=>true,'revision'=>$next]];
        },function()use($i,$a,$id,$secret):void{$this->currentAccess($i,$a,$id,$secret);});
    }
    public function accessFile(string $secret,$file):array
    {
        $file=IssuerPolicy::id($file);[$i,$a,$id]=$this->resolveAccess($secret);return $this->transaction($i,$a,[],'access_file',null,function()use($i,$a,$id,$secret,$file):array{$r=$this->currentAccess($i,$a,$id,$secret);if($r['purpose']!=='training')throw new DomainException('issuer_hr_access_denied');return $this->participantFile($i,$a,(int)$r['object_id'],$file);});
    }
}
