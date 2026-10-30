<?php

defined('BASEPATH') or exit('No direct script access allowed');
/**
 * Class Service_management
 */
class Service_management extends AdminController
{
	/**
	 * __construct
	 */
	public function __construct()
	{
		parent::__construct();
        $this->load->library('stripe_subscriptions');
        $this->load->library('stripe_core');
		$this->load->model('service_management_model');
		$this->load->model('service_contract_model');
		$this->load->helper('download');

		hooks()->do_action('service_management_init');

	}

	/**
	 * setting
	 * @return [type] 
	 */
	public function setting()
	{
		if (!has_permission('service_management', '', 'edit') && !is_admin() && !has_permission('service_management', '', 'create')) {
			access_denied('service_management');
		}

		$data['group'] = $this->input->get('group');
		$data['title'] = _l('setting');

		$data['tab'][] = 'category';
		$data['tab'][] = 'unit';
		$data['tab'][] = 'general';

		if ($data['group'] == '') {
			$data['group'] = 'category';
			$data['item_categories'] = $this->service_management_model->get_item_category(false, false);
			$data['tabs']['view'] = 'settings/categories/' . $data['group'];
		}elseif ($data['group'] == 'category') {
			$data['item_categories'] = $this->service_management_model->get_item_category(false, false);
			$data['tabs']['view'] = 'settings/categories/' . $data['group'];
		}elseif($data['group'] == 'unit'){
			$data['item_units'] = $this->service_management_model->get_item_unit();
			$data['tabs']['view'] = 'settings/units/' . $data['group'];
		}elseif($data['group'] == 'status'){
			$data['item_status'] = $this->service_management_model->get_status();
			$data['tabs']['view'] = 'settings/status/' . $data['group'];
		}elseif($data['group'] == 'general'){
			$data['tabs']['view'] = 'settings/general/' . $data['group'];
		}


		$this->load->view('settings/manage_setting', $data);
	}

	/**
	 * categories
	 * @param  string $id 
	 * @return [type]     
	 */
	public function categories($id = '') {
		if ($this->input->post()) {
			$message = '';
			$data = $this->input->post();

			if (!$this->input->post('id')) {

				$mess = $this->service_management_model->add_item_category($data);
				if ($mess) {
					set_alert('success', _l('added_successfully'));
				} else {
					set_alert('warning', _l('Add_commodity_type_false'));
				}
				redirect(admin_url('service_management/setting?group=category'));

			} else {
				$id = $data['id'];
				unset($data['id']);
				$success = $this->service_management_model->update_item_category($data, $id);
				if ($success) {
					set_alert('success', _l('updated_successfully'));
				}

				redirect(admin_url('service_management/setting?group=category'));
			}
		}
	}

	/**
	 * [delete_color description]
	 * @param  [type] $id [description]
	 * @return [type]     [description]
	 */
	public function delete_category($id) {
		if (!$id) {
			redirect(admin_url('service_management/setting?group=category'));
		}

		if(!has_permission('service_management', '', 'delete')  &&  !is_admin()) {
			access_denied('service_management');
		}

		$response = $this->service_management_model->delete_item_category($id);
		if ($response) {
			set_alert('success', _l('deleted'));
			redirect(admin_url('service_management/setting?group=category'));
		} else {
			set_alert('warning', _l('problem_deleting'));
			redirect(admin_url('service_management/setting?group=category'));
		}

	}

	/**
	 * change category status
	 * @param  [type] $id     
	 * @param  [type] $status 
	 * @return [type]         
	 */
	public function change_category_status($id, $status) {
		if (has_permission('service_management', '', 'edit')) {
			if ($this->input->is_ajax_request()) {
				$this->service_management_model->change_category_status($id, $status);
			}
		}
	}

	/**
	 * units
	 * @param  string $id 
	 * @return [type]     
	 */
	public function units($id = '') {
		if ($this->input->post()) {
			$message = '';
			$data = $this->input->post();

			if (!$this->input->post('id')) {

				$mess = $this->service_management_model->add_item_unit($data);
				if ($mess) {
					set_alert('success', _l('added_successfully'));
				} else {
					set_alert('warning', _l('Add_commodity_type_false'));
				}
				redirect(admin_url('service_management/setting?group=unit'));

			} else {
				$id = $data['id'];
				unset($data['id']);
				$success = $this->service_management_model->update_item_unit($data, $id);
				if ($success) {
					set_alert('success', _l('updated_successfully'));
				}

				redirect(admin_url('service_management/setting?group=unit'));
			}
		}
	}

	/**
	 * [delete_color description]
	 * @param  [type] $id [description]
	 * @return [type]     [description]
	 */
	public function delete_unit($id) {
		if (!$id) {
			redirect(admin_url('service_management/setting?group=unit'));
		}

		if(!has_permission('service_management', '', 'delete')  &&  !is_admin()) {
			access_denied('service_management');
		}

		$response = $this->service_management_model->delete_item_unit($id);
		if ($response) {
			set_alert('success', _l('deleted'));
			redirect(admin_url('service_management/setting?group=unit'));
		} else {
			set_alert('warning', _l('problem_deleting'));
			redirect(admin_url('service_management/setting?group=unit'));
		}

	}

	/**
	 * change category status
	 * @param  [type] $id     
	 * @param  [type] $status 
	 * @return [type]         
	 */
	public function change_unit_status($id, $status) {
		if (has_permission('service_management', '', 'edit')) {
			if ($this->input->is_ajax_request()) {
				$this->service_management_model->change_unit_status($id, $status);
			}
		}
	}

	/**
	 * status
	 * @param  string $id 
	 * @return [type]     
	 */
	public function status($id = '') {
		if ($this->input->post()) {
			$message = '';
			$data = $this->input->post();

			if (!$this->input->post('id')) {

				$mess = $this->service_management_model->add_status($data);
				if ($mess) {
					set_alert('success', _l('added_successfully'));
				} else {
					set_alert('warning', _l('Add_commodity_type_false'));
				}
				redirect(admin_url('service_management/setting?group=status'));

			} else {
				$id = $data['id'];
				unset($data['id']);
				$success = $this->service_management_model->update_status($data, $id);
				if ($success) {
					set_alert('success', _l('updated_successfully'));
				}

				redirect(admin_url('service_management/setting?group=status'));
			}
		}
	}

	/**
	 * delete status
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function delete_status($id) {
		if (!$id) {
			redirect(admin_url('service_management/setting?group=status'));
		}

		if(!has_permission('service_management', '', 'delete')  &&  !is_admin()) {
			access_denied('service_management');
		}

		$response = $this->service_management_model->delete_status($id);
		if ($response) {
			set_alert('success', _l('deleted'));
			redirect(admin_url('service_management/setting?group=status'));
		} else {
			set_alert('warning', _l('problem_deleting'));
			redirect(admin_url('service_management/setting?group=status'));
		}

	}

	/**
	 * change category status
	 * @param  [type] $id     
	 * @param  [type] $status 
	 * @return [type]         
	 */
	public function change_status($id, $status) {
		if (has_permission('service_management', '', 'edit')) {
			if ($this->input->is_ajax_request()) {
				$this->service_management_model->change_status($id, $status);
			}
		}
	}

