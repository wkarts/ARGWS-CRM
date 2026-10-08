<?php defined('BASEPATH') or exit('No direct script access allowed');?>
<?php 
	if($built_invoice->updated_data != '' && $built_invoice->updated_data != null){
		$updated_data = json_decode($built_invoice->updated_data);
	}
?>

<?php if($template->setting != ''){ ?>
<div class="col-md-12">
<?php foreach(json_decode($template->setting) as $key => $setting){
	if($key == 'header'){ ?>
		<div class="panel panel-info">
			<div class="panel-heading"><?php echo _l('ib_header_information'); ?></div>
			<div class="panel-body">
				<div class="row">
					<?php foreach($setting as $key_setting => $set){ ?>
						<?php if($key_setting == 'logo'){ ?>
							<div class="col-md-6">
								<label for="logo"><?php echo _l('ib_logo'); ?></label>
								<?php echo get_dark_company_logo(); ?>
							</div>
						<?php }else if($key_setting == 'qrcode'){ ?>
							<div class="col-md-6">
								<label for="logo"><?php echo _l('ib_qr_code'); ?></label>
								<img src="<?php echo get_invoice_qrcode_link($invoice->id, $built_invoice_id); ?>" class="img-responsive" alt>
							</div>
						<?php }else if($key_setting == 'title'){ ?>
							<div class="col-md-12">
								<?php $header_title = isset($updated_data->header->title) ? $updated_data->header->title : format_invoice_number($invoice->id);
								echo render_input('header[title]', 'ib_title', $header_title); ?>
							</div>
						<?php }else if($key_setting == 'invoice_infor'){ ?>
							<div class="col-md-12">
								<p class="bold row-infor-color"><?php echo _l('ib_invoice_information'); ?></p>
								<hr class="row-infor-hr">
							</div>
							<?php if(isset($set->invoice_number)){ ?>
								<div class="col-md-6">
									<?php $invoice_number = isset($updated_data->header->invoice_infor->invoice_number) ? $updated_data->header->invoice_infor->invoice_number : format_invoice_number($invoice->id);
									echo render_input('header[invoice_infor][invoice_number]', 'ib_invoice_number', $invoice_number); ?>
								</div>
							<?php } ?>
							<?php if(isset($set->invoice_date)){ ?>
								<div class="col-md-6">
									<?php $invoice_date = isset($updated_data->header->invoice_infor->invoice_date) ? $updated_data->header->invoice_infor->invoice_date : _d($invoice->date);
									echo render_date_input('header[invoice_infor][invoice_date]', 'ib_invoice_date', $invoice_date ); ?>
								</div>
							<?php } ?>
							<?php if(isset($set->invoice_due_date)){ ?>
								<div class="col-md-6">
									<?php $invoice_due_date = isset($updated_data->header->invoice_infor->invoice_due_date) ? $updated_data->header->invoice_infor->invoice_due_date : _d($invoice->duedate);
									echo render_date_input('header[invoice_infor][invoice_due_date]', 'ib_invoice_due_date', $invoice_due_date ); ?>
								</div>
							<?php } ?>
							<?php if(isset($set->invoice_amount)){ ?>
								<div class="col-md-6">
									<?php $invoice_amount = isset($updated_data->header->invoice_infor->invoice_amount) ? $updated_data->header->invoice_infor->invoice_amount : $invoice->total;
									 echo render_input('header[invoice_infor][invoice_amount]', 'header_invoice_amount', $invoice_amount, 'number'); ?>
								</div>
							<?php } ?>
							<?php if(isset($set->custom_field)){ ?>
								<?php
									foreach($set->custom_field as $field_id => $field){
										$cf = get_custom_field_by_id($field_id);
										if($cf != ''){ ?>
											<div class="col-md-6">
												<?php $value = isset($updated_data->header->invoice_infor->custom_field->$field_id->value) ? $updated_data->header->invoice_infor->custom_field->$field_id->value : $field->value;
												 echo render_input('header[invoice_infor][custom_field]['.$field_id.'][value]', $cf->label, $value); ?>
											</div>

									<?php }
									}
								 ?>
							<?php } ?>

						<?php } ?>
					<?php } ?>
				</div>
			</div>
		</div>
	<?php }else if($key == 'sender_receiver'){ ?>
		<div class="panel panel-info">
			<div class="panel-heading"><?php echo _l('ib_sender_recriver_information_section'); ?></div>
			<div class="panel-body">
				<div class="row">
					<?php foreach($setting as $key_setting => $set){ ?>
						<?php if($key_setting == 'sender_infor'){ ?>
						<div class=" col-md-6">	
							<div class="row">
								<div class="col-md-12">
									<p class="bold row-infor-color"><?php echo _l('ib_sender_information'); ?></p>
									<hr class="row-infor-hr">
								</div>
								<?php if(isset($set->name->active)){ ?>
									<div class="col-md-6">
										<?php $sender_name = isset($updated_data->sender_receiver->sender_infor->name) ? $updated_data->sender_receiver->sender_infor->name : get_option('invoice_company_name');
										echo render_input('sender_receiver[sender_infor][name]', 'ib_name', $sender_name); ?>
									</div>
								<?php } ?>
								<?php if(isset($set->phone->active)){ ?>
									<div class="col-md-6">
										<?php $sender_phone = isset($updated_data->sender_receiver->sender_infor->phone) ? $updated_data->sender_receiver->sender_infor->phone : get_option('invoice_company_phonenumber');
										echo render_input('sender_receiver[sender_infor][phone]', 'ib_phone', $sender_phone); ?>
									</div>
								<?php } ?>
								<?php if(isset($set->email->active)){ ?>
									<div class="col-md-6">
										<?php $sender_email = isset($updated_data->sender_receiver->sender_infor->email) ? $updated_data->sender_receiver->sender_infor->email : get_option('smtp_email');
										echo render_input('sender_receiver[sender_infor][email]', 'ib_email', $sender_email); ?>
									</div>
								<?php } ?>
								<?php if(isset($set->website->active)){ ?>
									<div class="col-md-6">
										<?php $sender_web = isset($updated_data->sender_receiver->sender_infor->website) ? $updated_data->sender_receiver->sender_infor->website : site_url();
										echo render_input('sender_receiver[sender_infor][website]', 'ib_website', $sender_web); ?>
									</div>
								<?php } ?>
								<?php if(isset($set->street->active)){ ?>
									<div class="col-md-6">
										<?php $sender_street = isset($updated_data->sender_receiver->sender_infor->street) ? $updated_data->sender_receiver->sender_infor->street : get_option('invoice_company_address');
										echo render_input('sender_receiver[sender_infor][street]', 'ib_street_name', $sender_street); ?>
									</div>
								<?php } ?>
								<?php if(isset($set->city->active)){ ?>
									<div class="col-md-6">
										<?php $sender_city = isset($updated_data->sender_receiver->sender_infor->city) ? $updated_data->sender_receiver->sender_infor->city : get_option('invoice_company_city');
										echo render_input('sender_receiver[sender_infor][city]', 'ib_city', $sender_city); ?>
									</div>
								<?php } ?>
								<?php if(isset($set->state->active)){ ?>
									<div class="col-md-6">
										<?php $sender_state = isset($updated_data->sender_receiver->sender_infor->state) ? $updated_data->sender_receiver->sender_infor->state : get_option('company_state');
										echo render_input('sender_receiver[sender_infor][state]', 'ib_state', $sender_state); ?>
									</div>
								<?php } ?>
								<?php if(isset($set->zipcode->active)){ ?>
									<div class="col-md-6">
										<?php $sender_zipcode = isset($updated_data->sender_receiver->sender_infor->zipcode) ? $updated_data->sender_receiver->sender_infor->zipcode : get_option('invoice_company_postal_code');
										echo render_input('sender_receiver[sender_infor][zipcode]', 'ib_zipcode', $sender_zipcode); ?>
									</div>
								<?php } ?>
								<?php if(isset($set->country->active)){ ?>
									<div class="col-md-6">
										<?php $sender_country = isset($updated_data->sender_receiver->sender_infor->country) ? $updated_data->sender_receiver->sender_infor->country : get_option('invoice_company_country_code');
										echo render_input('sender_receiver[sender_infor][country]', 'ib_country', $sender_country); ?>
									</div>
								<?php } ?>
								<?php if(isset($set->vat->active)){ ?>
									<div class="col-md-6">
										<?php $sender_vat = isset($updated_data->sender_receiver->sender_infor->vat) ? $updated_data->sender_receiver->sender_infor->vat : get_option('company_vat');
										echo render_input('sender_receiver[sender_infor][vat]', 'ib_vat_number', $sender_vat); ?>
									</div>
								<?php } ?>
								<?php if(isset($set->sales_person->active)){ ?>
									<div class="col-md-6">
										<?php  $sales_person = isset($updated_data->sender_receiver->sender_infor->sales_person) ? $updated_data->sender_receiver->sender_infor->sales_person : get_staff_full_name($invoice->sale_agent);
										echo render_input('sender_receiver[sender_infor][sales_person]', 'ib_sales_person', $sales_person); ?>
									</div>
								<?php } ?>
								<?php if(isset($set->custom_field)){ ?>
									<?php foreach($set->custom_field as $field_id => $field){ ?>
										<?php $cf = get_custom_field_by_id($field_id); ?>
										<?php if($cf != '' && isset($field->active)){ ?>
											<div class="col-md-6">
												<?php $value = isset($updated_data->sender_receiver->sender_infor->custom_field->$field_id->value) ? $updated_data->sender_receiver->sender_infor->custom_field->$field_id->value : $field->value;
												echo render_input('sender_receiver[sender_infor][custom_field]['.$field_id.'][value]', $cf->label, $value); ?>
											</div>
										<?php } ?>
									<?php } ?>
								<?php } ?>
							</div>
						</div>
						<?php }else if($key_setting == 'receiver_infor'){ ?>
						<div class=" col-md-6">
							<div class="row">
								<div class="col-md-12">
									<p class="bold row-infor-color"><?php echo _l('ib_receiver_information'); ?></p>
									<hr class="row-infor-hr">
								</div>

								<?php 
									$this->load->model('clients_model'); 
									$_client = $this->clients_model->get($invoice->clientid);
									$_client_email = ib_get_primary_contact_email($invoice->clientid);
								?>
								<?php if(isset($set->name->active)){ ?>
									<div class="col-md-6">
										<?php $receiver_name = isset($updated_data->sender_receiver->receiver_infor->name) ? $updated_data->sender_receiver->receiver_infor->name : get_company_name($invoice->clientid);
										echo render_input('sender_receiver[receiver_infor][name]', 'ib_name', $receiver_name); ?>
									</div>
								<?php } ?>
								<?php if(isset($set->phone->active)){ ?>
									<div class="col-md-6">
										<?php $receiver_phone = isset($updated_data->sender_receiver->receiver_infor->phone) ? $updated_data->sender_receiver->receiver_infor->phone : $_client->phonenumber;
										echo render_input('sender_receiver[receiver_infor][phone]', 'ib_phone', $receiver_phone); ?>
									</div>
								<?php } ?>
								<?php if(isset($set->email->active)){ ?>
									<div class="col-md-6">
										<?php $receiver_email = isset($updated_data->sender_receiver->receiver_infor->email) ? $updated_data->sender_receiver->receiver_infor->email : $_client_email;
										echo render_input('sender_receiver[receiver_infor][email]', 'ib_email', $receiver_email); ?>
									</div>
								<?php } ?>
								<?php if(isset($set->website->active)){ ?>
									<div class="col-md-6">
										<?php $receiver_website = isset($updated_data->sender_receiver->receiver_infor->website) ? $updated_data->sender_receiver->receiver_infor->website : $_client->website;
										echo render_input('sender_receiver[receiver_infor][website]', 'ib_website', $receiver_website ); ?>
									</div>
								<?php } ?>
								<?php if(isset($set->street->active)){ ?>
									<div class="col-md-6">
										<?php $receiver_street = isset($updated_data->sender_receiver->receiver_infor->street) ? $updated_data->sender_receiver->receiver_infor->street : $_client->address;
										echo render_input('sender_receiver[receiver_infor][street]', 'ib_street_name', $receiver_street); ?>
									</div>
								<?php } ?>
								<?php if(isset($set->city->active)){ ?>
									<div class="col-md-6">
										<?php $receiver_city = isset($updated_data->sender_receiver->receiver_infor->city) ? $updated_data->sender_receiver->receiver_infor->city : $_client->city;
										echo render_input('sender_receiver[receiver_infor][city]', 'ib_city', $receiver_city); ?>
									</div>
								<?php } ?>
								<?php if(isset($set->state->active)){ ?>
									<div class="col-md-6">
										<?php $receiver_state = isset($updated_data->sender_receiver->receiver_infor->state) ? $updated_data->sender_receiver->receiver_infor->state : $_client->state;
										echo render_input('sender_receiver[receiver_infor][state]', 'ib_state', $receiver_state); ?>
									</div>
								<?php } ?>
								<?php if(isset($set->zipcode->active)){ ?>
									<div class="col-md-6">
										<?php $receiver_zipcode = isset($updated_data->sender_receiver->receiver_infor->zipcode) ? $updated_data->sender_receiver->receiver_infor->zipcode : $_client->zip;
										echo render_input('sender_receiver[receiver_infor][zipcode]', 'ib_zipcode', $receiver_zipcode); ?>
									</div>
								<?php } ?>
								<?php if(isset($set->country->active)){ ?>
									<div class="col-md-6">
										<?php $receiver_country = isset($updated_data->sender_receiver->receiver_infor->country) ? $updated_data->sender_receiver->receiver_infor->country : get_country_name($_client->country);
										echo render_input('sender_receiver[receiver_infor][country]', 'ib_country', $receiver_country); ?>
									</div>
								<?php } ?>
								<?php if(isset($set->vat->active)){ ?>
									<div class="col-md-6">
										<?php $receiver_vat = isset($updated_data->sender_receiver->receiver_infor->vat) ? $updated_data->sender_receiver->receiver_infor->vat : $_client->vat;
										echo render_input('sender_receiver[receiver_infor][vat]', 'ib_vat_number', $receiver_vat); ?>
									</div>
								<?php } ?>
								<?php if(isset($set->billing_to->active)){ ?>
									<div class="col-md-6">
										<?php $billing_to = isset($updated_data->sender_receiver->receiver_infor->billing_to) ? $updated_data->sender_receiver->receiver_infor->billing_to : strip_tags(format_customer_info($invoice, 'invoice', 'billing'));
										echo render_textarea('sender_receiver[receiver_infor][billing_to]', 'ib_billing_to', $billing_to ); ?>
									</div>
								<?php } ?>
								<?php if(isset($set->shipping_to->active)){ ?>
									<div class="col-md-6">
										<?php $shipping_to = isset($updated_data->sender_receiver->receiver_infor->shipping_to) ? $updated_data->sender_receiver->receiver_infor->shipping_to : strip_tags(format_customer_info($invoice, 'invoice', 'shipping'));
										echo render_textarea('sender_receiver[receiver_infor][shipping_to]', 'ib_shipping_to', strip_tags(format_customer_info($invoice, 'invoice', 'shipping')) ); ?>
									</div>
								<?php } ?>
								<?php if(isset($set->custom_field)){ ?>
									<?php foreach($set->custom_field as $field_id => $field){ ?>
										<?php $cf = get_custom_field_by_id($field_id); ?>
										<?php if($cf != '' && isset($field->active)){ ?>
											<div class="col-md-6">
												<?php $value = isset($updated_data->sender_receiver->receiver_infor->custom_field->$field_id->value) ? $updated_data->sender_receiver->receiver_infor->custom_field->$field_id->value : $field->value;
												echo render_input('sender_receiver[receiver_infor][custom_field]['.$field_id.'][value]', $cf->label, $value); ?>
											</div>
										<?php } ?>
									<?php } ?>
								<?php } ?>
							</div>
						</div>
						<?php } ?>
					<?php } ?>
				</div>
			</div>
		</div>
	</div>
	<?php }else if($key == 'item_table'){ ?>
	<?php
		$not_show_column = ['thead_color', 'text_color', 'font_size', 'font_style', 'text_align', 'striped_row', 'image', 'custom_field'] ;
		$items = get_items_table_data($invoice, 'invoice', 'html', true);
	?>
		
	<div class="col-md-12">
		<div class="panel panel-info">
			<div class="panel-heading"><?php echo _l('ib_items_table'); ?></div>
			<div class="panel-body">
				<div class="row">
					<div class="table-responsive s_table">
						<table class="table invoice-items-table items table-main-invoice-edit has-calculations no-mtop">
							<thead>
								<tr>
									<?php foreach($setting as $key_setting => $set){ ?>
										<?php if( !in_array($key_setting, $not_show_column) && isset($set->active) ){ ?>
											<th><?php echo _l($key_setting); ?></th>
										<?php }else if($key_setting == 'custom_field'){
													foreach($set as $field_id => $field){
														$cf = get_custom_field_by_id($field_id);
														if($cf != '' && isset($field->active)){
															echo '<th>'.$cf->label.'</th>';
														}
													}
										 	  }
										 ?>
									<?php } ?>
								</tr>

							</thead>
							<tbody>
								<?php foreach($invoice->items as $it_key => $inv_item){ ?>
									<?php
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

			                            if(isset($setting->custom_field)){ 
			                                foreach($setting->custom_field as $index => $_field){
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
									<tr>
										<?php foreach($setting as $key_setting => $set){ ?>
											<?php if( !in_array($key_setting, $not_show_column) && isset($set->active)){ ?>
												<?php if($key_setting == 'item_order'){ ?>
													<td><?php echo '<span>'.$inv_item['item_order'].'</span>'; ?></td>
												<?php }else if($key_setting == 'item_name'){ ?>
													<td><?php echo '<span>'.$inv_item['description'].'</span>'; ?></td>
												<?php }else if($key_setting == 'description'){ ?>
													<td><?php echo '<span>'.$inv_item['long_description'].'</span>'; ?></td>
												<?php }else if($key_setting == 'quantity'){ ?>
													<td><?php echo '<span>'.$inv_item['qty'].'</span>'; ?></td>
												<?php }else if($key_setting == 'unit'){ ?>
													<td><?php echo '<span>'.$inv_item['unit'].'</span>'; ?></td>
												<?php }else if($key_setting == 'unit_price'){ ?>
													<td><?php echo '<span>'.app_format_money($inv_item['rate'], $invoice->currency_name).'</span>'; ?></td>
												<?php }else if($key_setting == 'tax_rate'){ ?>
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
													 <td><?php echo '<span>'.$itemHTML.'</span>'; ?></td>
												<?php }else if($key_setting == 'tax_amount'){ ?>
													<?php 
			                                            $item_taxes = get_invoice_item_taxes($inv_item['id']); 
			                                            $tax_amount = 0;
			                                            if (count($item_taxes) > 0) {
			                                                foreach ($item_taxes as $tax) {
			                                                    $tax_amount += $inv_item['qty'] * $inv_item['rate'] * $tax['taxrate'] / 100;
			                                                }
			                                            }
			                                        ?>
													<td><?php echo '<span>'.app_format_money($tax_amount, $invoice->currency_name).'</span>'; ?></td>
												<?php }else if($key_setting == 'subtotal'){ ?>
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
			                                        <td><?php echo '<span>'.app_format_money($subtotal, $invoice->currency_name).'</span>'; ?></td>

												<?php }else if($key_setting == 'sku'){  ?>
													<?php
														$item = ib_get_item_by_name($inv_item['description']);
														$sku = isset($item->sku_code) ? $item->sku_code : '';
													 ?>
													<td><?php 
													$_sku = $sku;
													if(isset($updated_data)){
														foreach($updated_data->item_table as $int_item_id => $it){
															if($int_item_id == $inv_item['id']){
																$_sku = $it->sku;
															}
														}
													}

													echo render_input('item_table['.$inv_item['id'].'][sku]', '', $_sku); ?></td>
												<?php }else if($key_setting == 'barcode'){ ?>
													<?php 
														$item = ib_get_item_by_name($inv_item['description']);
														$barcode = isset($item->commodity_barcode) ? $item->commodity_barcode : '';
													?>
													<td><?php 
													$_barcode = $barcode;
													if(isset($updated_data)){
														foreach($updated_data->item_table as $int_item_id => $it){
															if($int_item_id == $inv_item['id']){
																$_barcode = $it->barcode;
															}
														}
													}
													echo render_input('item_table['.$inv_item['id'].'][barcode]', '', $_barcode); ?></td>
												<?php } ?>

											<?php }else if($key_setting == 'custom_field'){
													foreach($set as $field_id => $_field){
														$cf = get_custom_field_by_id($field_id);
														if($cf != '' && isset($_field->active)){
															if($_field->value != ''){
																$value = $_field->value;
																foreach($updated_data->item_table as $int_item_id => $it){
																	if($int_item_id == $inv_item['id']){
																		$value = $it->custom_field->$field_id->value;
																	}
																}

																echo '<td>'.render_input('item_table['.$inv_item['id'].'][custom_field]['.$field_id.'][value]', '', $value).'</td>';
															}else if($_field->value == '' && $_field->formula != ''){
																$formula_value = calculate_formula($data_item_table_formula, $_field->formula);

																$value = $formula_value;
																if(isset($updated_data)){
																	foreach($updated_data->item_table as $int_item_id => $it){
																		if($int_item_id == $inv_item['id']){
																			$value = $it->custom_field->$field_id->value;
																		}
																	}
																}
																echo '<td>'.render_input('item_table['.$inv_item['id'].'][custom_field]['.$field_id.'][value]', '', $value).'</td>';
															}
														}
													}
										 	  	} ?>
										<?php } ?>
									</tr>
								<?php } ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php }else if($key == 'invoice_total'){ 

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
	<div class="col-md-12">
		<div class="panel panel-info">
			<div class="panel-heading"><?php echo _l('ib_invoice_total_information'); ?></div>
			<div class="panel-body">
				<div class="row">
					<?php foreach($setting as $key_setting => $set){ ?>
						<?php
							if($key_setting == 'subtotal_label' && isset($set->active)){
								$label = isset($updated_data->invoice_total->subtotal_label->label_content) ? $updated_data->invoice_total->subtotal_label->label_content : $set->label_content;
								echo '<div class="col-md-6">'.render_input('invoice_total[subtotal_label][label_content]', 'ib_subtotal_label',  $label).'</div>';
							}else if($key_setting == 'total_tax_label' && isset($set->active)){
								$label = isset($updated_data->invoice_total->total_tax_label->label_content) ? $updated_data->invoice_total->total_tax_label->label_content : $set->label_content;
								echo '<div class="col-md-6">'.render_input('invoice_total[total_tax_label][label_content]', 'ib_total_tax_label',  $label).'</div>';
							}else if($key_setting == 'discount_label' && isset($set->active)){
								$label = isset($updated_data->invoice_total->discount_label->label_content) ? $updated_data->invoice_total->discount_label->label_content : $set->label_content;
								echo '<div class="col-md-6">'.render_input('invoice_total[discount_label][label_content]', 'ib_discount_label',  $label).'</div>';
							}else if($key_setting == 'grand_total_label' && isset($set->active)){
								$label = isset($updated_data->invoice_total->grand_total_label->label_content) ? $updated_data->invoice_total->grand_total_label->label_content : $set->label_content;
								echo '<div class="col-md-6">'.render_input('invoice_total[grand_total_label][label_content]', 'ib_grand_total_label',  $label).'</div>';
							}else if($key_setting == 'payment_amount_label' && isset($set->active)){
								$label = isset($updated_data->invoice_total->payment_amount_label->label_content) ? $updated_data->invoice_total->payment_amount_label->label_content : $set->label_content;
								echo '<div class="col-md-6">'.render_input('invoice_total[payment_amount_label][label_content]', 'ib_payment_amount_label',  $label).'</div>';
							}else if($key_setting == 'custom_field'){
								foreach ($set as $field_id => $_field) {
									$cf = get_custom_field_by_id($field_id);
									if($cf != '' && isset($_field->active)){
										if($_field->value != ''){
											$value = isset($updated_data->invoice_total->custom_field->$field_id->value) ? $updated_data->invoice_total->custom_field->$field_id->value : $_field->value;
											echo '<div class="col-md-6">'.render_input('invoice_total[custom_field]['.$field_id.'][value]', $cf->name,  $value).'</div>';
										}else if($_field->value == '' && $_field->formula != ''){
											$invoice_total_formula_value = calculate_formula($data_invoice_total_formula, $_field->formula);
											$value = isset($updated_data->invoice_total->custom_field->$field_id->value) ? $updated_data->invoice_total->custom_field->$field_id->value : $invoice_total_formula_value;
											echo '<div class="col-md-6">'.render_input('invoice_total[custom_field]['.$field_id.'][value]', $cf->name,  $value).'</div>';
										}
									}
								}
							}
						 ?>
					<?php } ?>
				</div>
			</div>
		</div>
	</div>
	<?php }else if($key == 'bottom_information'){ ?>
	<div class="col-md-12">
		<div class="panel panel-info">
			<div class="panel-heading"><?php echo _l('ib_invoice_total_information'); ?></div>
			<div class="panel-body">
				<div class="row">
					<?php foreach($setting as $key_setting => $set){ ?>
						<?php
							if($key_setting == 'payment_method' && isset($set->active)){
								$label = isset($updated_data->bottom_information->payment_method->label_content) ? $updated_data->bottom_information->payment_method->label_content : $set->label_content;
								echo '<div class="col-md-6">'.render_input('bottom_information[payment_method][label_content]', 'ib_payment_method',  $label).'</div>';
							}else if($key_setting == 'bank_infor' && isset($set->active)){
								$label = isset($updated_data->bottom_information->bank_infor->label_content) ? $updated_data->bottom_information->bank_infor->label_content : $set->label_content;
								echo '<div class="col-md-6">'.render_input('bottom_information[bank_infor][label_content]', 'ib_bank_infor',  $label).'</div>';
							}else if($key_setting == 'signature' && isset($set->active)){
								$label = isset($updated_data->bottom_information->signature->label_content) ? $updated_data->bottom_information->signature->label_content : $set->label_content;
								echo '<div class="col-md-6">'.render_input('bottom_information[signature][label_content]', 'ib_signature_label',  $label).'</div>';
							}else if($key_setting == 'qrcode' && isset($set->active)){
								$label = isset($updated_data->bottom_information->qrcode->label_content) ? $updated_data->bottom_information->qrcode->label_content : $set->label_content;
								echo '<div class="col-md-6">'.render_input('bottom_information[qrcode][label_content]', 'ib_qr_label',  $label).'</div>';
							}else if($key_setting == 'messages' && isset($set->active)){
								$content = isset($updated_data->bottom_information->messages->content) ? $updated_data->bottom_information->messages->content : $set->content;
								echo '<div class="col-md-6">'.render_input('bottom_information[messages][content]', 'ib_messages',  $content).'</div>';
							}else if($key_setting == 'delivery_note' && isset($set->active)){
								$content = isset($updated_data->bottom_information->delivery_note->content) ? $updated_data->bottom_information->delivery_note->content : $set->content;
								echo '<div class="col-md-6">'.render_input('bottom_information[delivery_note][content]', 'ib_delivery_note',  $content).'</div>';
							}else if($key_setting == 'client_note' && isset($set->active)){
								$content = isset($updated_data->bottom_information->client_note->content) ? $updated_data->bottom_information->client_note->content : $set->content;
								echo '<div class="col-md-6">'.render_input('bottom_information[client_note][content]', 'ib_client_note',  $content).'</div>';
							}else if($key_setting == 'custom_field' && isset($set->active)){ 
								foreach ($set as $field_id => $_field) {
									$cf = get_custom_field_by_id($field_id);
									if($cf != '' && isset($_field->active)){
										$value = isset($updated_data->bottom_information->custom_field->$field_id->value) ? $updated_data->bottom_information->custom_field->$field_id->value : $_field->value;
										echo '<div class="col-md-6">'.render_input('bottom_information[custom_field]['.$field_id.'][value]', $cf->name,  $value).'</div>';
									}
								}
							}
						?>
					<?php } ?>
				</div>
			</div>
		</div>
	</div>
	<?php }else if($key == 'footer'){ ?>
	<div class="col-md-12">
		<div class="panel panel-info">
			<div class="panel-heading"><?php echo _l('ib_footer_information'); ?></div>
			<div class="panel-body">
				<div class="row">
					<?php foreach($setting as $key_setting => $set){ ?>
						<?php 
							if($key_setting == 'term_condition' && isset($set->active)){
								$content = isset($updated_data->footer->term_condition->content) ? $updated_data->footer->term_condition->content : $set->content;
								echo '<div class="col-md-6">'.render_input('footer[term_condition][content]', 'ib_term_condition',  $content).'</div>';
							}else if($key_setting == 'custom_field'){ 
								foreach ($set as $field_id => $_field) {
									$cf = get_custom_field_by_id($field_id);
									if($cf != '' && isset($_field->active)){
										$value = isset($updated_data->footer->custom_field->$field_id->value) ? $updated_data->footer->custom_field->$field_id->value : $_field->value;
										echo '<div class="col-md-6">'.render_input('footer[custom_field]['.$field_id.'][value]', $cf->name, $value).'</div>';
									}
								}
							}
						?>
					<?php } ?>
				</div>
			</div>
		</div>
	</div>
	<?php } ?>
<?php }  ?>

<?php } ?>