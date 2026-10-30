<?php
defined('BASEPATH') or exit('No direct script access allowed');
/**
 * Service management model
 */
class Service_management_model extends App_Model
{


	/**
	 * get item category
	 * @param  boolean $id 
	 * @return [type]      
	 */
	public function get_item_category($id = false, $active = false) {

		if (is_numeric($id)) {
			$this->db->where('id', $id);
			return $this->db->get(db_prefix() . 'items_groups')->row();
		}
		if ($id == false) {
			if($active){
				$this->db->where('display', 1);
			}
			$this->db->order_by(db_prefix().'items_groups.order', 'asc');

			return $this->db->get(db_prefix() . 'items_groups')->result_array();
		}

	}

	/**
	 * add item category
	 * @param [type] $data 
	 */
	public function add_item_category($data)
	{
		if (isset($data['display'])) {
			unset($data['display']);
			$data['display'] = 1;
		}else{
			$data['display'] = 0;
		}

		$this->db->insert(db_prefix().'items_groups',$data);
		$insert_id = $this->db->insert_id();

		if ($insert_id) {
			return $insert_id;
		}
		return false;
	}

	/**
	 * update unit categories
	 * @param  [type] $data 
	 * @param  [type] $id   
	 * @return [type]       
	 */
	public function update_item_category($data, $id)
	{
		if (isset($data['display'])) {
			unset($data['display']);
			$data['display'] = 1;
		}else{
			$data['display'] = 0;
		}

		$affected_rows=0;
		$this->db->where('id', $id);
		$this->db->update(db_prefix() . 'items_groups', $data);
		if ($this->db->affected_rows() > 0) {
			return true;
		}

		return false;  
	}

	/**
	 * delete item category
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function delete_item_category($id) {
		$this->db->where('id', $id);
		$this->db->delete(db_prefix() . 'items_groups');
		if ($this->db->affected_rows() > 0) {
			return true;
		}
		return false;
	}

	/**
	 * change category status
	 * @param  [type] $id     
	 * @param  [type] $status 
	 * @return [type]         
	 */
	public function change_category_status($id, $status)
	{
		$this->db->where('id', $id);
		$this->db->update(db_prefix() . 'items_groups', [
			'display' => $status,
		]);

		return true;
	}

	/**
	 * get item unit
	 * @param  boolean $id     
	 * @param  boolean $active 
	 * @return [type]          
	 */
	public function get_item_unit($id = false, $active = false) {

		if (is_numeric($id)) {
			$this->db->where('id', $id);
			return $this->db->get(db_prefix() . 'sm_units')->row();
		}
		if ($id == false) {
			if($active){
				$this->db->where('display', 1);
			}
			$this->db->order_by(db_prefix().'sm_units.order', 'asc');

			return $this->db->get(db_prefix() . 'sm_units')->result_array();
		}

	}

	/**
	 * add item unit
	 * @param [type] $data 
	 */
	public function add_item_unit($data)
	{
		if (isset($data['display'])) {
			unset($data['display']);
			$data['display'] = 1;
		}else{
			$data['display'] = 0;
		}

		$this->db->insert(db_prefix().'sm_units',$data);
		$insert_id = $this->db->insert_id();

		if ($insert_id) {
			return $insert_id;
		}
		return false;
	}

	/**
	 * update item unit
	 * @param  [type] $data 
	 * @param  [type] $id   
	 * @return [type]       
	 */
	public function update_item_unit($data, $id)
	{
		if (isset($data['display'])) {
			unset($data['display']);
			$data['display'] = 1;
		}else{
			$data['display'] = 0;
		}

		$affected_rows=0;
		$this->db->where('id', $id);
		$this->db->update(db_prefix() . 'sm_units', $data);
		if ($this->db->affected_rows() > 0) {
			return true;
		}

		return false;  
	}

	/**
	 * delete item unit
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function delete_item_unit($id) {
		$this->db->where('id', $id);
		$this->db->delete(db_prefix() . 'sm_units');
		if ($this->db->affected_rows() > 0) {
			return true;
		}
		return false;
	}

	/**
	 * change unit status
	 * @param  [type] $id     
	 * @param  [type] $status 
	 * @return [type]         
	 */
	public function change_unit_status($id, $status)
	{
		$this->db->where('id', $id);
		$this->db->update(db_prefix() . 'sm_units', [
			'display' => $status,
		]);

		return true;
	}

	/**
	 * get status
	 * @param  boolean $id     
	 * @param  boolean $active 
	 * @return [type]          
	 */
	public function get_status($id = false, $active = false) {

		if (is_numeric($id)) {
			$this->db->where('id', $id);
			return $this->db->get(db_prefix() . 'sm_item_status')->row();
		}
		if ($id == false) {
			if($active){
				$this->db->where('display', 1);
			}
			return $this->db->get(db_prefix() . 'sm_item_status')->result_array();
		}

	}

	/**
	 * add status
	 * @param [type] $data 
	 */
	public function add_status($data)
	{
		if (isset($data['display'])) {
			unset($data['display']);
			$data['display'] = 1;
		}else{
			$data['display'] = 0;
		}

		$this->db->insert(db_prefix().'sm_item_status',$data);
		$insert_id = $this->db->insert_id();

		if ($insert_id) {
			return $insert_id;
		}
		return false;
	}

	/**
	 * update item unit
	 * @param  [type] $data 
	 * @param  [type] $id   
	 * @return [type]       
	 */
	public function update_status($data, $id)
	{
		if (isset($data['display'])) {
			unset($data['display']);
			$data['display'] = 1;
		}else{
			$data['display'] = 0;
		}

		$affected_rows=0;
		$this->db->where('id', $id);
		$this->db->update(db_prefix() . 'sm_item_status', $data);
		if ($this->db->affected_rows() > 0) {
			return true;
		}

		return false;  
	}

	/**
	 * delete item unit
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function delete_status($id) {
		$this->db->where('id', $id);
		$this->db->delete(db_prefix() . 'sm_item_status');
		if ($this->db->affected_rows() > 0) {
			return true;
		}
		return false;
	}

	/**
	 * change status
	 * @param  [type] $id     
	 * @param  [type] $status 
	 * @return [type]         
	 */
	public function change_status($id, $status)
	{
		$this->db->where('id', $id);
		$this->db->update(db_prefix() . 'sm_item_status', [
			'display' => $status,
		]);

		return true;
	}

	/**
	 * change setting with checkbox
	 * @param  [type] $data 
	 * @return [type]       
	 */
	public function change_setting_with_checkbox($data)
	{

		$val = $data['input_name_status'] == 'true' ? 1 : 0;

		$this->db->where('name',$data['input_name']);
		$this->db->update(db_prefix() . 'options', [
			'value' => $val,
		]);
		if ($this->db->affected_rows() > 0) {
			return true;
		}else{
			return false;
		}
	}

	/**
	 * get product
	 * @param  boolean $id 
	 * @return [type]      
	 */
	public function get_product($id = false, $active = 1)
	{

		if (is_numeric($id)) {
			$this->db->where('id', $id);
			$product = $this->db->get(db_prefix() . 'items')->row();

			$this->db->where('item_id', $id);
			$product->item_billing_plan = $this->db->get(db_prefix().'sm_items_cycles')->result_array();

			return $product;
		}
		if ($id == false) {
			$sql_where = db_prefix().'items.can_be_product_service = "can_be_product_service"';
			$this->db->where($sql_where);
			$this->db->where('active', $active);
			return $this->db->get(db_prefix() . 'items')->result_array();
		}

	}

	/**
	 * get product
	 * @param  boolean $id 
	 * @return [type]      
	 */
	public function get_subscription_product($id = false, $active = 1)
	{

		if (is_numeric($id)) {
			$this->db->where('id', $id);
			$product = $this->db->get(db_prefix() . 'items')->row();

			$this->db->where('item_id', $id);
			$product->item_billing_plan = $this->db->get(db_prefix().'sm_items_cycles')->result_array();

			return $product;
		}
		if ($id == false) {
			$sql_where = db_prefix().'items.can_be_product_service = "can_be_product_service" AND service_type = "subscriptions"';
			$this->db->where($sql_where);
			$this->db->where('active', $active);
			return $this->db->get(db_prefix() . 'items')->result_array();
		}

	}

	/**
	 * check sku duplicate
	 * @param  [type] $data 
	 * @return [type]       
	 */
	public function check_sku_duplicate($data)
	{	
		if(isset($data['item_id']) && $data['item_id'] != ''){
		//check update
			$this->db->where('sku_code', $data['sku_code']);
			$this->db->where('id != ', $data['item_id']);

			$items = $this->db->get(db_prefix() . 'items')->result_array();

			if(count($items) > 0){
				return false;
			}
			return true;

		}elseif(isset($data['sku_code']) && $data['sku_code'] != ''){

		//check insert
			$this->db->where('sku_code', $data['sku_code']);
			$items = $this->db->get(db_prefix() . 'items')->row();
			if($items){
				return false;
			}
			return true;
		}

		return true;
	}

	/**
	 * delete mrp attachment file
	 * @param  [type] $attachment_id 
	 * @param  [type] $folder_name   
	 * @return [type]                
	 */
	public function delete_sm_attachment_file($attachment_id, $folder_name)
	{
		$deleted    = false;
		$attachment = $this->misc_model->get_file($attachment_id);
		if ($attachment) {
			if (empty($attachment->external)) {
				unlink($folder_name .$attachment->rel_id.'/'.$attachment->file_name);
			}
			$this->db->where('id', $attachment->id);
			$this->db->delete(db_prefix() . 'files');
			if ($this->db->affected_rows() > 0) {
				$deleted = true;
				log_activity('MRP Attachment Deleted [ID: ' . $attachment->rel_id . '] folder name: '.$folder_name);
			}

			if (is_dir($folder_name .$attachment->rel_id)) {
				// Check if no attachments left, so we can delete the folder also
				$other_attachments = list_files($folder_name .$attachment->rel_id);
				if (count($other_attachments) == 0) {
					// okey only index.html so we can delete the folder also
					delete_dir($folder_name .$attachment->rel_id);
				}
			}
		}

		return $deleted;
	}

	/**
	 * delete product
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function delete_product($id)
	{

		/*delete file attachment*/
		if(1==2){			
			$array_file= $this->sm_get_attachments_file($id, 'commodity_item_file');
			if(count($array_file) > 0 ){
				foreach ($array_file as $key => $file_value) {
					$this->delete_sm_attachment_file($file_value['id'], SERVICE_MANAGEMENT_PRODUCT_UPLOAD);
				}
			}
		}

