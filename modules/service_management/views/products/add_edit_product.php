<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php init_head(); ?>
<div id="wrapper">
	<div class="content">
		<div class="row">
			<?php 
			$id = '';
			$title = '';
			$can_be_product_service = '';

			$product_type ='';
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

				$title .= _l('sm_update_product');
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
			
			}else{
				$title .= _l('sm_add_product');

				$can_be_product_service = 'checked';

				$product_type = 'storable_product';
				$rate = 1.0;
				$barcode = sm_generate_commodity_barcode();
				$sku_code = sm_generate_commodity_barcode();
				$purchase_price = 0.0;

			}

			?>

			<?php echo form_open_multipart(admin_url('service_management/add_edit_product/'.$id), array('id' => 'add_update_product','autocomplete'=>'off')); ?>

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
									
										<input type="hidden" name="id" value="<?php echo new_html_entity_decode($id) ?>">

									<div class="col-md-6">
										<?php echo render_input('commodity_code','sm_service_code',$commodity_code,'text'); ?>
									</div>
									<div class="col-md-6">
										<?php echo render_input('description','sm_service_name',$description,'text'); ?>
									</div>

									<div class="form-group hide">
										<div class="checkbox checkbox-primary">
											<input  type="checkbox" id="can_be_product_service" name="can_be_product_service" value="can_be_product_service" <?php echo new_html_entity_decode($can_be_product_service); ?>>
											<label for="can_be_product_service"><?php echo _l('can_be_product_service'); ?></label>
										</div>
									</div>

								</div>
								<div class="row">
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

								<div class="horizontal-scrollable-tabs preview-tabs-top">
									<div class="scroller arrow-left"><i class="fa fa-angle-left"></i></div>
									<div class="scroller arrow-right"><i class="fa fa-angle-right"></i></div>
									<div class="horizontal-tabs">
										<ul class="nav nav-tabs nav-tabs-horizontal mbot15" role="tablist">
											<li role="presentation" class="active">
												<a href="#general_information" aria-controls="general_information" role="tab" data-toggle="tab">
													<span class="glyphicon glyphicon-align-justify"></span>&nbsp;<?php echo _l('sm_tab_general_information'); ?>
												</a>
											</li>
											<li role="presentation" class="">
												<a href="#tab_variants" aria-controls="tab_variants" role="tab" data-toggle="tab">
													<span class="fa fa-cogs menu-icon"></span>&nbsp;<?php echo _l('sm_product_cycle'); ?>
												</a>
											</li>
										</ul>
									</div>
								</div>
								<br>


								<div class="tab-content active">
									<div role="tabpanel" class="tab-pane active" id="general_information">
										
										<div class="row">
											<div class="col-md-6">

												<?php echo render_select('group_id',$product_group,array('id', 'name'), 'sm_category_name', $group_id, [], [], '', '' , false); ?>   
											</div>
											<div class="col-md-3">
												<div class="form-group">
													<label class="control-label" for="tax"><?php echo _l('tax_1'); ?></label>
													<select class="selectpicker display-block" data-width="100%" name="tax" data-none-selected-text="<?php echo _l('no_tax'); ?>">
														<option value=""></option>
														<?php foreach($taxes as $tax){ ?>
															<?php 
																$tax1_select='';
																if($tax['id'] == $tax1){
																	$tax1_select .='selected';
																}
															 ?>
															<option value="<?php echo new_html_entity_decode($tax['id']); ?>" data-subtext="<?php echo new_html_entity_decode($tax['name']); ?>" <?php echo new_html_entity_decode($tax1_select) ?>><?php echo new_html_entity_decode($tax['taxrate']); ?>%</option>
														<?php } ?>
													</select>
												</div>
											</div>
											<div class="col-md-3">
												<div class="form-group">
													<label class="control-label" for="tax2"><?php echo _l('tax_2'); ?></label>
													<select class="selectpicker display-block" data-width="100%" name="tax2" data-none-selected-text="<?php echo _l('no_tax'); ?>">
														<option value=""></option>
														<?php foreach($taxes as $tax){ ?>
															<?php 
																$tax2_select='';
																if($tax['id'] == $tax2){
																	$tax2_select .='selected';
																}
															 ?>
															<option value="<?php echo new_html_entity_decode($tax['id']); ?>" data-subtext="<?php echo new_html_entity_decode($tax['name']); ?>" <?php echo new_html_entity_decode($tax2_select) ?>><?php echo new_html_entity_decode($tax['taxrate']); ?>%</option>
														<?php } ?>
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
												<?php echo render_textarea('service_policy', 'sm_service_policy', $service_policy, array(), array(), '', 'tinymce'); ?>
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

									<div role="tabpanel" class="tab-pane" id="tab_variants">
										<label class="variant_note"><?php echo _l('sm_Billing_Plan_Units_tooltip'); ?><a href="<?php echo admin_url('service_management/setting?group=unit'); ?>"> <?php echo _l('sm_Setting_Billing_Plan_Units') ?></a></label>
										<div class="row">
											<div class="list_approve">
												<?php 
													echo new_html_entity_decode($this->load->view('products/render_product_cycle'));
												 ?>

											</div>

										</div>
									</div>
									
								</div>
							</div>

						</div>

						<div class="modal-footer">
							
							<a href="<?php echo admin_url('service_management/product_management'); ?>"  class="btn btn-default mr-2 "><?php echo _l('close'); ?></a>
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
	require('modules/service_management/assets/js/products/add_edit_product_js.php');
	?>
</body>
</html>
