<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Staff_theme extends AdminController
{
    public function __construct()
    {
        parent::__construct();
    }
	public function index()
	{
		if (!(int) get_option(SI_CUSTOM_THEME_MODULE_NAME . '_enable_staff_theme')) {
			access_denied(_l('si_custom_theme'));
		}
		if($this->input->post())
		{
			$theme_id = $this->input->post('staff_theme');
			$success = $this->si_custom_theme_model->save_user_theme($theme_id,'staff');
			if ($success) {
					set_alert('success', _l('updated_successfully', _l('si_custom_theme')));
					redirect(admin_url('si_custom_theme/staff_theme'));
			}
		}
		$data['title'] = _l('si_custom_theme_title');
		$data['selected_theme'] = $this->si_custom_theme_model->get_staff_theme_id();
		//$data['selected_client_theme'] = get_option(SI_CUSTOM_THEME_MODULE_NAME.'_default_clients_theme');
		$data['theme_list'] = $this->si_custom_theme_model->get_themes();
		$this->load->view('si_custom_theme_staff_view', $data);
	}
	public function get_theme()
	{
		$theme_id = $this->input->post('theme');
		if(is_numeric($theme_id)){
			echo si_custom_theme_render(['general', 'texts', 'tabs', 'buttons', 'admin', 'modals', 'tags'],$theme_id);
		}	
	}
}	