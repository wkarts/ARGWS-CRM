<?php

declare(strict_types=1);
/** Exact bearer endpoints before any optional module, staff session or application hydration. */
final class CompanyApiScope
{
    private static bool $active=false;
    public static function prepare($ci):bool
    {
        self::$active=false;if($ci->input->is_cli_request()||defined('API')||trim((string)$ci->router->fetch_directory(),'/')!==''||$ci->router->fetch_module()||strtolower((string)$ci->router->fetch_class())!=='company_api'||!in_array(strtolower((string)$ci->router->fetch_method()),['invoices','invoice_pdf','financial'],true))return false;
        if(!isset($ci->uri)||!method_exists($ci->uri,'segment'))return false;$segments=[];for($i=1;$i<=5;$i++){$value=$ci->uri->segment($i);if($value===null||$value===false||$value==='')break;$segments[]=(string)$value;}
        if(($segments[0]??null)!=='company_api')return false;$method=$segments[1]??null;if($method==='financial'){if(count($segments)!==2)return false;}elseif($method==='invoices'){if(count($segments)!==2&&(count($segments)!==3||!preg_match('/^[1-9][0-9]*$/D',$segments[2])))return false;}elseif($method==='invoice_pdf'){if(count($segments)!==3||!preg_match('/^[1-9][0-9]*$/D',$segments[2]))return false;}else return false;
        self::$active=true;return true;
    }
    public static function active():bool{return self::$active;}
}
