<?php

/*
//Debug Mode
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
*/

/**
 * Ensures that the module init file can't be accessed directly, only within the application.
 */
defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Asaas - Módulo de Pagamento
Description: Integração com Sistema Financeiro Asaas com a função de recebimento via catão de crédito, Boleto e Pix.
Author: WWSoft -> Wallace Kleiton - argws/asaas-sdk-php v0.2.61
Version: 1.5.4
Requires at least: 4.5.8
Author URI: https://t.me/wkartspro
*/

define('ASAAS_MODULE_NAME', 'asaas');

hooks()->add_action('before_render_payment_gateway_settings', 'asaas_before_render_payment_gateway_settings');
hooks()->add_action('app_admin_footer', 'asaas_settings_tab_footer');
hooks()->add_filter('before_settings_updated', 'asaas_validate_admin_settings');
hooks()->add_action('after_invoice_added', 'asaas_after_invoice_added');
if (function_exists('do_action_deprecated')) {
    hooks()->add_action('invoice_updated', 'asaas_after_invoice_updated');
} else {
    // Compatibilidade com versões anteriores do fluxo de atualização de faturas.
    hooks()->add_action('after_invoice_updated', 'asaas_after_invoice_updated');
}
hooks()->add_filter('before_invoice_delete_allowed', 'asaas_before_invoice_delete_allowed', 10, 2);
hooks()->add_filter('before_invoice_cancel_allowed', 'asaas_before_invoice_cancel_allowed', 10, 2);
hooks()->add_filter('before_invoice_payment_insert_allowed', 'asaas_before_invoice_payment_insert_allowed', 10, 3);
hooks()->add_filter('before_invoice_credit_insert_allowed', 'asaas_before_invoice_payment_insert_allowed', 10, 3);
hooks()->add_filter('before_invoice_payment_mutation_allowed', 'asaas_before_invoice_payment_mutation_allowed', 10, 4);
hooks()->add_action('before_invoice_deleted', 'asaas_before_invoice_deleted');
hooks()->add_action('invoice_marked_as_cancelled', 'asaas_invoice_marked_as_cancelled');
hooks()->add_action('admin_init', 'asaas_origin_admin_menu');
hooks()->add_action('after_payment_added', 'asaas_after_payment_added');
hooks()->add_action('payment_recorded', 'asaas_after_payment_added');
hooks()->add_action('invoice_payment_added', 'asaas_after_payment_added');
hooks()->add_action('invoice_status_changed', 'asaas_invoice_status_changed');
hooks()->add_action('after_invoice_status_changed', 'asaas_invoice_status_changed');
hooks()->add_action('after_cron_run', 'asaas_process_pending_webhooks');

/** Runs only under the core invoice write lock; no provider calls or side effects. */
function asaas_before_invoice_payment_insert_allowed($allowed, array $invoice, array $data): bool
{
    if ($allowed !== true) return false;
    $CI = &get_instance();
    require_once __DIR__.'/libraries/Asaas/PaymentOriginStore.php';
    // Preserve manual/other-gateway behavior for legacy invoices with no known
    // origin. Known Asaas charges need canonical reconciliation, not an ID match.
    return (new \Asaas\PaymentOriginStore($CI->db))->byInvoice((int)($invoice['id']??0)) === null;
}

/** Canonical receipt evidence is immutable through generic admin operations. */
function asaas_before_invoice_payment_mutation_allowed($allowed, int $paymentId, int $invoiceId, string $operation): bool
{
    if ($allowed !== true) return false;
    $CI = &get_instance();
    $outbox=db_prefix().'asaas_settlement_outbox';
    if (!$CI->db->table_exists($outbox)) return true;
    // The canonical writer inserts receipt/outbox atomically. Any existing
    // reference, including damaged evidence, needs assisted reversal/review.
    // Never permit deletion merely because part of that proof became inconsistent.
    return $CI->db->get_where($outbox,['receipt_id'=>$paymentId])->num_rows()===0;
}

function asaas_process_pending_webhooks()
{
    $CI = &get_instance();
    try {
        $CI->load->library('asaas/asaas_gateway');
        $result = $CI->asaas_gateway->getAdapter()->processPendingWebhookEvents(20);
        if (!empty($result['failed'])) {
            log_message('error', '[Asaas] Existem eventos pendentes para nova tentativa no próximo CRON.');
        }
    } catch (Throwable $error) {
        log_message('error', '[Asaas] Falha temporária na rotina de reconciliação: ' . get_class($error));
    }
}


