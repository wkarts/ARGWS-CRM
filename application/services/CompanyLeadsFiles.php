<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyOperationFileContent.php';

/** Files are private SQL objects; their identifiers never resolve through the native public download. */
trait CompanyLeadsFiles
{
    public function addFile($issuer,$actor,$id,$revision,string $filename,string $bytes,string $key):string
    {
        $id=IssuerPolicy::id($id);$revision=self::integer($revision,1);[$name,$mime,$bytes]=CompanyOperationFileContent::normalize($filename,$bytes);$hash=hash('sha256',$bytes);$size=strlen($bytes);
        return $this->request($issuer,$actor,'edit','file_added',$key,[$id,$revision,$name,$mime,$hash,$size],function(int $i,int $a)use($id,$revision,$name,$mime,$bytes,$hash,$size,$key):array{
            $old=$this->lead($i,$id);$this->editable($old,$revision);$quota=$this->rows('SELECT COUNT(*) n,COALESCE(SUM(byte_size),0) bytes FROM '.$this->p.'company_lead_files WHERE issuer_id=? AND lead_id=?',[$i,$id])[0];CompanyOperationFileContent::quota((int)$quota['n'],(int)$quota['bytes'],$size);
            $file=hash('sha256','company-lead-file:'.$key);$this->insert('company_lead_files',['file_key'=>$file,'issuer_id'=>$i,'lead_id'=>$id,'actor_id'=>$a,'filename'=>$name,'mime'=>$mime,'content_hash'=>$hash,'byte_size'=>$size,'content'=>$bytes,'active'=>1,'created_at'=>self::now()],'file_key');$this->updateRow('company_leads',$old,['revision'=>$revision+1,'updated_at'=>self::now()]);$this->bumpNoticeRevision($i,$id,$revision+1);return ['lead',$id,$file];
        });
    }
    public function hideFile($issuer,$actor,$id,$revision,string $file,string $key):int
    {
        $id=IssuerPolicy::id($id);$revision=self::integer($revision,1);self::key($file);
        return $this->request($issuer,$actor,'edit','file_hidden',$key,[$id,$revision,$file],function(int $i,int $a)use($id,$revision,$file):array{$old=$this->lead($i,$id);$this->editable($old,$revision);$r=$this->rows('SELECT * FROM '.$this->p.'company_lead_files WHERE file_key=? AND issuer_id=? AND lead_id=? FOR UPDATE',[$file,$i,$id])[0]??null;if(!$r||(int)$r['active']!==1)throw new DomainException('company_lead_access_denied');$this->updateRow('company_lead_files',$r,['active'=>0],'file_key');$this->updateRow('company_leads',$old,['revision'=>$revision+1,'updated_at'=>self::now()]);$this->bumpNoticeRevision($i,$id,$revision+1);return ['lead',$id,$id];});
    }
    public function file($issuer,$actor,$id,string $file):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);self::key($file);
        return $this->transaction($issuer,$actor,'view','file_read',null,function()use($issuer,$id,$file):array{$lead=$this->lead($issuer,$id);$this->remember('company_leads',['id'=>$id],$lead);$r=$this->rows('SELECT * FROM '.$this->p.'company_lead_files WHERE file_key=? AND issuer_id=? AND lead_id=? AND active=1 FOR UPDATE',[$file,$issuer,$id])[0]??null;if(!$r)throw new DomainException('company_lead_access_denied');$this->remember('company_lead_files',['file_key'=>$file],$r);
            if((int)$r['byte_size']!==strlen($r['content'])||!hash_equals($r['content_hash'],hash('sha256',$r['content'])))throw new RuntimeException('issuer_lead_file_invalid');
            try{[$name,$mime,$canonical]=CompanyOperationFileContent::normalize($r['filename'],$r['content']);}catch(DomainException $e){throw new RuntimeException('issuer_lead_file_invalid',0,$e);}
            if($name!==$r['filename']||$mime!==$r['mime']||!hash_equals(hash('sha256',$canonical),$r['content_hash']))throw new RuntimeException('issuer_lead_file_invalid');return ['filename'=>$name,'mime'=>$mime,'bytes'=>$r['content'],'size'=>(int)$r['byte_size'],'hash'=>$r['content_hash']];});
    }
}