	/**
	 * sm check box setting
	 * @return [type] 
	 */
	public function sm_check_box_setting(){
		$data = $this->input->post();

		if (!has_permission('service_management', '', 'edit') && !is_admin()) {
			$success = false;
			$message = _l('Not permission edit');

			echo json_encode([
				'message' => $message,
				'success' => $success,
			]);
			die;
		}

		if($data != 'null'){
			$value = $this->service_management_model->change_setting_with_checkbox($data);
			if($value){
				$success = true;
				$message = _l('updated_successfully');
			}else{
				$success = false;
				$message = _l('updated_false');
			}
			echo json_encode([
				'message' => $message,
				'success' => $success,
			]);
			die;
		}
	}

	/**
	* product table
	* @return [type] 
	*/
	public function product_table()
	{
		$this->app->get_table_data(module_views_path('service_management', 'products/product_table'));
	}

	/**
	 * product management
	 * @param  string $id 
	 * @return [type]     
	 */
	public function product_management($id = '')
	{

		$data['title'] = _l('sm_product_management');
		$data['products_services'] = $this->service_management_model->get_product();
		$data['product_id'] = $id;
		$data['product_categories'] = $this->service_management_model->get_item_category();

		$this->load->view('products/product_management', $data);
	}


	/**
	 * add edit product
	 * @param [type] $type : product or product variant
	 * @param string $id   
	 */
	public function add_edit_product($id = '')
	{
		if (!has_permission('service_management', '', 'view')  && !is_admin()) {
			access_denied('work_center');
		}
		
		if ($this->input->post()) {
			$data = $this->input->post();
			$data['service_policy'] = json_decode($this->input->post('service_policy', false));

			if ($id == '') {
				if (!has_permission('service_management', '', 'create') && !is_admin()) {
					access_denied('work_center');
				}

				$insert_id = $this->service_management_model->add_product($data);
				$url = admin_url('service_management/product_management');

				if ($insert_id) {

					set_alert('success', _l('mrp_added_successfully'));
					/*upload multifile*/
					echo json_encode([
						'url' => $url,
						'commodityid' => $insert_id,
						'add_or_update' => 'add',

					]);
					die;
				}

				set_alert('warning', _l('mrp_added_failed'));
				$url = admin_url('service_management/product_management');

				echo json_encode([
					'url' => $url,
					'add_or_update' => 'add',

				]);
				die;

			} else {
				if (!has_permission('service_management', '', 'edit') && !is_admin()) {
					access_denied('work_center');
				}
				$success = $this->service_management_model->update_product($data, $id);
				/*update file*/
				set_alert('success', _l('mrp_updated_successfully'));
				$url = admin_url('service_management/product_management');

				echo json_encode([
					'url' => $url,
					'commodityid' => $id,
					'add_or_update' => 'update',

				]);
				die;
			}
		}
		
		$data=[];
		$data['title'] = _l('sm_add_product');
		if ($id != ''){
			$data['product'] = $this->service_management_model->get_product($id);
			$data['product_attachments'] = $this->service_management_model->sm_get_attachments_file($id, 'commodity_item_file');
			$data['title'] = _l('sm_update_product');
			$data['total_billing_plan'] = count($data['product']->item_billing_plan)+1;
		}

		$data['product_group'] = $this->service_management_model->get_item_category('', true);
		$data['units'] = $this->service_management_model->get_item_unit('', true);
		$data['taxes'] = sm_get_taxes();

		$this->load->view('service_management/products/add_edit_product', $data);
	}


	/**
	 * check sku duplicate
	 * @return [type] 
	 */
	public function check_sku_duplicate()
	{
		$data = $this->input->post();
		$result = $this->service_management_model->check_sku_duplicate($data);

		echo json_encode([
			'message' => $result
		]);
		die;	
	}


    /**
     * add product attachment
     * @param [type] $id 
     */
    public function add_product_attachment($id, $type = '')
    {

    	sm_handle_product_attachments($id);
    	$url = '';
    	if($type == 'subscription'){
    		$url = admin_url('service_management/subscription_services_management');
    	}
    	else{
    		$url = admin_url('service_management/product_management');	
    	}
    	echo json_encode([
    		'url' => $url,
    		'id' => $id,
    	]);
    }


	/**
	 * delete product attachment
	 * @param  [type] $attachment_id 
	 * @param  [type] $rel_type      
	 * @return [type]                
	 */
	public function delete_product_attachment($attachment_id, $rel_type)
	{
		if (!has_permission('service_management', '', 'delete') && !is_admin()) {
			access_denied('service_management');
		}

		$folder_name = '';

		switch ($rel_type) {
			case 'manufacturing':
			$folder_name = 'modules/manufacturing/products/';
			break;
			case 'warehouse':
			$folder_name = module_dir_path('warehouse', 'uploads/item_img/');
			break;
			case 'purchase':
			$folder_name = module_dir_path('purchase', 'uploads/item_img/');
			break;
			case 'service_management':
			$folder_name = SERVICE_MANAGEMENT_PRODUCT_UPLOAD;
			break;
		}

		echo json_encode([
			'success' => $this->service_management_model->delete_sm_attachment_file($attachment_id, $folder_name),
		]);
	}


	/**
	 * delete product
	 * @param  [type] $id       
	 * @param  [type] $rel_type 
	 * @return [type]           
	 */
	public function delete_product($id, $type = '')
	{

		if (!$id) {
			redirect(admin_url('service_management/product_management'));
		}

		if(!has_permission('service_management', '', 'delete')  &&  !is_admin()) {
			access_denied('service_management');
		}

		$response = $this->service_management_model->delete_product($id);
		if (is_array($response) && isset($response['referenced'])) {
			set_alert('warning', _l('is_referenced', _l('commodity')));
		} elseif ($response == true) {
			set_alert('success', _l('mrp_deleted'));
		} else {
			set_alert('warning', _l('problem_deleting'));
		}
		if($type == 'subscription' ){
			redirect(admin_url('service_management/subscription_services_management'));
		}
		redirect(admin_url('service_management/product_management'));
	}

	/**
	 * service managements
	 * @return [type] 
	 */
	public function service_managements()
	{

		$data['title'] = _l('sm_order_management');
		$data['product_categories'] = $this->service_management_model->get_item_category();
		$data['clients'] = $this->clients_model->get();


		$this->load->view('service_management/service_managements/service_management', $data);
	}

	/**
	 * service management table
	 * @return [type] 
	 */
	public function service_management_table()
	{
		$this->app->get_table_data(module_views_path('service_management', 'service_managements/service_management_table'));
	}

