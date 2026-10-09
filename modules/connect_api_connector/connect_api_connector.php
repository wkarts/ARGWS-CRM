<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Connect|API Conector
Description: Conector operacional de uma instância do Connect|API para mensagens, notificações e eventos do sistema.
Author: ARGWS
Version: 1.1.2
Requires at least: 2.9.4
*/

define('CONNECT_API_CONNECTOR_MODULE', 'connect_api_connector');
define('CONNECT_API_CONNECTOR_VERSION', '1.1.2');
define('CONNECT_API_CONNECTOR_PERMISSION', 'connect-api-connector');
// Mantém os IDs históricos usados pelo módulo antigo para preservar as mensagens já configuradas no ARGWS CRM.
define('CONNECT_API_CONNECTOR_TRIGGER_INVOICE_SENT', 'invoice_send_to_customer2');
define('CONNECT_API_CONNECTOR_TRIGGER_PAYMENT_RECORDED', 'invoice_payment_recorded');
// IDs usados nas versões 1.0.0-1.0.7; mantidos apenas para migração de configuração.
define('CONNECT_API_CONNECTOR_TRIGGER_INVOICE_SENT_V107', 'connect_api_connector_invoice_sent');
define('CONNECT_API_CONNECTOR_TRIGGER_PAYMENT_RECORDED_V107', 'connect_api_connector_payment_recorded');

register_activation_hook(CONNECT_API_CONNECTOR_MODULE, 'connect_api_connector_activate');
register_deactivation_hook(CONNECT_API_CONNECTOR_MODULE, 'connect_api_connector_deactivate');
register_uninstall_hook(CONNECT_API_CONNECTOR_MODULE, 'connect_api_connector_uninstall');
register_language_files(CONNECT_API_CONNECTOR_MODULE, [CONNECT_API_CONNECTOR_MODULE]);

if (!function_exists('connect_api_register_parent_menu')) {
    function connect_api_register_parent_menu()
    {
        static $registered = false;
        if ($registered) {
            return;
        }

        $CI = &get_instance();
        $CI->app_menu->add_sidebar_menu_item('connect-api', [
            'name'     => _l('connect_api_menu_root'),
            'position' => 78,
            'icon'     => 'fa fa-random',
        ]);
        $registered = true;
    }
}

hooks()->add_action('admin_init', 'connect_api_connector_register_permissions');
hooks()->add_action('admin_init', 'connect_api_connector_register_menu');
hooks()->add_filter('sms_gateways', 'connect_api_connector_sms_gateways');
hooks()->add_filter('sms_triggers', 'connect_api_connector_sms_triggers');
hooks()->add_filter('sms_gateway_available_triggers', 'connect_api_connector_sms_triggers');
hooks()->add_action('invoice_sent', 'connect_api_connector_invoice_sent');
// O ARGWS CRM já dispara invoice_payment_recorded diretamente pelo App_sms; não duplicamos esse envio.
hooks()->add_action('app_init', 'connect_api_connector_ensure_sms_takeover', 99);
hooks()->add_action('app_init', 'connect_api_connector_disable_legacy_sms_hooks', 1000);

function connect_api_connector_activate()
{
    require_once __DIR__ . '/install.php';
    connect_api_connector_install();
}

function connect_api_connector_deactivate()
{
    // Configuração preservada.
}

function connect_api_connector_uninstall()
{
    // Não remove configuração automaticamente.
}

function connect_api_connector_register_permissions()
{
    $config = [];
    $config['capabilities'] = [
        'view'      => _l('connect_api_connector_permission_view'),
        'configure' => _l('connect_api_connector_permission_configure'),
        'send'      => _l('connect_api_connector_permission_send'),
        'reconnect' => _l('connect_api_connector_permission_reconnect'),
    ];
    register_staff_capabilities(CONNECT_API_CONNECTOR_PERMISSION, $config, _l('connect_api_connector_title'));
}

function connect_api_connector_register_menu()
{
    if (!is_admin() && !staff_can('view', CONNECT_API_CONNECTOR_PERMISSION)) {
        return;
    }

    connect_api_register_parent_menu();
    $CI = &get_instance();
    $CI->app_menu->add_sidebar_children_item('connect-api', [
        'slug'     => 'connect-api-connector',
        'name'     => _l('connect_api_connector_menu'),
        'href'     => admin_url('connect_api_connector'),
        'position' => 2,
    ]);
}

