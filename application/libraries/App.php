<?php

defined('BASEPATH') or exit('No direct script access allowed');

class App
{
    /**
     * Options autoload=1
     *
     * @var array
     */
    private $options = [];

    /**
     * Quick actions create aside
     *
     * @var array
     */
    private $quick_actions = [];

    /**
     * CI Instance
     *
     * @deprecated 1.9.8 Use $this->ci instead
     *
     * @var object
     */
    private $_instance;

    /**
     * CI Instance
     *
     * @var object
     */
    private $ci;

    /**
     * Show or hide setup menu
     *
     * @var bool
     */
    private $show_setup_menu = true;

    /**
     * Available reminders
     *
     * @var array
     */
    private $available_reminders = [
        'customer',
        'lead',
        'estimate',
        'invoice',
        'proposal',
        'expense',
        'credit_note',
        'ticket',
        'task',
    ];

    /**
     * Tables where currency id is used
     *
     * @var array
     */
    private $tables_with_currency = [];

    /**
     * Media folder
     *
     * @var string
     */
    private $media_folder;

    /**
     * The registered settings sections.
     */
    protected array $settingsSections = [];

    /**
     * Available languages
     *
     * @var array
     */
    private $available_languages = [];

    public function __construct()
    {
        $this->ci = &get_instance();
        require_once APPPATH . 'services/CompanyRequestScope.php';
        CompanyRequestScope::beforeHydration($this->ci);
        // @deprecated
        $this->_instance = $this->ci;

        // Keep Stripe as a functional payment integration while disabling the
        // SDK's request timing/telemetry headers for all CRM requests.
        if (class_exists('Stripe\\Stripe')) {
            \Stripe\Stripe::setEnableTelemetry(false);
        }

        $this->init();

        hooks()->do_action('app_base_after_construct_action');
    }

    /** Drop request-local option, permission-bearing UI and quick-action caches on company selection. */
    public function clearCompanyCaches(): void
    {
        $this->options = [];
        $this->quick_actions = [];
        $this->settingsSections = [];
    }

    public function get_settings_sections()
    {
        $sections = app_sort_by_position($this->settingsSections);

        foreach ($sections as $key => $section) {
            $sections[$key]['children'] = app_sort_by_position($section['children']);
        }

        return $sections;
    }

    public function add_settings_section($id, $data)
    {
        foreach ($data['children'] ?? [] as $key => $child) {
            if (! isset($child['id'])) {
                $data['children'][$key]['id'] = basename($child['view']);
            }
        }

        if (array_key_exists($id, $this->settingsSections)) {
            $this->settingsSections[$id] = array_merge($this->settingsSections[$id], $data);
        } else {
            $this->settingsSections[$id] = $data;
        }

        return $this;
    }

    public function add_settings_section_child($parent_id, $id, $data)
    {
        if (! isset($this->settingsSections[$parent_id])) {
            $this->settingsSections[$parent_id] = [];
        }

        if (! isset($this->settingsSections[$parent_id]['children'])) {
            $this->settingsSections[$parent_id]['children'] = [];
        }

        if (! isset($data['id'])) {
            $data['id'] = $id;
        }

        $this->settingsSections[$parent_id]['children'][] = $data;

        return $this;
    }

    /**
     * Check if database upgrade is required
     *
     * @param string $v
     *
     * @return bool
     */
    public function is_db_upgrade_required($v = '')
    {
        if (! is_numeric($v)) {
            $v = $this->get_current_db_version();
        }

        $this->ci->load->config('migration');

        return (bool) ((int) $this->ci->config->item('migration_version') !== (int) $v);
    }

    /**
     * Return current database version
     *
     * @return string
     */
    public function get_current_db_version()
    {
        $this->ci->db->limit(1);

        return $this->ci->db->get(db_prefix() . 'migrations')->row()->version;
    }