	/**
	 * add edit service
	 * @param string $id 
	 */
	public function add_edit_order($id = '')
	{
		if (!has_permission('service_management', '', 'view')  && !is_admin()) {
			access_denied('service_management');
		}
		
		if ($this->input->post()) {
			$data = $this->input->post();
			$id = $this->input->post('id');

			if ($id == '') {
				if (!has_permission('service_management', '', 'create') && !is_admin()) {
					access_denied('service_management');
				}

				$insert_id = $this->service_management_model->add_order($data);
				if ($insert_id) {
					set_alert('success', _l('sm_added_successfully'));
				}
				redirect(admin_url('service_management/service_managements'));

			} else {
				if (!has_permission('service_management', '', 'edit') && !is_admin()) {
					access_denied('service_management');
				}
				$success = $this->service_management_model->update_order($data, $id);
				/*update file*/
				set_alert('success', _l('sm_updated_successfully'));
				redirect(admin_url('service_management/service_managements'));
			}
		}
		
		$data=[];
		$data['title'] = _l('sm_new_order');
		$service_row_template = '';
		$service_row_template .= $this->service_management_model->create_service_row_template();
		if ($id != ''){

			$order = $this->service_management_model->get_order($id);
			$data['order'] = $order;
			$data['title'] = _l('sm_update_order');

			$order_index = 0;
			$taxname = '';
			foreach ($order->order_details as $key => $order_detail) {
				$order_index++;

				$service_row_template .= $this->service_management_model->create_service_row_template('items[' . $order_index . ']', $order_detail['item_name'], $order_detail['item_id'], $order_detail['billing_plan_unit_id'], $order_detail['billing_plan_value'], $order_detail['billing_plan_type'], $order_detail['billing_plan_rate'], $order_detail['quantity'], $taxname, $order_detail['tax_name'], $order_detail['tax_rate'], $order_detail['tax_id'], $order_detail['sub_total'], $order_detail['total_money'], $order_detail['discount'], $order_detail['discount_money'], $order_detail['total_after_discount'], $order_detail['id'], true);

			}

		}

		$data['taxes'] = $this->taxes_model->get();
		$data['ajaxItems'] = false;
		if (total_rows(db_prefix() . 'items', db_prefix().'items.can_be_product_service = "can_be_product_service" AND active = 1') <= sm_ajax_on_total_items()) {
			$data['items'] = $this->service_management_model->sm_get_grouped('');
		} else {
			$data['items']     = [];
			$data['ajaxItems'] = true;
		}

		$data['staffs'] = $this->staff_model->get();
		$data['clients'] = $this->clients_model->get();
		$data['product_group'] = $this->service_management_model->get_item_category('', true);
		$data['units'] = $this->service_management_model->get_item_unit('', true);
		$data['taxes'] = sm_get_taxes();

		$data['service_row_template'] = $service_row_template;
		$data['order_code'] = 'ORDER'.date('YmdHis');
		$get_base_currency =  get_base_currency();
		if($get_base_currency){
			$data['base_currency_id'] = $get_base_currency->id;
		}else{
			$data['base_currency_id'] = 0;
		}

		$this->load->view('service_management/service_managements/add_edit_service_management', $data);
	}

	/**
	 * product detail
	 * @param  [type] $product_id 
	 * @return [type]             
	 */
	public function product_detail($product_id) {
		$commodity_item = $this->service_management_model->get_product($product_id);

		if (!$commodity_item) {
			blank_page('Product item Not Found', 'danger');
		}


		$data['service'] = $this->service_management_model->get_product($product_id);

		$data['title'] = _l("sm_service_detail");
		$data['service_file'] = $this->service_management_model->sm_get_attachments_file($product_id, 'commodity_item_file');

		$this->load->view('products/product_detail', $data);

	}

	/**
	 * sm product delete bulk action
	 * @return [type] 
	 */
	public function sm_product_delete_bulk_action()
	{
		if (!is_staff_member()) {
			ajax_access_denied();
		}

		$total_deleted = 0;

		if ($this->input->post()) {

			$ids                   = $this->input->post('ids');
			$rel_type                   = $this->input->post('rel_type');

			/*check permission*/
			switch ($rel_type) {
				case 'commodity_list':
				if (!has_permission('manufacturing', '', 'delete') && !is_admin()) {
					access_denied('product');
				}
				break;


				default:
				break;
			}

			/*delete data*/
			if ($this->input->post('mass_delete')) {
				if (is_array($ids)) {
					switch ($rel_type) {
						case 'commodity_list':
						foreach ($ids as $id) {
							if ($this->service_management_model->delete_product($id)) {
								$total_deleted++;
							}
						}

						break;

						default:
							# code...
						break;
					}

				}

				/*return result*/
				switch ($rel_type) {
					case 'commodity_list':
					set_alert('success', _l('sm_total_product'). ": " .$total_deleted);
					break;

					default:
					break;

				}


			}

		}

	}

	/**
	 * sm client change data
	 * @param  [type] $customer_id     
	 * @param  string $current_invoice 
	 * @return [type]                  
	 */
	public function sm_client_change_data($customer_id, $current_invoice = '')
	{
		if ($this->input->is_ajax_request()) {
			$this->load->model('invoices_model');

			$data                     = [];
			$data['billing_shipping'] = $this->clients_model->get_customer_billing_and_shipping_details($customer_id);

			if ($current_invoice != '') {
				$this->db->select('status');
				$this->db->where('id', $current_invoice);
				$current_invoice_status = $this->db->get(db_prefix() . 'invoices')->row()->status;
			}
			echo json_encode($data);
		}
	}

    /**
     * get item by id
     * @param  [type]  $id               
     * @param  boolean $get_billing_plan 
     * @param  boolean $billing_plan_id  
     * @return [type]                    
     */
    public function get_item_by_id($id, $get_billing_plan = false, $billing_plan_id = false)
    {
    	if ($this->input->is_ajax_request()) {

    		$get_base_currency =  get_base_currency();
    		if($get_base_currency){
    			$base_currency_id = $get_base_currency->id;
    		}else{
    			$base_currency_id = 0;
    		}

    		$item                     = $this->service_management_model->get_item_v2($id);
    		$item->long_description   = nl2br($item->long_description);
    		$guarantee_new = '';

    		$item->guarantee_new = $guarantee_new;
    		$html = '<option value=""></option>';
    		if((int)$get_billing_plan ==  1){
    			$get_available_quantity = $this->service_management_model->get_adjustment_stock_quantity($billing_plan_id, $id, null, null);
    			if($get_available_quantity){
    				$item->available_quantity = (float)$get_available_quantity->inventory_number;
    			}else{
    				$item->available_quantity = 0;
    			}
    		}elseif($get_billing_plan){
    			$arr_warehouse_id = [];
    			$product = $this->service_management_model->get_product($id);
    			if($product){
    				if (count($product->item_billing_plan) > 0) {
    					foreach ($product->item_billing_plan as $item_billing_plan) {
    						if($item_billing_plan['status_cycles'] == 'active'){
    							$html .= '<option value="' . $item_billing_plan['id'] . '">' . app_format_money((float)$item_billing_plan['item_rate'], $base_currency_id).' ('. $item_billing_plan['unit_value'].' '. _l($item_billing_plan['unit_type']) . ')</option>';
    						}
    					}
    				}
    			}

    		}
    		$item->billing_plan_html = $html;

    		echo json_encode($item);
    	}
    }

	/**
	 * get billing unit
	 * @return [type] 
	 */
	public function get_billing_unit() {
		$data = $this->input->post();
		if ($data != 'null') {

			$product_cycle = $this->service_management_model->get_product_cycle($data['billing_plan_unit_id']);

			$billing_plan_value = 0;
			$billing_plan_type = 0;
			$billing_plan_rate = 0;
			$extend_value = 0;
			$promotion_extended_percent = 0;

			if ($product_cycle) {
				$message = true;
				$billing_plan_rate = $product_cycle->item_rate;
				$billing_plan_value = $product_cycle->unit_value;
				$billing_plan_type = $product_cycle->unit_type;

				if($this->service_management_model->sm_check_the_next_order($product_cycle->extend_value, $product_cycle->item_id, $data['client_id'])){
					$extend_value = $product_cycle->extend_value;
					$promotion_extended_percent = $product_cycle->promotion_extended_percent;
				}

			} else {
				$message = _l('sm_billing_plan_unit_does_not_exist');
			}
			
			echo json_encode([
				'message' => $message,
				'value' => $billing_plan_rate,
				'extend_value' => $extend_value,
				'promotion_extended_percent' => $promotion_extended_percent,
				'billing_plan_value' => $billing_plan_value,
				'billing_plan_type' => $billing_plan_type,
			]);
			die;
		}
	}

