<?php

declare(strict_types=1);
/** Exact public mTLS inbox boundary before autoload; no session or global application providers. */
final class CompanyBbCallbackScope
{
    private static bool $active=false;
    public static function prepare($ci):bool
    {
        self::$active=false;
        if($ci->input->is_cli_request()||defined('API')
            ||trim((string)$ci->router->fetch_directory(),'/')!=='../../modules/banco_brasil/controllers'
            ||strtolower((string)$ci->router->fetch_module())!=='banco_brasil'
            ||strtolower((string)$ci->router->fetch_class())!=='notifications'
            ||strtolower((string)$ci->router->fetch_method())!=='index')return false;
        // MX can route an admin-prefixed URL into the same module directory. It is never a machine callback.
        if(!isset($ci->uri)||!method_exists($ci->uri,'segment'))return false;
        $segments=[];for($i=1;$i<=4;$i++){$value=$ci->uri->segment($i);if($value===null||$value===false||$value==='')break;$segments[]=(string)$value;}
        if($segments!==['banco_brasil','notifications']&&$segments!==['banco_brasil','notifications','index'])return false;
        self::$active=true;return true;
    }
    public static function active():bool{return self::$active;}
}
