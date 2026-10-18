<?php

defined('BASEPATH') or exit('No direct script access allowed');
/*
Module Name: Stripe Ideal V2
Description: Stripe Ideal Payment module
Version: 1.0.0
Requires at least: 3.1.6
*/

const IDEAL_MODULE_NAME       = 'ideal';
const IDEAL_MODULE_GATEWAY_ID = 'Ideal_gateway';

// register_language_files(IDEAL_MODULE_NAME, [IDEAL_MODULE_NAME]);
register_payment_gateway(IDEAL_MODULE_GATEWAY_ID, IDEAL_MODULE_NAME);
register_activation_hook(IDEAL_MODULE_NAME, 'idealModuleActivation');

function idealModuleActivation()
{
    add_option('ideal_module_stripe_webhook_id');
    add_option('ideal_module_stripe_webhook_signing_secret');
}

/**
 * Get the necessary Stripe integration webhook events
 */
function idealModuleWebhookEvents(): array
{
    return [
        Stripe\Event::TYPE_PAYMENT_INTENT_SUCCEEDED,
    ];
}

/**
 * @param array{id: string, active: string, instance: Ideal_gateway} $gateway
 */
function idealModuleWebhookCheck(array $gateway): void
{
    if ($gateway['id'] === IDEAL_MODULE_GATEWAY_ID) {
        $idealGateway = $gateway['instance'];
        if ($idealGateway->hasSecretKey() && $gateway['active'] == '1') {
            try {
                $webhook = $idealGateway->getCurrentWebhookObject();
            } catch (Exception $e) {
                echo '<div class="alert alert-warning">';
                // useful when user add wrong keys
                // e.q. This API call cannot be made with a publishable API key. Please use a secret API key. You can find a list of your API keys at https://dashboard.stripe.com/account/apikeys.
                echo $e->getMessage();
                echo '</div>';

                return;
            }

            $environment = $idealGateway->environment();
            $endpoint    = $idealGateway->getWebhookEndPoint();
            $CI          = &get_instance();
            if ($CI->session->has_userdata('stripe-webhook-failure')) {
                echo '<div class="alert alert-warning" style="margin-bottom:15px;">';
                echo '<h4>Error: ' . $CI->session->userdata('stripe-webhook-failure') . '</h4>';
                echo 'The system was unable to create the <b>required</b> webhook endpoint for Stripe.';
                echo '<br />You should consider creating webhook manually directly via Stripe dashboard for your environment (' . $environment . ')';
                echo '<br /><br /><b>Webhook URL:</b><br />' . $endpoint;
                echo '<br /><br /><b>Webhook events:</b><br />' . implode(',<br />', idealModuleWebhookEvents());
                echo '</div>';
            }

            if (! $webhook || ! startsWith($webhook->url, site_url())) {
                echo '<div class="alert alert-warning">';
                echo 'Webhook endpoint (' . $endpoint . ') not found for ' . $environment . ' environment.';
                echo '<br />' . idealModuleManagementButton('create_webhook', 'Criar webhook no Stripe');
                echo '</div>';
            } elseif ($webhook->id != get_option('ideal_module_stripe_webhook_id')) {
                echo '<div class="alert alert-warning">';
                echo 'The application stored Stripe webhook id does not match the configured webhook.';
                echo '<br />' . idealModuleManagementButton('create_webhook', 'Recriar webhook e excluir o anterior', true);
                echo '</div>';
            } elseif ($webhook->status != 'enabled') {
                echo '<div class="alert alert-warning">';
                echo 'O webhook configurado está desabilitado. ' . idealModuleManagementButton('enable_webhook', 'Habilitar webhook');
                echo '</div>';
            }
        }
    }
}

hooks()->add_action('before_update_system_options', 'prevent_activate_ideal_gateway');
function prevent_activate_ideal_gateway($systemOptions): void
{
    /** @var CI&object{'ideal_gateway': Ideal_gateway} $ci */
    $ci = &get_instance();
    $ci->load->library('ideal_gateway');
    $options = $systemOptions['settings'];

    if (!is_array($options)) {
        // prevent bugs from module breaking the feature, e.g. third-party module developer did not wrap add value to settings[]
        return;
    }

    if (! array_key_exists('paymentmethod_Ideal_gateway_active', $options)) {
        return;
    }

    $idealKeyEmpty      = empty($options['paymentmethod_Ideal_gateway_api_publishable_key']) || empty($options['paymentmethod_Ideal_gateway_api_secret_key']);
    $idealGatewayActive = $options['paymentmethod_Ideal_gateway_active'] == '1';

    if ($idealKeyEmpty && $idealGatewayActive) {
        $ci->ideal_gateway->markAsInactive();
        set_alert('danger', _l('ideal_gateway_cannot_be_activated_keys_not_configured'));
        redirect(admin_url('settings?group=payment_gateways&tab=online_payments_Ideal_gateway_tab'));
    }
}


/** Buttons target separate POST forms, never a nested form inside Settings. */
function idealModuleManagementButton(string $action, string $label, bool $recreate = false): string
{
    if (!\app\services\PaymentGatewayCatalog::idealEnabled()
        || !is_staff_logged_in() || !staff_can('edit', 'settings')
        || !in_array($action, ['create_webhook', 'enable_webhook'], true)) {
        return '';
    }
    $id = 'ideal-management-' . $action . ($recreate ? '-recreate' : '');
    $GLOBALS['ideal_management_forms'][$id] = ['action' => $action, 'recreate' => $recreate];
    return '<button type="submit" class="btn btn-default btn-sm" form="' . e($id) . '">'
        . e($label) . '</button>';
}

hooks()->add_action('app_admin_footer', 'idealModuleManagementForms');
function idealModuleManagementForms(): void
{
    if (empty($GLOBALS['ideal_management_forms']) || !\app\services\PaymentGatewayCatalog::idealEnabled()
        || !is_staff_logged_in() || !staff_can('edit', 'settings')) {
        return;
    }
    $CI = &get_instance();
    $token = $CI->session->userdata('ideal_management_csrf');
    if (!is_string($token) || strlen($token) !== 64) {
        $token = bin2hex(random_bytes(32));
        $CI->session->set_userdata('ideal_management_csrf', $token);
    }
    foreach ($GLOBALS['ideal_management_forms'] as $id => $form) {
        // form_open also includes CodeIgniter's token when global CSRF is enabled.
        echo form_open(site_url('ideal/' . $form['action']), ['id' => $id, 'method' => 'post']);
        echo form_hidden('ideal_management_csrf', $token);
        if ($form['recreate']) {
            echo form_hidden('recreate', '1');
        }
        echo form_close();
    }
}
