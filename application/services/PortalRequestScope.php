<?php

declare(strict_types=1);
/** Exact native public route, selected by the server router before application autoload. */
final class PortalRequestScope
{
    private static bool $active=false;
    public static function prepare($ci): bool
    {
        if($ci->input->is_cli_request()||defined('API')||trim((string)$ci->router->fetch_directory(),'/')!==''||$ci->router->fetch_module()||strtolower((string)$ci->router->fetch_class())!=='company_portal'||strtolower((string)$ci->router->fetch_method())!=='index')return false;
        self::$active=true;return true;
    }
    public static function active(): bool{return self::$active;}
}
