<?php
declare(strict_types=1);
require_once __DIR__.'/CompanySettingsStorage.php';
/** Existing native upload options, resolved for one attested company only. */
final class NativeCompanyUploadPreferences
{
    public static function fields():array{return ['allowed_files','media_max_file_size_upload'];}
    public static function defaultValue(string $field):string
    {
        return match($field){'allowed_files'=>'.png,.jpg,.jpeg,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar,.txt','media_max_file_size_upload'=>'10',default=>throw new DomainException('native_upload_option_invalid')};
    }
    public static function prepare(array $values):array
    {
        if(array_diff(array_keys($values),self::fields()))throw new DomainException('native_upload_option_invalid');
        foreach($values as$key=>$value){
            if(!is_string($value))throw new DomainException('native_upload_option_invalid');
            if($key==='media_max_file_size_upload'){
                if(!preg_match('/^[1-9][0-9]{0,3}$/D',$value)||(int)$value>1024)throw new DomainException('native_upload_option_invalid');
            }else{
                if(strlen($value)>2000||!preg_match('/^\.[a-z0-9]{1,16}(?:,\.[a-z0-9]{1,16})*$/D',$value))throw new DomainException('native_upload_option_invalid');
                $extensions=explode(',',$value);
                if(count($extensions)!==count(array_unique($extensions)))throw new DomainException('native_upload_option_invalid');
                foreach($extensions as$extension)if(preg_match('/^\.(?:php[0-9]*|phtml|phar|cgi|pl|py|sh|bash|exe|dll|com|bat|cmd|htaccess|htpasswd|asp|aspx|jsp|html?|svg|js)$/D',$extension))throw new DomainException('native_upload_option_invalid');
            }
        }
        return$values;
    }
    public static function read($db,string $prefix,int $issuer,int $actor):array
    {
        $acl=new IssuerResourceAccess($db,$prefix);if(!$acl->member($issuer,$actor))throw new DomainException('native_upload_denied');
        $storage=new CompanySettingsStorage($db,$prefix);$values=[];
        foreach(self::fields()as$key)$values[$key]=$storage->exists($issuer,$key)?$storage->get($issuer,$key):self::defaultValue($key);
        self::prepare($values);if(!$acl->member($issuer,$actor))throw new DomainException('native_upload_denied');
        return$values;
    }
    /** Metadata limit only: each upload endpoint must still validate MIME, ownership and storage. */
    public static function maxBytes(array $values):int{self::prepare($values);if(!isset($values['media_max_file_size_upload']))throw new DomainException('native_upload_option_invalid');return(int)$values['media_max_file_size_upload']*1024*1024;}
}
