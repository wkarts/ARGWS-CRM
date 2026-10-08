<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
	<div class="content">
		<div class="row">
			<div class="col-md-12" id="small-table">
				<div class="panel_s">
					<div class="panel-body">
						<div class="row">
							<div class="col-md-12">
								<h4 class="no-margin h4-color"><i class="fa fa-calendar menu-icon" aria-hidden="true"></i> <?php echo _l($title); ?></h4>
							</div>
						</div>
						<hr class="hr-color">

						<div class="row">
							
							<div class=" col-md-3 ">
								<div class="form-group">
									<select name="client_filter[]" id="client_filter" class="selectpicker" multiple="true" data-actions-box="true"  data-live-search="true" data-width="100%" data-none-selected-text="<?php echo _l('clients'); ?>">

										<?php foreach($clients as $client) { ?>
											<option value="<?php echo new_html_entity_decode($client['userid']); ?>"><?php echo new_html_entity_decode($client['company']); ?></option>
										<?php } ?>
									</select>
								</div>
							</div>

							<div class=" col-md-3 ">
								<div class="form-group">
									<select name="order_filter[]" id="orderilter" class="selectpicker" multiple="true" data-actions-box="true"  data-live-search="true" data-width="100%" data-none-selected-text="<?php echo _l('sm_order'); ?>">

										<?php foreach($orders as $order) { ?>
											<option value="<?php echo new_html_entity_decode($order['id']); ?>"><?php echo new_html_entity_decode($order['order_code']); ?></option>
										<?php } ?>
									</select>
								</div>
							</div>
							<div class=" col-md-3 ">
								<div class="form-group">
									<select name="product_filter[]" id="product_filter" class="selectpicker" multiple="true" data-actions-box="true"  data-live-search="true" data-width="100%" data-none-selected-text="<?php echo _l('sm_service_name'); ?>">

										<?php foreach($products as $product) { ?>
											<option value="<?php echo new_html_entity_decode($product['id']); ?>"><?php echo new_html_entity_decode($product['commodity_code'].' '.$product['description']); ?></option>
										<?php } ?>
									</select>
								</div>
							</div>
							<?php $sm_service_status = sm_service_status(); ?>
							<div class=" col-md-3 ">
								<div class="form-group">
									<select name="service_status_filter[]" id="service_status_filter" class="selectpicker" multiple="true" data-actions-box="true"  data-live-search="true" data-width="100%" data-none-selected-text="<?php echo _l('sm_status'); ?>">

										<?php foreach($sm_service_status as $sm_service_statu) { ?>
											<option value="<?php echo new_html_entity_decode($sm_service_statu['id']); ?>"><?php echo new_html_entity_decode($sm_service_statu['name']); ?></option>
										<?php } ?>
									</select>
								</div>
							</div>
							

						</div>
						<br>

						<div class="row">
							<div class="col-md-12">
								<!-- view/manage -->            
								<div class="modal bulk_actions" id="service_management_bulk_actions" tabindex="-1" role="dialog">
									<div class="modal-dialog" role="document">
										<div class="modal-content">
											<div class="modal-header">
												<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
												<h4 class="modal-title"><?php echo _l('bulk_actions'); ?></h4>
											</div>
											<div class="modal-body">
												<?php if(has_permission('service_management','','delete') || is_admin()){ ?>
													<div class="checkbox checkbox-danger">
														<input type="checkbox" name="mass_delete" id="mass_delete">
														<label for="mass_delete"><?php echo _l('mass_delete'); ?></label>
													</div>

												<?php } ?>
											</div>
											<div class="modal-footer">
												<button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
												<?php if(has_permission('service_management','','delete') || is_admin()){ ?>
													<a href="#" class="btn btn-info" onclick="warehouse_delete_bulk_action(this); return false;"><?php echo _l('confirm'); ?></a>
												<?php } ?>
											</div>
										</div>
									</div>
								</div>

								<!-- update multiple item -->

								<div class="modal export_item hide" id="service_management_export_item" tabindex="-1" role="dialog">
									<div class="modal-dialog" role="document">
										<div class="modal-content">
											<div class="modal-header">
												<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
												<h4 class="modal-title"><?php echo _l('export_item'); ?></h4>
											</div>
											<div class="modal-body">
												<?php if(has_permission('service_management','','delete') || is_admin()){ ?>
													<div class="checkbox checkbox-danger">
														<input type="checkbox" name="mass_delete" id="mass_delete">
														<label for="mass_delete"><?php echo _l('mass_delete'); ?></label>
													</div>

												<?php } ?>
											</div>
											<div class="modal-footer">
												<button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
												<?php if(has_permission('service_management','','delete') || is_admin()){ ?>
													<a href="#" class="btn btn-info" onclick="warehouse_delete_bulk_action(this); return false;"><?php echo _l('confirm'); ?></a>
												<?php } ?>
											</div>
										</div>
									</div>
								</div>

								<!-- print barcode -->      
								<?php echo form_open_multipart(admin_url('service_management/item_print_barcode'), array('id'=>'item_print_barcode')); ?>      
								<div class="modal bulk_actions" id="table_commodity_list_print_barcode" tabindex="-1" role="dialog">
									<div class="modal-dialog" role="document">
										<div class="modal-content">
											<div class="modal-header">
												<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
												<h4 class="modal-title"><?php echo _l('print_barcode'); ?></h4>
											</div>
											<div class="modal-body">
												<?php if(has_permission('service_management','','create') || is_admin()){ ?>

													<div class="row">
														<div class="col-md-6">
															<div class="form-group">
																<div class="radio radio-primary radio-inline" >
																	<input onchange="print_barcode_option(this); return false" type="radio" id="y_opt_1_" name="select_item" value="0" checked >
																	<label for="y_opt_1_"><?php echo _l('select_all'); ?></label>
																</div>
															</div>
														</div>

														<div class="col-md-6">
															<div class="form-group">
																<div class="radio radio-primary radio-inline" >
																	<input onchange="print_barcode_option(this); return false" type="radio" id="y_opt_2_" name="select_item" value="1" >
																	<label for="y_opt_2_"><?php echo _l('sm_select_item'); ?></label>
																</div>
															</div>
														</div>
													</div>     

													<div class="row display-select-item hide ">
														<div class=" col-md-12">
															<div class="form-group">
																<select name="item_select_print_barcode[]" id="item_select_print_barcode" class="selectpicker" data-live-search="true" multiple="true" data-actions-box="true" data-width="100%" data-none-selected-text="<?php echo _l('select_item_print_barcode'); ?>">

																	<?php foreach($commodity_filter as $commodity) { ?>
																		<option value="<?php echo new_html_entity_decode($commodity['id']); ?>"><?php echo new_html_entity_decode($commodity['description']); ?></option>
																	<?php } ?>
																</select>
															</div>
														</div>
													</div>

												<?php } ?>
											</div>
											<div class="modal-footer">
												<button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>

												<?php if(has_permission('service_management','','create') || is_admin()){ ?>

													<button type="submit" class="btn btn-info" ><?php echo _l('confirm'); ?></button>
												<?php } ?>
											</div>
										</div>
									</div>
								</div>
								<?php echo form_close(); ?>

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
									_l('sm_discount_money'),
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
				</div>
				
			</div>
		</div>

	</div>

	<?php init_tail(); ?>
	<?php require 'modules/service_management/assets/js/service_managements/client_services/client_service_manage_js.php';?>
</body>
</html>
