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
    'order_id',
	'invoice_id',
	'item_name',
	'billing_plan_value',
	'quantity',
	'sub_total',
	'tax_id',
	'discount_money',
	'total_after_discount',
	'start_date',
	'status',
	'1',
];
$sIndexColumn = 'id';
$sTable = db_prefix() . 'sm_service_details';

$where = [];
$join = [];

$client_filter = $this->ci->input->post('client_filter');
$order_filter = $this->ci->input->post('order_filter');
$product_filter = $this->ci->input->post('product_filter');
$service_status_filter = $this->ci->input->post('service_status_filter');
$order_id_filter = $this->ci->input->post('order_id');


if (isset($client_filter)) {
	$where_client_ft = '';
	foreach ($client_filter as $client_id) {
		if ($client_id != '') {
			if ($where_client_ft == '') {
				$where_client_ft .= 'AND ('.db_prefix().'sm_service_details.client_id = "' . $client_id . '"';
			} else {
				$where_client_ft .= 'or '.db_prefix().'sm_service_details.client_id = "' . $client_id . '"';
			}
		}
	}
	if ($where_client_ft != '') {
		$where_client_ft .= ')';
		array_push($where, $where_client_ft);
	}
}

if (isset($order_filter)) {
	$where_order_ft = '';
	foreach ($order_filter as $order_id) {
		if ($order_id != '') {
			if ($where_order_ft == '') {
				$where_order_ft .= 'AND ('.db_prefix().'sm_service_details.order_id = "' . $order_id . '"';
			} else {
				$where_order_ft .= 'or '.db_prefix().'sm_service_details.order_id = "' . $order_id . '"';
			}
		}
	}
	if ($where_order_ft != '') {
		$where_order_ft .= ')';
		array_push($where, $where_order_ft);
	}
}

if (isset($product_filter)) {
	$where_product_ft = '';
	foreach ($product_filter as $item_id) {
		if ($item_id != '') {
			if ($where_product_ft == '') {
				$where_product_ft .= 'AND ('.db_prefix().'sm_service_details.item_id = "' . $item_id . '"';
			} else {
				$where_product_ft .= 'or '.db_prefix().'sm_service_details.item_id = "' . $item_id . '"';
			}
		}
	}
	if ($where_product_ft != '') {
		$where_product_ft .= ')';
		array_push($where, $where_product_ft);
	}
}

if (isset($service_status_filter)) {
	$where_status_ft = '';
	foreach ($service_status_filter as $status) {
		if ($status != '') {
			if ($where_status_ft == '') {
				$where_status_ft .= 'AND ('.db_prefix().'sm_service_details.status = "' . $status . '"';
			} else {
				$where_status_ft .= 'or '.db_prefix().'sm_service_details.status = "' . $status . '"';
			}
		}
	}
	if ($where_status_ft != '') {
		$where_status_ft .= ')';
		array_push($where, $where_status_ft);
	}
}

if(isset($order_id_filter)){
	$where[] = 'AND '.db_prefix().'sm_service_details.order_id = '.$order_id_filter;
}


$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, ['billing_plan_type', 'billing_plan_rate', 'expiration_date', 'billing_plan_value', 'order_id', 'tax_rate', 'tax_name']);

$output = $result['output'];
$rResult = $result['rResult'];


foreach ($rResult as $aRow) {
	$row = [];
	for ($i = 0; $i < count($aColumns); $i++) {


		if ($aColumns[$i] == 'id') {
            $_data =  $aRow['id'] ;
		}elseif ($aColumns[$i] == 'order_id') {

			$name = '<a href="' . admin_url('service_management/order_detail/' . $aRow['order_id'] ).'" >' . sm_order_code($aRow['order_id']) . '</a>';

			$name .= '<div class="row-options">';
			$name .= '<a href="' . admin_url('service_management/order_detail/' . $aRow['order_id'] ).'" >' . _l('view') . '</a>';

            $name .= '</div>';

            $_data = $name;
		}elseif($aColumns[$i] == 'client_id') {
			$_data = get_company_name($aRow['client_id']);

		}elseif($aColumns[$i] == 'invoice_id') {
			$_data = '<a href="' . admin_url('invoices#' . $aRow['invoice_id'] ).'" >' . format_invoice_number($aRow['invoice_id']) . '</a>';

		}elseif($aColumns[$i] == 'item_name') {
			$_data = $aRow['item_name'];

		}elseif($aColumns[$i] == 'billing_plan_value') {
			$_data = app_format_money((float)$aRow['billing_plan_rate'], $base_currency_id).' ('. $aRow['billing_plan_value'].' '. _l($aRow['billing_plan_type']) . ')';

		}elseif($aColumns[$i] == 'quantity') {
			$_data = app_format_money((float)$aRow['quantity'], $base_currency_id);

		}elseif($aColumns[$i] == 'sub_total') {
			$_data = app_format_money((float)$aRow['sub_total'], $base_currency_id);
			
		}elseif($aColumns[$i] == 'tax_id') {
			$_data = sm_render_taxes_html(sm_convert_item_taxes($aRow['tax_id'], $aRow['tax_rate'], $aRow['tax_name']), 15);
			
		}elseif($aColumns[$i] == 'discount_money') {
			$_data = app_format_money((float)$aRow['discount_money'], $base_currency_id);
			
		}elseif($aColumns[$i] == 'total_after_discount') {
			$_data = app_format_money((float)$aRow['total_after_discount'], $base_currency_id);
			
		}elseif($aColumns[$i] == 'start_date') {
			$option = '';
			$option .= _dt($aRow['start_date']);
			if($aRow['expiration_date'] != null){
			$option .= ' - '. _dt($aRow['expiration_date']);

			}
			$_data = $option;
			
		}elseif($aColumns[$i] == 'status') {
			$_data = render_order_status_html($aRow['id'], 'services', $aRow['status']);
		}elseif($aColumns[$i] == '1'){
			$option = '';
			$allow_renewal_before_day = 1;
			if($aRow['billing_plan_type'] == 'day'){
				$allow_renewal_before_day = 1;
			}elseif($aRow['billing_plan_type'] == 'month'){
				$allow_renewal_before_day = 3;
			}elseif($aRow['billing_plan_type'] == 'year'){
				$allow_renewal_before_day = 30;
			}

			if(($aRow['status'] == 'expired' || (strtotime('+'.(int)$allow_renewal_before_day.' days', strtotime(date('Y-m-d H:i:s'))) >= strtotime($aRow['expiration_date']))) && ($aRow['status'] != 'complete') ){

				if((has_permission('service_management', '', 'edit') || has_permission('service_management', '', 'create') || is_admin())){
					$option .='<a href="'. admin_url('service_management/renewal_service/'.$aRow['id']).'"class="btn btn-sm btn-success text-right mright5">'._l("sm_renewal_service").'</a>';
				}

			}

			$_data = $option;
		}else{
			$_data = '';
		}

		$row[] = $_data;

	}
	$output['aaData'][] = $row;
}

