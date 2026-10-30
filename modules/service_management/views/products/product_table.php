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
	'1',
	db_prefix() . 'items.id',
	'description',
	'group_id',
	'2',
];
$sIndexColumn = 'id';
$sTable = db_prefix() . 'items';

$where = [];

$commodity_ft = $this->ci->input->post('item_filter');
$product_type_ft = $this->ci->input->post('product_type_filter');
$product_category_ft = $this->ci->input->post('product_category_filter');


$tags_ft = $this->ci->input->post('item_filter');

$join= [];

$where[] = 'AND '.db_prefix().'items.active = 1';

if (isset($commodity_ft)) {
	$where_commodity_ft = '';
	foreach ($commodity_ft as $commodity_id) {
		if ($commodity_id != '') {
			if ($where_commodity_ft == '') {
				$where_commodity_ft .= ' AND ('.db_prefix().'items.id = "' . $commodity_id . '"';
			} else {
				$where_commodity_ft .= ' or '.db_prefix().'items.id = "' . $commodity_id . '"';
			}
		}
	}
	if ($where_commodity_ft != '') {
		$where_commodity_ft .= ')';
		array_push($where, $where_commodity_ft);
	}
}

if (isset($product_type_ft)) {
	$where_product_type_ft = '';
	foreach ($product_type_ft as $product_type) {
		if ($product_type != '') {
			if ($where_product_type_ft == '') {
				$where_product_type_ft .= ' AND ('.db_prefix().'items.product_type = "' . $product_type . '"';
			} else {
				$where_product_type_ft .= ' or '.db_prefix().'items.product_type = "' . $product_type . '"';
			}
		}
	}
	if ($where_product_type_ft != '') {
		$where_product_type_ft .= ')';
		array_push($where, $where_product_type_ft);
	}
}

if (isset($product_category_ft)) {
	$where_product_category_ft = '';
	foreach ($product_category_ft as $product_category) {
		if ($product_category != '') {
			if ($where_product_category_ft == '') {
				$where_product_category_ft .= ' AND ('.db_prefix().'items.group_id = "' . $product_category . '"';
			} else {
				$where_product_category_ft .= ' or '.db_prefix().'items.group_id = "' . $product_category . '"';
			}
		}
	}
	if ($where_product_category_ft != '') {
		$where_product_category_ft .= ')';
		array_push($where, $where_product_category_ft);
	}
}

$where[] = 'AND '.db_prefix().'items.can_be_product_service = "can_be_product_service" AND  '.db_prefix().'items.service_type = "normal"';


$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [db_prefix().'items.id', 'commodity_code']);

$output = $result['output'];
$rResult = $result['rResult'];

$item_plans = [];
$item_billing_plans = $this->ci->service_management_model->get_item_billing_plan_unit();
foreach ($item_billing_plans as $value) {
    $item_plans[$value['item_id']][] = $value;
}

