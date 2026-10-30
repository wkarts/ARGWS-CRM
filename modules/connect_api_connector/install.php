<?php

defined('BASEPATH') or exit('No direct script access allowed');

function connect_api_connector_install()
{
    add_option('connect_api_connector_api_url', '');
    add_option('connect_api_connector_instance_name', '');
    add_option('connect_api_connector_instance_token', '');
    add_option('connect_api_connector_instance_number', '');
    add_option('connect_api_connector_verify_tls', '1');
    add_option('connect_api_connector_timeout', '30');
    add_option('connect_api_connector_default_country', '55');
    add_option('connect_api_connector_default_area', '');
    add_option('connect_api_connector_auto_normalize', '1');
    add_option('connect_api_connector_sms_takeover', '1');
    add_option('sms_trigger_invoice_send_to_customer2', 'Olá {contact_firstname}, sua fatura {invoice_number} foi emitida. Valor: {invoice_total}. Vencimento: {invoice_duedate}. Acesse: {invoice_link}');
    add_option('sms_trigger_invoice_payment_recorded', 'Olá {contact_firstname}, recebemos o pagamento da fatura {invoice_number}. Obrigado! Consulte: {invoice_link}');
}
