<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Invoices Builder model
 */
class Invoices_builder_model extends App_Model {
	/**
	 * Constructs a new instance.
	 */
	public function __construct() {
		parent::__construct();
	}
	
	/**
	 * { invoices builder model }
	 *
	 * @param        $id      The identifier
	 * @param        $status  The status
	 */
	public function change_template_active($id, $status){
		$this->db->where('id', $id);
        $this->db->update(db_prefix() . 'ib_templates', [
            'active' => $status,
        ]);

        if ($this->db->affected_rows() > 0) {
            return true;
        }

        return false;
	}

	/**
	 * Gets the template.
	 *
	 * @param        $id     The identifier
	 */
	public function get_template($id, $select = '*'){
		$this->db->select($select);
		$this->db->where('id', $id);
	    return $this->db->get(db_prefix().'ib_templates')->row();
	}

	/**
	 * Adds a field.
	 *
	 * @param        $data   The data
	 */
	public function add_field($data){
		$data_field['name'] = $data['field_name'];
		$data_field['label'] = $data['field_label'];
		$data_field['value'] = $data['value'];
		$data_field['type'] = $data['type'];
		$data_field['slug'] = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '_', strtolower($data_field['name'])));
		$data_field['created_at'] = date('Y-m-d H:i:s');
		$data_field['updated_at'] = date('Y-m-d H:i:s');
		$data_field['created_by'] = get_staff_user_id();
		$data_field['formula'] = isset($data['formula']) ? $data['formula'] : '';

		$this->db->insert(db_prefix().'ib_customfield', $data_field);
		$insert_id = $this->db->insert_id();
		if($insert_id){
			return $insert_id;
		}
		return false;
	}

	/**
	 * add template
	 * @param array $data 
	 */
	public function add_template($data){
		$insert_data['capture_image'] = $data['capture_image'];
		$insert_data['capture_html'] = $data['capture_html'];
		unset($data['capture_image']);
		unset($data['capture_html']);
		$insert_data['setting'] = json_encode($data);
		$insert_data['name'] = $data['name'];
		$insert_data['created_at'] = date('Y-m-d H:i:s');
		$insert_data['updated_at'] = date('Y-m-d H:i:s');
		$insert_data['created_by'] = get_staff_user_id();
		$insert_data['active'] = 1;
		$this->db->insert(db_prefix() . 'ib_templates', $insert_data);
		return $this->db->insert_id();
	}

	/**
	 * update template
	 * @param array $data 
	 */
	public function update_template($data, $id){
		$update_data['capture_image'] = $data['capture_image'];
		$update_data['capture_html'] =  $data['capture_html'];
		unset($data['capture_image']);
		unset($data['capture_html']);
		$update_data['setting'] = json_encode($data);
		$update_data['name'] = $data['name'];
		$update_data['created_at'] = date('Y-m-d H:i:s');
		$update_data['updated_at'] = date('Y-m-d H:i:s');
		$update_data['created_by'] = get_staff_user_id();
		$update_data['active'] = 1;
		if(isset($data['default_template'])){
			$update_data['default_template'] = $data['default_template'];
		}
		$this->db->where('id', $id);
		$this->db->update(db_prefix() . 'ib_templates', $update_data);
		if ($this->db->affected_rows() > 0) {
			return true;
		}
		return false;
	}

	/**
	 * get custom field
	 * @param  string  $id         
	 * @param  string  $where      
	 * @param  string  $select     
	 * @param  boolean $count      
	 * @param  string  $order_by   
	 * @param  string  $order_type 
	 * @return object or array or integer              
	 */
	public function get_custom_field($id = '', $where = '', $select = '*', $count = false, $order_by = '', $order_type = 'asc'){
		if(is_numeric($id) && $id > 0){
			$this->db->where('id', $id);
			$this->db->select($select);
			return $this->db->get(db_prefix().'ib_customfield')->row();
		}
		else{
			$this->db->select($select);
			if($where != ''){
				$this->db->where($where);
			}
			if ($order_by != '') {
				$this->db->order_by($order_by, $order_type);
			}
			$data = $this->db->get(db_prefix().'ib_customfield');
			if($count == true){
				return $data->num_rows();
			}
			else{
				return $data->result_array();
			}
		}
	}
	
	/**
	 * Gets the templates.
	 *
	 * @return       The templates.
	 */
	public function get_templates($where = '', $select = '*'){
		if($select != ''){
			$this->db->select($select);
		}
		if($where != ''){
			$this->db->where($where);
		}
		$this->db->where('active', 1);
		return $this->db->get(db_prefix().'ib_templates')->result_array();
	}

	/**
	 * delete template
	 * @param  integer $id 
	 * @return integer     
	 */
	public function delete_template($id){
		$this->db->where('id', $id);
		$this->db->delete(db_prefix().'ib_templates');
		if ($this->db->affected_rows() > 0) {

			$this->db->where('template_id', $id);
			$this->db->delete(db_prefix().'ib_invoices_built');

			return true;
		}
		return false;
	}

	/**
	 * Builds an invoices with template.
	 *
	 * @param        $data   The data
	 */
	public function build_invoices_with_template($data){
		$rs = 0;
		if(isset($data['invoice_id']) && count($data['invoice_id'])){
			foreach($data['invoice_id'] as $invoice_id){
				$build_data['invoice_id'] = $invoice_id;
				$build_data['template_id'] = $data['template_id'];
				$build_data['hash'] = app_generate_hash();
				$build_data['description'] = $data['description'];
				$build_data['status'] = 'new';
				$build_data['created_by'] = get_staff_user_id();
				$build_data['created_at'] = date('Y-m-d H:i:s');
				$build_data['updated_at'] = date('Y-m-d H:i:s');

				if(total_rows(db_prefix().'ib_invoices_built', ['invoice_id' => $invoice_id, 'template_id' => $data['template_id']]) == 0){
					$this->db->insert(db_prefix().'ib_invoices_built', $build_data);
					$insert_id = $this->db->insert_id();
					if($insert_id){
						$rs++;
					}
				}
			}
		}

		if($rs > 0){
			return true;
		}
		return false;
	}

	/**
	 * Gets the built invoice.
	 *
	 * @param        $id     The identifier
	 */
	public function get_built_invoice($id){
		$this->db->where('id', $id);
		return $this->db->get(db_prefix().'ib_invoices_built')->row();
	}

	/**
	 * { delete_built_invoice }
	 *
	 * @param        $id     The identifier
	 */
	public function delete_built_invoice($id){
		$this->db->where('id',$id);
		$this->db->delete(db_prefix().'ib_invoices_built');
		if($this->db->affected_rows()){
			return true;
		}
		return false;
	}

	/**
	 * Gets the invoice identifiers by template.
	 */
	public function get_invoice_ids_by_template($template_id){
		$this->db->where('template_id', $template_id);
		$invoice_built = $this->db->get(db_prefix().'ib_invoices_built')->result_array();
		$invoice_ids = [];
		foreach($invoice_built as $inv){
			$invoice_ids[] = $inv['invoice_id'];
		}

		return $invoice_ids;
	}

	/**
     * { product catalog pdf }
     *
     * @param        $html  
     *
     * @return      ( pdf )
     */
    public function _pdf_view($invoice, $template, $built_invoice_id, $tag = '')
    {   
        return app_pdf('_pdf_view', module_dir_path(INVOICES_BUILDER_MODULE_NAME, 'libraries/pdf/Built_invoice_pdf'), $invoice, $template, $built_invoice_id, $tag);
    }

    /**
     * Sends an invoice.
     *
     * @param        $data   The data
     *
     * @return     bool    
     */
    public function send_invoice($data){
    	$this->load->model('invoices_model');
    	$mail_data = [];
        $count_sent = 0;
        $built_invoice = $this->get_built_invoice($data['built_inv_id']);

        $invoice = $this->invoices_model->get($built_invoice->invoice_id);
        $template = $this->get_template($built_invoice->template_id);

        if($data['attach_pdf']){
            try {
                $pdf = $this->_pdf_view($invoice, $template, $data['built_inv_id']);
            } catch (Exception $e) {
                echo html_entity_decode($e->getMessage());
                die;
            }

            $attach = $pdf->Output(format_invoice_number($invoice->id) . '.pdf', 'S');
        }


        if(strlen(get_option('smtp_host')) > 0 && strlen(get_option('smtp_password')) > 0 && strlen(get_option('smtp_username')) > 0){
            foreach($data['send_to'] as $mail){

                $mail_data['built_inv_id'] = $data['built_inv_id'];
                $mail_data['content'] = $data['content'];
                $mail_data['mail_to'] = $mail;

                $template = mail_template('invoice_to_contact', 'invoices_builder', array_to_object($mail_data));

                if($data['attach_pdf']){
                    $template->add_attachment([
                        'attachment' => $attach,
                        'filename'   => str_replace('/', '-', format_invoice_number($invoice->id) . '.pdf'),
                        'type'       => 'application/pdf',
                    ]);
                }

                $rs = $template->send();

                if($rs){
                    $count_sent++;
                }
            }

            if($count_sent > 0){
                return true;
            }  
        }

        return false;
    }
    /**
     * clone template
     * @param  integer $id 
     * @return integer     
     */
    public function clone_template($id){
    	$data_template = $this->get_template($id);
    	if($data_template){
    		$data = (Array)$data_template;
    		unset($data['id']);
    		$data['name'] = $this->auto_template_name($data['name']);
    		$data['created_at'] = date('Y-m-d H:i:s');
    		$data['updated_at'] = date('Y-m-d H:i:s');
    		$data['created_by'] = get_staff_user_id();
    		$data['default_template'] = 0;
    		$this->db->insert(db_prefix() . 'ib_templates', $data);
    		return $this->db->insert_id();
    	}
    	return 0;
    }
    /**
     * auto template name
     * @param  string  $name     
     * @param  integer $increase 
     * @return string            
     */
    public function auto_template_name($name, $increase = 1){
    	$new_name = $name.' ('.$increase.')';
    	$data_template = $this->get_templates('name="'.$new_name.'"', '1');
    	if($data_template){
    		return $this->auto_template_name($name, $increase += 1);
    	}
    	else{
    		return $new_name;
    	}
    }

    /**
     * { edit converted invoice }
     *
     * @param        $id     The identifier
     * @param        $data   The data
     */
    public function edit_converted_invoice($id, $data){
    	if(isset($data['invoice_id'])){
    		unset($data['invoice_id']);
    	}

    	if(isset($data['template_id'])){
    		$update_data['template_id'] = $data['template_id'];
    		unset($data['template_id']);
    	}

    	$update_data['updated_data'] = json_encode($data);

    	$this->db->where('id', $id);
    	$this->db->update(db_prefix().'ib_invoices_built', $update_data);
    	if($this->db->affected_rows()){
			return true;
		}
		return false;
    }

    /**
     * Gets the built invoice for client.
     */
    public function get_built_invoice_for_client($client){

    	$this->db->select('*, ' .db_prefix().'ib_invoices_built.id as id, '.db_prefix().'ib_invoices_built.hash as hash, '. db_prefix() . 'currencies.id as currencyid, ' . db_prefix() . 'invoices.id as invoice_id, ' . db_prefix() . 'currencies.name as currency_name, ' .db_prefix().'invoices.hash as invoice_hash');
        $this->db->from(db_prefix() . 'ib_invoices_built');
        $this->db->join(db_prefix() . 'invoices', '' . db_prefix() . 'invoices.id = ' . db_prefix() . 'ib_invoices_built.invoice_id', 'left');
    	$this->db->join(db_prefix() . 'currencies', '' . db_prefix() . 'currencies.id = ' . db_prefix() . 'invoices.currency', 'left');
    	$this->db->where(db_prefix().'invoices.clientid' , $client);
    	$this->db->order_by('number,YEAR(date)', 'desc');
    	return $this->db->get()->result_array();
    }
    /**
     * check duplicate field
     * @param  string $type        
     * @param  string $check_value 
     * @param  string $check_field 
     * @return boolean              
     */
    public function check_duplicate_field($type, $value, $check_field){
    	$this->db->where('type', $type);
    	$this->db->where($check_field, $value);
    	$data = $this->db->get(db_prefix().'ib_customfield')->row();
    	if($data){
    		return true;
    	}
    	return false;
    }
}