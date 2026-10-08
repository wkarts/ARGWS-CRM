<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Cron extends App_Controller
{
    public function index($key = '')
    {
        update_option('cron_has_run_from_cli', 1);

        if (defined('APP_CRON_KEY') && ($key != APP_CRON_KEY)) {
            header('HTTP/1.0 401 Unauthorized');

            exit('A chave do cron job informada está incorreta. Ela deve ser igual à definida na constante APP_CRON_KEY.');
        }

        $last_cron_run = get_option('last_cron_run');
        $seconds       = hooks()->apply_filters('cron_functions_execute_seconds', 300);

        if ($last_cron_run == '' || (time() > ($last_cron_run + $seconds))) {
            $this->load->model('cron_model');
            $this->cron_model->run();
        }
    }
}
