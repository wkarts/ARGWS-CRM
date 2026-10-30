<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Loyalty Controller
 */
class invoices_builder extends AdminController {

	/**
	 * Constructs a new instance.
	 */
	public function __construct() {
		parent::__construct();		
		$this->load->model('invoices_builder_model');
        hooks()->do_action('invoices_builder_init');
	}

	/**
	 * { template }
	 */
	public function templates(){		
		$data['title'] = _l('ib_templates');
		$this->load->view('templates/manage', $data);
	}


	/**
	 * { template }
	 */
	public function template($id = ''){
		$data['title'] = _l('ib_building_template');
		if($this->input->post()){
			$data = $this->input->post();
			if($data['id'] == ''){
				unset($data['id']);
				$res = $this->invoices_builder_model->add_template($data);
				if($res > 0){
					set_alert('success', _l('added_successfully'));
				}
				else{
					set_alert('danger', _l('add_failed'));
				}
			}
			else{
				$res = $this->invoices_builder_model->update_template($data, $data['id']);
				if($res == true){
					set_alert('success', _l('updated_successfully'));
				}
				else{
					set_alert('danger', _l('update_failed'));
				}
			}
			redirect(admin_url('invoices_builder/templates'));
		}
        $this->load->model('currencies_model');
		$data['template'] = new stdClass();
		if($id != ''){
			$data_template = $this->invoices_builder_model->get_template($id);
			$data['templates'] = $data_template;
			if($data_template){
				$data['template'] = json_decode($data_template->setting);	
			}
			$data['title'] = _l('ib_edit_template');
		}
        else{
            $data['template'] = json_decode(default_template());   
        }

        $list = [];
        $data_customfield = $this->invoices_builder_model->get_custom_field('','type = "item_table" AND value REGEXP \'^-?[0-9]+$\'', 'name, value');
        foreach ($data_customfield as $key => $value) {
            $list[] = ['id' => 'item_table|'.$value['name'], 'value' => $value['value']];
        }
        $data['item_table_custom_field_value'] = json_encode($list);

        $list = [];
        $data_customfield = $this->invoices_builder_model->get_custom_field('','(type = "item_table" OR type = "invoice_total") AND value REGEXP \'^-?[0-9]+$\'', 'name, value');
        foreach ($data_customfield as $key => $value) {
            $list[] = ['id' => 'invoice_total|'.$value['name'], 'value' => $value['value']];
        }
        $data['invoice_total_custom_field_value'] = json_encode($list);

        $base_currency = $this->currencies_model->get_base_currency();
        $data['currency_name'] = $base_currency->name;
		$data['id'] = $id;
		$this->load->view('templates/template', $data);
	}

	/**
	 * { table templates }
	 */
	public function table_templates(){
		$this->app->get_table_data(module_views_path('invoices_builder', 'templates/templates_table'));
	}

	/**
	 * { xinvoice }
	 */
	public function xinvoice(){
		$data['title'] = _l('ib_xinvoice');
		$this->load->view('xinvoice/index', $data);
	}


	 /* Change client status / active / inactive */
    public function change_template_active($id, $status)
    {
        if ($this->input->is_ajax_request()) {
            $this->invoices_builder_model->change_template_active($id, $status);
        }
    }

    /**
     * { preview_template }
     */
    public function preview_template($id, $type = 'HTML'){

    	$data['template'] = $this->invoices_builder_model->get_template($id);

    	$data['type'] = $type;
    	$data['title'] = $data['template']->name;
    	$this->load->view('xinvoice/index', $data);
    }

