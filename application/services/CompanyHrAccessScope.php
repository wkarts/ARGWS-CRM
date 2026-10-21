<?php

declare(strict_types=1);

/** Public recipient surface selected before legacy module/session identity hydration. */
final class CompanyHrAccessScope
{
    private static bool $active=false;
    private static bool $requested=false;
    public static function earlyCandidate(array $server):bool
    {
        if(PHP_SAPI==='cli'&&!isset($server['REQUEST_URI']))return false;$uri=$server['REQUEST_URI']??null;$script=$server['SCRIPT_NAME']??null;if(!is_string($uri)||!is_string($script)||preg_match('/[\x00-\x1f\x7f]/',$uri)||basename($script)!=='index.php')return false;
        $path=parse_url($uri,PHP_URL_PATH);if(!is_string($path))return false;$path=preg_replace('#/+#','/',rawurldecode($path));$base=rtrim(str_replace('\\','/',dirname($script)),'/.');foreach([$base.'/company_hr_access',$base.'/index.php/company_hr_access'] as $prefix)if(strcasecmp($path,$prefix)===0||strncasecmp($path,$prefix.'/',strlen($prefix)+1)===0)return true;return false;
    }
    public static function prepare($ci):bool
    {
        self::$active=false;self::$requested=false;if($ci->input->is_cli_request()||defined('API')||trim((string)$ci->router->fetch_directory(),'/')!==''||$ci->router->fetch_module()||strtolower((string)$ci->router->fetch_class())!=='company_hr_access')return false;
        self::$requested=true;if(strtolower((string)$ci->router->fetch_method())!=='index'||!isset($ci->uri)||!method_exists($ci->uri,'segment'))return false;
        $secret=$ci->uri->segment(3);$extra=$ci->uri->segment(4);
        if($ci->uri->segment(1)!=='company_hr_access'||$ci->uri->segment(2)!=='index'||!is_string($secret)||!preg_match('/^hrco_[a-f0-9]{64}$/D',$secret)||!in_array($extra,[null,false,''],true))return false;
        if(!self::canonicalRequest($_SERVER,['company_hr_access','index',$secret]))return false;self::$active=true;return true;
    }
    /** Decoded router segments do not attest the original recipient namespace. */
    private static function canonicalRequest(array $server,array $segments):bool
    {
        $uri=$server['REQUEST_URI']??null;$script=$server['SCRIPT_NAME']??null;
        if(!is_string($uri)||!is_string($script)||!str_starts_with($uri,'/')||str_starts_with($uri,'//')||str_contains($uri,'#')||preg_match('/[\x00-\x1f\x7f]/',$uri)||!str_starts_with($script,'/')||basename($script)!=='index.php'||str_contains($script,'\\')||str_contains($script,'//')||preg_match('/[\x00-\x20\x7f%?#]/',$script))return false;
        foreach(explode('/',$script) as $part)if($part==='.'||$part==='..')return false;
        $path=parse_url($uri,PHP_URL_PATH);if(!is_string($path))return false;$base=rtrim(dirname($script),'/');$tail='/'.implode('/',$segments);
        return $path===$base.$tail||$path===$base.'/index.php'.$tail;
    }
    public static function active():bool{return self::$active;}
    public static function requested():bool{return self::$requested;}
}
