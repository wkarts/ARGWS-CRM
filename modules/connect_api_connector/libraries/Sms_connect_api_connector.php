<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Sms_connect_api_connector extends App_sms
{
    public function __construct()
    {
        parent::__construct();
        $this->add_gateway('connect_api_connector', [
            'name' => 'Connect|API',
            'info' => '<p><strong>Connect|API</strong> transforma os gatilhos SMS do ARGWS CRM em mensagens WhatsApp usando a instância vinculada no Conector. URL, instância e token são administrados no próprio módulo.</p>',
            'options' => [],
        ]);
    }

    public function send($number, $message, $trigger = null)
    {
        $CI = &get_instance();
        $CI->load->library(CONNECT_API_CONNECTOR_MODULE . '/ConnectApiConnectorConfig');
        $CI->load->library(CONNECT_API_CONNECTOR_MODULE . '/ConnectApiConnectorClient');

        try {
            $config = $CI->connectapiconnectorconfig->all();
            $normalized = $CI->connectapiconnectorconfig->normalizeNumber($number);
            if ($normalized === '') {
                throw new RuntimeException('Número de destino vazio ou inválido.');
            }
            $CI->connectapiconnectorclient->configure($config)->sendText($normalized, $message);
            $triggerInfo = $trigger ? ' [gatilho: ' . $trigger . ']' : '';
            log_activity('Connect|API Connector: notificação enviada para ' . $this->maskNumber($normalized) . $triggerInfo);
            return true;
        } catch (Throwable $e) {
            $this->set_error('Connect|API: ' . $e->getMessage());
            $triggerInfo = $trigger ? ' [gatilho: ' . $trigger . ']' : '';
            log_activity('Connect|API Connector: falha de envio' . $triggerInfo . ' - ' . $e->getMessage());
            return false;
        }
    }

    private function maskNumber($number)
    {
        $number = (string) $number;
        if (strlen($number) <= 4) return $number;
        return str_repeat('*', strlen($number) - 4) . substr($number, -4);
    }
}
