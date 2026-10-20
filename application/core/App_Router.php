<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
 * The guarded standalone installer includes the front controller from a
 * method scope. MX\Modules reads CI config through $GLOBALS['CFG']; ensure
 * the global symbol is populated before loading the router.
 */
if ((!isset($GLOBALS['CFG']) || !is_object($GLOBALS['CFG']) || !method_exists($GLOBALS['CFG'], 'item'))
    && function_exists('load_class')) {
    $GLOBALS['CFG'] = load_class('Config', 'core');
}
global $CFG;

/* load the MX_Router class */
require APPPATH . 'third_party/MX/Router.php';

class App_Router extends MX_Router
{
    public function locate($segments)
    {
        // Empresas is a native controller. Its retained module directory is
        // historical data and must not participate in MX controller/helper lookup.
        if (($segments[0] ?? null) === ADMIN_URI && strtolower((string) ($segments[1] ?? '')) === 'issuers') {
            $this->module = null;
            $this->directory = 'admin/';
            return array_slice($segments, 1);
        }
        if (strtolower((string) ($segments[0] ?? '')) === 'issuers') {
            // The retired unprefixed module has no public runtime endpoint.
            $this->module = null;
            $this->directory = '';
            return parent::locate(['__retired_issuers_module_has_no_route__']);
        }
        return parent::locate($segments);
    }
}