	/**
	 * get service row template
	 * @return [type] 
	 */
	public function get_service_row_template()
	{
		$name = $this->input->post('name');
		$item_name = $this->input->post('item_name');
		$billing_plan_unit_id = $this->input->post('billing_plan_unit_id');
		$quantity = $this->input->post('quantity');
		$billing_plan_value = $this->input->post('billing_plan_value');
		$billing_plan_type = $this->input->post('billing_plan_type');
		$billing_plan_rate = $this->input->post('billing_plan_rate');
		$taxname = $this->input->post('taxname');
		$item_id = $this->input->post('item_id');
		$discount = $this->input->post('discount');
		$tax_rate = $this->input->post('tax_rate');
		$itemid = $this->input->post('itemid');
		$item_key = $this->input->post('item_key');
		$service_row_template = '';

		$service_row_template .= $this->service_management_model->create_service_row_template($name, $item_name, $item_id, $billing_plan_unit_id, $billing_plan_value, $billing_plan_type, $billing_plan_rate, $quantity,$taxname, '', $tax_rate, '', '', '', $discount, '', '', $item_key );

		echo  $service_row_template;
	}

	/**
	 * delete order
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function delete_order($id) {

		if(!has_permission('service_management', '', 'delete')  &&  !is_admin()) {
			access_denied('service_management');
		}

		$response = $this->service_management_model->delete_order($id);
		if ($response == true) {
			set_alert('success', _l('deleted'));
		} else {
			set_alert('warning', _l('problem_deleting'));
		}
		redirect(admin_url('service_management/service_managements'));
	}

	/*clone contract from Core*/ 

	/* List all contracts */
	public function manage_contract()
	{
		close_setup_menu();

		if (!has_permission('service_management', '', 'view') && !has_permission('service_management', '', 'view_own')) {
			access_denied('contracts');
		}

		$data['expiring']               = $this->service_contract_model->get_contracts_about_to_expire(get_staff_user_id());
		$data['count_active']           = sm_count_active_contracts();
		$data['count_expired']          = sm_count_expired_contracts();
		$data['count_recently_created'] = sm_count_recently_created_contracts();
		$data['count_trash']            = sm_count_trash_contracts();
		$data['chart_types']            = json_encode($this->service_contract_model->get_contracts_types_chart_data());
		$data['chart_types_values']     = json_encode($this->service_contract_model->get_contracts_types_values_chart_data());
		$data['contract_types']         = $this->service_contract_model->get_contract_types();
		$data['years']                  = $this->service_contract_model->get_contracts_years();
		$this->load->model('currencies_model');
		$data['base_currency'] = $this->currencies_model->get_base_currency();
		$data['title']         = _l('contracts');
		$this->load->view('service_management/contracts/manage', $data);
	}

	/**
	 * table
	 * @param  string $clientid 
	 * @return [type]           
	 */
	public function table($clientid = '')
	{
		if (!has_permission('service_management', '', 'view') && !has_permission('service_management', '', 'view_own')) {
			ajax_access_denied();
		}
		$this->app->get_table_data(module_views_path('service_management', 'contracts/contracts'), [
			'clientid' => $clientid,
		]);
	}

	/* Edit contract or add new contract */
	public function contract($id = '')
	{
		if ($this->input->post()) {
			if ($id == '') {
				if (!has_permission('service_management', '', 'create')) {
					access_denied('contracts');
				}
				$id = $this->service_contract_model->add($this->input->post());
				if ($id) {
					set_alert('success', _l('added_successfully', _l('contract')));
					redirect(admin_url('service_management/contract/' . $id));
				}
			} else {
				if (!has_permission('service_management', '', 'edit')) {
					access_denied('contracts');
				}
				$success = $this->service_contract_model->update($this->input->post(), $id);
				if ($success) {
					set_alert('success', _l('updated_successfully', _l('contract')));
				}
				redirect(admin_url('service_management/contract/' . $id));
			}
		}
		if ($id == '') {
			$title = _l('add_new', _l('contract_lowercase'));
			$data['orders'] = $this->service_management_model->get_order_without_contract();

		} else {
			$data['contract']                 = $this->service_contract_model->get($id, [], true);
			$data['contract_renewal_history'] = $this->service_contract_model->get_contract_renewal_history($id);
			$data['totalNotes']               = total_rows(db_prefix() . 'notes', ['rel_id' => $id, 'rel_type' => 'contract']);
			if (!$data['contract'] || (!has_permission('service_management', '', 'view') && $data['contract']->addedfrom != get_staff_user_id())) {
				blank_page(_l('contract_not_found'));
			}

			$data['contract_merge_fields'] = $this->app_merge_fields->get_flat('sm_contract', ['other', 'client'], '{email_signature}');


			$title = $data['contract']->subject;

			$data = array_merge($data, prepare_mail_preview_data('contract_send_to_customer', $data['contract']->client));

			$data['orders'] = $this->service_management_model->get_order_without_contract($data['contract']->order_id);

		}

		if ($this->input->get('customer_id')) {
			$data['customer_id'] = $this->input->get('customer_id');
		}

		$this->load->model('currencies_model');
		$data['base_currency'] = $this->currencies_model->get_base_currency();
		$data['types']         = $this->service_contract_model->get_contract_types();
		$data['title']         = $title;
		$data['bodyclass']     = 'contract';
		$data['id']     = $id;

		$this->load->view('service_management/contracts/contract', $data);
	}

	/**
	 * get template
	 * @return [type] 
	 */
	public function get_template()
	{
		$name = $this->input->get('name');
		echo $this->load->view('service_management/contracts/templates/' . $name, [], true);
	}

	/**
	 * mark as signed
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function mark_as_signed($id)
	{
		if (!has_permission('service_management', '', 'create') && !has_permission('service_management', '', 'edit')) {
			access_denied('mark contract as signed');
		}

		$this->service_contract_model->mark_as_signed($id);

		redirect(admin_url('service_management/contract/' . $id));
	}

	/**
	 * unmark as signed
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function unmark_as_signed($id)
	{
		if (!has_permission('service_management', '', 'create') && !has_permission('service_management', '', 'edit')) {
			access_denied('mark contract as signed');
		}

		$this->service_contract_model->unmark_as_signed($id);

		redirect(admin_url('service_management/contract/' . $id));
	}

	/**
	 * pdf
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function pdf($id)
	{
		if (!has_permission('service_management', '', 'view') && !has_permission('service_management', '', 'view_own')) {
			access_denied('contracts');
		}

		if (!$id) {
			redirect(admin_url('service_management/contracts'));
		}

		$contract = $this->service_contract_model->get($id);

		try {
			$pdf = contract_pdf($contract);
		} catch (Exception $e) {
			echo $e->getMessage();
			die;
		}

		$type = 'D';

		if ($this->input->get('output_type')) {
			$type = $this->input->get('output_type');
		}

		if ($this->input->get('print')) {
			$type = 'I';
		}

		$pdf->Output(slug_it($contract->subject) . '.pdf', $type);
	}

	/**
	 * send to email
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function send_to_email($id)
	{
		if (!has_permission('service_management', '', 'view') && !has_permission('service_management', '', 'view_own')) {
			access_denied('contracts');
		}
		$success = $this->service_contract_model->send_contract_to_client($id, $this->input->post('attach_pdf'), $this->input->post('cc'));
		if ($success) {
			set_alert('success', _l('contract_sent_to_client_success'));
		} else {
			set_alert('danger', _l('contract_sent_to_client_fail'));
		}
		redirect(admin_url('service_management/contract/' . $id));
	}

	/**
	 * add note
	 * @param [type] $rel_id 
	 */
	public function add_note($rel_id)
	{
		if ($this->input->post() && (has_permission('service_management', '', 'view') || has_permission('service_management', '', 'view_own'))) {
			$this->misc_model->add_note($this->input->post(), 'contract', $rel_id);
			echo $rel_id;
		}
	}