function connect_api_connector_can($capability)
{
    return is_admin() || staff_can($capability, CONNECT_API_CONNECTOR_PERMISSION);
}

function connect_api_connector_sms_gateways($gateways)
{
    $gateway = CONNECT_API_CONNECTOR_MODULE . '/sms_connect_api_connector';
    if (!in_array($gateway, $gateways, true)) {
        $gateways[] = $gateway;
    }
    return $gateways;
}

function connect_api_connector_sms_triggers($triggers)
{
    $invoiceFields = [
        '{contact_firstname}', '{contact_lastname}', '{client_company}',
        '{client_vat_number}', '{client_id}', '{invoice_link}',
        '{invoice_number}', '{invoice_duedate}', '{invoice_date}',
        '{invoice_status}', '{invoice_subtotal}', '{invoice_total}',
    ];

    // Gatilho extra que existia no WhatsAppV2 e não faz parte do núcleo do ARGWS CRM:
    // envio da fatura imediatamente após invoice_sent.
    $triggers[CONNECT_API_CONNECTOR_TRIGGER_INVOICE_SENT] = [
        'merge_fields' => $invoiceFields,
        'label' => _l('connect_api_connector_trigger_invoice_sent'),
        'info' => _l('connect_api_connector_trigger_invoice_sent_info'),
    ];

    // O invoice_payment_recorded já é um gatilho nativo do ARGWS CRM. Só criamos a definição
    // caso uma instalação muito antiga não o tenha fornecido.
    if (!isset($triggers[CONNECT_API_CONNECTOR_TRIGGER_PAYMENT_RECORDED])) {
        $triggers[CONNECT_API_CONNECTOR_TRIGGER_PAYMENT_RECORDED] = [
            'merge_fields' => $invoiceFields,
            'label' => _l('connect_api_connector_trigger_payment_recorded'),
            'info' => _l('connect_api_connector_trigger_payment_recorded_info'),
        ];
    }

    return $triggers;
}

function connect_api_connector_invoice_sent($invoiceId)
{
    if (!connect_api_connector_sms_takeover_enabled()) return;
    connect_api_connector_trigger_for_invoice($invoiceId, CONNECT_API_CONNECTOR_TRIGGER_INVOICE_SENT, 'invoice_overdue_notice');
}

// Mantido como API de compatibilidade. O ARGWS CRM moderno já executa o gatilho
// invoice_payment_recorded pelo App_sms dentro do fluxo de pagamento.
function connect_api_connector_payment_recorded($invoiceId)
{
    connect_api_connector_trigger_for_invoice($invoiceId, CONNECT_API_CONNECTOR_TRIGGER_PAYMENT_RECORDED, 'invoice_payment_recorded');
}

function connect_api_connector_trigger_for_invoice($invoiceId, $trigger, $templateName)
{
    $CI = &get_instance();
    $CI->load->helper('sms_helper');
    $CI->load->model('invoices_model');
    $CI->load->model('clients_model');

    $invoice = $CI->invoices_model->get($invoiceId);
    if (!$invoice || !isset($invoice->clientid)) {
        log_activity('Connect|API Connector: evento ignorado; fatura não localizada para ID ' . (int) $invoiceId);
        return;
    }

    if (!is_sms_trigger_active($trigger)) {
        return;
    }

    $contacts = $CI->clients_model->get_contacts($invoice->clientid, ['active' => 1]);
    foreach ($contacts as $contact) {
        if (empty($contact['phonenumber'])) {
            continue;
        }
        $template = mail_template($templateName, $invoice, $contact);
        $mergeFields = $template->get_merge_fields();
        $CI->app_sms->trigger($trigger, $contact['phonenumber'], $mergeFields);
    }
}

function connect_api_connector_sms_takeover_enabled()
{
    return get_option('connect_api_connector_sms_takeover') !== '0';
}

function connect_api_connector_is_configured()
{
    return trim((string) get_option('connect_api_connector_api_url')) !== ''
        && trim((string) get_option('connect_api_connector_instance_name')) !== ''
        && trim((string) get_option('connect_api_connector_instance_token')) !== '';
}

