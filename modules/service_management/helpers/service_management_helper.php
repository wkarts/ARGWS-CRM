<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * sm unit type value
 * @return [type] 
 */
function sm_unit_type_value()
{
	$units = [];
	$units[] = [
		'name' => 'day',
		'label' => _l('sm_day'),
	];
	$units[] = [
		'name' => 'month',
		'label' => _l('sm_month'),
	];
	$units[] = [
		'name' => 'year',
		'label' => _l('sm_year'),
	];

	return $units;
}

/**
 * sm extend cycle value
 * @return [type] 
 */
function sm_extend_cycle_value()
{
	$sm_extend_cycle_value = [];
	$sm_extend_cycle_value[] = [
		'name' => '1',
		'label' => _l('1'),
	];
	$sm_extend_cycle_value[] = [
		'name' => '2',
		'label' => _l('2'),
	];
	
	$sm_extend_cycle_value[] = [
		'name' => '3',
		'label' => _l('3'),
	];
	$sm_extend_cycle_value[] = [
		'name' => '4',
		'label' => _l('4'),
	];
	$sm_extend_cycle_value[] = [
		'name' => '5',
		'label' => _l('5'),
	];
	$sm_extend_cycle_value[] = [
		'name' => '6',
		'label' => _l('6'),
	];
	$sm_extend_cycle_value[] = [
		'name' => '7',
		'label' => _l('7'),
	];
	$sm_extend_cycle_value[] = [
		'name' => '8',
		'label' => _l('8'),
	];
	$sm_extend_cycle_value[] = [
		'name' => '9',
		'label' => _l('9'),
	];
	$sm_extend_cycle_value[] = [
		'name' => '10',
		'label' => _l('10'),
	];

	return $sm_extend_cycle_value;
}

/**
 * sm handle product attachments
 * @param  [type] $id 
 * @return [type]     
 */
function sm_handle_product_attachments($id)
{

	if (isset($_FILES['file']) && _perfex_upload_error($_FILES['file']['error'])) {
		header('HTTP/1.0 400 Bad error');
		echo _perfex_upload_error($_FILES['file']['error']);
		die;
	}
	$path = SERVICE_MANAGEMENT_PRODUCT_UPLOAD . $id . '/';
	$CI   = & get_instance();

	if (isset($_FILES['file']['name'])) {

		// Get the temp file path
		$tmpFilePath = $_FILES['file']['tmp_name'];
		// Make sure we have a filepath
		if (!empty($tmpFilePath) && $tmpFilePath != '') {

			_maybe_create_upload_path($path);
			$filename    = $_FILES['file']['name'];
			$newFilePath = $path . $filename;
			// Upload the file into the temp dir
			if (move_uploaded_file($tmpFilePath, $newFilePath)) {

				$attachment   = [];
				$attachment[] = [
					'file_name' => $filename,
					'filetype'  => $_FILES['file']['type'],
				];

				$CI->misc_model->add_attachment_to_database($id, 'commodity_item_file', $attachment);

			}
		}
	}

}


/**
 * sm get taxes
 * @param  string $id 
 * @return [type]     
 */
function sm_get_taxes($id ='')
{
	$CI           = & get_instance();

	if (is_numeric($id)) {
		$CI->db->where('id',$id);

		return $CI->db->get(db_prefix().'taxes')->row();
	}
	$CI->db->order_by('taxrate', 'ASC');
	return $CI->db->get(db_prefix().'taxes')->result_array();

}

/**
 * sm_ajax_on_total_items
 * @return [type] 
 */
function sm_ajax_on_total_items()
{
	$sm_on_total_items = 200;
	return (int)$sm_on_total_items;
}

/**
 * sm convert item taxes
 * @param  [type] $tax      
 * @param  [type] $tax_rate 
 * @param  [type] $tax_name 
 * @return [type]           
 */
