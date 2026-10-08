<?php
defined('BASEPATH') or exit('No direct script access allowed');

hooks()->add_action('after_email_templates', 'add_invoice_builder_email_templates');

if(!class_exists('qrstr')){
    include_once('modules/invoices_builder/assets/plugins/phpqrcode/qrlib.php');
}

/**
 * { insert default template }
 */
function insert_default_template(){
	$CI = & get_instance();
	$CI->db->where('default_template', 1);
	if($CI->db->get(db_prefix().'ib_templates')->num_rows() == 0){
		$arrContextOptions = array(
			"ssl"=>array(
				"verify_peer"=>false,
				"verify_peer_name"=>false,
			),
		);  

		$str = file_get_contents(module_dir_url(INVOICES_BUILDER_MODULE_NAME, 'libraries/example_templates/templates.json'), false, stream_context_create($arrContextOptions));
		$data = json_decode($str, true);
		if($data && count($data) > 0){
			foreach($data as $k => $value){
				$CI->db->insert(db_prefix().'ib_templates', $value);
			}
		}
	}
}

/**
 * builder_template
 * @return array 
 */
function builder_template(){
	$setting_type1 = ['show_on_column', 'width', 'height'];
	$setting_type2 = ['show_on_column', 'text_color', 'font_size', 'font_style', 'text_align'];
	$setting_type3 = ['invoice_number', 'invoice_date', 'invoice_due_date', 'invoice_amount', 'show_on_column', 'text_color', 'font_size', 'font_style', 'text_align'];
	return [
		['id' => 'header', 'control' => [
			['control_name' => 'row_color', 'setting' => []],
			['control_name' => 'col_number', 'setting' => []],
			['control_name' => 'logo', 'setting' => $setting_type1],
			['control_name' => 'qrcode', 'setting' => $setting_type1],
			['control_name' => 'title', 'setting' => $setting_type2],
			['control_name' => 'invoice_infor', 'setting' => $setting_type3]
		]]
	];
}

/**
 * Gets the template default.
 */
function get_template_default(){
	$CI = & get_instance();
	$CI->db->where('default_template', 1);
	return $CI->db->get(db_prefix().'ib_templates')->row();
}

/**
 * { generate invoice qrcode }
 */
function generate_invoice_qrcode($invoice_id, $built_invoice_id){
	$CI = & get_instance();
	$CI->load->model('invoices_model');
	$CI->load->model('invoices_builder_model');
	$invoice = $CI->invoices_model->get($invoice_id);
	$built_invoice = $CI->invoices_builder_model->get_built_invoice($built_invoice_id);

	if($invoice){
		$html = '';
		$html .= _l('ib_invoice_number').' #: '.format_invoice_number($invoice_id)."\n";
		$html .= site_url('invoices_builder/invoice_link/index/'.$built_invoice_id.'/'.$built_invoice->hash);

		$codeContents = $html;

		$tempDir = FCPATH.'modules/invoices_builder/uploads/qrcodes/';
		$file_name = 'invoice_qr_'.$invoice_id.'_'.$built_invoice_id;
		$rs_path = $tempDir.$file_name;
		if(!file_exists($rs_path)){
            QRcode::png($codeContents, $rs_path.'.png', "L", 4, 4);
        }
	}
}

/**
 * Gets the invoice qrcode link.
 *
 * @param        $invoice_id  The invoice identifier
 *
 * @return     string  The invoice qrcode link.
 */
function get_invoice_qrcode_link($invoice_id, $built_invoice, $type = 'html'){
	$tempDir = base_url('modules/invoices_builder/uploads/qrcodes/');
	if($type == 'pdf'){
		$tempDir = FCPATH.'modules/invoices_builder/uploads/qrcodes/';
	}

	$file_name = 'invoice_qr_'.$invoice_id.'_'.$built_invoice;
	$rs_path = $tempDir.$file_name;
	$link = '';
	if(!file_exists($rs_path.'.png')){
		generate_invoice_qrcode($invoice_id, $built_invoice);
	}
	$link = $rs_path;
	return $link.'.png';
}

/**
 * Gets the invoice qrcode link.
 *
 * @param        $invoice_id  The invoice identifier
 *
 * @return     string  The invoice qrcode link.
 */