	/**
	 * get notes
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function get_notes($id)
	{
		if ((has_permission('service_management', '', 'view') || has_permission('service_management', '', 'view_own'))) {
			$data['notes'] = $this->misc_model->get_notes($id, 'contract');
			$this->load->view('service_management/includes/sales_notes_template', $data);
		}
	}

	/**
	 * clear signature
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function clear_signature($id)
	{
		if (has_permission('service_management', '', 'delete')) {
			$this->service_contract_model->clear_signature($id);
		}

		redirect(admin_url('service_management/contract/' . $id));
	}

	/**
	 * save contract data
	 * @return [type] 
	 */
	public function save_contract_data()
	{
		if (!has_permission('service_management', '', 'edit')) {
			header('HTTP/1.0 400 Bad error');
			echo json_encode([
				'success' => false,
				'message' => _l('access_denied'),
			]);
			die;
		}

		$success = false;
		$message = '';

		$this->db->where('id', $this->input->post('contract_id'));
		$this->db->update(db_prefix() . 'sm_contracts', [
			'content' => html_purify($this->input->post('content', false)),
		]);

		$success = $this->db->affected_rows() > 0;
		$message = _l('updated_successfully', _l('contract'));

		echo json_encode([
			'success' => $success,
			'message' => $message,
		]);
	}

	/**
	 * add comment
	 */
	public function add_comment()
	{
		if ($this->input->post()) {
			echo json_encode([
				'success' => $this->service_contract_model->add_comment($this->input->post()),
			]);
		}
	}

	/**
	 * edit comment
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function edit_comment($id)
	{
		if ($this->input->post()) {
			echo json_encode([
				'success' => $this->service_contract_model->edit_comment($this->input->post(), $id),
				'message' => _l('comment_updated_successfully'),
			]);
		}
	}

	/**
	 * get comments
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function get_comments($id)
	{
		$data['comments'] = $this->service_contract_model->get_comments($id);
		$this->load->view('service_management/contracts/comments_template', $data);
	}

	/**
	 * remove comment
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function remove_comment($id)
	{
		$this->db->where('id', $id);
		$comment = $this->db->get(db_prefix() . 'contract_comments')->row();
		if ($comment) {
			if ($comment->staffid != get_staff_user_id() && !is_admin()) {
				echo json_encode([
					'success' => false,
				]);
				die;
			}
			echo json_encode([
				'success' => $this->service_contract_model->remove_comment($id),
			]);
		} else {
			echo json_encode([
				'success' => false,
			]);
		}
	}

	/**
	 * renew
	 * @return [type] 
	 */
	public function renew()
	{
		if (!has_permission('service_management', '', 'create') && !has_permission('service_management', '', 'edit')) {
			access_denied('contracts');
		}
		if ($this->input->post()) {
			$data    = $this->input->post();
			$success = $this->service_contract_model->renew($data);
			if ($success) {
				set_alert('success', _l('contract_renewed_successfully'));
			} else {
				set_alert('warning', _l('contract_renewed_fail'));
			}
			redirect(admin_url('service_management/contract/' . $data['contractid'] . '?tab=renewals'));
		}
	}

	/**
	 * delete renewal
	 * @param  [type] $renewal_id 
	 * @param  [type] $contractid 
	 * @return [type]             
	 */
	public function delete_renewal($renewal_id, $contractid)
	{
		$success = $this->service_contract_model->delete_renewal($renewal_id, $contractid);
		if ($success) {
			set_alert('success', _l('contract_renewal_deleted'));
		} else {
			set_alert('warning', _l('contract_renewal_delete_fail'));
		}
		redirect(admin_url('service_management/contract/' . $contractid . '?tab=renewals'));
	}

	/**
	 * copy
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function copy($id)
	{
		if (!has_permission('service_management', '', 'create')) {
			access_denied('contracts');
		}
		if (!$id) {
			redirect(admin_url('service_management/contracts'));
		}
		$newId = $this->service_contract_model->copy($id);
		if ($newId) {
			set_alert('success', _l('contract_copied_successfully'));
		} else {
			set_alert('warning', _l('contract_copied_fail'));
		}
		redirect(admin_url('service_management/contract/' . $newId));
	}

	/* Delete contract from database */
	public function delete($id)
	{
		if (!has_permission('service_management', '', 'delete')) {
			access_denied('contracts');
		}
		if (!$id) {
			redirect(admin_url('service_management/manage_contract'));
		}
		$response = $this->service_contract_model->delete($id);
		if ($response == true) {
			set_alert('success', _l('deleted', _l('contract')));
		} else {
			set_alert('warning', _l('problem_deleting', _l('contract_lowercase')));
		}
		if (strpos($_SERVER['HTTP_REFERER'], 'clients/') !== false) {
			redirect($_SERVER['HTTP_REFERER']);
		} else {
			redirect(admin_url('service_management/manage_contract'));
		}
	}

	/* Manage contract types Since Version 1.0.3 */
	public function type($id = '')
	{
		if (!is_admin() && get_option('staff_members_create_inline_contract_types') == '0') {
			access_denied('contracts');
		}
		if ($this->input->post()) {
			if (!$this->input->post('id')) {
				$id = $this->service_contract_model->add_contract_type($this->input->post());
				if ($id) {
					$success = true;
					$message = _l('added_successfully', _l('contract_type'));
				}
				echo json_encode([
					'success' => $success,
					'message' => $message,
					'id'      => $id,
					'name'    => $this->input->post('name'),
				]);
			} else {
				$data = $this->input->post();
				$id   = $data['id'];
				unset($data['id']);
				$success = $this->service_contract_model->update_contract_type($data, $id);
				$message = '';
				if ($success) {
					$message = _l('updated_successfully', _l('contract_type'));
				}
				echo json_encode([
					'success' => $success,
					'message' => $message,
				]);
			}
		}
	}

	/**
	 * types
	 * @return [type] 
	 */
	public function types()
	{
		if (!is_admin()) {
			access_denied('contracts');
		}
		if ($this->input->is_ajax_request()) {
			$this->app->get_table_data('contract_types');
		}
		$data['title'] = _l('contract_types');
		$this->load->view('service_management/contracts/manage_types', $data);
	}

