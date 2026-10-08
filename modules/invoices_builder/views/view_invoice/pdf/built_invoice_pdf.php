<?php

defined('BASEPATH') or exit('No direct script access allowed');

$template_setting = json_decode($template->setting);

if($built_invoice->updated_data != '' && $built_invoice->updated_data != null){
    $updated_data = json_decode($built_invoice->updated_data);
}

// ---- Header section start
$header_setting = $template_setting->header;

$header_data = [
   'logo',
   'qrcode',
   'title',
   'invoice_infor',
];

$regularItemWidth  = ib_get_regular_table_width($header_setting->col_number);

$custom_pdf_logo_image_url = get_option('custom_pdf_logo_image_url');
$companyUploadPath         = get_upload_path_by_type('company');
$logoUrl                   = '';

if ($custom_pdf_logo_image_url != '') {
    $logoUrl = $custom_pdf_logo_image_url;
} else {
    if (get_option('company_logo_dark') != '' && file_exists($companyUploadPath . get_option('company_logo_dark'))) {
        $logoUrl = $companyUploadPath . get_option('company_logo_dark');
    } elseif (get_option('company_logo') != '' && file_exists($companyUploadPath . get_option('company_logo'))) {
        $logoUrl = $companyUploadPath . get_option('company_logo');
    }
}
$tblheader = '';
$tblheader = '<table cellpadding="10">';
$tblheader .= '<tbody>';
$tblheader .= '<tr style="background-color:'.$header_setting->row_color.'" >';
for($i = 1; $i <= $header_setting->col_number; $i++){ 
    $style = '';
    if($i == $header_setting->col_number){
        $style = 'style="text-align:right;"';
    }
    $tblheader .= '<td width="'.$regularItemWidth.'%" '.$style.'>';
    foreach($header_data as $hd){
        if(isset($header_setting->$hd) && $header_setting->$hd->show_on_column == $i){ 
            if($hd == 'logo' && isset($header_setting->$hd->active)){ 
                $justify_content = (isset($header_setting->$hd->text_align) ? 'vertical-align: middle; text-align:'.$header_setting->$hd->text_align.';' : 'vertical-align: middle; text-align:left');
                $tblheader .= '<div class="build-qrcode mtop10 mbot10" style="width: '.$header_setting->$hd->width.'px; height:'.$header_setting->$hd->height.'px; '.$justify_content.'"><img width="' . $header_setting->$hd->width . 'px" src="' . $logoUrl . '"></div>';
            }else if($hd == 'qrcode' && isset($header_setting->$hd->active)){ 
                $justify_content = (isset($header_setting->$hd->text_align) ? 'vertical-align: middle; text-align:'.$header_setting->$hd->text_align.';' : 'vertical-align: middle; text-align:left');
                $tblheader .= '<div class="build-qrcode mtop10 mbot10" style="width: '.$header_setting->$hd->width.'px; height:'.$header_setting->$hd->height.'px; '.$justify_content.'"><img src="'.get_invoice_qrcode_link($invoice->id, $built_invoice_id,'pdf').'" style="width:'.$header_setting->$hd->width.'px; height:'.$header_setting->$hd->height.'px;  float:'.$header_setting->$hd->text_align.';" class="img-responsive" alt></div>';
            }else if($hd == 'title' && isset($header_setting->$hd->active)){
                $title = format_invoice_number($invoice->id);

                $header_title = isset($updated_data->header->title) ? $updated_data->header->title : $title;

                $font_style = $header_setting->$hd->font_style;
                if($font_style == 'initial'){
                    $font_style = 'normal';
                }
                $tblheader .= '<p style="font-size: '.$header_setting->$hd->font_size.'px;  font-style: '.$font_style.'; color: '.$header_setting->$hd->text_color.'; text-align: '.$header_setting->$hd->text_align.'">'.$header_title.'</p>';
            }else if($hd == 'invoice_infor' && isset($header_setting->$hd->active)){
                if(isset($header_setting->$hd->invoice_number)){
                    $invoice_number = isset($updated_data->header->invoice_infor->invoice_number) ? $updated_data->header->invoice_infor->invoice_number : format_invoice_number($invoice->id);
                    $tblheader .= '<p class="mbot5" style="font-size:'.$header_setting->$hd->font_size.'px; font-style:'.$header_setting->$hd->font_style.'; color:'.$header_setting->$hd->text_color.'; text-align:'.$header_setting->$hd->text_align.'"> '. _l('ib_invoice_number').': '.$invoice_number.'</p>';
                }

                if(isset($header_setting->$hd->invoice_date)){ 
                    $invoice_date = isset($updated_data->header->invoice_infor->invoice_date) ? $updated_data->header->invoice_infor->invoice_date : _d($invoice->date);
                    $tblheader .= '<p class="mbot5" style="font-size:'.$header_setting->$hd->font_size.'px; font-style:'.$header_setting->$hd->font_style.'; color:'.$header_setting->$hd->text_color.'; text-align:'.$header_setting->$hd->text_align.'"> '. _l('ib_invoice_date').': '.$invoice_date.'</p>';
                }

                if(isset($header_setting->$hd->invoice_due_date)){
                    $invoice_due_date = isset($updated_data->header->invoice_infor->invoice_due_date) ? $updated_data->header->invoice_infor->invoice_due_date : _d($invoice->duedate);
                    $tblheader .= '<p class="mbot5" style="font-size:'.$header_setting->$hd->font_size.'px; font-style:'.$header_setting->$hd->font_style.'; color:'.$header_setting->$hd->text_color.'; text-align:'.$header_setting->$hd->text_align.'"> '. _l('ib_invoice_due_date').': '.$invoice_due_date.'</p>';
                }

                if(isset($header_setting->$hd->invoice_amount)){
                    $invoice_amount = isset($updated_data->header->invoice_infor->invoice_amount) ? $updated_data->header->invoice_infor->invoice_amount : $invoice->total;
                    $tblheader .= '<p class="mbot5" style="font-size:'.$header_setting->$hd->font_size.'px; font-style:'.$header_setting->$hd->font_style.'; color:'.$header_setting->$hd->text_color.'; text-align:'.$header_setting->$hd->text_align.'"> '. _l('ib_invoice_amount').': '.app_format_money($invoice_amount, $invoice->currency_name).'</p>';
                }

                if(isset($header_setting->$hd->invoice_status)){ 
                    $tblheader .= '<p class="mbot5" style="font-size:'.$header_setting->$hd->font_size.'px; font-style:'.$header_setting->$hd->font_style.'; color:'.$header_setting->$hd->text_color.'; text-align:'.$header_setting->$hd->text_align.'"> '. _l('ib_invoice_status').': <span style="color:rgb(' . invoice_status_color_pdf($invoice->status) . ');text-transform:uppercase;">'.format_invoice_status($invoice->status, '', false).'</span></p>';
                }

                if(isset($header_setting->$hd->custom_field)){ 
                    foreach($header_setting->$hd->custom_field as $field_id => $field){
                        $cf = get_custom_field_by_id($field_id);
                        if($cf != '' && isset($field->active)){
                            $value = isset($updated_data->header->invoice_infor->custom_field->$field_id->value) ? $updated_data->header->invoice_infor->custom_field->$field_id->value : $field->value; 
                            $tblheader .= '<p class="mbot5" style="font-size:'.$header_setting->$hd->font_size.'px; font-style:'.$header_setting->$hd->font_style.'; color:'.$header_setting->$hd->text_color.'; text-align:'.$header_setting->$hd->text_align.'"> '. $value.'</p>';

                        }
                    }
                }
            }
        }
    }

    $tblheader .= '</td>';
}