$CI = &get_instance();

register_activation_hook(ASAAS_MODULE_NAME, 'asaas_module_activation_hook');

function asaas_module_activation_hook() {
    $CI = &get_instance();
    $mediaPath = FCPATH . $CI->app->get_media_folder() . '/' . ASAAS_MODULE_NAME;

    if (!file_exists($mediaPath)) {
        mkdir($mediaPath, 0755, true);
        fopen(rtrim($mediaPath, '/') . '/' . 'index.html', 'w');
    }

    require_once(__DIR__ . '/install.php');
}

if (function_exists('app_payment_gateways')) {
    $gateways = app_payment_gateways();
    if (is_object($gateways)) {
        if (method_exists($gateways, 'add_gateway')) {
            $gateways->add_gateway('asaas_gateway');
        } elseif (method_exists($gateways, 'addGateway')) {
            $gateways->addGateway('asaas_gateway');
        } else {
            register_payment_gateway('asaas_gateway', ASAAS_MODULE_NAME);
        }
    } else {
        register_payment_gateway('asaas_gateway', ASAAS_MODULE_NAME);
    }
} else {
    register_payment_gateway('asaas_gateway', ASAAS_MODULE_NAME);
}

function asaas_before_render_payment_gateway_settings($gateway)
{
    if (is_array($gateway) && ($gateway['id'] ?? '') === 'asaas') {
        $environment = get_option('paymentmethod_asaas_sandbox');
        $valid = in_array($environment, ['0', '1'], true);
        echo '<a data-asaas-webhook-admin-url="' . html_escape(admin_url('asaas'))
            . '" data-asaas-environment-valid="' . ($valid ? '1' : '0')
            . '" data-asaas-environment-value="' . ($valid ? $environment : '')
            . '" data-asaas-settings-admin="' . (is_admin() ? '1' : '0')
            . '" data-asaas-profile-ready="' . (\Asaas\EnvironmentSettings::ready() ? '1' : '0')
            . '" href="' . html_escape(admin_url('asaas')) . '">Gerenciar recebedores e segurança dos webhooks Asaas</a>';
        require_once __DIR__ . '/libraries/Asaas/SettingsPresentation.php';
        $CI = &get_instance();
        $summary = \Asaas\SettingsPresentation::legacy($CI->db, defined('APP_BASE_URL') ? (string) APP_BASE_URL : '', (string) $CI->config->item('index_page'));
        $CI->load->view('asaas/settings_summary', ['asaas_summary' => $summary]);
    }
    return $gateway;
}

function asaas_validate_admin_settings($data)
{
    require_once __DIR__ . '/libraries/Asaas/AdminSettingsPolicy.php';
    try { return \Asaas\AdminSettingsPolicy::validate($data); }
    catch (InvalidArgumentException | DomainException $error) {
        show_error('Configuração Asaas inválida. Confira o ambiente, os campos e os valores permitidos. Tokens exigem a tela de webhooks.', 400);
        return [];
    }
}

function asaas_settings_tab_footer()
{
    echo '<script src="' . html_escape(module_dir_url('asaas', 'assets/js/settings-tabs.js')) . '"></script>';
}

function asaas_after_invoice_added($invoice_id)
{
    // The nominal-company service owns its own issuance and must never enter the legacy hook.
    if (class_exists('CompanySettingsRuntime', false) && CompanySettingsRuntime::active()
        && !CompanySettingsRuntime::isPrimary()) return $invoice_id;
    $CI = &get_instance();
    $CI->load->library('asaas_gateway');
    if ($CI->asaas_gateway->getSetting('active') === '1' && $CI->asaas_gateway->getSetting('billet_only') === '1') {
        $CI->load->model('invoices_model');
        $invoice = $CI->invoices_model->get($invoice_id);

        if ($invoice) {
            if ($invoice->duedate) {

                $allowed_payment_modes = isset($invoice->allowed_payment_modes) ? unserialize($invoice->allowed_payment_modes) : array();

                if (in_array(ASAAS_MODULE_NAME, $allowed_payment_modes)) {
                    $billet = $CI->asaas_gateway->charge_billet($invoice);
                }
            }
        }
        return $invoice_id;
    }
}

