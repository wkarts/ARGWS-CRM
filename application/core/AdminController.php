<?php

defined('BASEPATH') or exit('No direct script access allowed');

class AdminController extends App_Controller
{
    public function __construct()
    {
        parent::__construct();

        // Only the server bootstrap can attest this exact authenticated controller.
        // Isolated company routes must not hydrate legacy menus, models or hooks.
        if (class_exists('CompanyRequestScope', false) && CompanyRequestScope::sealedFor($this)) {
            if (CompanyRequestScope::nativeFor($this)) {
                // Shared native views use these pure presentation helpers. The
                // general translation helper checks the sealed company scope
                // and skips global module hooks in this path.
                $this->load->helper(['admin', 'general', 'app_branding']);
                require_once APPPATH . 'services/NativeCompanyAdminPresentation.php';
                NativeCompanyAdminPresentation::prepare($this);
            }
            return;
        }
        $nativeUpgradeOnly = class_exists('CompanyRequestScope', false)
            && CompanyRequestScope::nativeUpgradeOnlyFor($this);

        if (!is_staff_logged_in()) {
            if (strpos(current_full_url(), get_admin_uri() . '/authentication') === false) {
                redirect_after_login_to_current_url();
            }

            redirect(admin_url('authentication'));
            return;
        }

        if ($this->uri->segment(3) != 'notifications_check') {
            // In case staff have setup logged in as client - This is important don't change it
            foreach (['client_user_id', 'contact_user_id', 'client_logged_in', 'logged_in_as_client'] as $sk) {
                if ($this->session->has_userdata($sk)) {
                    $this->session->unset_userdata($sk);
                }
            }
        }

        // Update staff last activity
        $this->db->where('staffid', get_staff_user_id());
        $this->db->update('staff', ['last_activity' => date('Y-m-d H:i:s')]);

        $this->load->model('staff_model');

        $currentUser = $this->staff_model->get(get_staff_user_id());

        // Deleted or inactive but have session
        if (!$currentUser || $currentUser->active == 0) {
            if (!isset($this->authentication_model)) {
                $this->load->model('authentication_model');
            }
            $this->authentication_model->logout();
            redirect(admin_url('authentication'));
            return;
        }

        if ($this->app->is_db_upgrade_required($this->current_db_version) || $this->app->is_core_upgrade_required()) {
            if (!is_admin() || (int) $currentUser->admin !== 1) {
                show_error('O administrador precisa atualizar o banco de dados.', 503);
                return;
            }
            require_once APPPATH . 'services/AdminDatabaseUpgrade.php';
            if ($this->input->post('upgrade_database')) {
                if (!AdminDatabaseUpgrade::authorize($this)) {
                    show_error('Solicitação inválida. Atualize a página e tente novamente.', 403);
                    return;
                }
                if (!$nativeUpgradeOnly) {
                    hooks()->do_action('pre_upgrade_database');
                }
                $result = $this->app->upgrade_database();
                if (!is_array($result) || !empty($result['success'])) {
                    return;
                }
                $databaseUpgradeError = $result['message'];
                $this->output->set_status_header(503);
                $this->current_db_version = $this->app->get_current_db_version();
            }
            $databaseUpgradeToken = AdminDatabaseUpgrade::token($this->session);
            $this->renderDatabaseUpgrade($databaseUpgradeToken, $databaseUpgradeError ?? null);
            return;
        }

        if ($nativeUpgradeOnly) {
            show_error('O núcleo de empresas continua indisponível. A área administrativa permanece bloqueada até a validação da migration.', 503);
            return;
        }

        hooks()->do_action('pre_admin_init');

        // Do not check on ajax requests
        if (!$this->input->is_ajax_request()) {
            if (ENVIRONMENT == 'production' && is_admin()) {
                if ($this->config->item('encryption_key') === '') {
                    die('<h1>Encryption key not sent in application/config/app-config.php</h1>Configure uma chave de criptografia de 32 caracteres em application/config/app-config.php para prosseguir.');
                } elseif (strlen($this->config->item('encryption_key')) != 32) {
                    die('<h1>Encryption key length should be 32 charachters</h1>Configure uma chave de criptografia de 32 caracteres em application/config/app-config.php para prosseguir.');
                }
            }

            _maybe_system_setup_warnings();

            $this->init_quick_actions_links();
        }

        $GLOBALS['current_user'] = $currentUser;

        init_admin_assets();

        hooks()->do_action('admin_init');

        $vars = [
            'current_user'    => $currentUser,
            'current_version' => $this->current_db_version,
            'task_statuses'   => $this->tasks_model->get_statuses(),
        ];

        if (!$this->input->is_ajax_request()) {
            $vars['sidebar_menu'] = $this->app_menu->get_sidebar_menu_items();
            $vars['setup_menu']   = $this->app_menu->get_setup_menu_items();
        }

        /**
        * Autoloaded view variables
        * @var array
        */
        $vars = hooks()->apply_filters('admin_area_auto_loaded_vars', $vars);
        $this->load->vars($vars);
    }

    /** The standard native update screen, before optional admin hooks/assets. */
    protected function renderDatabaseUpgrade(string $databaseUpgradeToken, ?string $databaseUpgradeError): void
    {
        include VIEWPATH . 'admin/includes/db_update_required.php';
        exit;
    }

    private function init_quick_actions_links()
    {
        require_once APPPATH.'services/NativeAdminQuickActionsCatalog.php';
        $restrictTickets=get_option('access_tickets_to_none_staff_members') == 0 && !is_staff_member();
        foreach(NativeAdminQuickActionsCatalog::entries($restrictTickets,_d(date('Y-m-d'))) as $entry){
            $entry['name']=_l($entry['label']);unset($entry['label']);
            $this->app->add_quick_actions_link($entry);
        }
    }
}