foreach ($rResult as $aRow) {
	$row = [];
	for ($i = 0; $i < count($aColumns); $i++) {

		if (strpos($aColumns[$i], 'as') !== false && !isset($aRow[$aColumns[$i]])) {
			$_data = $aRow[strafter($aColumns[$i], 'as ')];
		} else {
			$_data = $aRow[$aColumns[$i]];
		}


		/*get commodity file*/
		$arr_images = $this->ci->service_management_model->sm_get_attachments_file($aRow['id'], 'commodity_item_file');
		if($aColumns[$i] == db_prefix() . 'items.id'){
			if (count($arr_images) > 0) {

				if(file_exists(SERVICE_MANAGEMENT_PRODUCT_UPLOAD . $arr_images[0]['rel_id'] . '/' . $arr_images[0]['file_name'])) {
					$_data = '<img class="images_w_table" src="' . site_url('modules/service_management/uploads/products/' . $arr_images[0]['rel_id'] . '/' . $arr_images[0]['file_name']) . '" alt="' . $arr_images[0]['file_name'] . '" >';
				}elseif(file_exists('modules/manufacturing/products/' . $arr_images[0]['rel_id'] . '/' . $arr_images[0]['file_name'])) {
					$_data = '<img class="images_w_table" src="' . site_url('modules/service_management/uploads/products/' . $arr_images[0]['rel_id'] . '/' . $arr_images[0]['file_name']) . '" alt="' . $arr_images[0]['file_name'] . '" >';
				}elseif (file_exists('modules/warehouse/uploads/item_img/' . $arr_images[0]['rel_id'] . '/' . $arr_images[0]['file_name'])) {
					$_data = '<img class="images_w_table" src="' . site_url('modules/warehouse/uploads/item_img/' . $arr_images[0]['rel_id'] . '/' . $arr_images[0]['file_name']) . '" alt="' . $arr_images[0]['file_name'] . '" >';
				} elseif (file_exists('modules/purchase/uploads/item_img/'. $arr_images[0]['rel_id'] . '/' . $arr_images[0]['file_name'])) {
					$_data = '<img class="images_w_table" src="' . site_url('modules/purchase/uploads/item_img/' . $arr_images[0]['rel_id'] . '/' . $arr_images[0]['file_name']) . '" alt="' . $arr_images[0]['file_name'] . '" >';
				}else{
					$_data = '<img class="images_w_table" src="' . site_url('modules/service_management/uploads/null_image.jpg') . '" alt="nul_image.jpg">';
				}
			} else {

				$_data = '<img class="images_w_table" src="' . site_url('modules/service_management/uploads/null_image.jpg') . '" alt="nul_image.jpg">';
			}
		}

		if ($aColumns[$i] == 'description') {
			$code = '<a href="' . admin_url('service_management/product_detail/' . $aRow['id']) . '">' . $aRow['commodity_code'].'_'.$aRow['description'] . '</a>';
			$code .= '<div class="row-options">';

			$code .= '<a href="' . admin_url('service_management/product_detail/' . $aRow['id']) . '" >' . _l('view') . '</a>';

			if (has_permission('service_management', '', 'edit') || is_admin()) {
				$code .= ' | <a href="' . admin_url('service_management/add_edit_product/' . $aRow['id']) . '"  >' . _l('edit') . '</a>';
			}
			if (has_permission('service_management', '', 'delete') || is_admin()) {
				$code .= ' | <a href="' . admin_url('service_management/delete_product/' . $aRow['id']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
			}

			$code .= '</div>';

			$_data = $code;

		}elseif($aColumns[$i] == '1'){
			$_data = '<div class="checkbox"><input type="checkbox" value="' . $aRow['id'] . '"><label></label></div>';
		}elseif ($aColumns[$i] == 'unit_id') {
			if ($aRow['unit_id'] != null) {
				$_data = sm_get_unit_name($aRow['unit_id']);
			} else {
				$_data = '';
			}
		} elseif ($aColumns[$i] == 'rate') {
			$_data = app_format_money((float) $aRow['rate'], '');
		} elseif ($aColumns[$i] == 'purchase_price') {
			$_data = app_format_money((float) $aRow['purchase_price'], '');

		} elseif ($aColumns[$i] == 'group_id') {
			$_data = sm_get_group_name($aRow['group_id']) != null ? sm_get_group_name($aRow['group_id'])->name : '';

		} elseif ($aColumns[$i] == '2') {
			$_data ='';
			$arr_billing_plan = [];
			if(isset($item_plans[$aRow['id']])){
				$arr_billing_plan = $item_plans[$aRow['id']];
			}

			$str = '';
			if(count($arr_billing_plan) > 0){
				foreach ($arr_billing_plan as $wh_key => $billing_plan) {
					$str = '';
					if ($billing_plan['unit_id'] != null && $billing_plan['unit_id'] != '0') {

						if($billing_plan['status_cycles'] == 'active'){
							$str .= '<span class="label label-success  tag-id-1"><span class="tag">' . app_format_money((float)$billing_plan['item_rate'], $base_currency_id).' ('. $billing_plan['unit_value'].' '. _l($billing_plan['unit_type']) . ')</span><span class="hide">, </span></span>&nbsp';
						}else{
							$str .= '<span class="label label-warning tag-id-1"><span class="tag">' . app_format_money((float)$billing_plan['item_rate'], $base_currency_id).' ('. $billing_plan['unit_value'].' '. _l($billing_plan['unit_type']) . ')</span><span class="hide">, </span></span>&nbsp';
						}

						$_data .= $str;
						if($wh_key%3 ==0){
							$_data .='<br/>';
						}

					}
				}

			} else {
				$_data = '';
			}

		} 


		$row[] = $_data;

	}
	$output['aaData'][] = $row;
}

