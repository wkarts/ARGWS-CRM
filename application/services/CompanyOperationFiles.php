<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyContractsStorage.php';require_once __DIR__.'/CompanyOperationFilesSchema.php';require_once __DIR__.'/CompanyAssets.php';
/** Private contract/support blobs. Kind is a closed domain choice, never an SQL resource name. */
final class CompanyOperationFiles extends CompanyContractsStorage
{
    protected string $feature='contracts';
    private const TARGETS=['contract'=>['contracts','company_contracts','company_contract_files','contract_id'],'ticket'=>['tickets','company_tickets','company_ticket_files','ticket_id']];
    private function target(string $kind):array{if(!isset(self::TARGETS[$kind]))throw new DomainException('company_operation_invalid_input');$target=self::TARGETS[$kind];$this->feature=$target[0];return $target;}
    protected function transaction(callable $work)
    {
        CompanyContractsSchema::assertValid($this->db,$this->p);CompanyOperationFilesSchema::assertValid($this->db,$this->p);
        return IssuerTransaction::run($this->db,$this->p,array_merge(['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','issuers_clients','issuers_client_links','clients','issuers_audit'],array_keys(CompanyContractsSchema::definitions($this->p)),array_keys(CompanyOperationFilesSchema::definitions($this->p)),$this->apiTables()),$this->guardedWork($work));
    }
    private function parent(int $issuer,string $kind,int $id,bool $editable=false):array
    {
        [, $table]=$this->target($kind);$row=$this->rows('SELECT * FROM '.$this->p.$table.' WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$id])[0]??null;
        if(!$row)throw new DomainException('company_operation_access_denied');if($editable&&(($kind==='contract'&&!in_array($row['status'],['draft','active'],true))||($kind==='ticket'&&$row['status']!=='open')))throw new DomainException('company_operation_file_closed');return $row;
    }
    private function bump(int $issuer,string $kind,int $id,int $revision):void
    {
        [, $table]=$this->target($kind);IssuerStorage::query($this->db,'UPDATE '.$this->p.$table.' SET revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[gmdate('Y-m-d H:i:s'),$issuer,$id,$revision]);if((int)$this->parent($issuer,$kind,$id)['revision']!==$revision+1)throw new RuntimeException('issuer_operation_write_unconfirmed');
    }
    private static function normalize(string $name,string $bytes):array
    {
        if(strlen($name)>191||$name===''||trim($name)!==$name||str_starts_with($name,'.')||preg_match('//u',$name)!==1||preg_match('/[\x00-\x1f\x7f\\\\\/]/',$name)||strlen($bytes)<8||strlen($bytes)>2097152)throw new DomainException('company_operation_file_invalid');$extension=strtolower(pathinfo($name,PATHINFO_EXTENSION));$mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if($extension==='pdf'&&$mime==='application/pdf'&&preg_match('/^%PDF-[12]\.[0-9][\r\n]/',$bytes))return [$name,'application/pdf',$bytes];if(!in_array($extension,['png','jpg','jpeg'],true)||!in_array($mime,['image/png','image/jpeg'],true))throw new DomainException('company_operation_file_invalid');try{$inline=CompanyAssets::normalize($bytes);}catch(DomainException $e){throw new DomainException('company_operation_file_invalid');}$name=pathinfo($name,PATHINFO_FILENAME).'.png';if(strlen($name)>191)throw new DomainException('company_operation_file_invalid');return [$name,'image/png',base64_decode(substr($inline,strlen('data:image/png;base64,')),true)];
    }
    public function upload($issuer,$actor,string $kind,$id,$revision,string $key,string $name,string $bytes):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);[,,$table,$field]=$this->target($kind);$this->authorize($issuer,$actor,'edit');[$name,$mime,$bytes]=self::normalize($name,$bytes);$hash=hash('sha256',$bytes);
        return $this->operation($issuer,$actor,'edit',$key,$kind.'_file_upload',[$id,$revision,$name,$mime,$hash],function()use($issuer,$actor,$kind,$id,$revision,$key,$name,$mime,$bytes,$hash,$table,$field):int{
            $row=$this->parent($issuer,$kind,$id,true);if((int)$row['revision']!==$revision)throw new DomainException('company_operation_stale');
            $usage=$this->rows('SELECT COUNT(*) n,COALESCE(SUM(byte_size),0) bytes FROM '.$this->p.$table.' WHERE issuer_id=? AND '.$field.'=?',[$issuer,$id])[0];if((int)$usage['n']>=20||(int)$usage['bytes']+strlen($bytes)>20971520)throw new DomainException('company_operation_file_limit');
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.$table.'(file_key,issuer_id,'.$field.',actor_id,filename,mime,content_hash,byte_size,content,active,created_at) VALUES(?,?,?,?,?,?,?,?,?,1,?)',[$key,$issuer,$id,$actor,$name,$mime,$hash,strlen($bytes),$bytes,gmdate('Y-m-d H:i:s')]);$stored=$this->rows('SELECT filename,mime,content_hash,content,byte_size,active,actor_id FROM '.$this->p.$table.' WHERE file_key=? AND issuer_id=? AND '.$field.'=?',[$key,$issuer,$id])[0]??[];
            if((int)($stored['actor_id']??0)!==$actor||($stored['filename']??null)!==$name||($stored['mime']??null)!==$mime||($stored['content_hash']??null)!==$hash||(int)($stored['active']??0)!==1||(int)($stored['byte_size']??0)!==strlen($bytes)||!hash_equals($hash,hash('sha256',$stored['content']??'')))throw new RuntimeException('issuer_operation_write_unconfirmed');$this->bump($issuer,$kind,$id,$revision);return $id;
        });
    }
    public function page($issuer,$actor,string $kind,$id):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);[,,$table,$field]=$this->target($kind);$this->authorize($issuer,$actor,'view');
        return $this->transaction(function()use($issuer,$actor,$kind,$id,$table,$field):array{(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'view');$this->parent($issuer,$kind,$id);$rows=$this->rows('SELECT file_key,filename,mime,byte_size,content_hash,created_at FROM '.$this->p.$table.' WHERE issuer_id=? AND '.$field.'=? AND active=1 ORDER BY created_at,file_key LIMIT 20',[$issuer,$id]);$this->authorize($issuer,$actor,'view');return $rows;});
    }
    public function file($issuer,$actor,string $kind,$id,string $key):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);[,,$table,$field]=$this->target($kind);if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_operation_file_invalid');$this->authorize($issuer,$actor,'view');
        return $this->transaction(function()use($issuer,$actor,$kind,$id,$key,$table,$field):array{(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'view');$this->parent($issuer,$kind,$id);$row=$this->rows('SELECT filename,mime,byte_size,content_hash,content FROM '.$this->p.$table.' WHERE file_key=? AND issuer_id=? AND '.$field.'=? AND active=1 FOR UPDATE',[$key,$issuer,$id])[0]??null;
            if(!$row)throw new DomainException('company_operation_access_denied');if(!in_array($row['mime'],['application/pdf','image/png'],true)||(int)$row['byte_size']<8||(int)$row['byte_size']>2097152||strlen($row['content'])!==(int)$row['byte_size']||!hash_equals($row['content_hash'],hash('sha256',$row['content'])))throw new RuntimeException('issuer_operation_file_corrupt');
            $metadata=json_encode(['file_key'=>$key,'sha256'=>$row['content_hash']],JSON_THROW_ON_ERROR);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$actor,'company_'.$kind.'_file_downloaded',$issuer,$id,gmdate('Y-m-d H:i:s'),$metadata]);$audit=(int)$this->db->insert_id();if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND subject_id=? AND metadata_json=?',[$audit,$issuer,$id,$metadata]))throw new RuntimeException('issuer_operation_write_unconfirmed');$this->authorize($issuer,$actor,'view');return $row;
        });
    }
    public function hide($issuer,$actor,string $kind,$id,$revision,string $file,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);[,,$table,$field]=$this->target($kind);if(!preg_match('/^[a-f0-9]{64}$/D',$file))throw new DomainException('company_operation_file_invalid');
        return $this->operation($issuer,$actor,'edit',$key,$kind.'_file_hide',[$id,$revision,$file],function()use($issuer,$kind,$id,$revision,$file,$table,$field):int{$row=$this->parent($issuer,$kind,$id);if((int)$row['revision']!==$revision)throw new DomainException('company_operation_stale');if(!$this->rows('SELECT file_key FROM '.$this->p.$table.' WHERE file_key=? AND issuer_id=? AND '.$field.'=? FOR UPDATE',[$file,$issuer,$id]))throw new DomainException('company_operation_access_denied');IssuerStorage::query($this->db,'UPDATE '.$this->p.$table.' SET active=0 WHERE file_key=? AND issuer_id=? AND '.$field.'=?',[$file,$issuer,$id]);if($this->rows('SELECT file_key FROM '.$this->p.$table.' WHERE file_key=? AND active=1',[$file]))throw new RuntimeException('issuer_operation_write_unconfirmed');$this->bump($issuer,$kind,$id,$revision);return $id;});
    }
}
