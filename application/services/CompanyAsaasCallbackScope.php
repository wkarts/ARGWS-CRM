<?php

declare(strict_types=1);
/** Exact public inbox route before autoload. No staff session or module gateway may be hydrated. */
final class CompanyAsaasCallbackScope
{
    private static bool $active=false;
    public static function prepare($ci):bool
    {
        self::$active=false;
        if($ci->input->is_cli_request()||defined('API')||trim((string)$ci->router->fetch_directory(),'/')!==''||$ci->router->fetch_module()||!in_array(strtolower((string)$ci->router->fetch_class()),['asaas_callback','company_asaas_callback'],true)||strtolower((string)$ci->router->fetch_method())!=='integration')return false;
        if(!isset($ci->uri)||!method_exists($ci->uri,'segment'))return false;
        $segments=[];for($i=1;$i<=4;$i++){$value=$ci->uri->segment($i);if($value===null||$value===false||$value==='')break;$segments[]=(string)$value;}
        if(count($segments)!==3||!in_array($segments[0],['asaas_callback','company_asaas_callback'],true)||$segments[1]!=='integration'||!preg_match('/^[a-f0-9]{32}$/D',$segments[2]))return false;
        self::$active=true;return true;
    }
    public static function active():bool{return self::$active;}
}
