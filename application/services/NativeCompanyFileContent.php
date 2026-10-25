<?php
declare(strict_types=1);
/** Pure bounded validation. Documents remain untrusted forced-download bytes; no extraction or execution. */
final class NativeCompanyFileContent
{
    public const MAX_BYTES=20971520;
    public static function maximum(?string $upload=null,?string $post=null):int
    {
        $limits=[self::MAX_BYTES];foreach([$upload??(string)ini_get('upload_max_filesize'),$post??(string)ini_get('post_max_size')]as$value){
            $value=trim($value);if(!preg_match('/^([0-9]+)([KMG]?)$/iD',$value,$m))throw new DomainException('native_file_configuration_invalid');$n=(float)$m[1];$n*=match(strtoupper($m[2])){'K'=>1024,'M'=>1048576,'G'=>1073741824,default=>1};if($n>0)$limits[]=(int)min($n,self::MAX_BYTES);
        }return min($limits);
    }
    public static function external(string $provider,string $url):string
    {
        $parts=parse_url($url);if(!in_array($provider,['gdrive','dropbox'],true)||!$parts||($parts['scheme']??'')!=='https'||isset($parts['user'])||isset($parts['pass'])||isset($parts['port'])||strlen($url)>4096||preg_match('/[\x00-\x20\x7f]/',$url))throw new DomainException('native_file_external_invalid');
        $hosts=$provider==='gdrive'?['drive.google.com','docs.google.com']:['www.dropbox.com','dropbox.com','dl.dropboxusercontent.com'];if(!in_array(strtolower($parts['host']??''),$hosts,true))throw new DomainException('native_file_external_invalid');return$url;
    }
    public static function normalize(string $name,string $bytes,string $allowed,int $maximum):array
    {
        if($maximum<1||$maximum>self::MAX_BYTES||$name===''||strlen($name)>191||trim($name)!==$name||str_starts_with($name,'.')||preg_match('//u',$name)!==1||preg_match('/[\x00-\x1f\x7f\\\\\/]/',$name)||strlen($bytes)<1||strlen($bytes)>$maximum)throw new DomainException('native_file_content_invalid');
        $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));$extensions=array_map('trim',explode(',',strtolower($allowed)));foreach($extensions as$e)if(!preg_match('/^\.[a-z0-9]{1,16}$/D',$e))throw new DomainException('native_file_configuration_invalid');
        if(!in_array('.'.$ext,$extensions,true))throw new DomainException('native_file_type_not_allowed');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if($ext==='pdf'&&$mime==='application/pdf'&&preg_match('/^%PDF-[12]\.[0-9][\r\n]/',$bytes))return[$name,'application/pdf',$bytes];
        if(in_array($ext,['png','jpg','jpeg'],true)){
            $info=@getimagesizefromstring($bytes);if(!$info||!in_array($mime,['image/png','image/jpeg'],true)||($info['mime']??'')!==$mime||$info[0]<1||$info[1]<1||$info[0]>4096||$info[1]>4096||$info[0]*$info[1]>8000000)throw new DomainException('native_file_content_invalid');
            if(!function_exists('imagecreatefromstring'))throw new RuntimeException('issuer_file_codec_unavailable');$image=@imagecreatefromstring($bytes);if(!$image)throw new DomainException('native_file_content_invalid');
            ob_start();try{if(!imagepng($image,null,6))throw new DomainException('native_file_content_invalid');$out=ob_get_contents();}finally{ob_end_clean();imagedestroy($image);}if(!is_string($out)||strlen($out)>$maximum)throw new DomainException('native_file_content_invalid');return[pathinfo($name,PATHINFO_FILENAME).'.png','image/png',$out];
        }
        if($ext==='txt'&&preg_match('//u',$bytes)===1&&!str_contains($bytes,"\0"))return[$name,'text/plain',$bytes];
        if(in_array($ext,['doc','xls'],true)&&strlen($bytes)>=512&&str_starts_with($bytes,"\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1"))return[$name,'application/octet-stream',$bytes];
        if(in_array($ext,['zip','docx','xlsx'],true)){$names=self::zipEntries($bytes);if($ext==='docx'&&(!isset($names['[Content_Types].xml'])||!isset($names['word/document.xml'])))throw new DomainException('native_file_content_invalid');if($ext==='xlsx'&&(!isset($names['[Content_Types].xml'])||!isset($names['xl/workbook.xml'])))throw new DomainException('native_file_content_invalid');return[$name,'application/octet-stream',$bytes];}
        if($ext==='rar'&&strlen($bytes)>=16&&(str_starts_with($bytes,"Rar!\x1a\x07\x00")||str_starts_with($bytes,"Rar!\x1a\x07\x01\x00")))return[$name,'application/octet-stream',$bytes];
        throw new DomainException('native_file_content_invalid');
    }
    private static function zipEntries(string $data):array
    {
        $end=strrpos($data,"PK\x05\x06");if($end===false||$end+22>strlen($data))throw new DomainException('native_file_content_invalid');$e=unpack('vdisk/vstart/vdiskcount/vcount/Vsize/Voffset/vcomment',substr($data,$end+4,18));
        if($e['disk']!==0||$e['start']!==0||$e['diskcount']!==$e['count']||$e['count']>1000||$end+22+$e['comment']!==strlen($data)||$e['offset']+$e['size']!==$end)throw new DomainException('native_file_content_invalid');
        $pos=$e['offset'];$names=[];$total=0;
        for($n=0;$n<$e['count'];$n++){
            if(substr($data,$pos,4)!=="PK\x01\x02"||$pos+46>$end)throw new DomainException('native_file_content_invalid');
            $c=unpack('vmade/vneeded/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vsize/vname/vextra/vcomment/vdisk/vinternal/Vexternal/Voffset',substr($data,$pos+4,42));$name=substr($data,$pos+46,$c['name']);
            if($c['flags']&1||!in_array($c['method'],[0,8],true)||$c['disk']!==0||$c['name']<1||$pos+46+$c['name']+$c['extra']+$c['comment']>$end||$c['offset']+30>$e['offset']||substr($data,$c['offset'],4)!=="PK\x03\x04"||preg_match('/[\x00-\x1f\x7f\\\\]/',$name)||str_starts_with($name,'/')||preg_match('~(^|/)\.\.(/|$)~',$name)||preg_match('/^[A-Za-z]:/',$name)||isset($names[$name])||(($c['external']>>16)&0170000)===0120000||preg_match('/(?:vbaProject\.bin|\.exe|\.dll|\.php|\.js)$/i',$name))throw new DomainException('native_file_content_invalid');
            $local=unpack('vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vsize/vname/vextra',substr($data,$c['offset']+6,24));$start=$c['offset']+30+$local['name']+$local['extra'];if($local['method']!==$c['method']||$local['flags']!==$c['flags']||substr($data,$c['offset']+30,$local['name'])!==$name||$start+$c['compressed']>$e['offset'])throw new DomainException('native_file_content_invalid');
            $total+=$c['size'];if($total>104857600||$c['size']>max(1048576,$c['compressed']*100))throw new DomainException('native_file_content_invalid');$names[$name]=true;$pos+=46+$c['name']+$c['extra']+$c['comment'];
        }
        if($pos!==$end)throw new DomainException('native_file_content_invalid');return$names;
    }
}