function asaas_after_invoice_updated($hookData = null)
{
    try {
        return asaas_update_invoice_payment($hookData);
    } catch (Throwable $error) {
        log_message('error', '[Asaas] Sincronização não confirmada; revisar origem da cobrança.');
        if (function_exists('set_alert')) {
            set_alert('warning', 'Fatura salva, mas a cobrança Asaas exige revisão em Asaas: origem e pendências.');
        }
        return $hookData;
    }
}

function asaas_update_invoice_payment($hookData = null)
{
    $CI = &get_instance();

    // Descobre o invoice_id vindo do hook (compatível com múltiplas assinaturas)
    $args = func_get_args();
    $id = 0;

    if (is_array($hookData) && isset($hookData['invoice_id'])) {
        $id = (int) $hookData['invoice_id'];
    } elseif (is_array($hookData) && isset($hookData['id'])) {
        $id = (int) $hookData['id'];
    } elseif (is_numeric($hookData)) {
        $id = (int) $hookData;
    } elseif (isset($args[1])) {
        if (is_array($args[1]) && isset($args[1]['invoice_id'])) {
            $id = (int) $args[1]['invoice_id'];
        } elseif (is_numeric($args[1])) {
            $id = (int) $args[1];
        }
    }

    if ($id <= 0) {
        log_message('error', '[Asaas] invoice_updated: invoice_id inválido.');
        return $id;
    }

    $CI->load->library('asaas_gateway');
    $CI->load->model('invoices_model');

    $invoice = $CI->invoices_model->get($id);
    if (!$invoice) {
        log_message('error', '[Asaas] invoice_updated: invoice não encontrada para invoice_id=' . $id);
        return $id;
    }

    $CI->asaas_gateway->getAdapter()->assertInvoiceScope((array) $invoice);

    if (in_array((int) $invoice->status, [5, 6], true)) {
        return $id;
    }

    if (empty($invoice->duedate)) {
        log_message('info', '[Asaas] invoice_updated: sem dueDate para invoice_id=' . $id);
        return $id;
    }

    $allowed_payment_modes = [];
    if (isset($invoice->allowed_payment_modes) && is_string($invoice->allowed_payment_modes) && $invoice->allowed_payment_modes !== '') {
        $tmp = @unserialize($invoice->allowed_payment_modes);
        if (is_array($tmp)) {
            $allowed_payment_modes = $tmp;
        }
    }

    if (!in_array('asaas', $allowed_payment_modes, true)) {
        log_message('info', '[Asaas] invoice_updated: forma de pagamento asaas não permitida para invoice_id=' . $id);
        return $id;
    }

    $origin = $CI->asaas_gateway->getAdapter()->originStore()->byInvoice((int) $id);
    $profileSetting = static function (string $name) use ($CI, $origin): string {
        return $origin ? $CI->asaas_gateway->getSettingForOrigin($name, $origin) : $CI->asaas_gateway->getSetting($name);
    };
    $description = $profileSetting('description');
    $discount_value = (float) $profileSetting('discount_value');
    $dueDateLimitDays = (int) $profileSetting('discount_days');
    $discount_type = (int) $profileSetting('discount_type');
    $update_charge = (int) $profileSetting('update_charge');

    // Desconto
    $adminnote = isset($invoice->adminnote) && is_string($invoice->adminnote) ? $invoice->adminnote : '';
    $sem_desconto = strpos($adminnote, '{sem_desconto}') !== false;

    $discount = [
        'type' => 'PERCENTAGE',
        'value' => $discount_value,
        'dueDateLimitDays' => $dueDateLimitDays,
    ];

    if ($discount_type === 1) {
        $discount['type'] = 'FIXED';
    }

    if ($sem_desconto) {
        // força zerar desconto quando marcado
        $discount['type'] = 'PERCENTAGE';
        $discount['value'] = 0;
    }

    $invoice_number = $invoice->prefix . str_pad((string) $invoice->number, 6, '0', STR_PAD_LEFT);
    $finalDescription = mb_convert_encoding(str_replace('{invoice_number}', $invoice_number, $description), 'UTF-8');

    // Busca cliente (com e-mail primário)
    $CI->db->select('c.*, cc.email');
    $CI->db->from(db_prefix() . 'clients c');
    $CI->db->join(db_prefix() . 'contacts cc', 'cc.userid = c.userid AND cc.is_primary = 1', 'LEFT');
    $CI->db->where('c.userid', $invoice->clientid);
    $client = $CI->db->get()->row();

    if (!$client) {
        log_message('error', '[Asaas] invoice_updated: cliente não encontrado para invoice_id=' . $id);
        return $id;
    }

    if ($origin) {
        if (empty($origin['remote_id']) || $origin['state'] !== 'issued') {
            log_message('error', '[Asaas] Emissão exige conciliação. invoice_id=' . (int) $id);
            return $id;
        }
        $remote = $CI->asaas_gateway->getAdapter()->getPayment($origin['remote_id']);
        $cliente_id = (string) ($remote['customer'] ?? '');
    } else {
        $customerEns = $CI->asaas_gateway->ensure_customer_for_client((int) $invoice->clientid, $client);
        $cliente_id = (string) ($customerEns['asaas_customer_id'] ?? '');
    }
    if ($cliente_id === '') {
        log_message('error', '[Asaas] Cliente Asaas não confirmado. invoice_id=' . (int) $id);
        return $id;
    }

    // Monta payload base
    $chargePayloadArray = [
        'customer' => $cliente_id,
        'billingType' => 'UNDEFINED',
        'dueDate' => $invoice->duedate,
        'value' => (float) $invoice->total,
        'description' => $finalDescription,
        'externalReference' => (string) $invoice->hash,
        'postalService' => false,
        'discount' => $discount,
    ];

    // 1) Se já existe cobrança vinculada (externalReference), tenta atualizar
    $charges = $CI->asaas_gateway->get_charge2($invoice->hash);

    if (is_array($charges) && count($charges) > 0) {
        if ($update_charge !== 1) {
            log_message('info', '[Asaas] invoice_updated: update_charge desabilitado - não atualizando cobranças existentes. invoice_id=' . $id);
        }

        foreach ($charges as $charge) {
            if ($update_charge === 1) {
                $billingType = isset($charge->billingType) ? (string) $charge->billingType : 'UNDEFINED';
                $payloadArray = $chargePayloadArray;
                $payloadArray['billingType'] = $billingType;

                log_message('info', '[Asaas] Operação de cobrança solicitada; payload e resposta omitidos.');
                $updateResponse = $CI->asaas_gateway->update_charge($charge->id, json_encode($payloadArray));
                log_message('info', '[Asaas] Operação de cobrança solicitada; payload e resposta omitidos.');

                if (is_array($updateResponse) && !isset($updateResponse['errors']) && !isset($updateResponse['error'])) {
                    $CI->asaas_gateway->upsert_payment_map((int) $id, $updateResponse, $cliente_id);
                } else {
                    log_message('error', '[Asaas] Operação de cobrança não confirmada; confira a fila de conciliação.');
                    // mesmo com erro, mantém map com o que já existe
                    $CI->asaas_gateway->upsert_payment_map((int) $id, $charge, $cliente_id);
                }
            } else {
                // update desabilitado: garante map com a cobrança atual
                $CI->asaas_gateway->upsert_payment_map((int) $id, $charge, $cliente_id);
            }
        }

        return $id;
    }

    // 2) Não existe cobrança: criar SEM depender do update_charge
    log_message('info', '[Asaas] Operação de cobrança solicitada; payload e resposta omitidos.');

    $createResponseRaw = $CI->asaas_gateway->create_charge(null, null, json_encode($chargePayloadArray));
    $createResponse = is_string($createResponseRaw) ? json_decode($createResponseRaw, true) : $createResponseRaw;
    if (!is_array($createResponse)) {
        $createResponse = [];
    }

    log_message('info', '[Asaas] Operação de cobrança solicitada; payload e resposta omitidos.');

    if (isset($createResponse['errors']) || isset($createResponse['error']) || empty($createResponse['id'])) {
        log_message('error', '[Asaas] Operação de cobrança não confirmada; confira a fila de conciliação.');
        return $id;
    }

    $CI->asaas_gateway->upsert_payment_map((int) $id, $createResponse, $cliente_id);

    return $id;
}

