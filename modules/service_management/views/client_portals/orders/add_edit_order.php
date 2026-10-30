<?php hooks()->do_action('head_element_client'); ?>
<div id="wrapper">
	<div class="content">
		<div class="row">
			<?php
			$currency_name = '';
			if(isset($base_currency)){
				$currency_name = $base_currency->name;
			}
			
			if(new_strlen($service_row_template) > 0){
				$cart_empty = 1;
			}else{
				$cart_empty = 0;

			}

			$list_id = [];
			if(isset($_COOKIE['cart_id_list'])){
				$list_id = $_COOKIE['cart_id_list'];
				if($list_id){
				}
			}
			$sub_total = 0;
			$date = date('Y-m-d');
			$user_id = '';
			if(is_client_logged_in()) {
				$user_id = get_client_user_id();
			}
			?>
			<div class="col-md-12">	
				<?php echo form_open_multipart(site_url('service_management/service_management_client/add_order'), array('id'=>'add_order')); ?>
				<div class="panel_s invoice accounting-template fr1 <?php if($cart_empty == 0){ echo 'hide'; } ?>">

					<div class="panel-body">

						<div class="row">
							<div class="col-md-12">
								<h4 class="no-margin font-bold "><i class="fa fa-object-ungroup menu-icon" aria-hidden="true"></i> <?php echo new_html_entity_decode($title); ?></h4>
								<hr>
							</div>
						</div>

						<?php 
						$id = '';
						$current_day = date("Y-m-d");

						if(isset($order)){
							$id = $order->id;
							echo form_hidden('isedit');
						}
						?>
						<input type="hidden" name="id" value="<?php echo new_html_entity_decode($id); ?>">
						<input type="hidden" name="created_type" value="client">

						<div class="row" >
							<div class="col-md-12">
								<div class="row">

									<div class="col-md-6">
										<?php $order_code = $order_code; ?>
										<?php echo render_input('order_code', 'sm_order_number',$order_code,'',array('readonly' => 'true')) ?>
									</div>

									<div class="col-md-6">
										<?php $datecreated = date("Y-m-d H:i:s") ;?>
										<?php $disabled=[]; ?>

										<?php echo render_datetime_input('datecreated','sm_date_created', _dt($datecreated), $disabled) ?>

									</div>

									<br>


									<div class="col-md-6">

										<div class="form-group">
											<label for="client_id"><?php echo _l('client'); ?></label>
											<select name="client_id" id="client_id" class="selectpicker" data-live-search="true" data-width="100%" data-none-selected-text="<?php echo _l('ticket_settings_none_assigned'); ?>" <?php if($edit_approval == 'true'){ echo 'disabled';} ; ?>  >
												<?php foreach($clients as $s) { ?>
													<option value="<?php echo new_html_entity_decode($s['userid']); ?>" <?php if(get_client_user_id() == $s['userid']){ echo 'selected'; } ?>><?php echo new_html_entity_decode($s['company']); ?></option>
												<?php } ?>
											</select>
										</div>

									</div>
									<div class=" col-md-6">
										<div class="form-group">
											<label for="created_id" class="control-label"><?php echo _l('contact'); ?></label>
											<select name="created_id" class="selectpicker" id="created_id" data-width="100%" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>" > 
												<?php foreach($contacts as $s){ ?>
													<option value="<?php echo new_html_entity_decode($s['id']); ?>" <?php if(get_contact_user_id() == $s['id']){ echo 'selected' ;} ?>> <?php echo new_html_entity_decode($s['firstname']).' '.new_html_entity_decode($s['lastname']); ?></option>                  
												<?php }?>
											</select>

										</div>
									</div>


									<div class="col-md-6">
										<div class="row">
											
											<div class="col-md-6">
												<p class="bold"><?php echo _l('invoice_bill_to'); ?></p>

												<input type="hidden" name="billing_street" value="<?php echo new_html_entity_decode($client->billing_street) ?>">
												<input type="hidden" name="billing_city" value="<?php echo new_html_entity_decode($client->billing_city) ?>">
												<input type="hidden" name="billing_state" value="<?php echo new_html_entity_decode($client->billing_state) ?>">
												<input type="hidden" name="billing_country" value="<?php echo new_html_entity_decode($client->billing_country) ?>">
												<input type="hidden" name="billing_zip" value="<?php echo new_html_entity_decode($client->billing_zip) ?>">
												<input type="hidden" name="shipping_street" value="<?php echo new_html_entity_decode($client->shipping_street) ?>">
												<input type="hidden" name="shipping_city" value="<?php echo new_html_entity_decode($client->shipping_city) ?>">
												<input type="hidden" name="shipping_state" value="<?php echo new_html_entity_decode($client->shipping_state) ?>">
												<input type="hidden" name="shipping_country" value="<?php echo new_html_entity_decode($client->shipping_country) ?>">
												<input type="hidden" name="shipping_zip" value="<?php echo new_html_entity_decode($client->shipping_zip) ?>">

												<address>
													<span class="billing_street">
														<?php $billing_street = (isset($client) ? $client->billing_street : '--'); ?>
														<?php $billing_street = ($billing_street == '' ? '--' :$billing_street); ?>
														<?php echo new_html_entity_decode($billing_street); ?></span><br>
														<span class="billing_city">
															<?php $billing_city = (isset($client) ? $client->billing_city : '--'); ?>
															<?php $billing_city = ($billing_city == '' ? '--' :$billing_city); ?>
															<?php echo new_html_entity_decode($billing_city); ?></span>,
															<span class="billing_state">
																<?php $billing_state = (isset($client) ? $client->billing_state : '--'); ?>
																<?php $billing_state = ($billing_state == '' ? '--' :$billing_state); ?>
																<?php echo new_html_entity_decode($billing_state); ?></span>
																<br/>
																<span class="billing_country">
																	<?php $billing_country = (isset($client) ? get_country_short_name($client->billing_country) : '--'); ?>
																	<?php $billing_country = ($billing_country == '' ? '--' :$billing_country); ?>
																	<?php echo new_html_entity_decode($billing_country); ?></span>,
																	<span class="billing_zip">
																		<?php $billing_zip = (isset($client) ? $client->billing_zip : '--'); ?>
																		<?php $billing_zip = ($billing_zip == '' ? '--' :$billing_zip); ?>
																		<?php echo new_html_entity_decode($billing_zip); ?></span>
																	</address>
																</div>
																<div class="col-md-6">
																	<p class="bold"><?php echo _l('ship_to'); ?></p>
																	<address>
																		<span class="shipping_street">
																			<?php $shipping_street = (isset($client) ? $client->shipping_street : '--'); ?>
																			<?php $shipping_street = ($shipping_street == '' ? '--' :$shipping_street); ?>
																			<?php echo new_html_entity_decode($shipping_street); ?></span><br>
																			<span class="shipping_city">
																				<?php $shipping_city = (isset($client) ? $client->shipping_city : '--'); ?>
																				<?php $shipping_city = ($shipping_city == '' ? '--' :$shipping_city); ?>
																				<?php echo new_html_entity_decode($shipping_city); ?></span>,
																				<span class="shipping_state">
																					<?php $shipping_state = (isset($client) ? $client->shipping_state : '--'); ?>
																					<?php $shipping_state = ($shipping_state == '' ? '--' :$shipping_state); ?>
																					<?php echo new_html_entity_decode($shipping_state); ?></span>
																					<br/>
																					<span class="shipping_country">
																						<?php $shipping_country = (isset($client) ? get_country_short_name($client->shipping_country) : '--'); ?>
																						<?php $shipping_country = ($shipping_country == '' ? '--' :$shipping_country); ?>
																						<?php echo new_html_entity_decode($shipping_country); ?></span>,
																						<span class="shipping_zip">
																							<?php $shipping_zip = (isset($client) ? $client->shipping_zip : '--'); ?>
																							<?php $shipping_zip = ($shipping_zip == '' ? '--' :$shipping_zip); ?>
																							<?php echo new_html_entity_decode($shipping_zip); ?></span>
																						</address>
																					</div>
																				</div>
																			</div>






																		</div>


																	</div>

																</div>

															</div>
															<!-- end panel body 1 -->

															<div class="panel-body mtop10">
																<div class="row">

																</div>
																<div class="fr1">
																	<div class="table-responsive s_table ">
																		<table class="table invoice-items-table items table-main-invoice-edit has-calculations no-mtop">
																			<thead>
																				<tr>
																					<th></th>
																					<th width="20%" align="left"><i class="fa fa-exclamation-circle" aria-hidden="true" data-toggle="tooltip" data-title="<?php echo _l('item_description_new_lines_notice'); ?>"></i> <?php echo _l('sm_service_name'); ?></th>
																					<th width="13%" align="right" class="available_quantity"><?php echo _l('sm_product_cycle'); ?></th>
																					<th width="7%" align="right" class="qty"><?php echo _l('item_quantity_placeholder'); ?></th>
																					<th width="12%" align="right"><?php echo _l('invoice_table_tax_heading'); ?></th>
																					<th width="10%" align="right"><?php echo _l('invoice_subtotal'); ?></th>
																					<th width="7%" align="right"><?php echo _l('sm_discount'); ?></th>
																					<th width="10%" align="right"><?php echo _l('sm_discount_amount'); ?></th>
																					<th width="10%" align="right"><?php echo _l('sm_total_money'); ?></th>

																					<th align="center"></th>
																					<th align="center"><i class="fa fa-cog"></i></th>
																				</tr>
																			</thead>
																			<tbody>
																				<?php echo new_html_entity_decode($service_row_template); ?>
																			</tbody>
																		</table>
																	</div>
																	<div class="col-md-8 col-md-offset-4">
																		<table class="table text-right">
																			<tbody>
																				<tr id="subtotal">
																					<td><span class="bold"><?php echo _l('invoice_subtotal'); ?> :</span>
																					</td>
																					<td class="wh-subtotal">
																					</td>
																				</tr>
																				<tr id="total_discount">
																					<td><span class="bold"><?php echo _l('sm_total_discount'); ?> :</span>
																					</td>
																					<td class="wh-total_discount">
																					</td>
																				</tr>

																				<tr id="totalmoney">
																					<td><span class="bold"><?php echo _l('sm_total_money'); ?> :</span>
																					</td>
																					<td class="wh-total">
																					</td>
																				</tr>
																			</tbody>
																		</table>
																	</div>
																	<div id="removed-items"></div>


																</div>

																<?php echo render_textarea('client_note','sm_client_note','',array(),array(),'mtop15'); ?>

															</div>
															<div class="row">
																<div class="col-md-12 mtop15">
																	<div class="panel-body bottom-transaction">
																		
																		<a href="javascript:void(0)" class="btn btn-primary pull-right add_order">
																			<?php echo _l('sm_order'); ?>
																		</a>
																	</div>
																	<div class="btn-bottom-pusher"></div>
																</div>
															</div>
														</div>
														<?php echo form_close(); ?>

														<!-- dont have item in cart -->
														<div class="content fr2 <?php if($cart_empty == 1){ echo 'hide'; } ?>">
															<div class="panel_s">
																<div class="panel-body">
																	<div class="col-md-12 text-center">
																		<h4><?php echo _l('cart_empty'); ?></h4>	   		    		
																	</div>
																	<br>
																	<br>
																	<br>
																	<br>
																	<div class="col-md-12 text-center">
																		<a href="javascript:history.back()" class="btn btn-primary">
																			<i class="fa fa-long-arrow-left" aria-hidden="true"></i> <?php echo _l('return_to_the_previous_page'); ?></a>
																		</div>
																	</div>
																</div>
															</div>
														</div>
													</div>
												</div>
											</div>
											<?php hooks()->do_action('app_customers_portal_footer'); ?>
										<?php require 'modules/service_management/assets/js/client_portals/orders/add_edit_order_js.php';?>
