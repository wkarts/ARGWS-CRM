<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyResourceReferences.php';
require_once __DIR__.'/CompanyProjectExtras.php';

/** Staff projection for the original download/file/project route. */
final class NativeCompanyProjectFileDownload
{
    public function __construct(private $db,private string $prefix)
    {if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('Invalid prefix');}
    public function read(int $issuer,int $actor,$project,string $key):array
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_project_file_invalid');
        $ref=NativeCompanyResourceReferences::resolve($this->db,$this->prefix,$issuer,$actor,'project',$project,'view');
        if($ref['source_kind']!=='company_project')throw new DomainException('company_project_access_denied');
        $file=$this->owned($issuer,$actor,'project',$ref['source_id'],$key);
        if(NativeCompanyResourceReferences::resolve($this->db,$this->prefix,$issuer,$actor,'project',$project,'view')!==$ref)throw new DomainException('company_project_access_denied');
        return $file;
    }
    public function readTask(int $issuer,int $actor,$task,string $key):array
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_project_file_invalid');
        $ref=NativeCompanyResourceReferences::resolve($this->db,$this->prefix,$issuer,$actor,'task',$task,'view');
        if($ref['source_kind']!=='company_task')throw new DomainException('company_project_access_denied');
        $file=$this->owned($issuer,$actor,'task',$ref['source_id'],$key);
        if(NativeCompanyResourceReferences::resolve($this->db,$this->prefix,$issuer,$actor,'task',$task,'view')!==$ref)throw new DomainException('company_project_access_denied');
        return$file;
    }
    private function owned(int$issuer,int$actor,string$kind,int$id,string$key):array
    {
        $extras=new CompanyProjectExtras($this->db,$this->prefix);$file=$extras->file($issuer,$actor,$kind,$id,$key);
        $list=$extras->files($issuer,$actor,$kind,$id);$match=array_values(array_filter($list['items'],static fn($item)=>$item['file_key']===$key));
        if(count($match)!==1||$match[0]['content_hash']!==$file['content_hash']||$match[0]['filename']!==$file['filename']||(int)$match[0]['byte_size']!==(int)$file['byte_size']||$match[0]['mime']!==$file['mime'])throw new DomainException('company_project_access_denied');
        return $file;
    }
}
