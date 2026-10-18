<?php

defined('BASEPATH') or exit('Acesso direto ao script não permitido.');

/**
 * @property-read Emails_model $emails_model
 * @property-read App_Email $email
 */
class Emails extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if ($this->isolated_company() && (!class_exists('CompanyRequestScope',false) || !CompanyRequestScope::sealedFor($this))) { show_error('Acesso negado.',403); return; }
        $this->load->model('emails_model');
    }

    /* List all email templates */
    public function index()
    {
        if ($this->isolated_company()) { return $this->isolated_templates(); }
        if (staff_cant('view', 'email_templates')) {
            access_denied('email_templates');
        }
        // PT-BR é o idioma único do CRM. Não criar modelos para outros idiomas.
        // Recupera modelos de módulos previamente ativados, sem tocar em
        // personalizações ou nos estados habilitado/desabilitado existentes.
        require_once APPPATH . 'services/EmailTemplatesPtBr.php';
        try {
            EmailTemplatesPtBr::synchronizeModuleTemplates($this->db);
        } catch (\Throwable $exception) {
            log_message('error', 'Não foi possível conciliar os modelos PT-BR: '
                . $exception->getMessage());
        }

        $data['staff'] = $this->emails_model->get([
            'type'     => 'staff',
            'language' => 'portuguese_br',
        ]);

        $data['credit_notes'] = $this->emails_model->get([
            'type'     => 'credit_note',
            'language' => 'portuguese_br',
        ]);

        $data['tasks'] = $this->emails_model->get([
            'type'     => 'tasks',
            'language' => 'portuguese_br',
        ]);
        $data['client'] = $this->emails_model->get([
            'type'     => 'client',
            'language' => 'portuguese_br',
        ]);
        $data['tickets'] = $this->emails_model->get([
            'type'     => 'ticket',
            'language' => 'portuguese_br',
        ]);
        $data['invoice'] = $this->emails_model->get([
            'type'     => 'invoice',
            'language' => 'portuguese_br',
        ]);
        $data['estimate'] = $this->emails_model->get([
            'type'     => 'estimate',
            'language' => 'portuguese_br',
        ]);
        $data['contracts'] = $this->emails_model->get([
            'type'     => 'contract',
            'language' => 'portuguese_br',
        ]);
        $data['proposals'] = $this->emails_model->get([
            'type'     => 'proposals',
            'language' => 'portuguese_br',
        ]);
        $data['projects'] = $this->emails_model->get([
            'type'     => 'project',
            'language' => 'portuguese_br',
        ]);
        $data['leads'] = $this->emails_model->get([
            'type'     => 'leads',
            'language' => 'portuguese_br',
        ]);

        $data['gdpr'] = $this->emails_model->get([
            'type'     => 'gdpr',
            'language' => 'portuguese_br',
        ]);

        $data['subscriptions'] = $this->emails_model->get([
            'type'     => 'subscriptions',
            'language' => 'portuguese_br',
        ]);

        $data['estimate_request'] = $this->emails_model->get([
            'type'     => 'estimate_request',
            'language' => 'portuguese_br',
        ]);

        $data['notifications'] = $this->emails_model->get([
            'type'     => 'notifications',
            'language' => 'portuguese_br',
        ]);

        $data['title'] = _l('email_templates');

        $data['hasPermissionEdit'] = staff_can('edit',  'email_templates');

        $this->load->view('admin/emails/email_templates', $data);
    }

    /* Edit email template */
    public function email_template($id)
    {
        if ($this->isolated_company()) { return $this->isolated_templates($id); }
        if (staff_cant('view', 'email_templates')) {
            access_denied('email_templates');
        }
        if (!$id) {
            redirect(admin_url('emails'));
        }

        $selectedTemplate = $this->emails_model->get_email_template_by_id($id);
        if (!$selectedTemplate) { show_404(); return; }

        if ($this->input->post()) {
            if (staff_cant('edit', 'email_templates')) {
                access_denied('email_templates');
            }

            $data = $this->input->post();
            $tmp  = $this->input->post(null, false);

            foreach ($data['message'] as $key => $contents) {
                $data['message'][$key] = $tmp['message'][$key];
            }

            foreach ($data['subject'] as $key => $contents) {
                $data['subject'][$key] = $tmp['subject'][$key];
            }

            $data['fromname'] = $tmp['fromname'];

            $success = $this->emails_model->update($data, $id);

            if ($success) {
                set_alert('success', _l('updated_successfully', _l('email_template')));
            }

            redirect(admin_url('emails/email_template/' . $id));
        }

        // portuguese_br is not included here
        $data['available_languages'] = $this->app->get_available_languages();

        if (($key = array_search('portuguese_br', $data['available_languages'])) !== false) {
            unset($data['available_languages'][$key]);
        }

        $data['available_merge_fields'] = $this->app_merge_fields->all();

        $data['template'] = $selectedTemplate;
        $title            = $data['template']->name;
        $data['title']    = $title;
        $this->load->view('admin/emails/template', $data);
    }

    public function enable_by_type($type)
    {
        if (staff_can('edit',  'email_templates')) {
            $this->emails_model->mark_as_by_type($type, 1);
        }
        redirect(admin_url('emails'));
    }

    public function disable_by_type($type)
    {
        if (staff_can('edit',  'email_templates')) {
            $this->emails_model->mark_as_by_type($type, 0);
        }
        redirect(admin_url('emails'));
    }

    public function enable($id)
    {
        if (staff_can('edit',  'email_templates')) {
            $template = $this->emails_model->get_email_template_by_id($id);
            if (!$template) { show_404(); return; }
            $this->emails_model->mark_as($template->slug, 1);
        }
        redirect(admin_url('emails'));
    }

    public function disable($id)
    {
        if (staff_can('edit',  'email_templates')) {
            $template = $this->emails_model->get_email_template_by_id($id);
            if (!$template) { show_404(); return; }
            $this->emails_model->mark_as($template->slug, 0);
        }

        redirect(admin_url('emails'));
    }

    private function isolated_company(): bool
    {
        return class_exists('CompanySettingsRuntime',false) && CompanySettingsRuntime::active() && !CompanySettingsRuntime::isPrimary();
    }

    private function isolated_templates($id=null)
    {
        if (!CompanyRequestScope::sealedFor($this) || !CompanySettingsRuntime::canResource('email_templates','view')) { show_error('Acesso negado.',403); return; }
        $selected=$id===null?null:$this->emails_model->get_email_template_by_id($id);
        if ($id!==null && (!$selected || $selected->language!=='portuguese_br')) { show_404(); return; }
        $saved=false;$error=null;
        if ($this->input->post()) {
            if (!$selected || !CompanySettingsRuntime::canResource('email_templates','edit')) { show_error('Acesso negado.',403); return; }
            $post=$this->input->post(null,false);
            try {
                if (!is_array($post) || array_diff(array_keys($post),['subject','message','fromname','fromemail','active','plaintext']) || !empty($_FILES)) throw new DomainException('invalid_template');
                foreach(['subject','message','fromname','fromemail'] as $key) if (!isset($post[$key]) || !is_string($post[$key])) throw new DomainException('invalid_template');
                if (strlen($post['subject'])>998 || strlen($post['fromname'])>191 || strlen($post['fromemail'])>191 || strlen($post['message'])>1048576
                    || preg_match('/[\r\n]/',$post['subject'].$post['fromname'].$post['fromemail'])
                    || ($post['fromemail']!=='' && !filter_var($post['fromemail'],FILTER_VALIDATE_EMAIL))) throw new DomainException('invalid_template');
                foreach(['active','plaintext'] as $key) if(isset($post[$key]) && $post[$key]!=='1')throw new DomainException('invalid_template');
                $this->emails_model->update_company_template($id,$post);
                $selected=$this->emails_model->get_email_template_by_id($id);
                $saved=true;
            } catch (DomainException $exception) {
                $this->output->set_status_header(422);$error='Não foi possível salvar. Confira os campos e sua permissão nesta empresa.';
            } catch (RuntimeException $exception) {
                $this->output->set_status_header(503);$error='Não foi possível confirmar a gravação. Recarregue antes de tentar novamente.';
            }
        }
        require_once APPPATH.'services/CompanySettingsIdentity.php';
        $identity=(new CompanySettingsIdentity($this->db,db_prefix()))->read(CompanySettingsRuntime::issuerId());
        $this->load->view('admin/emails/company_scoped',[
            'company_identity'=>$identity,'selected'=>$selected,'templates'=>$selected?[]:$this->emails_model->get(['language'=>'portuguese_br']),
            'can_edit'=>CompanySettingsRuntime::canResource('email_templates','edit'),'saved'=>$saved,'error'=>$error,
            'context_token'=>CompanyRequestScope::token(),'list_url'=>CompanyRequestScope::adminUrl($this,'emails'),
            'edit_url'=>CompanyRequestScope::adminUrl($this,'emails/email_template/'),
        ]);
    }

    /* Since version 1.0.1 - test your smtp settings */
    public function sent_smtp_test_email()
    {
        if ($this->input->post()) {
            $this->load->config('email');
            // Simulate fake template to be parsed
            $template           = new StdClass();
            $template->message  = get_option('email_header') . 'Este é um e-mail de teste do SMTP.<br />Se você recebeu esta mensagem, significa que suas configurações de SMTP estão corretas.' . get_option('email_footer');
            $template->fromname = get_option('companyname') != '' ? get_option('companyname') : 'TESTE';
            $template->subject  = 'Teste de Configuração do SMTP';

            $template = parse_email_template($template);

            hooks()->do_action('before_send_test_smtp_email');
            $this->email->initialize();
            if (get_option('mail_engine') == 'phpmailer') {
                $this->email->set_debug_output(function ($err) {
                    if (!isset($GLOBALS['debug'])) {
                        $GLOBALS['debug'] = '';
                    }
                    $GLOBALS['debug'] .= $err . '<br />';

                    return $err;
                });

                $this->email->set_smtp_debug(3);
            }

            $this->email->set_newline(config_item('newline'));
            $this->email->set_crlf(config_item('crlf'));

            $this->email->from(get_option('smtp_email'), $template->fromname);
            $this->email->to($this->input->post('test_email'));

            $systemBCC = get_option('bcc_emails');

            if ($systemBCC != '') {
                $this->email->bcc($systemBCC);
            }

            $this->email->subject($template->subject);
            $this->email->message($template->message);

            if ($this->email->send(true)) {
                set_alert('success', 'Parece que suas configurações de SMTP estão corretas. Verifique seu e-mail agora.');
                hooks()->do_action('smtp_test_email_success');
            } else {
                set_debug_alert('<h1>Suas configurações de SMTP não estão corretas. Veja o log de depuração abaixo.</h1><br />' . $this->email->print_debugger() . (isset($GLOBALS['debug']) ? $GLOBALS['debug'] : ''));

                hooks()->do_action('smtp_test_email_failed');
            }
        }
    }

    public function delete_queued_email($id)
    {
        if (staff_can('edit', 'settings')) {
            $this->email->delete_queued_email($id);
             set_alert('success', _l('deleted', _l('email_queue')));
        }

        redirect(admin_url('settings?group=email&tab=email_queue'));
    }

    public function clear_queued_emails()
    {
        if (staff_can('edit', 'settings')) {
            $this->email->clear_queued_emails();
            set_alert('success', _l('email_queue_cleared'));
        }
        redirect(admin_url('settings?group=email&tab=email_queue'));
    }
}
