<?php

declare(strict_types=1);
/** Pure, deterministic ZIP store formatter. Authorization belongs to the sealed reader, never this class. */
final class CompanyProjectFilesZip
{
    public static function build(array $files):string
    {
        if(!$files||count($files)>20)throw new DomainException('company_project_file_limit');$entries=[];$manifest=[];$bytes=0;
        foreach($files as $index=>$file){if(!in_array($file['mime']??null,['application/pdf','image/png'],true)||!is_string($file['content']??null)||!is_string($file['filename']??null)||!is_string($file['content_hash']??null)||strlen($file['content'])<8||strlen($file['content'])>2097152||!hash_equals($file['content_hash'],hash('sha256',$file['content'])))throw new RuntimeException('issuer_project_file_unconfirmed');$bytes+=strlen($file['content']);if($bytes>20971520)throw new DomainException('company_project_file_limit');$name=sprintf('anexo-%02d.',count($entries)+1).($file['mime']==='image/png'?'png':'pdf');$entries[$name]=$file['content'];$manifest[]=['arquivo'=>$name,'nome_original'=>$file['filename'],'mime'=>$file['mime'],'bytes'=>strlen($file['content']),'sha256'=>$file['content_hash']];}
        $entries['manifesto.json']=json_encode(['formato'=>1,'arquivos'=>$manifest],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";$data='';$central='';$count=0;
        foreach($entries as $name=>$body){$offset=strlen($data);$size=strlen($body);$crc=crc32($body);$length=strlen($name);$data.=pack('VvvvvvVVVvv',0x04034b50,20,0x800,0,0,33,$crc,$size,$size,$length,0).$name.$body;$central.=pack('VvvvvvvVVVvvvvvVV',0x02014b50,20,20,0x800,0,0,33,$crc,$size,$size,$length,0,0,0,0,0,$offset).$name;$count++;}
        return $data.$central.pack('VvvvvVVv',0x06054b50,0,0,$count,$count,strlen($central),strlen($data),0);
    }
}