function asaas_origin_admin_menu()
{
    if (is_admin()) {
        $CI = &get_instance();
        $CI->app_menu->add_sidebar_children_item('sales', [
            'slug' => 'asaas-origins', 'name' => 'Asaas: origem e pendências',
            'href' => admin_url('asaas/payment_origins'), 'position' => 99,
        ]);
    }
}

function asaas_before_invoice_delete_allowed($allowed, $id)
{
    return asaas_guard_invoice_mutation($allowed, (int) $id, 'delete');
}

function asaas_before_invoice_cancel_allowed($allowed, $id)
{
    return asaas_guard_invoice_mutation($allowed, (int) $id, 'cancel');
}

function asaas_guard_invoice_mutation($allowed, int $invoiceId, string $action): bool
{
    if ($allowed !== true) {
        return false;
    }
    $CI = &get_instance();
    $CI->load->library('asaas/asaas_gateway');
    $adapter = $CI->asaas_gateway->getAdapter();
    $store = $adapter->originStore();
    $origin = $store->byInvoice($invoiceId);
    if (!$origin) {
        // Historical IDs alone do not prove an account. Keep the pre-existing
        // local policy, expose the unresolved mapping, and NEVER guess a DELETE.
        if ($store->ready() && $CI->asaas_gateway->get_payment_id_by_invoice($invoiceId)) {
            $store->event($invoiceId, 'legacy_' . $action . '_requires_review');
            if (function_exists('set_alert')) {
                set_alert('warning', 'Origem Asaas não conciliada. A cobrança remota não foi removida; revise Asaas: origem e pendências.');
            }
        }
        return true;
    }
    try {
        if ($action === 'delete' && (int) $CI->asaas_gateway->getSettingForOrigin('delete_charge', $origin) !== 1) {
            $store->state($invoiceId, 'review_required', 'automatic_removal_disabled');
            $result = ['success' => false, 'code' => 'automatic_removal_disabled'];
        } else {
            $result = $adapter->removeInvoicePayment($invoiceId);
        }
    } catch (Throwable $error) {
        $result = ['success' => false, 'code' => 'removal_unconfirmed'];
        log_message('error', '[Asaas] Remoção não confirmada para invoice_id=' . $invoiceId);
    }
    if (!$result['success'] && function_exists('set_alert')) {
        set_alert('warning', 'Fatura preservada: a remoção Asaas não foi confirmada ou há recebimentos. Revise Asaas: origem e pendências.');
    }
    return $result['success'] === true;
}

