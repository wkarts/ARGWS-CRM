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
Version: 1.4.5
Requires at least: 2.9.*
Author URI: https://t.me/wkartspro
*/

define('ASAAS_MODULE_NAME', 'asaas');

hooks()->add_action('before_render_payment_gateway_settings', 'asaas_before_render_payment_gateway_settings');
hooks()->add_action('app_admin_footer', 'asaas_settings_tab_footer');
hooks()->add_action('after_invoice_added', 'asaas_after_invoice_added');
if (function_exists('do_action_deprecated')) {
    hooks()->add_action('invoice_updated', 'asaas_after_invoice_updated');
} else {
    // Compatibilidade com versões anteriores do fluxo de atualização de faturas.
    hooks()->add_action('after_invoice_updated', 'asaas_after_invoice_updated');
}
hooks()->add_action('before_invoice_deleted', 'asaas_before_invoice_deleted');
hooks()->add_action('after_payment_added', 'asaas_after_payment_added');
hooks()->add_action('payment_recorded', 'asaas_after_payment_added');
hooks()->add_action('invoice_payment_added', 'asaas_after_payment_added');
hooks()->add_action('invoice_status_changed', 'asaas_invoice_status_changed');
hooks()->add_action('after_invoice_status_changed', 'asaas_invoice_status_changed');

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
    return $gateway;
}

function asaas_settings_tab_footer()
{
?>
    <script>
        $(document).ready(function() {

            $(".form-control datepicker").attr("required", "true");

            function validate_invoice_form(e) {
                e = void 0 === e ? "#invoice-form" : e,
                    appValidateForm($(e), {
                        clientid: {
                            required: {
                                depends: function() {
                                    return !$("select#clientid").hasClass("customer-removed")
                                }
                            }
                        },
                        date: "required",
                        currency: "required",
                        repeat_every_custom: {
                            min: 1
                        },
                        number: {
                            required: !0
                        }
                    }), $("body").find('input[name="number"]').rules("add", {
                        remote: {
                            url: admin_url + "invoices/validate_invoice_number",
                            type: "post",
                            data: {
                                number: function() {
                                    return $('input[name="number"]').val()
                                },
                                isedit: function() {
                                    return $('input[name="number"]').data("isedit")
                                },
                                original_number: function() {
                                    return $('input[name="number"]').data("original-number")
                                },
                                date: function() {
                                    return $('input[name="date"]').val()
                                }
                            }
                        },
                        messages: {
                            remote: app.lang.invoice_number_exists
                        }
                    })
            }
        });

        $("#online_payments_asaas_tab > div:nth-child(13) > div:nth-child(2) > label").html("Valor fixo");

        $("#online_payments_asaas_tab > div:nth-child(13) > div:nth-child(3) > label").html("Porcentagem");

        $("#y_opt_1_Tipo\\ de\\ desconto").change(function() {

            $("#online_payments_asaas_tab > div:nth-child(13) > label").empty();
            $("#online_payments_asaas_tab > div:nth-child(13) > label").html("Valor desconto ");

        });

        $("#y_opt_2_Tipo\\ de\\ desconto").change(function() {

            // console.log( asaas);
            $("#online_payments_asaas_tab > div:nth-child(13) > label").empty();
            $("#online_payments_asaas_tab > div:nth-child(13) > label").html("Valor desconto (Informar porcentagem)");

        });
    </script>
<?php
}

