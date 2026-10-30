<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_100 extends App_module_migration
{
    public function up()
    {
        add_option('connect_api_connector_api_url', '');
        add_option('connect_api_connector_instance_name', '');
        add_option('connect_api_connector_instance_token', '');
        add_option('connect_api_connector_verify_tls', '1');
        add_option('connect_api_connector_timeout', '30');
        add_option('connect_api_connector_default_country', '55');
        add_option('connect_api_connector_default_area', '');
        add_option('connect_api_connector_auto_normalize', '1');
    }
}
