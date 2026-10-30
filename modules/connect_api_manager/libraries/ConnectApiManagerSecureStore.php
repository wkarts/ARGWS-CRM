<?php

defined('BASEPATH') or exit('No direct script access allowed');

class ConnectApiManagerSecureStore
{
    private $CI;
    private const PREFIX = 'enc:v1:';

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->library('encryption');
    }

    public function encrypt($value)
    {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }
        if (strpos($value, self::PREFIX) === 0) {
            return $value;
        }
        return self::PREFIX . base64_encode($this->CI->encryption->encrypt($value));
    }

    public function decrypt($value)
    {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }
        if (strpos($value, self::PREFIX) !== 0) {
            return $value;
        }
        $raw = base64_decode(substr($value, strlen(self::PREFIX)), true);
        if ($raw === false) {
            return '';
        }
        $plain = $this->CI->encryption->decrypt($raw);
        return $plain === false ? '' : (string) $plain;
    }
}
