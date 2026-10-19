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

class App_Router extends MX_Router {}