    /**
     * Adds a field.
     */
    public function add_field(){
    	if($this->input->post()){
    		$data = $this->input->post();
    		$select_available = $data['select_available'];
    		$item_id = $data['item_id'];
    		unset($data['select_available']);
    		unset($data['item_id']);
    		$col_md = $data['col_md'];
    		unset($data['col_md']);
    		$type = $data['type'];
    		if($select_available == 'true'){
    			$data_customfield = $this->invoices_builder_model->get_custom_field($item_id,'', 'formula, label, value, type, name');
    			if($data_customfield){
    				$field_data["active"] = 0;
    				$field_data["id"] = $item_id;
    				$field_data["type"] = $type;
    				$field_data["formula"] =  (($data_customfield->type == 'item_table' || $data_customfield->type == 'invoice_total') ? $data_customfield->formula : null);
                    $field_data["field_label"] = $data_customfield->label;
                    $field_data["value"] = $data_customfield->value;
                    $field_data["name"] = $data_customfield->name;
    				$field_data["formula"] = $data_customfield->formula;
    			}
    		}
    		else{
    			$result = $this->invoices_builder_model->add_field($data);    	
    			if($result > 0){   
    				$field_data["active"] = 0;
    				$field_data["id"] = $result;
    				$field_data["type"] = $type;
    				$field_data["formula"] = (($type == 'item_table' || $type == 'invoice_total') ? $data['formula'] : null);
                    $field_data["field_label"] =  $data['field_label'];
                    $field_data["value"] =  $data['value'];
                    $field_data["name"] = $data['field_name'];
    				$field_data["formula"] =  $data['formula'];
    			}
    		}
    		if(isset($field_data)){
    			$html = '';
    			$html .= '<div class="col-md-'.$col_md.' custom_field_item">';
    			$html .= $this->load->view('templates/includes/custom_field_item', $field_data, true);
    			$html .= '</div>';
                $preview_html = $this->load->view('templates/includes/custom_field_preview_item', $field_data, true);
                $expression_slug_option = '<option value=""></option>';
                $formular_list = formular_control_list($type);
                foreach ($formular_list as $key => $value) {
                    $expression_slug_option .= '<option value="'.$value['id'].'">'.$value['label'].'</option>';
                }
                $list = [];
                if($type == 'item_table'){
                    $data_customfield = $this->invoices_builder_model->get_custom_field('','type = "item_table" AND value REGEXP \'^-?[0-9]+$\'', 'name, value');
                    foreach ($data_customfield as $key => $value) {
                        $list[] = ['id' => 'item_table|'.$value['name'], 'value' => $value['value']];
                    }
                }
                elseif($type == 'invoice_total'){
                    $data_customfield = $this->invoices_builder_model->get_custom_field('','(type = "item_table" OR type = "invoice_total") AND value REGEXP \'^-?[0-9]+$\'', 'name, label, value');
                    foreach ($data_customfield as $key => $value) {
                        $list[] = ['id' => 'invoice_total|'.$value['name'], 'value' => $value['value']];
                    }
                }

    			echo json_encode([
    				'success' => true,
                    'html' => $html,
    				'preview_html' => $preview_html,
                    'expression_slug_option' => $expression_slug_option,
                    'custom_field_json' => $list
    			]);
    		}else{

	    		echo json_encode([
	    			'success' => false,
	    		]);
	    	}
    	}
    }

    /**
     * { ib invoices builded }
     */
    public function build_invoices(){
    	$data['title'] = _l('ib_build_invoices');
        $this->load->model('invoices_model');
        $this->load->model('clients_model');
        $this->load->model('staff_model');

        $data['list_invoices'] = $this->invoices_model->get();
        if (!has_permission('invoices', '', 'view')) {
            $data['list_invoices'] = $this->invoices_model->get('', get_invoices_where_sql_for_staff(get_staff_user_id()));
        }

        $data['clients'] = $this->clients_model->get();
        if (!has_permission('customers', '', 'view')) {
            $data['clients'] = $this->clients_model->get('', db_prefix().'clients.userid IN (SELECT customer_id FROM '.db_prefix().'customer_admins WHERE staff_id=' . get_staff_user_id() . ')');
        }

        $data['staff'] = $this->staff_model->get();
        $data['templates'] = $this->invoices_builder_model->get_templates();
    	$this->load->view('build_invoices/manage', $data);
    }