	/* Delete announcement from database */
	public function delete_contract_type($id)
	{
		if (!$id) {
			redirect(admin_url('service_management/types'));
		}
		if (!is_admin()) {
			access_denied('contracts');
		}
		$response = $this->service_contract_model->delete_contract_type($id);
		if (is_array($response) && isset($response['referenced'])) {
			set_alert('warning', _l('is_referenced', _l('contract_type_lowercase')));
		} elseif ($response == true) {
			set_alert('success', _l('deleted', _l('contract_type')));
		} else {
			set_alert('warning', _l('problem_deleting', _l('contract_type_lowercase')));
		}
		redirect(admin_url('service_management/types'));
	}

	/**
	 * add contract attachment
	 * @param [type] $id 
	 */
	public function add_contract_attachment($id)
	{
		sm_handle_contract_attachment($id);
	}

	/**
	 * add external attachment
	 */
	public function add_external_attachment()
	{
		if ($this->input->post()) {
			$this->misc_model->add_attachment_to_database(
				$this->input->post('contract_id'),
				'sm_contract',
				$this->input->post('files'),
				$this->input->post('external')
			);
		}
	}

	/**
	 * delete contract attachment
	 * @param  [type] $attachment_id 
	 * @return [type]                
	 */
	public function delete_contract_attachment($attachment_id)
	{
		$file = $this->misc_model->get_file($attachment_id);
		if ($file->staffid == get_staff_user_id() || is_admin()) {
			echo json_encode([
				'success' => $this->service_contract_model->delete_contract_attachment($attachment_id),
			]);
		}
	}

	/**
	 * order_detail
	 * @param  [type] $order_id 
	 * @return [type]           
	 */
	public function order_detail($order_id)
	{

		if (!$order_id) {
			redirect(admin_url('service_management/service_managements'));
		}

		$order = $this->service_management_model->get_order($order_id);
		if(!$order){
			blank_page(_l('order_not_found'));
		}

		$order->clientid = $order->client_id;
		$data['title'] = _l('sm_order_detail');
		$data['order'] = $order;
		$data['order']->client = $this->clients_model->get($order->client_id);
		$data['tax_data'] = $this->service_management_model->get_html_tax_order($order_id);
		$get_base_currency =  get_base_currency();
		if($get_base_currency){
			$data['base_currency'] = $get_base_currency->id;
		}else{
			$data['base_currency'] = 0;
		}

		$this->load->view('service_management/service_managements/service_detail', $data);
	}

	/**
	 * order status mark as
	 * @param  [type] $status 
	 * @param  [type] $id     
	 * @param  [type] $type   
	 * @return [type]         
	 */
	public function order_status_mark_as($status, $id, $type)
	{
		$success = $this->service_management_model->order_status_mark_as($status, $id, $type);
		$message = '';

		if ($success) {
			$message = _l('sm_change_order_status_successfully');
		}
		echo json_encode([
			'success'  => $success,
			'message'  => $message
		]);
	}

	/**
	 * create invoice from order
	 * @param  [type] $order_id 
	 * @return [type]           
	 */
	public function create_invoice_from_order($order_id)
	{
		if (!has_permission('service_management', '', 'edit') && !is_admin() && !has_permission('service_management', '', 'create')) {
			access_denied('service_management');
		}

		$invoice_id = $this->service_management_model->create_invoice_from_order($order_id);

		if($invoice_id){
			set_alert('success', _l('sm_create_invoice_successful'));
			redirect(admin_url('invoices#'.$invoice_id));
		}
		set_alert('warning', _l('sm_create_invoice_failed'));
		redirect(admin_url('service_management/service_managements'));
	}

	/**
	 * service managements
	 * @return [type] 
	 */
	public function product_services()
	{
		$data['title'] = _l('sm_services_management');
		$data['product_categories'] = $this->service_management_model->get_item_category();
		$data['clients'] = $this->clients_model->get();
		$data['orders'] = $this->service_management_model->get_order();
		$data['products'] = $this->service_management_model->get_product();

		$this->load->view('service_management/service_managements/client_services/client_service_manage', $data);
	}

	/**
	 * service management table
	 * @return [type] 
	 */
	public function client_service_table()
	{
		$this->app->get_table_data(module_views_path('service_management', 'service_managements/client_services/client_service_table'));
	}

	/**
	 * renewal service
	 * @param  [type] $service_id 
	 * @return [type]             
	 */
	public function renewal_service($service_id)
	{
		if (!$service_id) {
			redirect(admin_url('service_management/product_services'));
		}

		$get_service = $this->service_management_model->get_service($service_id);
		if(!$get_service){
			blank_page(_l('service_not_found'));
		}

		/*check service exists*/
		$get_product_cycle = $this->service_management_model->get_product_cycle($get_service->billing_plan_unit_id, 'status_cycles = "active"');
		$product = $this->service_management_model->get_product($get_service->item_id);

		if(!$get_product_cycle || !$product){
			blank_page(_l('sm_the_service_you_requested_to_renew_has_been_discontinued'));
		}

		$invoice_id = $this->service_management_model->renewal_service($service_id);

		if($invoice_id){
			set_alert('success', _l('sm_create_invoice_successful'));
			redirect(admin_url('invoices#'.$invoice_id));
		}
		set_alert('warning', _l('sm_create_invoice_failed'));
		redirect(admin_url('service_management/product_services'));

	}

	/**
	 * contract addendums table
	 * @param  string $clientid 
	 * @return [type]           
	 */
	public function contract_addendums_table($clientid = '')
	{
		if (!has_permission('service_management', '', 'view') && !has_permission('service_management', '', 'view_own')) {
			ajax_access_denied();
		}
		$this->app->get_table_data(module_views_path('service_management', 'contract_addendums/contract_addendum_table'), [
			'clientid' => $clientid,
		]);
	}

	/**
	 * manage contract addendum
	 * @return [type] 
	 */
	public function manage_contract_addendum()
	{
		close_setup_menu();

		if (!has_permission('service_management', '', 'view') && !has_permission('service_management', '', 'view_own')) {
			access_denied('contracts');
		}

		$this->load->model('currencies_model');
		$data['base_currency'] = $this->currencies_model->get_base_currency();
		$data['title']         = _l('sm_contract_addendum');
		$this->load->view('service_management/contract_addendums/contract_addendum_management', $data);
	}

	/**
	 * contract addendum
	 * @param  string $value 
	 * @return [type]        
	 */
	public function contract_addendum($id = '')
	{
		if ($this->input->post()) {
			if ($id == '') {
				if (!has_permission('service_management', '', 'create')) {
					access_denied('contracts');
				}
				$id = $this->service_contract_model->add_contract_addendum($this->input->post());
				if ($id) {
					set_alert('success', _l('added_successfully', _l('contract')));
					redirect(admin_url('service_management/contract_addendum/' . $id));
				}
			} else {
				if (!has_permission('service_management', '', 'edit')) {
					access_denied('contracts');
				}
				$success = $this->service_contract_model->update_contract_addendum($this->input->post(), $id);
				if ($success) {
					set_alert('success', _l('updated_successfully', _l('contract')));
				}
				redirect(admin_url('service_management/contract_addendum/' . $id));
			}
		}
		$data['contracts'] = $this->service_contract_model->get();
		if ($id == '') {
			$title = _l('add_new', _l('sm_contract_addendum'));

		} else {
			$data['contract_addendum']                 = $this->service_contract_model->get_contract_addendum($id, [], true);
			$data['contract_renewal_history'] = $this->service_contract_model->get_contract_renewal_history($id);
			$data['totalNotes']               = total_rows(db_prefix() . 'notes', ['rel_id' => $id, 'rel_type' => 'contract']);
			if (!$data['contract_addendum'] || (!has_permission('service_management', '', 'view') && $data['contract_addendum']->addedfrom != get_staff_user_id())) {
				blank_page(_l('contract_not_found'));
			}

			$data['contract_merge_fields'] = $this->app_merge_fields->get_flat('sm_contract_addendum', ['other', 'client'], '{email_signature}');

			$title = $data['contract_addendum']->subject;

			$data = array_merge($data);
		}

		if ($this->input->get('customer_id')) {
			$data['customer_id'] = $this->input->get('customer_id');
		}

		$this->load->model('currencies_model');
		$data['base_currency'] = $this->currencies_model->get_base_currency();
		$data['types']         = $this->service_contract_model->get_contract_types();
		$data['title']         = $title;
		$data['bodyclass']     = 'contract';
		$data['id']     = $id;

		$this->load->view('service_management/contract_addendums/add_edit_contract_addendum', $data);
	}

