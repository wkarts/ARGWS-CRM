<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="panel-body">
	<div class="preview-form preview-form-overflow">
	<div class="main">
		<div class="page" style="background-color: <?php echo ib_html_entity_decode($template->background_color); ?>" id="main_page">
			<div class="wrap-page-content">
			<div class="page-content">
				<!-- Header section -->
				<?php 
				$header_setting = $template->header; 
				if(isset($header_setting->invoice_infor->custom_field)){
					$custom_field_data = $header_setting->invoice_infor->custom_field;
					unset($header_setting->invoice_infor->custom_field);
					foreach ($custom_field_data as $key => $value) {
						$header_setting->invoice_infor->$key = $value;
					}
				}
				?>
				<div class="page-section page-section-1">
					<div class="flex-box header_panel" style="background-color: <?php echo ib_html_entity_decode($header_setting->row_color); ?>">
						<?php for($i = 1; $i <= $header_setting->col_number; $i++){ ?>
							<div class="flex4 flex-item">
								<?php foreach($header_setting as $column_name => $column){ 
									if(isset($column->show_on_column) && $column->show_on_column == $i){ 
										$css_style = '';
										$css_style .= (isset($column->font_size) ? 'font-size:'.$column->font_size.'px;' : '');
										$css_style .= (isset($column->font_style) ? 'font-style:'.$column->font_style.';' : '');
										$css_style .= (isset($column->text_color) ? 'color:'.$column->text_color.';' : '');
										$css_style .= (isset($column->text_align) ? 'text-align:'.$column->text_align.';' : '');									
										$justify_content = (isset($column->text_align) ? 'justify-content:'.$column->text_align.';' : 'justify-content:left');
										$content = '';
										if($column_name == 'qrcode'){ 
											$style2 = '';
											$style2 .= (isset($column->width) ? 'width:'.$column->width.'px;' : '');
											$style2 .= (isset($column->height) ? 'height:'.$column->height.'px;' : '');
											$content = '<div class="d-flex" style="'.$justify_content.'"><img style="'.$style2.'" src="'.base_url('modules/invoices_builder/assets/images/qr_code.jpg').'" class="img-responsive" alt></div>';
										} 
										elseif($column_name == 'logo'){ 
											$style2 = '';
											$style2 .= (isset($column->width) ? 'width:'.$column->width.'px;' : '');
											$company_logo = get_option('company_logo' . (get_option('company_logo_dark') != '' ? '_dark' : ''));
											$content = '<div class="d-flex" style="'.$justify_content.'" ><img style="'.$style2.'" src="'.base_url('uploads/company/' . $company_logo).'" class="img-responsive" alt></div>';
										} 
										elseif($column_name == 'title'){ 
											$content = 'Example';
										} 
										elseif($column_name == 'invoice_infor'){ 
											foreach ($column as $child_name => $child_row) {
												if($child_name == 'invoice_number'){
													$content .= '<p class="mbot5 control-element-2 invoice_number'.($child_row == 1 ? '' : ' d-none').'">'._l('ib_invoice_number').': INV-0001</p>';
												}
												if($child_name == 'invoice_date'){
													$content .= '<p class="mbot5 control-element-2 invoice_date'.($child_row == 1 ? '' : ' d-none').'">'._l('ib_invoice_date').': '._d(date('Y-m-d')).'</p>';
												}
												if($child_name == 'invoice_due_date'){
													$content .= '<p class="mbot5 control-element-2 invoice_due_date'.($child_row == 1 ? '' : ' d-none').'">'._l('ib_invoice_due_date').': '._d(date('Y-m-d')).'</p>';
												}
												if($child_name == 'invoice_amount'){
													$content .= '<p class="mbot5 control-element-2 invoice_amount'.($child_row == 1 ? '' : ' d-none').'">'._l('ib_invoice_amount').': '.app_format_money('100', $currency_name).'</p>';
												}
												if($child_name == 'invoice_status'){
													$content .= '<p class="mbot5 control-element-2 invoice_status'.($child_row == 1 ? '' : ' d-none').'">'._l('ib_header_invoice_status').': <span class="text-danger">UNPAID</span></p>';
												}
												if(is_numeric($child_name)){
													$content .= '<p class="mbot5 control-element-2 '.$child_name.''.((isset($child_row->active)) ? '' : ' d-none').'">'._l('ib_header_invoice_status').': '.$child_row->value.'</p>';
												}
											}
										} 
										?>
										<div class="mbot5 mleft10 d-grid control-element <?php echo ib_html_entity_decode($column_name).''.(isset($column->active) ? '' : ' d-none') ?>" style="<?php echo ib_html_entity_decode($css_style); ?>" data-sort="<?php echo ib_html_entity_decode($i); ?>"><?php echo ib_html_entity_decode($content); ?></div>
									<?php }} ?>
								</div>
							<?php }
							?>
						</div>
					</div>
					<!-- sender receiver section -->
					<?php 
					$sender_receiver_setting = $template->sender_receiver; 
					if(isset($sender_receiver_setting->sender_infor->custom_field)){
						$custom_field_data = $sender_receiver_setting->sender_infor->custom_field;
						unset($sender_receiver_setting->sender_infor->custom_field);
						foreach ($custom_field_data as $key => $value) {
							$sender_receiver_setting->sender_infor->$key = $value;
						}
					}

					if(isset($sender_receiver_setting->receiver_infor->custom_field)){
						$custom_field_data = $sender_receiver_setting->receiver_infor->custom_field;
						unset($sender_receiver_setting->receiver_infor->custom_field);
						foreach ($custom_field_data as $key => $value) {
							$sender_receiver_setting->receiver_infor->$key = $value;
						}
					}
					?>
					<div class="page-section page-section-2">
						<div class="flex-box sender_receiver_panel" style="background-color: <?php echo ib_html_entity_decode($sender_receiver_setting->row_color); ?>">
							<?php for($i = 1; $i <= $sender_receiver_setting->col_number; $i++){ 
								?>
								<div class="flex4 flex-item">
									<?php foreach($sender_receiver_setting as $column_name => $column){ 
										if(isset($column->show_on_column) && $column->show_on_column == $i){ 
											?>
											<div class="control-element <?php echo ib_html_entity_decode($column_name).''.(isset($column->active) ? '' : ' d-none'); ?>" style="background-color: <?php echo ib_html_entity_decode($column->column_color); ?>"  data-sort="<?php echo ib_html_entity_decode($i); ?>">
												<br>
												<p class="mbot5 mleft10 bold fs20"><?php echo ($column_name == 'sender_infor' ? _l('ib_seller') : _l('ib_buyer')); ?></p>
												<?php foreach ($column as $child_name => $child) { 
														$style = '';
														if(isset($child->font_size) && isset($child->font_style) && isset($child->text_color) && isset($child->text_align)){
															$style = 'font-size:'.$child->font_size.'px;font-style:'.$child->font_style.';color:'.$child->text_color.';text-align:'.$child->text_align;
														}
														$content = $child_name;
														if($child_name == 'name'){
															$content = _l('ib_name').': Jonh Smith'; 
														}
														elseif($child_name == 'phone'){
															$content = _l('ib_phonenumber').': 0123456789';
														}
														elseif($child_name == 'email'){
															$content = _l('ib_email').': seller@gmail.com'; 
														}
														elseif($child_name == 'website'){
															$content = _l('ib_website').': '.site_url();
														}
														elseif($child_name == 'street'){
															$content = _l('ib_street').': 50 Pinchelone Street'; 
														}
														elseif($child_name == 'city'){
															$content = _l('ib_city').': Hampton'; 
														}
														elseif($child_name == 'state'){
															$content = _l('ib_state').': Virginia'; 
														}
														elseif($child_name == 'zipcode'){
															$content = _l('ib_zipcode').': 23669';
														}
														elseif($child_name == 'country'){
															$content = _l('ib_country').': United States'; 
														}
														elseif($child_name == 'vat'){
															$content = _l('ib_vat_number').': VAT0001'; 
														}
														elseif($child_name == 'sales_person'){
															$content = _l('ib_sales_person').': Jonh Doe'; 
														}

														elseif($child_name == 'name'){
															$content = _l('ib_name').': Jonh Doe'; 
														}
														elseif($child_name == 'phone'){
															$content = _l('ib_phonenumber').': 0123456789'; 
														}
														elseif($child_name == 'email'){
															$content = _l('ib_email').': mail@gmail.com'; 
														}
														elseif($child_name == 'website'){
															$content = _l('ib_website').': https://site.com'; 
														}
														elseif($child_name == 'street'){
															$content = _l('ib_street').': 50 Pinchelone Street'; 
														}
														elseif($child_name == 'city'){
															$content = _l('ib_city').': Hampton'; 
														}
														elseif($child_name == 'state'){
															$content = _l('ib_state').': Virginia'; 
														}
														elseif($child_name == 'zipcode'){
															$content = _l('ib_zipcode').': 23669';
														}
														elseif($child_name == 'country'){
															$content = _l('ib_country').': United States'; 
														}
														elseif($child_name == 'vat'){
															$content = _l('ib_vat_number').': VAT0001'; 
														}
														elseif($child_name == 'billing_to'){
															$content = _l('ib_billing_to').': 50 Pinchelone Street, Hampton, VA 23669, United States';
														}
														elseif($child_name == 'shipping_to'){
															$content = _l('ib_shipping_to').': 3801 Chalk Butte Rd, Cut Bank, MT 59427, United States'; 
														}
														else{
															if(is_numeric($child_name)){
																if($child->value != ''){
																	$content = $child->value; 
																}
															}
														}
														?>										
														<p class="mbot5 mleft10 mright10 control-element-2 <?php echo ib_html_entity_decode($column_name.' '.$child_name).''.(isset($child->active) ? '' : ' d-none'); ?>" style="<?php echo ib_html_entity_decode($style); ?>" ><?php echo ib_html_entity_decode($content); ?></p>
													<?php } ?>
												</div>
											<?php }} ?>
										</div>
									<?php } ?>
								</div>
							</div>


							<!-- Item table section -->
							<div class="page-section page-section-3">
								<div class="p-10 item_table_panel">
									<div class="table-responsive">
										<?php 
										$item_table_setting = $template->item_table; 
										$thead_color = $item_table_setting->thead_color;
										$striped_row = $item_table_setting->striped_row;
										$header_style = '';
										$header_style .= (isset($item_table_setting->text_align) ? 'text-align:'.$item_table_setting->text_align.';' : '');
										$header_style .= (isset($item_table_setting->font_style) ? 'font-style:'.$item_table_setting->font_style.';' : '');
										$header_style .= (isset($item_table_setting->font_size) ? 'font-size:'.$item_table_setting->font_size.'px;' : '');
										$header_style .= (isset($item_table_setting->text_color) ? 'color:'.$item_table_setting->text_color.';' : '');

										$odd_row_color = '';
										$even_row_color = '';
										if(isset($striped_row->active)){
											$odd_row_color = $striped_row->odd_row_color;
											$even_row_color = $striped_row->even_row_color;
										}
										unset($item_table_setting->thead_color);
										unset($item_table_setting->striped_row);

										if(isset($item_table_setting->custom_field)){
											$custom_field_data = $item_table_setting->custom_field;
											unset($item_table_setting->custom_field);
											foreach ($custom_field_data as $key => $value) {
												$item_table_setting->$key = $value;
											}
										}

										$formula_data = [];
										$array_1 = [
											['id' => 'item_table|quantity', 'value' => 1],
											['id' => 'item_table|tax_rate', 'value' => 0],
											['id' => 'item_table|tax_amount', 'value' => 0],
											['id' => 'item_table|unit_price', 'value' => 50],
											['id' => 'item_table|subtotal', 'value' => 50]
										];
										$array_2 = [
											['id' => 'item_table|quantity', 'value' => 2],
											['id' => 'item_table|tax_rate', 'value' => 0],
											['id' => 'item_table|tax_amount', 'value' => 0],
											['id' => 'item_table|unit_price', 'value' => 50],
											['id' => 'item_table|subtotal', 'value' => 100]
										];
										$data_customfield = $this->invoices_builder_model->get_custom_field('', 'type = "item_table" AND  value REGEXP \'^-?[0-9]+$\'', 'name, value');
										foreach ($data_customfield as $key => $value) {
											$array_1[] = ['id' => 'item_table|'.$value['name'], 'value' => $value['value']];
											$array_2[] = ['id' => 'item_table|'.$value['name'], 'value' => $value['value']];
										}
										$formula_data[1] = $array_1;
										$formula_data[2] = $array_2;
										$total_column = count((Array)$item_table_setting);
										$status_module = (ib_get_status_modules('warehouse') || ib_get_status_modules('purchase'));
										?>
										<table class="table mtop0">
											<thead style="background: <?php echo ib_html_entity_decode($thead_color); ?>">
												<tr>
													<?php for($i = 0; $i <= $total_column; $i++){ ?>
														<?php foreach($item_table_setting as $column_name => $column_data){ 
															if(isset($column_data->order_column) && $column_data->order_column == $i){
																$header_content = $column_name;
																if(is_numeric($column_name)){
																	$field_data = $this->invoices_builder_model->get_custom_field($column_name, '', 'label');
																	if($field_data){
																		$header_content = $field_data->label;
																	}
																}
																$required_hide = false;
																if(($column_name == 'sku' || $column_name == 'barcode' || $column_name == 'image') && !($status_module)){
																	$required_hide = true;
																}
																?>
																<th class="text-nowrap control-element <?php echo ib_html_entity_decode($column_name).''.((isset($column_data->active) && $required_hide == false) ? '' : ' d-none'); ?>" style="<?php echo ib_html_entity_decode($header_style); ?>" data-sort="<?php echo ib_html_entity_decode($i); ?>"><?php echo _l($header_content); ?></th>
															<?php } ?>
														<?php } ?>
													<?php } ?>
												</tr>
											</thead>
											<tbody>
												<?php 
												for($r = 1; $r <= 2; $r++){
													$row_style = '';
													if($r == 1 && $odd_row_color != ''){
														$row_style = 'background-color:'.$odd_row_color;
													}
													elseif($r == 2 && $even_row_color != ''){
														$row_style = 'background-color:'.$even_row_color;
													}
													?>
													<tr style="<?php echo ib_html_entity_decode($row_style); ?>">
														<?php for($i = 0; $i <= $total_column; $i++){ ?>
															<?php foreach($item_table_setting as $column_name => $column){ 
																?>
																<?php if(isset($column->order_column) && $column->order_column == $i){
																	$css_style = '';
																	$css_style .= (isset($column->font_size) ? 'font-size:'.$column->font_size.'px;' : '');
																	$css_style .= (isset($column->font_style) ? 'font-style:'.$column->font_style.';' : '');
																	$css_style .= (isset($column->text_color) ? 'color:'.$column->text_color.';' : '');
																	$css_style .= (isset($column->text_align) ? 'text-align:'.$column->text_align.';' : '');

																	$style2 = '';
																	$style2 .= (isset($column->width) ? 'width:'.$column->width.'px;' : '');
																	$style2 .= (isset($column->height) ? 'height:'.$column->height.'px;' : '');
																	$content = '';
																	$required_hide = false;
																	if($column_name == 'item_order'){ 
																		$content = $r;
																	} 
																	elseif($column_name == 'item_name'){ 
																		$content = 'Women\'s handbags '.$r;
																	} 
																	elseif($column_name == 'description'){ 
																		$content = 'Packing: 40pcs/carton<br>
																		Carton Size: 70x50x50cm';
																	} 
																	elseif($column_name == 'quantity'){ 
																		$quantity = $r;
																		$content = '<span>'.$quantity.'</span><input type="hidden" class="hidden_customfield_value" data-formula="" data-type="item_table" data-row-index="'.$r.'" name="item_table|'.$column_name.'" value="'.$quantity.'">';
																	} 
																	elseif($column_name == 'unit'){ 
																		$content = 'pcs';
																	} 
																	elseif($column_name == 'unit_price'){ 
																		$unit_price = 50;
																		$content = '<span>'.app_format_money($unit_price, $currency_name).'</span><input type="hidden" class="hidden_customfield_value" data-formula="" data-type="item_table" data-row-index="'.$r.'" name="item_table|'.$column_name.'" value="'.$unit_price.'">';
																	} 
																	elseif($column_name == 'tax_rate'){ 
																		$tax_rate = '0';
																		$content = '<span>'.$tax_rate.'</span><input type="hidden" class="hidden_customfield_value" data-formula="" data-type="item_table" data-row-index="'.$r.'" name="item_table|'.$column_name.'" value="'.$tax_rate.'">';
																	} 
																	elseif($column_name == 'tax_amount'){ 
																		$tax_amount = 0;
																		$content = '<span>'.app_format_money($tax_amount, $currency_name).'</span><input type="hidden" class="hidden_customfield_value" data-formula="" data-type="item_table" data-row-index="'.$r.'" name="item_table|'.$column_name.'" value="'.$tax_amount.'">';
																	} 
																	elseif($column_name == 'subtotal'){ 
																		$sub_total_value = $r * 50;
																		$content = '<span>'.app_format_money($sub_total_value, $currency_name).'</span><input type="hidden" class="hidden_customfield_value" data-formula="" data-type="item_table" data-row-index="'.$r.'" name="item_table|'.$column_name.'" value="'.$sub_total_value.'">';
																	} 
																	elseif($column_name == 'sku'){ 
																		$content = 'SKU000'.$r;																		
																	} 
																	elseif($column_name == 'barcode'){ 
																		$content = '0123456789abcdefghijklmnopqrstuvwxyz';
																	} 
																	elseif($column_name == 'image'){ 
																		$content = '<img class="images_w_table" style="'.$style2.'" src="'.base_url('modules/invoices_builder/assets/images/bag.jpg').'">';
																	}
																	else{
																		if(is_numeric($column_name)){
																			$field_data = $this->invoices_builder_model->get_custom_field($column_name, '', 'name');
																			if($field_data){
																				if($column->value != ''){
																					$content = '<span>'.$column->value.'</span><input type="hidden" class="hidden_customfield_value" data-formula="" data-type="item_table" data-row-index="'.$r.'" name="item_table|'.$field_data->name.'" value="'.$column->value.'">';
																				}
																				elseif($column->formula != ''){		
																					$value = calculate_formula($formula_data[$r], $column->formula);
																					$content = '<span>'.$value.'</span><input type="hidden" class="hidden_customfield_value has-formula" data-formula="'.$column->formula.'" data-type="item_table" data-row-index="'.$r.'" name="item_table|'.$field_data->name.'" value="'.$value.'">';
																				}
																			}
																		}
																	}
																	if(($column_name == 'sku' || $column_name == 'barcode' || $column_name == 'image') && !($status_module)){
																			$required_hide = true;
																	}
																	?>																	
																	<td class="text-nowrap control-element <?php echo ib_html_entity_decode($column_name).' '.((isset($column->active) && $required_hide == false) ? '' : 'd-none'); ?>" style="<?php echo ib_html_entity_decode($css_style); ?>" data-sort="<?php echo ib_html_entity_decode($i); ?>"><?php echo ib_html_entity_decode($content); ?></td>
																<?php } ?>
															<?php } ?>
														<?php } ?>
													</tr>
												<?php } ?>
											</tbody>
										</table>

									</div>
								</div>
							</div>

							<!-- Invoice total section -->
							<?php 
							$invoice_total_setting = $template->invoice_total; 
							if(isset($invoice_total_setting->custom_field)){
								$custom_field_data = $invoice_total_setting->custom_field;
								unset($invoice_total_setting->custom_field);
								foreach ($custom_field_data as $key => $value) {
									$invoice_total_setting->$key = $value;
								}
							}
							?>
							<div class="page-section page-section-4">
								<div class="flex-box invoice_total_panel" style="background-color: <?php echo ib_html_entity_decode($invoice_total_setting->row_color); ?>">
									<?php for($i = 1; $i <= $invoice_total_setting->col_number; $i++){ 
											$order = 0;
										?>
										<div class="flex4 flex-item">
											<?php foreach($invoice_total_setting as $column_name => $column){ 
												if(isset($column->order_column) && $column->order_column == $i){ 
													$order++;
													$style = '';
													if(isset($column->font_size) && isset($column->font_style) && isset($column->text_color) && isset($column->text_align)){
														$style = 'font-size:'.$column->font_size.'px;font-style:'.$column->font_style.';color:'.$column->text_color.';text-align:'.$column->text_align;
													}
													$content = '';
													if($column_name == 'subtotal_value'){ 
														$subtotal_value = 150;
														$content = '<span>'.app_format_money($subtotal_value, $currency_name).'</span><input type="hidden" class="hidden_customfield_value" name="invoice_total|'.$column_name.'" value="'.$subtotal_value.'">';
													} 
													elseif($column_name == 'taxes_value'){ 
														$taxes_value = 0;
														$content = '<span>'.app_format_money('0', $currency_name).'</span><input type="hidden" class="hidden_customfield_value" name="invoice_total|'.$column_name.'" value="'.$taxes_value.'">';
													} 
													elseif($column_name == 'total_tax_value'){ 
														$total_tax_value = 0;
														$content = '<span>'.app_format_money($total_tax_value, $currency_name).'</span><input type="hidden" class="hidden_customfield_value" name="invoice_total|'.$column_name.'" value="'.$total_tax_value.'">';
													} 
													elseif($column_name == 'discount_value'){ 
														$discount_value = 0;
														$content = '<span>'.app_format_money($discount_value, $currency_name).'</span><input type="hidden" class="hidden_customfield_value" name="invoice_total|'.$column_name.'" value="'.$discount_value.'">';
													}
													elseif($column_name == 'grand_total_value'){ 
														$grand_total_value = 150;
														$content = '<span>'.app_format_money($grand_total_value , $currency_name).'</span><input type="hidden" class="hidden_customfield_value" name="invoice_total|'.$column_name.'" value="'.$grand_total_value.'">';
													}
													elseif($column_name == 'payment_amount_value'){ 
														$grand_total_value = 150;
														$content = '<span>'.app_format_money($grand_total_value , $currency_name).'</span><input type="hidden" class="hidden_customfield_value" name="invoice_total|'.$column_name.'" value="'.$grand_total_value.'">';
													}
													else{
														if(is_numeric($column_name)){
															$field_data = $this->invoices_builder_model->get_custom_field($column_name, '', 'name');
															if($field_data){
																if($column->value != ''){
																	$content = '<span>'.$column->value.'</span><input type="hidden" class="hidden_customfield_value" data-formula="" data-type="invoice_total" data-row-index="" name="invoice_total|'.$field_data->name.'" value="'.$column->value.'">';
																}
																elseif($column->formula != ''){	
																	$value = '';	
																	$content = '<span>'.$value.'</span><input type="hidden" class="hidden_customfield_value has-formula" data-formula="'.$column->formula.'" data-type="invoice_total" data-row-index="" name="invoice_total|'.$field_data->name.'" value="'.$value.'">';
																}
															}
														}
														else{
															if(isset($column->label_content)){
																$content = $column->label_content;
															}
														}
													}
													?>
													<p class="mbot5 mleft10 mright10 control-element <?php echo ib_html_entity_decode($column_name).''.(isset($column->active) ? '' : ' d-none'); ?>" style="<?php echo ib_html_entity_decode($style); ?>"  data-sort="<?php echo ib_html_entity_decode($i); ?>" data-order="<?php echo ib_html_entity_decode($order); ?>"><?php echo ib_html_entity_decode($content); ?></p>
												<?php }} ?>
											</div>
										<?php } ?>
									</div>
								</div>


								<!-- bottom information section -->
								<?php 
								$bottom_information_setting = $template->bottom_information; 
								if(isset($bottom_information_setting->custom_field)){
									$custom_field_data = $bottom_information_setting->custom_field;
									unset($bottom_information_setting->custom_field);
									foreach ($custom_field_data as $key => $value) {
										$bottom_information_setting->$key = $value;
									}
								}
								?>
								<div class="page-section page-section-5">
									<div class="flex-box bottom_information_panel" style="background-color: <?php echo ib_html_entity_decode($bottom_information_setting->row_color); ?>">
										<?php for($i = 1; $i <= $bottom_information_setting->col_number; $i++){ 
												$order = 0;
											?>
											<div class="flex4 flex-item">
												<?php foreach($bottom_information_setting as $column_name => $column){ 
													if(isset($column->order_column) && $column->order_column == $i){ 
														$order++;
														$css_style = '';
														$css_style .= (isset($column->font_size) ? 'font-size:'.$column->font_size.'px;' : '');
														$css_style .= (isset($column->font_style) ? 'font-style:'.$column->font_style.';' : '');
														$css_style .= (isset($column->text_color) ? 'color:'.$column->text_color.';' : '');
														$css_style .= (isset($column->text_align) ? 'text-align:'.$column->text_align.';' : '');
														$content = '';
														if(isset($column->label_content)){
															if($column_name == 'qrcode' || $column_name == 'signature'){ 
																$style2 = '';
																$style2 .= (isset($column->width) ? 'width:'.$column->width.'px;' : '');
																$style2 .= (isset($column->height) ? 'height:'.$column->height.'px;' : '');
																$justify_content = (isset($column->text_align) ? 'justify-content:'.$column->text_align.';' : 'justify-content:left');
																if($column_name == 'signature'){
																	$content = '<span>'.$column->label_content.'</span><div class="d-flex" style="'.$justify_content.'"><div style="'.$style2.'" class="border-default signature"></div></div>';
																}
																else{
																	$content = '<span>'.$column->label_content.'</span><div class="d-flex" style="'.$justify_content.'"><img style="'.$style2.'" src="'.base_url('modules/invoices_builder/assets/images/qr_code.jpg').'" class="img-responsive" alt></div>';
																}
															} 
															else{
																$content = '<span>'.$column->label_content.'</span>';
															}
														}
														else{
															if(is_numeric($column_name)){
																$content = '<span>'.$column->value.'</span>';
															}
															else{
																if(isset($column->content)){
																	$content = '<span>'.$column->content.'</span>';
																}
															}
														}
														?>
														<div class="mbot5 mleft10 mright10 d-grid control-element <?php echo ib_html_entity_decode($column_name).''.(isset($column->active) ? '' : ' d-none'); ?>" style="<?php echo ib_html_entity_decode($css_style); ?>"  data-sort="<?php echo ib_html_entity_decode($i); ?>" data-order="<?php echo ib_html_entity_decode($order); ?>"><?php echo ib_html_entity_decode($content); ?></div>
													<?php }} ?>
												</div>
											<?php }
											?>
										</div>
									</div>


									<!-- Footer section -->
									<?php 
									$footer_setting = $template->footer; 
									if(isset($footer_setting->custom_field)){
										$custom_field_data = $footer_setting->custom_field;
										unset($footer_setting->custom_field);
										foreach ($custom_field_data as $key => $value) {
											$footer_setting->$key = $value;
										}
									}
									?>
									<div class="page-section page-section-6">
										<div class="flex-box footer_panel" style="background-color: <?php echo ib_html_entity_decode($footer_setting->row_color); ?>">
											<?php for($i = 1; $i <= $footer_setting->col_number; $i++){ 
												$order = 0;
												?>
												<div class="flex4 flex-item">
													<?php foreach($footer_setting as $column_name => $column){ 
														if(isset($column->order_column) && $column->order_column == $i){ 
															$order++;
															$style = '';
															if(isset($column->font_size) && isset($column->font_style) && isset($column->text_color) && isset($column->text_align)){
																$style = 'font-size:'.$column->font_size.'px;font-style:'.$column->font_style.';color:'.$column->text_color.';text-align:'.$column->text_align;
															}
															$content = '';
															if(is_numeric($column_name)){
																$content = '<span>'.$column->value.'</span>';
															}
															else{
																if(isset($column->content)){
																	$content = '<span>'.$column->content.'</span>';
																}
															}
															?>
															<p class="mbot5 mleft10 mright10 control-element control-element <?php echo ib_html_entity_decode($column_name).''.(isset($column->active) ? '' : ' d-none'); ?>" style="<?php echo ib_html_entity_decode($style); ?>"  data-sort="<?php echo ib_html_entity_decode($i); ?>" data-order="<?php echo ib_html_entity_decode($order); ?>"><?php echo ib_html_entity_decode($content); ?></p>
														<?php }} ?>
													</div>
												<?php } ?>
											</div>
										</div>

									</div>
								</div>
							</div>
						</div>
						</div>
						</div>
