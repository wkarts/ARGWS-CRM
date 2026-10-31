<?php

declare(strict_types=1);

require_once __DIR__.'/CompanySettingsIdentity.php';
require_once __DIR__.'/CompanyAssets.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';

/**
 * Publishes a small public projection for the PWA.  The public endpoint never
 * opens a session or a database connection; it only consumes this projection.
 * A failed projection therefore leaves the last valid branding (or V2) intact.
 */
final class NativePwaIdentity
{
    private const FALLBACK_NAME='ARGWS CRM';
    private const THEME='#2563eb';
    private const RUNTIME='assets/pwa/runtime';

    public static function publish($ci):?array
    {
        try{
            $config=self::config();$db=$ci->db;$prefix=db_prefix();
            $primary=(new IssuerResourceAccess($db,$prefix))->primaryId();
            $name=self::installationName($config);$nameSource=$name!==''?'installation':null;
            $theme=self::theme($config['pwa_theme_color']??self::THEME);
            $icons=self::installationIcons($config);$icon=$icons['base'];$maskable=$icons['maskable'];$iconSource=$icon!==null?'installation':null;
            if($name===''){$name=$primary===null?'':trim((new CompanySettingsIdentity($db,$prefix))->displayName($primary));if($name!=='')$nameSource='primary_company';}
            if($name===''){$name=self::FALLBACK_NAME;$nameSource='v2';}
            if($icon===null&&$primary!==null){
                $context=class_exists('CompanyRequestScope',false)?CompanyRequestScope::context():null;
                $actor=$context?->staffId();
                $primaryReadable=is_int($actor)&&$actor>0&&(new IssuerResourceAccess($db,$prefix))->member($primary,$actor);
                if(!$primaryReadable){
                    // A secondary-company administrator must never replace an
                    // already-published primary-company brand with V2 merely
                    // because the primary asset is outside that actor's ACL.
                    if(self::projectionExists())return null;
                }else{
                    try{$branding=(new CompanyAssets($db,$prefix))->brandingForStaff($primary,$actor);$icon=self::dataUriBytes($branding['favicon']??null);$maskable=$icon;if($icon!==null)$iconSource='primary_company';}
                    catch(Throwable $ignored){if(self::projectionExists())return null;}
                }
            }
            if($icon===null){$icon=self::readFile(FCPATH.'assets/pwa/icon-512.png');if($maskable===null)$maskable=self::readFile(FCPATH.'assets/pwa/icon-maskable-512.png')?:$icon;$iconSource='v2';}
            if($maskable===null)$maskable=$icon;
            $files=[];foreach(['icon-192'=>[192,$icon],'icon-512'=>[512,$icon],'icon-maskable-512'=>[512,$maskable],'apple-touch-icon-180'=>[180,$icon],'favicon-16'=>[16,$icon],'favicon-32'=>[32,$icon]] as $key=>$spec)$files[$key]=self::raster($spec[1],$spec[0],$key==='icon-maskable-512');
            $hashes=[];foreach($files as $key=>$bytes)$hashes[$key]=hash('sha256',$bytes);
            $revision=hash('sha256',json_encode(['format'=>2,'name'=>$name,'name_source'=>$nameSource,'theme_color'=>$theme,'icon_source'=>$iconSource,'hashes'=>$hashes],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
            $projection=['format'=>2,'revision'=>$revision,'name'=>$name,'short_name'=>self::shortName($name),'theme_color'=>$theme,'source'=>$iconSource,'name_source'=>$nameSource,'icon_source'=>$iconSource,'files'=>[],'hashes'=>$hashes];
            foreach(array_keys($files) as $key)$projection['files'][$key]=$key.'.png';
            self::write($files,$projection);
            return['revision'=>$revision,'source'=>$iconSource,'name'=>$name];
        }catch(Throwable $error){if(function_exists('log_message'))log_message('error','PWA identity projection was not refreshed; existing projection preserved.');return null;}
    }

    private static function config():array
    {
        $config=[];$file=defined('APPPATH')?APPPATH.'config/branding.php':dirname(__DIR__).'/config/branding.php';if(is_file($file))require $file;return is_array($config)?$config:[];
    }
    private static function installationName(array $config):string
    {
        $environment=getenv('APP_DISPLAY_NAME');if(is_string($environment)&&trim($environment)!=='')return self::name($environment);
        return self::name($config['pwa_installation_name']??'');
    }
    private static function name($value):string
    {
        $value=is_string($value)?trim($value):'';if($value===''||strlen($value)>191||preg_match('/[\x00-\x1f\x7f]/',$value))return'';return$value;
    }
    private static function shortName(string $name):string{return strlen($name)<=32?$name:substr($name,0,29).'...';}
    private static function theme($value):string{return is_string($value)&&preg_match('/^#[0-9a-f]{6}$/i',$value)?strtolower($value):self::THEME;}
    private static function installationIcons(array $config):array
    {
        $read=function($relative):?string{if(!is_string($relative)||!preg_match('~^assets/[A-Za-z0-9_./-]+\.png$~D',$relative)||str_contains($relative,'..'))return null;$root=realpath(FCPATH.'assets');$file=realpath(FCPATH.$relative);if($root===false||$file===false||strpos($file,$root.DIRECTORY_SEPARATOR)!==0||!is_file($file))return null;return self::readFile($file);};
        $base=$read($config['pwa_icon_512']??'')??$read($config['pwa_icon_192']??'');$maskable=$read($config['pwa_icon_maskable_512']??'');return['base'=>$base,'maskable'=>$maskable];
    }
    private static function dataUriBytes($value):?string
    {
        if(!is_string($value)||!preg_match('#^data:image/png;base64,([A-Za-z0-9+/]+={0,2})$#D',$value,$match))return null;$bytes=base64_decode($match[1],true);return is_string($bytes)?$bytes:null;
    }
    private static function readFile(string $file):?string{$bytes=@file_get_contents($file);return is_string($bytes)&&$bytes!==''?$bytes:null;}
    private static function projectionExists():bool
    {
        $root=(defined('FCPATH')?FCPATH:dirname(__DIR__,2)).self::RUNTIME;
        return is_file($root.'/identity.json');
    }
    private static function raster(string $bytes,int $size,bool $maskable):string
    {
        if(!function_exists('imagecreatefromstring'))throw new RuntimeException('issuer_asset_codec_unavailable');$source=@imagecreatefromstring($bytes);if(!$source)throw new DomainException('company_asset_invalid_image');$width=imagesx($source);$height=imagesy($source);if($width<1||$height<1)throw new DomainException('company_asset_invalid_image');$canvas=imagecreatetruecolor($size,$size);imagealphablending($canvas,false);imagesavealpha($canvas,true);$background=$maskable?imagecolorallocatealpha($canvas,37,99,235,0):imagecolorallocatealpha($canvas,0,0,0,127);imagefill($canvas,0,0,$background);$bound=$size*($maskable?0.72:0.88);$scale=min($bound/$width,$bound/$height);$targetWidth=max(1,(int)round($width*$scale));$targetHeight=max(1,(int)round($height*$scale));$x=(int)(($size-$targetWidth)/2);$y=(int)(($size-$targetHeight)/2);imagecopyresampled($canvas,$source,$x,$y,0,0,$targetWidth,$targetHeight,$width,$height);ob_start();imagepng($canvas,null,6);$out=ob_get_contents();ob_end_clean();imagedestroy($source);imagedestroy($canvas);if(!is_string($out)||$out==='')throw new RuntimeException('issuer_asset_codec_failed');return$out;
    }
    private static function write(array $files,array $projection):void
    {
        $dir=(defined('FCPATH')?FCPATH:dirname(__DIR__,2)).self::RUNTIME;if(!is_dir($dir)&&!mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('pwa_projection_directory_unavailable');
        foreach($files as $key=>$bytes){$tmp=$dir.'/.'.$key.'.'.bin2hex(random_bytes(8)).'.tmp';if(file_put_contents($tmp,$bytes,LOCK_EX)!==strlen($bytes)||!rename($tmp,$dir.'/'.$key.'.png')){if(is_file($tmp))@unlink($tmp);throw new RuntimeException('pwa_projection_write_failed');}@chmod($dir.'/'.$key.'.png',0640);}
        $json=json_encode($projection,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";$tmp=$dir.'/.identity.'.bin2hex(random_bytes(8)).'.tmp';if(file_put_contents($tmp,$json,LOCK_EX)!==strlen($json)||!rename($tmp,$dir.'/identity.json')){if(is_file($tmp))@unlink($tmp);throw new RuntimeException('pwa_projection_manifest_failed');}@chmod($dir.'/identity.json',0640);
    }
}
