<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php hooks()->do_action('app_customers_portal_head'); ?>

<div class="panel_s section-heading section-invoices">
	<div class="panel-body">
		<div class="col-md-6">

			<h4 class="no-margin section-text"><?php echo _l('sm_contracts'); ?></h4>
			
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
					<a href="<?php echo site_url('service_management/service_management_client/order_managements') ?>" ><?php echo _l('sm_order_management'); ?></a>
				</li>
				<li class="customers-nav-item-contracts">
					<a href="<?php echo site_url('service_management/service_management_client/contract_managements') ?>" class="a_active"><strong><?php echo _l('sm_contracts'); ?></strong></a>
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
					<th class="th-invoice-number hide"><?php echo _l('the_number_sign'); ?></th>
					<th class="th-invoice-number"><?php echo _l('contract_list_subject'); ?></th>
					<th class="th-invoice-number hide"><?php echo _l('contract_list_client'); ?></th>
					<th class="th-invoice-number"><?php echo _l('contract_types_list_name'); ?></th>
					<th class="th-invoice-number"><?php echo _l('contract_value'); ?></th>
					<th class="th-invoice-number"><?php echo _l('contract_list_start_date'); ?></th>
					<th class="th-invoice-number"><?php echo _l('contract_list_end_date'); ?></th>
					<th class="th-invoice-number"><?php echo _l('project'); ?></th>
					<th class="th-invoice-number"><?php echo _l('sm_order_number'); ?></th>
					<th class="th-invoice-number"><?php echo _l('signature'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach($contracts as $contract){ ?>
					<tr>
						<td class="hide" data-order="<?php echo new_html_entity_decode($contract['id']); ?>"><?php echo new_html_entity_decode($contract['id']); ?></td>
						<td data-order="<?php echo new_html_entity_decode($contract['order_id']); ?>"><a href="<?php echo site_url('service_management/service_management_client/client_contract/' . $contract['id'] . '/' . $contract['hash'])  ?>"><?php echo new_html_entity_decode($contract['subject']); ?></a></td>

						<td data-order="<?php echo new_html_entity_decode($contract['client']); ?>" class="hide"><?php echo get_company_name($contract['client']); ?></td>
						<td data-order="<?php echo new_html_entity_decode($contract['type_name']); ?>"><?php echo new_html_entity_decode($contract['type_name']); ?></td>
						<td data-order="<?php echo new_html_entity_decode($contract['contract_value']); ?>"><?php echo app_format_money((float)$contract['contract_value'], $base_currency); ?></td>
						<td data-order="<?php echo new_html_entity_decode($contract['datestart']); ?>"><?php echo _d($contract['datestart']); ?></td>
						<td data-order="<?php echo new_html_entity_decode($contract['dateend']); ?>"><?php echo _d($contract['dateend']); ?></td>

						<?php if(is_numeric($contract['project_id']) && $contract['project_id'] != 0){ ?>
						<td data-order="<?php echo new_html_entity_decode($contract['project_id']); ?>"><a href="<?php echo site_url('clients/project/'.$contract['project_id']) ?>"><?php echo  get_project_name_by_id($contract['project_id']); ?></a></td>
						<?php }else{ ?>
						<td data-order="<?php echo new_html_entity_decode($contract['project_id']); ?>"></td>
						<?php } ?>


						<?php if(is_numeric($contract['order_id']) && $contract['order_id'] != 0){ ?>
							<td data-order="<?php echo new_html_entity_decode($contract['order_id']); ?>"><a href="<?php echo site_url('service_management/service_management_client/order_detail/'.$contract['order_id']) ?>"><?php echo sm_order_code($contract['order_id']); ?></a></td>
						<?php }else{ ?>
							<td data-order="<?php echo new_html_entity_decode($contract['order_id']); ?>"></td>
						<?php } ?>
						<td data-order="<?php echo new_html_entity_decode($contract['marked_as_signed']); ?>">
							
							<?php 
							if ($contract['marked_as_signed'] == 1) {
								echo '<span class="text-success">' . _l('marked_as_signed') . '</span>';
							} elseif (!empty($contract['signature'])) {
								echo '<span class="text-success">' . _l('is_signed') . '</span>';
							} else {
								echo '<span class="text-muted">' . _l('is_not_signed') . '</span>';
							}
							?>
						</td>

					</tr>
				<?php } ?>
			</tbody>
		</table>
	</div>
</div>
	<?php hooks()->do_action('app_customers_portal_footer'); ?>
