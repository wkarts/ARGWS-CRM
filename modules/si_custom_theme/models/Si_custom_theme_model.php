<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Si_custom_theme_model extends App_Model
{
	public function __construct()
	{
		parent::__construct();
	}

	/**
	* get all themes
	*/
	function get_themes($id='')
	{
		$theme_list = [];
		if(is_numeric($id))
			$this->db->where('id',$id);
		$this->db->order_by('theme_type','DESC');
		$result = $this->db->get(db_prefix() . 'si_custom_theme_list');
		if(!empty($result))
		{
			foreach($result->result_array() as $theme)
			{
				$theme_list[$theme['id']] = $theme;
				$theme_list[$theme['id']]['class'] = explode("|",$theme_list[$theme['id']]['class']);
			}
		}
		if(is_numeric($id) && isset($theme_list[$id]))
			return $theme_list[$id];
		else										
			return $theme_list;
	}
	/**
	* Add new theme style
	* @param mixed $data All $_POST data
	* @return mixed
	*/
	public function add($data)
	{
		$this->db->insert(db_prefix() . 'si_custom_theme_list', $data);
		$insert_id = $this->db->insert_id();
		if ($insert_id) {
			log_activity('New Theme Added [Name:' . $data['theme_name'] . ']');
			return $insert_id;
		}
		return false;
	}
	/**
	* Update theme style
	* @param mixed $data All $_POST data
	* @return mixed
	*/
	public function update($data,$theme_id)
	{
		$this->db->where('id',$theme_id);
		$update = $this->db->update(db_prefix() . 'si_custom_theme_list', $data);
		if ($update) {
			$theme = $this->get_themes($theme_id);
			log_activity('Theme Updated [Name:' . $theme['theme_name'] . ']');
			return true;
		}
		return false;
	}
	/**
	* Delete theme style
	* @param  mixed $id theme id
	* @return boolean
	*/
	public function delete($id)
	{
		$this->db->where('id', $id);
		$this->db->delete(db_prefix() . 'si_custom_theme_list');
		if ($this->db->affected_rows() > 0) {
			log_activity('Theme Deleted [ID:' . $id . ']');
			return true;
		}
		return false;
	}
	/**
	* Reset theme style
	* @param  mixed $id theme id
	* @return boolean
	*/
	public function reset_theme()
	{
		$this->db->where('theme_type', 'default');
		$this->db->set('theme_style', 'default_style',false);#copy from default_style
		$this->db->update(db_prefix() . 'si_custom_theme_list');
		if ($this->db->affected_rows() > 0) {
			log_activity('Theme Reset done.');
			return true;
		}
		return false;
	}
	/*
		get staff theme if set, if not set return default theme
	*/
	public function get_staff_theme_id()
	{
		$theme_id = get_option(SI_CUSTOM_THEME_MODULE_NAME.'_default_theme');
		$enabled_staff_theme = (int)get_option(SI_CUSTOM_THEME_MODULE_NAME.'_enable_staff_theme');
		if(is_staff_logged_in() && $enabled_staff_theme)
		{
			if(!$this->session->has_userdata('si_ct_staff_theme')){
				$this->db->where('staff_id',get_staff_user_id());
				$result = $this->db->get(db_prefix() . 'si_custom_theme_staff')->row();
				if($result)
				{
					$theme_id = $result->theme_id;
					$this->session->set_userdata('si_ct_staff_theme',$theme_id);
				}
			}
			else
				$theme_id = $this->session->userdata('si_ct_staff_theme');	
		}
		return $theme_id;
	}
	
	/*
		get client theme if set, if not set return default client theme
	*/
	public function get_client_theme_id()
	{
		$theme_id = get_option(SI_CUSTOM_THEME_MODULE_NAME.'_default_clients_theme');
		$enabled_client_theme = (int)get_option(SI_CUSTOM_THEME_MODULE_NAME.'_enable_client_theme');
		if(is_client_logged_in() && $enabled_client_theme)
		{
			if(!$this->session->has_userdata('si_ct_client_theme')){
				$this->db->where('contact_id',get_contact_user_id());
				$result = $this->db->get(db_prefix() . 'si_custom_theme_client')->row();
				if(!empty($result))
				{
					$theme_id = $result->theme_id;
					$this->session->set_userdata('si_ct_client_theme',$theme_id);
				}
			}
			else
				$theme_id = $this->session->userdata('si_ct_client_theme');
		}
		return $theme_id;
	}
	/*
		save staff/client theme
	*/
	public function save_user_theme($theme_id,$type='staff')
	{
		
		if($type=='staff'){
			$this->session->unset_userdata('si_ct_staff_theme');
			$staff_id = get_staff_user_id();
			$this->db->where('staff_id',$staff_id);
			$result = $this->db->get(db_prefix() . 'si_custom_theme_staff')->row();
			if($result){
				$this->db->where('staff_id',$staff_id);
				$this->db->set('theme_id',$theme_id);
				return $this->db->update(db_prefix() . 'si_custom_theme_staff');
			}
			else{
				return $this->db->insert(db_prefix() . 'si_custom_theme_staff',['theme_id'=>$theme_id,'staff_id'=>$staff_id]);
			}	
		}
		if($type=='client'){
			$this->session->unset_userdata('si_ct_client_theme');
			$contact_id = get_contact_user_id();
			$this->db->where('contact_id',$contact_id);
			$result = $this->db->get(db_prefix() . 'si_custom_theme_client')->row();
			if($result){
				$this->db->where('contact_id',$contact_id);
				$this->db->set('theme_id',$theme_id);
				return $this->db->update(db_prefix() . 'si_custom_theme_client');
			}
			else{
				return $this->db->insert(db_prefix() . 'si_custom_theme_client',['theme_id'=>$theme_id,'contact_id'=>$contact_id]);
			}	
		}
		return false;
	}
}
