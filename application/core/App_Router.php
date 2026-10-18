<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
 * The guarded standalone installer includes the front controller from a
 * method scope. MX\Modules reads the CI config through its global contract;
 * restore that reference before loading the router if the include scope did
 * not publish it.
 */
global $CFG;
if ((!is_object($CFG) || !method_exists($CFG, 'item')) && function_exists('load_class')) {
    $CFG =& load_class('Config', 'core');
}

/* load the MX_Router class */
require APPPATH . 'third_party/MX/Router.php';

class App_Router extends MX_Router {}