function get_invoice_signature_link($invoice_id){ 
	$CI = & get_instance();
	$tempDir = base_url('modules/invoices_builder/uploads/signature/');

	$CI->db->where('rel_id', $invoice_id);
	$CI->db->where('rel_type', 'invoice_signature');
	$signature = $CI->db->get(db_prefix().'files')->row();

	$file_name = '';
	if($signature){
		$file_name = $signature->file_name;
	}
	

	$rs_path = $tempDir.$file_name;
	if(!file_exists($rs_path)){ 
		return '';
	}

	return $rs_path;
}

/**
 * { ib get primary contact email }
 *
 * @param        $clientid  The clientid
 *
 * @return     string  
 */
function ib_get_primary_contact_email($clientid){
	$contact_id = get_primary_contact_user_id($clientid);
	$CI = & get_instance();
	$CI->load->model('clients_model');
	if($contact_id){
		$contact = $CI->clients_model->get_contact($contact_id);
		return $contact->email;
	}
	return '';
}
/**
 * formular control list.
 */
function formular_control_list($type = ''){
	$CI = & get_instance();
    $CI->load->model('invoices_builder_model');
	if($type == 'item_table'){
		$list = [
			['id' => 'item_table|quantity', 'label' => _l('ib_item_table').' | '._l('ib_quantity')],
			['id' => 'item_table|tax_rate', 'label' => _l('ib_item_table').' | '._l('ib_tax_rate')],
			['id' => 'item_table|tax_amount', 'label' => _l('ib_item_table').' | '._l('ib_tax_amount')],
			['id' => 'item_table|unit_price', 'label' => _l('ib_item_table').' | '._l('ib_unit_price')],
			['id' => 'item_table|subtotal', 'label' => _l('ib_item_table').' | '._l('ib_subtotal')]
		];
		$data_customfield = $CI->invoices_builder_model->get_custom_field('','type = "item_table" AND value REGEXP \'^-?[0-9]+$\'', 'name, label');
		foreach ($data_customfield as $key => $value) {
			$list[] = ['id' => 'item_table|'.$value['name'], 'label' => _l('ib_item_table').' | '.$value['label']];
		}
		return $list;
	}
	else{
		$list = [
			['id' => 'item_table|subtotal', 'label' => _l('ib_item_table').' | '._l('ib_subtotal')],
			['id' => 'invoice_total|subtotal_value', 'label' => _l('ib_invoice_total').' | '._l('ib_subtotal_value')],
			['id' => 'invoice_total|total_tax_value', 'label' => _l('ib_invoice_total').' | '._l('ib_total_tax_value')],
			['id' => 'invoice_total|discount_value', 'label' => _l('ib_invoice_total').' | '._l('ib_discount_value')],
			['id' => 'invoice_total|grand_total_value', 'label' => _l('ib_invoice_total').' | '._l('ib_grand_total_value')]
		];
		$data_customfield = $CI->invoices_builder_model->get_custom_field('','(type = "item_table" OR type = "invoice_total") AND value REGEXP \'^-?[0-9]+$\'', 'name, label, type');
		foreach ($data_customfield as $key => $value) {
			$list[] = ['id' => $value['type'].'|'.$value['name'], 'label' => _l('ib_'.$value['type']).' | '.$value['label']];
		}
		return $list;
	}
}

/**
 * { ib_get_template_name_by_id }
 *
 * @param        $template_id  The template identifier
 */
function ib_get_template_name_by_id($template_id){
	$CI = & get_instance();
	$CI->db->where('id', $template_id);
	$template = $CI->db->get(db_prefix().'ib_templates')->row();

	if($template){
		return $template->name;
	}
	return '';
}

/**
 * Check public invoice restrictions - hash, clientid
 * @since  Version 1.0.1
 * @param  mixed $id   invoice id
 * @param  string $hash invoice hash
 */
function check_public_invoice_restrictions($id, $hash)
{
    $CI = &get_instance();
    $CI->load->model('invoices_builder_model');
    $CI->load->model('invoices_model');
    if (!$hash || !$id) {
        show_404();
    }

    $built_invoice = $CI->invoices_builder_model->get_built_invoice($id);

    if (!$built_invoice){
    	 show_404();
    }

    $invoice = $CI->invoices_model->get($built_invoice->invoice_id);

    if (!$invoice || ($invoice->hash != $hash)) {
        show_404();
    }

}