function sm_convert_item_taxes($tax, $tax_rate, $tax_name)
{
	/*taxrate taxname
	5.00    TAX5
	id      rate        name
	2|1 ; 6.00|10.00 ; TAX5|TAX10%*/
	$CI           = & get_instance();
	$taxes = [];
	if($tax != null && new_strlen($tax) > 0){
		$arr_tax_id = new_explode('|', $tax);
		if($tax_name != null && new_strlen($tax_name) > 0){
			$arr_tax_name = new_explode('|', $tax_name);
			$arr_tax_rate = new_explode('|', $tax_rate);
			foreach ($arr_tax_name as $key => $value) {
				$taxes[]['taxname'] = $value . '|' .  $arr_tax_rate[$key];
			}
		}elseif($tax_rate != null && new_strlen($tax_rate) > 0){
			$CI->load->model('service_management/service_management_model');
			$arr_tax_id = new_explode('|', $tax);
			$arr_tax_rate = new_explode('|', $tax_rate);
			foreach ($arr_tax_id as $key => $value) {
				$_tax_name = $CI->service_management_model->get_tax_name($value);
				if(isset($arr_tax_rate[$key])){
					$taxes[]['taxname'] = $_tax_name . '|' .  $arr_tax_rate[$key];
				}else{
					$taxes[]['taxname'] = $_tax_name . '|' .  $CI->service_management_model->tax_rate_by_id($value);

				}
			}
		}else{
			$CI->load->model('service_management/service_management_model');
			$arr_tax_id = new_explode('|', $tax);
			$arr_tax_rate = new_explode('|', $tax_rate);
			foreach ($arr_tax_id as $key => $value) {
				$_tax_name = $CI->service_management_model->get_tax_name($value);
				$_tax_rate = $CI->service_management_model->tax_rate_by_id($value);
				$taxes[]['taxname'] = $_tax_name . '|' .  $_tax_rate;
			} 
		}

	}

	return $taxes;
}

/**
 * sm handle contract attachment
 * @param  [type] $id 
 * @return [type]     
 */
function sm_handle_contract_attachment($id)
{
	if (isset($_FILES['file']) && _perfex_upload_error($_FILES['file']['error'])) {
		header('HTTP/1.0 400 Bad error');
		echo _perfex_upload_error($_FILES['file']['error']);
		die;
	}
	if (isset($_FILES['file']['name']) && $_FILES['file']['name'] != '') {
		hooks()->do_action('before_upload_sm_contract_attachment', $id);
		$path = SM_CONTRACT_FOLDER . $id . '/';
		// Get the temp file path
		$tmpFilePath = $_FILES['file']['tmp_name'];
		// Make sure we have a filepath
		if (!empty($tmpFilePath) && $tmpFilePath != '') {
			_maybe_create_upload_path($path);
			$filename    = unique_filename($path, $_FILES['file']['name']);
			$newFilePath = $path . $filename;
			// Upload the file into the company uploads dir
			if (move_uploaded_file($tmpFilePath, $newFilePath)) {
				$CI           = & get_instance();
				$attachment   = [];
				$attachment[] = [
					'file_name' => $filename,
					'filetype'  => $_FILES['file']['type'],
				];
				$CI->misc_model->add_attachment_to_database($id, 'sm_contract', $attachment);

				return true;
			}
		}
	}

	return false;
}

/**
 * sm count active contracts
 * @param  [type] $staffId 
 * @return [type]          
 */
function sm_count_active_contracts($staffId = null)
{
	$where_own = [];
	$staffId   = is_null($staffId) ? get_staff_user_id() : $staffId;

	if (!has_permission('service_management', '', 'view')) {
		$where_own = ['addedfrom' => $staffId];
	}

	return total_rows(db_prefix() . 'sm_contracts', '(DATE(dateend) >"' . date('Y-m-d') . '" AND trash=0' . (count($where_own) > 0 ? ' AND addedfrom=' . $staffId : '') . ') OR (DATE(dateend) IS NULL AND trash=0' . (count($where_own) > 0 ? ' AND addedfrom=' . $staffId : '') . ')');
}

/**
 * Get total number of expired contracts
 *
 * @param integer|null $staffId
 *
 * @return integer
 */
function sm_count_expired_contracts($staffId = null)
{
	$where_own = [];
	$staffId   = is_null($staffId) ? get_staff_user_id() : $staffId;

	if (!has_permission('service_management', '', 'view')) {
		$where_own = ['addedfrom' => $staffId];
	}

	return total_rows(db_prefix() . 'sm_contracts', array_merge(['DATE(dateend) <' => date('Y-m-d'), 'trash' => 0], $where_own));
}

/**
 * sm count recently created contracts
 * @param  integer $days    
 * @param  [type]  $staffId 
 * @return [type]           
 */
