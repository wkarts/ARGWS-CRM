<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php init_head(); ?>
<div id="wrapper">
	<div class="content">
		<div class="row">
			<?php 
			$id = '';
			$can_be_product_service = '';
			$product_type ='';
			$billing_plan ='';
			$sku_code ='';
			$long_description='';
			$service_policy='';
			$commodity_code='';
			$description='';
			$group_id='';
			$tax1='';
			$tax2='';
			$allow_extension_service='checked';
			$type    = 'product_variant';

			if(isset($product)){
				$id    = $product->id;
				$type    = 'product_variant';
				$can_be_product_service = 'checked';
				$product_type = $product->product_type;
				$rate = $product->rate;
				$barcode = $product->commodity_barcode;
				$purchase_price = $product->purchase_price;
				$sku_code = $product->sku_code;
				$long_description = $product->long_description;
				$service_policy = $product->service_policy;
				$description_sale = $product->description_sale;
				$description = $product->description;
				$commodity_code = $product->commodity_code;
				$group_id = $product->group_id;
				$tax1 = $product->tax;
				$tax2 = $product->tax2;
				if($product->allow_extension_service == 'allow'){
					$allow_extension_service = 'checked';
				}else{
					$allow_extension_service = '';

				}
				$billing_plan = $product->stripe_plan_id;
			}else{
				$can_be_product_service = 'checked';
				$product_type = 'storable_product';
				$rate = 1.0;
				$barcode = sm_generate_commodity_barcode();
				$sku_code = sm_generate_commodity_barcode();
				$purchase_price = 0.0;
			}

			?>

			<?php echo form_open_multipart(admin_url('service_management/add_edit_subscription/'.$id), array('id' => 'add_update_product','autocomplete'=>'off')); ?>

			<div class="col-md-12" >
				<div class="panel_s">
					
					<div class="panel-body">
						<div class="row mb-5">
							<div class="col-md-5">
								<h4 class="no-margin"><?php echo new_html_entity_decode($title); ?> 
							</div>
						</div>
						<hr class="hr-color">

						<!-- start tab -->
						<div class="modal-body">
							<div class="tab-content">
								<!-- start general infor -->
								<div class="row">
									<div class="row">
										<?php 
										if(isset($product_error) && $product_error != ''){ ?>
											<div class="col-md-12">
												<div class="alert alert-warning">
													<?php echo new_html_entity_decode($product_error); ?>
												</div>
											</div>
										<?php } ?>
										<div class="col-md-6">
											<input type="hidden" name="id" value="<?php echo new_html_entity_decode($id) ?>">
											<?php echo render_input('commodity_code','sm_service_code',$commodity_code,'text'); ?>
										</div>
										<div class="col-md-6">
											<?php echo render_input('description','sm_subscription_name',$description,'text'); ?>
										</div>

										<div class="form-group hide">
											<div class="checkbox checkbox-primary">
												<input  type="checkbox" id="can_be_product_service" name="can_be_product_service" value="can_be_product_service" <?php echo new_html_entity_decode($can_be_product_service); ?>>
												<label for="can_be_product_service"><?php echo _l('can_be_product_service'); ?></label>
											</div>
										</div>

									</div>
									<div class="row hide">
										<div class="col-md-12">
											<label><?php echo _l('sm_allow_extension_service') ?></label>
											<div class="onoffswitch">
												<input type="checkbox"  name="allow_extension_service" class="onoffswitch-checkbox" id="allow_extension_service" <?php echo new_html_entity_decode($allow_extension_service) ?>>
												<label class="onoffswitch-label" for="allow_extension_service"></label>
											</div>
										</div>
									</div>

									<div class="row">
										<div class="col-md-12">
											<?php if(isset($product) && $type == 'product_variant'){ ?>
												<?php if($product->attributes != null) {
													$array_attributes = json_decode($product->attributes);
													foreach ($array_attributes as $att_key => $att_value) {
														?>
														<button type="button" class="btn btn-sm btn-primary btn_text_tr"><?php echo new_html_entity_decode($att_value->name.' : '.$att_value->option); ?></button>
													<?php }} ?>
												<?php } ?>
											</div>
										</div>

									</div>
									<br>
									<div class="row">
										<div class="tab-content">

											<div class="row">
												<div class="col-md-6">
													<div class="form-group select-placeholder">
														<label for="stripe_plan_id"><small class="req text-danger">* </small><?php echo _l('sm_billing_plan'); ?></label>
														<select id="stripe_plan_id" name="stripe_plan_id" class="selectpicker" data-live-search="true" data-width="100%" data-none-selected-text="<?php echo _l('stripe_subscription_select_plan'); ?>">
															<option value=""></option>
															<?php if (isset($plans->data)) { ?>
																<?php foreach ($plans->data as $plan) {
																	if (!$plan->active) {
																		if (!isset($product)) {
																			continue;
																		}
																		if ($product->stripe_plan_id != $plan->id) {
																			continue;
																		}
																	}

																	$selected = '';
																	if (isset($product) && $product->stripe_plan_id == $plan->id) {
																		$selected = ' selected';
																	}
																	$subtext = app_format_money(strcasecmp($plan->currency, 'JPY') == 0 ? $plan->amount : $plan->amount / 100, strtoupper($plan->currency));
																	if ($plan->interval_count == 1) {
																		$subtext .= ' / ' . $plan->interval;
																	} else {
																		$subtext .= ' (every ' . $plan->interval_count . ' ' . $plan->interval . 's)';
																	} ?>
																	<option value="<?php echo e($plan->id); ?>" data-interval-count="<?php echo e($plan->interval_count); ?>"
																		data-interval="<?php echo e($plan->interval); ?>" data-amount="<?php echo e($plan->amount); ?>"
																		data-subtext="<?php echo e($subtext); ?>" <?php echo e($selected); ?>>
																		<?php
																		if (!empty($plan->nickname)) {
																			echo new_html_entity_decode($plan->nickname);
																		} elseif (isset($plan->product->name)) {
																			echo new_html_entity_decode($plan->product->name);
																		} else {
																			echo '[Plan Name Not Set in Stripe, ID:' . $plan->id . ']';
																		} ?>
																	</option>
																	<?php
																} ?>
															<?php } ?>
														</select>
													</div>
												</div>
												<div class="col-md-6">
													<?php echo render_select('group_id',$product_group,array('id', 'name'), 'sm_group', $group_id, [], [], '', '' , false); ?>   
												</div>
												<div class="col-md-6">
													<div class="form-group">
														<label class="control-label" for="tax"><?php echo _l('tax_1'); ?> (Stripe)</label>
														<select class="selectpicker display-block" data-width="100%" name="tax" data-none-selected-text="<?php echo _l('no_tax'); ?>">
															<option value=""></option>
															<?php foreach ($stripe_tax_rates->data as $tax) {
																if ($tax->inclusive) {
																	continue;
																}
																if (!$tax->active) {
																	if (!isset($product)) {
																		continue;
																	}
																	if ($product->tax != $tax->id) {
																		continue;
																	}
																} ?>
																<option value="<?php echo e($tax->id); ?>"
																	data-subtext="<?php echo !empty($tax->country) ? $tax->country : ''; ?>" <?php if (isset($product) && $product->tax == $tax->id) {
																		echo ' selected';
																	} ?>>
																	<?php echo e($tax->display_name); ?>
																	<?php echo !empty($tax->jurisdiction) ? ' - ' . $tax->jurisdiction . ' ' : ''; ?>
																	(<?php echo e($tax->percentage); ?>%)
																</option>
																<?php
															} ?>
														</select>
													</div>
												</div>
												<div class="col-md-6">
													<div class="form-group">
														<label class="control-label" for="tax2"><?php echo _l('tax_2'); ?></label>
														<select class="selectpicker display-block" data-width="100%" name="tax2" data-none-selected-text="<?php echo _l('no_tax'); ?>">
															<option value=""></option>
															<?php foreach ($stripe_tax_rates->data as $tax) {
																if ($tax->inclusive) {
																	continue;
																}
																if (!$tax->active) {
																	if (!isset($product)) {
																		continue;
																	}
																	if ($product->tax2 != $tax->id) {
																		continue;
																	}
																} ?>
																<option value="<?php echo e($tax->id); ?>"
																	data-subtext="<?php echo !empty($tax->country) ? $tax->country : ''; ?>" <?php if (isset($product) && $product->tax2 == $tax->id) {
																		echo ' selected';
																	} ?>>
																	<?php echo e($tax->display_name); ?>
																	<?php echo !empty($tax->jurisdiction) ? ' - ' . $tax->jurisdiction . ' ' : ''; ?>
																	(<?php echo e($tax->percentage); ?>%)
																</option>
																<?php
															} ?>
														</select>
													</div>
												</div>
											</div>	
											<div class="row hide">
												<div class="col-md-6">
													<?php echo render_input('commodity_barcode','barcode',$barcode,'text'); ?> 
												</div>
												<div class="col-md-6">
													<?php echo render_input('sku_code','sku_code', $sku_code,'text'); ?> 
												</div>
											</div>
											<div class="row">
												<div class="col-md-12">
													<?php echo render_textarea('long_description', 'description', $long_description); ?>
												</div>
											</div>
											<div class="row">
												<div class="col-md-12">
													<?php echo render_textarea('service_policy', 'sm_terms_conditions', $service_policy, array(), array(), '', 'tinymce'); ?>
												</div>
											</div>


											<div class="row">
												<div class="col-md-12">
													<div id="dropzoneDragArea" class="dz-default dz-message">
														<span><?php echo _l('sm_attach_images'); ?></span>
													</div>
													<div class="dropzone-previews"></div>

													<div id="images_old_preview">

														<?php if( isset($product_attachments) && count($product_attachments) > 0){ ?>
															<?php foreach ($product_attachments as $product_attachment) { ?>
																<?php $rel_type = '' ;?>

																<?php if(file_exists('modules/manufacturing/products/' . $product_attachment['rel_id'] . '/' . $product_attachment['file_name'])){ ?>
																	<?php $rel_type = 'manufacturing' ;?>

																<?php }elseif(file_exists('modules/warehouse/uploads/item_img/' . $product_attachment["rel_id"] . '/' . $product_attachment["file_name"])){ ?>
																	<?php $rel_type = 'warehouse' ;?>

																<?php }elseif(file_exists('modules/purchase/uploads/item_img/'. $product_attachment["rel_id"] . '/' . $product_attachment["file_name"])){ ?>

																	<?php $rel_type = 'purchase' ;?>
																<?php } elseif(file_exists(SERVICE_MANAGEMENT_PRODUCT_UPLOAD. $product_attachment["rel_id"] . '/' . $product_attachment["file_name"])){ ?>

																	<?php $rel_type = 'service_management' ;?>
																<?php } ?>

																<?php if($rel_type != ''){ ?>
																	<div class="dz-preview dz-image-preview image_old <?php echo new_html_entity_decode($product_attachment['id']) ?>">
																		<div class="dz-image">
																			<?php if(file_exists('modules/manufacturing/products/' . $product_attachment['rel_id'] . '/' . $product_attachment['file_name'])){ ?>

																				<img class="images_w_table" src="<?php echo site_url('modules/manufacturing/uploads/products/' . $product_attachment['rel_id'] . '/' . $product_attachment['file_name']) . '" alt="' . $product_attachment['file_name'] ?>" >

																			<?php }elseif(file_exists('modules/warehouse/uploads/item_img/' . $product_attachment["rel_id"] . '/' . $product_attachment["file_name"])){ ?>

																				<img class="images_w_table" src="<?php echo site_url('modules/warehouse/uploads/item_img/' . $product_attachment['rel_id'] . '/' . $product_attachment['file_name']) . '" alt="' . $product_attachment['file_name'] ?>" >

																			<?php }elseif(file_exists('modules/purchase/uploads/item_img/'. $product_attachment["rel_id"] . '/' . $product_attachment["file_name"])){ ?>

																				<img class="images_w_table" src="<?php echo site_url('modules/purchase/uploads/item_img/' . $product_attachment['rel_id'] . '/' . $product_attachment['file_name']) . '" alt="' . $product_attachment['file_name'] ?>" >

																			<?php }elseif(file_exists(SERVICE_MANAGEMENT_PRODUCT_UPLOAD. $product_attachment["rel_id"] . '/' . $product_attachment["file_name"])){ ?>

																				<img class="images_w_table" src="<?php echo site_url('modules/service_management/uploads/products/' . $product_attachment['rel_id'] . '/' . $product_attachment['file_name']) . '" alt="' . $product_attachment['file_name'] ?>" >

																			<?php } ?>
																		</div>
																		<div class="dz-error-mark">
																			<a class="dz-remove" data-dz-remove>Remove file</a>
																		</div>
																		<div class="remove_file">
																			<a href="#" class="text-danger" onclick="delete_product_attachment(this,<?php echo new_html_entity_decode($product_attachment['id']); ?>, <?php echo '\''.$rel_type.'\'' ; ?>); return false;"><i class="fa fa fa-times"></i></a>
																		</div>
																	</div>
																<?php } ?>

															<?php } ?>
														<?php } ?>
													</div>
												</div>
											</div>
										</div>
									</div>
								</div>
								<div class="modal-footer">
									<a href="<?php echo admin_url('service_management/subscription_services_management'); ?>"  class="btn btn-default mr-2 "><?php echo _l('close'); ?></a>
									<?php if(has_permission('manufacturing', '', 'create') || has_permission('manufacturing', '', 'edit')){ ?>
										<button type="submit" class="btn btn-info pull-right submit_button"><?php echo _l('submit'); ?></button>
									<?php } ?>
								</div>
							</div>
						</div>
					</div>
					<?php echo form_close(); ?>
				</div>
			</div>
			<div id="box-loading"></div>

			<?php init_tail(); ?>
			<?php 
			require('modules/service_management/assets/js/subscription_services/add_edit_subscription_js.php');
			?>
		</body>
		</html>
