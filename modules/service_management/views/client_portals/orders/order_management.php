<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php hooks()->do_action('app_customers_portal_head'); ?>
<?php $this->load->model('subscriptions_model'); ?>

<div class="panel_s section-heading section-invoices">
	<div class="panel-body">
		<div class="col-md-6">

			<h4 class="no-margin section-text"><?php echo _l('sm_order_management'); ?></h4>
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
					<a href="<?php echo site_url('service_management/service_management_client/products_service_managements') ?>"><?php echo _l('sm_products_services'); ?></a>
				</li>
				<li class="customers-nav-item-services">
					<a href="<?php echo site_url('service_management/service_management_client/service_managements') ?>"><?php echo _l('sm_services_management'); ?></a>
				</li>
				<li class="customers-nav-item-orders">
					<a href="<?php echo site_url('service_management/service_management_client/order_managements') ?>" class="a_active" ><strong><?php echo _l('sm_order_management'); ?></strong></a>
				</li>
				<li class="customers-nav-item-contracts">
					<a href="<?php echo site_url('service_management/service_management_client/contract_managements') ?>" ><?php echo _l('sm_contracts'); ?></a>
				</li>
				

			</ul>
		</div>
	</div>
</div>

<div class="panel_s">
	<div class="panel-body">

		<hr />
		<table class="table dt-table table-invoices" data-order-col="1" data-order-type="desc">
			<thead>
				<tr>
					<th class="th-invoice-number hide"><?php echo _l('id'); ?></th>
					<th class="th-invoice-number"><?php echo _l('sm_order_number'); ?></th>
					<th class="th-invoice-number"><?php echo _l('client'); ?></th>
					<th class="th-invoice-number"><?php echo _l('sm_amount'); ?></th>
					<th class="th-invoice-number"><?php echo _l('sm_total_tax'); ?></th>
					<th class="th-invoice-number"><?php echo _l('sm_date'); ?></th>
					<th class="th-invoice-number"><?php echo _l('sm_status'); ?></th>
					<th class="th-invoice-number"><?php echo _l('sm_invoice'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach($client_orders as $order){ ?>
					<tr>
						<td class="hide" data-order="<?php echo new_html_entity_decode($order['id']); ?>"><?php echo new_html_entity_decode($order['id']); ?></td>
						<td data-order="<?php echo new_html_entity_decode($order['order_code']); ?>">
							<?php if(!is_numeric($order['subscription_id']) || $order['subscription_id'] == 0){ ?>
								<a href="<?php echo site_url('service_management/service_management_client/order_detail/'.$order['id']) ?>"><?php echo new_html_entity_decode($order['order_code']); ?></a>
							<?php }else{ ?>
								<?php
									$hash = $this->subscriptions_model->get_by_id($order['subscription_id'])->hash;

								 ?>

								<a href="<?php echo site_url('subscription/'.$hash) ?>"><?php echo new_html_entity_decode($order['order_code']); ?></a>
							<?php } ?>
						</td>
						<td data-order="<?php echo new_html_entity_decode($order['client_id']); ?>"><?php echo get_company_name($order['client_id']); ?></td>
						<td data-order="<?php echo new_html_entity_decode($order['total']); ?>">
							<?php if(!is_numeric($order['subscription_id']) || $order['subscription_id'] == 0){ ?>
								<?php echo app_format_money((float)$order['total'], $base_currency_id); ?>
							<?php }else{ ?>
								<?php 
									$product = $this->service_management_model->get_product($order['product_id']);

									$subtext = '';
									if($product){

										$subtext = app_format_money( $product->subscription_price, $base_currency_id);

								        if ($product->subscription_count  == 1) {
								           $subtext .= ' / ' . $product->subscription_period;
								        } else {
								           $subtext .= ' (every ' . $product->subscription_count . ' ' . $product->subscription_period . 's)';
								        }

									}

									echo new_html_entity_decode($subtext); 
								?>
							<?php } ?>	
						</td>
						<td data-order="<?php echo new_html_entity_decode($order['total_tax']); ?>"><?php echo app_format_money((float)$order['total_tax'], $base_currency_id); ?></td>
						<td data-order="<?php echo new_html_entity_decode($order['datecreated']); ?>"><?php echo _dt($order['datecreated']); ?></td>
						<td data-order="<?php echo new_html_entity_decode($order['status']); ?>"><?php echo render_order_status_html($order['id'], 'order', $order['status']); ?></td>

						<?php 
						$option = '';
						if(!is_numeric($order['subscription_id']) || $order['subscription_id'] == 0){
							if($order['invoice_id'] == 0 && ($order['status'] == 'complete' || $order['status'] == 'confirm')){

								$option .= '<a href="'. site_url('service_management/service_management_client/create_invoice_from_order/'.$order['id']).'" class="btn btn-success text-right mright5">'. _l('sm_create_invoice') .'</a>';

							}elseif($order['invoice_id'] != 0){
								$option .= '<a href="'.site_url('invoice/'.$order['invoice_id'].'/'.sm_get_invoice_hash($order['invoice_id'])).'" class="btn btn-primary btn-icon" data-original-title="View Invoice" data-toggle="tooltip" data-placement="top">
								<i class="fa fa-eye"></i>
								</a>';
							}
						}

						$_data = $option;
						?>
						<td data-order="<?php echo new_html_entity_decode($order['status']); ?>"><?php echo new_html_entity_decode($option); ?></td>

					</tr>
				<?php } ?>
			</tbody>
		</table>
	</div>
</div>
<?php hooks()->do_action('app_customers_portal_footer'); ?>