		$this->db->where('id', $id);
		$this->db->update(db_prefix() . 'items', ['active' => 0]);
		if ($this->db->affected_rows() > 0) {

			return true;
		}
		return false;

	}

	/**
	 * mrp get attachments file
	 * @param  [type] $rel_id   
	 * @param  [type] $rel_type 
	 * @return [type]           
	 */
	public function sm_get_attachments_file($rel_id, $rel_type)
	{
		$this->db->order_by('dateadded', 'desc');
		$this->db->where('rel_id', $rel_id);
		$this->db->where('rel_type', $rel_type);

		return $this->db->get(db_prefix() . 'files')->result_array();

	}


	/**
	 * add product
	 * @param [type] $data 
	 */
	public function add_product($formdata)
	{
		$arr_item_unit = [];
		$get_item_unit = $this->get_item_unit();
		foreach ($get_item_unit as $value) {
			$arr_item_unit[$value['id']] = $value;
		}

		$data=[];
		
		$arr_insert_cf=[];

		$unit_ids = [];
		$item_rates = [];
		$extend_values = [];
		$promotion_extended_percents = [];
		$status_cycles = [];
		$ids = [];
		$item_ids = [];
		$unit_values = [];
		$unit_types = [];
		$cycle_ids = [];

		foreach ($formdata['formdata'] as $key => $value) {
			if(preg_match('/^custom_fields/', $value['name'])){
				$index =  new_str_replace('custom_fields[items][', '', $value['name']);
				$index =  new_str_replace(']', '', $index);

				$arr_custom_fields[$index] = $value['value'];

			}elseif(preg_match('/^name/', $value['name'])){
				$variation_name_temp = $value['value'];
			}elseif(preg_match('/^options/', $value['name'])){
				$variation_option_temp = $value['value'];

				array_push($arr_variation, [
					'name' => $variation_name_temp,
					'options' => new_explode(',', $variation_option_temp),
				]);

				$variation_name_temp='';
				$variation_option_temp='';
			}elseif(preg_match("/^variation_names_/", $value['name'] )){
				array_push($arr_attributes, [
					'name' => new_str_replace('variation_names_', '', $value['name']),
					'option' => $value['value'],
				]);
			}elseif($value['name'] == 'supplier_taxes_id[]'){
				if(isset($data['supplier_taxes_id'])){
					$data['supplier_taxes_id'] .= ','.$value['value'];
				}else{
					$data['supplier_taxes_id'] = $value['value'];
				}

			}elseif(preg_match("/^unit_id/", $value['name'] )){
				$unit_ids[] = $value['value'];
			}elseif(preg_match("/^item_rate/", $value['name'] )){
				$item_rates[] = $value['value'];
			}elseif(preg_match("/^extend_value/", $value['name'] )){
				$extend_values[] = $value['value'];
			}elseif(preg_match("/^promotion_extended_percent/", $value['name'] )){
				$promotion_extended_percents[] = $value['value'];
			}elseif(preg_match("/^status_cycles/", $value['name'] )){
				$status_cycles[count($unit_ids)-1] = $value['value'];
			}elseif(preg_match("/^id/", $value['name'] )){
				$ids[] = $value['value'];
			}elseif(preg_match("/^item_id/", $value['name'] )){
				$item_ids[] = $value['value'];
			}elseif(preg_match("/^unit_value/", $value['name'] )){
				$unit_values[] = $value['value'];
			}elseif(preg_match("/^unit_type/", $value['name'] )){
				$unit_types[] = $value['value'];

			}elseif(preg_match("/^cycle_id/", $value['name'] )){
				$cycle_ids[] = $value['value'];

			}elseif( $value['name'] != 'csrf_token_name' && $value['name'] != 'id'){
				$data[$value['name']] = $value['value'];

			}

		}

		$data['service_policy'] = $formdata['service_policy'];
		$data['can_be_inventory'] = null;
		if(isset($data['allow_extension_service']) && $data['allow_extension_service'] == 'on'){
			$data['allow_extension_service'] = 'allow';
		}else{
			$data['allow_extension_service'] = 'reject';
		}

		//generate sku_code
		if($data['sku_code'] == ''){

			$sql_where = 'SELECT * FROM ' . db_prefix() . 'items order by id desc limit 1';
			$res = $this->db->query($sql_where)->row();
			$last_commodity_id = 0;
			if (isset($res)) {
				$last_commodity_id = $this->db->query($sql_where)->row()->id;
			}
			$next_commodity_id = (int) $last_commodity_id + 1;

			$sku_code = str_pad($next_commodity_id,5,'0',STR_PAD_LEFT); 
			if(new_strlen($data['commodity_code']) == 0){
				$data['commodity_code'] = $sku_code;
			}
			$data['sku_code'] = $sku_code;

		}else{
			if(new_strlen($data['commodity_code']) == 0){
				$data['commodity_code'] =  $data['sku_code'];
			}
		}

		$this->db->insert(db_prefix() . 'items', $data);
		$insert_id = $this->db->insert_id();

		if ($insert_id) {
			if(isset($arr_insert_cf) && count($arr_insert_cf) > 0){
				handle_custom_fields_post($insert_id, $arr_insert_cf, true);
			}

			$items_cycles_insert_data = [];

			//create cycle
			if(count($unit_ids) > 0){
				foreach ($unit_ids as $key => $value) {
					$unit_id = isset($unit_ids[$key]) && $unit_ids[$key] != '' ? $unit_ids[$key] : null;
					$unit_value = null;
					$unit_type = null;
					if($unit_id != null){
						$unit_value = isset($arr_item_unit[$unit_id]) ? $arr_item_unit[$unit_id]['unit_value'] : null;
						$unit_type = isset($arr_item_unit[$unit_id]) ? $arr_item_unit[$unit_id]['unit_type'] : null;
					}

					$items_cycles_insert_data[] = [
						"item_id" => $insert_id,
						"unit_id" => $unit_id,
						"unit_value" => $unit_value,
						"unit_type" => $unit_type,
						"item_rate" => isset($item_rates[$key]) && $item_rates[$key] != '' ? $item_rates[$key] : null,
						"extend_value" => isset($extend_values[$key]) && $extend_values[$key] != '' ? $extend_values[$key] : null,
						"promotion_extended_percent" => isset($promotion_extended_percents[$key]) && $promotion_extended_percents[$key] != '' ? $promotion_extended_percents[$key] : 0,
						"status_cycles" => isset($status_cycles[$key]) && $status_cycles[$key] == 'on' ? 'active' : 'inactive'
					];

				}

				if(count($items_cycles_insert_data) > 0){
					$this->db->insert_batch(db_prefix().'sm_items_cycles', $items_cycles_insert_data);
				}
			}
			
			log_activity('New Product service Added [ID:' . $insert_id . ', ' . $data['description'] . ']');

			return $insert_id;
		}

		return false;
	}


	/**
	 * update product
	 * @param  [type] $data 
	 * @param  [type] $id   
	 * @return [type]       
	 */
	public function update_product($formdata, $id)
	{
		$arr_item_unit = [];
		$get_item_unit = $this->get_item_unit();
		foreach ($get_item_unit as $value) {
			$arr_item_unit[$value['id']] = $value;
		}

		$affected_rows = 0;
		$data=[];
		
		$arr_insert_cf=[];
		$unit_ids = [];
		$item_rates = [];
		$extend_values = [];
		$promotion_extended_percents = [];
		$status_cycles = [];
		$ids = [];
		$item_ids = [];
		$unit_values = [];
		$unit_types = [];
		$cycle_ids = [];

		foreach ($formdata['formdata'] as $key => $value) {
			if(preg_match('/^custom_fields/', $value['name'])){
				$index =  new_str_replace('custom_fields[items][', '', $value['name']);
				$index =  new_str_replace(']', '', $index);

				$arr_custom_fields[$index] = $value['value'];

			}elseif(preg_match('/^name/', $value['name'])){
				$variation_name_temp = $value['value'];
			}elseif(preg_match('/^options/', $value['name'])){
				$variation_option_temp = $value['value'];

				array_push($arr_variation, [
					'name' => $variation_name_temp,
					'options' => new_explode(',', $variation_option_temp),
				]);

				$variation_name_temp='';
				$variation_option_temp='';
			}elseif(preg_match("/^variation_names_/", $value['name'] )){
				array_push($arr_attributes, [
					'name' => new_str_replace('variation_names_', '', $value['name']),
					'option' => $value['value'],
				]);
			}elseif($value['name'] == 'supplier_taxes_id[]'){
				if(isset($data['supplier_taxes_id'])){
					$data['supplier_taxes_id'] .= ','.$value['value'];
				}else{
					$data['supplier_taxes_id'] = $value['value'];
				}

			}elseif(preg_match("/^unit_id/", $value['name'] )){
				$unit_ids[] = $value['value'];
			}elseif(preg_match("/^item_rate/", $value['name'] )){
				$item_rates[] = $value['value'];
			}elseif(preg_match("/^extend_value/", $value['name'] )){
				$extend_values[] = $value['value'];
			}elseif(preg_match("/^promotion_extended_percent/", $value['name'] )){
				$promotion_extended_percents[] = $value['value'];
			}elseif(preg_match("/^status_cycles/", $value['name'] )){
				$status_cycles[count($unit_ids)-1] = $value['value'];
			}elseif(preg_match("/^id/", $value['name'] )){
				$ids[] = $value['value'];
			}elseif(preg_match("/^item_id/", $value['name'] )){
				$item_ids[] = $value['value'];
			}elseif(preg_match("/^unit_value/", $value['name'] )){
				$unit_values[] = $value['value'];
			}elseif(preg_match("/^unit_type/", $value['name'] )){
				$unit_types[] = $value['value'];

			}elseif(preg_match("/^cycle_id/", $value['name'] )){
				$cycle_ids[] = $value['value'];

			}elseif( $value['name'] != 'csrf_token_name' && $value['name'] != 'id'){
				$data[$value['name']] = $value['value'];

			}

		}

		$data['service_policy'] = $formdata['service_policy'];
		$data['can_be_inventory'] = null;
		if(isset($data['allow_extension_service']) && $data['allow_extension_service'] == 'on'){
			$data['allow_extension_service'] = 'allow';
		}else{
			$data['allow_extension_service'] = 'reject';
		}

		$this->db->where('id', $id);
		$this->db->update(db_prefix() . 'items', $data);
		if ($this->db->affected_rows() > 0) {
			$affected_rows++;
		}
			//create, inactive item billing plane when update
			$items_cycles_insert_data = [];
			$items_cycles_update_data = [];
			$items_cycles_delete_data = [];

			if(count($unit_ids) > 0){
				foreach ($unit_ids as $key => $value) {

					$unit_id = isset($unit_ids[$key]) && $unit_ids[$key] != '' ? $unit_ids[$key] : null;
					$unit_value = null;
					$unit_type = null;
					if($unit_id != null){
						$unit_value = isset($arr_item_unit[$unit_id]) ? $arr_item_unit[$unit_id]['unit_value'] : null;
						$unit_type = isset($arr_item_unit[$unit_id]) ? $arr_item_unit[$unit_id]['unit_type'] : null;
					}

					if(is_numeric($value) && $value > 0 && isset($cycle_ids[$key]) && is_numeric($cycle_ids[$key]) ){
						/*update*/
						$items_cycles_delete_data[] = $cycle_ids[$key];

						$items_cycles_update_data[] = [
							"id" => $cycle_ids[$key],
							"item_id" => $id,
							"unit_id" => $unit_id,
							"unit_value" => $unit_value,
							"unit_type" => $unit_type,
							"item_rate" => isset($item_rates[$key]) && $item_rates[$key] != '' ? $item_rates[$key] : null,
							"extend_value" => isset($extend_values[$key]) && $extend_values[$key] != '' ? $extend_values[$key] : null,
							"promotion_extended_percent" => isset($promotion_extended_percents[$key]) && $promotion_extended_percents[$key] != '' ? $promotion_extended_percents[$key] : 0,
							"status_cycles" => isset($status_cycles[$key]) && $status_cycles[$key] == 'on' ? 'active' : 'inactive'
						];

					}else{
						/*insert*/
						$items_cycles_insert_data[] = [
							"item_id" => $id,
							"unit_id" => $unit_id,
							"unit_value" => $unit_value,
							"unit_type" => $unit_type,
							"item_rate" => isset($item_rates[$key]) && $item_rates[$key] != '' ? $item_rates[$key] : null,
							"extend_value" => isset($extend_values[$key]) && $extend_values[$key] != '' ? $extend_values[$key] : null,
							"promotion_extended_percent" => isset($promotion_extended_percents[$key]) && $promotion_extended_percents[$key] != '' ? $promotion_extended_percents[$key] : 0,
							"status_cycles" => isset($status_cycles[$key]) && $status_cycles[$key] == 'on' ? 'active' : 'inactive'
						];

					}
				}

				if(count($items_cycles_delete_data) > 0){
					$this->db->where('id NOT IN('.implode(",", $items_cycles_delete_data).')');
					$this->db->where('item_id', $id);

					$affectedRows = $this->db->delete(db_prefix().'sm_items_cycles');
					if($affectedRows > 0){
						$affected_rows++;
					}
				}else{
					$this->db->where('item_id', $id);
					$affectedRows = $this->db->delete(db_prefix().'sm_items_cycles');
					if($affectedRows > 0){
						$affected_rows++;
					}
				}

				if(count($items_cycles_update_data) > 0){
					$affectedRows = $this->db->update_batch(db_prefix().'sm_items_cycles', $items_cycles_update_data, 'id');
					if($affectedRows > 0){
						$affected_rows++;
					}
				}

				if(count($items_cycles_insert_data) > 0){
					$affectedRows = $this->db->insert_batch(db_prefix().'sm_items_cycles', $items_cycles_insert_data, 'id');
					if($affectedRows > 0){
						$affected_rows++;
					}
				}

			}
			
	
		if($affected_rows > 0){
			return true;
		}
		return false;
	}

	/**
	 * create service row template
	 * @param  string  $name                 
	 * @param  string  $item_name            
	 * @param  string  $item_id              
	 * @param  string  $billing_plan_unit_id 
	 * @param  string  $billing_plan_value   
	 * @param  string  $billing_plan_type    
	 * @param  string  $billing_plan_rate    
	 * @param  integer $quantity             
	 * @param  string  $tax_name             
	 * @param  string  $tax_rate             
	 * @param  string  $tax_id               
	 * @param  string  $sub_total            
	 * @param  string  $total_money          
	 * @param  string  $discount             
	 * @param  string  $discount_money       
	 * @param  string  $total_after_discount 
	 * @param  string  $item_key             
	 * @param  boolean $is_edit              
	 * @return [type]                        
	 */
	public function create_service_row_template($name = '', $item_name = '',  $item_id = '', $billing_plan_unit_id = '', $billing_plan_value = '', $billing_plan_type = '', $billing_plan_rate = '', $quantity = 1, $taxname = '', $tax_name = '', $tax_rate = '', $tax_id = '', $sub_total = '', $total_money = '', $discount = '', $discount_money = '', $total_after_discount = '', $item_key = '', $is_edit = false, $client = false , $label_value = []) {

		$get_base_currency =  get_base_currency();
		if($get_base_currency){
			$base_currency_id = $get_base_currency->id;
		}else{
			$base_currency_id = 0;
		}
		
		$this->load->model('invoice_items_model');
		$row = '';

		$name_item_id = 'item_id';
		$name_item_name = 'item_name';
		$name_billing_plan_unit_id = 'billing_plan_unit_id';
		$name_billing_plan_value = 'billing_plan_value';
		$name_billing_plan_type = 'billing_plan_type';
		$name_available_quantity = 'available_quantity';
		$name_quantity = 'quantity';
		$name_billing_plan_rate = 'billing_plan_rate';
		$name_tax_id_select = 'tax_select';
		$name_tax_id = 'tax_id';
		$name_total_money = 'total_money';
		$name_tax_rate = 'tax_rate';
		$name_tax_name = 'tax_name';
		$array_attr = [];
		$array_attr_payment = ['data-payment' => 'invoice'];
		$name_sub_total = 'sub_total';
		$name_discount = 'discount';
		$name_discount_money = 'discount_money';
		$name_total_after_discount = 'total_after_discount';

		$array_qty_attr = [ 'min' => '0.0', 'step' => 'any', 'readonly' => true];
		$array_rate_attr = [ 'min' => '0.0', 'step' => 'any'];
		$array_discount_attr = [ 'min' => '0.0', 'step' => 'any'];
		$str_rate_attr = 'min="0.0" step="any"';
		$arr_quantity_attr = ['min' => '1'];


		if ($name == '') {
			$row .= '<tr class="main">
                  <td></td>';
			$vehicles = [];
			$array_attr = ['placeholder' => _l('billing_plan_rate')];
			$billing_plan_unit_id_name_attr = [];
			$manual             = true;
			$invoice_item_taxes = '';
			$amount = '';
			$sub_total = 0;
			$sm_product_cycle_ex = [];

		} else {
			$row .= '<tr class="sortable item">
					<td class="dragger"><input type="hidden" class="order" name="' . $name . '[order]"><input type="hidden" class="ids" name="' . $name . '[id]" value="' . $item_key . '"></td>';
			$name_item_id = $name . '[item_id]';
			$name_item_name = $name . '[item_name]';
			$name_billing_plan_unit_id = $name . '[billing_plan_unit_id]';
			$name_billing_plan_value = $name . '[billing_plan_value]';
			$name_billing_plan_type = $name . '[billing_plan_type]';
			$name_quantity = $name . '[quantity]';
			$name_billing_plan_rate = $name . '[billing_plan_rate]';
			$name_tax_id_select = $name . '[tax_select][]';
			$name_tax_id = $name . '[tax_id]';
			$name_total_money = $name . '[total_money]';
			$name_tax_rate = $name . '[tax_rate]';
			$name_tax_name = $name .'[tax_name]';
			$name_sub_total = $name .'[sub_total]';
			$name_discount = $name .'[discount]';
			$name_discount_money = $name .'[discount_money]';
			$name_total_after_discount = $name .'[total_after_discount]';

			$billing_plan_unit_id_name_attr = ["onchange" => "get_billing_unit('" . $name_item_id . "','" . $name_billing_plan_unit_id . "','" . $name_discount . "','" . $name_billing_plan_rate . "','" . $name_billing_plan_value . "','" . $name_billing_plan_type . "');", "data-none-selected-text" => _l('warehouse_name'), 'data-from_stock_id' => 'invoice'];
			
			$arr_quantity_attr = ['onblur' => 'wh_calculate_total();',  'onchange' => 'wh_calculate_total();', 'min' => '0.0' , 'step' => 'any',  'data-quantity' => (float)$quantity];

			$array_rate_attr = ['onblur' => 'wh_calculate_total();', 'onchange' => 'wh_calculate_total();', 'min' => '0.0' , 'step' => 'any', 'data-amount' => 'invoice', 'placeholder' => _l('rate')];
			$array_discount_attr = ['onblur' => 'wh_calculate_total();', 'onchange' => 'wh_calculate_total();', 'min' => '0.0' , 'step' => 'any', 'data-amount' => 'invoice', 'placeholder' => _l('sm_discount')];


			$manual             = false;

			$tax_money = 0;
			$tax_rate_value = 0;

			if($is_edit){
				$invoice_item_taxes = sm_convert_item_taxes($tax_id, $tax_rate, $tax_name);
				$arr_tax_rate = new_explode('|', $tax_rate);
				foreach ($arr_tax_rate as $key => $value) {
					$tax_rate_value += (float)$value;
				}
			}else{
				$invoice_item_taxes = $taxname;
				$tax_rate_data = $this->sm_get_tax_rate($taxname);
				$tax_rate_value = $tax_rate_data['tax_rate'];
			}

			if((float)$tax_rate_value != 0){
				$tax_money = (float)$billing_plan_rate * (float)$quantity * (float)$tax_rate_value / 100;
				$goods_money = (float)$billing_plan_rate * (float)$quantity + (float)$tax_money;
				$amount = (float)$billing_plan_rate * (float)$quantity + (float)$tax_money;
			}else{
				$goods_money = (float)$billing_plan_rate * (float)$quantity;
				$amount = (float)$billing_plan_rate * (float)$quantity;
			}

			$sub_total = (float)$billing_plan_rate * (float)$quantity;
			$amount = app_format_number($amount);

			$sm_product_cycle_ex = [];
			$product = $this->get_product($item_id);

			if($product){
				if (count($product->item_billing_plan) > 0) {
					foreach ($product->item_billing_plan as $item_billing_plan) {
						if($item_billing_plan['status_cycles'] == 'active'){

							$sm_product_cycle_ex[] = [
								'name' => $item_billing_plan['id'],
								'label' => app_format_money((float)$item_billing_plan['item_rate'], $base_currency_id).' ('. $item_billing_plan['unit_value'].' '. _l($item_billing_plan['unit_type']) . ')',
							];

						}
					}
				}
			}

		}

		if($client){

			$row .= '<td class="hide">' . render_textarea($name_item_name, '', $item_name, ['rows' => 2, 'placeholder' => _l('item_description_placeholder'), 'readonly' => true] ) . '</td>';
			$row .= '<td class="">' . $item_name . '</td>';
		}else{
			$row .= '<td class="">' . render_textarea($name_item_name, '', $item_name, ['rows' => 2, 'placeholder' => _l('item_description_placeholder'), 'readonly' => true] ) . '</td>';

		}

		if($client){
			$row .= '<td class="warehouse_select hide">' .
			render_select($name_billing_plan_unit_id, $sm_product_cycle_ex,array('name','label'),'',$billing_plan_unit_id, $billing_plan_unit_id_name_attr, ["data-none-selected-text" => _l('warehouse_name')], 'no-margin', '', false).
			'</td>';

			$row .= '<td class="warehouse_select text-right">' .$label_value['0'].	'</td>';
			
		}else{

			$row .= '<td class="warehouse_select">' .
			render_select($name_billing_plan_unit_id, $sm_product_cycle_ex,array('name','label'),'',$billing_plan_unit_id, $billing_plan_unit_id_name_attr, ["data-none-selected-text" => _l('warehouse_name')], 'no-margin', '', false).
			'</td>';
		}

		
		$row .= '<td class="quantity">' . render_input($name_quantity, '', $quantity, 'number', $arr_quantity_attr) . '</td>';

		$row .= '<td class="rate hide">' . render_input($name_billing_plan_rate, '', $billing_plan_rate, 'number', $array_rate_attr) . '</td>';

		if($client){
			$row .= '<td class="taxrate hide">' . $this->get_taxes_dropdown_template($name_tax_id_select, $invoice_item_taxes, 'invoice', $item_key, true, $manual) . '</td>';
			$row .= '<td class="taxrate text-right">' . $label_value['1'] . '</td>';
		}else{
			$row .= '<td class="taxrate">' . $this->get_taxes_dropdown_template($name_tax_id_select, $invoice_item_taxes, 'invoice', $item_key, true, $manual) . '</td>';
		}

		$row .= '<td class="amount" align="right">' . $amount . '</td>';

		if($client){
			$row .= '<td class="discount hide">' . render_input($name_discount, '', $discount, 'number', $array_discount_attr) . '</td>';
			$row .= '<td class="discount text-right">' . $discount . '%</td>';
		}else{

			$row .= '<td class="discount">' . render_input($name_discount, '', $discount, 'number', $array_discount_attr) . '</td>';
		}

		$row .= '<td class="label_discount_money" align="right">' . $amount . '</td>';
		$row .= '<td class="label_total_after_discount" align="right">' . $amount . '</td>';

		$row .= '<td class="hide item_id">' . render_input($name_item_id, '', $item_id, 'text', ['placeholder' => _l('item_id')]) . '</td>';
		$row .= '<td class="hide billing_plan_value">' . render_input($name_billing_plan_value, '', $billing_plan_value, 'text', ['placeholder' => _l('billing_plan_value')]) . '</td>';
		$row .= '<td class="hide billing_plan_type">' . render_input($name_billing_plan_type, '', $billing_plan_type, 'text', ['placeholder' => _l('billing_plan_type')]) . '</td>';
		$row .= '<td class="hide discount_money">' . render_input($name_discount_money, '', $discount_money, 'number', []) . '</td>';
		$row .= '<td class="hide total_after_discount">' . render_input($name_total_after_discount, '', $total_after_discount, 'number', []) . '</td>';

		if ($name == '') {
			$row .= '<td></td>';
			$row .= '<td><button type="button" onclick="sm_add_item_to_table(\'undefined\',\'undefined\'); return false;" class="btn pull-right btn-info"><i class="fa fa-check"></i></button></td>';
		} else {
			
			$row .= '<td></td>';
			$row .= '<td><a href="#" class="btn btn-danger pull-right" onclick="wh_delete_item(this,' . $billing_plan_unit_id . ',\'.invoice-item\'); return false;"><i class="fa fa-trash"></i></a></td>';
		}
		$row .= '</tr>';
		return $row;
	}

	public function get_taxes_dropdown_template($name, $taxname, $type = '', $item_key = '', $is_edit = false, $manual = false)
	{
        // if passed manually - like in proposal convert items or project
		if($taxname != '' && !is_array($taxname)){
			$taxname = new_explode(',', $taxname);
		}

		if ($manual == true) {
            // + is no longer used and is here for backward compatibilities
			if (is_array($taxname) || strpos($taxname, '+') !== false) {
				if (!is_array($taxname)) {
					$__tax = new_explode('+', $taxname);
				} else {
					$__tax = $taxname;
				}
                // Multiple taxes found // possible option from default settings when invoicing project
				$taxname = [];
				foreach ($__tax as $t) {
					$tax_array = new_explode('|', $t);
					if (isset($tax_array[0]) && isset($tax_array[1])) {
						array_push($taxname, $tax_array[0] . '|' . $tax_array[1]);
					}
				}
			} else {
				$tax_array = new_explode('|', $taxname);
                // isset tax rate
				if (isset($tax_array[0]) && isset($tax_array[1])) {
					$tax = get_tax_by_name($tax_array[0]);
					if ($tax) {
						$taxname = $tax->name . '|' . $tax->taxrate;
					}
				}
			}
		}
        // First get all system taxes
		$this->load->model('taxes_model');
		$taxes = $this->taxes_model->get();
		$i     = 0;
		foreach ($taxes as $tax) {
			unset($taxes[$i]['id']);
			$taxes[$i]['name'] = $tax['name'] . '|' . $tax['taxrate'];
			$i++;
		}
		if ($is_edit == true) {

            // Lets check the items taxes in case of changes.
            // Separate functions exists to get item taxes for Invoice, Estimate, Proposal, Credit Note
			$func_taxes = 'get_' . $type . '_item_taxes';
			if (function_exists($func_taxes)) {
				$item_taxes = call_user_func($func_taxes, $item_key);
			}

			foreach ($item_taxes as $item_tax) {
				$new_tax            = [];
				$new_tax['name']    = $item_tax['taxname'];
				$new_tax['taxrate'] = $item_tax['taxrate'];
				$taxes[]            = $new_tax;
			}
		}

        // In case tax is changed and the old tax is still linked to estimate/proposal when converting
        // This will allow the tax that don't exists to be shown on the dropdowns too.
		if (is_array($taxname)) {
			foreach ($taxname as $tax) {
                // Check if tax empty
				if ((!is_array($tax) && $tax == '') || is_array($tax) && $tax['taxname'] == '') {
					continue;
				};
                // Check if really the taxname NAME|RATE don't exists in all taxes
				if (!value_exists_in_array_by_key($taxes, 'name', $tax)) {
					if (!is_array($tax)) {
						$tmp_taxname = $tax;
						$tax_array   = new_explode('|', $tax);
					} else {
						$tax_array   = new_explode('|', $tax['taxname']);
						$tmp_taxname = $tax['taxname'];
						if ($tmp_taxname == '') {
							continue;
						}
					}
					$taxes[] = ['name' => $tmp_taxname, 'taxrate' => $tax_array[1]];
				}
			}
		}

        // Clear the duplicates
		$taxes = $this->wh_uniqueByKey($taxes, 'name');

		$select = '<select class="selectpicker display-block taxes" data-width="100%" name="' . $name . '" multiple data-none-selected-text="' . _l('no_tax') . '">';

		foreach ($taxes as $tax) {
			$selected = '';
			if (is_array($taxname)) {
				foreach ($taxname as $_tax) {
					if (is_array($_tax)) {
						if ($_tax['taxname'] == $tax['name']) {
							$selected = 'selected';
						}
					} else {
						if ($_tax == $tax['name']) {
							$selected = 'selected';
						}
					}
				}
			} else {
				if ($taxname == $tax['name']) {
					$selected = 'selected';
				}
			}

			$select .= '<option value="' . $tax['name'] . '" ' . $selected . ' data-taxrate="' . $tax['taxrate'] . '" data-taxname="' . $tax['name'] . '" data-subtext="' . $tax['name'] . '">' . $tax['taxrate'] . '%</option>';
		}
		$select .= '</select>';

		return $select;
	}

	/**
	 * wh uniqueByKey
	 * @param  [type] $array 
	 * @param  [type] $key   
	 * @return [type]        
	 */
	public function wh_uniqueByKey($array, $key)
	{
		$temp_array = [];
		$i          = 0;
		$key_array  = [];

		foreach ($array as $val) {
			if (!in_array($val[$key], $key_array)) {
				$key_array[$i]  = $val[$key];
				$temp_array[$i] = $val;
			}
			$i++;
		}

		return $temp_array;
	}

	/**
	 * get order
	 * @param  boolean $id 
	 * @return [type]      
	 */
	public function get_order($id = false, $where = '')
	{

		if (is_numeric($id)) {
			$this->db->where('id', $id);
			$order = $this->db->get(db_prefix() . 'sm_orders')->row();

			$this->db->where('order_id', $id);
			$order->order_details = $this->db->get(db_prefix().'sm_order_details')->result_array();

			return $order;
		}
		if ($id == false) {
			if(new_strlen($where) > 0){
				$this->db->where($where);
			}
			return $this->db->get(db_prefix() . 'sm_orders')->result_array();
		}

	}

	/**
	 * sm get grouped
	 * @param  string  $can_be     
	 * @param  boolean $search_all 
	 * @return [type]              
	 */
	public function sm_get_grouped($can_be = '', $search_all = false)
	{
		$items = [];
		$this->db->order_by('name', 'asc');
		$groups = $this->db->get(db_prefix() . 'items_groups')->result_array();

		array_unshift($groups, [
			'id'   => 0,
			'name' => '',
		]);

		foreach ($groups as $group) {
			$this->db->select('*,' . db_prefix() . 'items_groups.name as group_name,' . db_prefix() . 'items.id as id, description');
			if(new_strlen($can_be) > 0){
				$this->db->where($can_be, $can_be);
			}
			
			$this->db->where('group_id', $group['id']);
			$this->db->where(db_prefix().'items.can_be_product_service = "can_be_product_service"');
			$this->db->where(db_prefix().'items.active', 1);
			$this->db->join(db_prefix() . 'items_groups', '' . db_prefix() . 'items_groups.id = ' . db_prefix() . 'items.group_id', 'left');
			$this->db->order_by('description', 'asc');

			$_items = $this->db->get(db_prefix() . 'items')->result_array();

			if (count($_items) > 0) {
				$items[$group['id']] = [];
				foreach ($_items as $i) {
					array_push($items[$group['id']], $i);
				}
			}
		}

		return $items;
	}

	/**
	 * get item v2
	 * @param  string $id 
	 * @return [type]     
	 */
	public function get_item_v2($id = '')
	{
		$columns             = $this->db->list_fields(db_prefix() . 'items');
		$rateCurrencyColumns = '';
		foreach ($columns as $column) {
			if (strpos($column, 'rate_currency_') !== false) {
				$rateCurrencyColumns .= $column . ',';
			}
		}
		$this->db->select($rateCurrencyColumns . '' . db_prefix() . 'items.id as itemid,rate,
			t1.taxrate as taxrate,t1.id as taxid,t1.name as taxname,
			t2.taxrate as taxrate_2,t2.id as taxid_2,t2.name as taxname_2,
			CONCAT(commodity_code,"_",description) as code_description,long_description,group_id,' . db_prefix() . 'items_groups.name as group_name,unit');
		$this->db->from(db_prefix() . 'items');
		$this->db->join('' . db_prefix() . 'taxes t1', 't1.id = ' . db_prefix() . 'items.tax', 'left');
		$this->db->join('' . db_prefix() . 'taxes t2', 't2.id = ' . db_prefix() . 'items.tax2', 'left');
		$this->db->join(db_prefix() . 'items_groups', '' . db_prefix() . 'items_groups.id = ' . db_prefix() . 'items.group_id', 'left');
		$this->db->order_by('description', 'asc');
		if (is_numeric($id)) {
			$this->db->where(db_prefix() . 'items.id', $id);

			return $this->db->get()->row();
		}

		return $this->db->get()->result_array();
	}

	/**
	 * get product cycle
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function get_product_cycle($id, $where = '')
	{
		if (is_numeric($id)) {

			$this->db->where('id', $id);
			if(new_strlen($where) > 0){
				$this->db->where($where);
			}
			$item_billing_plan = $this->db->get(db_prefix().'sm_items_cycles')->row();
			return $item_billing_plan;
		}
	}

	/**
	 * sm check the next order
	 * @param  [type] $extend_value 
	 * @return [type]               
	 */
	public function sm_check_the_next_order($extend_value, $item_id, $client_id)
	{
		$sql = "SELECT client_id, item_id, count(".db_prefix()."sm_service_details.id) as total FROM ".db_prefix()."sm_service_details
		GROUP BY client_id, item_id";

		$orders = $this->db->query($sql)->result_array();

		foreach ($orders as $value) {
			if((int)$extend_value != 0){
				if($value['client_id'] == $client_id && $value['item_id'] == $item_id && (int)$value['total'] >= (int)$extend_value){
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * sm get tax rate
	 * @param  [type] $taxname 
	 * @return [type]          
	 */
	public function sm_get_tax_rate($taxname)
	{	
		$tax_rate = 0;
		$tax_rate_str = '';
		$tax_id_str = '';
		$tax_name_str = '';
		if(is_array($taxname)){
			foreach ($taxname as $key => $value) {
				$_tax = new_explode("|", $value);
				if(isset($_tax[1])){
					$tax_rate += (float)$_tax[1];
					if(new_strlen($tax_rate_str) > 0){
						$tax_rate_str .= '|'.$_tax[1];
					}else{
						$tax_rate_str .= $_tax[1];
					}

					$this->db->where('name', $_tax[0]);
					$taxes = $this->db->get(db_prefix().'taxes')->row();
					if($taxes){
						if(new_strlen($tax_id_str) > 0){
							$tax_id_str .= '|'.$taxes->id;
						}else{
							$tax_id_str .= $taxes->id;
						}
					}

					if(new_strlen($tax_name_str) > 0){
						$tax_name_str .= '|'.$_tax[0];
					}else{
						$tax_name_str .= $_tax[0];
					}
				}
			}
		}
		return ['tax_rate' => $tax_rate, 'tax_rate_str' => $tax_rate_str, 'tax_id_str' => $tax_id_str, 'tax_name_str' => $tax_name_str];
	}

	/**
     * Gets the tax name.
     *
     * @param        $tax    The tax
     *
     * @return     string  The tax name.
     */
	public function get_tax_name($tax){
		$this->db->where('id', $tax);
		$tax_if = $this->db->get(db_prefix().'taxes')->row();
		if($tax_if){
			return $tax_if->name;
		}
		return '';
	}

	/**
     * { tax rate by id }
     *
     * @param        $tax_id  The tax identifier
     */
	public function tax_rate_by_id($tax_id){
		$this->db->where('id', $tax_id);
		$tax = $this->db->get(db_prefix().'taxes')->row();
		if($tax){
			return $tax->taxrate;
		}
		return 0;
	}

	/**
	 * add order
	 * @param [type]  $data 
	 * @param boolean $id   
	 */
	public function add_order($data, $id = false) {
		$order_details = [];
		if (isset($data['newitems'])) {
			$order_details = $data['newitems'];
			unset($data['newitems']);
		}

		unset($data['item_select']);
		unset($data['item_name']);
		unset($data['item_id']);
		unset($data['billing_plan_unit_id']);
		unset($data['quantity']);
		unset($data['rate']);
		unset($data['discount']);
		unset($data['tax_rate']);
		unset($data['tax_name']);
		unset($data['discount_money']);
		unset($data['total_after_discount']);
		unset($data['billing_plan_value']);
		unset($data['billing_plan_rate']);
		unset($data['billing_plan_type']);
		if(isset($data['include_shipping'])){
			unset($data['include_shipping']);
		}
		if(isset($data['tax_select'])){
			unset($data['tax_select']);
		}

		$data['datecreated'] = to_sql_date($data['datecreated'], true);
		$data['total'] 	= reformat_currency_j($data['total']);
		$data['discount_total'] = reformat_currency_j($data['discount_total']);

		if($data['created_type'] == 'client'){
			$data['status'] = 'confirm';
		}else{
			$data['status'] = 'draft';
		}

		$this->db->insert(db_prefix() . 'sm_orders', $data);
		$insert_id = $this->db->insert_id();

		/*update save note*/

		if (isset($insert_id)) {
			foreach ($order_details as $order_detail) {
				$order_detail['order_id'] = $insert_id;

				$tax_money = 0;
				$tax_rate_value = 0;
				$tax_rate = null;
				$tax_id = null;
				$tax_name = null;
				if(isset($order_detail['tax_select'])){
					$tax_rate_data = $this->sm_get_tax_rate($order_detail['tax_select']);
					$tax_rate_value = $tax_rate_data['tax_rate'];
					$tax_rate = $tax_rate_data['tax_rate_str'];
					$tax_id = $tax_rate_data['tax_id_str'];
					$tax_name = $tax_rate_data['tax_name_str'];
				}

				if((float)$tax_rate_value != 0){
					$tax_money = (float)$order_detail['billing_plan_rate'] * (float)$order_detail['quantity'] * (float)$tax_rate_value / 100;
					$total_money = (float)$order_detail['billing_plan_rate'] * (float)$order_detail['quantity'] + (float)$tax_money;
					$amount = (float)$order_detail['billing_plan_rate'] * (float)$order_detail['quantity'] + (float)$tax_money;
				}else{
					$total_money = (float)$order_detail['billing_plan_rate'] * (float)$order_detail['quantity'];
					$amount = (float)$order_detail['billing_plan_rate'] * (float)$order_detail['quantity'];
				}

				$sub_total = (float)$order_detail['billing_plan_rate'] * (float)$order_detail['quantity'];

				$order_detail['tax_id'] = $tax_id;
				$order_detail['total_money'] = $total_money;
				$order_detail['tax_rate'] = $tax_rate;
				$order_detail['sub_total'] = $sub_total;
				$order_detail['tax_name'] = $tax_name;

				unset($order_detail['order']);
				unset($order_detail['id']);
				unset($order_detail['tax_select']);
				

				$this->db->insert(db_prefix() . 'sm_order_details', $order_detail);
			}

			if($data['created_type'] = 'client'){
				$this->remove_order_data_cookie();
			}
		}

		if (isset($insert_id)) {
			return $insert_id;
		}
		return false;
	}

	/**
	 * Adds a subscription order.
	 *
	 * @param        $data   The data
	 *
	 * @return     bool    
	 */
	public function add_subscription_order($data){

		$data['order_code'] = 'ORDER'.date('YmdHis');
		$data['created_type'] = 'client';
		$data['status'] = 'confirm';

		$this->db->insert(db_prefix() . 'sm_orders', $data);
		$insert_id = $this->db->insert_id();

		if($insert_id){
			return $insert_id;
		}
		return false;

	}

	/**
	 * update order
	 * @param  [type]  $data 
	 * @param  boolean $id   
	 * @return [type]        
	 */
	public function update_order($data, $id = false) {
    	$results=0;

    	$order_details = [];
		$update_order_details = [];
		$remove_order_details = [];
		if(isset($data['isedit'])){
			unset($data['isedit']);
		}

		if (isset($data['newitems'])) {
			$order_details = $data['newitems'];
			unset($data['newitems']);
		}

		if (isset($data['items'])) {
			$update_order_details = $data['items'];
			unset($data['items']);
		}
		if (isset($data['removed_items'])) {
			$remove_order_details = $data['removed_items'];
			unset($data['removed_items']);
		}

		unset($data['item_select']);
		unset($data['item_name']);
		unset($data['item_id']);
		unset($data['billing_plan_unit_id']);
		unset($data['quantity']);
		unset($data['rate']);
		unset($data['discount']);
		unset($data['tax_rate']);
		unset($data['tax_name']);
		unset($data['discount_money']);
		unset($data['total_after_discount']);
		unset($data['billing_plan_value']);
		unset($data['billing_plan_rate']);
		unset($data['billing_plan_type']);
		if(isset($data['include_shipping'])){
			unset($data['include_shipping']);
		}
		if(isset($data['tax_select'])){
			unset($data['tax_select']);
		}

    	$data['datecreated'] = to_sql_date($data['datecreated'], true);
		$data['total'] 	= reformat_currency_j($data['total']);
		$data['discount_total'] = reformat_currency_j($data['discount_total']);

    	$order_id = $data['id'];
    	unset($data['id']);

    	$this->db->where('id', $order_id);
    	$this->db->update(db_prefix() . 'sm_orders', $data);
    	if ($this->db->affected_rows() > 0) {
			$results++;
		}

    	/*update googs delivery*/
    	foreach ($update_order_details as $order_detail) {
			$tax_money = 0;
			$tax_rate_value = 0;
			$tax_rate = null;
			$tax_id = null;
			$tax_name = null;
			if(isset($order_detail['tax_select'])){
				$tax_rate_data = $this->sm_get_tax_rate($order_detail['tax_select']);
				$tax_rate_value = $tax_rate_data['tax_rate'];
				$tax_rate = $tax_rate_data['tax_rate_str'];
				$tax_id = $tax_rate_data['tax_id_str'];
				$tax_name = $tax_rate_data['tax_name_str'];
			}

			if((float)$tax_rate_value != 0){
				$tax_money = (float)$order_detail['billing_plan_rate'] * (float)$order_detail['quantity'] * (float)$tax_rate_value / 100;
				$total_money = (float)$order_detail['billing_plan_rate'] * (float)$order_detail['quantity'] + (float)$tax_money;
				$amount = (float)$order_detail['billing_plan_rate'] * (float)$order_detail['quantity'] + (float)$tax_money;
			}else{
				$total_money = (float)$order_detail['billing_plan_rate'] * (float)$order_detail['quantity'];
				$amount = (float)$order_detail['billing_plan_rate'] * (float)$order_detail['quantity'];
			}

			$sub_total = (float)$order_detail['billing_plan_rate'] * (float)$order_detail['quantity'];

			$order_detail['tax_id'] = $tax_id;
			$order_detail['total_money'] = $total_money;
			$order_detail['tax_rate'] = $tax_rate;
			$order_detail['sub_total'] = $sub_total;
			$order_detail['tax_name'] = $tax_name;

			unset($order_detail['order']);
			unset($order_detail['tax_select']);

			$this->db->where('id', $order_detail['id']);
			if ($this->db->update(db_prefix() . 'sm_order_details', $order_detail)) {
				$results++;
			}
		}

		// delete receipt note
		foreach ($remove_order_details as $order_detail_id) {
			$this->db->where('id', $order_detail_id);
			if ($this->db->delete(db_prefix() . 'sm_order_details')) {
				$results++;
			}
		}

		// Add goods deliveries
		foreach ($order_details as $order_detail) {
			$order_detail['order_id'] = $order_id;

			$tax_money = 0;
			$tax_rate_value = 0;
			$tax_rate = null;
			$tax_id = null;
			$tax_name = null;
			if(isset($order_detail['tax_select'])){
				$tax_rate_data = $this->sm_get_tax_rate($order_detail['tax_select']);
				$tax_rate_value = $tax_rate_data['tax_rate'];
				$tax_rate = $tax_rate_data['tax_rate_str'];
				$tax_id = $tax_rate_data['tax_id_str'];
				$tax_name = $tax_rate_data['tax_name_str'];
			}

			if((float)$tax_rate_value != 0){
				$tax_money = (float)$order_detail['billing_plan_rate'] * (float)$order_detail['quantity'] * (float)$tax_rate_value / 100;
				$total_money = (float)$order_detail['billing_plan_rate'] * (float)$order_detail['quantity'] + (float)$tax_money;
				$amount = (float)$order_detail['billing_plan_rate'] * (float)$order_detail['quantity'] + (float)$tax_money;
			}else{
				$total_money = (float)$order_detail['billing_plan_rate'] * (float)$order_detail['quantity'];
				$amount = (float)$order_detail['billing_plan_rate'] * (float)$order_detail['quantity'];
			}

			$sub_total = (float)$order_detail['billing_plan_rate'] * (float)$order_detail['quantity'];

			$order_detail['tax_id'] = $tax_id;
			$order_detail['total_money'] = $total_money;
			$order_detail['tax_rate'] = $tax_rate;
			$order_detail['sub_total'] = $sub_total;
			$order_detail['tax_name'] = $tax_name;

			unset($order_detail['order']);
			unset($order_detail['id']);
			unset($order_detail['tax_select']);

			$this->db->insert(db_prefix() . 'sm_order_details', $order_detail);
			if($this->db->insert_id()){
				$results++;
			}
		}

        
        hooks()->do_action('sm_after_order_updated', $order_id);

    	return $results > 0 ? true : false;

    }


	/**
	 * delete order
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function delete_order($id)
	{

		hooks()->do_action('sm_before_order_deleted', $id);
		$affected_rows = 0;

		$this->db->where('order_id', $id);
		$this->db->delete(db_prefix() . 'sm_order_details');
		if ($this->db->affected_rows() > 0) {

			$affected_rows++;
		}

		$this->db->where('id', $id);
		$this->db->delete(db_prefix() . 'sm_orders');
		if ($this->db->affected_rows() > 0) {

			$affected_rows++;
		}

		if ($affected_rows > 0) {
			return true;
		}
		return false;
	}

	/**
	 * get order without contract
	 * @param  string $order_id 
	 * @return [type]           
	 */
	public function get_order_without_contract($order_id = '')
	{

		$this->db->where('id NOT IN( select order_id from '.db_prefix().'sm_contracts where status <> "draft" AND status <> "cancelled" AND order_id IS NOT NULL AND order_id <> "0")');
		if(is_numeric($order_id)){
			$this->db->or_where('id', $order_id);
		}
		$this->db->where(db_prefix().'sm_orders.status <> "draft" AND '.db_prefix().'sm_orders.status <> "cancelled"');
		$orders = $this->db->get(db_prefix() . 'sm_orders')->result_array();

		return $orders;
	}

	/**
	 * get html tax order
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function get_html_tax_order($id)
	{
		$html = '';
		$html_currency = '';
		$preview_html = '';
		$pdf_html = '';
		$taxes = [];
		$t_rate = [];
		$tax_val = [];
		$tax_val_rs = [];
		$tax_name = [];
		$rs = [];
		$pdf_html_currency = '';

		$this->load->model('currencies_model');
		$base_currency = $this->currencies_model->get_base_currency();

		$order = $this->get_order($id);

		if($order->order_details){
			foreach($order->order_details as $row){
				if($row['tax_id'] != ''){
					$tax_arr = new_explode('|', $row['tax_id']);

					$tax_rate_arr = [];
					if($row['tax_rate'] != ''){
						$tax_rate_arr = new_explode('|', $row['tax_rate']);
					}

					foreach($tax_arr as $k => $tax_it){
						if(!isset($tax_rate_arr[$k]) ){
							$tax_rate_arr[$k] = $this->tax_rate_by_id($tax_it);
						}

						if(!in_array($tax_it, $taxes)){
							$taxes[$tax_it] = $tax_it;
							$t_rate[$tax_it] = $tax_rate_arr[$k];
							$tax_name[$tax_it] = $this->get_tax_name($tax_it).' ('.$tax_rate_arr[$k].'%)';
						}
					}
				}
			}
		}

		if($order->order_details){
			if(count($tax_name) > 0){
				foreach($tax_name as $key => $tn){
					$tax_val[$key] = 0;
					foreach($order->order_details as $row_dt){
						if(!(strpos($row_dt['tax_id'], $taxes[$key]) === false)){
							$tax_val[$key] += ($row_dt['quantity']*$row_dt['billing_plan_rate']*$t_rate[$key]/100);
						}
					}
					$pdf_html .= '<tr id="subtotal"><td ></td><td></td><td></td><td class="text_left">'.$tn.'</td><td class="text_right">'.app_format_money($tax_val[$key], $base_currency->symbol).'</td></tr>';
					$preview_html .= '<tr id="subtotal"><td>'.$tn.'</td><td>'.app_format_money($tax_val[$key], '').'</td><tr>';
					$html .= '<tr class="tax-area_pr"><td>'.$tn.'</td><td width="65%">'.app_format_money($tax_val[$key], '').'</td></tr>';
					$html_currency .= '<tr class="tax-area_pr"><td>'.$tn.'</td><td width="65%">'.app_format_money($tax_val[$key], $base_currency->symbol).'</td></tr>';
					$tax_val_rs[] = $tax_val[$key];
					$pdf_html_currency .= '<tr ><td align="right" width="85%">'.$tn.'</td><td align="right" width="15%">'.app_format_money($tax_val[$key], $base_currency->symbol).'</td></tr>';
				}
			}
		}

		$rs['pdf_html'] = $pdf_html;
		$rs['preview_html'] = $preview_html;
		$rs['html'] = $html;
		$rs['taxes'] = $taxes;
		$rs['taxes_val'] = $tax_val_rs;
		$rs['html_currency'] = $html_currency;
		$rs['pdf_html_currency'] = $pdf_html_currency;
		return $rs;
	}

	/**
	 * get item billing plan unit
	 * @param  boolean $id     
	 * @param  boolean $active 
	 * @return [type]          
	 */
	public function get_item_billing_plan_unit($id = false, $active = false) {

		if (is_numeric($id)) {
			$this->db->where('id', $id);
			return $this->db->get(db_prefix() . 'sm_items_cycles')->row();
		}
		if ($id == false) {
			if($active){
				$this->db->where('status_cycles', 'active');
			}
			return $this->db->get(db_prefix() . 'sm_items_cycles')->result_array();
		}

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

		$status_f = false;
		if($type == 'order'){
			$this->db->where('id', $id);
			$this->db->update(db_prefix() . 'sm_orders', ['status' => $status]);
			if ($this->db->affected_rows() > 0) {
				$status_f = true;
				//write log
			}
		}elseif($type == 'services'){
			$this->db->where('id', $id);
			$this->db->update(db_prefix() . 'sm_service_details', ['status' => $status]);
			if ($this->db->affected_rows() > 0) {
				$status_f = true;
				//write log
			}
		}elseif($type == 'packing_list'){
			$this->db->where('id', $id);
			$this->db->update(db_prefix() . 'wh_packing_lists', ['delivery_status' => $status]);
			if ($this->db->affected_rows() > 0) {
				$status_f = true;
				//write log for packing list
				$this->log_wh_activity($id, 'packing_list', _l('wh_'.$status));


				//write log for delivery note
				$activity_log = '';
				$delivery_id = '';
				$get_packing_list = $this->get_packing_list($id);
				if($get_packing_list){
					$activity_log .= $get_packing_list->packing_list_number .' - '.$get_packing_list->packing_list_name;
					$delivery_id = $get_packing_list->delivery_note_id;
				}
				$activity_log .= ': '._l('wh_'.$status);
				if(is_numeric($delivery_id)){
					
					$get_goods_delivery = $this->get_goods_delivery($delivery_id);
					if($get_goods_delivery && is_numeric($get_goods_delivery->customer_code)){
						$this->warehouse_check_update_shipment_when_delivery_note_approval($id, $status, 'packing_list_status_mark', $delivery_id);
					}
					
					$this->check_update_shipment_when_delivery_note_approval($id, $status, 'packing_list_status_mark', $delivery_id);


					$delivery_note_log_des = ' <a href="'.admin_url('warehouse/manage_packing_list/' . $id).'">'.$activity_log.'</a> ';
					$this->log_wh_activity($delivery_id, 'delivery', $delivery_note_log_des);

				// check update delivery status of delivery note
					$delivery_list_status = delivery_list_status();
					$arr_delivery_list_status_name = [];
					$arr_delivery_list_status_order = [];
					foreach ($delivery_list_status as $value) {
						$arr_delivery_list_status_name[$value['id']] = $value['order'];
						$arr_delivery_list_status_order[$value['order']] = $value['id'];
					}

					$get_packing_list_by_deivery_note = $this->get_packing_list_by_deivery_note($delivery_id);
					if(count($get_packing_list_by_deivery_note) > 0){
						$goods_delivery_status = '';
						$goods_delivery_status_order = '';
						$packing_list_order = 0;

						$get_goods_delivery = $this->get_goods_delivery($delivery_id);
						if($get_goods_delivery){
							$goods_delivery_status = $get_goods_delivery->delivery_status;
						}

						if(isset($arr_delivery_list_status_name[$goods_delivery_status])){
							$goods_delivery_status_order = $arr_delivery_list_status_name[$goods_delivery_status];
						}
						
						foreach ($get_packing_list_by_deivery_note as $value) {
							if(isset($arr_delivery_list_status_name[$value['delivery_status']])){
								if((int)$arr_delivery_list_status_name[$value['delivery_status']] >=  $packing_list_order){
									$packing_list_order = (int)$arr_delivery_list_status_name[$value['delivery_status']];
								}
							}
						}

						if((int)$packing_list_order > (int)$goods_delivery_status_order){
							if(isset($arr_delivery_list_status_order[$packing_list_order])){
								$this->db->where('id', $delivery_id);
								$this->db->update(db_prefix() . 'goods_delivery', ['delivery_status' => $arr_delivery_list_status_order[$packing_list_order] ]);

								$get_goods_delivery = $this->get_goods_delivery($delivery_id);
								if($get_goods_delivery && is_numeric($get_goods_delivery->customer_code)){
									$this->warehouse_check_update_shipment_when_delivery_note_approval($delivery_id, $arr_delivery_list_status_order[$packing_list_order], 'delivery_status_mark');
								}
								
								$this->check_update_shipment_when_delivery_note_approval($delivery_id, $arr_delivery_list_status_order[$packing_list_order], 'delivery_status_mark');

							}
						}

					}
				}

				
			}
		}
		return $status_f;
	}

	/**
	 * create invoice from order
	 * @param  [type] $order_id 
	 * @return [type]           
	 */
	public function create_invoice_from_order($order_id)
	{

		$order = $this->get_order($order_id);
		if($order){
			$this->load->model('currencies_model');
			$this->load->model('invoices_model');

			$arr_items = [];

			foreach ($order->order_details as $index => $order_detail) {
				$taxvalue = [];
				$tax_rates = new_explode('|', $order_detail['tax_rate']);
				$tax_names = new_explode('|', $order_detail['tax_name']);
				$tax_ids = new_explode('|', $order_detail['tax_id']);

				foreach ($tax_names as $key => $tax_name) {
					$tax_rate = isset($tax_rates[$key]) ? $tax_rates[$key] : 0;
				    $taxvalue[] = $tax_name. '|' .$tax_rate;
				}

				array_push($arr_items, [
					"order" => $index,
					"description" => $order_detail['item_name'],
					"long_description" => '',
					"unit" => '',
					"rate" => $order_detail['billing_plan_rate'],
					"qty" => $order_detail['quantity'],
					"taxname" => $taxvalue,
				]);
			}

			// get payment mode default
			$payment_mode_ids = [];
			$this->load->model('payment_modes_model');
			$data_payment_modes = $this->payment_modes_model->get('', [
				'expenses_only !=' => 1,
			]);
			foreach ($data_payment_modes as $data_payment_mode) {
				if($data_payment_mode['selected_by_default'] == 1){
					$payment_mode_ids[] = $data_payment_mode['id'];
				}
			}
			$sale_agent = $order->created_id;

			$base_currency = $this->currencies_model->get_base_currency();
			$customer_id = $order->client_id;

			$data['clientnote'] = $order->client_note;
			$data['adminnote'] = $order->admin_note;
			$data['cancel_merged_invoices'] = 'on';
			$data['clientid'] = $customer_id;
			$data['project_id'] = '';

			$data['billing_street'] = $order->billing_street;
			$data['billing_city'] = $order->billing_city;
			$data['billing_state'] = $order->billing_state;
			$data['billing_zip'] = $order->billing_zip;
			$data['billing_country'] = $order->billing_country;
			$data['include_shipping'] = 'on';
			$data['show_shipping_on_invoice'] = 'on';
			$data['shipping_street'] = $order->shipping_street;
			$data['shipping_city'] = $order->shipping_city;
			$data['shipping_state'] = $order->shipping_state;
			$data['shipping_zip'] = $order->shipping_zip;
			$data['shipping_country'] = $order->shipping_country;

			$data['number'] = get_option('next_invoice_number');
			$data['date'] = _d(date('Y-m-d'));
			$data['duedate'] = _d(date('Y-m-d', strtotime('+' . get_option('invoice_due_after') . ' DAY', strtotime(date('Y-m-d')))));

			if(is_array($payment_mode_ids)){
				$data['allowed_payment_modes'] = $payment_mode_ids;
			}else{
				$data['allowed_payment_modes'] = array(0 => $payment_mode_ids);
			}

			$data['tags'] = '';
			$data['currency'] = $base_currency->id;
			$data['sale_agent'] = $sale_agent;
			$data['recurring'] = '0';
			$data['repeat_every_custom'] = '1';
			$data['repeat_type_custom'] = 'day';
			$data['adminnote'] = '';
			$data['item_select'] = '';
			$data['task_select'] = '';
			$data['show_quantity_as'] = '';
			$data['description'] = '';
			$data['long_description'] = '';
			$data['quantity'] = '1';
			$data['unit'] = '';
			$data['rate'] = '';
			$data['taxname'] = 'TAXT 10|10.00';

			$data['adjustment'] = '0';
			$data['task_id'] = '';
			$data['expense_id'] = '';
			$data['terms'] = '';

			$data['total'] = $order->total;
			$data['subtotal'] = $order->sub_total;      
			$data['total_tax'] = $order->total_tax;
			$data['discount_total'] = $order->discount_total;
			$data['discount_percent'] = '0';
			$data['discount_type'] = 'after_tax';
			$data['newitems'] = $arr_items;

			$insert_id = $this->invoices_model->add($data);
			if ($insert_id) {
				$this->db->where('id', $order_id);
				$this->db->update(db_prefix().'sm_orders', ['invoice_id' => $insert_id]);
				return $insert_id;
			}
		}
		return false;
	}

	/**
	 * create servicee log for order
	 * @param  [type] $payment_id 
	 * @return [type]             
	 */
	public function create_servicee_log_for_order($payment_id)
	{
		$this->db->where('id', $payment_id);    
		$invoicepaymentrecords = $this->db->get(db_prefix().'invoicepaymentrecords')->row();
		if($invoicepaymentrecords){
			$this->load->model('invoices_model');
			$invoice = $this->invoices_model->get($invoicepaymentrecords->invoiceid);

			$this->db->where('invoice_id', $invoicepaymentrecords->invoiceid);
			$order = $this->db->get(db_prefix().'sm_orders')->row();

			$this->db->where('renewal_invoice_id', $invoicepaymentrecords->invoiceid);
			$renewal_service = $this->db->get(db_prefix().'sm_service_invoices')->row();

			
			if($invoice && is_numeric($invoice->subscription_id) && $invoice->subscription_id != 0){

				$this->db->where('subscription_id', $invoice->subscription_id);
				$this->db->order_by('id', 'desc');
				$order = $this->db->get(db_prefix().'sm_orders')->row();

				if($order){
					$this->db->where('id', $order->id);
					$this->db->update(db_prefix().'sm_orders', ['invoice_id' => $invoice->id, 'status' => 'paid']);
				}

			}elseif($order){
				//check if payment for invoice == order amount
				$sql_where = "SELECT sum(amount) as total_payment FROM `".db_prefix()."invoicepaymentrecords`
				WHERE invoiceid = '".$invoicepaymentrecords->invoiceid."'
				GROUP BY invoiceid;";
				$invoice_payment = $this->db->query($sql_where)->row();

				if((float)$invoice_payment->total_payment >= $order->total){
					$get_order = $this->get_order($order->id);
					if($get_order){
						$arr_service_details = [];
						foreach ($get_order->order_details as $order_detail) {
							$start_date = date('Y-m-d H:i:s');
							$expiration_date = NULL;

							if($order_detail['billing_plan_type'] == 'day'){
								$temp_day = $order_detail['billing_plan_value']*$order_detail['quantity'];
								$expiration_date = date('Y-m-d H:i:s', strtotime('+'.(int)$temp_day.' days', strtotime($start_date)));

							}elseif($order_detail['billing_plan_type'] == 'month'){
								$temp_month = $order_detail['billing_plan_value']*$order_detail['quantity'];
								$expiration_date = date('Y-m-d H:i:s', strtotime('+'.(int)$temp_month.' months', strtotime($start_date)));

							}elseif($order_detail['billing_plan_type'] == 'year'){
								$temp_year = $order_detail['billing_plan_value']*$order_detail['quantity'];
								$expiration_date = date('Y-m-d H:i:s', strtotime('+'.(int)$temp_year.' years', strtotime($start_date)));
							}


							$arr_service_details[] = [
								'order_id' => $order->id,
								'invoice_id' => $invoicepaymentrecords->invoiceid,
								'client_id' => $get_order->client_id,
								'item_id' => $order_detail['item_id'],
								'item_name' => $order_detail['item_name'],
								'billing_plan_unit_id' => $order_detail['billing_plan_unit_id'],
								'billing_plan_value' => $order_detail['billing_plan_value'],
								'billing_plan_type' => $order_detail['billing_plan_type'],
								'billing_plan_rate' => $order_detail['billing_plan_rate'],
								'quantity' => $order_detail['quantity'],
								'discount' => $order_detail['discount'],
								'discount_money' => $order_detail['discount_money'],
								'total_after_discount' => $order_detail['total_after_discount'],
								'tax_id' => $order_detail['tax_id'],
								'tax_rate' => $order_detail['tax_rate'],
								'tax_name' => $order_detail['tax_name'],
								'sub_total' => $order_detail['sub_total'],
								'total_money' => $order_detail['total_money'],
								'start_date' => $start_date,
								'expiration_date' => $expiration_date,
								'status' => 'activate',
								'datecreated' => date('Y-m-d H:i:s'),
						    ];
						}

						if(count($arr_service_details) > 0){
							$this->db->insert_batch(db_prefix().'sm_service_details', $arr_service_details);
						}
					}

				}
			}elseif($renewal_service){
				/*case renewal service*/
				//check if payment for invoice == order amount
				$sql_where = "SELECT sum(amount) as total_payment FROM `".db_prefix()."invoicepaymentrecords`
				WHERE invoiceid = '".$invoicepaymentrecords->invoiceid."'
				GROUP BY invoiceid;";
				$invoice_payment = $this->db->query($sql_where)->row();

				$this->load->model('invoices_model');
				$invoices = $this->invoices_model->get($invoicepaymentrecords->invoiceid);

				if((float)$invoice_payment->total_payment >= $invoices->total){
					$get_old_service = $this->get_service($renewal_service->old_service_id);
					$get_order = $this->get_order($renewal_service->order_id);


					if($get_old_service){
						$arr_service_details = [];
						$start_date = date('Y-m-d H:i:s');

						if(strtotime($start_date) < strtotime($get_old_service->expiration_date)){
							$start_date = $get_old_service->expiration_date;
						}
						$expiration_date = NULL;

						if($get_old_service->billing_plan_type == 'day'){
							$temp_day = $get_old_service->billing_plan_value*$get_old_service->quantity;
							$expiration_date = date('Y-m-d H:i:s', strtotime('+'.(int)$temp_day.' days', strtotime($start_date)));

						}elseif($get_old_service->billing_plan_type == 'month'){
							$temp_month = $get_old_service->billing_plan_value*$get_old_service->quantity;
							$expiration_date = date('Y-m-d H:i:s', strtotime('+'.(int)$temp_month.' months', strtotime($start_date)));

						}elseif($get_old_service->billing_plan_type == 'year'){
							$temp_year = $get_old_service->billing_plan_value*$get_old_service->quantity;
							$expiration_date = date('Y-m-d H:i:s', strtotime('+'.(int)$temp_year.' years', strtotime($start_date)));
						}

						$allow_discount = false;
						$discount_percent = 0;
						$total_tax_rate = 0;
						$get_product_cycle = $this->get_product_cycle($get_old_service->billing_plan_unit_id, 'status_cycles = "active"');
						if($get_product_cycle){
							$allow_discount = $this->sm_check_the_next_order($get_product_cycle->extend_value,$get_old_service->item_id, $get_old_service->client_id);
							if($allow_discount){
								$discount_percent = $get_product_cycle->promotion_extended_percent;
							}
						}
						$tax_rates = new_explode('|', $get_old_service->tax_rate);
						$tax_names = new_explode('|', $get_old_service->tax_name);
						$tax_ids = new_explode('|', $get_old_service->tax_id);

						foreach ($tax_names as $key => $tax_name) {
							$tax_rate = isset($tax_rates[$key]) ? $tax_rates[$key] : 0;
							$taxvalue[] = $tax_name. '|' .$tax_rate;
							$total_tax_rate += $tax_rate;
						}
						$subtotal = round((float)$get_old_service->billing_plan_rate*$get_old_service->quantity, 2);
						$total_tax = round((float)$subtotal*$total_tax_rate/100, 2);
						$discount_total = round((float)$discount_percent*($subtotal+$total_tax)/100, 2);
						$total = $subtotal+$total_tax-$discount_total;


						$arr_service_details[] = [
							'order_id' => $get_order->id,
							'invoice_id' => $invoicepaymentrecords->invoiceid,
							'client_id' => $get_order->client_id,
							'item_id' => $get_old_service->item_id,
							'item_name' => $get_old_service->item_name,
							'billing_plan_unit_id' => $get_old_service->billing_plan_unit_id,
							'billing_plan_value' => $get_old_service->billing_plan_value,
							'billing_plan_type' => $get_old_service->billing_plan_type,
							'billing_plan_rate' => $get_old_service->billing_plan_rate,
							'quantity' => $get_old_service->quantity,
							'discount' => $discount_percent,
							'discount_money' => $discount_total,
							'total_after_discount' => $total,
							'tax_id' => $get_old_service->tax_id,
							'tax_rate' => $get_old_service->tax_rate,
							'tax_name' => $get_old_service->tax_name,
							'sub_total' => $subtotal,
							'total_money' => $subtotal+$total_tax,
							'start_date' => $start_date,
							'expiration_date' => $expiration_date,
							'status' => 'service_has_been_renewal',
							'datecreated' => date('Y-m-d H:i:s'),
						];

						if(count($arr_service_details) > 0){
							$this->db->insert_batch(db_prefix().'sm_service_details', $arr_service_details);
						}
					}

				}

			}
		}

		return true;
	}

	/**
	 * get service
	 * @param  boolean $id    
	 * @param  string  $where 
	 * @return [type]         
	 */
	public function get_service($id = false, $where = '')
	{
		if (is_numeric($id)) {
			$this->db->where('id', $id);
			$service = $this->db->get(db_prefix() . 'sm_service_details')->row();
			return $service;
		}
		if ($id == false) {
			if(new_strlen($where) > 0){
				$this->db->where($where);
			}
			$this->db->order_by('id', 'desc');
			return $this->db->get(db_prefix() . 'sm_service_details')->result_array();
		}

	}

	/**
	 * renewal service
	 * @param  [type] $service_id 
	 * @return [type]             
	 */
	public function renewal_service($service_id)
	{
		$service = $this->get_service($service_id);
		if($service){
			$total = 0;
			$subtotal = 0;
			$total_tax = 0;
			$discount_total = 0;
			$total_tax_rate = 0;
			$product_rate = $service->billing_plan_rate;

			$order = $this->get_order($service->order_id);
			$this->load->model('currencies_model');
			$this->load->model('invoices_model');

			$allow_discount = false;
			$discount_percent = 0;
			$get_product_cycle = $this->get_product_cycle($service->billing_plan_unit_id, 'status_cycles = "active"');
			if($get_product_cycle){
				$product_rate = $get_product_cycle->item_rate;
				$allow_discount = $this->sm_check_the_next_order($get_product_cycle->extend_value,$service->item_id, $service->client_id);
				if($allow_discount){
					$discount_percent = $get_product_cycle->promotion_extended_percent;
				}
			}

			$arr_items = [];

			$taxvalue = [];
			$tax_rates = new_explode('|', $service->tax_rate);
			$tax_names = new_explode('|', $service->tax_name);
			$tax_ids = new_explode('|', $service->tax_id);

			/*get product information*/
			$product = $this->get_product($service->item_id);

			if($product){
				$tax_rates = [];
				$tax_names = [];
				$tax_ids = [];

				if($product->tax != null && is_numeric($product->tax) && $product->tax != 0){
					$tax_rates[] = $this->tax_rate_by_id($product->tax);
					$tax_names[] = $this->get_tax_name($product->tax);
					$tax_ids[] = $product->tax;
				}

				if($product->tax2 != null && is_numeric($product->tax2) && $product->tax2 != 0){
					$tax_rates[] = $this->tax_rate_by_id($product->tax2);
					$tax_names[] = $this->get_tax_name($product->tax2);
					$tax_ids[] = $product->tax2;
				}
				
			}

			foreach ($tax_names as $key => $tax_name) {
				$tax_rate = isset($tax_rates[$key]) ? $tax_rates[$key] : 0;
				$taxvalue[] = $tax_name. '|' .$tax_rate;
				$total_tax_rate += $tax_rate;
			}

			array_push($arr_items, [
				"order" => 1,
				"description" => $service->item_name,
				"long_description" => '',
				"unit" => '',
				"rate" => $product_rate,
				"qty" => $service->quantity,
				"taxname" => $taxvalue,
			]);

			$subtotal = round((float)$product_rate*$service->quantity, 2);
			$total_tax = round((float)$subtotal*$total_tax_rate/100, 2);
			$discount_total = round((float)$discount_percent*($subtotal+$total_tax)/100, 2);
			$total = $subtotal+$total_tax-$discount_total;

			// get payment mode default
			$payment_mode_ids = [];
			$this->load->model('payment_modes_model');
			$data_payment_modes = $this->payment_modes_model->get('', [
				'expenses_only !=' => 1,
			]);
			foreach ($data_payment_modes as $data_payment_mode) {
				if($data_payment_mode['selected_by_default'] == 1){
					$payment_mode_ids[] = $data_payment_mode['id'];
				}
			}
			$sale_agent = get_staff_user_id(0);

			$base_currency = $this->currencies_model->get_base_currency();
			$customer_id = $order->client_id;

			$data['clientnote'] = $order->client_note;
			$data['adminnote'] = $order->admin_note;
			$data['cancel_merged_invoices'] = 'on';
			$data['clientid'] = $customer_id;
			$data['project_id'] = '';

			$data['billing_street'] = $order->billing_street;
			$data['billing_city'] = $order->billing_city;
			$data['billing_state'] = $order->billing_state;
			$data['billing_zip'] = $order->billing_zip;
			$data['billing_country'] = $order->billing_country;
			$data['include_shipping'] = 'on';
			$data['show_shipping_on_invoice'] = 'on';
			$data['shipping_street'] = $order->shipping_street;
			$data['shipping_city'] = $order->shipping_city;
			$data['shipping_state'] = $order->shipping_state;
			$data['shipping_zip'] = $order->shipping_zip;
			$data['shipping_country'] = $order->shipping_country;

			$data['number'] = get_option('next_invoice_number');
			$data['date'] = _d(date('Y-m-d'));
			$data['duedate'] = _d(date('Y-m-d', strtotime('+' . get_option('invoice_due_after') . ' DAY', strtotime(date('Y-m-d')))));

			if(is_array($payment_mode_ids)){
				$data['allowed_payment_modes'] = $payment_mode_ids;
			}else{
				$data['allowed_payment_modes'] = array(0 => $payment_mode_ids);
			}

			$data['tags'] = '';
			$data['currency'] = $base_currency->id;
			$data['sale_agent'] = $sale_agent;
			$data['recurring'] = '0';
			$data['repeat_every_custom'] = '1';
			$data['repeat_type_custom'] = 'day';
			$data['adminnote'] = '';
			$data['item_select'] = '';
			$data['task_select'] = '';
			$data['show_quantity_as'] = '';
			$data['description'] = '';
			$data['long_description'] = '';
			$data['quantity'] = '1';
			$data['unit'] = '';
			$data['rate'] = '';
			$data['taxname'] = 'TAXT 10|10.00';

			$data['adjustment'] = '0';
			$data['task_id'] = '';
			$data['expense_id'] = '';
			$data['terms'] = '';

			$data['total'] = $total;
			$data['subtotal'] = $subtotal;      
			$data['total_tax'] = $total_tax;
			$data['discount_total'] = $discount_total;
			$data['discount_percent'] = '0';
			$data['discount_type'] = 'after_tax';
			$data['newitems'] = $arr_items;

			$insert_id = $this->invoices_model->add($data);
			if ($insert_id) {
				$this->db->where('id', $service_id);
				$this->db->update(db_prefix().'sm_service_details', ['status' => 'complete']);

				$service_inovices = [
					'old_service_id' => $service_id,
					'renewal_invoice_id' => $insert_id,
					'order_id' => $order->id,
					'client_id' => $order->client_id,
					'datecreated' => date('Y-m-d H:i:s'),
				];
				$this->db->insert(db_prefix().'sm_service_invoices', $service_inovices);
				return $insert_id;
			}
		}



	}

	/**
	 * count service by status
	 * @param  string $client_id 
	 * @return [type]            
	 */
	public function count_service_by_status($client_id = '')
	{
		$status = [];
		$sql_where = "SELECT count(id) as total, status, client_id FROM ".db_prefix()."sm_service_details
		WHERE client_id = '".$client_id."'
		GROUP BY client_id, ".db_prefix()."sm_service_details.status;";
		$service_detail = $this->db->query($sql_where)->result_array();
		foreach ($service_detail as $value) {
		    $status[$value['status']] = $value['total'];
		}

		return $status;
	}

	/**
	 * get list product by group
	 * @param  [type]  $id_chanel    
	 * @param  string  $id_group     
	 * @param  string  $id_warehouse 
	 * @param  string  $key          
	 * @param  integer $limit        
	 * @param  integer $ofset        
	 * @return [type]                
	 */
	public function get_list_product_by_group($id_chanel, $id_group = '0', $id_warehouse = '', $key = '',$limit = 0, $ofset = 1){

		// Search product
		$search = '';
		if($key!=''){
			$search = ' and (description like \'%'.$key.'%\' or rate like \'%'.$key.'%\' or sku_code like \'%'.$key.'%\' or commodity_barcode like \'%'.$key.'%\') ';
		}

		// Product by group
		$group = '';
		if($id_group != '0'){
			$group = ' and group_id = '.$id_group.'';
		}


		$where = 'can_be_product_service = "can_be_product_service" AND active = 1';
		$where .= $group.''.$search;
		if($where != ''){
			$where = 'where '.$where;
		}

		$count_product = 'select count(id) as count from '.db_prefix().'items '.$where;
		$select_list_product = 'select * from '.db_prefix().'items '.$where.' limit '.$limit.','.$ofset;

		$arr_billing_plan = [];

		$this->db->where('status_cycles', 'active');
		$item_billing_plan = $this->db->get(db_prefix().'sm_items_cycles')->result_array();
		foreach ($item_billing_plan as $value) {
			if($value['status_cycles'] == 'active'){
				$arr_billing_plan[$value['item_id']][] = $value;
			}
		}

		return [
			'list_product' => $this->db->query($select_list_product)->result_array(),
			'count' => (int)$this->db->query($count_product)->row()->count,
			'arr_billing_plan' => $arr_billing_plan,
		];
	}

	/**
	 * sm_get_all_image_file_name
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function sm_get_all_image_file_name($id){
		$this->db->order_by('dateadded', 'desc');
		$this->db->where('rel_id',$id);
		$this->db->where('rel_type','commodity_item_file');
		$this->db->select('file_name');
		return $this->db->get(db_prefix().'files')->result_array();
	}

	/**
	 * remove order data cookie
	 * @return [type] 
	 */
	public function remove_order_data_cookie(){
		if (isset($_COOKIE['service_qty_list'])&&isset($_COOKIE['service_id_list'])) {
			unset($_COOKIE['service_qty_list']); 
			unset($_COOKIE['service_id_list']); 
			setcookie('service_qty_list', null, -1, '/'); 
			setcookie('service_id_list', null, -1, '/'); 
			return true;
		} else {
			return false;
		}
	}

	/**
	 * sm get list product by group_s
	 * @param  [type]  $id_chanel  
	 * @param  string  $id_group   
	 * @param  string  $id_product 
	 * @param  integer $limit      
	 * @param  integer $ofset      
	 * @return [type]              
	 */
	public function sm_get_list_product_by_group_s($id_chanel, $id_group = '', $id_product = '', $limit = 0, $ofset = 1){

		if($id_group!=''){
			$count_product = 'select count(id) as count from '.db_prefix().'items where group_id = '.$id_group.' and id != '.$id_product.' AND can_be_product_service = "can_be_product_service"';
			
			$select_list_product = 'select  id, description, long_description, rate from '.db_prefix().'items where  group_id = '.$id_group.' AND can_be_product_service = "can_be_product_service" and id != '.$id_product.' limit '.$limit.','.$ofset;

			$arr_billing_plan = [];

			$this->db->where('status_cycles', 'active');
			$item_billing_plan = $this->db->get(db_prefix().'sm_items_cycles')->result_array();
			foreach ($item_billing_plan as $value) {
				if($value['status_cycles'] == 'active'){
					$arr_billing_plan[$value['item_id']][] = $value;
				}
			}

			$result = [
				'list_product' => $this->db->query($select_list_product)->result_array(),
				'count' => (int)$this->db->query($count_product)->row()->count,
				'arr_billing_plan' => $arr_billing_plan,
			];
			return $result;
		}
	}

/**
 * add subscription service
 * @param array $data 
 */
	public function add_subscription_service($formdata){
		$arr_item_unit = [];
		$get_item_unit = $this->get_item_unit();
		foreach ($get_item_unit as $value) {
			$arr_item_unit[$value['id']] = $value;
		}

		$data=[];
		
		$arr_insert_cf=[];

		$item_rates = [];
		$extend_values = [];
		$promotion_extended_percents = [];
		$status_cycles = [];
		$ids = [];
		$item_ids = [];
		$unit_values = [];
		$unit_types = [];
		$cycle_ids = [];

		foreach ($formdata['formdata'] as $key => $value) {
			if(preg_match('/^custom_fields/', $value['name'])){
				$index =  new_str_replace('custom_fields[items][', '', $value['name']);
				$index =  new_str_replace(']', '', $index);

				$arr_custom_fields[$index] = $value['value'];

			}elseif(preg_match('/^name/', $value['name'])){
				$variation_name_temp = $value['value'];
			}elseif(preg_match('/^options/', $value['name'])){
				$variation_option_temp = $value['value'];

				array_push($arr_variation, [
					'name' => $variation_name_temp,
					'options' => new_explode(',', $variation_option_temp),
				]);

				$variation_name_temp='';
				$variation_option_temp='';
			}elseif(preg_match("/^variation_names_/", $value['name'] )){
				array_push($arr_attributes, [
					'name' => new_str_replace('variation_names_', '', $value['name']),
					'option' => $value['value'],
				]);
			}elseif($value['name'] == 'supplier_taxes_id[]'){
				if(isset($data['supplier_taxes_id'])){
					$data['supplier_taxes_id'] .= ','.$value['value'];
				}else{
					$data['supplier_taxes_id'] = $value['value'];
				}

			}elseif(preg_match("/^item_rate/", $value['name'] )){
				$item_rates[] = $value['value'];
			}elseif(preg_match("/^extend_value/", $value['name'] )){
				$extend_values[] = $value['value'];
			}elseif(preg_match("/^promotion_extended_percent/", $value['name'] )){
				$promotion_extended_percents[] = $value['value'];
			}elseif(preg_match("/^id/", $value['name'] )){
				$ids[] = $value['value'];
			}elseif(preg_match("/^item_id/", $value['name'] )){
				$item_ids[] = $value['value'];
			}elseif(preg_match("/^unit_value/", $value['name'] )){
				$unit_values[] = $value['value'];
			}elseif(preg_match("/^unit_type/", $value['name'] )){
				$unit_types[] = $value['value'];

			}elseif(preg_match("/^cycle_id/", $value['name'] )){
				$cycle_ids[] = $value['value'];

			}elseif( $value['name'] != 'csrf_token_name' && $value['name'] != 'id'){
				$data[$value['name']] = $value['value'];

			}

		}

		$data['service_policy'] = $formdata['service_policy'];
		$data['can_be_inventory'] = null;
		if(isset($data['allow_extension_service']) && $data['allow_extension_service'] == 'on'){
			$data['allow_extension_service'] = 'allow';
		}else{
			$data['allow_extension_service'] = 'reject';
		}

		//generate sku_code
		if($data['sku_code'] == ''){

			$sql_where = 'SELECT * FROM ' . db_prefix() . 'items order by id desc limit 1';
			$res = $this->db->query($sql_where)->row();
			$last_commodity_id = 0;
			if (isset($res)) {
				$last_commodity_id = $this->db->query($sql_where)->row()->id;
			}
			$next_commodity_id = (int) $last_commodity_id + 1;

			$sku_code = str_pad($next_commodity_id,5,'0',STR_PAD_LEFT); 
			if(new_strlen($data['commodity_code']) == 0){
				$data['commodity_code'] = $sku_code;
			}
			$data['sku_code'] = $sku_code;

		}else{
			if(new_strlen($data['commodity_code']) == 0){
				$data['commodity_code'] =  $data['sku_code'];
			}
		}
		$data['subscription_price'] = $formdata['subscription_price'];
		$data['subscription_period'] = $formdata['subscription_period'];
		$data['subscription_count'] = $formdata['subscription_count'];
		$data['service_type'] = 'subscriptions';
		$this->db->insert(db_prefix() . 'items', $data);
		$insert_id = $this->db->insert_id();

		if ($insert_id) {
			if(isset($arr_insert_cf) && count($arr_insert_cf) > 0){
				handle_custom_fields_post($insert_id, $arr_insert_cf, true);
			}
			log_activity('New Subscription Service Added [ID:' . $insert_id . ', ' . $data['description'] . ']');
			return $insert_id;
		}
		return false;
	}
	/**
	 * update subscription service
	 * @param  array $data 
	 * @return boolean       
	 */
	public function update_subscription_service($formdata, $id){
		$affected_rows = 0;
		$data=[];
		$arr_insert_cf=[];
		$item_rates = [];
		$extend_values = [];
		$promotion_extended_percents = [];
		$status_cycles = [];
		$ids = [];
		$item_ids = [];
		$unit_values = [];
		$unit_types = [];
		$cycle_ids = [];
		foreach ($formdata['formdata'] as $key => $value) {
			if(preg_match('/^custom_fields/', $value['name'])){
				$index =  new_str_replace('custom_fields[items][', '', $value['name']);
				$index =  new_str_replace(']', '', $index);
				$arr_custom_fields[$index] = $value['value'];
			}elseif(preg_match('/^name/', $value['name'])){
				$variation_name_temp = $value['value'];
			}elseif(preg_match('/^options/', $value['name'])){
				$variation_option_temp = $value['value'];
				array_push($arr_variation, [
					'name' => $variation_name_temp,
					'options' => new_explode(',', $variation_option_temp),
				]);
				$variation_name_temp='';
				$variation_option_temp='';
			}elseif(preg_match("/^variation_names_/", $value['name'] )){
				array_push($arr_attributes, [
					'name' => new_str_replace('variation_names_', '', $value['name']),
					'option' => $value['value'],
				]);
			}elseif($value['name'] == 'supplier_taxes_id[]'){
				if(isset($data['supplier_taxes_id'])){
					$data['supplier_taxes_id'] .= ','.$value['value'];
				}else{
					$data['supplier_taxes_id'] = $value['value'];
				}
			}elseif(preg_match("/^item_rate/", $value['name'] )){
				$item_rates[] = $value['value'];
			}elseif(preg_match("/^extend_value/", $value['name'] )){
				$extend_values[] = $value['value'];
			}elseif(preg_match("/^promotion_extended_percent/", $value['name'] )){
				$promotion_extended_percents[] = $value['value'];
			}elseif(preg_match("/^id/", $value['name'] )){
				$ids[] = $value['value'];
			}elseif(preg_match("/^item_id/", $value['name'] )){
				$item_ids[] = $value['value'];
			}elseif(preg_match("/^unit_value/", $value['name'] )){
				$unit_values[] = $value['value'];
			}elseif(preg_match("/^unit_type/", $value['name'] )){
				$unit_types[] = $value['value'];
			}elseif(preg_match("/^cycle_id/", $value['name'] )){
				$cycle_ids[] = $value['value'];
			}elseif( $value['name'] != 'csrf_token_name' && $value['name'] != 'id'){
				$data[$value['name']] = $value['value'];
			}
		}
		$data['service_policy'] = $formdata['service_policy'];
		$data['can_be_inventory'] = null;
		if(isset($data['allow_extension_service']) && $data['allow_extension_service'] == 'on'){
			$data['allow_extension_service'] = 'allow';
		}else{
			$data['allow_extension_service'] = 'reject';
		}
		$data['subscription_price'] = $formdata['subscription_price'];
		$data['subscription_period'] = $formdata['subscription_period'];
		$data['subscription_count'] = $formdata['subscription_count'];
		$this->db->where('id', $id);
		$this->db->update(db_prefix() . 'items', $data);
		if ($this->db->affected_rows() > 0) {
			$affected_rows++;
		}			
		if($affected_rows > 0){
			return true;
		}
		return false;
	}
	/**
	 * get value from data
	 * @param  array $data 
	 * @param  string $name 
	 * @return string       
	 */
	public function get_value_from_data($data, $name){
		foreach ($data['formdata'] as $key => $value) {
			if(preg_match('/^'.$name.'/', $value['name'])){
				return $value['value'];
			}
		}
		return '';
	}

	/*end file*/
}