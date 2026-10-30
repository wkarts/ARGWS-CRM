<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyOperationFileContent.php';

/** Private bounded PDF/images; supplier visibility is explicit and scoped to an invitation's request. */
trait CompanyPurchasesFiles
{
    private function evidenceFile(int $issuer,int $request,int $supplier,int $id): array
    {
        $row=$this->object($issuer,'file',$id);
        if ((int)$row['request_id']!==$request||($row['supplier_id']!==null&&(int)$row['supplier_id']!==$supplier)||(int)$row['active']!==1) { throw new DomainException('company_purchase_reference_denied'); }
        $this->fileIntegrity($row); return $row;
    }

    private function fileIntegrity(array $row): void
    {
        [$name,$mime,$content]=CompanyOperationFileContent::normalize($row['filename'],$row['content']);
        if ($name!==$row['filename']||$mime!==$row['mime']||$content!==$row['content']||strlen($content)!==(int)$row['byte_size']||!hash_equals($row['content_hash'],hash('sha256',$content))) { throw new RuntimeException('issuer_purchase_integrity_invalid'); }
    }

    public function upload($issuer,$actor,$request,?int $supplier,string $name,string $content,bool $portalVisible,string $key): int
    {
        $request=IssuerPolicy::id($request); if ($supplier!==null) { $supplier=IssuerPolicy::id($supplier); }
        [$name,$mime,$content]=CompanyOperationFileContent::normalize($name,$content); $hash=hash('sha256',$content);
        return $this->command($issuer,$actor,'edit','file_uploaded',$key,[$request,$supplier,$name,$mime,$hash,$portalVisible],function ($i,$a) use ($request,$supplier,$name,$mime,$content,$hash,$portalVisible,$key): array {
            $req=$this->object($i,'request',$request);
            if ($req['state']==='cancelled') { throw new DomainException('company_purchase_state_conflict'); }
            if ($supplier!==null) { $this->object($i,'supplier',$supplier); }
            if ($this->portal!==null&&($request!==$this->portal['request_id']||$supplier!==$this->portal['supplier_id']||!$portalVisible)) { throw new DomainException('company_purchase_denied'); }
            $quota=$this->rows('SELECT COUNT(*) n,COALESCE(SUM(byte_size),0) bytes FROM '.$this->p.'company_purchase_files WHERE issuer_id=? AND request_id=?',[$i,$request])[0];
            CompanyOperationFileContent::quota((int)$quota['n'],(int)$quota['bytes'],strlen($content));
            $id=$this->insert('company_purchase_files',['issuer_id'=>$i,'request_id'=>$request,'supplier_id'=>$supplier,'file_key'=>hash('sha256','purchase-file|'.$i.'|'.$key),'filename'=>$name,'mime'=>$mime,'content_hash'=>$hash,'byte_size'=>strlen($content),'content'=>$content,'portal_visible'=>(int)$portalVisible,'active'=>1,'revision'=>1,'created_by'=>$this->portal===null?$a:0,'created_at'=>self::now()]);
            return ['file',$id,$id];
        });
    }

    public function hideFile($issuer,$actor,$id,$revision,string $key): int
    {
        $id=IssuerPolicy::id($id); $revision=CompanyPurchasesValues::integer($revision,1);
        return $this->command($issuer,$actor,'edit','file_hidden',$key,[$id,$revision],function ($i) use ($id,$revision): array {
            $file=$this->object($i,'file',$id); $this->revision($file,$revision);
            if ((int)$file['active']!==1||$this->rows('SELECT id FROM '.$this->p.'company_purchase_documents WHERE issuer_id=? AND file_id=?',[$i,$id])||$this->rows('SELECT id FROM '.$this->p.'company_purchase_events WHERE issuer_id=? AND file_id=?',[$i,$id])) { throw new DomainException('company_purchase_evidence_immutable'); }
            $this->update('company_purchase_files',$file,['active'=>0,'revision'=>$revision+1]);
            return ['file',$id,$id];
        });
    }

    public function files($issuer,$actor,$request): array
    {
        $issuer=IssuerPolicy::id($issuer); $actor=IssuerPolicy::id($actor); $request=IssuerPolicy::id($request);
        return $this->transaction($issuer,$actor,'view','files',null,function () use ($issuer,$request): array {
            $this->object($issuer,'request',$request);
            if ($this->portal!==null&&$request!==$this->portal['request_id']) { throw new DomainException('company_purchase_denied'); }
            $rows=$this->observedRows('SELECT * FROM '.$this->p.'company_purchase_files WHERE issuer_id=? AND request_id=? AND active=1 ORDER BY id',[$issuer,$request]); $out=[];
            foreach ($rows as $row) {
                if ($this->portal!==null&&((int)$row['portal_visible']!==1||($row['supplier_id']!==null&&(int)$row['supplier_id']!==$this->portal['supplier_id']))) { continue; }
                $this->remember('company_purchase_files',$row); unset($row['content']); $out[]=$row;
            }
            return $out;
        });
    }

    public function download($issuer,$actor,$id): array
    {
        $issuer=IssuerPolicy::id($issuer); $actor=IssuerPolicy::id($actor); $id=IssuerPolicy::id($id);
        return $this->transaction($issuer,$actor,'view','download',null,function () use ($issuer,$id): array {
            $row=$this->object($issuer,'file',$id);
            if ((int)$row['active']!==1) { throw new DomainException('company_purchase_denied'); }
            if ($this->portal!==null&&((int)$row['request_id']!==$this->portal['request_id']||(int)$row['portal_visible']!==1||($row['supplier_id']!==null&&(int)$row['supplier_id']!==$this->portal['supplier_id']))) { throw new DomainException('company_purchase_denied'); }
            $this->fileIntegrity($row); return ['filename'=>$row['filename'],'mime'=>$row['mime'],'content'=>$row['content'],'content_hash'=>$row['content_hash']];
        });
    }
}
