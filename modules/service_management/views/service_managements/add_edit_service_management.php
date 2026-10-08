<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>


<div id="wrapper">
	<div class="content">
		<div class="row">
			<div class="col-md-12" id="small-table">
				<div class="panel_s">
					<?php echo form_open_multipart(admin_url('service_management/add_edit_order'), array('id'=>'add_order')); ?>
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
						<input type="hidden" name="created_type" value="staff">

						<div class="row" >
							<div class="col-md-12">
								<div class="row">

									<div class="col-md-6">
										<?php $order_code = isset($order)? $order->order_code: $order_code; ?>
										<?php echo render_input('order_code', 'sm_order_number',$order_code,'',array('readonly' => 'true')) ?>
									</div>

									<div class="col-md-6">
										<?php $datecreated = isset($order) ? $order->datecreated : date("Y-m-d H:i:s") ;?>
										<?php $disabled=[]; ?>

										<?php echo render_datetime_input('datecreated','sm_date_created', _dt($datecreated), $disabled) ?>

									</div>

									<br>


									<div class="col-md-6">

										<div class="form-group">
											<label for="client_id"><?php echo _l('client'); ?></label>
											<select name="client_id" id="client_id" class="selectpicker" data-live-search="true" data-width="100%" data-none-selected-text="<?php echo _l('ticket_settings_none_assigned'); ?>" <?php if($edit_approval == 'true'){ echo 'disabled';} ; ?>  >
												<option value=""></option>
												<?php foreach($clients as $s) { ?>
													<option value="<?php echo new_html_entity_decode($s['userid']); ?>" <?php if(isset($order) && $order->client_id == $s['userid']){ echo 'selected'; } ?>><?php echo new_html_entity_decode($s['company']); ?></option>
												<?php } ?>
											</select>
										</div>

									</div>
									<div class=" col-md-6">
										<div class="form-group">
											<label for="created_id" class="control-label"><?php echo _l('sale_agent_string'); ?></label>
											<select name="created_id" class="selectpicker" id="created_id" data-width="100%" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>" > 
												<option value=""></option> 
												<?php foreach($staffs as $s){ ?>
													<option value="<?php echo new_html_entity_decode($s['staffid']); ?>" <?php if(isset($order) && $order->created_id == $s['staffid']){ echo 'selected' ;} ?>> <?php echo new_html_entity_decode($s['firstname']).' '.new_html_entity_decode($s['lastname']); ?></option>                  
												<?php }?>
											</select>

										</div>
									</div>


									<div class="col-md-6">
										<div class="row">
											<div class="col-md-12">
												<hr class="hr-10" />
												<a href="#" class="edit_shipping_billing_info" data-toggle="modal" data-target="#billing_and_shipping_details"><i class="fa-regular fa-pen-to-square"></i></a>
												<?php $this->load->view('service_management/service_managements/billing_and_shipping_template'); ?>
											</div>
											<div class="col-md-6">
												<p class="bold"><?php echo _l('invoice_bill_to'); ?></p>
												<address>
													<span class="billing_street">
														<?php $billing_street = (isset($order) ? $order->billing_street : '--'); ?>
														<?php $billing_street = ($billing_street == '' ? '--' :$billing_street); ?>
														<?php echo new_html_entity_decode($billing_street); ?></span><br>
														<span class="billing_city">
															<?php $billing_city = (isset($order) ? $order->billing_city : '--'); ?>
															<?php $billing_city = ($billing_city == '' ? '--' :$billing_city); ?>
															<?php echo new_html_entity_decode($billing_city); ?></span>,
															<span class="billing_state">
																<?php $billing_state = (isset($order) ? $order->billing_state : '--'); ?>
																<?php $billing_state = ($billing_state == '' ? '--' :$billing_state); ?>
																<?php echo new_html_entity_decode($billing_state); ?></span>
																<br/>
																<span class="billing_country">
																	<?php $billing_country = (isset($order) ? get_country_short_name($order->billing_country) : '--'); ?>
																	<?php $billing_country = ($billing_country == '' ? '--' :$billing_country); ?>
																	<?php echo new_html_entity_decode($billing_country); ?></span>,
																	<span class="billing_zip">
																		<?php $billing_zip = (isset($order) ? $order->billing_zip : '--'); ?>
																		<?php $billing_zip = ($billing_zip == '' ? '--' :$billing_zip); ?>
																		<?php echo new_html_entity_decode($billing_zip); ?></span>
																	</address>
																</div>
																<div class="col-md-6">
																	<p class="bold"><?php echo _l('ship_to'); ?></p>
																	<address>
																		<span class="shipping_street">
																			<?php $shipping_street = (isset($order) ? $order->shipping_street : '--'); ?>
																			<?php $shipping_street = ($shipping_street == '' ? '--' :$shipping_street); ?>
																			<?php echo new_html_entity_decode($shipping_street); ?></span><br>
																			<span class="shipping_city">
																				<?php $shipping_city = (isset($order) ? $order->shipping_city : '--'); ?>
																				<?php $shipping_city = ($shipping_city == '' ? '--' :$shipping_city); ?>
																				<?php echo new_html_entity_decode($shipping_city); ?></span>,
																				<span class="shipping_state">
																					<?php $shipping_state = (isset($order) ? $order->shipping_state : '--'); ?>
																					<?php $shipping_state = ($shipping_state == '' ? '--' :$shipping_state); ?>
																					<?php echo new_html_entity_decode($shipping_state); ?></span>
																					<br/>
																					<span class="shipping_country">
																						<?php $shipping_country = (isset($order) ? get_country_short_name($order->shipping_country) : '--'); ?>
																						<?php $shipping_country = ($shipping_country == '' ? '--' :$shipping_country); ?>
																						<?php echo new_html_entity_decode($shipping_country); ?></span>,
																						<span class="shipping_zip">
																							<?php $shipping_zip = (isset($order) ? $order->shipping_zip : '--'); ?>
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

															<div class="panel-body mtop10 invoice-item">
																<div class="row">
																	<div class="col-md-4">
																		<?php $this->load->view('service_management/item_includes/main_item_select'); ?>
																	</div>
																	<div class="col-md-8 text-right hide">
																		<label class="bold mtop10 text-right" data-toggle="tooltip" title="" data-original-title="<?php echo _l('support_barcode_scanner_tooltip'); ?>"><?php echo _l('support_barcode_scanner'); ?>
																		<i class="fa fa-question-circle i_tooltip"></i></label>
																	</div>
																</div>

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


															<div class="row">
																<div class="col-md-12 mtop15">
																	<div class="panel-body bottom-transaction">

																		<?php $client_note = (isset($order) ? $order->client_note : ''); ?>
																		<?php $admin_note = (isset($order) ? $order->admin_note : ''); ?>
																		<?php echo render_textarea('client_note','sm_client_note',$client_note,array(),array(),'mtop15'); ?>
																		<?php echo render_textarea('admin_note','sm_admin_note',$admin_note,array(),array(),'mtop15'); ?>


																		<div class="btn-bottom-toolbar text-right">
																			<a href="<?php echo admin_url('service_management/service_managements'); ?>"class="btn btn-default text-right mright5"><?php echo _l('close'); ?></a>

																			
																			<?php if (is_admin() || has_permission('service_management', '', 'edit') || has_permission('service_management', '', 'create')) { ?>
																				<a href="javascript:void(0)"class="btn btn-info pull-right mright5 add_order" ><?php echo _l('save'); ?></a>

																			<?php } ?>

																		</div>
																	</div>
																	<div class="btn-bottom-pusher"></div>
																</div>
															</div>


														</div>




													</div>
													<?php echo form_close(); ?>
												</div>
											</div>
										</div>

										<div id="modal_wrapper"></div>
										<div id="change_serial_modal_wrapper"></div>

										<?php init_tail(); ?>
										<?php require 'modules/service_management/assets/js/service_managements/add_edit_service_management_js.php';?>
									</body>
									</html>