    /**
     * Upgrade database
     *
     * @return mixed
     */
    public function upgrade_database()
    {
        require_once APPPATH . 'services/AdminDatabaseUpgrade.php';
        if (!AdminDatabaseUpgrade::authorize($this->ci)) {
            show_error('Atualização restrita ao administrador ativo, por POST autenticado.', 403);
            return;
        }
        // Core migrations and company schema complete in this same authorized
        // action. A failed attempt returns to the native update form for retry.
        $update = $this->upgrade_database_silent();
        if (empty($update['success'])) {
            return $update;
        } else {
            $this->ci->session->unset_userdata('admin_database_upgrade_csrf');
            AdminDatabaseUpgrade::recordSuccess($this->ci->session, (int) get_staff_user_id(), (string) $this->get_current_db_version());
            update_option('last_updated_date', time());
            if (is_staff_logged_in()) {
                // The request may have entered before migrations made company scope
                // available. Re-resolve from persisted ACLs, never reuse that stale scope
                // or a submitted return URL. Only the primary has a native dashboard.
                if (!class_exists('CompanyContext', false)) {
                    require_once APPPATH . 'services/CompanyContext.php';
                }
                $destination = 'company_context';
                try {
                    $context = new CompanyContext($this->ci->db, db_prefix(), $this->ci->session, get_staff_user_id());
                    if ($context->resolve(false, true) !== null && $context->isPrimary()) {
                        $destination = '';
                    }
                } catch (DomainException $error) {
                    // Revoked/malformed selections need explicit recovery, not an
                    // automatic switch to another company after a database upgrade.
                }
                redirect(admin_url($destination), 'location', 303);
            } else {
                redirect(admin_url('authentication'));
            }
        }
    }

    /**
     * Apply pending database migrations during the first container setup.
     * The same migration path is used by the administrative update screen.
     */
    public function apply_pending_migrations_for_provisioning()
    {
        if (!is_cli()) {
            return ['success' => false, 'message' => 'Provisionamento de migrations restrito ao processo CLI.'];
        }
        return $this->apply_installation_migrations();
    }

    public function apply_pending_migrations_for_installation()
    {
        if (!class_exists('InstallationMigrationBootstrap', false)
            || !InstallationMigrationBootstrap::isRunning()) {
            return ['success' => false, 'message' => 'Contexto de instalação inválido.'];
        }
        return $this->apply_installation_migrations();
    }

    private function apply_installation_migrations()
    {
        $this->ci->load->config('migration');
        $beforeUpdateVersion = (int) $this->get_current_db_version();
        $targetVersion = (int) $this->ci->config->item('migration_version');

        $result = $this->upgrade_database_silent();
        if (empty($result['success'])) {
            return $result;
        }

        $currentVersion = (int) $this->get_current_db_version();
        if ($currentVersion !== $targetVersion) {
            return [
                'success' => false,
                'message' => 'A versão gravada no banco não corresponde à migration mais recente.',
            ];
        }

        update_option('last_updated_date', time());

        return [
            'success' => true,
            'from_version' => $beforeUpdateVersion,
            'to_version' => $currentVersion,
        ];
    }

    /** Return update-channel information without sending installation data. */
    public function get_update_info()
    {
        $this->ci->config->load('migration');
        return json_encode([
            'latest_version' => (int) $this->ci->config->item('migration_version'),
            'release_url'    => ARGWS_RELEASES_URL,
            'additional_data' => 'As atualizações são distribuídas pelo canal oficial ARGWS. Baixe a versão e aplique o pacote de atualização após fazer backup da instalação.',
        ]);
    }

    /**
     * Return all available languages in the application/language folder
     *
     * @return array
     */
    public function get_available_languages()
    {
        return ['portuguese_br'];
    }

    public function get_all_languages()
    {
        return ['portuguese_br'];
    }

    public function get_table_path($table, $my_prefixed = true)
    {
        $path = VIEWPATH . 'admin/tables/' . $table . EXT;

        if (! file_exists($path)) {
            $path = $table;
            if (! endsWith($path, EXT)) {
                $path .= EXT;
            }
        } elseif ($my_prefixed) {
            $myPrefixedPath = VIEWPATH . 'admin/tables/my_' . $table . EXT;

            if (file_exists($myPrefixedPath)) {
                $path = $myPrefixedPath;
            }
        }

        return $path;
    }

    /**
     * Function that will parse table data from the tables folder for amin area
     *
     * @param string $table  table filename
     * @param array  $params additional params
     *
     * @return void
     */
    public function get_table_data($table, $params = [])
    {
        $params = hooks()->apply_filters('table_params', $params, $table);

        $customFieldsColumns = [];

        foreach ($params as $key => $val) {
            ${$key} = $val;
        }

        include_once $this->get_table_path($table);

        echo json_encode($output);

        exit;
    }

    /**
     * All available reminders keys for the features
     *
     * @return array
     */
    public function get_available_reminders_keys()
    {
        return $this->available_reminders;
    }

    /** Runtime language policy; persisted customer options remain untouched. */
    private function ui_language_options()
    {
        return [
            'active_language' => 'portuguese_br',
            'enabled_languages' => '["portuguese_br"]',
            'disable_language' => '1',
        ];
    }