/**
 * Gets the col md by col number setting.
 *
 * @param        $setting  The setting
 */
function get_col_md_by_col_number_setting($setting){
	$col_md = 12;
	if($setting == 4){
		$col_md = 3;
	}else if($setting == 3){
		$col_md = 4;
	}else if($setting == 2){
		$col_md = 6;
	}else{
		$col_md = 12;
	}

	return $col_md;
}

/**
 * Gets the item identifier by description.
 *
 * @param        $des       The description
 * @param        $long_des  The long description
 *
 * @return     string  The item identifier by description.
 */
function ib_get_item_by_name($des){
    $CI           = & get_instance();
    $CI->db->where('description', $des);
   
    $item = $CI->db->get(db_prefix().'items')->row();

    if($item){
        return $item;
    }
    return '';
}

/**
 * get warehourse attachments
 * @param  integer $commodity_id 
 * @return array               
 */
function ib_get_item_attachments($commodity_id){
	$CI           = & get_instance();
    $CI->db->order_by('dateadded', 'desc');
    $CI->db->where('rel_id', $commodity_id);
    $CI->db->where('rel_type', 'commodity_item_file');
    return $CI->db->get(db_prefix() . 'files')->result_array();
}

function default_template(){
	$arrContextOptions = array(
			"ssl"=>array(
				"verify_peer"=>false,
				"verify_peer_name"=>false,
			),
		);  

	 $str = file_get_contents(module_dir_url(INVOICES_BUILDER_MODULE_NAME, 'libraries/example_templates/default_template.txt'), false, stream_context_create($arrContextOptions));

	 return $str;
}

/**
 * Gets the custom field by identifier.
 *
 * @param        $field_id  The field identifier
 */
function get_custom_field_by_id($field_id){
	$CI           = & get_instance();

	$CI->db->where('id', $field_id);
	$field = $CI->db->get(db_prefix().'ib_customfield')->row();

	if($field){
		return $field;
	}
	return '';
}

/**
 * { convert color }
 */
function ib_convert_color($color){
    $arr_rgb = [];
    $color = str_replace('#', '', $color);
    $split_hex_color = str_split( $color, 2 ); 
    $rgb1 = hexdec( $split_hex_color[0] );  
    $rgb2 = hexdec( $split_hex_color[1] );  
    $rgb3 = hexdec( $split_hex_color[2] );

    $arr_rgb[0] = $rgb1;
    $arr_rgb[1] = $rgb2;
    $arr_rgb[2] = $rgb3;

    return $arr_rgb;
}
/**
 * calculate formula
 * @param  array  $data    
 * @param  string $formula 
 * @param  string $type    
 */
function calculate_formula($data = [], $formula = ''){
	$value = null;
	foreach ($data as $key => $row) {
		$formula = str_replace($row["id"], $row["value"], $formula);
	}
	try {
		eval('$value = '.$formula.';');
	} catch (Throwable $t) {

	}
	return $value;
}

/**
 * { ib get regular table width }
 *
 * @param        $col_number  The col number
 */
function ib_get_regular_table_width($col_number){
	$width = 100/$col_number;
	return round($width, 2);
}

if (!function_exists('add_invoice_builder_email_templates')) {
    /**
     * Init appointly email templates and assign languages
     * @return void
     */
    function add_invoice_builder_email_templates() {
        $CI = &get_instance();

        $data['invoice_builder_templates'] = $CI->emails_model->get(['type' => 'invoice_builder', 'language' => 'english']);

        $CI->load->view('invoices_builder/email_templates', $data);
    }
}

/**
 * get status modules wh
 * @param  string $module_name 
 * @return boolean             
 */
function ib_get_status_modules($module_name){
	$CI             = &get_instance();
	$sql = 'select * from '.db_prefix().'modules where module_name = "'.$module_name.'" AND active =1 ';
	$module = $CI->db->query($sql)->row();
	if($module){
		return true;
	}else{
		return false;
	}
}

/**
 * { ib_html_entity_decode }
 *
 * @param      string  $str    The string
 *
 * @return     <string>  ( )
 */
function ib_html_entity_decode($str){
	return html_entity_decode($str ?? '');
}