$tblheader .= '</tr>';
$tblheader .= '</tbody>';
$tblheader .= '</table>';

$pdf->writeHTML($tblheader, false, false, false, false, '');

// ---- Header section end

// ---- Sender receiver section start

$sender_receiver_setting = $template_setting->sender_receiver;
$sender_receiver_data = [
   'sender_infor',
   'receiver_infor'
  ]; 

$regularSrWidth  = ib_get_regular_table_width($sender_receiver_setting->col_number);

$this->ci->load->model('clients_model'); 
$_client = $this->ci->clients_model->get($invoice->clientid);
$_client_email = ib_get_primary_contact_email($invoice->clientid);

$tblsr = '<table cellpadding="10" style="background-color:'.$sender_receiver_setting->row_color.'">';

$tblsr .= '<tbody>';
$tblsr .= '<tr>';
$tblsr .= '<td>';
$tblsr .= '<table cellpadding="10">';
$tblsr .= '<tbody>';
$tblsr .= '<tr>';

for($i = 1; $i <= $sender_receiver_setting->col_number; $i++){ 
        foreach($sender_receiver_data as $sr){ 
            if(isset($sender_receiver_setting->$sr) && $sender_receiver_setting->$sr->show_on_column == $i){ 
                if($sr == 'sender_infor' && isset($sender_receiver_setting->$sr->active)){ 
                    $tblsr .= '<td width="'.$regularSrWidth.'%" style="width: 100%; background-color:'.$sender_receiver_setting->$sr->column_color.'">';
                    $tblsr .= '<br><b class="fs-20">'. _l('ib_seller').'</b>';

                    if( isset($sender_receiver_setting->$sr->name->active) ){
                        $sender_name = isset($updated_data->sender_receiver->sender_infor->name) ? $updated_data->sender_receiver->sender_infor->name : get_option('invoice_company_name');
                        $tblsr .= '<br><span class="mbot5 mleft10" style="font-size:'.$sender_receiver_setting->$sr->name->font_size.'px font-style:'.$sender_receiver_setting->$sr->name->font_style.'; color:'.$sender_receiver_setting->$sr->name->text_color.';">'. _l('ib_name').': '.$sender_name.'</span>';
                    }
                    if( isset($sender_receiver_setting->$sr->phone->active)) {
                        $sender_phone = isset($updated_data->sender_receiver->sender_infor->phone) ? $updated_data->sender_receiver->sender_infor->phone : get_option('invoice_company_phonenumber');
                        $tblsr .= '<br><span class="mbot5 mleft10" style="font-size:'.$sender_receiver_setting->$sr->phone->font_size.'px font-style:'.$sender_receiver_setting->$sr->phone->font_style.'; color:'.$sender_receiver_setting->$sr->phone->text_color.';">'. _l('ib_phonenumber').': '.$sender_phone.'</span>';
                    }
                    if( isset($sender_receiver_setting->$sr->email->active) ){
                        $sender_email = isset($updated_data->sender_receiver->sender_infor->email) ? $updated_data->sender_receiver->sender_infor->email : get_option('smtp_email');
                        $tblsr .= '<br><span class="mbot5 mleft10" style="font-size:'.$sender_receiver_setting->$sr->email->font_size.'px font-style:'.$sender_receiver_setting->$sr->email->font_style.'; color:'.$sender_receiver_setting->$sr->email->text_color.';">'. _l('ib_email').': '.$sender_email.'</span>';
                    }
                    if( isset($sender_receiver_setting->$sr->website->active) ){
                        $sender_web = isset($updated_data->sender_receiver->sender_infor->website) ? $updated_data->sender_receiver->sender_infor->website : site_url();
                        $tblsr .= '<br><span class="mbot5 mleft10" style="font-size:'.$sender_receiver_setting->$sr->website->font_size.'px font-style:'.$sender_receiver_setting->$sr->website->font_style.'; color:'.$sender_receiver_setting->$sr->website->text_color.';">'. _l('ib_website').': '.$sender_web.'</span>';
                    }
                    if( isset($sender_receiver_setting->$sr->street->active) ){
                        $sender_street = isset($updated_data->sender_receiver->sender_infor->street) ? $updated_data->sender_receiver->sender_infor->street : get_option('invoice_company_address');
                        $tblsr .= '<br><span class="mbot5 mleft10" style="font-size:'.$sender_receiver_setting->$sr->street->font_size.'px font-style:'.$sender_receiver_setting->$sr->street->font_style.'; color:'.$sender_receiver_setting->$sr->street->text_color.';">'._l('ib_street').': '.$sender_street.'</span>';
                    }
                    if( isset($sender_receiver_setting->$sr->city->active) ){
                        $sender_city = isset($updated_data->sender_receiver->sender_infor->city) ? $updated_data->sender_receiver->sender_infor->city : get_option('invoice_company_city');
                        $tblsr .= '<br><span class="mbot5 mleft10" style="font-size:'.$sender_receiver_setting->$sr->city->font_size.'px font-style:'.$sender_receiver_setting->$sr->city->font_style.'; color:'.$sender_receiver_setting->$sr->city->text_color.';">'._l('ib_city').': '.$sender_city.'</span>';
                    }
                    if( isset($sender_receiver_setting->$sr->state->active) ){
                        $sender_state = isset($updated_data->sender_receiver->sender_infor->state) ? $updated_data->sender_receiver->sender_infor->state : get_option('company_state');
                        $tblsr .= '<br><span class="mbot5 mleft10" style="font-size:'.$sender_receiver_setting->$sr->state->font_size.'px font-style:'.$sender_receiver_setting->$sr->state->font_style.'; color:'.$sender_receiver_setting->$sr->state->text_color.';">'._l('ib_state').': '.$sender_state.'</span>';
                    }
                    if( isset($sender_receiver_setting->$sr->zipcode->active) ){
                        $sender_zipcode = isset($updated_data->sender_receiver->sender_infor->zipcode) ? $updated_data->sender_receiver->sender_infor->zipcode : get_option('invoice_company_postal_code');
                        $tblsr .= '<br><span class="mbot5 mleft10" style="font-size:'.$sender_receiver_setting->$sr->zipcode->font_size.'px font-style:'.$sender_receiver_setting->$sr->zipcode->font_style.'; color:'.$sender_receiver_setting->$sr->zipcode->text_color.';">'._l('ib_zipcode').': '.$sender_zipcode.'</span>';
                    }
                    if( isset($sender_receiver_setting->$sr->country->active) ){
                        $sender_country = isset($updated_data->sender_receiver->sender_infor->country) ? $updated_data->sender_receiver->sender_infor->country : get_option('invoice_company_country_code');
                        $tblsr .= '<br><span class="mbot5 mleft10" style="font-size:'.$sender_receiver_setting->$sr->country->font_size.'px font-style:'.$sender_receiver_setting->$sr->country->font_style.'; color:'.$sender_receiver_setting->$sr->country->text_color.';">'._l('ib_country').': '.$sender_country.'</span>';
                    }
                    if( isset($sender_receiver_setting->$sr->vat->active) ){
                        $sender_vat = isset($updated_data->sender_receiver->sender_infor->vat) ? $updated_data->sender_receiver->sender_infor->vat : get_option('company_vat');
                        $tblsr .= '<br><span class="mbot5 mleft10" style="font-size:'.$sender_receiver_setting->$sr->vat->font_size.'px font-style:'.$sender_receiver_setting->$sr->vat->font_style.'; color:'.$sender_receiver_setting->$sr->vat->text_color.';">'._l('ib_vat_number').': '.$sender_vat.'</span>';
                    }
                    if( isset($sender_receiver_setting->$sr->sales_person->active) ){
                        $sales_person = isset($updated_data->sender_receiver->sender_infor->sales_person) ? $updated_data->sender_receiver->sender_infor->sales_person : get_staff_full_name($invoice->sale_agent);
                        $tblsr .= '<br><span class="mbot5 mleft10" style="font-size:'.$sender_receiver_setting->$sr->sales_person->font_size.'px font-style:'.$sender_receiver_setting->$sr->sales_person->font_style.'; color:'.$sender_receiver_setting->$sr->sales_person->text_color.';">'._l('ib_sales_person').': '.$sales_person.'</span>';
                    }

                    if( isset($sender_receiver_setting->$sr->custom_field) ){
                        $sender_custom_field = $sender_receiver_setting->$sr->custom_field;
                        foreach($sender_custom_field as $field_id => $field){
                            $cf = get_custom_field_by_id($field_id);
                            if($cf != '' && isset($field->active)){
                                $value = isset($updated_data->sender_receiver->sender_infor->custom_field->$field_id->value) ? $updated_data->sender_receiver->sender_infor->custom_field->$field_id->value : $field->value;
                                $tblsr .= '<br><span class="mbot5 mleft10" style="font-size:'.$field->font_size.'px; font-style:'.$field->font_style.'; color:'.$field->text_color.';">'. $value.'</span>';
                            }
                        }
                    }
                    $tblsr .= '<br>';
                    $tblsr .= '</td>';
                }else if($sr == 'receiver_infor' && isset($sender_receiver_setting->$sr->active)){ 
                    $tblsr .= '<td width="'.$regularSrWidth.'%" style="width: 100%; background-color:'.$sender_receiver_setting->$sr->column_color.'">';
                    $tblsr .= '<br><b class="fs-20">'. _l('ib_buyer').'</b>';
                    if( isset($sender_receiver_setting->$sr->name->active) ){ 
                        $receiver_name = isset($updated_data->sender_receiver->receiver_infor->name) ? $updated_data->sender_receiver->receiver_infor->name : get_company_name($invoice->clientid);
                        $tblsr .= '<br><span class="mbot5 mleft10" style="font-size:'.$sender_receiver_setting->$sr->name->font_size.'px font-style:'.$sender_receiver_setting->$sr->name->font_style.'; color:'.$sender_receiver_setting->$sr->name->text_color.';">'. _l('ib_name').': '.$receiver_name.'</span>';
                    }
                    if( isset($sender_receiver_setting->$sr->phone->active) ){
                        $receiver_phone = isset($updated_data->sender_receiver->receiver_infor->phone) ? $updated_data->sender_receiver->receiver_infor->phone : $_client->phonenumber;
                        $tblsr .= '<br><span class="mbot5 mleft10" style="font-size:'.$sender_receiver_setting->$sr->phone->font_size.'px font-style:'.$sender_receiver_setting->$sr->phone->font_style.'; color:'.$sender_receiver_setting->$sr->phone->text_color.';">'. _l('ib_phonenumber').': '.$receiver_phone.'</span>';
                    }
                    if( isset($sender_receiver_setting->$sr->email->active) ){ 
                        $receiver_email = isset($updated_data->sender_receiver->receiver_infor->email) ? $updated_data->sender_receiver->receiver_infor->email : $_client_email;
                        $tblsr .= '<br><span class="mbot5 mleft10" style="font-size:'.$sender_receiver_setting->$sr->email->font_size.'px font-style:'.$sender_receiver_setting->$sr->email->font_style.'; color:'.$sender_receiver_setting->$sr->email->text_color.';">'. _l('ib_email').': '.$receiver_email.'</span>';
                    }
                    if( isset($sender_receiver_setting->$sr->website->active) ){ 
                        $receiver_website = isset($updated_data->sender_receiver->receiver_infor->website) ? $updated_data->sender_receiver->receiver_infor->website : $_client->website;
                        $tblsr .= '<br><span class="mbot5 mleft10" style="font-size:'.$sender_receiver_setting->$sr->website->font_size.'px font-style:'.$sender_receiver_setting->$sr->website->font_style.'; color:'.$sender_receiver_setting->$sr->website->text_color.';">'. _l('ib_website').': '.$receiver_website.'</span>';
                    }
                    if( isset($sender_receiver_setting->$sr->street->active) ){ 
                        $receiver_street = isset($updated_data->sender_receiver->receiver_infor->street) ? $updated_data->sender_receiver->receiver_infor->street : $_client->address;
                        $tblsr .= '<br><span class="mbot5 mleft10" style="font-size:'.$sender_receiver_setting->$sr->street->font_size.'px font-style:'.$sender_receiver_setting->$sr->street->font_style.'; color:'.$sender_receiver_setting->$sr->street->text_color.';">'. _l('ib_street').': '.$receiver_street.'</span>';
                    }
                    if( isset($sender_receiver_setting->$sr->city->active) ){ 
                        $receiver_city = isset($updated_data->sender_receiver->receiver_infor->city) ? $updated_data->sender_receiver->receiver_infor->city : $_client->city;
                        $tblsr .= '<br><span class="mbot5 mleft10" style="font-size:'.$sender_receiver_setting->$sr->city->font_size.'px font-style:'.$sender_receiver_setting->$sr->city->font_style.'; color:'.$sender_receiver_setting->$sr->city->text_color.';">'. _l('ib_city').': '.$receiver_city.'</span>';
                    }
                    if( isset($sender_receiver_setting->$sr->state->active) ){ 
                        $receiver_state = isset($updated_data->sender_receiver->receiver_infor->state) ? $updated_data->sender_receiver->receiver_infor->state : $_client->state;
                        $tblsr .= '<br><span class="mbot5 mleft10" style="font-size:'.$sender_receiver_setting->$sr->state->font_size.'px font-style:'.$sender_receiver_setting->$sr->state->font_style.'; color:'.$sender_receiver_setting->$sr->state->text_color.';">'. _l('ib_state').': '.$receiver_state.'</span>';
                    }
                    if( isset($sender_receiver_setting->$sr->zipcode->active) ){ 
                        $receiver_zipcode = isset($updated_data->sender_receiver->receiver_infor->zipcode) ? $updated_data->sender_receiver->receiver_infor->zipcode : $_client->zip;
                        $tblsr .= '<br><span class="mbot5 mleft10" style="font-size:'.$sender_receiver_setting->$sr->zipcode->font_size.'px font-style:'.$sender_receiver_setting->$sr->zipcode->font_style.'; color:'.$sender_receiver_setting->$sr->zipcode->text_color.';">'. _l('ib_zipcode').': '.$receiver_zipcode.'</span>';
                    }
                    if( isset($sender_receiver_setting->$sr->country->active) ){ 
                        $receiver_country = isset($updated_data->sender_receiver->receiver_infor->country) ? $updated_data->sender_receiver->receiver_infor->country : get_country_name($_client->country);
                        $tblsr .= '<br><span class="mbot5 mleft10" style="font-size:'.$sender_receiver_setting->$sr->country->font_size.'px font-style:'.$sender_receiver_setting->$sr->country->font_style.'; color:'.$sender_receiver_setting->$sr->country->text_color.';">'. _l('ib_country').': '.$receiver_country.'</span>';
                    }
                    if( isset($sender_receiver_setting->$sr->vat->active) ){ 
                        $receiver_vat = isset($updated_data->sender_receiver->receiver_infor->vat) ? $updated_data->sender_receiver->receiver_infor->vat : $_client->vat;
                        $tblsr .= '<br><span class="mbot5 mleft10" style="font-size:'.$sender_receiver_setting->$sr->vat->font_size.'px font-style:'.$sender_receiver_setting->$sr->vat->font_style.'; color:'.$sender_receiver_setting->$sr->vat->text_color.';">'. _l('ib_vat_number').': '.$receiver_vat.'</span>';
                    }
                    if( isset($sender_receiver_setting->$sr->billing_to->active) ){ 
                        $billing_to = isset($updated_data->sender_receiver->receiver_infor->billing_to) ? $updated_data->sender_receiver->receiver_infor->billing_to : strip_tags(format_customer_info($invoice, 'invoice', 'billing'));
                        $tblsr .= '<br><span class="mbot5 mleft10" style="font-size:'.$sender_receiver_setting->$sr->billing_to->font_size.'px font-style:'.$sender_receiver_setting->$sr->billing_to->font_style.'; color:'.$sender_receiver_setting->$sr->billing_to->text_color.';">'. _l('ib_billing_to').': '.$billing_to.'</span>';
                    }
                    if( isset($sender_receiver_setting->$sr->shipping_to->active) ){ 
                        $shipping_to = isset($updated_data->sender_receiver->receiver_infor->shipping_to) ? $updated_data->sender_receiver->receiver_infor->shipping_to : strip_tags(format_customer_info($invoice, 'invoice', 'shipping'));
                        $tblsr .= '<br><span class="mbot5 mleft10" style="font-size:'.$sender_receiver_setting->$sr->shipping_to->font_size.'px font-style:'.$sender_receiver_setting->$sr->shipping_to->font_style.'; color:'.$sender_receiver_setting->$sr->shipping_to->text_color.';">'. _l('ib_shipping_to').': '.$shipping_to.'</span>';
                    }
                    if( isset($sender_receiver_setting->$sr->custom_field) ){
                        $sender_custom_field = $sender_receiver_setting->$sr->custom_field;
                        foreach($sender_custom_field as $field_id => $field){
                            $cf = get_custom_field_by_id($field_id);
                            if($cf != '' && isset($field->active)){
                                $value = isset($updated_data->sender_receiver->receiver_infor->custom_field->$field_id->value) ? $updated_data->sender_receiver->receiver_infor->custom_field->$field_id->value : $field->value;
                                $tblsr .= '<br><span class="mbot5 mleft10" style="font-size:'.$field->font_size.'px; font-style:'.$field->font_style.'; color:'.$field->text_color.';">'. $value.'</span>';
                            }
                        }
                    }
                    $tblsr .= '<br>';
                    $tblsr .= '</td>';
                }
            }
        }
}
$tblsr .= '</tr>';
$tblsr .= '</tbody>';
$tblsr .= '</table>';
$tblsr .= '</td>';
$tblsr .= '</tr>';
$tblsr .= '</tbody>';
$tblsr .= '</table>';
$tblsr .= '<link href="' . FCPATH.'modules/invoices_builder/assets/css/pdf.css' . '"  rel="stylesheet" type="text/css" />';