    /** Return options with the runtime language policy applied. */
    public function get_options()
    {
        if (class_exists('CompanySettingsRuntime', false) && CompanySettingsRuntime::active()) {
            return array_replace(CompanySettingsRuntime::all(), $this->ui_language_options());
        }
        $options = $this->options;
        if (class_exists('IssuerCore', false) && IssuerCore::ready($this->ci->db, db_prefix())) {
            require_once APPPATH . 'services/CompanySettingsIdentity.php';
            foreach (array_merge(array_keys(CompanySettingsIdentity::ALIASES), CompanySettingsIdentity::PROFILE_OPTIONS) as $key) {
                $options[$key] = CompanySettingsIdentity::legacyPrincipalOption($this->ci->db, db_prefix(), $key);
            }
        }
        return array_replace($options, $this->ui_language_options());
    }

    /**
     * Function that gets option based on passed name
     *
     * @param string $name
     *
     * @return string
     */
    public function get_option($name)
    {
        if (class_exists('CompanySettingsRuntime', false) && CompanySettingsRuntime::active()) {
            return CompanySettingsRuntime::get($name);
        }
        $name = trim($name);
        if (class_exists('IssuerCore', false) && IssuerCore::ready($this->ci->db, db_prefix())) {
            require_once APPPATH . 'services/CompanySettingsIdentity.php';
            if (CompanySettingsIdentity::handles($name)) {
                return CompanySettingsIdentity::legacyPrincipalOption($this->ci->db, db_prefix(), $name);
            }
        }
        $val  = '';

        $languageOptions = $this->ui_language_options();
        if (array_key_exists($name, $languageOptions)) {
            return $languageOptions[$name];
        }

        if (! isset($this->options[$name])) {
            // is not auto loaded
            $this->ci->db->select('value');
            $this->ci->db->where('name', $name);
            $row = $this->ci->db->get(db_prefix() . 'options')->row();
            if ($row) {
                $val = $row->value;
            }
        } else {
            $val = $this->options[$name];
        }

        return hooks()->apply_filters('get_option', $val, $name);
    }

    /**
     * Add new quick action data
     *
     * @param array $item
     */
    public function add_quick_actions_link($item = [])
    {
        if (! isset($item['position'])) {
            $item['position'] = null;
        }

        $this->quick_actions[] = $item;
    }

    /**
     * Quick actions data set from core/AdminController.php
     *
     * @return array
     */
    public function get_quick_actions_links()
    {
        return hooks()->apply_filters('quick_actions_links', app_sort_by_position($this->quick_actions));
    }

    /**
     * Aside.php will set the menu visibility here based on few conditions
     *
     * @param int $total_setup_menu_items total setup menu items shown to the user
     */
    public function set_setup_menu_visibility($total_setup_menu_items)
    {
        $this->show_setup_menu = $total_setup_menu_items == 0 ? false : true;
    }

    /**
     * Check if should the script show the setup menu or not
     *
     * @return bool
     */
    public function show_setup_menu()
    {
        return hooks()->apply_filters('show_setup_menu', $this->show_setup_menu);
    }

    /**
     * Return tables that currency id is used
     *
     * @return array
     */
    public function get_tables_with_currency()
    {
        return hooks()->apply_filters('tables_with_currency', $this->tables_with_currency);
    }

    /**
     * Return the media folder name
     *
     * @return string
     */
    public function get_media_folder()
    {
        return hooks()->apply_filters('get_media_folder', $this->media_folder);
    }