function asaas_after_invoice_added($invoice_id)
{
    if (get_option('paymentmethod_asaas_billet_only')) {
        $CI = &get_instance();
        $CI->load->library('asaas_gateway');
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

    $description = (string) $CI->asaas_gateway->getSetting('description');
    $discount_value = (float) $CI->asaas_gateway->getSetting('discount_value');
    $dueDateLimitDays = (int) $CI->asaas_gateway->getSetting('discount_days');
    $discount_type = (int) $CI->asaas_gateway->getSetting('discount_type');
    $update_charge = (int) $CI->asaas_gateway->getSetting('update_charge');

    $invoice = $CI->invoices_model->get($id);
    if (!$invoice) {
        log_message('error', '[Asaas] invoice_updated: invoice não encontrada para invoice_id=' . $id);
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

    // Garante cliente no Asaas (MAP local tblasaas_customers_map -> busca estrita -> cria)
    $clientId = (int) $invoice->clientid;
    $customerEns = $CI->asaas_gateway->ensure_customer_for_client($clientId, $client);
    log_message('info', '[Asaas] invoice_updated: ensure_customer_for_client response=' . json_encode($customerEns, JSON_UNESCAPED_UNICODE));

    $cliente_id = isset($customerEns['asaas_customer_id']) ? (string) $customerEns['asaas_customer_id'] : '';
    if ($cliente_id === '') {
        log_message('error', '[Asaas] invoice_updated: Asaas customer_id vazio. invoice_id=' . $id . ' details=' . json_encode($customerEns, JSON_UNESCAPED_UNICODE));
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

                log_message('info', '[Asaas] invoice_updated: update_charge payload=' . json_encode($payloadArray, JSON_UNESCAPED_UNICODE));
                $updateResponse = $CI->asaas_gateway->update_charge($charge->id, json_encode($payloadArray));
                log_message('info', '[Asaas] invoice_updated: update_charge response=' . json_encode($updateResponse, JSON_UNESCAPED_UNICODE));

                if (is_array($updateResponse) && !isset($updateResponse['errors']) && !isset($updateResponse['error'])) {
                    $CI->asaas_gateway->upsert_payment_map((int) $id, $updateResponse, $cliente_id);
                } else {
                    log_message('error', '[Asaas] invoice_updated: erro ao atualizar cobrança charge_id=' . $charge->id . ' response=' . json_encode($updateResponse, JSON_UNESCAPED_UNICODE));
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
    log_message('info', '[Asaas] invoice_updated: create_charge payload=' . json_encode($chargePayloadArray, JSON_UNESCAPED_UNICODE));

    $createResponseRaw = $CI->asaas_gateway->create_charge(null, null, json_encode($chargePayloadArray));
    $createResponse = is_string($createResponseRaw) ? json_decode($createResponseRaw, true) : $createResponseRaw;
    if (!is_array($createResponse)) {
        $createResponse = [];
    }

    log_message('info', '[Asaas] invoice_updated: create_charge response=' . json_encode($createResponse, JSON_UNESCAPED_UNICODE));

    if (isset($createResponse['errors']) || isset($createResponse['error']) || empty($createResponse['id'])) {
        log_message('error', '[Asaas] invoice_updated: erro ao criar cobrança para invoice_id=' . $id . ' response=' . json_encode($createResponse, JSON_UNESCAPED_UNICODE));
        return $id;
    }

    $CI->asaas_gateway->upsert_payment_map((int) $id, $createResponse, $cliente_id);

    return $id;
}

function asaas_before_invoice_deleted($id)
{
    $CI = &get_instance();
    $CI->load->library('asaas_gateway');

    $delete_charge = (int) $CI->asaas_gateway->getSetting('delete_charge');
    if ($delete_charge !== 1) {
        log_message('info', '[Asaas] before_invoice_deleted: delete_charge desativado. invoice_id=' . (int) $id);
        return $id;
    }

    $invoiceId = (int) $id;
    $paymentId = $CI->asaas_gateway->get_payment_id_by_invoice($invoiceId);

    if (!$paymentId) {
        log_message('info', '[Asaas] before_invoice_deleted: sem payment map. invoice_id=' . $invoiceId);
        return $id;
    }

    // Preferência: cancelamento (mantém histórico no Asaas). Se falhar, tenta DELETE.
    try {
        $resp = $CI->asaas_gateway->adapter->cancelPayment($paymentId);
        log_message('info', '[Asaas] before_invoice_deleted: cancelPayment ok. invoice_id=' . $invoiceId . ' payment_id=' . $paymentId . ' resp=' . json_encode($resp, JSON_UNESCAPED_UNICODE));
        $CI->asaas_gateway->upsert_payment_map($invoiceId, ['id' => $paymentId, 'status' => 'CANCELLED']);
        return $id;
    } catch (Throwable $e) {
        log_message('error', '[Asaas] before_invoice_deleted: cancelPayment falhou. invoice_id=' . $invoiceId . ' payment_id=' . $paymentId . ' err=' . $e->getMessage());
    }

    try {
        $resp = $CI->asaas_gateway->adapter->deletePayment($paymentId);
        log_message('info', '[Asaas] before_invoice_deleted: deletePayment ok. invoice_id=' . $invoiceId . ' payment_id=' . $paymentId . ' resp=' . json_encode($resp, JSON_UNESCAPED_UNICODE));
        $CI->asaas_gateway->upsert_payment_map($invoiceId, ['id' => $paymentId, 'status' => 'DELETED']);
    } catch (Throwable $e) {
        log_message('error', '[Asaas] before_invoice_deleted: deletePayment falhou. invoice_id=' . $invoiceId . ' payment_id=' . $paymentId . ' err=' . $e->getMessage());
    }

    return $id;
}


function asaas_invoice_status_changed($data = null)
{
    $CI = &get_instance();
    $CI->load->library('asaas_gateway');

    $invoiceId = 0;
    $status = null;

    if (is_array($data)) {
        $invoiceId = (int) ($data['invoice_id'] ?? $data['id'] ?? 0);
        $status = $data['status'] ?? $data['new_status'] ?? null;
    } elseif (is_numeric($data)) {
        $invoiceId = (int) $data;
    }

    if ($invoiceId <= 0) {
        return;
    }

    $statusStr = is_string($status) ? strtolower(trim($status)) : '';
    $isCancelled = in_array($statusStr, ['cancelled', 'canceled', 'cancelado'], true) || in_array((string) $status, ['4', '5'], true);

    if (!$isCancelled) {
        return;
    }

    $paymentId = $CI->asaas_gateway->get_payment_id_by_invoice($invoiceId);
    if (!$paymentId) {
        log_message('info', '[Asaas] invoice_status_changed: sem payment map. invoice_id=' . $invoiceId);
        return;
    }

    try {
        $resp = $CI->asaas_gateway->adapter->cancelPayment($paymentId);
        log_message('info', '[Asaas] invoice_status_changed: cancelPayment ok. invoice_id=' . $invoiceId . ' payment_id=' . $paymentId . ' resp=' . json_encode($resp, JSON_UNESCAPED_UNICODE));
        $CI->asaas_gateway->upsert_payment_map($invoiceId, ['id' => $paymentId, 'status' => 'CANCELLED']);
    } catch (Throwable $e) {
        log_message('error', '[Asaas] invoice_status_changed: cancelPayment falhou. invoice_id=' . $invoiceId . ' payment_id=' . $paymentId . ' err=' . $e->getMessage());
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
        log_message('error', '[Asaas] after_payment_added erro: ' . $e->getMessage());
    }
}
