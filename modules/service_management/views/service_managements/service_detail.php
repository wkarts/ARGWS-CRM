<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
	<div class="content">
		<div class="row">
			<div class="col-md-12" id="small-table">
				<div class="panel_s">

					<div class="panel-body">
						<div class="ribbon info"><span><?php echo _l('sm_'.$order->status); ?></span></div>
						
						<div class="horizontal-scrollable-tabs preview-tabs-top">
							<div class="scroller arrow-left"><i class="fa fa-angle-left"></i></div>
							<div class="scroller arrow-right"><i class="fa fa-angle-right"></i></div>
							<div class="horizontal-tabs">
								<ul class="nav nav-tabs nav-tabs-horizontal mbot15" role="tablist">
									<li role="presentation" class="active">
										<a href="#tab_estimate" aria-controls="tab_estimate" role="tab" data-toggle="tab">
											<?php echo _l('sm_order_detail'); ?>
										</a>
									</li>
									
									<li role="presentation" class="hide">
										<a href="#tab_activilog" class="tab_activilog" aria-controls="tab_activilog" role="tab" data-toggle="tab">
											<?php echo _l('wh_activilog'); ?>
										</a>
									</li>
									<li role="presentation" data-toggle="tooltip" data-title="<?php echo _l('toggle_full_view'); ?>" class="tab-separator toggle_view">
										<a href="#" onclick="small_table_full_view(); return false;">
											<i class="fa fa-expand"></i>
										</a>
									</li>

								</ul>
							</div>
						</div>

						<div class="clearfix"></div>
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
														<a href="<?php echo admin_url('invoices#'.$order->invoice_id) ?>" ><?php echo format_invoice_number($order->invoice_id); ?></a>
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
										<div class="scroller arrow-left"><i class="fa fa-angle-left"></i></div>
										<div class="scroller arrow-right"><i class="fa fa-angle-right"></i></div>
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

																		<?php echo  sm_render_taxes_html(sm_convert_item_taxes($order_detail['tax_id'], $order_detail['tax_rate'], $order_detail['tax_name']), 15); ?>
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
											
											<?php 
											$table_data = array(
												_l('id'),
												_l('client'),
												_l('sm_order_number'),
												_l('sm_invoice'),
												_l('sm_service_name'),
												_l('sm_product_cycle'),
												_l('item_quantity_placeholder'),
												_l('invoice_subtotal'),
												_l('invoice_table_tax_heading'),
												_l('sm_discount_amount'),
												_l('sm_total_money'),
												_l('sm_start_date'),
												_l('sm_status'),
												_l('sm_options'),

											);

											render_datatable($table_data,'client_service_table',
												array('customizable-table'),
												array(
													'proposal_sm' => 'proposal_sm',
													'id'=>'table-client_service_table',
													'data-last-order-identifier'=>'client_service_table',
													'data-default-order'=>get_table_last_order('client_service_table'),
												)); ?>
											</div>

										</div>


									</div>
								</div>


								<div role="tabpanel" class="tab-pane" id="tab_activilog">
									<div class="panel_s no-shadow">
										<div class="activity-feed">
											<?php foreach($activity_log as $log){ ?>
												<div class="feed-item">
													<div class="date">
														<span class="text-has-action" data-toggle="tooltip" data-title="<?php echo _dt($log['date']); ?>">
															<?php echo time_ago($log['date']); ?>
														</span>
														<?php if($log['staffid'] == get_staff_user_id() || is_admin() || has_permission('service_management','','delete()')){ ?>
															<a href="#" class="pull-right text-danger" onclick="delete_wh_activitylog(this,<?php echo new_html_entity_decode($log['id']); ?>);return false;"><i class="fa fa fa-times"></i></a>
														<?php } ?>
													</div>
													<div class="text">
														<?php if($log['staffid'] != 0){ ?>
															<a href="<?php echo admin_url('profile/'.$log["staffid"]); ?>">
																<?php echo staff_profile_image($log['staffid'],array('staff-profile-xs-image pull-left mright5'));
																?>
															</a>
															<?php
														}
														$additional_data = '';
														if(!empty($log['additional_data'])){
															$additional_data = unserialize($log['additional_data']);
															echo ($log['staffid'] == 0) ? _l($log['description'],$additional_data) : $log['full_name'] .' - '._l($log['description'],$additional_data);
														} else {
															echo new_html_entity_decode($log['full_name']) . ' - ';
															echo _l($log['description']);
														}
														?>
													</div>

												</div>
											<?php } ?>
										</div>
										<div class="col-md-12">
											<?php echo render_textarea('wh_activity_textarea','','',array('placeholder'=>_l('wh_activilog')),array(),'mtop15'); ?>
											<div class="text-right">
												<button id="wh_enter_activity" class="btn btn-info"><?php echo _l('submit'); ?></button>
											</div>
										</div>
										<div class="clearfix"></div>
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
										<a href="<?php echo admin_url('service_management/service_managements'); ?>"class="btn btn-default text-right mright5"><?php echo _l('close'); ?></a>
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
	<input type="hidden" name="order_id" value="<?php echo new_html_entity_decode($order->id) ?>">


	<?php init_tail(); ?>
	<?php require 'modules/service_management/assets/js/service_managements/service_detail_js.php';?>

</body>
</html>