    /**
     * Upgrade database without throwing any errors
     *
     * @return mixed
     */
    private function upgrade_database_silent()
    {
        $this->ci->load->config('migration');
        $beforeUpdateVersion = $this->get_current_db_version();
        $updateToVersion = $this->ci->config->item('migration_version');
        if ((int) $beforeUpdateVersion > (int) $updateToVersion) {
            return ['success' => false, 'message' => 'O banco de dados pertence a uma versão mais recente do CRM. Use os arquivos compatíveis; nenhuma redução de versão foi executada.'];
        }
        $databaseFlags = [];
        foreach (['db_debug', 'save_queries'] as $flag) {
            if (property_exists($this->ci->db, $flag)) {
                $databaseFlags[$flag] = $this->ci->db->$flag;
                $this->ci->db->$flag = false;
            }
        }
        try {
            $this->ci->load->library('migration', [
                'migration_enabled'     => true,
                'migration_type'        => $this->ci->config->item('migration_type'),
                'migration_table'       => $this->ci->config->item('migration_table'),
                'migration_auto_latest' => $this->ci->config->item('migration_auto_latest'),
                'migration_version'     => $updateToVersion,
                'migration_path'        => $this->ci->config->item('migration_path'),
            ]);
            hooks()->do_action('before_update_database', $updateToVersion);
            if (!defined('DOING_DATABASE_UPGRADE')) { define('DOING_DATABASE_UPGRADE', true); }
            if ($this->ci->migration->current() === false) {
                throw new RuntimeException('core_migration_unconfirmed');
            }
            // CI3's runner does not propagate a failed version-marker write.
            if ((int) $this->get_current_db_version() !== (int) $updateToVersion) {
                throw new RuntimeException('core_migration_tracking_unconfirmed');
            }
            // Native forward migration calls this too. Reconciliation is
            // idempotent and covers retry after a partially completed update.
            $this->load_issuer_core();
            IssuerCore::reconcile($this->ci->db, db_prefix());
            if (!IssuerCore::ready($this->ci->db, db_prefix())) {
                throw new RuntimeException('company_core_unconfirmed');
            }
            delete_option('upgraded_from_version');
            add_option('upgraded_from_version', $beforeUpdateVersion);
            hooks()->do_action('database_updated', $updateToVersion);
            return ['success' => true];
        } catch (Throwable $error) {
            $errorCode = (string) $error->getMessage();
            $safeCode = preg_match('/^(?:core|company|issuer)_[a-z_]{1,64}$/D', $errorCode)
                ? $errorCode
                : 'unclassified';
            $sourceFile = basename((string) $error->getFile());
            if (!preg_match('/^[A-Za-z0-9_.-]{1,128}$/D', $sourceFile)) {
                $sourceFile = 'unknown';
            }
            $safeMessage = 'Falha na atualização nativa [' . $safeCode . ']: ' . get_class($error)
                . ' @ ' . $sourceFile . ':' . (int) $error->getLine();
            error_log($safeMessage);
            log_message('error', $safeMessage);
            return ['success' => false, 'message' => 'Não foi possível concluir a atualização do CRM. Os dados foram preservados. Verifique os logs e tente novamente.'];
        } finally {
            foreach ($databaseFlags as $flag => $value) {
                $this->ci->db->$flag = $value;
            }
        }
    }

    /** Read-only readiness; never installs or repairs schema during GET/login. */
    public function is_core_upgrade_required(): bool
    {
        try {
            $this->load_issuer_core();
            return !IssuerCore::ready($this->ci->db, db_prefix());
        } catch (Throwable $error) {
            log_message('error', 'Falha na verificação do núcleo de empresas: ' . get_class($error));
            return true;
        }
    }

    private function load_issuer_core(): void
    {
        if (!class_exists('IssuerCore', false)) {
            require_once APPPATH . 'services/issuers/IssuerCore.php';
        }
    }

    /**
     * Init necessary data
     */
    protected function init()
    {
        // Temporary checking for v1.8.0
        if ($this->ci->db->field_exists('autoload', db_prefix() . 'options')) {
            $options = $this->ci->db->select('name, value')
                ->where('autoload', 1)
                ->get(db_prefix() . 'options')->result_array();
        } else {
            $options = $this->ci->db->select('name, value')
                ->get(db_prefix() . 'options')->result_array();
        }

        // Loop the options and store them in a array to prevent fetching again and again from database
        foreach ($options as $option) {
            $this->options[$option['name']] = $option['value'];
        }

        /**
         * Available languages
         */
        foreach (list_folders(APPPATH . 'language') as $language) {
            if (is_dir(APPPATH . 'language/' . $language) && $language !== '.git') {
                array_push($this->available_languages, $language);
            }
        }

        /**
         * Media folder
         *
         * @var string
         */
        $this->media_folder = hooks()->apply_filters('before_set_media_folder', 'media');

        /**
         * Tables with currency
         *
         * @var array
         */
        $this->tables_with_currency = [
            [
                'table' => db_prefix() . 'invoices',
                'field' => 'currency',
            ],
            [
                'table' => db_prefix() . 'expenses',
                'field' => 'currency',
            ],
            [
                'table' => db_prefix() . 'proposals',
                'field' => 'currency',
            ],
            [
                'table' => db_prefix() . 'estimates',
                'field' => 'currency',
            ],
            [
                'table' => db_prefix() . 'clients',
                'field' => 'default_currency',
            ],
            [
                'table' => db_prefix() . 'creditnotes',
                'field' => 'currency',
            ],
            [
                'table' => db_prefix() . 'subscriptions',
                'field' => 'currency',
            ],
        ];
    }

    /**
     * Predefined contact permission
     *
     * @deprecated 1.9.8 use get_contact_permissions() instead
     *
     * @return array
     */
    public function get_contact_permissions()
    {
        return get_contact_permissions();
    }
}
