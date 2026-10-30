<?php

defined('BASEPATH') or exit('No direct script access allowed');
/**
 * This class describes a warehouse client.
 */
class Service_management_client extends ClientsController
{

	/**
	 * __construct description
	 */
	public function __construct()
	{
		parent::__construct();
		$this->load->model('service_management_model');
		$this->load->model('service_contract_model');
		$this->load->helper('download');

		if(get_option('service_management_display_on_portal') != 1){
			 set_alert('warning', _l('access_denied'));
			 redirect(site_url('clients'));
		}

		if(!is_client_logged_in() && !is_staff_logged_in()){ 
			redirect_after_login_to_current_url();
			redirect(site_url('authentication/login'));
		}
	}

	/**
	 * service managements
	 * @param  boolean $status 
	 * @return [type]          
	 */
	public function service_managements($status = false)
	{

		if($status){
			$data['client_services'] = $this->service_management_model->get_service(false, 'client_id = '.get_client_user_id().' AND status = "'.$status.'"');
		}else{
			$data['client_services'] = $this->service_management_model->get_service(false, 'client_id = '.get_client_user_id());
		}

		$data['service_status'] = $this->service_management_model->count_service_by_status(get_client_user_id());

		$get_base_currency =  get_base_currency();
		if($get_base_currency){
			$base_currency_id = $get_base_currency->id;
		}else{
			$base_currency_id = 0;
		}
		$data['base_currency_id'] = $base_currency_id;

		$data['title']    = _l('sm_services_management');
		$this->data($data);
		$this->view('client_portals/service_managements/service_management');

		$this->layout();
	}

	/**
	 * client_contract
	 * @param  [type] $id   
	 * @param  [type] $hash 
	 * @return [type]       
	 */
	public function client_contract($id, $hash)
	{
		sm_check_contract_restrictions($id, $hash);
		$contract = $this->service_contract_model->get($id);

		if (!$contract) {
			show_404();
		}


		if ($this->input->post()) {
			$action = $this->input->post('action');

			switch ($action) {
				case 'contract_pdf':
				$pdf = contract_pdf($contract);
				$pdf->Output(slug_it($contract->subject . '-' . get_option('companyname')) . '.pdf', 'D');

				break;
				case 'sign_contract':
				process_digital_signature_image($this->input->post('signature', false), SM_CONTRACTS_UPLOADS_FOLDER . $id);
				$this->db->where('id', $id);
				$this->db->update(db_prefix().'sm_contracts', array_merge(get_acceptance_info_array(), [
					'signed' => 1,
				]));

					// Notify contract creator that customer signed the contract
				send_contract_signed_notification_to_staff($id);

				set_alert('success', _l('document_signed_successfully'));
				redirect($_SERVER['HTTP_REFERER']);

				break;
				case 'contract_comment':
					// comment is blank
				if (!$this->input->post('content')) {
					redirect($this->uri->uri_string());
				}
				$data                = $this->input->post();
				$data['contract_id'] = $id;
				$this->service_contract_model->add_comment($data, true);
				redirect($this->uri->uri_string() . '?tab=discussion');

				break;
			}
		}

		$this->disableNavigation();
		$this->disableSubMenu();

		$data['title']     = $contract->subject;
		$data['contract']  = hooks()->apply_filters('contract_html_pdf_data', $contract);
		$data['bodyclass'] = 'contract contract-view';
		$data['contract_addendums'] = $this->service_contract_model->get_contract_addendum(false, 'contract_id = '.$id.' AND trash = 0 AND not_visible_to_client = 0');

		$data['identity_confirmation_enabled'] = true;
		$data['bodyclass'] .= ' identity-confirmation';
		$this->app_scripts->theme('sticky-js','assets/plugins/sticky/sticky.js');
		$data['comments'] = $this->service_contract_model->get_comments($id);
		hooks()->do_action('contract_html_viewed', $id);
		$this->app_css->remove('reset-css','customers-area-default');
		$data                      = hooks()->apply_filters('contract_customers_area_view_data', $data);
		$this->data($data);
		no_index_customers_area();
		$this->view('contracts/contracthtml');


		$this->layout();
	}

