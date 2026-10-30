<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_110 extends App_module_migration
{
    public function up()
    {
        add_option('connect_api_connector_sms_takeover','1');
        $key='sms_trigger_invoice_send_to_customer2';
        $current=trim((string)get_option($key));
        $tokens=['{contact_firstname}','{contact_lastname}','{client_company}','{client_vat_number}','{client_id}','{invoice_link}','{invoice_number}','{invoice_duedate}','{invoice_date}','{invoice_status}','{invoice_subtotal}','{invoice_total}'];
        $hits=0;foreach($tokens as$t)if(strpos($current,$t)!==false)$hits++;
        // Corrige apenas o caso conhecido em que a mensagem era um dump/lista de merge fields.
        // Textos personalizados de verdade são preservados.
        if($current==='' || ($hits>=8 && substr_count($current,',')>=6 && substr_count($current,"\n")<3)){
            update_option($key,"Olá {contact_firstname} {contact_lastname}, espero que esteja tudo bem.\n\nGostaríamos de informar que uma nova fatura foi gerada para {client_company}. Seguem os detalhes:\n\nData de Vencimento: {invoice_duedate}\nNúmero da Fatura: {invoice_number}\nTotal a Pagar: {invoice_total}\n\nPara visualizar e efetuar o pagamento da fatura, acesse:\n{invoice_link}\n\nSe tiver qualquer dúvida ou precisar de assistência, estamos à disposição.");
        }
    }
}
