<?php

defined('BASEPATH') or exit('No direct script access allowed');

class ConnectApiConnectorConfig
{
    private $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->library(CONNECT_API_CONNECTOR_MODULE . '/ConnectApiConnectorSecureStore');
    }

    public function all()
    {
        return [
            'base_url' => get_option('connect_api_connector_api_url'),
            'instance_name' => get_option('connect_api_connector_instance_name'),
            'instance_token' => $this->CI->connectapiconnectorsecurestore->decrypt(get_option('connect_api_connector_instance_token')),
            'instance_number' => preg_replace('/\D+/', '', (string) get_option('connect_api_connector_instance_number')),
            'verify_tls' => get_option('connect_api_connector_verify_tls') !== '0',
            'timeout' => (int) (get_option('connect_api_connector_timeout') ?: 30),
            'default_country' => preg_replace('/\D+/', '', (string) get_option('connect_api_connector_default_country')),
            'default_area' => preg_replace('/\D+/', '', (string) get_option('connect_api_connector_default_area')),
            'auto_normalize' => get_option('connect_api_connector_auto_normalize') !== '0',
            'sms_takeover' => get_option('connect_api_connector_sms_takeover') !== '0',
        ];
    }

    public function normalizeNumber($number)
    {
        $config = $this->all();
        $digits = preg_replace('/\D+/', '', (string) $number);
        if (!$config['auto_normalize'] || $digits === '') {
            return $digits;
        }

        $country = $config['default_country'] ?: '55';
        $area = $config['default_area'];
        if (strpos($digits, $country) === 0 && strlen($digits) >= 12) {
            return $digits;
        }
        if ((strlen($digits) === 8 || strlen($digits) === 9) && $area !== '') {
            return $country . $area . $digits;
        }
        if (strlen($digits) === 10 || strlen($digits) === 11) {
            return $country . $digits;
        }
        return $digits;
    }
}