	/**
	 * renewal service
	 * @param  [type] $service_id 
	 * @return [type]             
	 */
	public function renewal_service($service_id)
	{
		if (!$service_id) {
			redirect(site_url('service_management/service_management_client/service_managements'));

		}

		$get_service = $this->service_management_model->get_service($service_id);
		if(!$get_service){
			redirect(site_url('service_management/service_management_client/service_managements'));
		}

		/*check service exists*/
		$get_product_cycle = $this->service_management_model->get_product_cycle($get_service->billing_plan_unit_id, 'status_cycles = "active"');
		$product = $this->service_management_model->get_product($get_service->item_id);

		if(!$get_product_cycle || !$product){
			set_alert('warning', _l('sm_the_service_you_requested_to_renew_has_been_discontinued'));
			redirect(site_url('service_management/service_management_client/service_managements'));
		}

		$invoice_id = $this->service_management_model->renewal_service($service_id);

		if($invoice_id){
			$this->load->model('invoices_model');
			$invoices = $this->invoices_model->get($invoice_id);
			set_alert('success', _l('sm_create_invoice_successful'));
			redirect(site_url('invoice/'.$invoice_id.'/'.$invoices->hash));
		}
		set_alert('warning', _l('sm_create_invoice_failed'));
		redirect(site_url('service_management/service_management_client/service_managements'));

	}

	/**
	 * order managements
	 * @param  boolean $status 
	 * @return [type]          
	 */
	public function order_managements($status = false)
	{

		if($status){
			$data['client_orders'] = $this->service_management_model->get_order(false, 'client_id = '.get_client_user_id().' AND status = "'.$status.'"');
		}else{
			$data['client_orders'] = $this->service_management_model->get_order(false, 'client_id = '.get_client_user_id());
		}

		$get_base_currency =  get_base_currency();
		if($get_base_currency){
			$base_currency_id = $get_base_currency->id;
		}else{
			$base_currency_id = 0;
		}
		$data['base_currency_id'] = $base_currency_id;

		$data['title']    = _l('sm_order_management');
		$this->data($data);
		$this->view('client_portals/orders/order_management');

		$this->layout();
	}

	/**
	 * order detail
	 * @param  [type] $order_id 
	 * @return [type]           
	 */
	public function order_detail($order_id)
	{

		if (!$order_id) {
			redirect(site_url('service_management/service_management_client/order_managements'));

		}

		$order = $this->service_management_model->get_order($order_id);
		if(!$order){
			redirect(site_url('service_management/service_management_client/order_managements'));
		}

		if($order->client_id != get_client_user_id()){
			redirect(site_url('service_management/service_management_client/order_managements'));
		}

		$order->clientid = $order->client_id;
		$data['title'] = _l('sm_order_detail');
		$data['order'] = $order;
		$data['order']->client = $this->clients_model->get($order->client_id);
		$data['tax_data'] = $this->service_management_model->get_html_tax_order($order_id);
		$data['service_details'] = $this->service_management_model->get_service(false, 'order_id = '. $order_id);

		$get_base_currency =  get_base_currency();
		if($get_base_currency){
			$data['base_currency'] = $get_base_currency->id;
		}else{
			$data['base_currency'] = 0;
		}

		$this->data($data);
		$this->view('client_portals/orders/order_detail');
		$this->layout();
	}

	/**
	 * contract managements
	 * @return [type] 
	 */
	public function contract_managements()
	{

		$data['contracts'] = $this->service_contract_model->get(false, 'client = '.get_client_user_id().' AND trash = 0 AND not_visible_to_client = 0');
		$this->load->model('currencies_model');
		$data['base_currency'] = $this->currencies_model->get_base_currency();
		$data['title']         = _l('contracts');

		$this->data($data);
		$this->view('client_portals/contracts/contract_management');
		$this->layout();
	}

	/**
	 * contract file
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

			if (!is_client_logged_in()) {
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

			if (!is_client_logged_in()) {
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
	 * contract addendum pdf
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function contract_addendum_pdf($id)
	{
		
		if (!$id) {
			redirect(site_url('service_management/service_management_client/contract_managements'));
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
	 * sm products services
	 * @return [type] 
	 */
	public function products_service_managements($page= 1,$id = 0, $warehouse = 0,$key = '')
	{

		
			$warehouse = 0;
			if($page == '' || !is_numeric($page)){
				$page = 1;
			}
			if($id == ''|| !is_numeric($id)){
				$id = 0;
			}
			if($key != ''){
				$key = trim(urldecode($key));
				$data['keyword'] = $key;
			}
			
			$data['ofset'] = 24;
			$data['title'] = _l('sales');
			$data['group_product'] = $this->service_management_model->get_item_category();      
			$data['group_id'] = $id;
			$data_product = $this->service_management_model->get_list_product_by_group(2, $id, $warehouse, $key,($page-1)*$data['ofset'], $data['ofset']);
			$arr_billing_plan = $data_product['arr_billing_plan'];
			$data['product'] = $data_product['list_product'];
			$date = date('Y-m-d');
			foreach ($data['product'] as $key => $item) {
				if(isset($arr_billing_plan[$item['id']])){
					$data['product'][$key]['billing_plans'] = $arr_billing_plan[$item['id']];
				}else{
					$data['product'][$key]['billing_plans'] = [];
				}
			}

			$data['title_group'] = _l('all_products');
			$data['page'] = $page;
			$data['ofset_count'] = $data_product['count'];
			$data['total_page'] = ceil($data['ofset_count']/$data['ofset']);
			$this->load->model('currencies_model');
			$data['base_currency'] = $this->currencies_model->get_base_currency();


		$data['title']         = _l('sm_products_services');

		$this->data($data);
		$this->view('client_portals/products/product_management');
		$this->layout();
	}