if(isset($sender_receiver_setting->sender_infor->active) || isset($sender_receiver_setting->receiver_infor->active)){
    $pdf->writeHTML($tblsr, false, false, false, false, '');
}

// The items table
$item_table_setting = $template_setting->item_table;
$table_columns = [
    'item_order',
    'item_name',
    'description',
    'quantity',
    'unit',
    'unit_price',
    'tax_rate',
    'tax_amount',
    'subtotal',
    'sku',
    'image',
    'barcode'
];

$header_style = '';
$header_style .= (isset($item_table_setting->text_align) ? 'text-align:'.$item_table_setting->text_align.';' : '');
$header_style .= (isset($item_table_setting->font_style) ? 'font-style:'.$item_table_setting->font_style.';' : '');
$header_style .= (isset($item_table_setting->font_size) ? 'font-size:'.$item_table_setting->font_size.'px;' : '');
$header_style .= (isset($item_table_setting->text_color) ? 'color:'.$item_table_setting->text_color.';' : '');

$num_col = count($table_columns);
if(isset($item_table_setting->custom_field)){ 
    $num_col = $num_col + count((array) $item_table_setting->custom_field);
}

$tbl_items = '';
$tbl_items .= '<table cellpadding="10">';
$tbl_items .= '<tr style="background-color:'.$item_table_setting->thead_color.'">';
for($i = 1; $i <= $num_col; $i++){
    foreach($table_columns as $col){
        if(isset($item_table_setting->$col->active) && $item_table_setting->$col->order_column == $i){
            $width = '';
            if( isset($item_table_setting->description->active) ){
                if($col == 'item_order'){
                    $width = 'width="5%"';
                }else if($col == 'description'){
                    $width .= 'width="22%"';
                }else if($col == 'quantity'){
                    $width = 'width="10%"';
                }
            }
            $tbl_items .= '<th '.$width.' class="bold" style="'.$header_style.' border-right: 1px solid #f0f0f0;">'._l($col).'</th>';
        }
    }
    if(isset($item_table_setting->custom_field)){ 
        foreach($item_table_setting->custom_field as $index => $_field){ 
            $cf = get_custom_field_by_id($index);
            if($_field->order_column == $i && $cf != '' && isset($_field->active)){
                $tbl_items .=  '<th class="bold" style="'.$header_style.' border-right: 1px solid #f0f0f0;">'.$cf->label.'</th>';
            } 
        }
    }
}
$tbl_items .= '</tr>';
$tbl_items .= '<tbody>';
foreach($invoice->items as $it_key => $inv_item){
    $tr_style = '';
    if(isset($item_table_setting->striped_row->active)){
        
        if( (($it_key + 1) % 2) == 0){
            $tr_style = 'style=" background-color:'. $item_table_setting->striped_row->even_row_color.';"';
        }else{
            $tr_style = 'style=" background-color:'. $item_table_setting->striped_row->odd_row_color.';"';
        }
    }

    $item_taxes = get_invoice_item_taxes($inv_item['id']); 
    $tax_amount_formula = 0;
    $tax_rate_formula = 0;
    if (count($item_taxes) > 0) {
        foreach ($item_taxes as $tax) {
            $tax_amount_formula += $inv_item['qty'] * $inv_item['rate'] * $tax['taxrate'] / 100;
            $tax_rate_formula += $tax['taxrate'];
        }
    }

    $subtotal_formula =  $inv_item['qty'] * $inv_item['rate'] + $tax_rate_formula;

    $data_item_table_formula = [
        ['id' => 'item_table|quantity', 'value' => $inv_item['qty']],
        ['id' => 'item_table|tax_rate', 'value' => $tax_rate_formula],
        ['id' => 'item_table|tax_amount', 'value' => $tax_amount_formula],
        ['id' => 'item_table|unit_price', 'value' => $inv_item['rate']],
        ['id' => 'item_table|subtotal', 'value' => $subtotal_formula]
    ];

    if(isset($item_table_setting->custom_field)){ 
        foreach($item_table_setting->custom_field as $index => $_field){
            $cf = get_custom_field_by_id($index);
            if($_field->value != ''){ 
               $data_fml = [ 
                  'id' => 'item_table|'.$cf->name, 
                  'value' => $_field->value
               ];
               $data_item_table_formula[] = $data_fml;
            }else if($_field->value == '' && $_field->formula != ''){ 
               $data_fml = [ 
                  'id' => 'item_table|'.$cf->name, 
                  'value' => calculate_formula($data_item_table_formula, $_field->formula)
               ];
               $data_item_table_formula[] = $data_fml;
            }
        }
    }

    $tbl_items .= '<tr '.$tr_style.'>';
    for($i = 1; $i <= $num_col; $i++){
        foreach($table_columns as $col){
            if(isset($item_table_setting->$col->active) && $item_table_setting->$col->order_column == $i){
                $item = ib_get_item_by_name($inv_item['description']);
                if($col == 'item_order'){
                    $tbl_items .= '<td style="text-align:'.$item_table_setting->$col->text_align.';"><span style="font-size:'.$item_table_setting->$col->font_size.'px; font-style:'.$item_table_setting->$col->font_style.'; color:'.$item_table_setting->$col->text_color.'; ">'.($it_key + 1).'</span></td>';
                }else if($col == 'item_name'){
                    $tbl_items .= '<td style="text-align:'.$item_table_setting->$col->text_align.';"><span style="font-size:'.$item_table_setting->$col->font_size.'px; font-style:'.$item_table_setting->$col->font_style.'; color:'.$item_table_setting->$col->text_color.'; ">'.$inv_item['description'].'</span></td>';
                }else if($col == 'description'){
                    $tbl_items .= '<td style="text-align:'.$item_table_setting->$col->text_align.';"><span style="font-size:'.$item_table_setting->$col->font_size.'px; font-style:'.$item_table_setting->$col->font_style.'; color:'.$item_table_setting->$col->text_color.'; ">'.$inv_item['long_description'].'</span></td>';
                }else if($col == 'quantity'){
                    $tbl_items .= '<td style="text-align:'.$item_table_setting->$col->text_align.';"><span style="font-size:'.$item_table_setting->$col->font_size.'px; font-style:'.$item_table_setting->$col->font_style.'; color:'.$item_table_setting->$col->text_color.'; ">'.$inv_item['qty'].'</span></td>';
                }else if($col == 'unit'){
                    $tbl_items .= '<td style="text-align:'.$item_table_setting->$col->text_align.';"><span style="font-size:'.$item_table_setting->$col->font_size.'px; font-style:'.$item_table_setting->$col->font_style.'; color:'.$item_table_setting->$col->text_color.'; ">'.$inv_item['unit'].'</span></td>';
                }else if($col == 'unit_price'){
                    $tbl_items .= '<td style="text-align:'.$item_table_setting->$col->text_align.';"><span style="font-size:'.$item_table_setting->$col->font_size.'px; font-style:'.$item_table_setting->$col->font_style.'; color:'.$item_table_setting->$col->text_color.'; ">'.app_format_money($inv_item['rate'], $invoice->currency_name).'</span></td>';
                }else if($col == 'tax_rate'){
                    $item_taxes = get_invoice_item_taxes($inv_item['id']); 
                    $itemHTML = '';
                    if (count($item_taxes) > 0) {
                        foreach ($item_taxes as $tax) {
                            $item_tax = '';
                            if ((count($item_taxes) > 1 && get_option('remove_tax_name_from_item_table') == false) || get_option('remove_tax_name_from_item_table') == false || multiple_taxes_found_for_item($item_taxes)) {
                                $tmp      = explode('|', $tax['taxname']);
                                $item_tax = $tmp[0] . ' ' . app_format_number($tmp[1]) . '%<br />';
                            } else {
                                $item_tax .= app_format_number($tax['taxrate']) . '%';
                            }
                            $itemHTML .= hooks()->apply_filters('item_tax_table_row', $item_tax, $inv_item);
                         }
                    } else {
                        $itemHTML .= hooks()->apply_filters('item_tax_table_row', '0%', $inv_item);
                    }

                    $tbl_items .= '<td style="text-align:'.$item_table_setting->$col->text_align.';"><span style="font-size:'.$item_table_setting->$col->font_size.'px; font-style:'.$item_table_setting->$col->font_style.'; color:'.$item_table_setting->$col->text_color.'; ">'.$itemHTML.'</span></td>';
                }else if($col == 'tax_amount'){
                    $item_taxes = get_invoice_item_taxes($inv_item['id']); 
                    $tax_amount = 0;
                    if (count($item_taxes) > 0) {
                        foreach ($item_taxes as $tax) {
                            $tax_amount += $inv_item['qty'] * $inv_item['rate'] * $tax['taxrate'] / 100;
                        }
                    }
                    $tbl_items .= '<td style="text-align:'.$item_table_setting->$col->text_align.';"><span style="font-size:'.$item_table_setting->$col->font_size.'px; font-style:'.$item_table_setting->$col->font_style.'; color:'.$item_table_setting->$col->text_color.'; ">'.app_format_money($tax_amount, $invoice->currency_name).'</span></td>';
                }else if($col == 'subtotal'){
                    $item_taxes = get_invoice_item_taxes($inv_item['id']); 
                    $tax_amount = 0;
                    if (count($item_taxes) > 0) {
                        foreach ($item_taxes as $tax) {
                            $tax_amount += $inv_item['qty'] * $inv_item['rate'] * $tax['taxrate'] / 100;
                        }
                    }
                    $subtotal =  $inv_item['qty'] * $inv_item['rate'] + $tax_amount;

                    $tbl_items .= '<td style="text-align:'.$item_table_setting->$col->text_align.';"><span style="font-size:'.$item_table_setting->$col->font_size.'px; font-style:'.$item_table_setting->$col->font_style.'; color:'.$item_table_setting->$col->text_color.'; ">'.app_format_money($subtotal, $invoice->currency_name).'</span></td>';
                }else if($col == 'sku'){
                    $sku = isset($item->sku_code) ? $item->sku_code : '';
                    $_sku = $sku;
                    if(isset($updated_data)){
                        foreach($updated_data->item_table as $int_item_id => $it){
                            if($int_item_id == $inv_item['id']){
                                $_sku = $it->sku;
                            }
                        }
                    }
                    $tbl_items .= '<td style="text-align:'.$item_table_setting->$col->text_align.';"><span style="font-size:'.$item_table_setting->$col->font_size.'px; font-style:'.$item_table_setting->$col->font_style.'; color:'.$item_table_setting->$col->text_color.'; ">'.$_sku.'</span></td>';
                }else if($col == 'barcode'){
                    $barcode = isset($item->commodity_barcode) ? $item->commodity_barcode : '';
                    $_barcode = $barcode;
                    if(isset($updated_data)){
                        foreach($updated_data->item_table as $int_item_id => $it){
                            if($int_item_id == $inv_item['id']){
                                $_barcode = $it->barcode;
                            }
                        }
                    }
                    $tbl_items .= '<td style="text-align:'.$item_table_setting->$col->text_align.';"><span style="font-size:'.$item_table_setting->$col->font_size.'px; font-style:'.$item_table_setting->$col->font_style.'; color:'.$item_table_setting->$col->text_color.'; ">'.$_barcode.'</span></td>';

                }else if($col == 'image'){
                    $item_id = isset($item->id) ? $item->id : 0;
                    $arr_images = ib_get_item_attachments($item_id);
                    $style = 'width: '.$item_table_setting->$col->width.'px; height:'.$item_table_setting->$col->height.'px';
                    if(count($arr_images) > 0){
                        if(file_exists('modules/warehouse/uploads/item_img/' . $arr_images[0]['rel_id'] . '/' . $arr_images[0]['file_name'])){
                            $_data = '<img class="images_w_table" src="' . FCPATH .'modules/warehouse/uploads/item_img/' . $arr_images[0]['rel_id'] .'/'.$arr_images[0]['file_name'].'" alt="'.$arr_images[0]['file_name'] .'" style="'.$style.'">';
                        }else if(file_exists('modules/warehouse/uploads/item_img/'.$arr_images[0]['rel_id'] .'/'.$arr_images[0]['file_name'])){
                            $_data = '<img class="images_w_table" src="' . FCPATH.'modules/purchase/uploads/item_img/' . $arr_images[0]['rel_id'] .'/'.$arr_images[0]['file_name'].'" alt="'.$arr_images[0]['file_name'] .'" style="'.$style.'" >';
                        }else {
                            $_data = '<img class="images_w_table" src="' . FCPATH.'modules/manufacturing/uploads/products/' . $arr_images[0]['rel_id'] . '/' . $arr_images[0]['file_name'] . '" alt="' . $arr_images[0]['file_name'] . '" style="'.$style.'">';
                        }

                    }else{

                        $_data = '<img class="images_w_table" src="' . FCPATH.'modules/purchase/uploads/nul_image.jpg' .'" alt="nul_image.jpg" style="'.$style.'">';
                    }
                    $tbl_items .= '<td>'.$_data.'</td>';

                }
            }
        }

        if(isset($item_table_setting->custom_field)){ 
            foreach($item_table_setting->custom_field as $index => $_field){
                $cf = get_custom_field_by_id($index);
                if($_field->order_column == $i && $cf != '' && isset($_field->active)){
                    $_value = '';
                    if($_field->value != ''){
                        $value = $_field->value;
                        if(isset($updated_data)){
                            foreach($updated_data->item_table as $int_item_id => $it){
                                if($int_item_id == $inv_item['id']){
                                    $value = $it->custom_field->$index->value;
                                }
                            }
                        }
                        $_value = $value;
                    }else if($_field->value == '' && $_field->formula != ''){
                        $formula_value = calculate_formula($data_item_table_formula, $_field->formula);
                        $value = $formula_value;
                        if(isset($updated_data)){
                            foreach($updated_data->item_table as $int_item_id => $it){
                                if($int_item_id == $inv_item['id']){
                                    $value = $it->custom_field->$index->value;
                                }
                            }
                        }
                        $_value = app_format_money($value, $invoice->currency_name);
                    } 
                    $tbl_items .= '<td style="text-align:'.$_field->text_align.';"><span style="font-size:'.$_field->font_size.'px; font-style:'.$_field->font_style.'; color:'.$_field->text_color.'; ">'.$_value.'</span></td>';
                }
            }
        }
    }
    $tbl_items .= '</tr>';
}

