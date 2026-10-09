<?php

defined('BASEPATH') or exit('No direct script access allowed');

/* load the MX_Router class */
require APPPATH . 'third_party/MX/Router.php';

class App_Router extends MX_Router
{
    public function __construct($routing = null)
    {
        parent::__construct($routing);

        if (PHP_SAPI === 'cli' && getenv('ARGWS_SETUP_MIGRATION_TOKEN') !== false) {
            $directory = is_string($this->directory) && $this->directory !== ''
                ? $this->directory
                : '(root)';
            fwrite(STDERR, '[ARGWS CRM setup] Rota CLI resolvida: classe=' . $this->class
                . '; método=' . $this->method
                . '; diretório=' . $directory . '.' . PHP_EOL);
        }
    }
}