	/**
	 * get billing plan list
	 * @param  [type] $product_id 
	 * @return [type]             
	 */
	public function get_billing_plan_list($product_id){
		$html = '';
		$product = $this->service_management_model->get_product($product_id);
		$this->load->model('currencies_model');
		$data['base_currency'] = $this->currencies_model->get_base_currency();
		$data['item_billing_plan'] = $product->item_billing_plan;

		if($product){
			$html = $this->load->view('client_portals/products/includes/list_billing_plan', $data, true);
		}
		echo json_encode($html);
		die;
	}

	/**
	 * view_cart
	 * @param  string $id 
	 * @return [type]     
	 */
	public function view_cart($id = ''){
		$this->load->model('currencies_model');
		$client_user_id = get_client_user_id();
		$data['base_currency'] = $this->currencies_model->get_base_currency();
		$data['title'] = _l('cart');
		$data['logged'] = $id;
		$client = $this->clients_model->get(get_client_user_id());
		$data['clients'] = $this->clients_model->get('', db_prefix().'clients.userid = '.get_client_user_id());
		$data['contacts'] = $this->clients_model->get_contacts(get_client_user_id(), db_prefix().'contacts.id = '.get_contact_user_id());
		$data['client'] = $client;	

		$data['order_code'] = 'ORDER'.date('YmdHi');
		$get_base_currency =  get_base_currency();
		if($get_base_currency){
			$data['base_currency_id'] = $get_base_currency->id;
		}else{
			$data['base_currency_id'] = 0;
		}

		$service_row_template = '';
		if(isset($_COOKIE['service_id_list']) && new_strlen($_COOKIE['service_id_list']) > 0){
			$item_billing_id = $_COOKIE['service_id_list'];
			$item_id_quantity = $_COOKIE['service_qty_list'];

			$this->db->where('id IN('.$item_billing_id.')');
			$item_billing_plan = $this->db->get(db_prefix().'sm_items_cycles')->result_array();

			$arr_items = [];
			$sql_where = 'SELECT * from '.db_prefix().'items WHERE id IN (select item_id from '.db_prefix().'sm_items_cycles where id IN('.$item_billing_id.'))';
			$items = $this->db->query($sql_where)->result_array();
			foreach ($items as $key => $value) {
				$arr_items[$value['id']] = $value;
			}

			/*get tax data*/
			$this->load->model('taxes_model');
			$arr_tax = [];
			$taxes = $this->taxes_model->get();
			foreach ($taxes as $value) {
			    $arr_tax[$value['id']] = $value;
			}

			$order_index = 0;
			$item_id_quantity = new_explode(',', $item_id_quantity);

			foreach ($item_billing_plan as $key => $billing_value) {
				if($billing_value['status_cycles'] == 'active' && isset($arr_items[$billing_value['item_id']])){
					$order_index++;
					$quantity = isset($item_id_quantity[$key]) ? (int)$item_id_quantity[$key] : 1;
					$billing_item = $arr_items[$billing_value['item_id']];
					$item_name = $billing_item['commodity_code'].'_'.$billing_item['description'];
					$item_id = $billing_item['id'];

					$extend_value = 0;
					$discount = 0;
					$taxname = '';
					$label_value = [];
					$tax_str = '';

					if(is_numeric($billing_item['tax']) && $billing_item['tax']){
						if(isset($arr_tax[$billing_item['tax']])){
							$taxname = [];
							$taxname[] = $arr_tax[$billing_item['tax']]['name'].'|'.$arr_tax[$billing_item['tax']]['taxrate'];
							$tax_str .= $arr_tax[$billing_item['tax']]['taxrate'].'%';
						}
					}

					if(is_numeric($billing_item['tax2']) && $billing_item['tax2']){
						if(isset($arr_tax[$billing_item['tax']])){
							if(is_array($taxname)){
								$taxname[] = $arr_tax[$billing_item['tax2']]['name'].'|'.$arr_tax[$billing_item['tax2']]['taxrate'];
							}else{
								$taxname = [];
								$taxname[] = $arr_tax[$billing_item['tax2']]['name'].'|'.$arr_tax[$billing_item['tax2']]['taxrate'];
							}
							$tax_str .= ','.$arr_tax[$billing_item['tax2']]['taxrate'].'%';
						}
					}

					$label_value[] = app_format_money((float)$billing_value['item_rate'], $data['base_currency_id']).' ('. $billing_value['unit_value'].' '. _l($billing_value['unit_type']) . ')';
					$label_value[] = $tax_str;

					if($this->service_management_model->sm_check_the_next_order($billing_value['extend_value'], $billing_value['item_id'], $client_user_id)){
						$extend_value = $billing_value['extend_value'];
						$discount = $billing_value['promotion_extended_percent'];
					}

					$service_row_template .= $this->service_management_model->create_service_row_template('newitems[' . $order_index . ']', $item_name, $item_id, $billing_value['id'], $billing_value['unit_value'], $billing_value['unit_type'], $billing_value['item_rate'], $quantity, $taxname, '', '', '', '', '', $discount, '', '', $key, false, true, $label_value);

				}
			}
		}

		$data['service_row_template'] = $service_row_template;

		$this->data($data);
		$this->view('client_portals/orders/add_edit_order');
		$this->layout();
	}

