<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php hooks()->do_action('ib_load_component'); ?>
<?php 
   if($built_invoice->updated_data != '' && $built_invoice->updated_data != null){
      $updated_data = json_decode($built_invoice->updated_data);
   }
?>
<?php $template_setting = json_decode($template->setting);  ?>
<div class="clearfix"></div>
<div class="panel_s reset-panel">
   <div class="panel-body preview-form reset-panel mtop20">
      <div class="top mbot15" data-sticky data-sticky-class="preview-sticky-header">
      <div class="container preview-sticky-container">
         <div class="row">
            <div class="<?php if($template_setting->page_rotation == 'portrait'){ echo 'col-md-8 col-md-offset-2';}else{ echo 'col-md-12'; } ?>">

               <div class="visible-xs">
                  <div class="clearfix"></div>
               </div>
               <a href="#" class="btn btn-success pull-right mleft5 mtop5 action-button invoice-html-pay-now-top hide sticky-hidden
                  <?php if (($invoice->status != Invoices_model::STATUS_PAID && $invoice->status != Invoices_model::STATUS_CANCELLED
                     && $invoice->total > 0) && found_invoice_mode($payment_modes, $invoice->id, false)) {
                     echo ' pay-now-top';
                  } ?>">
                  <?php echo _l('invoice_html_online_payment_button_text'); ?>
               </a>
               <?php echo form_open($this->uri->uri_string()); ?>
               <button type="submit" name="invoicepdf" value="invoicepdf" class="btn btn-default pull-right action-button mtop5 mbot5">
                  <i class='fa fa-file-pdf-o'></i>
                  <?php echo _l('clients_invoice_html_btn_download'); ?>
               </button>
               <?php echo form_close(); ?>
               <?php if (is_client_logged_in() && has_contact_permission('invoices')) { ?>
                  <a href="<?php echo site_url('invoices_builder/client_invoice'); ?>" class="btn btn-default pull-right mtop5 mright5 action-button go-to-portal">
                     <?php echo _l('client_go_to_dashboard'); ?>
                  </a>
               <?php } ?>
               <div class="clearfix"></div>
            </div>
         </div>
      </div>
   </div>

   <div class="clearfix"></div>

      <div class="page <?php if($template_setting->page_rotation == 'portrait'){ echo 'col-md-8 col-md-offset-2';}else{ echo 'col-md-12'; } ?>" style="background-color: <?php echo ib_html_entity_decode($template_setting->background_color); ?>">
         <!-- Header section -->
         <?php $header_setting = $template_setting->header; ?>
         <div class="flex-box" style="background-color: <?php echo ib_html_entity_decode($header_setting->row_color); ?>">
            <?php $header_data = [
               'logo',
               'qrcode',
               'title',
               'invoice_infor',
            ]; ?>
            
            <?php 
            $col_md = get_col_md_by_col_number_setting($header_setting->col_number);
            for($i = 1; $i <= $header_setting->col_number; $i++){ ?>
               <div class="flex4">
                  <?php foreach($header_data as $hd){ ?>
                     <?php if(isset($header_setting->$hd) && $header_setting->$hd->show_on_column == $i){ ?>
                        <?php if($hd == 'logo' && isset($header_setting->$hd->active)){ 
                           $justify_content = (isset($header_setting->$hd->text_align) ? 'justify-content:'.$header_setting->$hd->text_align.';' : 'justify-content:left');
                           $company_logo = get_option('company_logo' . (get_option('company_logo_dark') != '' ? '_dark' : ''));
                           ?>
                           <div class="d-flex" style="<?php echo ib_html_entity_decode($justify_content); ?>"><img style="width: <?php echo ib_html_entity_decode($header_setting->$hd->width).'px'; ?>;" src="<?php  echo base_url('uploads/company/' . $company_logo);  ?>" class="img-responsive" alt>
                           </div>
                        <?php }else if($hd == 'qrcode' && isset($header_setting->$hd->active)){ 
                              $justify_content = (isset($header_setting->$hd->text_align) ? 'justify-content:'.$header_setting->$hd->text_align.';' : 'justify-content:left');
                           ?>
                           <div class="d-flex" style="<?php echo ib_html_entity_decode($justify_content); ?>"><img style="width: <?php echo ib_html_entity_decode($header_setting->$hd->width).'px'; ?>;  height: <?php echo ib_html_entity_decode($header_setting->$hd->height).'px'; ?>; ?>" src="<?php echo get_invoice_qrcode_link($invoice->id, $built_invoice_id); ?>" class="img-responsive" alt></div>                         
                        <?php }else if($hd == 'title' && isset($header_setting->$hd->active)){ ?>
                           <div class="mbot5 d-grid" style="text-align: <?php echo ib_html_entity_decode($header_setting->$hd->text_align); ?>;">
                              <?php $title = isset($updated_data->header->title) ? $updated_data->header->title : format_invoice_number($invoice->id); ?>
                              <span style="font-size: <?php echo ib_html_entity_decode($header_setting->$hd->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($header_setting->$hd->font_style); ?>; color: <?php echo ib_html_entity_decode($header_setting->$hd->text_color); ?>;"><?php echo ib_html_entity_decode($title); ?></span>
                           </div>
                        <?php }else if($hd == 'invoice_infor' && isset($header_setting->$hd->active)){ ?>
                           <div class="mbot5 d-grid control-element invoice_infor" >
                              <?php if(isset($header_setting->$hd->invoice_number)){ ?>
                                 <?php $invoice_number = isset($updated_data->header->invoice_infor->invoice_number) ? $updated_data->header->invoice_infor->invoice_number : format_invoice_number($invoice->id); ?> 
                                 <p class="mbot5" style="font-size: <?php echo ib_html_entity_decode($header_setting->$hd->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($header_setting->$hd->font_style); ?>; color: <?php echo ib_html_entity_decode($header_setting->$hd->text_color); ?>; text-align: <?php echo ib_html_entity_decode($header_setting->$hd->text_align); ?>;"> <?php echo _l('ib_invoice_number').': '.$invoice_number; ?></p>
                              <?php } ?>
                              <?php if(isset($header_setting->$hd->invoice_date)){ ?>
                                 <?php $invoice_date = isset($updated_data->header->invoice_infor->invoice_date) ? $updated_data->header->invoice_infor->invoice_date : _d($invoice->date); ?> 
                                 <p class="mbot5" style="font-size: <?php echo ib_html_entity_decode($header_setting->$hd->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($header_setting->$hd->font_style); ?>; color: <?php echo ib_html_entity_decode($header_setting->$hd->text_color); ?>; text-align: <?php echo ib_html_entity_decode($header_setting->$hd->text_align); ?>;"> <?php echo _l('ib_invoice_date').': '.$invoice_date; ?></p>
                              <?php } ?>
                              <?php if(isset($header_setting->$hd->invoice_due_date)){ ?>
                                 <?php $invoice_due_date = isset($updated_data->header->invoice_infor->invoice_due_date) ? $updated_data->header->invoice_infor->invoice_due_date : _d($invoice->duedate); ?>
                                 <p class="mbot5" style="font-size: <?php echo ib_html_entity_decode($header_setting->$hd->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($header_setting->$hd->font_style); ?>; color: <?php echo ib_html_entity_decode($header_setting->$hd->text_color); ?>; text-align: <?php echo ib_html_entity_decode($header_setting->$hd->text_align); ?>;"> <?php echo _l('ib_invoice_due_date').': '.$invoice_due_date; ?></p>
                              <?php } ?>
                              <?php if(isset($header_setting->$hd->invoice_amount)){ ?>
                                 <?php $invoice_amount = isset($updated_data->header->invoice_infor->invoice_amount) ? $updated_data->header->invoice_infor->invoice_amount : $invoice->total; ?>
                                 <p class="mbot5" style="font-size: <?php echo ib_html_entity_decode($header_setting->$hd->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($header_setting->$hd->font_style); ?>; color: <?php echo ib_html_entity_decode($header_setting->$hd->text_color); ?>; text-align: <?php echo ib_html_entity_decode($header_setting->$hd->text_align); ?>;"> <?php echo _l('ib_invoice_amount').': '.app_format_money($invoice_amount, $invoice->currency_name); ?></p>
                              <?php } ?>
                              <?php if(isset($header_setting->$hd->invoice_status)){ ?>
                                 <p class="mbot5" style="font-size: <?php echo ib_html_entity_decode($header_setting->$hd->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($header_setting->$hd->font_style); ?>; color: <?php echo ib_html_entity_decode($header_setting->$hd->text_color); ?>; text-align: <?php echo ib_html_entity_decode($header_setting->$hd->text_align); ?>;"> <?php echo _l('ib_invoice_status').': '; ?><span class="text-<?php echo get_invoice_status_label($invoice->status); ?> text-uppercase"><?php echo format_invoice_status($invoice->status, '', false); ?><span></p>
                              <?php } ?>
                              <?php if(isset($header_setting->$hd->custom_field)){ ?>
                                 <?php foreach($header_setting->$hd->custom_field as $field_id => $field){ 
                                    $cf = get_custom_field_by_id($field_id);
                                    if($cf != '' && isset($field->active)){
                                       $value = isset($updated_data->header->invoice_infor->custom_field->$field_id->value) ? $updated_data->header->invoice_infor->custom_field->$field_id->value : $field->value;
                                    ?>
                                       <p class="mbot5" style="font-size: <?php echo ib_html_entity_decode($header_setting->$hd->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($header_setting->$hd->font_style); ?>; color: <?php echo ib_html_entity_decode($header_setting->$hd->text_color); ?>; text-align: <?php echo ib_html_entity_decode($header_setting->$hd->text_align); ?>;"> <?php echo ib_html_entity_decode($value); ?></p>
                                    <?php } ?>
                                 <?php } ?>
                              <?php } ?>
                           </div>
                        <?php } ?>
                     <?php } ?>
                  <?php } ?>
               </div>
            <?php } ?>
         </div>
         <!-- sender receiver section -->
         <?php $sender_receiver_setting = $template_setting->sender_receiver; ?>
         <div class="flex-box" style="background-color: <?php echo ib_html_entity_decode($sender_receiver_setting->row_color); ?>">
            <?php  $sender_receiver_data = [
               'sender_infor',
               'receiver_infor'
              ]; ?>

            <?php 
            for($i = 1; $i <= $sender_receiver_setting->col_number; $i++){ ?>
               <div class="flex4 d-grid">
                  <?php foreach($sender_receiver_data as $sr){ ?>
                     <?php if(isset($sender_receiver_setting->$sr) && $sender_receiver_setting->$sr->show_on_column == $i){ ?>
                        <?php if($sr == 'sender_infor' && isset($sender_receiver_setting->$sr->active)){ ?>
                           <div class="sender_infor" style="background-color: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->column_color); ?>">
                               <br>
                              <p class="mbot5 mleft10 bold fs20"><?php echo _l('ib_seller'); ?></p>
                              <?php if( isset($sender_receiver_setting->$sr->name->active) ){ ?>
                                 <?php $sender_name = isset($updated_data->sender_receiver->sender_infor->name) ? $updated_data->sender_receiver->sender_infor->name : get_option('invoice_company_name'); ?>
                                 <p class="mbot5 mleft10" style="font-size: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->name->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->name->font_style); ?>; color: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->name->text_color); ?>;"> <?php echo _l('ib_name').': '.$sender_name; ?></p>
                              <?php } ?>
                              <?php if( isset($sender_receiver_setting->$sr->phone->active) ){ ?>
                                 <?php $sender_phone = isset($updated_data->sender_receiver->sender_infor->phone) ? $updated_data->sender_receiver->sender_infor->phone : get_option('invoice_company_phonenumber'); ?>
                                 <p class="mbot5 mleft10" style="font-size: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->phone->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->phone->font_style); ?>; color: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->phone->text_color); ?>;"> <?php echo _l('ib_phonenumber').': '.$sender_phone; ?></p>
                              <?php } ?>
                              <?php if( isset($sender_receiver_setting->$sr->email->active) ){ ?>
                                 <?php $sender_email = isset($updated_data->sender_receiver->sender_infor->email) ? $updated_data->sender_receiver->sender_infor->email : get_option('smtp_email'); ?>
                                 <p class="mbot5 mleft10" style="font-size: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->email->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->email->font_style); ?>; color: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->email->text_color); ?>;"> <?php echo _l('ib_email').': '.$sender_email; ?></p>
                              <?php } ?>
                              <?php if( isset($sender_receiver_setting->$sr->website->active) ){ ?>
                                 <?php $sender_web = isset($updated_data->sender_receiver->sender_infor->website) ? $updated_data->sender_receiver->sender_infor->website : site_url(); ?>
                                 <p class="mbot5 mleft10" style="font-size: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->website->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->website->font_style); ?>; color: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->website->text_color); ?>;"> <?php echo _l('ib_website').': '.$sender_web; ?></p>
                              <?php } ?>
                              <?php if( isset($sender_receiver_setting->$sr->street->active) ){ ?>
                                 <?php $sender_street = isset($updated_data->sender_receiver->sender_infor->street) ? $updated_data->sender_receiver->sender_infor->street : get_option('invoice_company_address'); ?>
                                 <p class="mbot5 mleft10" style="font-size: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->street->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->street->font_style); ?>; color: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->street->text_color); ?>;"> <?php echo _l('ib_street').': '.$sender_street; ?></p>
                              <?php } ?>
                              <?php if( isset($sender_receiver_setting->$sr->city->active) ){ ?>
                                 <?php $sender_city = isset($updated_data->sender_receiver->sender_infor->city) ? $updated_data->sender_receiver->sender_infor->city : get_option('invoice_company_city'); ?>
                                 <p class="mbot5 mleft10" style="font-size: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->city->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->city->font_style).'px'; ?>; color: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->city->text_color); ?>;"> <?php echo _l('ib_city').': '.$sender_city; ?></p>
                              <?php } ?>
                              <?php if( isset($sender_receiver_setting->$sr->state->active) ){ ?>
                                 <?php $sender_state = isset($updated_data->sender_receiver->sender_infor->state) ? $updated_data->sender_receiver->sender_infor->state : get_option('company_state'); ?>
                                 <p class="mbot5 mleft10" style="font-size: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->state->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->state->font_style); ?>; color: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->state->text_color); ?>;"> <?php echo _l('ib_state').': '.$sender_state; ?></p>
                              <?php } ?>
                              <?php if( isset($sender_receiver_setting->$sr->zipcode->active) ){ ?>
                                 <?php $sender_zipcode = isset($updated_data->sender_receiver->sender_infor->zipcode) ? $updated_data->sender_receiver->sender_infor->zipcode : get_option('invoice_company_postal_code'); ?>
                                 <p class="mbot5 mleft10" style="font-size: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->zipcode->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->zipcode->font_style); ?>; color: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->zipcode->text_color); ?>;"> <?php echo _l('ib_zipcode').': '.$sender_zipcode; ?></p>
                              <?php } ?>
                              <?php if( isset($sender_receiver_setting->$sr->country->active) ){ ?>
                                 <?php $sender_country = isset($updated_data->sender_receiver->sender_infor->country) ? $updated_data->sender_receiver->sender_infor->country : get_option('invoice_company_country_code'); ?>
                                 <p class="mbot5 mleft10" style="font-size: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->country->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->country->font_style); ?>; color: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->country->text_color); ?>;"> <?php echo _l('ib_country').': '.$sender_country; ?></p>
                              <?php } ?>
                              <?php if( isset($sender_receiver_setting->$sr->vat->active) ){ ?>
                                 <?php $sender_vat = isset($updated_data->sender_receiver->sender_infor->vat) ? $updated_data->sender_receiver->sender_infor->vat : get_option('company_vat'); ?>
                                 <p class="mbot5 mleft10" style="font-size: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->vat->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->vat->font_style).'px'; ?>; color: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->vat->text_color); ?>;"> <?php echo _l('ib_vat_number').': '.$sender_vat; ?></p>
                              <?php } ?>
                              <?php if( isset($sender_receiver_setting->$sr->sales_person->active) ){ ?>
                                 <?php  $sales_person = isset($updated_data->sender_receiver->sender_infor->sales_person) ? $updated_data->sender_receiver->sender_infor->sales_person : get_staff_full_name($invoice->sale_agent); ?>
                                 <p class="mbot5 mleft10" style="font-size: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->sales_person->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->sales_person->font_style); ?>; color: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->sales_person->text_color); ?>;"> <?php echo _l('ib_sales_person').': '. $sales_person; ?></p>
                              <?php } ?>
                              <?php if( isset($sender_receiver_setting->$sr->custom_field) ){ ?>
                                 <?php $sender_custom_field = $sender_receiver_setting->$sr->custom_field; ?>
                                 <?php foreach($sender_custom_field as $field_id => $field){ ?>
                                    <?php $cf = get_custom_field_by_id($field_id); ?>
                                    <?php if($cf != '' && isset($field->active)){ ?>
                                       <?php $value = isset($updated_data->sender_receiver->sender_infor->custom_field->$field_id->value) ? $updated_data->sender_receiver->sender_infor->custom_field->$field_id->value : $field->value; ?>

                                       <p class="mbot5 mleft10" style="font-size: <?php echo ib_html_entity_decode($field->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($field->font_style); ?>; color: <?php echo ib_html_entity_decode($field->text_color); ?>;"> <?php echo ib_html_entity_decode($value); ?></p>
                                    <?php  } ?>
                                 <?php } ?>
                              <?php } ?>

                           </div>
                        <?php }else if($sr == 'receiver_infor' && isset($sender_receiver_setting->$sr->active)){ ?>
                           <?php 
                              $this->load->model('clients_model'); 
                              $_client = $this->clients_model->get($invoice->clientid);
                              $_client_email = ib_get_primary_contact_email($invoice->clientid);
                           ?>
                           <div class="receiver_infor" style="background-color: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->column_color); ?>">
                              <br>
                              <p class="mbot5 mleft10 bold" style="font-size: 20px;"><?php echo _l('ib_buyer'); ?></p>
                              <?php if( isset($sender_receiver_setting->$sr->name->active) ){ ?>
                                 <?php $receiver_name = isset($updated_data->sender_receiver->receiver_infor->name) ? $updated_data->sender_receiver->receiver_infor->name : get_company_name($invoice->clientid); ?>
                                 <p class="mbot5 mleft10" style="font-size: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->name->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->name->font_style); ?>; color: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->name->text_color); ?>;"> <?php echo _l('ib_name').': '.$receiver_name; ?></p>
                              <?php } ?>
                              <?php if( isset($sender_receiver_setting->$sr->phone->active) ){ ?>
                                 <?php $receiver_phone = isset($updated_data->sender_receiver->receiver_infor->phone) ? $updated_data->sender_receiver->receiver_infor->phone : $_client->phonenumber; ?>
                                 <p class="mbot5 mleft10" style="font-size: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->phone->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->phone->font_style); ?>; color: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->phone->text_color); ?>;"> <?php echo _l('ib_phonenumber').': '.$receiver_phone; ?></p>
                              <?php } ?>
                              <?php if( isset($sender_receiver_setting->$sr->email->active) ){ ?>
                                 <?php $receiver_email = isset($updated_data->sender_receiver->receiver_infor->email) ? $updated_data->sender_receiver->receiver_infor->email : $_client_email; ?>
                                 <p class="mbot5 mleft10" style="font-size: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->email->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->email->font_style); ?>; color: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->email->text_color); ?>;"> <?php echo _l('ib_email').': '.$receiver_email; ?></p>
                              <?php } ?>
                              <?php if( isset($sender_receiver_setting->$sr->website->active) ){ ?>
                                 <?php $receiver_website = isset($updated_data->sender_receiver->receiver_infor->website) ? $updated_data->sender_receiver->receiver_infor->website : $_client->website; ?>
                                 <p class="mbot5 mleft10" style="font-size: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->website->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->website->font_style); ?>; color: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->website->text_color); ?>;"> <?php echo _l('ib_website').': '.$receiver_website; ?></p>
                              <?php } ?>
                              <?php if( isset($sender_receiver_setting->$sr->street->active) ){ ?>
                                 <?php $receiver_street = isset($updated_data->sender_receiver->receiver_infor->street) ? $updated_data->sender_receiver->receiver_infor->street : $_client->address; ?>
                                 <p class="mbot5 mleft10" style="font-size: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->street->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->street->font_style); ?>; color: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->street->text_color); ?>;"> <?php echo _l('ib_street').': '.$receiver_street; ?></p>
                              <?php } ?>
                              <?php if( isset($sender_receiver_setting->$sr->city->active) ){ ?>
                                 <?php $receiver_city = isset($updated_data->sender_receiver->receiver_infor->city) ? $updated_data->sender_receiver->receiver_infor->city : $_client->city; ?>
                                 <p class="mbot5 mleft10" style="font-size: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->city->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->city->font_style).'px'; ?>; color: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->city->text_color); ?>;"> <?php echo _l('ib_city').': '.$receiver_city; ?></p>
                              <?php } ?>
                              <?php if( isset($sender_receiver_setting->$sr->state->active) ){ ?>
                                 <?php $receiver_state = isset($updated_data->sender_receiver->receiver_infor->state) ? $updated_data->sender_receiver->receiver_infor->state : $_client->state; ?>
                                 <p class="mbot5 mleft10" style="font-size: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->state->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->state->font_style); ?>; color: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->state->text_color); ?>;"> <?php echo _l('ib_state').': '.$receiver_state; ?></p>
                              <?php } ?>
                              <?php if( isset($sender_receiver_setting->$sr->zipcode->active) ){ ?>
                                 <?php $receiver_zipcode = isset($updated_data->sender_receiver->receiver_infor->zipcode) ? $updated_data->sender_receiver->receiver_infor->zipcode : $_client->zip; ?>
                                 <p class="mbot5 mleft10" style="font-size: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->zipcode->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->zipcode->font_style); ?>; color: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->zipcode->text_color); ?>;"> <?php echo _l('ib_zipcode').': '.$receiver_zipcode; ?></p>
                              <?php } ?>
                              <?php if( isset($sender_receiver_setting->$sr->country->active) ){ ?>
                                 <?php $receiver_country = isset($updated_data->sender_receiver->receiver_infor->country) ? $updated_data->sender_receiver->receiver_infor->country : get_country_name($_client->country); ?>
                                 <p class="mbot5 mleft10" style="font-size: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->country->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->country->font_style); ?>; color: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->country->text_color); ?>;"> <?php echo _l('ib_country').': '.$receiver_country; ?></p>
                              <?php } ?>
                              <?php if( isset($sender_receiver_setting->$sr->vat->active) ){ ?>
                                 <?php $receiver_vat = isset($updated_data->sender_receiver->receiver_infor->vat) ? $updated_data->sender_receiver->receiver_infor->vat : $_client->vat; ?>
                                 <p class="mbot5 mleft10" style="font-size: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->vat->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->vat->font_style).'px'; ?>; color: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->vat->text_color); ?>;"> <?php echo _l('ib_vat_number').': '.$receiver_vat; ?></p>
                              <?php } ?>
                              <?php if( isset($sender_receiver_setting->$sr->billing_to->active) ){ ?>
                                 <?php $billing_to = isset($updated_data->sender_receiver->receiver_infor->billing_to) ? $updated_data->sender_receiver->receiver_infor->billing_to : strip_tags(format_customer_info($invoice, 'invoice', 'billing')); ?>
                                 <p class="mbot5 mleft10" style="font-size: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->billing_to->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->billing_to->font_style); ?>; color: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->billing_to->text_color); ?>;"> <?php echo _l('ib_billing_to').': '.$billing_to; ?></p>
                              <?php } ?>
                              <?php if( isset($sender_receiver_setting->$sr->shipping_to->active) ){ ?>
                                 <?php $shipping_to = isset($updated_data->sender_receiver->receiver_infor->shipping_to) ? $updated_data->sender_receiver->receiver_infor->shipping_to : strip_tags(format_customer_info($invoice, 'invoice', 'shipping')); ?>
                                 <p class="mbot5 mleft10" style="font-size: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->shipping_to->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->shipping_to->font_style); ?>; color: <?php echo ib_html_entity_decode($sender_receiver_setting->$sr->shipping_to->text_color); ?>;"> <?php echo _l('ib_shipping_to').': '.$shipping_to; ?></p>
                              <?php } ?>

                              <?php if( isset($sender_receiver_setting->$sr->custom_field) ){ ?>
                                 <?php $receiver_custom_field = $sender_receiver_setting->$sr->custom_field; ?>
                                 <?php foreach($receiver_custom_field as $field_id => $field){ ?>
                                    <?php $cf = get_custom_field_by_id($field_id); ?>
                                    <?php if($cf != '' && isset($field->active)){ ?>
                                       <?php $value = isset($updated_data->sender_receiver->receiver_infor->custom_field->$field_id->value) ? $updated_data->sender_receiver->receiver_infor->custom_field->$field_id->value : $field->value; ?>
                                       <p class="mbot5 mleft10" style="font-size: <?php echo ib_html_entity_decode($field->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($field->font_style); ?>; color: <?php echo ib_html_entity_decode($field->text_color); ?>;"> <?php echo ib_html_entity_decode($value); ?></p>
                                    <?php  } ?>
                                 <?php } ?>
                              <?php } ?>

                                 <br>
                           </div>
                        <?php } ?>
                     <?php } ?>
                  <?php } ?>
               </div>
            <?php } ?>
         </div>

         <!-- Item table section -->
         <div class="item_table_panel">
           
               <div class="table-responsive">
                  <?php $item_table_setting = $template_setting->item_table;
                  
                  $header_style = '';
                  $header_style .= (isset($item_table_setting->text_align) ? 'text-align:'.$item_table_setting->text_align.';' : '');
                  $header_style .= (isset($item_table_setting->font_style) ? 'font-style:'.$item_table_setting->font_style.';' : '');
                  $header_style .= (isset($item_table_setting->font_size) ? 'font-size:'.$item_table_setting->font_size.'px;' : '');
                  $header_style .= (isset($item_table_setting->text_color) ? 'color:'.$item_table_setting->text_color.';' : '');
                   ?>

                  <?php $table_columns = [
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
                  ]; ?>

                  <?php 
                  $num_col = count($table_columns);
                  if(isset($item_table_setting->custom_field)){ 
                     $num_col = $num_col + count((array) $item_table_setting->custom_field);
                     ?>
                     
                  <?php } ?>

                  <table class="table ">
                     <thead style="background: <?php echo ib_html_entity_decode($item_table_setting->thead_color); ?>">
                        <tr>
                           <?php for($i = 1; $i <= $num_col; $i++){ ?>
                              <?php foreach($table_columns as $col){ ?>
                                 <?php if(isset($item_table_setting->$col->active) && $item_table_setting->$col->order_column == $i){ ?>

                                    <th class="text-nowrap" style="<?php echo ib_html_entity_decode($header_style); ?>"><?php echo _l($col); ?></th>

                                 <?php } ?>
                              <?php } ?>
                               <!-- Customfield thead -->
                               <?php if(isset($item_table_setting->custom_field)){ ?>
                                 <?php foreach($item_table_setting->custom_field as $index => $_field){ ?>
                                    <?php $cf = get_custom_field_by_id($index); ?>
                                    <?php if($_field->order_column == $i && $cf != '' && isset($_field->active)){ ?>
                                       <th class=" text-nowrap" style="<?php echo ib_html_entity_decode($header_style); ?>"><?php echo ib_html_entity_decode($cf->label); ?></th>
                                    <?php } ?>
                                 <?php } ?>
                              <?php } ?>
                           <?php } ?>

                        </tr>
                     </thead>
                     <tbody>
                        <?php foreach($invoice->items as $it_key => $inv_item){ ?>
                           <?php
                              $row_color = '';
                              if(isset($item_table_setting->striped_row->active)){
                                 
                                 if( (($it_key + 1) % 2) == 0){
                                    $row_color = $item_table_setting->striped_row->even_row_color;
                                 }else{
                                    $row_color = $item_table_setting->striped_row->odd_row_color;
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

                            ?>
                           <tr <?php if(isset($item_table_setting->striped_row)){ echo 'style="background: '.$row_color.'; "'; } ?> >
                              <?php for($i = 1; $i <= $num_col; $i++){ ?>
                                 <?php foreach($table_columns as $col){ ?>
                                    <?php if(isset($item_table_setting->$col->active) && $item_table_setting->$col->order_column == $i){ ?>
                                       <?php if($col == 'item_order'){ ?>
                                          <td class="text-nowrap" style="text-align: <?php echo ib_html_entity_decode($item_table_setting->$col->text_align); ?>;"><span style="font-size: <?php echo ib_html_entity_decode($item_table_setting->$col->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($item_table_setting->$col->font_style); ?>; color: <?php echo ib_html_entity_decode($item_table_setting->$col->text_color); ?>;"><?php echo ib_html_entity_decode($it_key + 1); ?></span></td>
                                       <?php } ?>
                                       <?php if($col == 'item_name'){ ?>
                                          <td class="text-nowrap" style="text-align: <?php echo ib_html_entity_decode($item_table_setting->$col->text_align); ?>;"><span style="font-size: <?php echo ib_html_entity_decode($item_table_setting->$col->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($item_table_setting->$col->font_style); ?>; color: <?php echo ib_html_entity_decode($item_table_setting->$col->text_color); ?>;"><?php echo ib_html_entity_decode($inv_item['description']); ?></span></td>
                                       <?php } ?>
                                       <?php if($col == 'description'){ ?>
                                          <td class="text-nowrap" style="text-align: <?php echo ib_html_entity_decode($item_table_setting->$col->text_align); ?>;"><span style="font-size: <?php echo ib_html_entity_decode($item_table_setting->$col->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($item_table_setting->$col->font_style); ?>; color: <?php echo ib_html_entity_decode($item_table_setting->$col->text_color); ?>;"><?php echo ib_html_entity_decode($inv_item['long_description']); ?></span></td>
                                       <?php } ?>
                                       <?php if($col == 'quantity'){ ?>
                                          <td class="text-nowrap" style="text-align: <?php echo ib_html_entity_decode($item_table_setting->$col->text_align); ?>;"><span style="font-size: <?php echo ib_html_entity_decode($item_table_setting->$col->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($item_table_setting->$col->font_style); ?>; color: <?php echo ib_html_entity_decode($item_table_setting->$col->text_color); ?>;"><?php echo ib_html_entity_decode($inv_item['qty']); ?></span></td>
                                       <?php } ?>
                                       <?php if($col == 'unit'){ ?>
                                          <td class="text-nowrap" style="text-align: <?php echo ib_html_entity_decode($item_table_setting->$col->text_align); ?>;"><span style="font-size: <?php echo ib_html_entity_decode($item_table_setting->$col->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($item_table_setting->$col->font_style); ?>; color: <?php echo ib_html_entity_decode($item_table_setting->$col->text_color); ?>;"><?php echo ib_html_entity_decode($inv_item['unit']); ?></span></td>
                                       <?php } ?>
                                       <?php if($col == 'unit_price'){ ?>
                                          <td class="text-nowrap" style="text-align: <?php echo ib_html_entity_decode($item_table_setting->$col->text_align); ?>;"><span style="font-size: <?php echo ib_html_entity_decode($item_table_setting->$col->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($item_table_setting->$col->font_style); ?>; color: <?php echo ib_html_entity_decode($item_table_setting->$col->text_color); ?>;"><?php echo app_format_money($inv_item['rate'], $invoice->currency_name); ?></span></td>
                                       <?php } ?>
                                       <?php if($col == 'tax_rate'){ ?>
                                          <?php 
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
                                          ?>
                                          <td class="text-nowrap" style="text-align: <?php echo ib_html_entity_decode($item_table_setting->$col->text_align); ?>;"><span style="font-size: <?php echo ib_html_entity_decode($item_table_setting->$col->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($item_table_setting->$col->font_style); ?>; color: <?php echo ib_html_entity_decode($item_table_setting->$col->text_color); ?>;"><?php echo ib_html_entity_decode($itemHTML); ?></span></td>
                                       <?php } ?>

                                       <?php if($col == 'tax_amount'){ ?>
                                          <?php 
                                             $item_taxes = get_invoice_item_taxes($inv_item['id']); 
                                             $tax_amount = 0;
                                             if (count($item_taxes) > 0) {
                                                 foreach ($item_taxes as $tax) {
                                                     $tax_amount += $inv_item['qty'] * $inv_item['rate'] * $tax['taxrate'] / 100;
                                                 }
                                             }
                                          ?>
                                          <td class="text-nowrap" style="text-align: <?php echo ib_html_entity_decode($item_table_setting->$col->text_align); ?>;"><span style="font-size: <?php echo ib_html_entity_decode($item_table_setting->$col->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($item_table_setting->$col->font_style); ?>; color: <?php echo ib_html_entity_decode($item_table_setting->$col->text_color); ?>;"><?php echo app_format_money($tax_amount, $invoice->currency_name); ?></span></td>
                                       <?php } ?>

                                       <?php if($col == 'subtotal'){ ?>
                                          <?php 
                                             $item_taxes = get_invoice_item_taxes($inv_item['id']); 
                                             $tax_amount = 0;
                                             if (count($item_taxes) > 0) {
                                                 foreach ($item_taxes as $tax) {
                                                     $tax_amount += $inv_item['qty'] * $inv_item['rate'] * $tax['taxrate'] / 100;
                                                 }
                                             }

                                             $subtotal =  $inv_item['qty'] * $inv_item['rate'] + $tax_amount;
                                          ?>

                                          <td class="text-nowrap" style="text-align: <?php echo ib_html_entity_decode($item_table_setting->$col->text_align); ?>;"><span style="font-size: <?php echo ib_html_entity_decode($item_table_setting->$col->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($item_table_setting->$col->font_style); ?>; color: <?php echo ib_html_entity_decode($item_table_setting->$col->text_color); ?>;"><?php echo app_format_money($subtotal, $invoice->currency_name); ?></span></td>

                                       <?php } ?>
                                       <?php $item = ib_get_item_by_name($inv_item['description']); ?>
                                       <?php if($col == 'sku'){ ?>
                                          <?php $sku = isset($item->sku_code) ? $item->sku_code : ''; ?>
                                          <?php 
                                             $_sku = $sku;
                                             if(isset($updated_data)){
                                                foreach($updated_data->item_table as $int_item_id => $it){
                                                   if($int_item_id == $inv_item['id']){
                                                      $_sku = $it->sku;
                                                   }
                                                } 
                                             } ?>
                                          <td class="text-nowrap" style="text-align: <?php echo ib_html_entity_decode($item_table_setting->$col->text_align); ?>;"><span style="font-size: <?php echo ib_html_entity_decode($item_table_setting->$col->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($item_table_setting->$col->font_style); ?>; color: <?php echo ib_html_entity_decode($item_table_setting->$col->text_color); ?>;"><?php echo ib_html_entity_decode($_sku); ?></span></td>
                                       <?php } ?>
                                       <?php if($col == 'barcode'){ ?>
                                          <?php $barcode = isset($item->commodity_barcode) ? $item->commodity_barcode : ''; ?>
                                          <?php 
                                             $_barcode = $barcode;
                                             if(isset($updated_data)){
                                                foreach($updated_data->item_table as $int_item_id => $it){
                                                   if($int_item_id == $inv_item['id']){
                                                      $_barcode = $it->barcode;
                                                   }
                                                }
                                             } ?>
                                          <td class="text-nowrap" style="text-align: <?php echo ib_html_entity_decode($item_table_setting->$col->text_align); ?>;"><span style="font-size: <?php echo ib_html_entity_decode($item_table_setting->$col->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($item_table_setting->$col->font_style); ?>; color: <?php echo ib_html_entity_decode($item_table_setting->$col->text_color); ?>;"><?php echo ib_html_entity_decode($_barcode); ?></span></td>
                                       <?php } ?>
                                       <?php if($col == 'image'){ ?>
                                          <?php $item_id = isset($item->id) ? $item->id : 0;
                                          $arr_images = ib_get_item_attachments($item_id);
                                             $style = 'width: '.$item_table_setting->$col->width.'px; height: '.$item_table_setting->$col->height.'px';
                                             if(count($arr_images) > 0){
                                                 if(file_exists('modules/purchase/uploads/item_img/' .$arr_images[0]['rel_id'] .'/'.$arr_images[0]['file_name'])){
                                                     $_data = '<img class="images_w_table" src="' . site_url('modules/purchase/uploads/item_img/' . $arr_images[0]['rel_id'] .'/'.$arr_images[0]['file_name']).'" alt="'.$arr_images[0]['file_name'] .'" style="'.$style.'" >';
                                                 }else if(file_exists('modules/warehouse/uploads/item_img/' . $arr_images[0]['rel_id'] . '/' . $arr_images[0]['file_name'])){
                                                     $_data = '<img class="images_w_table" src="' . site_url('modules/warehouse/uploads/item_img/' . $arr_images[0]['rel_id'] .'/'.$arr_images[0]['file_name']).'" alt="'.$arr_images[0]['file_name'] .'" style="'.$style.'">';
                                                 }else {
                                                     $_data = '<img class="images_w_table" src="' . site_url('modules/manufacturing/uploads/products/' . $arr_images[0]['rel_id'] . '/' . $arr_images[0]['file_name']) . '" alt="' . $arr_images[0]['file_name'] . '" style="'.$style.'">';
                                                 }

                                             }else{

                                                 $_data = '<img class="images_w_table" src="' . site_url('modules/purchase/uploads/nul_image.jpg' ).'" alt="nul_image.jpg" style="'.$style.'">';
                                             } ?>
                                          <td>
                                             <div class="build-logo" style="width: <?php echo ib_html_entity_decode($item_table_setting->$col->width).'px'; ?>;  height: <?php echo ib_html_entity_decode($item_table_setting->$col->height).'px'; ?>">
                                                <?php echo ib_html_entity_decode($_data); ?>
                                             </div>
                                          </td>
                                       <?php } ?>

                                    <?php } ?>
                                 <?php } ?>

                                 <!-- Customfield td -->
                                 <?php if(isset($item_table_setting->custom_field)){ ?>
                                    <?php foreach($item_table_setting->custom_field as $index => $_field){ ?>
                                       <?php $cf = get_custom_field_by_id($index); ?>
                                       <?php if($_field->order_column == $i && $cf != '' && isset($_field->active)){ ?>
                                          <td class="text-nowrap" style="text-align: <?php echo ib_html_entity_decode($_field->text_align); ?>;">
                                             <span style="font-size: <?php echo ib_html_entity_decode($_field->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($_field->font_style); ?>; color: <?php echo ib_html_entity_decode($_field->text_color); ?>;">
                                             <?php 
                                                if($_field->value != ''){
                                                   $value = $_field->value;
                                                   if(isset($updated_data)){
                                                      foreach($updated_data->item_table as $int_item_id => $it){
                                                         if($int_item_id == $inv_item['id']){
                                                            $value = $it->custom_field->$index->value;
                                                         }
                                                      }
                                                   }
                                                   echo ib_html_entity_decode($value);
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
                                                   echo app_format_money($value, $invoice->currency_name);
                                                }  
                                              ?>
                                             </span>
                                          </td>
                                       <?php } ?>
                                    <?php } ?>
                                 <?php } ?>
                              <?php } ?>
                           </tr>
                        <?php } ?>
                     </tbody>
                  </table>
               </div>
         </div>
         <!-- Invoice total section -->
          <?php $invoice_total_setting = $template_setting->invoice_total; ?>
         <div class="flex-box" style="background-color: <?php echo ib_html_entity_decode($invoice_total_setting->row_color); ?>;">
           <?php 
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
           ?>

           <?php 
           for($i = 1; $i <= $invoice_total_setting->col_number; $i++){ ?>
               <div class="flex4">
                  <?php foreach($invoice_total_data as $inv_t){ ?>
                     <?php if(isset($invoice_total_setting->$inv_t->active) && $invoice_total_setting->$inv_t->order_column == $i){ ?>

                        <?php if( $inv_t == 'subtotal_label'){ ?>
                           <?php $label = isset($updated_data->invoice_total->subtotal_label->label_content) ? $updated_data->invoice_total->subtotal_label->label_content : $invoice_total_setting->$inv_t->label_content; ?>
                           <p class="mbot5 mleft10 mright10" style="font-size: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->font_style); ?>; color: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->text_color); ?>; text-align: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->text_align); ?>;"> <?php echo ib_html_entity_decode($label); ?></p>
                        <?php } ?>

                        <?php if( $inv_t == 'subtotal_value'){ ?>
                           <p class="mbot5 mleft10 mright10" style="font-size: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->font_style); ?>; color: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->text_color); ?>; text-align: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->text_align); ?>;"> <?php echo app_format_money($invoice->subtotal, $invoice->currency_name); ?></p>
                        <?php } ?>

                        <?php if( $inv_t == 'taxes_label'){ ?>
                           <?php 
                              $items = get_items_table_data($invoice, 'invoice', 'html', true);
                               if(count($items->taxes()) > 0){
                                  foreach($items->taxes() as $k => $tax){ ?>
                                    <p class="mbot5 mleft10 mright10" style="font-size: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->font_style); ?>; color: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->text_color); ?>; text-align: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->text_align); ?>;"> <?php echo ib_html_entity_decode($tax['taxname'].' ('.app_format_number($tax['taxrate']).'%)'); ?></p>
                                 <?php }
                               } ?>

                        <?php } ?>

                        <?php if( $inv_t == 'taxes_value'){ 
                            $items = get_items_table_data($invoice, 'invoice', 'html', true);

                            if(count($items->taxes()) > 0){
                               foreach($items->taxes() as $k => $tax){ ?>
                                    <p class="mbot5 mleft10 mright10" style="font-size: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->font_style); ?>; color: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->text_color); ?>; text-align: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->text_align); ?>;"> <?php echo app_format_money($tax['total_tax'], $invoice->currency_name); ?></p>
                             <?php }
                            } ?>
                              

                        <?php } ?>

                        <?php if( $inv_t == 'total_tax_label'){ ?>
                           <?php $label = isset($updated_data->invoice_total->total_tax_label->label_content) ? $updated_data->invoice_total->total_tax_label->label_content : $invoice_total_setting->$inv_t->label_content; ?>
                           <p class="mbot5 mleft10 mright10" style="font-size: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->font_style); ?>; color: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->text_color); ?>; text-align: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->text_align); ?>;"> <?php echo ib_html_entity_decode($label); ?></p>
                        <?php } ?>

                        <?php if( $inv_t == 'total_tax_value'){ ?>
                           <p class="mbot5 mleft10 mright10" style="font-size: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->font_style); ?>; color: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->text_color); ?>; text-align: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->text_align); ?>;"> <?php echo app_format_money($invoice->total_tax, $invoice->currency_name); ?></p>
                        <?php } ?>
                        <?php if( $inv_t == 'discount_label'){ ?>
                           <?php $label = isset($updated_data->invoice_total->discount_label->label_content) ? $updated_data->invoice_total->discount_label->label_content : $invoice_total_setting->$inv_t->label_content; ?>
                           <p class="mbot5 mleft10 mright10" style="font-size: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->font_style); ?>; color: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->text_color); ?>; text-align: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->text_align); ?>;"> <?php echo ib_html_entity_decode($label); ?></p>
                        <?php } ?>
                        <?php if( $inv_t == 'discount_value'){ ?>
                           <p class="mbot5 mleft10 mright10" style="font-size: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->font_style); ?>; color: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->text_color); ?>; text-align: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->text_align); ?>;"> <?php echo app_format_money($invoice->discount_total, $invoice->currency_name); ?></p>
                        <?php } ?>

                        <?php if( $inv_t == 'grand_total_label'){ ?>
                           <?php $label = isset($updated_data->invoice_total->grand_total_label->label_content) ? $updated_data->invoice_total->grand_total_label->label_content : $invoice_total_setting->$inv_t->label_content; ?>
                           <p class="mbot5 mleft10 mright10" style="font-size: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->font_style); ?>; color: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->text_color); ?>; text-align: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->text_align); ?>;"> <?php echo ib_html_entity_decode($label); ?></p>
                        <?php } ?>
                        <?php if( $inv_t == 'grand_total_value'){ ?>
                           <p class="mbot5 mleft10 mright10" style="font-size: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->font_style); ?>; color: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->text_color); ?>; text-align: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->text_align); ?>;"> <?php echo app_format_money($invoice->total, $invoice->currency_name); ?></p>
                        <?php } ?>

                        <?php if( $inv_t == 'payment_amount_label'){ ?>

                           <?php $label = isset($updated_data->invoice_total->payment_amount_label->label_content) ? $updated_data->invoice_total->payment_amount_label->label_content : $invoice_total_setting->$inv_t->label_content; ?>
                           <p class="mbot5 mleft10 mright10" style="font-size: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->font_style); ?>; color: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->text_color); ?>; text-align: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->text_align); ?>;"> <?php echo ib_html_entity_decode($label); ?></p>
                        <?php } ?>

                        <?php if( $inv_t == 'payment_amount_value'){ ?>
                           <?php $invoice_payment_amount = $invoice->total - get_invoice_total_left_to_pay($invoice->id); ?>
                           <p class="mbot5 mleft10 mright10" style="font-size: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->font_style); ?>; color: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->text_color); ?>; text-align: <?php echo ib_html_entity_decode($invoice_total_setting->$inv_t->text_align); ?>;"> <?php echo app_format_money($invoice_payment_amount, $invoice->currency_name); ?></p>
                        <?php } ?>

                     <?php } ?>
                  <?php } ?>

                  <?php

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


                   ?>

                  <?php if( isset($invoice_total_setting->custom_field) ){ ?>
                     <?php $invoice_total_field = $invoice_total_setting->custom_field; ?>
                     <?php foreach($invoice_total_field as $field_id => $field){ ?>
                        <?php $cf = get_custom_field_by_id($field_id); ?>
                        <?php if($cf != '' && $field->order_column == $i && isset($field->active)){ ?>
                           <?php if($field->value != ''){ ?>
                              <?php $value = isset($updated_data->invoice_total->custom_field->$field_id->value) ? $updated_data->invoice_total->custom_field->$field_id->value : $field->value; ?>
                              <p class="mbot5 mleft10 mright10" style="font-size: <?php echo ib_html_entity_decode($field->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($field->font_style); ?>; color: <?php echo ib_html_entity_decode($field->text_color); ?>; text-align: <?php echo ib_html_entity_decode($field->text_align); ?>;"> <?php echo ib_html_entity_decode($value); ?></p>
                           <?php }else if($field->value == '' && $field->formula != ''){ ?>
                              <?php

                                 $invoice_total_formula_value = calculate_formula($data_invoice_total_formula, $_field->formula);
                                 $value = isset($updated_data->invoice_total->custom_field->$field_id->value) ? $updated_data->invoice_total->custom_field->$field_id->value : $invoice_total_formula_value;                  
                               ?>

                               <p class="mbot5 mleft10 mright10" style="font-size: <?php echo ib_html_entity_decode($field->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($field->font_style); ?>; color: <?php echo ib_html_entity_decode($field->text_color); ?>; text-align: <?php echo ib_html_entity_decode($field->text_align); ?>;"> <?php echo app_format_money($value, $invoice->currency_name); ?></p>

                           <?php } ?>
                        <?php  } ?>
                     <?php } ?>
                  <?php } ?>
               </div>
           <?php } ?>

         </div>

         <!-- bottom information section -->
          <?php $bot_infor_setting = $template_setting->bottom_information; ?>
         <div class="flex-box" style="background-color: <?php echo ib_html_entity_decode($bot_infor_setting->row_color); ?>;">
            <?php 
              $bottom_data = [
                  'payment_method',
                  'bank_infor',
                  'signature',
                  'qrcode',
                  'messages',
                  'delivery_note',
                  'client_note',
              ]; 
           ?>

            <?php 
            for($i = 1; $i <= $bot_infor_setting->col_number; $i++){ ?>
               <div class="flex4">
               <?php foreach($bottom_data as $bot){ ?>
                  <?php if(isset($bot_infor_setting->$bot->active) && $bot_infor_setting->$bot->order_column == $i){ ?>

                     <?php if( $bot == 'payment_method'){ ?>
                        <?php $label = isset($updated_data->bottom_information->payment_method->label_content) ? $updated_data->bottom_information->payment_method->label_content : $bot_infor_setting->$bot->label_content; ?>
                        <p class="mbot5 mleft10 mright10 d-grid" style="font-size: <?php echo ib_html_entity_decode($bot_infor_setting->$bot->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($bot_infor_setting->$bot->font_style); ?>; color: <?php echo ib_html_entity_decode($bot_infor_setting->$bot->text_color); ?>; text-align: <?php echo ib_html_entity_decode($bot_infor_setting->$bot->text_align); ?>;"> <?php echo ib_html_entity_decode($label); ?></p>
                     <?php } ?>

                     <?php if( $bot == 'bank_infor'){ ?>
                        <?php $label = isset($updated_data->bottom_information->bank_infor->label_content) ? $updated_data->bottom_information->bank_infor->label_content : $bot_infor_setting->$bot->label_content; ?>
                        <p class="mbot5 mleft10 mright10 d-grid" style="font-size: <?php echo ib_html_entity_decode($bot_infor_setting->$bot->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($bot_infor_setting->$bot->font_style); ?>; color: <?php echo ib_html_entity_decode($bot_infor_setting->$bot->text_color); ?>; text-align: <?php echo ib_html_entity_decode($bot_infor_setting->$bot->text_align); ?>;"> <?php echo ib_html_entity_decode($label); ?></p>
                     <?php } ?>

                     <?php if( $bot == 'signature'){ 
                           $justify_content = (isset($bot_infor_setting->$bot->text_align) ? 'justify-content:'.$bot_infor_setting->$bot->text_align.';' : 'justify-content:left');
                           $label = isset($updated_data->bottom_information->signature->label_content) ? $updated_data->bottom_information->signature->label_content : $bot_infor_setting->$bot->label_content;
                        ?>
                        <div class="mbot5 mleft10 mright10 d-grid" class="text-align: <?php echo (isset($bot_infor_setting->$bot->text_align) ? $bot_infor_setting->$bot->text_align : 'left'); ?>">
                           
                           <div class="d-flex" style="<?php echo ib_html_entity_decode($justify_content); ?>">
                              <span><?php echo ib_html_entity_decode($label); ?></span>
                               <?php if(get_invoice_signature_link($invoice->id) != ''){ ?>
                              <img src="<?php echo get_invoice_signature_link($invoice->id); ?>" class="img-responsive" style="width: <?php echo ib_html_entity_decode($bot_infor_setting->$bot->width).'px'; ?>;  height: <?php echo ib_html_entity_decode($bot_infor_setting->$bot->height).'px'; ?>" alt>
                           <?php } ?>
                           </div>
                        </div>
                     <?php } ?>

                     <?php if( $bot == 'qrcode'){ 
                           $justify_content = (isset($bot_infor_setting->$bot->text_align) ? 'justify-content:'.$bot_infor_setting->$bot->text_align.';' : 'justify-content:left');
                           $label = isset($updated_data->bottom_information->qrcode->label_content) ? $updated_data->bottom_information->qrcode->label_content : $bot_infor_setting->$bot->label_content;
                        ?>
                        <div class="mbot5 mleft10 mright10 d-grid" class="text-align: <?php echo (isset($bot_infor_setting->$bot->text_align) ? $bot_infor_setting->$bot->text_align : 'left'); ?>">
                           <span><?php echo ib_html_entity_decode($label); ?></span><div class="d-flex" style="<?php echo ib_html_entity_decode($justify_content); ?>"><img style="width: <?php echo ib_html_entity_decode($bot_infor_setting->$bot->width).'px'; ?>;  height: <?php echo ib_html_entity_decode($bot_infor_setting->$bot->height).'px'; ?>" src="<?php echo get_invoice_qrcode_link($invoice->id, $built_invoice_id); ?>" class="img-responsive" alt></div>
                        </div>
                     <?php } ?>

                     <?php if( $bot == 'messages'){ ?>
                        <?php $content = isset($updated_data->bottom_information->messages->content) ? $updated_data->bottom_information->messages->content : $bot_infor_setting->$bot->content; ?>
                        <p class="mbot5 mleft10 mright10 d-grid" style="font-size: <?php echo ib_html_entity_decode($bot_infor_setting->$bot->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($bot_infor_setting->$bot->font_style); ?>; color: <?php echo ib_html_entity_decode($bot_infor_setting->$bot->text_color); ?>; text-align: <?php echo ib_html_entity_decode($bot_infor_setting->$bot->text_align); ?>;"> <?php echo ib_html_entity_decode($content); ?></p>
                     <?php } ?>
                     <?php if( $bot == 'delivery_note'){ ?>
                        <?php $content = isset($updated_data->bottom_information->delivery_note->content) ? $updated_data->bottom_information->delivery_note->content : $bot_infor_setting->$bot->content; ?>
                        <p class="mbot5 mleft10 mright10 d-grid" style="font-size: <?php echo ib_html_entity_decode($bot_infor_setting->$bot->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($bot_infor_setting->$bot->font_style); ?>; color: <?php echo ib_html_entity_decode($bot_infor_setting->$bot->text_color); ?>; text-align: <?php echo ib_html_entity_decode($bot_infor_setting->$bot->text_align); ?>;"> <?php echo ib_html_entity_decode($content); ?></p>
                     <?php } ?>
                     <?php if( $bot == 'client_note'){ ?>
                        <?php $content = isset($updated_data->bottom_information->client_note->content) ? $updated_data->bottom_information->client_note->content : $bot_infor_setting->$bot->content; ?>
                        <p class="mbot5 mleft10 mright10 d-grid" style="font-size: <?php echo ib_html_entity_decode($bot_infor_setting->$bot->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($bot_infor_setting->$bot->font_style); ?>; color: <?php echo ib_html_entity_decode($bot_infor_setting->$bot->text_color); ?>; text-align: <?php echo ib_html_entity_decode($bot_infor_setting->$bot->text_align); ?>;"> <?php echo ib_html_entity_decode($content); ?></p>
                     <?php } ?>

                  <?php } ?>
               <?php } ?>

               <?php if( isset($bot_infor_setting->custom_field) ){ ?>
                  <?php $bot_infor_field = $bot_infor_setting->custom_field; ?>
                  <?php foreach($bot_infor_field as $field_id => $field){ ?>
                     <?php $cf = get_custom_field_by_id($field_id); ?>
                     <?php if($cf != '' && $field->order_column == $i && isset($field->active)){ ?>
                        <?php if($field->value != ''){ ?>
                           <?php $value = isset($updated_data->bottom_information->custom_field->$field_id->value) ? $updated_data->bottom_information->custom_field->$field_id->value : $field->value; ?>
                           <p class="mbot5 mleft10 mright10 d-grid" style="font-size: <?php echo ib_html_entity_decode($field->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($field->font_style); ?>; color: <?php echo ib_html_entity_decode($field->text_color); ?>; text-align: <?php echo ib_html_entity_decode($field->text_align); ?>;"> <?php echo ib_html_entity_decode($value); ?></p>
                        <?php } ?>
                     <?php  } ?>
                  <?php } ?>
               <?php } ?>
               </div>
            <?php } ?>
         </div>

         <!-- Footer section -->
          <?php $footer_setting = $template_setting->footer; ?>
         <div class="flex-box" style="background-color: <?php echo ib_html_entity_decode($footer_setting->row_color); ?>;">
            <?php 
              $footer_data = [
                  'term_condition',
              ]; 
           ?>

            <?php 
            for($i = 1; $i <= $footer_setting->col_number; $i++){ ?>
               <div class="flex4">
               <?php foreach($footer_data as $foot){ ?>
                  <?php if(isset($footer_setting->$foot->active) && $footer_setting->$foot->order_column == $i){ ?>
                     <?php if( $foot == 'term_condition'){ ?>
                        <?php $content = isset($updated_data->footer->term_condition->content) ? $updated_data->footer->term_condition->content : $footer_setting->$foot->content; ?>
                        <p class="mbot5 mleft10" style="font-size: <?php echo ib_html_entity_decode($footer_setting->$foot->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($footer_setting->$foot->font_style); ?>; color: <?php echo ib_html_entity_decode($footer_setting->$foot->text_color); ?>; text-align: <?php echo ib_html_entity_decode($footer_setting->$foot->text_align); ?>;"> <?php echo ib_html_entity_decode($content); ?></p>
                     <?php } ?>
                  <?php } ?>
               <?php } ?>

               <?php if( isset($footer_setting->custom_field) ){ ?>
                  <?php $footer_field = $footer_setting->custom_field; ?>
                  <?php foreach($footer_field as $field_id => $field){ ?>
                     <?php $cf = get_custom_field_by_id($field_id); ?>
                     <?php if($cf != '' && $field->order_column == $i && isset($field->active)){ ?>
                        <?php if($field->value != ''){ ?>
                           <?php $value = isset($updated_data->footer->custom_field->$field_id->value) ? $updated_data->footer->custom_field->$field_id->value : $field->value; ?>
                           <p class="mbot5 mleft10" style="font-size: <?php echo ib_html_entity_decode($field->font_size).'px'; ?>; font-style: <?php echo ib_html_entity_decode($field->font_style); ?>; color: <?php echo ib_html_entity_decode($field->text_color); ?>; text-align: <?php echo ib_html_entity_decode($field->text_align); ?>;"> <?php echo ib_html_entity_decode($value); ?></p>
                        <?php } ?>
                     <?php  } ?>
                  <?php } ?>
               <?php } ?>
               </div>
            <?php } ?>
         </div>

          <div class="panel-body preview-form reset-panel mtop20">
            <div class="col-md-12 invoice-html-payments">
               <?php
               $total_payments = count($invoice->payments);
               if ($total_payments > 0) { ?>
                  <p class="bold mbot15 font-medium"><?php echo _l('invoice_received_payments'); ?>:</p>
                  <table class="table table-hover invoice-payments-table">
                     <thead>
                        <tr>
                           <th><?php echo _l('invoice_payments_table_number_heading'); ?></th>
                           <th><?php echo _l('invoice_payments_table_mode_heading'); ?></th>
                           <th><?php echo _l('invoice_payments_table_date_heading'); ?></th>
                           <th><?php echo _l('invoice_payments_table_amount_heading'); ?></th>
                        </tr>
                     </thead>
                     <tbody>
                        <?php foreach ($invoice->payments as $payment) { ?>
                           <tr>
                              <td>
                                 <span class="pull-left"><?php echo ib_html_entity_decode($payment['paymentid']); ?></span>
                                 <?php echo form_open($this->uri->uri_string()); ?>
                                 <button type="submit" value="<?php echo ib_html_entity_decode($payment['paymentid']); ?>" class="btn btn-icon btn-default pull-right" name="paymentpdf"><i class="fa fa-file-pdf"></i></button>
                                 <?php echo form_close(); ?>
                              </td>
                              <td><?php echo ib_html_entity_decode($payment['name']); ?> <?php if (!empty($payment['paymentmethod'])) {
                                                                     echo ' - ' . $payment['paymentmethod'];
                                                                  } ?></td>
                              <td><?php echo _d($payment['date']); ?></td>
                              <td><?php echo app_format_money($payment['amount'], $invoice->currency_name); ?></td>
                           </tr>
                        <?php } ?>
                     </tbody>
                  </table>
                  <hr />
               <?php } else { ?>
                  <h5 class="bold pull-left"><?php echo _l('invoice_no_payments_found'); ?></h5>
                  <div class="clearfix"></div>
                  <hr />
               <?php } ?>
            </div>

            <?php
               // No payments for paid and cancelled
               if (($invoice->status != Invoices_model::STATUS_PAID
                  && $invoice->status != Invoices_model::STATUS_CANCELLED
                  && $invoice->total > 0)) { ?>
                  <div class="col-md-12">
                     <div class="row">
                        <?php
                        $found_online_mode = false;
                        if (found_invoice_mode($payment_modes, $invoice->id, false)) {
                           $found_online_mode = true;
                        ?>
                           <div class="col-md-6 text-left">
                              <p class="bold mbot15 font-medium"><?php echo _l('invoice_html_online_payment'); ?></p>
                              <?php echo form_open($this->uri->uri_string(), array('id' => 'online_payment_form', 'novalidate' => true)); ?>
                              <?php foreach ($payment_modes as $mode) {
                                 if (!is_numeric($mode['id']) && !empty($mode['id'])) {
                                    if (!is_payment_mode_allowed_for_invoice($mode['id'], $invoice->id)) {
                                       continue;
                                    }
                              ?>
                                    <div class="radio radio-success online-payment-radio">
                                       <input type="radio" value="<?php echo ib_html_entity_decode($mode['id']); ?>" id="pm_<?php echo ib_html_entity_decode($mode['id']); ?>" name="paymentmode">
                                       <label for="pm_<?php echo ib_html_entity_decode($mode['id']); ?>"><?php echo ib_html_entity_decode($mode['name']); ?></label>
                                    </div>
                                    <?php if (!empty($mode['description'])) { ?>
                                       <div class="mbot15">
                                          <?php echo ib_html_entity_decode($mode['description']); ?>
                                       </div>
                              <?php }
                                 }
                              } ?>
                              <div class="form-group mtop25">
                                 <?php if (get_option('allow_payment_amount_to_be_modified') == 1) { ?>
                                    <label for="amount" class="control-label"><?php echo _l('invoice_html_amount'); ?></label>
                                    <div class="input-group">
                                       <input type="number" required max="<?php echo ib_html_entity_decode($invoice->total_left_to_pay); ?>" data-total="<?php echo ib_html_entity_decode($invoice->total_left_to_pay); ?>" name="amount" class="form-control" value="<?php echo ib_html_entity_decode($invoice->total_left_to_pay); ?>">
                                       <span class="input-group-addon">
                                          <?php echo ib_html_entity_decode($invoice->symbol); ?>
                                       </span>
                                    </div>
                                 <?php } else {
                                    echo '<h4 class="bold mbot25">' . _l('invoice_html_total_pay', app_format_money($invoice->total_left_to_pay, $invoice->currency_name)) . '</h4>';
                                 }
                                 ?>
                              </div>
                              <div id="pay_button">
                                 <input id="pay_now" type="submit" name="make_payment" class="btn btn-success" value="<?php echo _l('invoice_html_online_payment_button_text'); ?>">
                              </div>
                              <input type="hidden" name="hash" value="<?php echo ib_html_entity_decode($hash); ?>">
                              <?php echo form_close(); ?>
                           </div>
                        <?php } ?>
                        <?php if (found_invoice_mode($payment_modes, $invoice->id)) { ?>
                           <div class="invoice-html-offline-payments <?php if ($found_online_mode == true) {
                                                                        echo 'col-md-6 text-right';
                                                                     } else {
                                                                        echo 'col-md-12';
                                                                     }; ?>">
                              <p class="bold mbot15 font-medium"><?php echo _l('invoice_html_offline_payment'); ?>:</p>
                              <?php foreach ($payment_modes as $mode) {
                                 if (is_numeric($mode['id'])) {
                                    if (!is_payment_mode_allowed_for_invoice($mode['id'], $invoice->id)) {
                                       continue;
                                    }
                              ?>
                                    <p class="bold"><?php echo ib_html_entity_decode($mode['name']); ?></p>
                                    <?php if (!empty($mode['description'])) { ?>
                                       <div class="mbot15">
                                          <?php echo ib_html_entity_decode($mode['description']); ?>
                                       </div>
                              <?php }
                                 }
                              } ?>
                           </div>
                        <?php } ?>
                     </div>
                  </div>
               <?php } ?>
            </div>

      </div>
   </div>
</div>

<?php require 'modules/invoices_builder/assets/js/view_invoice/html_index_js.php';?>
