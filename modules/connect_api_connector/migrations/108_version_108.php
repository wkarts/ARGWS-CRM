<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_108 extends App_module_migration
{
    public function up()
    {
        add_option('connect_api_connector_sms_takeover', '1');

        // 1) Preserva qualquer texto já configurado na 1.0.0-1.0.7.
        $legacyInvoiceKey = 'sms_trigger_invoice_send_to_customer2';
        $v107InvoiceKey   = 'sms_trigger_connect_api_connector_invoice_sent';
        $invoiceMessage   = trim((string) get_option($legacyInvoiceKey));
        $v107Invoice      = trim((string) get_option($v107InvoiceKey));

        if ($invoiceMessage === '' && $v107Invoice !== '') {
            update_option($legacyInvoiceKey, $v107Invoice);
            $invoiceMessage = $v107Invoice;
        }

        // 2) Em instalação nova, entrega uma mensagem de cobrança utilizável imediatamente.
        if ($invoiceMessage === '') {
            update_option(
                $legacyInvoiceKey,
                'Olá {contact_firstname}, sua fatura {invoice_number} foi emitida. Valor: {invoice_total}. Vencimento: {invoice_duedate}. Acesse: {invoice_link}'
            );
        }

        // 3) O pagamento registrado é um gatilho nativo do ARGWS CRM. Migra eventual texto criado
        // nas versões anteriores do Connector para o ID nativo sem sobrescrever configuração existente.
        $paymentKey     = 'sms_trigger_invoice_payment_recorded';
        $v107PaymentKey = 'sms_trigger_connect_api_connector_payment_recorded';
        $paymentMessage = trim((string) get_option($paymentKey));
        $v107Payment    = trim((string) get_option($v107PaymentKey));

        if ($paymentMessage === '' && $v107Payment !== '') {
            update_option($paymentKey, $v107Payment);
            $paymentMessage = $v107Payment;
        }

        if ($paymentMessage === '') {
            update_option(
                $paymentKey,
                'Olá {contact_firstname}, recebemos o pagamento da fatura {invoice_number}. Obrigado! Consulte: {invoice_link}'
            );
        }

        // 4) Se o Connector já está configurado, ele assume o gateway SMS/WhatsApp do ARGWS CRM.
        $configured = trim((string) get_option('connect_api_connector_api_url')) !== ''
            && trim((string) get_option('connect_api_connector_instance_name')) !== ''
            && trim((string) get_option('connect_api_connector_instance_token')) !== '';

        if ($configured && get_option('connect_api_connector_sms_takeover') !== '0') {
            add_option('sms_connect_api_connector_active', '1');
            update_option('sms_connect_api_connector_active', '1');
            if (get_option('sms_whatsapiv2_active') !== false) {
                update_option('sms_whatsapiv2_active', '0');
            }
        }
    }
}