function sm_count_recently_created_contracts($days = 7, $staffId = null)
{
	$diff1     = date('Y-m-d', strtotime('-' . $days . ' days'));
	$diff2     = date('Y-m-d', strtotime('+' . $days . ' days'));
	$staffId   = is_null($staffId) ? get_staff_user_id() : $staffId;
	$where_own = [];

	if (!staff_can('view', 'service_management')) {
		$where_own = ['addedfrom' => $staffId];
	}

	return total_rows(db_prefix() . 'sm_contracts', 'dateadded BETWEEN "' . $diff1 . '" AND "' . $diff2 . '" AND trash=0' . (count($where_own) > 0 ? ' AND addedfrom=' . $staffId : ''));
}

/**
 * sm count trash contracts
 * @param  [type] $staffId 
 * @return [type]          
 */
function sm_count_trash_contracts($staffId = null)
{
	$where_own = [];
	$staffId   = is_null($staffId) ? get_staff_user_id() : $staffId;

	if (!has_permission('service_management', '', 'view')) {
		$where_own = ['addedfrom' => $staffId];
	}

	return total_rows(db_prefix() . 'sm_contracts', array_merge(['trash' => 1], $where_own));
}

/**
 * sm_check_contract_restrictions
 * @param  [type] $id   
 * @param  [type] $hash 
 * @return [type]       
 */
function sm_check_contract_restrictions($id, $hash)
{
	$CI = &get_instance();
	$CI->load->model('service_management/service_contract_model');


	if (!$hash || !$id) {
		show_404();
	}

	if (!is_client_logged_in() && !is_staff_logged_in()) {
		if (get_option('view_contract_only_logged_in') == 1) {
			redirect_after_login_to_current_url();
			redirect(site_url('authentication/login'));
		}
	}

	$contract = $CI->service_contract_model->get($id);

	if (!$contract || ($contract->hash != $hash)) {
		show_404();
	}

	// Do one more check
	if (!is_staff_logged_in() && !is_staff_logged_in()) {
		if (get_option('view_contract_only_logged_in') == 1) {
			if ($contract->client != get_client_user_id()) {
				show_404();
			}
		}
	}
}

/**
 * sm get group name
 * @param  boolean $id 
 * @return [type]      
 */
function sm_get_group_name($id = false)
{
	$CI           = & get_instance();

	if (is_numeric($id)) {
		$CI->db->where('id', $id);

		return $CI->db->get(db_prefix() . 'items_groups')->row();
	}
	if ($id == false) {
		return $CI->db->query('select * from '.db_prefix().'items_groups')->result_array();
	}

}

/**
 * sm order status
 * @return [type] 
 */
function sm_order_status($status='')
{

	$statuses = [

		[
			'id'             => 'draft',
			'color'          => '#9e9e9e',
			'name'           => _l('sm_draft'),
			'order'          => 2,
			'filter_default' => true,
		],
		[
			'id'             => 'processing',
			'color'          => '#2196f3',
			'name'           => _l('sm_processing'),
			'order'          => 3,
			'filter_default' => true,
		],
		[
			'id'             => 'pending_payment',
			'color'          => '#3db8da',
			'name'           => _l('sm_pending_payment'),
			'order'          => 4,
			'filter_default' => true,
		],
		[
			'id'             => 'paid',
			'color'          => '#84c529',
			'name'           => _l('sm_paid'),
			'order'          => 5,
			'filter_default' => true,
		],
		[
			'id'             => 'confirm',
			'color'          => '#ffa500',
			'name'           => _l('sm_confirm'),
			'order'          => 6,
			'filter_default' => true,
		],

		[
			'id'             => 'complete',
			'color'          => '#84c529',
			'name'           => _l('sm_complete'),
			'order'          => 7,
			'filter_default' => false,
		],
		[
			'id'             => 'cancelled',
			'color'          => '#d71a1a',
			'name'           => _l('sm_cancelled'),
			'order'          => 7,
			'filter_default' => false,
		],
		
	];

	usort($statuses, function ($a, $b) {
		return $a['order'] - $b['order'];
	});

	return $statuses;
}

/**
 * get order status by id
 * @param  [type] $id   
 * @param  [type] $type 
 * @return [type]       
 */