	/**
	 * get_currency
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function get_currency($id)
	{
		echo json_encode(get_currency($id));
	}

	/**
	 * add_order
	 * @param string $id 
	 */
	public function add_order($id = '')
	{
		
		if ($this->input->post()) {
			$data = $this->input->post();
			$id = $this->input->post('id');

			if ($id == '') {
				

				$insert_id = $this->service_management_model->add_order($data);
				if ($insert_id) {
					set_alert('success', _l('sm_added_successfully'));
				}
				redirect(site_url('service_management/service_management_client/order_detail/'.$insert_id));

			} else {
				
				$success = $this->service_management_model->update_order($data, $id);
				/*update file*/
				set_alert('success', _l('sm_updated_successfully'));
				redirect(site_url('service_management/service_management_client/order_detail/'.$id));

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
		if (total_rows(db_prefix() . 'items', db_prefix().'items.can_be_product_service = "can_be_product_service"') <= sm_ajax_on_total_items()) {
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
		$data['order_code'] = 'ORDER'.date('YmdHi');
		$get_base_currency =  get_base_currency();
		if($get_base_currency){
			$data['base_currency_id'] = $get_base_currency->id;
		}else{
			$data['base_currency_id'] = 0;
		}

		$this->load->view('service_management/service_managements/add_edit_service_management', $data);
	}

	/**
	 * create invoice from order
	 * @param  [type] $order_id 
	 * @return [type]           
	 */
	public function create_invoice_from_order($order_id)
	{
		$invoice_id = $this->service_management_model->create_invoice_from_order($order_id);

		if($invoice_id){
			set_alert('success', _l('sm_create_invoice_successful'));
			redirect(site_url('invoice/'.$invoice_id.'/'.sm_get_invoice_hash($invoice_id)));
		}
		set_alert('warning', _l('sm_create_invoice_failed'));
		redirect(site_url('service_management/service_management_client/order_detail/'.$order_id));
	}

	/**
	 * get_product_by_group
	 * @param  integer $page      
	 * @param  integer $id        
	 * @param  integer $warehouse 
	 * @param  string  $key       
	 * @return [type]             
	 */
	public function get_product_by_group($page= 1,$id = 0, $warehouse = 0,$key = '')
	{
		$data['ofset'] = 24;          
		$data_product = $this->service_management_model->get_list_product_by_group(2,$id, $warehouse, $key,($page-1)*$data['ofset'],$data['ofset']);
		$arr_billing_plan = $data_product['arr_billing_plan'];
		$data['product'] = $data_product['list_product'];

		foreach ($data['product'] as $key => $item) {
			if(isset($arr_billing_plan[$item['id']])){
				$data['product'][$key]['billing_plans'] = $arr_billing_plan[$item['id']];
			}else{
				$data['product'][$key]['billing_plans'] = [];
			}
		}

		$data['title_group'] = '';
		$this->load->model('currencies_model');
		$data['base_currency'] = $this->currencies_model->get_base_currency();
		$html = $this->load->view('client_portals/products/includes/list_product_partial',$data,true);

		echo json_encode([
			'data'=>$html
		]);
		die;
	} 

	/**
	 * search product
	 * @param  [type] $group_id 
	 * @return [type]           
	 */
	public function search_product($group_id){
		if($this->input->post()){
			$data = $this->input->post();

			redirect(site_url('service_management/service_management_client/products_service_managements/1/'.$group_id.'/0/'.$data['keyword']));  

		}
	}

	/**
	 * detail
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function detail($id)
	{
		$this->load->model('currencies_model');
		$data['base_currency'] = $this->currencies_model->get_base_currency();          
		$date = date('Y-m-d');
		$data['detailt_product'] = $this->service_management_model->get_product($id);
		$product_category_name = '';

		$group_id = 0;
		$group_name = '';

		if($data['detailt_product']){
			$group_id = $data['detailt_product']->group_id;

			$sm_get_group_name = sm_get_group_name($data['detailt_product']->group_id);
			if($sm_get_group_name){
				$product_category_name = $sm_get_group_name->name;
			}
		}
		$data['product_category_name'] = $product_category_name;

		$data['group_id'] = $group_id;

		$max_product = 15;
		$count_product = 0;
		$data_product  = $this->service_management_model->sm_get_list_product_by_group_s(2,$group_id,$id,0,$max_product);

		$data['product'] = [];
		$data['price']  = 0;
		$data_prices = new stdClass;

		$discount_percent = 0;

		$date = date('Y-m-d');
		$data['product'] = $data_product['list_product'];

		if($data_product){

			$count_product = $data_product['count'];


			$data_product  = $this->service_management_model->sm_get_list_product_by_group_s(2,$data['group_id'],$id,0,$max_product);

			foreach ($data_product['list_product'] as $item) {

				$arr_billing_plan = $data_product['arr_billing_plan'];
				foreach ($data['product'] as $key => $item) {
					if(isset($arr_billing_plan[$item['id']])){
						$data['product'][$key]['billing_plans'] = $arr_billing_plan[$item['id']];
					}else{
						$data['product'][$key]['billing_plans'] = [];
					}
				}
			}


		}

		$list_billing_plan = '';
		$product = $this->service_management_model->get_product($id);
		$this->load->model('currencies_model');
		$data['base_currency'] = $this->currencies_model->get_base_currency();
		$data['item_billing_plan'] = $product->item_billing_plan;

		if($product){
			$list_billing_plan = $this->load->view('client_portals/products/includes/list_billing_plan', $data, true);
		}
		$data['list_billing_plan'] = $list_billing_plan;
		$this->data($data);
		$this->view('client_portals/products/product_detail');
		$this->layout();
	}


	/**
     * Creates a subscription in ARGWS CRM when a customer subscribes through the client portal.
     * 
     */
    public function subscribe()
    {

    	$this->load->model('subscriptions_model');
        $this->form_validation->set_rules('product_id', _l('product'), 'required');
        $this->form_validation->set_rules('quantity', 'quantity', 'required');

        $base_currency = get_base_currency();

  		$clientid = get_client_user_id();

        if ($this->input->post()) {
            $subscriptionProduct = $this->service_management_model->get_product($this->input->post('product_id'));
            $insert_id = $this->subscriptions_model->create([
                'name'                => $subscriptionProduct->description,
                'description'         => $subscriptionProduct->long_description,
                'description_in_item' => $subscriptionProduct->long_description,
                'date'                => null,
                'clientid'            => $clientid,
                'project_id'          => 0,
                'stripe_plan_id'      => $subscriptionProduct->stripe_plan_id,
                'quantity'            => $this->input->post('quantity'),
                'terms'               => $subscriptionProduct->service_policy,
                'stripe_tax_id'       => $subscriptionProduct->tax ? $subscriptionProduct->tax : false,
                'stripe_tax_id_2'     => $subscriptionProduct->tax2 ? $subscriptionProduct->tax2 : false,
                'currency'            => $base_currency->id,
            ]);

            if ($insert_id) {
            	$hash = '';
            	$this->db->where('id', $insert_id);
            	$subscriptions_data = $this->db->get(db_prefix().'subscriptions')->row();
            	if($subscriptions_data){
            		$hash = $subscriptions_data->hash;
            	}

                $purchaseData = [];
                $purchaseData['subscription_id'] = $insert_id;
                $purchaseData['client_id'] = $clientid;
                $purchaseData['product_id'] = $this->input->post('product_id');
                $purchaseData['datecreated'] = date('Y-m-d H:i:s');

                $this->service_management_model->add_subscription_order($purchaseData);
                redirect(site_url('subscription/' . $hash));
            }
        }
    }

}
