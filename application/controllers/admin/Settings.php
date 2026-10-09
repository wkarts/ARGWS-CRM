<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Settings extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('payment_modes_model');
        $this->load->model('settings_model');
    }

    // View all settings
    public function index()
    {
        if (staff_cant('view', 'settings')) {
            access_denied('settings');
        }

        $group = $this->input->get('group');

        // Support credentials and release controls are restricted to administrators.
        if ($group === 'argws_platform' && !is_admin()) {
            access_denied('settings');
        }

        // Pre 3.1.6
        if ($group === 'sales') {
            $group = 'sales_general';
        }

        if ($this->input->post()) {
            if (staff_cant('edit', 'settings')) {
                access_denied('settings');
            }

            $post_data = $this->input->post();

            if (!isset($post_data['settings']) || !is_array($post_data['settings'])) {
                $post_data['settings'] = [];
            }

            if (in_array($group, ['general', 'localization', 'argws_platform'], true)) {
                $post_data['settings']['active_language'] = 'portuguese_br';
                $post_data['settings']['enabled_languages'] = ['portuguese_br'];
                $post_data['settings']['disable_language'] = '1';
            }

            $hasArgwsPlatformSettings = array_intersect(
                array_keys($post_data['settings']),
                ['argws_support_enabled', 'argws_support_base_url', 'argws_support_public_token', 'argws_support_position', 'argws_support_type', 'argws_support_launcher_title', 'argws_terminology_policy']
            ) !== [];
            if ($hasArgwsPlatformSettings && !is_admin()) {
                access_denied('settings');
            }

            if ($hasArgwsPlatformSettings && !$this->normalize_argws_platform_settings($post_data['settings'])) {
                redirect(admin_url('settings?group=argws_platform'));
                return;
            }

            hooks()->do_action('before_update_system_options', $post_data);

            $logo_uploaded     = (handle_company_logo_upload() ? true : false);
            $favicon_uploaded  = (handle_favicon_upload() ? true : false);
            $signatureUploaded = (handle_company_signature_upload() ? true : false);

            $tmpData = $this->input->post(null, false);

            if (isset($post_data['settings']['email_header'])) {
                $post_data['settings']['email_header'] = $tmpData['settings']['email_header'];
            }

            if (isset($post_data['settings']['email_footer'])) {
                $post_data['settings']['email_footer'] = $tmpData['settings']['email_footer'];
            }

            if (isset($post_data['settings']['email_signature'])) {
                $post_data['settings']['email_signature'] = $tmpData['settings']['email_signature'];
            }

            if (isset($post_data['settings']['smtp_password'])) {
                $post_data['settings']['smtp_password'] = $tmpData['settings']['smtp_password'];
            }

            $success = $this->settings_model->update($post_data);

            if ($success > 0) {
                set_alert('success', _l('settings_updated'));
            }

            if ($logo_uploaded || $favicon_uploaded) {
                set_debug_alert(_l('logo_favicon_changed_notice'));
            }

            // Do hard refresh on general for the logo
            if ($group == 'general') {
                redirect(admin_url('settings?group=' . $group), 'refresh');
            } elseif ($signatureUploaded) {
                redirect(admin_url('settings?group=pdf&tab=signature'));
            } else {
                $redUrl = admin_url('settings?group=' . $group);

                if ($this->input->get('active_tab')) {
                    $redUrl .= '&tab=' . $this->input->get('active_tab');
                }

                redirect($redUrl);
            }
        }

        $this->load->model('taxes_model');
        $this->load->model('tickets_model');
        $this->load->model('leads_model');
        $this->load->model('currencies_model');
        $this->load->model('staff_model');
        $data['taxes']                                   = $this->taxes_model->get();
        $data['ticket_priorities']                       = $this->tickets_model->get_priority();
        $data['ticket_priorities']['callback_translate'] = 'ticket_priority_translate';
        $data['roles']                                   = $this->roles_model->get();
        $data['leads_sources']                           = $this->leads_model->get_source();
        $data['leads_statuses']                          = $this->leads_model->get_status();
        $data['title']                                   = _l('options');
        $data['staff']                                   = $this->staff_model->get('', ['active' => 1]);

        $data['admin_tabs'] = ['update', 'info', 'argws_platform'];

        if (! $group || (in_array($group, $data['admin_tabs']) && ! is_admin())) {
            $group = 'general';
        }

        // $data['tabs'] = $this->app_tabs->get_settings_tabs();
        $data['sections'] = $this->app->get_settings_sections();
        if (! in_array($group, $data['admin_tabs'])) {
            $data['group'] = collect($data['sections'])->pluck('children')->flatten(1)->first(function ($sectionGroup) use ($group) {
                return $sectionGroup['id'] == $group;
            });
        } else {
            // Core tabs are not registered
            $data['group']['id']       = $group;
            $data['group']['view']     = 'admin/settings/includes/' . $group;
            $data['group']['name']     = $group === 'info'
                ? 'Informações do servidor'
                : ($group === 'argws_platform' ? 'Plataforma ARGWS' : _l('settings_update'));
            $data['group']['children'] = [];
            if ($group === 'info') {
                $data['group']['without_submit_button'] = true;
            }
        }

        if (! $data['group']) {
            show_404();
        }

        if ($data['group']['id'] == 'update') {
            $data['update_info']    = json_decode($this->app->get_update_info());
            $data['latest_version'] = $data['update_info']->latest_version;
            $data['update_errors']  = [];
            $data['current_version'] = $this->current_db_version;
        }

        $data['contacts_permissions'] = get_contact_permissions();
        $data['payment_gateways']     = $this->payment_modes_model->get_payment_gateways(true);

        $this->load->view('admin/settings/all', $data);
    }

    private function normalize_argws_platform_settings(array &$settings)
    {
        $url = trim((string) ($settings['argws_support_base_url'] ?? ''));
        if ($url !== '') {
            $parts = parse_url($url);
            if (!filter_var($url, FILTER_VALIDATE_URL) || !$parts || strtolower($parts['scheme'] ?? '') !== 'https'
                || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
                || isset($parts['query']) || isset($parts['fragment'])) {
                set_alert('danger', 'Informe um endereço HTTPS válido, sem credenciais, parâmetros ou fragmentos.');
                return false;
            }
            $settings['argws_support_base_url'] = rtrim($url, '/');
        }

        $token = trim((string) ($settings['argws_support_public_token'] ?? ''));
        if (strlen($token) > 255 || preg_match('/[\r\n<>"\']/', $token)) {
            set_alert('danger', 'O token público do suporte contém caracteres inválidos ou excede 255 caracteres.');
            return false;
        }
        $settings['argws_support_public_token'] = $token;
        $settings['argws_support_enabled'] = !empty($settings['argws_support_enabled']) ? '1' : '0';
        if ($settings['argws_support_enabled'] === '1' && ($url === '' || $token === '')) {
            set_alert('danger', 'Informe o endereço HTTPS e o token público antes de habilitar o widget.');
            return false;
        }

        $position = $settings['argws_support_position'] ?? 'left';
        $settings['argws_support_position'] = in_array($position, ['left', 'right'], true) ? $position : 'left';
        $type = $settings['argws_support_type'] ?? 'expanded_bubble';
        $settings['argws_support_type'] = in_array($type, ['expanded_bubble', 'standard'], true) ? $type : 'expanded_bubble';
        $title = trim((string) ($settings['argws_support_launcher_title'] ?? 'Suporte'));
        $settings['argws_support_launcher_title'] = mb_substr($title !== '' ? $title : 'Suporte', 0, 60);

        $policy = (string) ($settings['argws_terminology_policy'] ?? '{}');
        $decoded = json_decode($policy, true);
        if (strlen($policy) > 65535 || json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            set_alert('danger', 'A política de terminologia deve ser um JSON válido de até 65.535 bytes.');
            return false;
        }
        foreach (['translate', 'keep_original', 'approved', 'contexts'] as $key) {
            if (!array_key_exists($key, $decoded) || !is_array($decoded[$key])) {
                set_alert('danger', 'A política de terminologia possui uma estrutura inválida.');
                return false;
            }
        }
        foreach (['translate', 'approved', 'contexts'] as $key) {
            foreach ($decoded[$key] as $term => $description) {
                if (!is_string($term) || trim($term) === '' || !is_string($description)) {
                    set_alert('danger', 'Termos, traduções e contextos devem ser textos válidos.');
                    return false;
                }
            }
        }
        foreach ($decoded['keep_original'] as $term) {
            if (!is_string($term) || trim($term) === '') {
                set_alert('danger', 'Os termos mantidos no idioma original devem ser textos válidos.');
                return false;
            }
        }
        $encodedPolicy = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encodedPolicy)) {
            set_alert('danger', 'Não foi possível validar a política de terminologia.');
            return false;
        }
        $settings['argws_terminology_policy'] = $encodedPolicy;
        return true;
    }

    public function delete_tag($id)
    {
        if (! $id) {
            redirect(admin_url('settings?group=tags'));
        }

        if (staff_cant('delete', 'settings')) {
            access_denied('settings');
        }

        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'tags');
        $this->db->where('tag_id', $id);
        $this->db->delete(db_prefix() . 'taggables');

        redirect(admin_url('settings?group=tags'));
    }

    public function remove_signature_image()
    {
        if (staff_cant('delete', 'settings')) {
            access_denied('settings');
        }

        $sImage = get_option('signature_image');
        if (file_exists(get_upload_path_by_type('company') . '/' . $sImage)) {
            unlink(get_upload_path_by_type('company') . '/' . $sImage);
        }

        update_option('signature_image', '');

        redirect(admin_url('settings?group=pdf&tab=signature'));
    }

    // Remove company logo from settings / ajax
    public function remove_company_logo($type = '')
    {
        hooks()->do_action('before_remove_company_logo');

        if (staff_cant('delete', 'settings')) {
            access_denied('settings');
        }

        $logoName = get_option('company_logo');
        if ($type == 'dark') {
            $logoName = get_option('company_logo_dark');
        }

        $path = get_upload_path_by_type('company') . '/' . $logoName;
        if (file_exists($path)) {
            unlink($path);
        }

        update_option('company_logo' . ($type == 'dark' ? '_dark' : ''), '');
        redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
    }

    public function remove_fv()
    {
        hooks()->do_action('before_remove_favicon');
        if (staff_cant('delete', 'settings')) {
            access_denied('settings');
        }
        if (file_exists(get_upload_path_by_type('company') . '/' . get_option('favicon'))) {
            unlink(get_upload_path_by_type('company') . '/' . get_option('favicon'));
        }
        update_option('favicon', '');
        redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
    }

    public function delete_option($name)
    {
        if (staff_cant('delete', 'settings')) {
            access_denied('settings');
        }

        echo json_encode([
            'success' => delete_option($name),
        ]);
    }

    public function clear_sessions()
    {
        if (staff_cant('delete', 'settings')) {
            access_denied('settings');
        }
        $this->db->empty_table(db_prefix() . 'sessions');

        set_alert('success', 'Sessões encerradas.');
        redirect(admin_url('settings?group=info'));
    }
}