function get_order_status_by_id($id, $type)
{
	$CI       = &get_instance();

	if($type == 'order'){
		$statuses = sm_order_status();
		$status = [
			'id'         => 0,
			'color'   => '#989898',
			'color' => '#989898',
			'name'       => _l('sm_draff'),
			'order'      => 1,
		];
	}else{
		$statuses = sm_service_status();
		$status = [
			'id'         => 0,
			'color'   => '#989898',
			'color' => '#989898',
			'name'       => _l('sm_activate'),
			'order'      => 3,
		];
	}

	foreach ($statuses as $s) {
		if ($s['id'] == $id) {
			$status = $s;

			break;
		}
	}

	return $status;
}


/**
 * render order status html
 * @param  [type]  $id           
 * @param  [type]  $type         
 * @param  string  $status_value 
 * @param  boolean $ChangeStatus 
 * @return [type]                
 */
function render_order_status_html($id, $type, $status_value = '', $ChangeStatus = true)
{
	$status          = get_order_status_by_id($status_value, $type);

	if($type == 'order'){
		$task_statuses = sm_order_status();
	}else{
		$task_statuses = sm_service_status();
	}

	$outputStatus    = '';

	$outputStatus .= '<span class="inline-block label" style="color:' . $status['color'] . ';border:1px solid ' . $status['color'] . '" task-status-table="' . $status_value . '">';
	$outputStatus .= $status['name'];
	$canChangeStatus = (has_permission('service_management', '', 'edit') || is_admin());

	if ($canChangeStatus && $ChangeStatus) {
		$outputStatus .= '<div class="dropdown inline-block mleft5 table-export-exclude">';
		$outputStatus .= '<a href="#" class="dropdown-toggle text-dark dropdown-font-size" id="tableTaskStatus-' . $id . '" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">';
		$outputStatus .= '<span data-toggle="tooltip" title="' . _l('ticket_single_change_status') . '"><i class="fa fa-caret-down" aria-hidden="true"></i></span>';
		$outputStatus .= '</a>';

		$outputStatus .= '<ul class="dropdown-menu dropdown-menu-right" aria-labelledby="tableTaskStatus-' . $id . '">';
		foreach ($task_statuses as $taskChangeStatus) {
			if ($status_value != $taskChangeStatus['id']) {
				$outputStatus .= '<li>
				<a href="#" onclick="order_status_mark_as(\'' . $taskChangeStatus['id'] . '\',' . $id . ',\'' . $type . '\'); return false;">
				' . _l('task_mark_as', $taskChangeStatus['name']) . '
				</a>
				</li>';
			}
		}
		$outputStatus .= '</ul>';
		$outputStatus .= '</div>';
	}

	$outputStatus .= '</span>';

	return $outputStatus;
}

/**
 * sm service status
 * @param  string $status 
 * @return [type]         
 */
function sm_service_status($status='')
{

	$statuses = [

		[
			'id'             => 'service_has_been_renewal',
			'color'          => '#2196f3',
			'name'           => _l('sm_service_has_been_renewal'),
			'order'          => 1,
			'filter_default' => false,
		],
		[
			'id'             => 'pause',
			'color'          => '#3db8da',
			'name'           => _l('sm_pause'),
			'order'          => 2,
			'filter_default' => false,
		],
		[
			'id'             => 'activate',
			'color'          => '#4caf50',
			'name'           => _l('sm_activate'),
			'order'          => 3,
			'filter_default' => true,
		],
		[
			'id'             => 'complete',
			'color'          => '#84c529',
			'name'           => _l('sm_complete'),
			'order'          => 4,
			'filter_default' => false,
		],
		
		[
			'id'             => 'expired',
			'color'          => '#ffa500',
			'name'           => _l('sm_expired'),
			'order'          => 5,
			'filter_default' => false,
		],
		[
			'id'             => 'cancelled',
			'color'          => '#d71a1a',
			'name'           => _l('sm_cancelled'),
			'order'          => 6,
			'filter_default' => false,
		],
		
	];

	usort($statuses, function ($a, $b) {
		return $a['order'] - $b['order'];
	});

	return $statuses;

	return $status;
}

/**
 * sm order code
 * @param  [type] $id 
 * @return [type]     
 */
function sm_order_code($id)
{
	$order_code = '';
	$CI           = & get_instance();
	$CI->db->where('id',$id);

	$sm_orders = $CI->db->get(db_prefix().'sm_orders')->row();
	if($sm_orders){
		$order_code = $sm_orders->order_code;
	}
	return $order_code;
}