    /**
     * { table invoices built }
     */
    public function table_invoices_built(){
    	$this->app->get_table_data(module_views_path('invoices_builder', 'build_invoices/table_invoices_built'));
    }

    /**
     * Builds an invoice.
     */
    public function build_invoice(){
    	$this->load->model('invoices_model');
        $this->load->model('clients_model');
        $this->load->model('projects_model');
        $this->load->model('staff_model');

    	$data['title'] = _l('ib_build_invoice');

        if($this->input->post()){
            $data_build = $this->input->post();
            $success = $this->invoices_builder_model->build_invoices_with_template($data_build);
            if($success){
                set_alert('success', _l('added_successfully'));
            }
            redirect(admin_url('invoices_builder/build_invoices'));
        }

    	$data['list_invoices'] = $this->invoices_model->get();
    	if (!has_permission('invoices', '', 'view')) {
    		$data['list_invoices'] = $this->invoices_model->get('', get_invoices_where_sql_for_staff(get_staff_user_id()));
    	}

        $data['customers'] = $this->clients_model->get();
        if (!has_permission('customers', '', 'view')) {
            $data['customers'] = $this->clients_model->get('', db_prefix().'clients.userid IN (SELECT customer_id FROM '.db_prefix().'customer_admins WHERE staff_id=' . get_staff_user_id() . ')');
        }

        $data['projects'] = $this->projects_model->get();
        if(!has_permission('projects', '', 'view')){
            $data['projects'] = $this->projects_model->get('',  db_prefix() . 'projects.id IN (SELECT project_id FROM ' . db_prefix() . 'project_members WHERE staff_id=' . get_staff_user_id() . ')');
        }

        $data['staff'] = $this->staff_model->get();

    	$data['templates'] = $this->invoices_builder_model->get_templates();
    	$this->load->view('build_invoices/build_invoice', $data);
    }

    /**
     * Gets the additional infor.
     *
     * @param        $invoice_id   The invoice identifier
     * @param        $template_id  The template identifier
     */
    public function get_additional_infor($invoice_id, $template_id, $built_invoice_id){
    	$this->load->model('invoices_model');

        $data['built_invoice_id'] = $built_invoice_id;
        $data['built_invoice'] = $this->invoices_builder_model->get_built_invoice($built_invoice_id);
    	$data['template'] = $this->invoices_builder_model->get_template($template_id);
    	$data['invoice'] = $this->invoices_model->get($invoice_id);

    	$html = $this->load->view('build_invoices/additional_infor', $data, true);

    	echo json_encode([
    		'html' => $html,
    	]);
    }

	/**
	 * delete template
	 * @param  integer $id 
	 */
    public function delete_template($id){
    	if($id != ''){
    		$res = $this->invoices_builder_model->delete_template($id);
    		if($res == true){
    			set_alert('success', _l('deleted_successfully'));
    		}
    		else{
    			set_alert('danger', _l('delete_failed'));
    		}
    	}
    	redirect(admin_url('invoices_builder/templates'));
    }

    /**
     * { edit_converted_invoice }
     *
     * @param        $id     The identifier
     */
    public function edit_converted_invoice($id){

        if($this->input->post()){
            $data_built = $this->input->post();
            $success = $this->invoices_builder_model->edit_converted_invoice($id, $data_built);
            if($success){
                set_alert('success', _l('updated_successfully'));
            }

            redirect(admin_url('invoices_builder/build_invoices'));
        }

        $data['title'] = _l('ib_build_invoice');
        $this->load->model('invoices_model');
        $data['list_invoices'] = $this->invoices_model->get();

        $data['built_invoice'] = $this->invoices_builder_model->get_built_invoice($id);
        if (!has_permission('invoices', '', 'view')) {
            $data['list_invoices'] = $this->invoices_model->get('', get_invoices_where_sql_for_staff(get_staff_user_id()));
        }

        $data['templates'] = $this->invoices_builder_model->get_templates();
        $this->load->view('build_invoices/edit_converted_invoice', $data);
    }

