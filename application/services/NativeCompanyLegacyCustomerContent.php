<?php
declare(strict_types=1);
/** Read existing native files without following links, interpreting paths from requests, or deleting bytes. */
final class NativeCompanyLegacyCustomerContent
{
    public static function read(int $client,string $name):array
    {
        if($client<1||!defined('CLIENT_ATTACHMENTS_FOLDER')||$name===''||basename($name)!==$name||strlen($name)>191||preg_match('/[\x00-\x1f\x7f\\\\\/]/',$name))throw new DomainException('native_customer_file_denied');
        $configured=rtrim(CLIENT_ATTACHMENTS_FOLDER,DIRECTORY_SEPARATOR);$root=realpath($configured);if($root===false||is_link($configured))throw new DomainException('native_customer_file_denied');
        $directory=$root.DIRECTORY_SEPARATOR.$client;$path=$directory.DIRECTORY_SEPARATOR.$name;
        if(is_link($directory)||is_link($path)||realpath($directory)!==$directory||realpath($path)!==$path||!is_file($path))throw new DomainException('native_customer_file_denied');
        $before=lstat($path);if(!$before||$before['size']<1||$before['size']>20971520)throw new DomainException('native_customer_file_denied');$handle=fopen($path,'rb');if($handle===false)throw new DomainException('native_customer_file_denied');
        try{$open=fstat($handle);if(!$open||$open['ino']!==$before['ino']||$open['dev']!==$before['dev']||($open['mode']&0170000)!==0100000)throw new DomainException('native_customer_file_denied');$bytes=stream_get_contents($handle,20971521);$after=fstat($handle);if(!is_string($bytes)||strlen($bytes)!==$before['size']||!$after||$after['size']!==$before['size']||$after['mtime']!==$before['mtime']||is_link($path)||realpath($path)!==$path)throw new DomainException('native_customer_file_denied');}finally{fclose($handle);}
        $mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);$videoTypes=['mp4'=>['video/mp4'],'m4v'=>['video/mp4','video/x-m4v'],'webm'=>['video/webm'],'ogv'=>['video/ogg'],'ogg'=>['video/ogg'],'flv'=>['video/x-flv']];
        $extension=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        if(!in_array($mime,['application/pdf','image/png','image/jpeg','text/plain'],true)&&!in_array($mime,$videoTypes[$extension]??[],true))$mime='application/octet-stream';return['content'=>$bytes,'content_hash'=>hash('sha256',$bytes),'byte_size'=>strlen($bytes),'mime'=>$mime];
    }
    public static function fingerprint(array $r):string
    {return hash('sha256',json_encode(array_map(static fn($k)=>(string)($r[$k]??''),['rel_id','rel_type','file_name','filetype','attachment_key','staffid','dateadded','external','external_link']),JSON_THROW_ON_ERROR));}
}
