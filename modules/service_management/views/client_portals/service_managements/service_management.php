<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php hooks()->do_action('app_customers_portal_head'); ?>


<div class="panel_s section-heading section-invoices">
	<div class="panel-body">
		<div class="col-md-6">

			<h4 class="no-margin section-text"><?php echo _l('sm_service_management'); ?></h4>
			
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
					<a href="<?php echo site_url('service_management/service_management_client/service_managements') ?>" class="a_active"><strong><?php echo _l('sm_services_management'); ?></strong></a>
				</li>
				<li class="customers-nav-item-orders">
					<a href="<?php echo site_url('service_management/service_management_client/order_managements') ?>"><?php echo _l('sm_order_management'); ?></a>
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
		<!-- hooks customer account stats -->
		<?php echo  hooks()->do_action('invoice_add_customer_account', $service_status); ?>
		<!-- hooks customer account stats -->

		<hr />
		<table class="table dt-table table-invoices" data-order-col="1" data-order-type="desc">
			<thead>
				<tr>
					<th class="th-invoice-number hide"><?php echo _l('id'); ?></th>
					<th class="th-invoice-number hide"><?php echo _l('client'); ?></th>
					<th class="th-invoice-number"><?php echo _l('sm_order_number'); ?></th>
					<th class="th-invoice-number"><?php echo _l('sm_invoice'); ?></th>
					<th class="th-invoice-number"><?php echo _l('sm_service_name'); ?></th>
					<th class="th-invoice-number"><?php echo _l('sm_product_cycle'); ?></th>
					<th class="th-invoice-number"><?php echo _l('item_quantity_placeholder'); ?></th>
					<th class="th-invoice-number"><?php echo _l('invoice_subtotal'); ?></th>
					<th class="th-invoice-number"><?php echo _l('invoice_table_tax_heading'); ?></th>
					<th class="th-invoice-number"><?php echo _l('sm_discount_money'); ?></th>
					<th class="th-invoice-number"><?php echo _l('sm_total_money'); ?></th>
					<th class="th-invoice-number"><?php echo _l('sm_start_date'); ?></th>
					<th class="th-invoice-number"><?php echo _l('sm_status'); ?></th>
					<th class="th-invoice-number"><?php echo _l('sm_options'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach($client_services as $service){ ?>
					<tr>
						<td class="hide" data-order="<?php echo new_html_entity_decode($service['id']); ?>"><?php echo new_html_entity_decode($service['id']); ?></td>
						<td  class="hide" data-order="<?php echo new_html_entity_decode($service['client_id']); ?>"><?php echo get_company_name($service['client_id']); ?></td>
						<td data-order="<?php echo new_html_entity_decode($service['order_id']); ?>"><a href="<?php echo site_url('service_management/service_management_client/order_detail/'.$service['order_id']) ?>"><?php echo sm_order_code($service['order_id']); ?></a></td>
						<td data-order="<?php echo new_html_entity_decode($service['invoice_id']); ?>"><a href="<?php echo site_url('invoice/'.$service['invoice_id'].'/'.sm_get_invoice_hash($service['invoice_id'])) ?>"><?php echo format_invoice_number($service['invoice_id']); ?></a></td>
						<td data-order="<?php echo new_html_entity_decode($service['item_name']); ?>"><?php echo new_html_entity_decode($service['item_name']); ?></td>
						<td data-order="<?php echo new_html_entity_decode($service['billing_plan_rate']); ?>"><?php echo app_format_money((float)$service['billing_plan_rate'], $base_currency_id).' ('. $service['billing_plan_value'].' '. _l($service['billing_plan_type']) . ')'; ?></td>
						<td data-order="<?php echo new_html_entity_decode($service['quantity']); ?>"><?php echo app_format_money((float)$service['quantity'], ''); ?></td>
						<td data-order="<?php echo new_html_entity_decode($service['sub_total']); ?>"><?php echo app_format_money((float)$service['sub_total'], $base_currency_id); ?></td>
						<?php echo sm_render_taxes_html(sm_convert_item_taxes($service['tax_id'], $service['tax_rate'], $service['tax_name']), 15); ?>

						<td data-order="<?php echo new_html_entity_decode($service['discount_money']); ?>"><?php echo app_format_money((float)$service['discount_money'], $base_currency_id); ?></td>

						<td data-order="<?php echo new_html_entity_decode($service['total_after_discount']); ?>"><?php echo app_format_money((float)$service['total_after_discount'], $base_currency_id); ?></td>
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
				<?php } ?>
			</tbody>
		</table>
	</div>
</div>
	<?php hooks()->do_action('app_customers_portal_footer'); ?>
