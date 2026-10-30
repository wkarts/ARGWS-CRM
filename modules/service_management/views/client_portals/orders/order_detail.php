<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php hooks()->do_action('app_customers_portal_head'); ?>

<div class="panel_s section-heading section-invoices">
	<div class="panel-body">
		<div class="col-md-6">

			<h4 class="no-margin section-text"><?php echo _l('sm_order_management'); ?></h4>
			<?php if(has_contact_permission('invoices')){ ?>
				<a href="<?php echo site_url('clients/statement'); ?>" class="view-account-statement"><?php echo _l('sm_view_products_services'); ?></a>
			<?php } ?>
		</div>

		<div class="col-md-6">
			<ul class="nav navbar-nav navbar-right">
				
				<li class="customers-nav-item-products_services">
					<a href="<?php echo site_url('service_management/service_management_client/products_service_managements') ?>"><?php echo _l('sm_products_services'); ?></a>
				</li>
				<li class="customers-nav-item-services">
					<a href="<?php echo site_url('service_management/service_management_client/service_managements') ?>"><?php echo _l('sm_services_management'); ?></a>
				</li>
				<li class="customers-nav-item-orders">
					<a href="<?php echo site_url('service_management/service_management_client/order_managements') ?>" class="a_active"><strong><?php echo _l('sm_order_management'); ?></strong></a>
				</li>
				<li class="customers-nav-item-contracts">
					<a href="<?php echo site_url('service_management/service_management_client/contract_managements') ?>"><?php echo _l('sm_contracts'); ?></a>
				</li>

			</ul>
		</div>
	</div>
</div>

