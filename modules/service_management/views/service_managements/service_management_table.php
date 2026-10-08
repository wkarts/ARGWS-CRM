<?php

defined('BASEPATH') or exit('No direct script access allowed');
$this->ci->load->model('service_management/service_management_model');
$get_base_currency =  get_base_currency();
if($get_base_currency){
	$base_currency_id = $get_base_currency->id;
}else{
	$base_currency_id = 0;
}

$aColumns = [
	'id',
	'client_id',
	'total',
	'total_tax',
	'datecreated',
	'status',
	'invoice_id',
];
$sIndexColumn = 'id';
$sTable = db_prefix() . 'sm_orders';

$where = [];

$client_filter = $this->ci->input->post('client_filter');

$join= [];


if (isset($client_filter)) {
	$where_client_ft = '';
	foreach ($client_filter as $client_id) {
		if ($client_id != '') {
			if ($where_client_ft == '') {
				$where_client_ft .= 'AND ('.db_prefix().'sm_orders.client_id = "' . $client_id . '"';
			} else {
				$where_client_ft .= 'or '.db_prefix().'sm_orders.client_id = "' . $client_id . '"';
			}
		}
	}
	if ($where_client_ft != '') {
		$where_client_ft .= ')';
		array_push($where, $where_client_ft);
	}
}


$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, ['order_code', 'subscription_id', 'product_id']);

$output = $result['output'];
$rResult = $result['rResult'];


foreach ($rResult as $aRow) {
	$row = [];
	for ($i = 0; $i < count($aColumns); $i++) {

		if ($aColumns[$i] == 'id') {
			$name = '<a href="' . admin_url('service_management/order_detail/' . $aRow['id'] ).'" >' . $aRow['order_code'] . '</a>';

			$name .= '<div class="row-options">';
			$name .= '<a href="' . admin_url('service_management/order_detail/' . $aRow['id'] ).'" >' . _l('view') . '</a>';

			if((has_permission('service_management', '', 'edit') || is_admin())){
				if($aRow['status'] == 'draft'){

					$name .= ' | <a href="' . admin_url('service_management/add_edit_order/' . $aRow['id'] ).'" >' . _l('edit') . '</a>';
				}
			}

            if ((has_permission('service_management', '', 'delete') || is_admin()) ) {
            	if($aRow['invoice_id'] == 0){
            		$name .= ' | <a href="' . admin_url('service_management/delete_order/' . $aRow['id'] ).'" class="text-danger _delete" >' . _l('delete') . '</a>';
            	}
            }

            $name .= '</div>';

            if(!is_numeric($aRow['subscription_id']) || $aRow['subscription_id'] == 0){
            	$_data = $name;
            }else{
            	$subscription_name = '<a href="' . admin_url('subscriptions/edit/' . $aRow['subscription_id'] ).'" >' . $aRow['order_code'] . ' </a><span class="label label-info"><i class="fa fa-repeat"></i> '._l('subscription_order').'</span>';

            	$subscription_name .= '<div class="row-options">';
				$subscription_name .= '<a href="' . admin_url('subscriptions/edit/' . $aRow['subscription_id'] ).'" >' . _l('view') . '</a>';

	            if ((has_permission('service_management', '', 'delete') || is_admin()) ) {
	            	$subscription_name .= ' | <a href="' . admin_url('service_management/delete_order/' . $aRow['id'] ).'" class="text-danger _delete" >' . _l('delete') . '</a>';
	            }

	            $subscription_name .= '</div>';

            	$_data = $subscription_name;
            }


            
		}elseif($aColumns[$i] == 'client_id') {
			$_data = get_company_name($aRow['client_id']);

		}elseif($aColumns[$i] == 'total') {
			if(!is_numeric($aRow['subscription_id']) || $aRow['subscription_id'] == 0){
				$_data = app_format_money((float)$aRow['total'], $base_currency_id);
			}else{
				$product = $this->ci->service_management_model->get_product($aRow['product_id']);

				$subtext = '';
				if($product){

					$subtext = app_format_money( $product->subscription_price, $base_currency_id);

			        if ($product->subscription_count  == 1) {
			           $subtext .= ' / ' . $product->subscription_period;
			        } else {
			           $subtext .= ' (every ' . $product->subscription_count . ' ' . $product->subscription_period . 's)';
			        }

				}
				$_data =  $subtext;
			}

		}elseif($aColumns[$i] == 'total_tax') {
			$_data = app_format_money((float)$aRow['total_tax'], $base_currency_id);
			
		}elseif($aColumns[$i] == 'datecreated') {
			$_data = _dt($aRow['datecreated']);
			
		}elseif($aColumns[$i] == 'status') {
			$_data = render_order_status_html($aRow['id'], 'order', $aRow['status']);
			
		}elseif($aColumns[$i] == 'invoice_id') {
			$option = '';
			if($aRow['invoice_id'] == 0 && ($aRow['status'] == 'complete' || $aRow['status'] == 'confirm')){

				if((has_permission('service_management', '', 'edit') || has_permission('service_management', '', 'create') || is_admin())){
					$option .='<a href="'. admin_url('service_management/create_invoice_from_order/'.$aRow['id']).'"class="btn btn-sm btn-success text-right mright5">'._l("sm_create_invoice").'</a>';
				}

			}elseif($aRow['invoice_id'] != 0){
					$option .= icon_btn('invoices#' . $aRow['invoice_id'], 'fa-regular fa-eye', 'btn-primary', ['data-original-title' => _l('sm_view_invoice'), 'data-toggle' => 'tooltip', 'data-placement' => 'top']);
			}

			if(!is_numeric($aRow['subscription_id']) || $aRow['subscription_id'] == 0){
				$_data = $option;
			}else{
				$_data = '';
			}
			
		}else{
			$_data = '';
		}

		$row[] = $_data;

	}
	$output['aaData'][] = $row;
}