function connect_api_connector_activate_sms_gateway()
{
    $CI = &get_instance();
    if (!isset($CI->app_sms) || !is_object($CI->app_sms)) {
        return false;
    }

    $gateways = $CI->app_sms->get_gateways();
    foreach (is_array($gateways) ? $gateways : [] as $gateway) {
        $id = isset($gateway['id']) ? (string) $gateway['id'] : '';
        if ($id === '') {
            continue;
        }
        $option = 'sms_' . $id . '_active';
        $desired = $id === 'connect_api_connector' ? '1' : '0';
        if ((string) get_option($option) !== $desired) {
            update_option($option, $desired);
        }
    }

    // Compatibilidade explícita com o gateway legado, mesmo se o módulo antigo
    // não estiver carregado na requisição atual.
    if ((string) get_option('sms_whatsapiv2_active') === '1') {
        update_option('sms_whatsapiv2_active', '0');
    }

    return true;
}

function connect_api_connector_ensure_sms_takeover()
{
    if (!connect_api_connector_sms_takeover_enabled() || !connect_api_connector_is_configured()) {
        return;
    }

    connect_api_connector_activate_sms_gateway();
}

function connect_api_connector_disable_legacy_sms_hooks()
{
    if (!connect_api_connector_sms_takeover_enabled()) return;
    // Se o módulo legado ainda estiver instalado, neutraliza apenas os hooks de envio dele.
    // O objetivo é evitar que invoice_to_customer2/invoice_payment_recorded_action chamem o
    // gateway Connect|API uma segunda vez. Não depende de desinstalar o módulo antigo.
    try {
        $hooks = hooks();
        if (is_object($hooks) && method_exists($hooks, 'remove_action')) {
            if (function_exists('invoice_to_customer2')) $hooks->remove_action('invoice_sent', 'invoice_to_customer2');
            if (function_exists('invoice_payment_recorded_action')) $hooks->remove_action('invoice_payment_recorded', 'invoice_payment_recorded_action');
        }
    } catch (Throwable $ignored) {}
}

function connect_api_connector_sms_gateway_is_active()
{
    return (string) get_option('sms_connect_api_connector_active') === '1';
}

function connect_api_connector_translate_state($state)
{
    $state = strtolower(trim((string) $state));
    $map = [
        'open' => 'Conectada',
        'connected' => 'Conectada',
        'connecting' => 'Conectando',
        'close' => 'Desconectada',
        'closed' => 'Desconectada',
        'disconnected' => 'Desconectada',
        'offline' => 'Offline',
        'online' => 'Online',
        'unknown' => 'Desconhecido',
    ];

    return $map[$state] ?? ($state === '' ? 'Desconhecido' : ucfirst($state));
}


function connect_api_connector_manager_available()
{
    if (function_exists('is_module_active')) {
        return (bool) is_module_active('connect_api_manager');
    }
    if (function_exists('module_exists')) {
        return (bool) module_exists('connect_api_manager');
    }

    return function_exists('connect_api_manager_can') || defined('CONNECT_API_MANAGER_MODULE');
}

function connect_api_connector_state_badge_class($state)
{
    $state = strtolower(trim((string) $state));
    if (in_array($state, ['open', 'connected', 'online'], true)) {
        return 'success';
    }
    if (in_array($state, ['connecting'], true)) {
        return 'warning';
    }
    if (in_array($state, ['close', 'closed', 'disconnected', 'offline'], true)) {
        return 'danger';
    }

    return 'default';
}


function connect_api_connector_format_phone($number)
{
    $digits = preg_replace('/\D+/', '', (string) $number);
    if ($digits === '') {
        return '-';
    }
    if (strpos($digits, '55') === 0 && strlen($digits) === 13) {
        return '+55 (' . substr($digits, 2, 2) . ') ' . substr($digits, 4, 5) . '-' . substr($digits, 9, 4);
    }
    if (strpos($digits, '55') === 0 && strlen($digits) === 12) {
        return '+55 (' . substr($digits, 2, 2) . ') ' . substr($digits, 4, 4) . '-' . substr($digits, 8, 4);
    }
    return '+' . $digits;
}