$tbl_items .= '</tbody>';
$tbl_items .= '</table>';

$pdf->writeHTML($tbl_items, false, false, false, false, '');


$invoice_total_setting = $template_setting->invoice_total;
$invoice_total_data = [
    'subtotal_label',
    'subtotal_value',
    'taxes_label',
    'taxes_value',
    'total_tax_label',
    'total_tax_value',
    'discount_label',
    'discount_value',
    'grand_total_label',
    'grand_total_value',
    'payment_amount_label',
    'payment_amount_value'
]; 
$regularTotalWidth  = ib_get_regular_table_width($sender_receiver_setting->col_number);

$tbltotal = '<table cellpadding="10">';
$tbltotal .= '<tbody>';
$tbltotal .= '<tr style="background-color:'.$invoice_total_setting->row_color.'">';
for($i = 1; $i <= $invoice_total_setting->col_number; $i++){
    $tbltotal .= '<td>';
    foreach($invoice_total_data as $inv_t){
        if(isset($invoice_total_setting->$inv_t->active) && $invoice_total_setting->$inv_t->order_column == $i){
            if( $inv_t == 'subtotal_label'){
                $label = isset($updated_data->invoice_total->subtotal_label->label_content) ? $updated_data->invoice_total->subtotal_label->label_content : $invoice_total_setting->$inv_t->label_content;
                $tbltotal .= '<p style="font-size:'.$invoice_total_setting->$inv_t->font_size.'px; font-style:'.$invoice_total_setting->$inv_t->font_style.'; color:'.$invoice_total_setting->$inv_t->text_color.'; text-align:'.$invoice_total_setting->$inv_t->text_align.';">'.$label.'</p>';
            }else if( $inv_t == 'subtotal_value'){
                $tbltotal .= '<p style="font-size:'.$invoice_total_setting->$inv_t->font_size.'px; font-style:'.$invoice_total_setting->$inv_t->font_style.'; color:'.$invoice_total_setting->$inv_t->text_color.'; text-align:'.$invoice_total_setting->$inv_t->text_align.';">'.app_format_money($invoice->subtotal, $invoice->currency_name).'</p>';
            }else if($inv_t == 'taxes_label'){
                $items = get_items_table_data($invoice, 'invoice', 'html', true);
                if(count($items->taxes()) > 0){
                    foreach($items->taxes() as $k => $tax){
                        $tbltotal .= '<p style="font-size:'.$invoice_total_setting->$inv_t->font_size.'px; font-style:'.$invoice_total_setting->$inv_t->font_style.'; color:'.$invoice_total_setting->$inv_t->text_color.'; text-align:'.$invoice_total_setting->$inv_t->text_align.';">'.$tax['taxname'].' ('.app_format_number($tax['taxrate']).'%)'.'</p>';
                    }
                }
            }else if($inv_t == 'taxes_value'){
                $items = get_items_table_data($invoice, 'invoice', 'html', true);
                if(count($items->taxes()) > 0){
                    foreach($items->taxes() as $k => $tax){ 
                        $tbltotal .= '<p style="font-size:'.$invoice_total_setting->$inv_t->font_size.'px; font-style:'.$invoice_total_setting->$inv_t->font_style.'; color:'.$invoice_total_setting->$inv_t->text_color.'; text-align:'.$invoice_total_setting->$inv_t->text_align.';">'.app_format_money($tax['total_tax'], $invoice->currency_name).'</p>';
                    }
                }

                
            }else if($inv_t == 'total_tax_label'){
                $label = isset($updated_data->invoice_total->total_tax_label->label_content) ? $updated_data->invoice_total->total_tax_label->label_content : $invoice_total_setting->$inv_t->label_content;
                $tbltotal .= '<p style="font-size:'.$invoice_total_setting->$inv_t->font_size.'px; font-style:'.$invoice_total_setting->$inv_t->font_style.'; color:'.$invoice_total_setting->$inv_t->text_color.'; text-align:'.$invoice_total_setting->$inv_t->text_align.';">'.$label.'</p>';
            }else if($inv_t == 'total_tax_value'){
                $tbltotal .= '<p style="font-size:'.$invoice_total_setting->$inv_t->font_size.'px; font-style:'.$invoice_total_setting->$inv_t->font_style.'; color:'.$invoice_total_setting->$inv_t->text_color.'; text-align:'.$invoice_total_setting->$inv_t->text_align.';">'.app_format_money($invoice->total_tax, $invoice->currency_name).'</p>';
            }else if($inv_t == 'discount_label'){
                $label = isset($updated_data->invoice_total->discount_label->label_content) ? $updated_data->invoice_total->discount_label->label_content : $invoice_total_setting->$inv_t->label_content;
                $tbltotal .= '<p style="font-size:'.$invoice_total_setting->$inv_t->font_size.'px; font-style:'.$invoice_total_setting->$inv_t->font_style.'; color:'.$invoice_total_setting->$inv_t->text_color.'; text-align:'.$invoice_total_setting->$inv_t->text_align.';">'.$label.'</p>';
            }else if($inv_t == 'discount_value'){
                $tbltotal .= '<p style="font-size:'.$invoice_total_setting->$inv_t->font_size.'px; font-style:'.$invoice_total_setting->$inv_t->font_style.'; color:'.$invoice_total_setting->$inv_t->text_color.'; text-align:'.$invoice_total_setting->$inv_t->text_align.';">'.app_format_money($invoice->discount_total, $invoice->currency_name).'</p>';
            }else if($inv_t == 'grand_total_label'){
                $label = isset($updated_data->invoice_total->grand_total_label->label_content) ? $updated_data->invoice_total->grand_total_label->label_content : $invoice_total_setting->$inv_t->label_content;
                $font_style = $invoice_total_setting->$inv_t->font_style;
                if($font_style == 'initial'){
                    $font_style = 'normal';
                }
                $tbltotal .= '<p style="font-size:'.$invoice_total_setting->$inv_t->font_size.'px; font-style:'.$font_style.'; color:'.$invoice_total_setting->$inv_t->text_color.'; text-align:'.$invoice_total_setting->$inv_t->text_align.';">'.$label.'</p>';
            }else if($inv_t == 'grand_total_value'){
                $tbltotal .= '<p style="font-size:'.$invoice_total_setting->$inv_t->font_size.'px; font-style:'.$invoice_total_setting->$inv_t->font_style.'; color:'.$invoice_total_setting->$inv_t->text_color.'; text-align:'.$invoice_total_setting->$inv_t->text_align.';">'.app_format_money($invoice->total, $invoice->currency_name).'</p>';
            }else if($inv_t == 'payment_amount_label'){
                $label = isset($updated_data->invoice_total->payment_amount_label->label_content) ? $updated_data->invoice_total->payment_amount_label->label_content : $invoice_total_setting->$inv_t->label_content;
                $tbltotal .= '<p style="font-size:'.$invoice_total_setting->$inv_t->font_size.'px; font-style:'.$invoice_total_setting->$inv_t->font_style.'; color:'.$invoice_total_setting->$inv_t->text_color.'; text-align:'.$invoice_total_setting->$inv_t->text_align.';">'.$label.'</p>';
            }else if($inv_t == 'payment_amount_value'){
                $invoice_payment_amount = $invoice->total - get_invoice_total_left_to_pay($invoice->id);
                $tbltotal .= '<p style="font-size:'.$invoice_total_setting->$inv_t->font_size.'px; font-style:'.$invoice_total_setting->$inv_t->font_style.'; color:'.$invoice_total_setting->$inv_t->text_color.'; text-align:'.$invoice_total_setting->$inv_t->text_align.';">'.app_format_money($invoice_payment_amount, $invoice->currency_name).'</p>';
            }
        }
    }

    $subtotal_invoice_total_formula = 0;
    foreach($invoice->items as $it_key => $inv_item){ 
        $item_taxes = get_invoice_item_taxes($inv_item['id']); 
        $tax_amount = 0;
        if (count($item_taxes) > 0) {
            foreach ($item_taxes as $tax) {
                $tax_amount += $inv_item['qty'] * $inv_item['rate'] * $tax['taxrate'] / 100;
            }
        }

        $subtotal =  $inv_item['qty'] * $inv_item['rate'] + $tax_amount;

        $subtotal_invoice_total_formula += $subtotal;
    }

    $data_invoice_total_formula = [
        ['id' => 'item_table|subtotal', 'value' => $subtotal_invoice_total_formula],
        ['id' => 'invoice_total|subtotal_value', 'value' => $invoice->subtotal],
        ['id' => 'invoice_total|total_tax_value','value' => $invoice->total_tax],
        ['id' => 'invoice_total|discount_value', 'value' => $invoice->discount_total],
        ['id' => 'invoice_total|grand_total_value', 'value' => $invoice->total]
    ];

    if( isset($invoice_total_setting->custom_field) ){ 
        $invoice_total_field = $invoice_total_setting->custom_field;
        foreach($invoice_total_field as $field_id => $field){ 
            $cf = get_custom_field_by_id($field_id);
            if($field->value != ''){
               $data_fml = [ 
                  'id' => 'invoice_total|'.$cf->name, 
                  'value' => $_field->value
               ];
               $data_invoice_total_formula[] = $data_fml;
            }else if($field->value == '' && $field->formula != ''){ 
               $data_fml = [ 
                  'id' => 'invoice_total|'.$cf->name, 
                  'value' => calculate_formula($data_invoice_total_formula, $_field->formula)
               ];
               $data_invoice_total_formula[] = $data_fml;
            }
        }
    }

    if( isset($invoice_total_setting->custom_field) ){
        $invoice_total_field = $invoice_total_setting->custom_field;
        foreach($invoice_total_field as $field_id => $field){
            $cf = get_custom_field_by_id($field_id);
            if($cf != '' && $field->order_column == $i && isset($field->active)){
                if($field->value != ''){
                    $value = isset($updated_data->invoice_total->custom_field->$field_id->value) ? $updated_data->invoice_total->custom_field->$field_id->value : $field->value;

                    $tbltotal .= '<p style="font-size:'.$field->font_size.'px; font-style:'.$field->font_style.'; color:'.$field->text_color.'; text-align:'.$field->text_align.'"> '.$value.'</p>';
                }else if($field->value == '' && $field->formula != ''){
                    
                    $invoice_total_formula_value = calculate_formula($data_item_table_formula, $_field->formula);
                    $value = isset($updated_data->invoice_total->custom_field->$field_id->value) ? $updated_data->invoice_total->custom_field->$field_id->value : $invoice_total_formula_value;
                    $tbltotal .= '<p style="font-size:'.$field->font_size.'px; font-style:'.$field->font_style.'; color:'.$field->text_color.'; text-align:'.$field->text_align.'"> '.app_format_money($value, $invoice->currency_name).'</p>';

                }
            }
        }
    }

    $tbltotal .= '</td>';
}
$tbltotal .= '</tr>';
$tbltotal .= '</tbody>';
$tbltotal .= '</table>';


