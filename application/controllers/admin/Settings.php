<?php

defined('BASEPATH') or exit('No direct script access allowed');
require_once APPPATH . 'services/CompanySettingsRuntime.php';
require_once APPPATH . 'services/CompanySettingsFormats.php';
require_once APPPATH . 'services/CompanySettingsCatalog.php';
require_once APPPATH . 'services/CompanyProjectFileSettings.php';
require_once APPPATH . 'services/CompanyPdfTypography.php';
require_once APPPATH . 'services/CompanyTicketPreferences.php';
require_once APPPATH.'services/CompanyTaskTimerStopPreferences.php';require_once APPPATH.'services/CompanyTaskTimerPreferences.php';require_once APPPATH . 'services/CompanyTaskPreferences.php';require_once APPPATH.'services/CompanyTaskPriority.php';

class Settings extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if ($this->isolated_company()) {
            if (!class_exists('CompanyRequestScope', false) || !CompanyRequestScope::sealedFor($this)) { show_error('Acesso negado.', 403); return; }
            $this->load->library('encryption');
            $this->load->model('settings_model');
            return;
        }
        $this->load->model('payment_modes_model');
        $this->load->model('settings_model');
    }

    // View all settings
    public function index()
    {
        if ($this->isolated_company() || (CompanySettingsRuntime::active() && in_array($this->input->get('group'),['task_timer_stop','task_timers','task_operations','project_files','pdf_typography','ticket_preferences','task_defaults'],true))) { return $this->isolated_index(); }
        if (!$this->settings_allowed('view')) {
            access_denied('settings');
        }

        $group = $this->input->get('group');
        if (CompanySettingsRuntime::active() && in_array($group, ['update', 'info'], true)
            && !CompanySettingsRuntime::canInfrastructure()) { access_denied('settings'); }
        if ($this->isolated_company() && $group === 'tags') { access_denied('settings'); }
        $nativeUpgradeSuccess = false;
        if ($group === 'update' && $this->input->method(true) === 'GET'
            && !$this->input->is_ajax_request() && is_staff_logged_in() && is_admin()) {
            require_once APPPATH . 'services/AdminDatabaseUpgrade.php';
            $nativeUpgradeSuccess = AdminDatabaseUpgrade::consumeSuccess($this->session, (int) get_staff_user_id(), (string) $this->current_db_version);
            if ($nativeUpgradeSuccess) {
                set_alert('success', 'O CRM foi atualizado com sucesso.');
            }
        }

        // Legacy installation support controls retain their administrator check; explicit company context uses resource ACL.
        if ($group === 'argws_platform' && !is_admin() && !CompanySettingsRuntime::active()) {
            access_denied('settings');
        }

        // Pre 3.1.6
        if ($group === 'sales') {
            $group = 'sales_general';
        }

        if ($this->input->post()) {
            if (!$this->settings_allowed('edit')) {
                access_denied('settings');
            }

            $post_data = $this->input->post();

            if (!isset($post_data['settings']) || !is_array($post_data['settings'])) {
                $post_data['settings'] = [];
            }

            // Known sales document preferences retain literal text and fail before legacy hooks/uploads.
            $rawSales = $this->input->post(null, false);
            $salesPreferences = array_intersect_key(is_array($rawSales['settings'] ?? null) ? $rawSales['settings'] : [], array_flip(CompanySettingsCatalog::fields('sales_documents')));
            if ($salesPreferences !== []) {
                try { $post_data['settings'] = array_replace($post_data['settings'], CompanySettingsCatalog::prepare('sales_documents', $salesPreferences)); }
                catch (DomainException $error) { show_error('Confira os prefixos e as condições dos documentos. Nenhuma definição foi alterada.', 422); return; }
            }

            // The exact native sales PDF choices fail before hooks/uploads or adjacent option writes.
            $pdfPreferences = array_intersect_key(is_array($rawSales['settings'] ?? null) ? $rawSales['settings'] : [], array_flip(CompanySettingsCatalog::fields('sales_pdf')));
            if ($pdfPreferences !== []) {
                try { $post_data['settings'] = array_replace($post_data['settings'], CompanySettingsCatalog::prepare('sales_pdf', $pdfPreferences)); }
                catch (DomainException $error) { show_error('Confira o formato dos PDFs. Nenhuma definição foi alterada.', 422); return; }
            }

            // Known immutable document PDF preferences share an exact narrow native contract.
            $documentPdfPreferences=array_intersect_key(is_array($rawSales['settings']??null)?$rawSales['settings']:[],array_flip(CompanySettingsCatalog::fields('document_pdf')));
            if($documentPdfPreferences!==[]){
                try{$post_data['settings']=array_replace($post_data['settings'],CompanySettingsCatalog::prepare('document_pdf',$documentPdfPreferences));}
                catch(DomainException $error){show_error('Confira o formato dos PDFs. Nenhuma definição foi alterada.',422);return;}
            }

            // Normalize language keys even when posted through another settings tab.
            if (in_array($group, ['general', 'localization', 'argws_platform'], true)
                || array_intersect(['active_language', 'enabled_languages', 'disable_language'], array_keys($post_data['settings']))) {
                $post_data['settings']['active_language'] = 'portuguese_br';
                $post_data['settings']['enabled_languages'] = ['portuguese_br'];
                $post_data['settings']['disable_language'] = '1';
            }

            $hasArgwsPlatformSettings = array_intersect(
                array_keys($post_data['settings']),
                ['argws_support_enabled', 'argws_support_base_url', 'argws_support_public_token', 'argws_support_position', 'argws_support_type', 'argws_support_launcher_title', 'argws_terminology_policy', 'support_contact_channel', 'support_whatsapp', 'support_email', 'support_site_url']
            ) !== [];
            if ($hasArgwsPlatformSettings && !is_admin() && !CompanySettingsRuntime::active()) {
                access_denied('settings');
            }

            if ($hasArgwsPlatformSettings && !$this->normalize_argws_platform_settings($post_data['settings'])) {
                redirect(admin_url('settings?group=argws_platform'));
                return;
            }

            $post_data['settings'] = \app\services\PaymentGatewayCatalog::filterSettings(
                $post_data['settings'], $this->payment_modes_model->get_payment_gateways(true)
            );

            if ($this->isolated_company()) {
                if (isset($post_data['tags']) || isset($post_data['custom_fields']) || $this->has_uploaded_files()) {
                    access_denied('settings');
                }
            } else {
                hooks()->do_action('before_update_system_options', $post_data);
            }

            $logo_uploaded     = (!$this->isolated_company() && handle_company_logo_upload() ? true : false);
            $favicon_uploaded  = (!$this->isolated_company() && handle_favicon_upload() ? true : false);
            $signatureUploaded = (!$this->isolated_company() && handle_company_signature_upload() ? true : false);

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

            try { $success = $this->settings_model->update($post_data); }
            catch (DomainException $error) {
                if (($salesPreferences === [] && $pdfPreferences === [] && $documentPdfPreferences === []) || !CompanySettingsRuntime::active() || !CompanySettingsRuntime::isPrimary()) { throw $error; }
                show_error('Não foi possível salvar as preferências. Confira os campos e sua permissão nesta empresa.', CompanySettingsRuntime::can('edit') ? 422 : 403); return;
            } catch (RuntimeException $error) {
                if (($salesPreferences === [] && $pdfPreferences === [] && $documentPdfPreferences === []) || !CompanySettingsRuntime::active() || !CompanySettingsRuntime::isPrimary()) { throw $error; }
                log_message('error','Principal sales preferences unconfirmed; no submitted values logged.');
                show_error('Não foi possível confirmar as preferências. Recarregue a página antes de tentar novamente.', 503); return;
            }

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

        if (! $group || (in_array($group, $data['admin_tabs']) && !CompanySettingsRuntime::active() && ! is_admin())) {
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
                : ($group === 'argws_platform' ? 'Configurações internas' : _l('settings_update'));
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
        $data['payment_gateways']     = \app\services\PaymentGatewayCatalog::forSelection(
            $this->payment_modes_model->get_payment_gateways(true)
        );

        $this->load->view('admin/settings/all', $data);
        if ($nativeUpgradeSuccess) {
            // The native renderer already emitted this alert in the document.
            $this->session->unset_userdata('message-success');
        }
    }

    /** Same Settings route and model, with no legacy global navigation or callback hydration. */
    private function isolated_index()
    {
        if (!$this->settings_allowed('view')) { show_error('Acesso negado.', 403); return; }
        $group = $this->input->get('group') ?: 'company';
        $groups = CompanySettingsCatalog::groups();
        if (!isset($groups[$group])) { show_error('Esta seção ainda não está disponível para a empresa selecionada.', 403); return; }
        $fields = CompanySettingsCatalog::fields($group);
        if($group==='company'){$this->load->helper(['fields','form']);}
        $error = null;
        $saved = false;
        if ($this->input->post()) {
            if (!$this->settings_allowed('edit')) { show_error('Acesso negado.', 403); return; }
            $post = $this->input->post(null, false);
            $settings = $post['settings'] ?? null;
            try {
                if (!is_array($settings) || isset($post['tags']) || isset($post['custom_fields']) || $this->has_uploaded_files()
                    || array_diff(array_keys($settings), $fields)) { throw new DomainException('company_settings_invalid_input'); }
                $settings = CompanySettingsCatalog::prepare($group, $settings);
                if ($group === 'email') {
                    if (!empty($post['clear_smtp_password'])) { $settings['smtp_password'] = ''; }
                    elseif (($settings['smtp_password'] ?? '') === '') { unset($settings['smtp_password']); }
                    $settings['mail_engine'] = 'phpmailer';
                    $settings['email_protocol'] = 'smtp';
                }
                if($group==='task_timer_stop'){$revision=$post['task_timer_stop_revision']??null;if(!is_string($revision)||!preg_match('/^(?:0|[1-9][0-9]{0,9})$/D',$revision)||(int)$revision>2147483647)throw new DomainException('company_task_timer_stop_preferences_invalid');(new CompanyTaskTimerStopPreferences($this->db,db_prefix()))->save(CompanySettingsRuntime::issuerId(),CompanySettingsRuntime::staffId(),(int)$revision,$settings);
                }elseif($group==='task_timers'){$revision=$post['task_timers_revision']??null;if(!is_string($revision)||!preg_match('/^(?:0|[1-9][0-9]{0,9})$/D',$revision)||(int)$revision>2147483647)throw new DomainException('company_task_timer_preferences_invalid');(new CompanyTaskTimerPreferences($this->db,db_prefix()))->save(CompanySettingsRuntime::issuerId(),CompanySettingsRuntime::staffId(),(int)$revision,$settings);
                }elseif($group==='task_operations'){
                    $revision=$post['task_operations_revision']??null;if(!is_string($revision)||!preg_match('/^(?:0|[1-9][0-9]{0,9})$/D',$revision)||(int)$revision>2147483647)throw new DomainException('company_task_operation_preferences_invalid');
                    (new CompanyTaskOperationPreferences($this->db,db_prefix()))->save(CompanySettingsRuntime::issuerId(),CompanySettingsRuntime::staffId(),(int)$revision,$settings);
                }elseif($group==='task_defaults'){
                    $revision=$post['task_defaults_revision']??null;if(!is_string($revision)||!preg_match('/^(?:0|[1-9][0-9]{0,9})$/D',$revision)||(int)$revision>2147483647)throw new DomainException('company_task_preferences_invalid');
                    (new CompanyTaskPreferences($this->db,db_prefix()))->save(CompanySettingsRuntime::issuerId(),CompanySettingsRuntime::staffId(),(int)$revision,$settings);
                }elseif($group==='ticket_preferences'){
                    $revision=$post['ticket_preferences_revision']??null;if(!is_string($revision)||!preg_match('/^(?:0|[1-9][0-9]{0,9})$/D',$revision)||(int)$revision>2147483647)throw new DomainException('company_ticket_preferences_invalid');
                    (new CompanyTicketPreferences($this->db,db_prefix()))->save(CompanySettingsRuntime::issuerId(),CompanySettingsRuntime::staffId(),(int)$revision,$settings);
                }elseif($group==='pdf_typography'){
                    $revision=$post['pdf_typography_revision']??null;if(!is_string($revision)||!preg_match('/^(?:0|[1-9][0-9]{0,9})$/D',$revision)||(int)$revision>2147483647)throw new DomainException('company_pdf_typography_invalid');
                    (new CompanyPdfTypography($this->db,db_prefix()))->save(CompanySettingsRuntime::issuerId(),CompanySettingsRuntime::staffId(),(int)$revision,$settings);
                }elseif($group==='project_files'){
                    $revision=$post['project_files_revision']??null;if(!is_string($revision)||!preg_match('/^(?:0|[1-9][0-9]{0,9})$/D',$revision)||(int)$revision>2147483647)throw new DomainException('company_project_file_settings_invalid');
                    (new CompanyProjectFileSettings($this->db,db_prefix()))->save(CompanySettingsRuntime::issuerId(),CompanySettingsRuntime::staffId(),(int)$revision,$settings);
                }else{$this->settings_model->update(['settings' => $settings]);}
                $saved = true;
            } catch (DomainException $exception) {
                $this->output->set_status_header(in_array($exception->getMessage(),['company_project_file_settings_stale','company_pdf_typography_stale','company_ticket_preferences_stale','company_task_preferences_stale','company_task_timer_preferences_stale','company_task_timer_stop_preferences_stale','company_task_operation_preferences_stale'],true)?409:422);
                $messages = [
                    'issuer_invalid_name' => 'Informe um nome válido para a empresa.',
                    'issuer_invalid_tax_id' => 'Informe um CPF ou CNPJ válido.',
                    'issuer_tax_conflict' => 'Este CPF ou CNPJ já está cadastrado em outra empresa.',
                    'company_settings_encryption_failed' => 'Não foi possível proteger a senha. Nenhuma senha em texto aberto foi salva.',
                ];
                $error = $messages[$exception->getMessage()] ?? 'Não foi possível salvar os dados. Verifique os campos e sua permissão nesta empresa.';
            } catch (RuntimeException $exception) {
                $this->output->set_status_header(503);
                $error = 'Não foi possível confirmar a gravação. Recarregue a página antes de tentar novamente.';
            }
        }
        $identity = (new CompanySettingsIdentity($this->db, db_prefix()))->read(CompanySettingsRuntime::issuerId());
        $pdfTypography=$group==='pdf_typography'?(new CompanyPdfTypography($this->db,db_prefix()))->page(CompanySettingsRuntime::issuerId(),CompanySettingsRuntime::staffId()):null;
        $projectFiles=$group==='project_files'?(new CompanyProjectFileSettings($this->db,db_prefix()))->page(CompanySettingsRuntime::issuerId(),CompanySettingsRuntime::staffId()):null;
        $taskTimerStop=$group==='task_timer_stop'?(new CompanyTaskTimerStopPreferences($this->db,db_prefix()))->page(CompanySettingsRuntime::issuerId(),CompanySettingsRuntime::staffId()):null;
        $taskTimers=$group==='task_timers'?(new CompanyTaskTimerPreferences($this->db,db_prefix()))->page(CompanySettingsRuntime::issuerId(),CompanySettingsRuntime::staffId()):null;
        $taskOperations=$group==='task_operations'?(new CompanyTaskOperationPreferences($this->db,db_prefix()))->page(CompanySettingsRuntime::issuerId(),CompanySettingsRuntime::staffId()):null;
        $taskDefaults=$group==='task_defaults'?(new CompanyTaskPreferences($this->db,db_prefix()))->page(CompanySettingsRuntime::issuerId(),CompanySettingsRuntime::staffId()):null;
        $ticketPreferences=$group==='ticket_preferences'?(new CompanyTicketPreferences($this->db,db_prefix()))->page(CompanySettingsRuntime::issuerId(),CompanySettingsRuntime::staffId()):null;
        $values = [];
        foreach ($fields as $name) {
            if ($name !== 'smtp_password') { $values[$name] = $taskTimerStop!==null?$taskTimerStop[$name]:($taskTimers!==null?$taskTimers[$name]:($taskOperations!==null?$taskOperations[$name]:($taskDefaults!==null?$taskDefaults[$name]:($ticketPreferences!==null?$ticketPreferences[$name]:($pdfTypography!==null?$pdfTypography[$name]:($projectFiles!==null?$projectFiles[$name]:(CompanySettingsRuntime::exists($name)?CompanySettingsRuntime::get($name):CompanySettingsCatalog::defaultValue($group,$name)))))))); }
        }
        $this->load->view('admin/settings/company_scoped', [
            'company_identity' => $identity, 'company_id' => CompanySettingsRuntime::issuerId(),
            'group' => $group, 'groups' => $groups, 'values' => $values, 'can_edit' => $this->settings_allowed('edit'),
            'password_configured' => $group === 'email' && CompanySettingsRuntime::get('smtp_password') !== '',
            'error' => $error, 'saved' => $saved,
            'pdf_typography_revision'=>$pdfTypography['revision']??null,
            'project_files_revision'=>$projectFiles['revision']??null,
            'ticket_preferences_revision'=>$ticketPreferences['revision']??null,
            'task_defaults_revision'=>$taskDefaults['revision']??null,
            'task_timer_stop_revision'=>$taskTimerStop['revision']??null,'task_operations_revision'=>$taskOperations['revision']??null,'task_timers_revision'=>$taskTimers['revision']??null,
            'context_token' => CompanyRequestScope::token(),
            'company_urls' => [
                'task_timer_stop'=>CompanyRequestScope::adminUrl($this,'settings?group=task_timer_stop'),
                'task_timers'=>CompanyRequestScope::adminUrl($this,'settings?group=task_timers'),
                'task_operations'=>CompanyRequestScope::adminUrl($this,'settings?group=task_operations'),
                'task_defaults'=>CompanyRequestScope::adminUrl($this,'settings?group=task_defaults'),
                'ticket_preferences'=>CompanyRequestScope::adminUrl($this,'settings?group=ticket_preferences'),
                'pdf_typography'=>CompanyRequestScope::adminUrl($this,'settings?group=pdf_typography'),
                'project_files'=>CompanyRequestScope::adminUrl($this,'settings?group=project_files'),
                'companies' => CompanyRequestScope::adminUrl($this, 'company_context'),
                'company' => CompanyRequestScope::adminUrl($this, 'settings?group=company'),
                'email' => CompanyRequestScope::adminUrl($this, 'settings?group=email'),
                'localization' => CompanyRequestScope::adminUrl($this, 'settings?group=localization'),
                'documents' => CompanyRequestScope::adminUrl($this, 'settings?group=documents'),
                'sales_documents' => CompanyRequestScope::adminUrl($this, 'settings?group=sales_documents'),
                'document_pdf' => CompanyRequestScope::adminUrl($this, 'settings?group=document_pdf'),
                'sales_pdf' => CompanyRequestScope::adminUrl($this, 'settings?group=sales_pdf'),
                'email_content' => CompanyRequestScope::adminUrl($this, 'settings?group=email_content'),
            ],
        ]);
    }

    private function settings_allowed(string $capability): bool
    {
        return CompanySettingsRuntime::active()
            ? CompanySettingsRuntime::can($capability) : staff_can($capability, 'settings');
    }

    private function isolated_company(): bool
    {
        return CompanySettingsRuntime::active() && !CompanySettingsRuntime::isPrimary();
    }

    private function has_uploaded_files(): bool
    {
        foreach ($_FILES as $file) {
            $errors = (array) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            foreach ($errors as $error) { if ((int) $error !== UPLOAD_ERR_NO_FILE) { return true; } }
        }
        return false;
    }

    private function normalize_argws_platform_settings(array &$settings)
    {
        // Contatos públicos de suporte: a configuração da instalação prevalece;
        // variáveis de ambiente são usadas quando não houver valores no banco.
        $contactChannel = (string) ($settings['support_contact_channel'] ?? 'none');
        if (!in_array($contactChannel, ['none', 'widget', 'whatsapp', 'email', 'website'], true)) {
            set_alert('danger', 'Selecione um canal de atendimento válido.');
            return false;
        }
        $settings['support_contact_channel'] = $contactChannel;

        $phone = preg_replace('/\\D+/', '', (string) ($settings['support_whatsapp'] ?? ''));
        if ($phone !== '' && strlen($phone) >= 10 && strlen($phone) <= 11) {
            $phone = '55' . $phone;
        }
        if ($phone !== '' && !preg_match('/^[1-9][0-9]{10,14}$/', $phone)) {
            set_alert('danger', 'Número do WhatsApp inválido. Informe país, DDD e número.');
            return false;
        }
        $settings['support_whatsapp'] = $phone;

        $email = trim((string) ($settings['support_email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_alert('danger', 'E-mail de suporte inválido.');
            return false;
        }
        $settings['support_email'] = $email;

        $site = trim((string) ($settings['support_site_url'] ?? ''));
        if ($site !== '') {
            $parts = parse_url($site);
            if (!filter_var($site, FILTER_VALIDATE_URL) || !$parts
                || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
                || isset($parts['user']) || isset($parts['pass'])) {
                set_alert('danger', 'Informe um endereço HTTPS válido para o site de atendimento.');
                return false;
            }
            $site = rtrim($site, '/');
        }
        $settings['support_site_url'] = $site;

        $fallback = [
            'whatsapp' => trim((string) getenv('CRM_SUPPORT_WHATSAPP')),
            'email' => trim((string) getenv('CRM_SUPPORT_EMAIL')),
            'website' => trim((string) getenv('CRM_SUPPORT_SITE_URL')),
        ];
        if ($this->isolated_company()) { $fallback = ['whatsapp' => '', 'email' => '', 'website' => '']; }
        $selection = ['whatsapp' => $phone, 'email' => $email, 'website' => $site];
        if (isset($selection[$contactChannel]) && $selection[$contactChannel] === ''
            && $fallback[$contactChannel] === '') {
            set_alert('danger', 'Informe os dados do canal selecionado ou configure a variável de ambiente correspondente.');
            return false;
        }

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
        if ($this->isolated_company()) { access_denied('settings'); }
        if (! $id) {
            redirect(admin_url('settings?group=tags'));
        }

        if (!$this->settings_allowed('delete')) {
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
        if ($this->isolated_company()) { access_denied('settings'); }
        if (!$this->settings_allowed('delete')) {
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
        if ($this->isolated_company()) { access_denied('settings'); }
        hooks()->do_action('before_remove_company_logo');

        if (!$this->settings_allowed('delete')) {
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
        if ($this->isolated_company()) { access_denied('settings'); }
        hooks()->do_action('before_remove_favicon');
        if (!$this->settings_allowed('delete')) {
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
        if (!$this->settings_allowed('delete')) {
            access_denied('settings');
        }

        echo json_encode([
            'success' => delete_option($name),
        ]);
    }

    public function clear_sessions()
    {
        if (CompanySettingsRuntime::active() && !CompanySettingsRuntime::canInfrastructure()) { access_denied('settings'); }
        if (!$this->settings_allowed('delete')) {
            access_denied('settings');
        }
        $this->db->empty_table(db_prefix() . 'sessions');

        set_alert('success', 'Sessões encerradas.');
        redirect(admin_url('settings?group=info'));
    }
}