function asaas_before_invoice_deleted($id)
{
    // Removal is performed in the returnable pre-delete filter. This legacy
    // action cannot veto deletion and must not repeat remote side effects.
    return $id;
}

function asaas_invoice_marked_as_cancelled($id)
{
    // The actual core hook is registered for compatibility/observability.
    // The returnable pre-cancel filter owns the remote operation.
    log_message('info', '[Asaas] invoice_marked_as_cancelled invoice_id=' . (int) $id);
}

function asaas_invoice_status_changed($data = null)
{
    // Status 4 is OVERDUE. Only explicit CANCELLED (5) is cancellation.
    if (is_array($data) && (string) ($data['status'] ?? $data['new_status'] ?? '') === '5') {
        asaas_invoice_marked_as_cancelled((int) ($data['invoice_id'] ?? $data['id'] ?? 0));
    }
}


/**
 * Quando um pagamento é registrado manualmente na aplicação (ex.: dinheiro),
 * espelhar no Asaas como "recebido em dinheiro" (receiveInCash).
 *
 * Hook(s) suportados: after_payment_added / payment_recorded / invoice_payment_added
 * A assinatura do hook pode variar entre versões, então tratamos os dados de forma defensiva.
 */
function asaas_after_payment_added($data = null)
{
    try {
        $CI = &get_instance();

        // Descobrir paymentId/invoiceId
        $paymentId = null;
        $invoiceId = null;

        if (is_numeric($data)) {
            $paymentId = (int) $data;
        } elseif (is_array($data)) {
            $paymentId = isset($data['paymentid']) ? (int) $data['paymentid'] : (isset($data['id']) ? (int) $data['id'] : null);
            $invoiceId = isset($data['invoiceid']) ? (int) $data['invoiceid'] : (isset($data['invoice_id']) ? (int) $data['invoice_id'] : null);
        } elseif (is_object($data)) {
            $paymentId = isset($data->paymentid) ? (int) $data->paymentid : (isset($data->id) ? (int) $data->id : null);
            $invoiceId = isset($data->invoiceid) ? (int) $data->invoiceid : (isset($data->invoice_id) ? (int) $data->invoice_id : null);
        }

        // Se não veio invoiceId, tenta carregar pelo paymentId
        if (!$invoiceId && $paymentId) {
            $CI->db->where('id', $paymentId);
            $row = $CI->db->get(db_prefix() . 'invoicepaymentrecords')->row_array();
            if ($row) {
                $invoiceId = (int) ($row['invoiceid'] ?? 0);
            }
        }

        if (!$invoiceId) {
            return;
        }

        // Carrega payment record (para detectar modo)
        $payment = null;
        if ($paymentId) {
            $CI->db->where('id', $paymentId);
            $payment = $CI->db->get(db_prefix() . 'invoicepaymentrecords')->row_array();
        }

        // Detectar se é pagamento em dinheiro/manual
        $isCash = false;

        if ($payment && isset($payment['paymentmode']) && $payment['paymentmode'] !== '') {
            // paymentmode pode ser id (int) ou nome (string) dependendo da versão/custom
            $pm = $payment['paymentmode'];

            if (is_numeric($pm)) {
                $CI->db->where('id', (int) $pm);
                $pmRow = $CI->db->get(db_prefix() . 'paymentmodes')->row_array();
                $pmName = strtolower((string) ($pmRow['name'] ?? ''));
                if (strpos($pmName, 'dinheiro') !== false || strpos($pmName, 'cash') !== false) {
                    $isCash = true;
                }
            } else {
                $pmName = strtolower((string) $pm);
                if (strpos($pmName, 'dinheiro') !== false || strpos($pmName, 'cash') !== false) {
                    $isCash = true;
                }
            }
        }

        if (!$isCash) {
            return;
        }

        // Gateway
        $CI->load->library('asaas_gateway');
        /** @var Asaas_gateway $gw */
        $gw = $CI->asaas_gateway;

        // Map (invoice -> asaas_payment_id)
        $map = $gw->get_payment_map_by_invoice_id($invoiceId);
        if (empty($map) || empty($map['asaas_payment_id'])) {
            log_message('info', '[Asaas] after_payment_added: sem map local para invoice_id=' . (int) $invoiceId . ' (cash).');
            return;
        }

        $asaasPaymentId = (string) $map['asaas_payment_id'];

        // Evita duplicar: se já está RECEIVED/CONFIRMED/RECEIVED_IN_CASH no cache, não reenvia
        $curStatus = strtolower((string) ($map['status'] ?? ''));
        if (in_array($curStatus, ['received', 'confirmed', 'received_in_cash', 'recebido', 'pago'], true)) {
            return;
        }

        $paymentDate = null;
        $value = null;

        if ($payment) {
            if (!empty($payment['date'])) {
                // A instalação normalmente armazena datetime
                $paymentDate = date('Y-m-d', strtotime((string) $payment['date']));
            }
            if (isset($payment['amount'])) {
                $value = (float) $payment['amount'];
            }
        }

        $payload = [
            'notifyCustomer' => false,
        ];
        if ($paymentDate) {
            $payload['paymentDate'] = $paymentDate;
        }
        if ($value !== null) {
            $payload['value'] = $value;
        }

        $resp = $gw->getAdapter()->confirmCashReceipt($asaasPaymentId, $payload);

        // Atualiza map com retorno (defensivo)
        $gw->upsert_payment_map($invoiceId, is_array($resp) ? $resp : ['id' => $asaasPaymentId, 'status' => 'RECEIVED_IN_CASH']);

        log_message('info', '[Asaas] Pagamento em dinheiro espelhado no Asaas para invoice_id=' . (int) $invoiceId . ' payment_id=' . $asaasPaymentId . '.');
    } catch (Throwable $e) {
        log_message('error', '[Asaas] Falha ao espelhar recebimento; confira a conciliação.');
    }
}


// Origin-bound reconciliation is opt-in and revalidates the persisted actor for every job.
hooks()->add_action('after_cron_run', static function () {
    require_once __DIR__.'/libraries/Asaas/CompanyCron.php';
    \Asaas\CompanyCron::run();
});
