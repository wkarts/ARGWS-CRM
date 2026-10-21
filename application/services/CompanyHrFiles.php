<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyOperationFileContent.php';

trait CompanyHrFiles
{
    private static function filePermissions(string $purpose,string $cap='view'):array
    {
        $base=match($purpose){'employee','record'=>self::permissions('hr_sensitive','view'),'contract'=>self::contractPermissions(),'course'=>self::permissions('hr_training','view'),'article'=>self::permissions(),default=>throw new DomainException('issuer_hr_invalid')};return array_merge($base,[['hr_files','view']],$cap==='view'?[]:[['hr_files',$cap]]);
    }
    private function fileParent(int $i,string $purpose,int $object):?int
    {
        if($purpose==='employee')return (int)$this->employee($i,$object,true)['id'];
        if($purpose==='record'){$r=$this->record($i,$object);if($r['state']==='void')throw new DomainException('issuer_hr_state');return (int)$this->employee($i,(int)$r['employee_id'],true)['id'];}
        if($purpose==='contract')return (int)$this->contract($i,$object,true)['employee_id'];
        if($purpose==='course'){$this->course($i,$object);return null;}
        if($purpose==='article'){$r=$this->row($i,'article',$object);$this->boundProof($i,'article',$object,$r);return null;}
        throw new DomainException('issuer_hr_invalid');
    }
    public function addFile($issuer,$actor,string $purpose,$object,$revision,string $filename,string $bytes,string $key):int
    {
        $object=IssuerPolicy::id($object);[$name,$mime,$bytes]=CompanyOperationFileContent::normalize($filename,$bytes);$hash=hash('sha256',$bytes);$size=strlen($bytes);
        return $this->request($issuer,$actor,self::filePermissions($purpose,'create'),'file_added',$key,[$purpose,$object,$revision,$name,$mime,$hash,$size],function(int $i,int $a)use($purpose,$object,$revision,$name,$mime,$bytes,$hash,$size):array{
            $employee=$this->fileParent($i,$purpose,$object);$parent=$this->row($i,$purpose,$object);$next=$this->revision($parent,$revision);$quotaSql='SELECT COUNT(*) n,COALESCE(SUM(size),0) bytes FROM '.$this->p.'company_hr_files WHERE issuer_id=? AND purpose=? AND object_id=?';$quota=$this->observedRows($quotaSql,[$i,$purpose,$object])[0];CompanyOperationFileContent::quota((int)$quota['n'],(int)$quota['bytes'],$size);
            $id=$this->insert('company_hr_files',['issuer_id'=>$i,'employee_id'=>$employee,'purpose'=>$purpose,'object_id'=>$object,'name'=>$name,'mime'=>$mime,'size'=>$size,'sha256'=>$hash,'content'=>$bytes,'hidden'=>0,'actor_id'=>$a,'revision'=>1,'created_at'=>self::now(),'updated_at'=>self::now()]);$this->expectRows($quotaSql,[$i,$purpose,$object],[['n'=>(int)$quota['n']+1,'bytes'=>(int)$quota['bytes']+$size]],true);$this->updateRow(self::OBJECTS[$purpose],$parent,['revision'=>$next,'updated_at'=>self::now()]);$this->validators[]=function()use($i,$purpose,$object):void{$this->fileParent($i,$purpose,$object);};return ['file',$id,$id];
        });
    }
    public function hideFile($issuer,$actor,string $purpose,$object,$file,$revision,string $key):int
    {
        $object=IssuerPolicy::id($object);$file=IssuerPolicy::id($file);return $this->request($issuer,$actor,self::filePermissions($purpose,'delete'),'file_hidden',$key,[$purpose,$object,$file,$revision],function(int $i)use($purpose,$object,$file,$revision):array{
            $this->fileParent($i,$purpose,$object);$r=$this->row($i,'file',$file);$this->boundProof($i,'file',$file,$r);if($r['purpose']!==$purpose||(int)$r['object_id']!==$object||(int)$r['hidden']!==0)throw new DomainException('issuer_hr_access_denied');$this->updateRow('company_hr_files',$r,['hidden'=>1,'revision'=>$this->revision($r,$revision),'updated_at'=>self::now()]);return ['file',$file,$file];
        });
    }
    private function fileBytes(int $i,int $file):array
    {
        $meta=$this->row($i,'file',$file);$this->boundProof($i,'file',$file,$meta);if((int)$meta['hidden']!==0)throw new DomainException('issuer_hr_access_denied');$r=$this->rows('SELECT content FROM '.$this->p.'company_hr_files WHERE issuer_id=? AND id=?',[$i,$file])[0]??null;if(!$r||strlen($r['content'])!==(int)$meta['size']||!hash_equals($meta['sha256'],hash('sha256',$r['content'])))throw new RuntimeException('issuer_hr_integrity_failed');
        try{[$name,$mime,$canonical]=CompanyOperationFileContent::normalize($meta['name'],$r['content']);}catch(DomainException $e){throw new RuntimeException('issuer_hr_integrity_failed');}
        if($name!==$meta['name']||$mime!==$meta['mime']||!hash_equals($meta['sha256'],hash('sha256',$canonical)))throw new RuntimeException('issuer_hr_integrity_failed');return ['filename'=>$name,'mime'=>$mime,'size'=>(int)$meta['size'],'hash'=>$meta['sha256'],'bytes'=>$r['content']];
    }
    public function files($issuer,$actor,string $purpose,$object):array
    {
        $object=IssuerPolicy::id($object);return $this->transaction($issuer,$actor,self::filePermissions($purpose),'files',null,function(int $i)use($purpose,$object):array{$this->fileParent($i,$purpose,$object);$rows=$this->observedRows('SELECT id FROM '.$this->p.'company_hr_files WHERE issuer_id=? AND purpose=? AND object_id=? ORDER BY id LIMIT 21',[$i,$purpose,$object]);if(count($rows)>20)throw new DomainException('issuer_hr_limit');$out=[];foreach($rows as $v){$r=$this->row($i,'file',(int)$v['id']);$this->boundProof($i,'file',(int)$r['id'],$r);if($r['sha256']!==$r['content_sha256'])throw new RuntimeException('issuer_hr_integrity_failed');unset($r['content_sha256']);$out[]=$r;}return $out;});
    }
    public function getFile($issuer,$actor,string $purpose,$object,$file):array
    {
        $object=IssuerPolicy::id($object);$file=IssuerPolicy::id($file);return $this->transaction($issuer,$actor,self::filePermissions($purpose),'file_read',null,function(int $i)use($purpose,$object,$file):array{$this->fileParent($i,$purpose,$object);$r=$this->row($i,'file',$file);$this->boundProof($i,'file',$file,$r);if($r['purpose']!==$purpose||(int)$r['object_id']!==$object)throw new DomainException('issuer_hr_access_denied');return $this->fileBytes($i,$file);});
    }
    private function participantFile(int $i,int $actor,int $assignment,int $file):array
    {
        $r=$this->assignment($i,$assignment);$employee=$this->employee($i,(int)$r['employee_id'],true);if((int)$employee['staff_id']!==$actor||$r['state']==='cancelled'||$r['due_at']<=self::now())throw new DomainException('issuer_hr_access_denied');$c=self::data($r,'contract_json','contract_hash');$materials=array_column($c['materials'],'sha256','id');$f=$this->row($i,'file',$file);
        if(!isset($materials[$file])||!hash_equals($materials[$file],$f['sha256'])||$f['purpose']!=='course'||(int)$f['object_id']!==(int)$r['course_id'])throw new DomainException('issuer_hr_access_denied');return $this->fileBytes($i,$file);
    }
    public function myTrainingFile($issuer,$actor,$assignment,$file):array
    {
        $assignment=IssuerPolicy::id($assignment);$file=IssuerPolicy::id($file);return $this->transaction($issuer,$actor,[],'training_file',null,function(int $i,int $a)use($assignment,$file):array{$this->ownEmployee($i,$a);return $this->participantFile($i,$a,$assignment,$file);});
    }
}