/**
 * sm handle contract addendum attachment
 * @param  [type] $id 
 * @return [type]     
 */
function sm_handle_contract_addendum_attachment($id)
{
	if (isset($_FILES['file']) && _perfex_upload_error($_FILES['file']['error'])) {
		header('HTTP/1.0 400 Bad error');
		echo _perfex_upload_error($_FILES['file']['error']);
		die;
	}
	if (isset($_FILES['file']['name']) && $_FILES['file']['name'] != '') {
		hooks()->do_action('before_upload_sm_contract_attachment', $id);
		$path = SM_CONTRACT_ADDENDUM_FOLDER . $id . '/';
		// Get the temp file path
		$tmpFilePath = $_FILES['file']['tmp_name'];
		// Make sure we have a filepath
		if (!empty($tmpFilePath) && $tmpFilePath != '') {
			_maybe_create_upload_path($path);
			$filename    = unique_filename($path, $_FILES['file']['name']);
			$newFilePath = $path . $filename;
			// Upload the file into the company uploads dir
			if (move_uploaded_file($tmpFilePath, $newFilePath)) {
				$CI           = & get_instance();
				$attachment   = [];
				$attachment[] = [
					'file_name' => $filename,
					'filetype'  => $_FILES['file']['type'],
				];
				$CI->misc_model->add_attachment_to_database($id, 'sm_contract_addendum', $attachment);

				return true;
			}
		}
	}

	return false;
}

/**
 * sm_contract_code
 * @param  [type] $id 
 * @return [type]     
 */
function sm_contract_name($id)
{
	$subject = '';
	$CI           = & get_instance();
	$CI->db->where('id',$id);

	$contract = $CI->db->get(db_prefix().'sm_contracts')->row();
	if($contract){
		$subject = $contract->subject;
	}
	return $subject;
}

/**
 * sm client id from contract
 * @param  [type] $id 
 * @return [type]     
 */
function sm_client_id_from_contract($id)
{
	$client_id = '';
	$CI           = & get_instance();
	$CI->db->where('id',$id);
	$contract = $CI->db->get(db_prefix().'sm_contracts')->row();
	if($contract){
		$client_id = $contract->client;
	}
	return $client_id;
}

/**
 * sm render taxes html
 * @param  [type] $item_tax 
 * @param  [type] $width    
 * @return [type]           
 */
function sm_render_taxes_html($item_tax, $width)
{
    $itemHTML = '';
    $itemHTML .= '<td align="right" width="' . $width . '%">';

    if(is_array($item_tax) && isset($item_tax)){
        if (count($item_tax) > 0) {
            foreach ($item_tax as $tax) {

                $item_tax = '';
                if ( get_option('remove_tax_name_from_item_table') == false || multiple_taxes_found_for_item($item_tax)) {
                    $tmp      = new_explode('|', $tax['taxname']);
                    $item_tax = $tmp[0] . ' ' . app_format_number($tmp[1]) . '%<br />';
                } else {
                    $item_tax .= app_format_number($tax['taxrate']) . '%';
                }
                $itemHTML .= $item_tax;
            }
        } else {
            $itemHTML .=  app_format_number(0) . '%';
        }
    }
    $itemHTML .= '</td>';

    return $itemHTML;
}

/**
 * sm get invoice hash
 * @param  [type] $id 
 * @return [type]     
 */
function sm_get_invoice_hash($id)
{
	$hash = '';
	$CI           = & get_instance();
	$CI->db->where('id',$id);

	$invoices = $CI->db->get(db_prefix().'invoices')->row();
	if($invoices){
		$hash = $invoices->hash;
	}
	return $hash;
}

/**
 * sm_get_image_items
 * @param  [type] $item_id 
 * @return [type]          
 */
