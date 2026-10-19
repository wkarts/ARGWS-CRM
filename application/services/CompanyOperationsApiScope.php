<?php

declare(strict_types=1);
/** Exact native bearer surface, prepared before module/session hydration. */
final class CompanyOperationsApiScope
{
    private static bool $active=false;
    private static bool $requested=false;
    /** Veto-only pre-system hint: no identity, credential or resource authority is inferred. */
    public static function earlyCandidate(array $server):bool
    {
        if(PHP_SAPI==='cli'&&!isset($server['REQUEST_URI']))return false;
        $uri=$server['REQUEST_URI']??null;$script=$server['SCRIPT_NAME']??null;
        if(!is_string($uri)||!is_string($script)||preg_match('/[\x00-\x1f\x7f]/',$uri)||basename($script)!=='index.php')return false;
        $path=parse_url($uri,PHP_URL_PATH);if(!is_string($path))return false;$path=preg_replace('#/+#','/',rawurldecode($path));$base=rtrim(str_replace('\\','/',dirname($script)),'/.');
        foreach([$base.'/company_operations_api',$base.'/index.php/company_operations_api'] as $prefix)if(strcasecmp($path,$prefix)===0||strncasecmp($path,$prefix.'/',strlen($prefix)+1)===0)return true;return false;
    }
    public static function prepare($ci):bool
    {
        self::$active=false;self::$requested=false;if($ci->input->is_cli_request()||defined('API')||trim((string)$ci->router->fetch_directory(),'/')!==''||$ci->router->fetch_module()||strtolower((string)$ci->router->fetch_class())!=='company_operations_api')return false;
        self::$requested=true;$regular=['projects','tasks','standalone_tasks','contracts','tickets'];$files=['project_files','task_files','contract_files','ticket_files'];$extras=['project_milestones','task_dependencies','project_follow','task_follow'];$method=strtolower((string)$ci->router->fetch_method());if(!in_array($method,array_merge($regular,$files,$extras),true)||!isset($ci->uri)||!method_exists($ci->uri,'segment'))return false;
        $segments=[];for($i=1;$i<=6;$i++){$v=$ci->uri->segment($i);if($v===null||$v===false||$v==='')break;$segments[]=(string)$v;}
        if(($segments[0]??null)!=='company_operations_api'||($segments[1]??null)!==$method)return false;
        if(in_array($method,$regular,true)){if(!in_array(count($segments),[2,3],true))return false;}
        elseif(in_array($method,$files,true)){if(!in_array(count($segments),[3,4],true)||(count($segments)===4&&!preg_match('/^[a-f0-9]{64}$/D',$segments[3])))return false;}
        elseif(count($segments)!==3)return false;
        if(isset($segments[2])&&(!preg_match('/^[1-9][0-9]{0,9}$/D',$segments[2])||(float)$segments[2]>2147483647))return false;self::$active=true;return true;
    }
    /** Keeps malformed attempts on this native controller isolated from legacy hydration too. */
    public static function requested():bool{return self::$requested;}
    public static function active():bool{return self::$active;}
}