	/**
	 * delete contract addendum
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function delete_contract_addendum($id)
	{
		if (!has_permission('service_management', '', 'delete')) {
			access_denied('contracts');
		}
		if (!$id) {
			redirect(admin_url('service_management/manage_contract_addendum'));
		}
		$response = $this->service_contract_model->delete_contract_addendum($id);
		if ($response == true) {
			set_alert('success', _l('deleted', _l('contract')));
		} else {
			set_alert('warning', _l('problem_deleting', _l('contract_lowercase')));
		}
		if (strpos($_SERVER['HTTP_REFERER'], 'clients/') !== false) {
			redirect($_SERVER['HTTP_REFERER']);
		} else {
			redirect(admin_url('service_management/manage_contract_addendum'));
		}
	}


	/**
	 * save contract data
	 * @return [type] 
	 */
	public function save_contract_addendum_data()
	{
		if (!has_permission('service_management', '', 'edit')) {
			header('HTTP/1.0 400 Bad error');
			echo json_encode([
				'success' => false,
				'message' => _l('access_denied'),
			]);
			die;
		}

		$success = false;
		$message = '';

		$this->db->where('id', $this->input->post('contract_id'));
		$this->db->update(db_prefix() . 'sm_contract_addendums', [
			'content' => html_purify($this->input->post('content', false)),
		]);

		$success = $this->db->affected_rows() > 0;
		$message = _l('updated_successfully', _l('contract'));

		echo json_encode([
			'success' => $success,
			'message' => $message,
		]);
	}

	/**
	 * add contract addendum attachment
	 * @param [type] $id 
	 */
	public function add_contract_addendum_attachment($id)
	{
		sm_handle_contract_addendum_attachment($id);
	}

	/**
	 * contract_file
	 * @param  [type] $folder_indicator 
	 * @param  string $attachmentid     
	 * @return [type]                   
	 */
	public function contract_file($folder_indicator, $attachmentid = '')
	{
		if ($folder_indicator == 'sm_contract') {
			if (!$attachmentid) {
				show_404();
			}

			$this->db->where('attachment_key', $attachmentid);
			$attachment = $this->db->get(db_prefix() . 'files')->row();
			if (!$attachment) {
				show_404();
			}

			if (!is_staff_logged_in()) {
				$this->db->select('not_visible_to_client');
				$this->db->where('id', $attachment->rel_id);
				$contract = $this->db->get(db_prefix() . 'sm_contracts')->row();
				if ($contract->not_visible_to_client == 1) {
					show_404();
				}
			}

			$path = SM_CONTRACT_FOLDER . $attachment->rel_id . '/' . $attachment->file_name;
		} elseif ($folder_indicator == 'contract_addendum') {
			if (!$attachmentid) {
				show_404();
			}

			$this->db->where('attachment_key', $attachmentid);
			$attachment = $this->db->get(db_prefix() . 'files')->row();
			if (!$attachment) {
				show_404();
			}

			if (!is_staff_logged_in()) {
				$this->db->select('not_visible_to_client');
				$this->db->where('id', $attachment->rel_id);
				$contract = $this->db->get(db_prefix() . 'sm_contract_addendums')->row();
				if ($contract->not_visible_to_client == 1) {
					show_404();
				}
			}

			$path = SM_CONTRACT_ADDENDUM_FOLDER . $attachment->rel_id . '/' . $attachment->file_name;
		}else{
			die('folder not specified');
		}

		$path = hooks()->apply_filters('download_file_path', $path, [
			'folder'       => $folder_indicator,
			'attachmentid' => $attachmentid,
		]);

		force_download($path, null);
	}

	/**
	 * delete contract addendum attachment
	 * @param  [type] $attachment_id 
	 * @return [type]                
	 */
	public function delete_contract_addendum_attachment($attachment_id)
	{
		$file = $this->misc_model->get_file($attachment_id);
		if ($file->staffid == get_staff_user_id() || is_admin()) {
			echo json_encode([
				'success' => $this->service_contract_model->delete_contract_addendum_attachment($attachment_id),
			]);
		}
	}

	/**
	 * contract addendum pdf
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function contract_addendum_pdf($id)
	{
		if (!has_permission('service_management', '', 'view') && !has_permission('service_management', '', 'view_own')) {
			access_denied('contracts');
		}

		if (!$id) {
			redirect(admin_url('service_management/manage_contract_addendum'));
		}

		$contract = $this->service_contract_model->get_contract_addendum($id);
		try {
			$pdf = contract_pdf($contract);
		} catch (Exception $e) {
			echo $e->getMessage();
			die;
		}

		$type = 'D';

		if ($this->input->get('output_type')) {
			$type = $this->input->get('output_type');
		}

		if ($this->input->get('print')) {
			$type = 'I';
		}

		$pdf->Output(slug_it($contract->subject) . '.pdf', $type);
	}

	/**
	 * template
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function template($id = null)
	{
		$content = $this->input->post('content', false);
		$content = html_purify($content);

		$data['name']      = $this->input->post('name');
		$data['content']   = $content;
		$data['addedfrom'] = get_staff_user_id();
		$data['type']      = $this->input->post('rel_type');

        // so when modal is submitted, it returns to the proposal/contract that was being edited.
		$rel_id = $this->input->post('rel_id');

		if (is_numeric($id)) {
			$this->authorize($id);
			$success = $this->templates_model->update($id, $data);
			$message = _l('template_updated');
		} else {
			$success = $this->templates_model->create($data);
			$message = _l('template_added');
		}

		if ($success) {
			set_alert('success', $message);
		}

		redirect(
			$data['type'] == 'contracts' ?
			admin_url('service_management/contract/' . $id) : 
			admin_url('proposals/list_proposals/' . $rel_id)
		);
	}

	/**
	 * product management
	 * @param  string $id 
	 * @return [type]     
	 */
	public function subscription_services_management($id = '')
	{
		$data['title'] = _l('sm_subscription_service_management');
		$data['products_services'] = $this->service_management_model->get_subscription_product();
		$data['product_id'] = $id;
		$data['product_categories'] = $this->service_management_model->get_item_category();
		$this->load->view('subscription_services/subscription_services_management', $data);
	}


