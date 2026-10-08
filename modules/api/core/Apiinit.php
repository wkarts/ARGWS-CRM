<?php

namespace modules\api\core;

/**
 * Compatibility bridge for older module integrations. ARGWS does not perform
 * remote purchase, license, domain, IP, or periodic validation.
 */
class Apiinit
{
    public static function the_da_vinci_code($module_name): bool
    {
        return true;
    }

    public static function ease_of_mind($module_name): void
    {
    }

    public static function activate($module): bool
    {
        return true;
    }

    public static function pre_validate($module_name, $code = ''): array
    {
        return ['status' => false, 'message' => 'A validação de compra foi removida.'];
    }
}
