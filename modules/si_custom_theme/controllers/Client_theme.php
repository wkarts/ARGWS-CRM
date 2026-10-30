<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Client_theme extends ClientsController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('si_custom_theme_model');
		if (!is_client_logged_in())
			redirect(site_url('authentication/login'));
		if (is_client_logged_in() && !is_contact_email_verified()) {
            redirect(site_url('verification'));
        }
    }
	public function index()
	{
		$data['bodyclass'] = 'si-ct-client-theme';
		if (!(int) get_option(SI_CUSTOM_THEME_MODULE_NAME . '_enable_client_theme')) {
			redirect(site_url());
		}
		if($this->input->post())
		{
			$theme_id = $this->input->post('client_theme');
			$success = $this->si_custom_theme_model->save_user_theme($theme_id,'client');
			if ($success) {
					set_alert('success', _l('updated_successfully', _l('si_custom_theme')));
					redirect('si_custom_theme/client_theme');
			}
		}
		$data['title'] = _l('si_custom_theme_title');
		$data['selected_theme'] = $this->si_custom_theme_model->get_client_theme_id();
		$data['theme_list'] = $this->si_custom_theme_model->get_themes();
		$this->data($data);
        $this->title(_l('si_custom_theme'));
		$this->view('client/si_custom_theme_client_view');
		no_index_customers_area();
		$this->layout();
	}
	public function get_theme()
	{
		$theme_id = $this->input->post('theme');
		if(is_numeric($theme_id)){
			echo si_custom_theme_render(['general', 'texts', 'tabs', 'buttons', 'customers', 'modals'],$theme_id);
		}	
	}
}	