    /**
     * { delete_built_invoice }
     */
    public function delete_built_invoice($id){
        if($id != ''){
            $success = $this->invoices_builder_model->delete_built_invoice($id);
            if($success){
                set_alert('success', _l('deleted_successfully'));
            }
        }
        redirect(admin_url('invoices_builder/build_invoices'));
    }

    /**
     * { filter_project_by_customer }
     *
     * @param        $customer  The customer
     */
    public function filter_project_by_customer($customer = ''){
        $this->load->model('projects_model');

        $projects = $this->projects_model->get();
        if($customer != ''){
            $projects = $this->projects_model->get('', db_prefix().'projects.clientid = '.$customer);
        }

        $html = '';
        $html = '<option value=""></option>';
        foreach($projects as $project){
            $html .= '<option value"'.$project['id'].'">'.$project['name'].'</option>';
        }

        echo json_encode([
            'html' => $html,
        ]);

    }

    /**
     * { filter invoice }
     */
    public function filter_invoice(){
        $this->load->model('invoices_model');
        if($this->input->post()){
            $data = $this->input->post();
            $html = '';
            
            $where = '1 = 1';
            if($data['customer'] != ''){
                $where .= ' AND '.db_prefix().'invoices.clientid = '.$data['customer'];
            }

            if($data['project'] != ''){
                $where .= ' AND '.db_prefix().'invoices.project_id = '.$data['project'];
            }

            if($data['sale_agent'] != ''){
                $where .= ' AND '.db_prefix().'invoices.sale_agent ='.$data['sale_agent'];
            }

            if(!has_permission('invoices', '', 'view')){
                $where .= ' AND '. get_invoices_where_sql_for_staff(get_staff_user_id());
            }

            if($data['template_id'] != ''){
                $invoice_ids = $this->invoices_builder_model->get_invoice_ids_by_template($data['template_id']);
                if(count($invoice_ids) > 0){
                    $where .= ' AND '.db_prefix().'invoices.id NOT IN ('.implode(',', $invoice_ids).')'; 
                }

            }

            $invoices = $this->invoices_model->get('', $where);

            foreach($invoices as $inv){
                $html .= '<option value="'.$inv['id'].'">'.format_invoice_number( $inv['id']).'</option>';
            }

            echo json_encode([
                'html' => $html
            ]);
        }
    }
    /**
     * check fomula
     * @return boolean 
     */
    public function check_fomula(){
        $result = false;
        $data = $this->input->get();
        $formula = $data['formula'];
        $type = $data['type'];
        $formular_control_list = formular_control_list($type);
        foreach ($formular_control_list as $key => $value) {
            $formula = str_replace($value["id"],"1",$formula);
        }
        try {
            eval('$value = '.$formula.';');
            $result = true;
        } catch (Throwable $t) {
            $result = false;
        }
        echo html_entity_decode($result);
    }

    /**
     * { _pdf view }
     *
     * @param        $id     The identifier
     */
    public function pdf_view($id){
        $this->load->model('invoices_model');
        if (!$id) {
            redirect(admin_url('invoices_builder/build_invoices'));
        }

        $data['id'] = $id;

        $invoice_built = $this->invoices_builder_model->get_built_invoice($id);

        $invoice = $this->invoices_model->get($invoice_built->invoice_id);

        $template = $this->invoices_builder_model->get_template($invoice_built->template_id);

        try {
            $pdf = $this->invoices_builder_model->_pdf_view($invoice, $template, $id);
        } catch (Exception $e) {
            echo html_entity_decode($e->getMessage());
            die;
        }
        
        $type = 'D';

        if ($this->input->get('output_type')) {
            $type = $this->input->get('output_type');
        }

        if ($this->input->get('print')) {
            $type = 'I';
        }

        $pdf->Output('invoice_pdf_'.time().'.pdf', $type);
    }