function sm_get_image_items($item_id){
	$file_path_rs  = site_url('modules/service_management/uploads/no_image.jpg');
	$CI           = & get_instance();

	$CI->load->model('service_management/service_management_model');
	$list_filename = $CI->service_management_model->sm_get_all_image_file_name($item_id);
	foreach ($list_filename as $key => $value) {
		$is_image_exist = false;
		if (file_exists('modules/warehouse/uploads/item_img/' . $item_id . '/' . $value["file_name"])) {
			$is_image_exist = true;  
			return site_url('modules/warehouse/uploads/item_img/' . $item_id . '/' . $value["file_name"]);

		} 
		elseif(file_exists('modules/purchase/uploads/item_img/'. $item_id . '/' . $value["file_name"])) {
			$is_image_exist = true;  
			return site_url('modules/purchase/uploads/item_img/' . $item_id . '/' . $value["file_name"]);
		}
		elseif(file_exists('modules/manufacturing/uploads/products/'. $item_id . '/' . $value["file_name"])) {
			$is_image_exist = true;         
			return site_url('modules/manufacturing/uploads/products/' . $item_id . '/' . $value["file_name"]);
		}
		elseif(file_exists('modules/service_management/uploads/products/'. $item_id . '/' . $value["file_name"])) {
			$is_image_exist = true; 
			return site_url('modules/service_management/uploads/products/' . $item_id . '/' . $value["file_name"]);
		}
	}
	return site_url('modules/service_management/uploads/no_image.jpg');     

}

/**
 * sm check image items
 * @param  [type] $item_id   
 * @param  [type] $file_name 
 * @return [type]            
 */
function sm_check_image_items($item_id, $file_name){
	$file_path  = 'modules/service_management/uploads/no_image.jpg';
	$check_img = false;
	if(omni_get_status_modules('warehouse') == true){
		$fp  = 'modules/warehouse/uploads/item_img/'.$item_id.'/'.$file_name;
		if(file_exists(FCPATH.$fp) ){
			$file_path = $fp;
			$check_img = true;
		}
	}
	if(!$check_img && omni_get_status_modules('purchase') == true){
		$fp  = 'modules/purchase/uploads/item_img/'.$item_id.'/'.$file_name;
		if(file_exists(FCPATH.$fp) ){
			$file_path = $fp;
			$check_img = true;
		}
	}
	if(!$check_img && omni_get_status_modules('manufacturing') == true){
		$fp  = 'modules/manufacturing/uploads/products/'.$item_id.'/'.$file_name;
		if(file_exists(FCPATH.$fp) ){
			$file_path = $fp;
			$check_img = true;
		}
	}

	if(!$check_img && omni_get_status_modules('service_management') == true){
		$fp  = 'modules/service_management/uploads/products/'.$item_id.'/'.$file_name;
		if(file_exists(FCPATH.$fp) ){
			$file_path = $fp;
			$check_img = true;
		}
	}
	
	return site_url($file_path);
}

/**
 * sm generate commodity barcode
 * @return [type] 
 */
function sm_generate_commodity_barcode()
{
	$CI           = & get_instance();

	$item = false;
	do {
		$length = 11;
		$chars = '0123456789';
		$count = new_strlen($chars);
		$password = '';
		for ($i = 0; $i < $length; $i++) {
			$index = rand(0, $count - 1);
			$password .= mb_substr($chars, $index, 1);
		}
		$CI->db->where('commodity_barcode', $password);
		$item = $CI->db->get(db_prefix() . 'items')->row();
	} while ($item);

	return $password;
}

/**
 * sm get unit name
 * @param  [type] $id 
 * @return [type]     
 */
function sm_get_unit_name($id)
{
	$CI   = & get_instance();
	$CI->db->where('unit_type_id', $id);
	$unit = $CI->db->get(db_prefix() . 'ware_unit_type')->row();

	$name='';
	if($unit){
		$name .= $unit->unit_name;
	}

	return $name;
}

/**
	 * [new_html_entity_decode description]
	 * @param  [type] $str [description]
	 * @return [type]      [description]
	 */
if (!function_exists('new_html_entity_decode')) {
	
	function new_html_entity_decode($str){
		return html_entity_decode($str ?? '');
	}
}

if (!function_exists('new_strlen')) {
	
	function new_strlen($str){
		return strlen($str ?? '');
	}
}

if (!function_exists('new_str_replace')) {
	
	function new_str_replace($search, $replace, $subject){
		return str_replace($search, $replace, $subject ?? '');
	}
}

if (!function_exists('new_explode')) {

	function new_explode($delimiter, $string){
		return explode($delimiter, $string ?? '');
	}
}

if (!function_exists('reformat_currency_j')) {

    function reformat_currency_j($value)
    {

        $f_dot = new_str_replace(',','', $value);
        return ((float)$f_dot + 0);
    }
}
