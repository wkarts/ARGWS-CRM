<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php hooks()->do_action('app_customers_portal_head'); ?>

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


		<div class="col-md-3 left_bar">
			<ul class="nav-tabs--vertical nav" role="navigation">
				<li class="head text-center">
					<h5><?php echo _l('category'); ?></h5>
					<a href="<?php echo site_url('service_management/service_management_client/products_service_managements/1/0/0'); ?>" class="view_all"><?php echo _l('all_products'); ?></a> 
				</li>
				<?php 
				$data['title_group'] = $title_group;
				foreach ($group_product as $key => $value) {
						$active = '';
						if($value['id'] == $group_id){
							$active = 'active';
							$data['title_group'] = $value['name'];
						}    		

						?>
						<li class="nav-item <?php echo new_html_entity_decode($active); ?>">
							<a href="<?php echo site_url('service_management/service_management_client/products_service_managements/1/'.$value['id'].'/0'); ?>" class="nav-link">
								<?php echo new_html_entity_decode($value['name']); ?>
							</a>
						</li>
						<?php	
				}
				?>					

			</ul>
		</div>
		<div class="col-md-9 right_bar">

			<div class="row">
				<?php echo form_open(site_url('service_management/service_management_client/search_product/'.$group_id),array('id'=>'invoice-form','class'=>'_transaction_form invoice-form')); ?>
				<div class="col-md-11">
					<input type="text" name="keyword" class="form-control" placeholder="Search for products here ..." value="<?php echo ((isset($keyword) && ($keyword != '')) ? $keyword : '') ?>">

				</div>
				<div class="col-md-1">
					<button type="submit" class="btn btn-info pull-right"><i class="fa fa-search"></i></button>
				</div>
				<?php echo form_close(); ?>

			</div>
			<?php $this->load->view('client_portals/products/includes/list_product_with_page',$data); ?>
			<hr>
		</div>

		<div class="modal fade" id="select_variation" tabindex="-1" role="dialog">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-body">
						<div class="row">
							<div class="col-md-12 title">
								<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
								<h4 class="modal-title">
									<?php echo _l('please_choose_classify'); ?>							
								</h4>
								<hr>
							</div>
						</div>
						<div class="row">
							<div class="col-md-4 image">
								<img src="">
							</div>
							<div class="col-md-8 right-division">
								<div class="row">
									<div class="col-md-12 prices"></div>
								</div>
								<div class="row">
									<div class="col-md-12 content"></div>
									<div class="col-md-12">
										<div class="pb-1 add-cart">
											<input type="hidden" name="parent_id">
											<input type="hidden" name="check_classify" value="1">
											<input type="hidden" name="has_variation" value="">
											<input type="number" name="qty" class="form-control qty" value="1" min="1" data-w_quantity="0">
											<button type="button" class="add_cart btn btn-success" data-id=""><i class="fa fa-shopping-cart"></i><?php echo _l('add_to_cart'); ?></button>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>              
				</div>
			</div>
		</div>
		<input type="hidden" name="msg_classify" value="<?php echo _l('please_choose'); ?>">
		<input type="hidden" name="msg_add" value="<?php echo _l('successfully_added'); ?>">
		<input type="hidden" name="msg_amount_not_available" value="<?php echo _l('sorry_the_number_of_current_products_is_not_enough'); ?>">

	<?php hooks()->do_action('app_customers_portal_footer'); ?>

<?php require 'modules/service_management/assets/js/client_portals/products/product_js.php';?>

