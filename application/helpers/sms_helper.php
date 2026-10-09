<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Gatilhos complementares opcionais da aplicação (sem workers adicionais).
require_once APPPATH . 'helpers/sms_events_helper.php';

hooks()->add_action('admin_init', 'maybe_test_sms_gateway');

function maybe_test_sms_gateway()
{
    $CI = &get_instance();
    if (!$CI->input->post('sms_gateway_test')) {
        return;
    }

    if (!is_admin() || $CI->input->method(true) !== 'POST') {
        $CI->output->set_status_header(403)->set_content_type('application/json')
            ->set_output(json_encode(['success' => false, 'error' => 'Acesso não autorizado.']))->_display();
        exit;
    }

    $id = (string) $CI->input->post('id');
    if ($id !== 'connect_api_connector'
        || !isset($CI->sms_connect_api_connector)
        || (string) get_option('sms_connect_api_connector_active') !== '1') {
        $CI->output->set_status_header(400)->set_content_type('application/json')
            ->set_output(json_encode(['success' => false, 'error' => 'Somente Connect|API está disponível para envios.']))->_display();
        exit;
    }

    $number = trim((string) $CI->input->post('number'));
    $message = trim((string) $CI->input->post('message'));
    if ($number === '' || $message === '' || mb_strlen($message) > 4000) {
        $CI->output->set_status_header(422)->set_content_type('application/json')
            ->set_output(json_encode(['success' => false, 'error' => 'Informe destinatário e mensagem de até 4.000 caracteres.']))->_display();
        exit;
    }

    $gateway = $CI->sms_connect_api_connector;
    unset($GLOBALS['sms_error']);
    $gateway->set_test_mode(true);
    try {
        $success = $gateway->send($number, clear_textarea_breaks(nl2br($message))) === true;
    } finally {
        $gateway->set_test_mode(false);
    }

    $response = ['success' => $success];
    if (!$success) {
        $response['error'] = 'Não foi possível enviar. Verifique a configuração do Conector e os logs.';
    }

    $CI->output->set_content_type('application/json')->set_output(json_encode($response))->_display();
    exit;
}

hooks()->add_action('admin_init', '_maybe_sms_gateways_settings_group');

function _maybe_sms_gateways_settings_group($groups)
{
    $CI = &get_instance();

    $gateways = $CI->app_sms->get_gateways();

    if (count($gateways) > 0) {
        $CI->app->add_settings_section_child('other', 'sms', [
            'name'     => 'SMS',
            'view'     => 'admin/settings/includes/sms',
            'position' => 60,
            'icon'     => 'fa-regular fa-message',
        ]);
    }
}

hooks()->add_action('app_init', 'app_init_sms_gateways');

function app_init_sms_gateways()
{
    $CI = &get_instance();

    // Preserva as classes históricas em disco, mas não as inicializa nem
    // permite seu uso como prestadores de envio. Um único transporte: Connect|API.
    $gateways = hooks()->apply_filters('sms_gateways', []);
    foreach (array_unique(is_array($gateways) ? $gateways : []) as $gateway) {
        if ($gateway === 'connect_api_connector/sms_connect_api_connector') {
            $CI->load->library($gateway);
        }
    }
}

function is_sms_trigger_active($trigger = '')
{
    $CI     = &get_instance();
    $active = $CI->app_sms->get_active_gateway();

    if (! $active) {
        return false;
    }

    return $CI->app_sms->is_trigger_active($trigger);
}

function can_send_sms_based_on_creation_date($data_date_created)
{
    $now       = time();
    $your_date = strtotime($data_date_created);
    $datediff  = $now - $your_date;

    $days_diff = floor($datediff / (60 * 60 * 24));

    return $days_diff < DO_NOT_SEND_SMS_ON_DATA_OLDER_THEN || $days_diff == DO_NOT_SEND_SMS_ON_DATA_OLDER_THEN;
}