$pdf->writeHTML($tbltotal, false, false, false, false, '');

$bot_infor_setting = $template_setting->bottom_information;
$bottom_data = [
    'payment_method',
    'bank_infor',
    'signature',
    'qrcode',
    'messages',
    'delivery_note',
    'client_note',
]; 

$regularBotWidth  = ib_get_regular_table_width($bot_infor_setting->col_number);

$tblbottom = '<table cellpadding="10">';
$tblbottom .= '<tbody>';
$tblbottom .= '<tr style="background-color:'.$bot_infor_setting->row_color.';">';
for($i = 1; $i <= $bot_infor_setting->col_number; $i++){
    $tblbottom .= '<td>';
    foreach($bottom_data as $bot){
        if(isset($bot_infor_setting->$bot->active) && $bot_infor_setting->$bot->order_column == $i){ 
            if( $bot == 'payment_method'){
                $label = isset($updated_data->bottom_information->payment_method->label_content) ? $updated_data->bottom_information->payment_method->label_content : $bot_infor_setting->$bot->label_content;
                $tblbottom .=  '<p class=" mleft10" style="font-size:'.$bot_infor_setting->$bot->font_size.'px; font-style:'.$bot_infor_setting->$bot->font_style.'; color:'.$bot_infor_setting->$bot->text_color.'; text-align:'.$bot_infor_setting->$bot->text_align.';"> '.$label.'</p>';
            }else if($bot == 'bank_infor'){
                $label = isset($updated_data->bottom_information->bank_infor->label_content) ? $updated_data->bottom_information->bank_infor->label_content : $bot_infor_setting->$bot->label_content;
                $tblbottom .=  '<p class=" mleft10" style="font-size:'.$bot_infor_setting->$bot->font_size.'px; font-style:'.$bot_infor_setting->$bot->font_style.'; color:'.$bot_infor_setting->$bot->text_color.'; text-align:'.$bot_infor_setting->$bot->text_align.';"> '.$label.'</p>';
            }else if($bot == 'signature'){
                $label = isset($updated_data->bottom_information->signature->label_content) ? $updated_data->bottom_information->signature->label_content : $bot_infor_setting->$bot->label_content;
                $tblbottom .=  '<p class=" mleft10" > '.$label.'</p>';
                $tblbottom .= '<div class="build-qrcode mtop10 mbot10" style="width: '.$bot_infor_setting->$bot->width.'px; height:'.$bot_infor_setting->$bot->height.'px; ">';

                if(get_invoice_signature_link($invoice->id) != ''){
                    $tblbottom .= '<img src="'.get_invoice_signature_link($invoice->id).'" class="img-responsive" style="width: '.$bot_infor_setting->$bot->width.'px; height:'.$bot_infor_setting->$bot->height.'px;" alt>';
                } 
                $tblbottom .= '</div>';
            }else if($bot == 'qrcode'){
                $justify_content = (isset($bot_infor_setting->$bot->text_align) ? 'vertical-align: middle; text-align:'.$bot_infor_setting->$bot->text_align.';' : 'vertical-align: middle; text-align:left');
                $label = isset($updated_data->bottom_information->qrcode->label_content) ? $updated_data->bottom_information->qrcode->label_content : $bot_infor_setting->$bot->label_content;
                $tblbottom .= '<span class=" " >'.$label.'</span><br>';
                $tblbottom .= '<div class="build-qrcode " style="width: '.$bot_infor_setting->$bot->width.'px; height:'.$bot_infor_setting->$bot->height.'px; '.$justify_content.'">
                   <img src="'.get_invoice_qrcode_link($invoice->id, $built_invoice_id,'pdf').'" class="img-responsive" style="width: '.$bot_infor_setting->$bot->width.'px; height:'.$bot_infor_setting->$bot->height.'px; " alt>
                </div>';
            }else if($bot == 'messages'){
                $content = isset($updated_data->bottom_information->messages->content) ? $updated_data->bottom_information->messages->content : $bot_infor_setting->$bot->content;
                $tblbottom .=  '<div class="mleft10" style="font-size:'.$bot_infor_setting->$bot->font_size.'px; font-style:'.$bot_infor_setting->$bot->font_style.'; color:'.$bot_infor_setting->$bot->text_color.'; text-align:'.$bot_infor_setting->$bot->text_align.';"> '.$content.'</div>';
            }else if($bot == 'delivery_note'){
                $content = isset($updated_data->bottom_information->delivery_note->content) ? $updated_data->bottom_information->delivery_note->content : $bot_infor_setting->$bot->content;
                $tblbottom .=  '<p class=" mleft10" style="font-size:'.$bot_infor_setting->$bot->font_size.'px; font-style:'.$bot_infor_setting->$bot->font_style.'; color:'.$bot_infor_setting->$bot->text_color.'; text-align:'.$bot_infor_setting->$bot->text_align.';"> '.$content.'</p>';
            }else if($bot == 'client_note'){
                $content = isset($updated_data->bottom_information->client_note->content) ? $updated_data->bottom_information->client_note->content : $bot_infor_setting->$bot->content;
                $tblbottom .=  '<p class=" mleft10" style="font-size:'.$bot_infor_setting->$bot->font_size.'px; font-style:'.$bot_infor_setting->$bot->font_style.'; color:'.$bot_infor_setting->$bot->text_color.'; text-align:'.$bot_infor_setting->$bot->text_align.';"> '.$content.'</p>';
            }
        }
    }

    if( isset($bot_infor_setting->custom_field) ){
        $bot_infor_field = $bot_infor_setting->custom_field;
        foreach($bot_infor_field as $field_id => $field){
            $cf = get_custom_field_by_id($field_id);
            if($cf != '' && $field->order_column == $i && isset($field->active)){
                if($field->value != ''){
                    $value = isset($updated_data->bottom_information->custom_field->$field_id->value) ? $updated_data->bottom_information->custom_field->$field_id->value : $field->value;
                    $tblbottom .= '<p class="mbot5 mleft10" style="font-size:'.$field->font_size.'px; font-style:'.$field->font_style.'; color:'.$field->text_color.' text-align:'.$field->text_align.'; ">'.$value.'</p>';
                }
            }
        }
    }


    $tblbottom .= '</td>';
}

