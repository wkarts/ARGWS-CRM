<?php

declare(strict_types=1);
/** Exact CLI route selected before application autoload; no sessions, models, transports or modules. */
final class CompanyRecurringWorkerScope
{
    private static bool $active=false;
    public static function prepare($ci):bool
    {
        self::$active=false;
        if(PHP_SAPI!=='cli'||!$ci->input->is_cli_request()||defined('API')||trim((string)$ci->router->fetch_directory(),'/')!==''||$ci->router->fetch_module()||strtolower((string)$ci->router->fetch_class())!=='company_recurring_worker'||strtolower((string)$ci->router->fetch_method())!=='index')return false;
        self::$active=true;return true;
    }
    public static function active():bool{return self::$active;}
}
