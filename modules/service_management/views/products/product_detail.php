<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php 
$get_base_currency =  get_base_currency();
if($get_base_currency){
	$base_currency_id = $get_base_currency->id;
}else{
	$base_currency_id = 0;
}
?>
<div id="wrapper">
	<div class="content">
		<div class="row">
			<div class="col-md-12">
				<div class="panel_s">
					<div class="panel-body">               
						<div class="clearfix"></div>
						<div class="row">
							<div class="col-md-6">
								
								<h4>
									<?php echo new_html_entity_decode($service->commodity_code.'_'.$service->description); ?>
								</h4>
							</div>
							<div class="col-md-6 pull-right">
								<?php if ( has_permission('service_management', '', 'edit') ) { 
									$edit_url = '';
									if($service->service_type == 'subscriptions'){
										$edit_url = 'service_management/add_edit_subscription/'.$service->id;
									}
									else{
										$edit_url = 'service_management/add_edit_product/'.$service->id;
									}
									?>
									<a href="<?php echo admin_url($edit_url); ?>" class="btn btn-info  pull-right display-block mright5"><?php echo _l('edit'); ?></a>
								<?php } ?>
								<?php if($service->service_type == 'subscriptions'){ ?>
									<a href="<?php echo admin_url('service_management/subscription_services_management'); ?>" class="btn btn-default  pull-right display-block mright5"><?php echo _l('sm_back'); ?></a>
								<?php }else{ ?>
									<a href="<?php echo admin_url('service_management/product_management'); ?>" class="btn btn-default  pull-right display-block mright5"><?php echo _l('sm_back'); ?></a>
								<?php } ?>
							</div>
						</div>


						<hr class="hr-panel-heading" /> 
						<div class="clearfix"></div> 
						<div class="col-md-12">

							<div class="row col-md-12">

								<h4 class="h4-color"><?php echo _l('sm_general_infor'); ?></h4>
								<hr class="hr-color">



								<div class="col-md-7 panel-padding">
									<table class="table border table-striped table-margintop">
										<tbody>

											<tr class="project-overview">
												<td class="bold"><?php echo _l('sm_service_code'); ?></td>
												<td><?php echo new_html_entity_decode($service->commodity_code) ; ?></td>
											</tr>
											<tr class="project-overview">
												<td class="bold"><?php echo _l('sm_service_name'); ?></td>
												<td><?php echo new_html_entity_decode($service->description) ; ?></td>
											</tr>
											
											
											<tr class="project-overview">
												<td class="bold"><?php echo _l('sm_product_category'); ?></td>
												<td><?php echo sm_get_group_name(new_html_entity_decode($service->group_id)) != null ? sm_get_group_name(new_html_entity_decode($service->group_id))->name : '' ; ?></td>
											</tr>
											<?php 
											if($service->service_type == 'subscriptions'){ ?>
												<tr class="project-overview">
													<td class="bold"><?php echo _l('sm_product_cycle'); ?></td>
													<td>
														<?php 
														$subtext = app_format_money($service->subscription_price, $base_currency_id);
														if ($service->subscription_count  == 1) {
															$subtext .= ' / ' .$service->subscription_period;
														} else {
															$subtext .= ' (every ' .$service->subscription_count . ' ' .$service->subscription_period . 's)';
														}
														echo new_html_entity_decode($subtext); ?>
													</td>
												</tr>
											<?php } ?>
										</tbody>
									</table>
								</div>

								<div class="gallery">
									<div class="wrapper-masonry">
										<div id="masonry" class="masonry-layout columns-3">
											<?php if(isset($service_file) && count($service_file) > 0){ ?>
												<?php foreach ($service_file as $key => $value) { ?>

													<?php if(file_exists('modules/warehouse/uploads/item_img/' .$value["rel_id"].'/'.$value["file_name"])){ ?>
														<a  class="images_w_table" href="<?php echo site_url('modules/warehouse/uploads/item_img/'.$value["rel_id"].'/'.$value["file_name"]); ?>"><img class="images_w_table" src="<?php echo site_url('modules/warehouse/uploads/item_img/'.$value["rel_id"].'/'.$value["file_name"]); ?>" alt="<?php echo new_html_entity_decode($value['file_name']) ?>"/></a>

													<?php }elseif(file_exists('modules/purchase/uploads/item_img/' . $value["rel_id"] . '/' . $value["file_name"])) { ?>
														<a  class="images_w_table" href="<?php echo site_url('modules/purchase/uploads/item_img/'.$value["rel_id"].'/'.$value["file_name"]); ?>"><img class="images_w_table" src="<?php echo site_url('modules/purchase/uploads/item_img/'.$value["rel_id"].'/'.$value["file_name"]); ?>" alt="<?php echo new_html_entity_decode($value['file_name']) ?>"/></a>


													<?php }elseif(file_exists('modules/manufacturing/uploads/products/' . $value["rel_id"] . '/' . $value["file_name"])){ ?>
														<a  class="images_w_table" href="<?php echo site_url('modules/manufacturing/uploads/products/'.$value["rel_id"].'/'.$value["file_name"]); ?>"><img class="images_w_table" src="<?php echo site_url('modules/manufacturing/uploads/products/'.$value["rel_id"].'/'.$value["file_name"]); ?>" alt="<?php echo new_html_entity_decode($value['file_name']) ?>"/></a>

													<?php }elseif(file_exists('modules/service_management/uploads/products/' . $value["rel_id"] . '/' . $value["file_name"])){ ?>
														<a  class="images_w_table" href="<?php echo site_url('modules/service_management/uploads/products/'.$value["rel_id"].'/'.$value["file_name"]); ?>"><img class="images_w_table" src="<?php echo site_url('modules/service_management/uploads/products/'.$value["rel_id"].'/'.$value["file_name"]); ?>" alt="<?php echo new_html_entity_decode($value['file_name']) ?>"/></a>

													<?php }else{ ?>
														<a  href="<?php echo site_url('modules/manufacturing/uploads/null_image.jpg'); ?>"><img class="images_w_table" src="<?php echo site_url('modules/manufacturing/uploads/null_image.jpg'); ?>" alt="null_image.jpg"/></a>
													<?php } ?>


												<?php } ?>
											<?php }else{ ?>

												<a  href="<?php echo site_url('modules/warehouse/uploads/nul_image.jpg'); ?>"><img class="images_w_table" src="<?php echo site_url('modules/warehouse/uploads/nul_image.jpg'); ?>" alt="nul_image.jpg"/></a>

											<?php } ?>
											<div class="clear"></div>
										</div>
									</div>
								</div>
								<br>
							</div>
							<?php 
							if($service->service_type != 'subscriptions'){ ?>
								<div class=" row col-md-12">
								<h4 class="h4-color"><?php echo _l('sm_product_cycle'); ?></h4>
								<hr class="hr-color">
							</div>
								<div class="col-md-12">
									<table class="table border table-striped ">
										<thead>
											<th class="th-color"><strong><?php echo _l('sm_item_unit'); ?></strong></th>
											<th class="text-center th-color"><strong><?php echo _l('sm_rate'); ?></strong></th>
											<th class="th-color"><strong><?php echo _l('sm_extend_value'); ?></strong></th>
											<th class="th-color"><strong><?php echo _l('sm_promotion_extended_percent'); ?></strong></th>
											<th class="th-color"><strong><?php echo _l('sm_status_label'); ?></strong></th>
										</thead>
										<tbody>
											<?php foreach($service->item_billing_plan as $item_billing_plan){ ?>
												<tr>
													<td><?php echo new_html_entity_decode($item_billing_plan['unit_value'].$item_billing_plan['unit_type']); ?></td>
													<td class="text-right"><?php echo new_html_entity_decode(app_format_money($item_billing_plan['item_rate'],'')); ?></td>
													<td class="text-right"><?php echo new_html_entity_decode($item_billing_plan['extend_value']); ?></td>
													<td class="text-right"><?php echo new_html_entity_decode($item_billing_plan['promotion_extended_percent']).'%'; ?></td>
													<td><?php echo _l($item_billing_plan['status_cycles']); ?></td>

												</tr>
											<?php } ?>
										</tbody>
									</table>  
								</div>
							<?php } ?>


							<div class=" row ">
								<div class="col-md-12">
									<h4 class="h4-color"><?php echo _l('description'); ?></h4>
									<hr class="hr-color">
									<h5><?php echo new_html_entity_decode($service->long_description) ; ?></h5>
								</div>
							</div>

							<div class=" row ">
								<div class="col-md-12">
									<h4 class="h4-color"><?php echo _l('sm_service_policy'); ?></h4>
									<hr class="hr-color">
									<h5><?php echo new_html_entity_decode($service->service_policy) ; ?></h5>
								</div>
							</div>
							



						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<?php echo form_close(); ?>


<?php echo form_hidden('commodity_id'); ?>
<?php echo form_hidden('parent_item_filter', 'false'); ?>


<?php init_tail(); ?>
<?php require 'modules/service_management/assets/js/products/product_detail.php';?>


</body>
</html>