	/**
	 * add edit product
	 * @param [type] $type : product or product variant
	 * @param string $id   
	 */
	public function add_edit_subscription($id = '')
	{
		if (!has_permission('service_management', '', 'view')  && !is_admin()) {
			access_denied('work_center');
		}
		if ($this->input->post()) {
			$data = $this->input->post();
			$stripe_plan_id = $this->service_management_model->get_value_from_data($data, 'stripe_plan_id');
			$plan = $this->stripe_subscriptions->get_plan($stripe_plan_id);
			$data['service_policy'] = json_decode($this->input->post('service_policy', false));
			$data['subscription_price'] = strcasecmp($plan->currency, 'JPY') == 0 ? $plan->amount : $plan->amount / 100;
			$data['subscription_period'] = $plan->interval;
			$data['subscription_count'] = $plan->interval_count;
			$url = admin_url('service_management/subscription_services_management');
			if ($id == '') {
				if (!has_permission('service_management', '', 'create') && !is_admin()) {
					access_denied('work_center');
				}
				$insert_id = $this->service_management_model->add_subscription_service($data);
				if ($insert_id) {
					set_alert('success', _l('mrp_added_successfully'));
					/*upload multifile*/
					echo json_encode([
						'url' => $url,
						'commodityid' => $insert_id,
						'add_or_update' => 'add',

					]);
					die;
				}
				set_alert('warning', _l('mrp_added_failed'));
				echo json_encode([
					'url' => $url,
					'add_or_update' => 'add',

				]);
				die;
			} else {
				if (!has_permission('service_management', '', 'edit') && !is_admin()) {
					access_denied('work_center');
				}
				$success = $this->service_management_model->update_subscription_service($data, $id);
				/*update file*/
				set_alert('success', _l('mrp_updated_successfully'));
				echo json_encode([
					'url' => $url,
					'commodityid' => $id,
					'add_or_update' => 'update',

				]);
				die;
			}
		}
		$data=[];
		$data['title'] = _l('sm_add_subscription_service');
		if ($id != ''){
			$data['product'] = $this->service_management_model->get_product($id);
			$data['product_attachments'] = $this->service_management_model->sm_get_attachments_file($id, 'commodity_item_file');
			$data['title'] = _l('sm_edit_subscription_service');
			$data['total_billing_plan'] = count($data['product']->item_billing_plan)+1;
		}
		$data['product_group'] = $this->service_management_model->get_item_category('', true);
		$data['units'] = $this->service_management_model->get_item_unit('', true);
		$data['taxes'] = sm_get_taxes();
		try {
			$data['plans'] = $this->stripe_subscriptions->get_plans();
			$this->load->library('stripe_core');
			$data['stripe_tax_rates'] = $this->stripe_core->get_tax_rates();
		} catch (Exception $e) {
			if ($this->stripe_subscriptions->has_api_key()) {
				$data['product_error'] = $e->getMessage();
			} else {
				$data['product_error'] = _l('api_key_not_set_error_message', '<a href="' . admin_url('settings?group=payment_gateways&tab=online_payments_stripe_tab') . '">Stripe Checkout</a>');
			}
		}
		$this->load->view('subscription_services/add_edit_subscription', $data);
	}


	/**
	* subscription services table
	* @return [type] 
	*/
	public function subscription_services_table()
	{
		$this->app->get_table_data(module_views_path('service_management', 'subscription_services/subscription_services_table'));
	}

	public function create_subscription()
	{
		if (!has_permission('subscriptions', '', 'create')) {
			access_denied('Subscriptions Create');
		}

		if ($this->input->post()) {
			$this->load->model('subscriptions_model');
            $subscriptionProduct = $this->service_management_model->get_product($this->input->post('product_id'));

			$insert_id = $this->subscriptions_model->create([
				'name'                => $this->input->post('name'),
				'description'         => nl2br($this->input->post('description')),
				'description_in_item' => $this->input->post('description_in_item') ? 1 : 0,
				'date'                => $this->input->post('date') ? to_sql_date($this->input->post('date')) : null,
				'clientid'            => $this->input->post('clientid'),
				'project_id'          => $this->input->post('project_id') ? $this->input->post('project_id') : 0,
				'stripe_plan_id'      => $this->input->post('stripe_plan_id'),
				'quantity'            => $this->input->post('quantity'),
				'terms'               => nl2br($this->input->post('terms')),
				'stripe_tax_id'       => $this->input->post('stripe_tax_id') ? $this->input->post('stripe_tax_id') : false,
				'stripe_tax_id_2'     => $this->input->post('stripe_tax_id_2') ? $this->input->post('stripe_tax_id_2') : false,
				'currency'            => $this->input->post('currency'),
			]);

			if ($insert_id) {

				$purchaseData = [];
				$purchaseData['subscription_id'] = $insert_id;
				$purchaseData['client_id'] = $this->input->post('clientid');
				$purchaseData['product_id'] = $this->input->post('product_id');
				$purchaseData['datecreated'] = date('Y-m-d H:i:s');

				$this->service_management_model->add_subscription_order($purchaseData);

				$this->subscriptions_model->update($insert_id, ['created_from' => $subscriptionProduct->created_from]);
				$hash = $this->subscriptions_model->get_by_id($insert_id)->hash;
			}

			set_alert('success', _l('added_successfully', _l('subscription')));
			redirect(admin_url('service_management/service_managements'));
		}

		$data['plans'] = [];

		try {
			$data['plans'] = $this->stripe_subscriptions->get_plans();
			$this->load->library('stripe_core');
			$data['stripe_tax_rates'] = $this->stripe_core->get_tax_rates();
		} catch (Exception $e) {
			if ($this->stripe_subscriptions->has_api_key()) {
				$data['subscription_error'] = $e->getMessage();
			} else {
				$data['subscription_error'] = _l('api_key_not_set_error_message', '<a href="' . admin_url('settings?group=payment_gateways&tab=online_payments_stripe_tab') . '">Stripe Checkout</a>');
			}
		}

		if ($this->input->get('customer_id')) {
			$data['customer_id'] = $this->input->get('customer_id');
		}

		$data['title'] = _l('add_new', _l('subscription_lowercase'));
		$this->load->model('taxes_model');
		$this->load->model('currencies_model');
		$data['taxes']      = $this->taxes_model->get();
		$data['currencies'] = $this->currencies_model->get();
		$data['bodyclass']  = 'subscription';
		$data['subscription_services'] = $this->service_management_model->get_subscription_product();
		$this->load->view('service_managements/subscriptions/add_subscription', $data);
	}

	/**
	 * get product plan
	 * @return [type] 
	 */
	public function get_product_plan() {
		$data = $this->input->post();
		if ($data != 'null') {
			$stripe_plan_id = '';
			$message = '';
			$subscription_name = '';

			$subscription_product = $this->service_management_model->get_subscription_product($data['product_id']);
			if($subscription_product){
				if(new_strlen($subscription_product->stripe_plan_id) > 0){
					$stripe_plan_id = $subscription_product->stripe_plan_id;
					$subscription_name = $subscription_product->commodity_code.' '.$subscription_product->description;
				}else{
					$message = _l('sm_billing_plan_of_subscription_service_does_not_exist');
				}
			}else {
				$message = _l('sm_subscription_service_does_not_exist');
			}

			echo json_encode([
				'message' => $message,
				'stripe_plan_id' => $stripe_plan_id,
				'subscription_name' => $subscription_name,
			]);
			die;
		}
	}

	/*end file*/
}