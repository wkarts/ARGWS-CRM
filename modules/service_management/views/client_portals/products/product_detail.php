<?php hooks()->do_action('app_customers_portal_head'); ?>

<?php if($detailt_product != null) { ?>
	<?php $id = $detailt_product->id;
	$currency_name = '';
	if(isset($base_currency)){
		$currency_name = $base_currency->name;
	}
	$array_list_id = [];
	if(isset($_COOKIE['service_id_list'])){
		$list_id = $_COOKIE['service_id_list'];
		if($list_id){
			$array_list_id = new_explode(',',$list_id);
		}
	}


	$user_id = '';
	if(is_client_logged_in()) {
		$user_id = get_client_user_id();
	}

	?>

	<div class="panel_s section-heading section-invoices">
		<div class="panel-body">
			<div class="col-md-6">

				<h4 class="no-margin section-text"><?php echo _l('sm_products_services'); ?></h4>

			</div>

			<div class="col-md-6">
				<ul class="nav navbar-nav navbar-right">
					<li class="customers-nav-item-Insurances-plan">
						<a href="<?php echo site_url('service_management/service_management_client/view_cart') ?>">
							<i class="fa fa-shopping-cart"></i>
							<strong><span class="text-danger service_qty_total"></span></strong>
						</a>
					</li>
					<li class="customers-nav-item-products_services">
						<a href="<?php echo site_url('service_management/service_management_client/products_service_managements') ?>" class="a_active"><strong><?php echo _l('sm_products_services'); ?></strong></a>
					</li>
					<li class="customers-nav-item-services">
						<a href="<?php echo site_url('service_management/service_management_client/service_managements') ?>"><?php echo _l('sm_services_management'); ?></a>
					</li>
					<li class="customers-nav-item-orders">
						<a href="<?php echo site_url('service_management/service_management_client/order_managements') ?>" ><?php echo _l('sm_order_management'); ?></a>
					</li>
					<li class="customers-nav-item-contracts">
						<a href="<?php echo site_url('service_management/service_management_client/contract_managements') ?>" ><?php echo _l('sm_contracts'); ?></a>
					</li>


				</ul>
			</div>
		</div>
	</div>

	<div class="wrapper row" id="select_variation">
		<input type="hidden" name="parent_id" value="<?php echo htmlentities($id); ?>">
		<input type="hidden" name="id" value="<?php echo htmlentities($id); ?>">
		<div class="preview col-md-6">						
			<div class="preview-pic tab-content">
				<?php 
				$date = date('Y-m-d');
				$html_listimage = '';
				$active = 'active';
				$list_filename = $this->service_management_model->sm_get_all_image_file_name($id);
				foreach ($list_filename as $key => $value) {
					$is_image_exist = false;
					if (file_exists('modules/warehouse/uploads/item_img/' . $id . '/' . $value["file_name"])) {
						$is_image_exist = true;					
					}elseif(file_exists('modules/purchase/uploads/item_img/'. $id . '/' . $value["file_name"])) {
						$is_image_exist = true;					
					}elseif(file_exists('modules/manufacturing/uploads/products/'. $id . '/' . $value["file_name"])) {
						$is_image_exist = true;					
					}elseif(file_exists('modules/service_management/uploads/products/'. $id . '/' . $value["file_name"])) {
						$is_image_exist = true;					
					}

					if($is_image_exist == true){

						?>
						<div class="contain_image tab-pane <?php echo new_html_entity_decode($active); ?>" id="pic-<?php echo new_html_entity_decode($key); ?>">
							<img src="<?php echo sm_check_image_items($id, $value['file_name']); ?>" />
						</div>
						<?php
						$active = '';
						$html_listimage.='<li class="'.new_html_entity_decode($active).'"><a data-target="#pic-'.new_html_entity_decode($key).'" data-toggle="tab"><img src="'.sm_check_image_items($id, $value['file_name']).'" /></a></li>';
					} 
				}

				if($html_listimage == ''){ 
					$active = 'active';
					$file_path  = 'modules/service_management/uploads/no_image.jpg';

					?>
					<div class="contain_image tab-pane <?php echo new_html_entity_decode($active); ?>" id="pic-<?php echo new_html_entity_decode(0); ?>">
						<img src="<?php echo site_url($file_path); ?>" />
					</div>
					<?php
					$html_listimage.='<li class="'.new_html_entity_decode($active).'"><a data-target="#pic-'.new_html_entity_decode(0).'" data-toggle="tab"><img src="'.site_url($file_path).'" /></a></li>';
				}

				?>		  	  
			</div>
			<ul class="preview-thumbnail nav nav-tabs">
				<?php echo new_html_entity_decode($html_listimage); ?>
			</ul>		
		</div>
		<div class="details col-md-6">
			<h3 class="product-title"><?php echo new_html_entity_decode($detailt_product->description); ?></h3>
			<h3 class="product-title sub hide"></h3>
			<span class="product-description"><a href="<?php echo site_url('omni_sales/omni_sales_client/index/1/'.$group_id); ?>"><?php echo _l('sm_item_category').': '. $product_category_name; ?></a></span>

			<p class="product-description"><?php echo new_html_entity_decode($detailt_product->long_description); ?></p>
			<p class="product-description sub hide"></p>
			
			<br>
			<div class="col-md-12">
			</div>
			
			<input type="hidden" name="msg_classify" value="<?php echo _l('please_choose'); ?>">
			<input type="hidden" name="msg_add" value="<?php echo _l('successfully_added'); ?>">
			<input type="hidden" name="msg_amount_not_available" value="<?php echo _l('sorry_the_number_of_current_products_is_not_enough'); ?>">
			<br>
			
			<div class="action row">
				<div class="col-md-12 content">
					<?php if($detailt_product->service_type == 'normal'){ ?>
						<?php echo new_html_entity_decode($list_billing_plan); ?>
					<?php }else if($detailt_product->service_type == 'subscriptions'){ ?>
						<div class="price_w">
							<span class="price text-danger">
								<?php $price = $detailt_product->subscription_price;
								if($detailt_product->service_type == 'subscriptions'){
			                        $subtext = app_format_money($price, $currency_name);
			                        if ($detailt_product->subscription_count == 1) {
			                            $subtext .= ' / ' . _l($detailt_product->subscription_period);
			                        } else {
			                            $subtext .= ' / ' . $detailt_product->subscription_count . '' . _l($detailt_product->subscription_period . 's');
			                        }
			                        echo new_html_entity_decode($subtext);
			                    }
		                        ?>
							</span>	
						</div>
					<?php } ?>
				</div>
				<div class="col-md-12">
					<?php if($detailt_product->service_type == 'normal'){ ?>
					<input type="hidden" name="parent_id">
					<input type="hidden" name="check_classify" value="1">
					<input type="hidden" name="has_variation" value="">
					<div class="form-group pull-left">
						<div class="input-group">
							<span class="input-group-addon minus" onclick="change_qty(-1);">
								<i class="fa fa-minus"></i>
							</span>
							<input id="quantity" type="number" name="qty" class="form-control qty text-center" value="1" min="1" data-w_quantity="0" max="100">
							<span class="input-group-addon plus" onclick="change_qty(1);">
								<i class="fa fa-plus"></i>				      
							</span>
						</div>
					</div>
					<?php } ?>

					<?php if($detailt_product->service_type == 'normal'){ ?>
						<button type="button" class="add_cart btn btn-success mleft10" data-id=""><i class="fa fa-shopping-cart"></i><?php echo _l('add_to_cart'); ?></button>
					<?php }else if($detailt_product->service_type == 'subscriptions'){ ?>
						<?= form_open(site_url('service_management/service_management_client/subscribe'), ['class' => 'servicesForm']) ?>
						<?= form_hidden('product_id', html_escape($detailt_product->id)) ?>
                        <?= form_hidden('type', 'subscription') ?>
                        <?php  echo  form_hidden('quantity', '1'); ?>
						<button type="submit" class="subscribe-btn btn btn-success "><i class="fa fa-repeat"></i> <?= _l('subscribe') ?></button>
						<?= form_close() ?>
					<?php } ?>

				</div>
			</div>

		</div>
	</div>
	<hr>
	<div class="col-md-12">	
		<div class="wrap_contents long_descriptions" >
			<?php
			echo new_html_entity_decode($detailt_product->service_policy); 
			?>
		</div>
		<div class="wrap_contents long_descriptions sub hide">
		</div>
		<br>
	</div>

	<?php if(count($product) > 0){ ?>
		<div class="right-detail">
			<div class="line">&#9658;<?php echo _l('suggested_products'); ?></div>
			<div id="slidehind">    
				<div class="frame-slide">
					<div class="frame" id="frameslide">
						<?php 
						foreach ($product as $key => $item) { ?>
							<?php if(count($item['billing_plans']) > 0){  ?>
								<a href="<?php 	echo site_url('service_management/service_management_client/detail/'.$item['id']); ?>">
									
									<img src="<?php echo sm_get_image_items($item['id']); ?>">
									<div class="name"><?php echo new_html_entity_decode($item['description']); ?></div>
								</a>
							<?php } ?>               
						<?php } ?>               
					</div>
				</div>
				<button class="btn btn-primary leftLst" onclick="scroll_slide(-1);"><i class="fa fa-chevron-left"></i></button>
				<button class="btn btn-primary rightLst" onclick="scroll_slide(1);"><i class="fa fa-chevron-right"></i></button>      	
			</div>
		</div>
	<?php } ?>


	<div class="modal fade" id="alert_add" tabindex="-1" role="dialog">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-body">
					<div class="row">
						<div class="col-md-12 alert_content">
							<div class="clearfix"></div>
							<br>
							<br>
							<center class="add_success hide"><h4><?php echo _l('successfully_added'); ?></h4></center>
							<center class="add_error hide"><h4><?php echo _l('sorry_the_number_of_current_products_is_not_enough'); ?></h4></center>
							<br>
							<br>
							<div class="clearfix"></div>
						</div>
					</div>
				</div>              
			</div>
		</div>
	</div>
	<input type="hidden" name="token_name" value="<?php echo new_html_entity_decode($this->security->get_csrf_token_name()); ?>">
	<input type="hidden" name="token_hash" value="<?php echo new_html_entity_decode($this->security->get_csrf_hash()); ?>">
<?php }
else{ ?>
	<br>
	<br>
	<br>
	<br>
	<center>
		<h4>
			<?php echo _l('data_does_not_exist'); ?>			
		</h4>
	</center>

	<br>
	<div class="col-md-12 text-center">
		<a href="javascript:history.back()" class="btn btn-primary">
			<i class="fa fa-long-arrow-left" aria-hidden="true"></i> <?php echo _l('return_to_the_previous_page'); ?></a>
		</div>
	</div>
<?php } ?>
<?php hooks()->do_action('app_customers_portal_footer'); ?>
<?php require 'modules/service_management/assets/js/client_portals/products/product_detail_js.php';?>

