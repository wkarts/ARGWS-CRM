<?php

declare(strict_types=1);
/** Exact bearer endpoints before any optional module, staff session or application hydration. */
final class CompanyApiScope
{
    private static bool $active=false;
    /** Veto-only pre-system hint: no identity, credential or resource authority is inferred. */
    public static function earlyCandidate(array $server):bool
    {
        if(PHP_SAPI==='cli'&&!isset($server['REQUEST_URI']))return false;
        $uri=$server['REQUEST_URI']??null;$script=$server['SCRIPT_NAME']??null;
        if(!is_string($uri)||!is_string($script)||preg_match('/[\x00-\x1f\x7f]/',$uri)||basename($script)!=='index.php')return false;
        $path=parse_url($uri,PHP_URL_PATH);if(!is_string($path))return false;$path=preg_replace('#/+#','/',rawurldecode($path));$base=rtrim(str_replace('\\','/',dirname($script)),'/.');
        foreach([$base.'/company_api',$base.'/index.php/company_api'] as $prefix)if(strcasecmp($path,$prefix)===0||strncasecmp($path,$prefix.'/',strlen($prefix)+1)===0)return true;return false;
    }
    public static function prepare($ci):bool
    {
        self::$active=false;if($ci->input->is_cli_request()||defined('API')||trim((string)$ci->router->fetch_directory(),'/')!==''||$ci->router->fetch_module()||strtolower((string)$ci->router->fetch_class())!=='company_api'||!in_array(strtolower((string)$ci->router->fetch_method()),['drafts','draft_change','draft_cancel','draft_issue','invoices','invoice_pdf','financial','payments','payment_reverse','credits','credit_pdf','credit_apply','credit_unapply','credit_refund','credit_refund_reverse','credit_void'],true))return false;
        if(!isset($ci->uri)||!method_exists($ci->uri,'segment'))return false;$segments=[];for($i=1;$i<=5;$i++){$value=$ci->uri->segment($i);if($value===null||$value===false||$value==='')break;$segments[]=(string)$value;}
        if(($segments[0]??null)!=='company_api')return false;$method=$segments[1]??null;if($method!==strtolower((string)$ci->router->fetch_method()))return false;
        if($method==='financial'){if(count($segments)!==2)return false;}
        elseif(in_array($method,['invoices','credits','drafts'],true)){if(count($segments)!==2&&(count($segments)!==3||!preg_match('/^[1-9][0-9]*$/D',$segments[2])))return false;}
        elseif(in_array($method,['draft_change','draft_cancel','draft_issue','invoice_pdf','payments','payment_reverse','credit_pdf','credit_apply','credit_unapply','credit_refund','credit_refund_reverse','credit_void'],true)){if(count($segments)!==3||!preg_match('/^[1-9][0-9]*$/D',$segments[2]))return false;}else return false;
        self::$active=true;return true;
    }
    public static function active():bool{return self::$active;}
}