<div class="panel_s">
	<div class="panel-body">
		<h4 class="invoice-html-status mtop7">
			<?php echo render_order_status_html($order->id, 'order', $order->status); ?>
		</h4>

		<div class="tab-content">
			<div role="tabpanel" class="tab-pane ptop10 active" id="tab_estimate">

				<div id="estimate-preview">
					<div class="row mtop10">
						<div class="col-md-3">
						</div>
						<div class="col-md-9 _buttons">
							<div class="visible-xs">
								<div class="mtop10"></div>
							</div>
							<div class="pull-right">
								<?php if((has_permission('service_management', '', 'edit') || is_admin())){
									if($order->status == 'draft'){ ?>
										<a href="<?php echo admin_url('service_management/add_edit_order/'.$order->id); ?>" data-toggle="tooltip" title="<?php echo _l('edit'); ?>" class="btn btn-default btn-with-tooltip" data-placement="bottom"><i class="fa-regular fa-pen-to-square"></i></a>
									<?php } ?>

								<?php } ?>
								<div class="btn-group hide">
									<a href="#" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fa fa-file-pdf-o"></i><?php if(is_mobile()){echo ' PDF';} ?> <span class="caret"></span></a>
									<ul class="dropdown-menu dropdown-menu-right">
										<li class="hidden-xs"><a href="<?php echo admin_url('warehouse/packing_list_pdf/'.$order->id.'?output_type=I'); ?>"><?php echo _l('view_pdf'); ?></a></li>
										<li class="hidden-xs"><a href="<?php echo admin_url('warehouse/packing_list_pdf/'.$order->id.'?output_type=I'); ?>" target="_blank"><?php echo _l('view_pdf_in_new_window'); ?></a></li>
										<li><a href="<?php echo admin_url('warehouse/packing_list_pdf/'.$order->id); ?>"><?php echo _l('download'); ?></a></li>
										<li>
											<a href="<?php echo admin_url('warehouse/packing_list_pdf/'.$order->id.'?print=true'); ?>" target="_blank">
												<?php echo _l('print'); ?>
											</a>
										</li>
									</ul>
								</div>
							</div>
						</div>
					</div>
					<hr />
					<div class="row">
						<div class="col-md-6">
							<h4 class="bold">
								<span id="invoice-number">
									<?php echo new_html_entity_decode($order->order_code); ?>
								</span>
							</h4>
							<address>
								<?php echo format_organization_info(); ?>
							</address>
							<?php if($order->invoice_id != 0){ ?>
								<p class="no-mbot">
									<span class="bold">
										<?php echo _l('sm_invoice'); ?>
										<a href="<?php echo site_url('invoice/'.$order->invoice_id.'/'.sm_get_invoice_hash($order->invoice_id)) ?>" ><?php echo format_invoice_number($order->invoice_id); ?></a>
									</span>
									<h5 class="bold">
									</h5>
								</p>
							<?php } ?>
						</div>
						<div class="col-sm-6 text-right">
							<span class="bold"><?php echo _l('invoice_bill_to'); ?>:</span>
							<address>
								<?php echo format_customer_info($order, 'invoice', 'billing', true); ?>
							</address>
							<span class="bold"><?php echo _l('ship_to'); ?>:</span>
							<address>
								<?php echo format_customer_info($order, 'invoice', 'shipping'); ?>
							</address>
							<p class="no-mbot">
								<span class="bold">
									<?php echo _l('sm_date_created'); ?>
								</span>
								<?php echo _dt($order->datecreated); ?>
							</p>
						</div>
					</div>

					<div class="horizontal-scrollable-tabs preview-tabs-top">
						<div class="horizontal-tabs">
							<ul class="nav nav-tabs nav-tabs-horizontal mbot15" role="tablist">
								<li role="presentation" class="active">
									<a href="#order_detail" aria-controls="order_detail" role="tab" data-toggle="tab">
										<?php echo _l('sm_order_detail'); ?>
									</a>
								</li>
								<li role="presentation">
									<a href="#service_details" aria-controls="service_details" role="tab" data-toggle="tab">
										<?php echo _l('sm_service_details'); ?>
									</a>
								</li>
							</ul>
						</div>
					</div>

					<div class="tab-content">
						<div role="tabpanel" class="tab-pane ptop10 active" id="order_detail">

							<div class="row">
								<div class="col-md-12">
									<div class="table-responsive">
										<table class="table items items-preview estimate-items-preview" data-type="estimate">
											<thead>
												<tr>
													<th align="center">#</th>
													<th  colspan="1"><?php echo _l('sm_service_name') ?></th>
													<th align="right" colspan="1"><?php echo _l('sm_billing_unit') ?></th>
													<th align="right" colspan="1"><?php echo _l('item_quantity_placeholder') ?></th>
													<th align="right" colspan="1"><?php echo _l('rate') ?></th>
													<th align="right" colspan="1"><?php echo _l('invoice_table_tax_heading') ?></th>
													<th align="right" colspan="1"><?php echo _l('invoice_subtotal') ?></th>
													<th align="right" colspan="1"><?php echo _l('sm_discount').'(%)' ?></th>
													<th align="right" colspan="1"><?php echo _l('sm_discount_amount') ?></th>
													<th align="right" colspan="1"><?php echo _l('sm_total_money') ?></th>
												</tr>
											</thead>
											<tbody class="ui-sortable">
												<?php 
												$subtotal = 0 ;
												foreach ($order->order_details as $delivery => $order_detail) {
													$delivery++;
													$discount = (isset($order_detail) ? $order_detail['discount'] : '');
													$discount_money = (isset($order_detail) ? $order_detail['discount_money'] : '');

													$quantity = (isset($order_detail) ? $order_detail['quantity'] : '');
													$unit_price = (isset($order_detail) ? $order_detail['billing_plan_rate'] : '');
													$total_after_discount = (isset($order_detail) ? $order_detail['total_after_discount'] : '');

													$unit_name = '';
													$commodity_name = $order_detail['item_name'];
													?>

													<tr>
														<td ><?php echo new_html_entity_decode($delivery) ?></td>
														<td ><?php echo new_html_entity_decode($commodity_name) ?></td>
														<td class="text-right"><?php echo app_format_money((float)$order_detail['billing_plan_rate'], $base_currency).' ('. $order_detail['billing_plan_value'].' '. _l($order_detail['billing_plan_type']) . ')' ?></td>
														<td class="text-right"><?php echo new_html_entity_decode($quantity).$unit_name ?></td>
														<td class="text-right"><?php echo app_format_money((float)$unit_price,'') ?></td>

														<?php echo  wh_render_taxes_html(wh_convert_item_taxes($order_detail['tax_id'], $order_detail['tax_rate'], $order_detail['tax_name']), 15); ?>
														<td class="text-right"><?php echo app_format_money((float)$order_detail['sub_total'],'') ?></td>
														<td class="text-right"><?php echo app_format_money((float)$discount,'') ?></td>
														<td class="text-right"><?php echo app_format_money((float)$discount_money,'') ?></td>
														<td class="text-right"><?php echo app_format_money((float)$total_after_discount,'') ?></td>
													</tr>
												<?php  } ?>
											</tbody>
										</table>

										<div class="col-md-8 col-md-offset-4">
											<table class="table text-right">
												<tbody>
													<tr id="subtotal">
														<td class="bold"><?php echo _l('invoice_subtotal'); ?></td>
														<td><?php echo app_format_money((float)$order->sub_total, $base_currency); ?></td>
													</tr>
													<?php if(isset($order) && $tax_data['html_currency'] != ''){
														echo new_html_entity_decode($tax_data['html_currency']);
													} ?>

													<tr id="total_discount">
														<?php
														$discount_total = isset($order) ?  $order->discount_total : 0 ;
														?>
														<td class="bold"><?php echo _l('sm_total_discount'); ?></td>
														<td><?php echo app_format_money((float)$discount_total, $base_currency); ?></td>
													</tr>

													<tr id="totalmoney">
														<?php
														$total = isset($order) ?  $order->total : 0 ;
														?>
														<td class="bold"><?php echo _l('sm_total_money'); ?></td>
														<td><?php echo app_format_money((float)$total, $base_currency); ?></td>
													</tr>
												</tbody>
											</table>
										</div>

									</div>
								</div>


							</div>

							<hr />
							<?php if($order->client_note != ''){ ?>
								<div class="col-md-12 row mtop15">
									<p class="bold text-muted"><?php echo _l('client_note'); ?></p>
									<p><?php echo new_html_entity_decode($order->client_note); ?></p>
								</div>
							<?php } ?>
							<?php if($order->admin_note != ''){ ?>
								<div class="col-md-12 row mtop15">
									<p class="bold text-muted"><?php echo _l('admin_note'); ?></p>
									<p><?php echo new_html_entity_decode($order->admin_note); ?></p>
								</div>
							<?php } ?>

						</div>

						<div role="tabpanel" class="tab-pane ptop10" id="service_details">

							<div class="row">
								<div class="col-md-12">
									<div class="table-responsive">
										<table class="table items items-preview estimate-items-preview" data-type="estimate">
											<thead>
												<tr>
													<th align="center" class="hide">#</th>
													<th align="right" colspan="1"><?php echo _l('client') ?></th>
													<th align="right" colspan="1"><?php echo _l('sm_order_number') ?></th>
													<th align="right" colspan="1"><?php echo _l('sm_invoice') ?></th>
													<th align="right" colspan="1"><?php echo _l('sm_service_name') ?></th>
													<th align="right" colspan="1"><?php echo _l('sm_product_cycle') ?></th>
													<th align="right" colspan="1"><?php echo _l('item_quantity_placeholder') ?></th>
													<th align="right" colspan="1"><?php echo _l('invoice_subtotal') ?></th>
													<th align="right" colspan="1"><?php echo _l('invoice_table_tax_heading') ?></th>
													<th align="right" colspan="1"><?php echo _l('sm_discount') ?></th>
													<th align="right" colspan="1"><?php echo _l('sm_total_money') ?></th>
													<th align="right" colspan="1"><?php echo _l('sm_start_date') ?></th>
													<th align="right" colspan="1"><?php echo _l('sm_status') ?></th>
													<th align="right" colspan="1"><?php echo _l('sm_options') ?></th>
												</tr>
											</thead>
											<tbody class="ui-sortable">
												<?php 
												$subtotal = 0 ;
												foreach ($service_details as $key => $service) {
													?>

													<tr>
														<td class="hide" data-order="<?php echo new_html_entity_decode($service['id']); ?>"><?php echo new_html_entity_decode($service['id']); ?></td>
														<td data-order="<?php echo new_html_entity_decode($service['client_id']); ?>"><?php echo get_company_name($service['client_id']); ?></td>
														<td data-order="<?php echo new_html_entity_decode($service['order_id']); ?>"><?php echo sm_order_code($service['order_id']); ?></td>
														<td data-order="<?php echo new_html_entity_decode($service['invoice_id']); ?>"><a href="<?php echo site_url('invoice/'.$service['invoice_id'].'/'.sm_get_invoice_hash($service['invoice_id'])) ?>"><?php echo format_invoice_number($service['invoice_id']); ?></a></td>
														<td data-order="<?php echo new_html_entity_decode($service['item_name']); ?>"><?php echo new_html_entity_decode($service['item_name']); ?></td>
														<td data-order="<?php echo new_html_entity_decode($service['billing_plan_rate']); ?>"><?php echo app_format_money((float)$service['billing_plan_rate'], $base_currency).' ('. $service['billing_plan_value'].' '. _l($service['billing_plan_type']) . ')'; ?></td>
														<td data-order="<?php echo new_html_entity_decode($service['quantity']); ?>"><?php echo app_format_money((float)$service['quantity'], ''); ?></td>
														<td data-order="<?php echo new_html_entity_decode($service['sub_total']); ?>"><?php echo app_format_money((float)$service['sub_total'], $base_currency); ?></td>
														<?php echo sm_render_taxes_html(sm_convert_item_taxes($service['tax_id'], $service['tax_rate'], $service['tax_name']), 15); ?>

														<td data-order="<?php echo new_html_entity_decode($service['discount_money']); ?>"><?php echo app_format_money((float)$service['discount_money'], $base_currency); ?></td>

														<td data-order="<?php echo new_html_entity_decode($service['total_after_discount']); ?>"><?php echo app_format_money((float)$service['total_after_discount'], $base_currency); ?></td>
														<?php 
														$option = '';
														$option .= _dt($service['start_date']);
														if($service['expiration_date'] != null){
															$option .= ' - '. _dt($service['expiration_date']);

														}
														?>
														<td data-order="<?php echo new_html_entity_decode($service['start_date']); ?>"><?php echo new_html_entity_decode($option); ?></td>
														<td data-order="<?php echo new_html_entity_decode($service['status']); ?>"><?php echo render_order_status_html($service['id'], 'services', $service['status']); ?></td>
														<?php 
														$option = '';
														$allow_renewal_before_day = 1;
														if($service['billing_plan_type'] == 'day'){
															$allow_renewal_before_day = 1;
														}elseif($service['billing_plan_type'] == 'month'){
															$allow_renewal_before_day = 3;
														}elseif($service['billing_plan_type'] == 'year'){
															$allow_renewal_before_day = 30;
														}

														if(($service['status'] == 'expired' || (strtotime('+'.(int)$allow_renewal_before_day.' days', strtotime(date('Y-m-d H:i:s'))) >= strtotime($service['expiration_date']))) && ($service['status'] != 'complete') ){

															if(is_primary_contact()){
																$option .='<a href="'. site_url('service_management/service_management_client/renewal_service/'.$service['id']).'"class="btn btn-sm btn-success text-right mright5">'._l("sm_renewal_service").'</a>';
															}

														}

														$_data = $option;
														?>
														<td data-order="<?php echo new_html_entity_decode($service['status']); ?>"><?php echo new_html_entity_decode($option); ?></td>

													</tr>
												<?php  } ?>
											</tbody>
										</table>

									</div>
								</div>


							</div>

						</div>

					</div>


				</div>
			</div>

		</div>

		<div class="modal fade" id="add_action" tabindex="-1" role="dialog">
			<div class="modal-dialog">
				<div class="modal-content">

					<div class="modal-body">
						<p class="bold" id="signatureLabel"><?php echo _l('signature'); ?></p>
						<div class="signature-pad--body">
							<canvas id="signature" height="130" width="550"></canvas>
						</div>
						<input type="text" class="sig-input-style" tabindex="-1" name="signature" id="signatureInput">
						<div class="dispay-block">
							<button type="button" class="btn btn-default btn-xs clear" tabindex="-1" onclick="signature_clear();"><?php echo _l('clear'); ?></button>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('cancel'); ?></button>
						<button onclick="sign_request(<?php echo new_html_entity_decode($order->id); ?>);" autocomplete="off" class="btn btn-success sign_request_class"><?php echo _l('e_signature_sign'); ?></button>
					</div>
				</div>
			</div>
		</div>

	</div>


	<div class="row">
		<div class="col-md-12 mtop15">
			<div class="panel-body bottom-transaction">

				<div class="btn-bottom-toolbar text-right">
				<?php 
				if($order->invoice_id == 0 && ($order->status == 'complete' || $order->status == 'confirm')){ ?>
					<a href="<?php echo site_url('service_management/service_management_client/create_invoice_from_order/'.$order->id); ?>"class="btn btn-success text-right mright5"><?php echo _l('sm_create_invoice'); ?></a>

				<?php }elseif($order->invoice_id != 0){?>

				<?php }	?>

					<a href="<?php echo site_url('service_management/service_management_client/order_managements'); ?>"class="btn btn-default text-right mright5"><?php echo _l('close'); ?></a>
				</div>
			</div>
			<div class="btn-bottom-pusher"></div>
		</div>
	</div>
</div>