$tblbottom .= '</tr>';
$tblbottom .= '</tbody>';
$tblbottom .= '</table>';

$pdf->writeHTML($tblbottom, false, false, false, false, '');

$footer_setting = $template_setting->footer;
$footer_data = [
    'term_condition',
];
$regularFootWidth  = ib_get_regular_table_width($footer_setting->col_number);

$tblfooter = '<table cellpadding="10">';
$tblfooter .= '<tbody>';
$tblfooter .= '<tr style="background-color:'.$footer_setting->row_color.';">';
for($i = 1; $i <= $footer_setting->col_number; $i++){ 
    $tblfooter .= '<td>';
    foreach($footer_data as $foot){
        if(isset($footer_setting->$foot->active) && $footer_setting->$foot->order_column == $i){
            if( $foot == 'term_condition'){
                $content = isset($updated_data->footer->term_condition->content) ? $updated_data->footer->term_condition->content : $footer_setting->$foot->content;
                $tblfooter .= '<p class="mbot5 mleft10" style="font-size:'.$footer_setting->$foot->font_size.'px; font-style:'.$footer_setting->$foot->font_style.'; color:'.$footer_setting->$foot->text_color.'; text-align:'.$footer_setting->$foot->text_align.'"> '.$content.'</p>';
            }
        }
    }

    if( isset($footer_setting->custom_field) ){
        $footer_field = $footer_setting->custom_field;
        foreach($footer_field as $field_id => $field){
            $cf = get_custom_field_by_id($field_id);
            if($cf != '' && $field->order_column == $i && isset($field->active)){
                if($field->value != ''){
                    $value = isset($updated_data->footer->custom_field->$field_id->value) ? $updated_data->footer->custom_field->$field_id->value : $field->value;
                    $tblfooter .= '<p class="mbot5 mleft10" style="font-size:'.$field->font_size.'px; font-style:'.$field->font_style.'; color:'.$field->text_color.'; text-align:'.$field->text_align.';">'.$value.'</p>';
                }
            }
        }
    }

    $tblfooter .= '</td>';
}
$tblfooter .= '</tr>';
$tblfooter .= '</tbody>';
$tblfooter .= '</table>';
$pdf->writeHTML($tblfooter, false, false, false, false, '');