    /**
     * Loads contact email.
     *
     * @param        $built_inv_id  The built inv identifier
     */
    public function load_contact_email($built_inv_id){
        $invoice_built = $this->invoices_builder_model->get_built_invoice($built_inv_id);
        $this->load->model('invoices_model');

        $invoice = $this->invoices_model->get($invoice_built->invoice_id);

        $html = '';
        if($invoice){
            $this->db->where('userid', $invoice->clientid);
            $contacts = $this->db->get(db_prefix().'contacts')->result_array();

            foreach($contacts as $ct){
                $html .= '<option value="'.$ct['email'].'" data-subtext="'.$ct['firstname'].' '.$ct['lastname'].'" selected>'.$ct['email'].'</option>';
            }
        }

        echo json_encode([
            'html' => $html,
        ]);

    }

    /**
     * Sends an invoice.
     */
    public function send_invoice(){
        if($this->input->post()){
            $data = $this->input->post();
            $data['content'] = $this->input->post('content', false);
            $send = $this->invoices_builder_model->send_invoice($data);
            if($send){
                set_alert('success',_l('send_invoice_successfully'));
                
            }else{
                set_alert('warning',_l('send_invoice_fail'));
            }
            redirect(admin_url('invoices_builder/build_invoices'));
            
        }
    }

    /**
     * { preview }
     *
     * @param      <type>  $id     The identifier
     */
    public function preview($id){
        $data['page_rotation'] = 'portrait';
        $data['title'] = '';
        $data_template = $this->invoices_builder_model->get_template($id, 'capture_html, setting, name');
        if($data_template){
            $data['template'] = json_decode($data_template->setting);
            $data['page_rotation'] = $data['template']->page_rotation;
            $data['title'] = $data_template->name;
            $this->load->model('currencies_model');
            $base_currency = $this->currencies_model->get_base_currency();
            $data['currency_name'] = $base_currency->name;
        }
        else{
            redirect(admin_url('invoices_builder/templates'));
        }
        $this->load->view('templates/preview_template', $data);
    }
    /**
     * clone template
     * @param  integer $id 
     * @return boolean     
     */
    public function clone_template($id){
        $result = false;
        $message = '';
        $rs = $this->invoices_builder_model->clone_template($id);
        if($rs > 0){
            $result = true;
            $message = _l('ib_successfully_cloned');
        }
        else{
            $message = _l('ib_clone_failed');
        }
        echo json_encode(['success' => $result, 'message' => $message]);
        die;
    }

    /**
     * Gets the preview.
     *
     * @param        $id     The identifier
     */
    public function get_preview($id){
        $html = '';
        $data['page_rotation'] = 'portrait';
        $data_template = $this->invoices_builder_model->get_template($id, 'setting, name');
        if($data_template){
            $data['template'] = json_decode($data_template->setting);
            $data['page_rotation'] = $data['template']->page_rotation;
            $this->load->model('currencies_model');
            $base_currency = $this->currencies_model->get_base_currency();
            $data['currency_name'] = $base_currency->name;
        }
        $html = $this->load->view('templates/includes/build_invoice_preview', $data, true);
        echo html_entity_decode($html);
        die;
    }
    /**
     * check duplicate field
     * @param  string $type        
     * @param  string $check_value 
     * @param  string $check_field 
     * @return boolean              
     */
    public function check_duplicate_field(){
        $type = $this->input->post('type');
        $check_name_value = $this->input->post('check_name_value');
        $check_label_value = $this->input->post('check_label_value');
        $result = $this->invoices_builder_model->check_duplicate_field($type, $check_name_value, 'name');
        $result2 = $this->invoices_builder_model->check_duplicate_field($type, $check_label_value, 'label');
        echo json_encode(['name_result' => $result, 'label_result' => $result2]);
        die;
    }


}