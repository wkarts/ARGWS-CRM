<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Emails_model extends App_Model
{
    private $attachment = [];
    private function companyTemplateScope(): string
    {
        require_once APPPATH.'services/CompanyEmailTemplates.php';
        return CompanyEmailTemplates::scopeSql($this->db,db_prefix(),db_prefix().'emailtemplates',CompanyEmailTemplates::currentIssuer());
    }
    private function scopeTemplates(): void { $this->db->where($this->companyTemplateScope(),null,false); }
    private function sealedCompanyEditor(): bool
    {
        return class_exists('CompanyRequestScope',false) && CompanyRequestScope::sealedFor(get_instance())
            && CompanySettingsRuntime::active() && !CompanySettingsRuntime::isPrimary();
    }



    /**
     * @deprecated 2.3.0
     */
    private $client_email_templates;

    /**
     * @deprecated 2.3.0
     */
    private $staff_email_templates;

    /**
     * @deprecated 2.3.0
     */
    private $rel_id;

    /**
     * @deprecated 2.3.0
     */
    private $rel_type;

    /**
     * @deprecated 2.3.0
     */
    private $staff_id;

    public function __construct()
    {
        if (class_exists('CompanyRequestScope',false) && CompanyRequestScope::sealedFor(get_instance())
            && CompanySettingsRuntime::active() && !CompanySettingsRuntime::isPrimary()) {
            CI_Model::__construct();
            return; // No global model-init hooks or legacy deprecated send helpers in this editor.
        }
        parent::__construct();
        $this->client_email_templates = get_client_email_templates_slugs();
        $this->staff_email_templates  = get_staff_email_templates_slugs();
    }

    /**
     * @param  string
     * @return array
     * Get email template by type
     */
    public function get($where = [], $result_type = 'result_array')
    {
        if ($where) { $this->db->group_start();$this->db->where($where);$this->db->group_end(); }
        $this->scopeTemplates();
        return $this->db->get(db_prefix() . 'emailtemplates')->{$result_type}();
    }

    /**
     * @param  integer
     * @return object
     * Get email template by id
     */
    public function get_email_template_by_id($id)
    {
        $this->db->where('emailtemplateid', $id);
        $this->scopeTemplates();
        return $this->db->get(db_prefix() . 'emailtemplates')->row();
    }

    /**
     * Create new email template
     * @param mixed $data
     */
    public function add_template($data)
    {
        $this->companyTemplateScope();
        unset($data['issuer_id']);
        if ($this->db->field_exists('issuer_id',db_prefix().'emailtemplates')) $data['issuer_id']=CompanyEmailTemplates::currentIssuer();
        $this->db->insert(db_prefix() . 'emailtemplates', $data);
        $insert_id = $this->db->insert_id();
        if ($insert_id) {
            return $insert_id;
        }

        return false;
    }

    /**
     * @param  array $_POST data
     * @param  integer ID
     * @return boolean
     * Update email template
     */
    public function update($data)
    {
        require_once APPPATH.'services/CompanyEmailTemplates.php';
        if ($this->db->table_exists(db_prefix().'issuers_lock')) {
            return IssuerTransaction::run($this->db,db_prefix(),$this->sealedCompanyEditor()?['issuers_lock','emailtemplates','issuers_audit']:['issuers_lock','emailtemplates'],fn()=>$this->updateScopedRows($data));
        }
        return $this->updateScopedRows($data);
    }
    /** Narrow native editor: lock before reading other language variants, preventing stale overwrites. */
    public function update_company_template($id,array $input): bool
    {
        require_once APPPATH.'services/CompanyEmailTemplates.php';
        if (!$this->sealedCompanyEditor()) throw new DomainException('company_settings_access_denied');
        return IssuerTransaction::run($this->db,db_prefix(),['issuers_lock','emailtemplates','issuers_audit'],function()use($id,$input){
            if(!CompanySettingsRuntime::canResource('email_templates','edit'))throw new DomainException('company_settings_access_denied');
            $selected=$this->get_email_template_by_id($id);
            if(!$selected || $selected->language!=='portuguese_br')throw new DomainException('company_settings_access_denied');
            $payload=['subject'=>[],'message'=>[],'fromname'=>$input['fromname'],'fromemail'=>$input['fromemail']];
            foreach($this->get(['slug'=>$selected->slug]) as $variant) {
                $variantId=(int)$variant['emailtemplateid'];
                $payload['subject'][$variantId]=$variantId===(int)$selected->emailtemplateid?$input['subject']:$variant['subject'];
                $payload['message'][$variantId]=$variantId===(int)$selected->emailtemplateid?$input['message']:$variant['message'];
            }
            if(!isset($input['active']))$payload['disabled']=1;
            if(isset($input['plaintext']))$payload['plaintext']=1;
            return $this->updateScopedRows($payload);
        });
    }

    private function updateScopedRows($data)
    {
        if ($this->sealedCompanyEditor() && !CompanySettingsRuntime::canResource('email_templates','edit')) throw new DomainException('company_settings_access_denied');
        foreach (array_keys($data['subject'] ?? []) as $id) { if (!$this->get_email_template_by_id($id)) return false; }
        $data['plaintext'] = isset($data['plaintext']) ? 1 : 0;

        if (isset($data['disabled'])) {
            $data['active'] = 0;
            unset($data['disabled']);
        } else {
            $data['active'] = 1;
        }

        $main_id      = false;
        $affectedRows = 0;
        $i            = 0;

        foreach ($data['subject'] as $id => $val) {
            if ($i == 0) {
                $main_id = $id;
            }

            $_data             = [];
            $_data['subject']  = $val;
            $_data['fromname'] = $data['fromname'];
            // Two factor authentication email template  don't have fromemail
            $_data['fromemail'] = isset($data['fromemail']) ? $data['fromemail'] : '';
            $_data['message']   = $data['message'][$id];
            $_data['plaintext'] = $data['plaintext'];
            $_data['active']    = $data['active'];

            $this->db->where('emailtemplateid', $id);
            $this->scopeTemplates();
            if ($this->db->update(db_prefix() . 'emailtemplates', $_data)===false) throw new RuntimeException('issuer_storage_failed');
            if ($this->db->affected_rows() > 0) {
                $affectedRows++;
            }

            $i++;
        }
        $main_template = $this->get_email_template_by_id($main_id);

        if ($affectedRows > 0 && $main_template) {
            if ($this->sealedCompanyEditor()) {
                IssuerStorage::query($this->db,'INSERT INTO '.db_prefix().'issuers_audit (actor_id,event,issuer_id,subject_id,created_at) VALUES (?,?,?,?,?)',
                    [CompanySettingsRuntime::staffId(),'email_template_updated',CompanySettingsRuntime::issuerId(),(int)$main_id,date('Y-m-d H:i:s')]);
            } else { log_activity('Email Template Updated [' . $main_template->name . ']'); }

            return true;
        }

        return false;
    }

    /**
     * Change template to active/inactive
     * @param  string $slug    template slug
     * @param  mixed $enabled enabled or disabled / 1 or 0
     * @return boolean
     */
    public function mark_as($slug, $enabled)
    {
        $this->db->where('slug', $slug);
        $this->scopeTemplates();
        $this->db->update(db_prefix() . 'emailtemplates', ['active' => $enabled]);

        return $this->db->affected_rows() > 0 ? true : false;
    }

    /**
     * Change template to active/inactive
     * @param  string $type    template type
     * @param  mixed $enabled enabled or disabled / 1 or 0
     * @return boolean
     */
    public function mark_as_by_type($type, $enabled)
    {
        $this->db->where('type', $type);
        $this->db->where('slug !=', 'two-factor-authentication');
        $this->scopeTemplates();
        $this->db->update(db_prefix() . 'emailtemplates', ['active' => $enabled]);

        return $this->db->affected_rows() > 0 ? true : false;
    }

    /**
     * Send email - No templates used only simple string
     * @since Version 1.0.2
     * @param  string $email   email
     * @param  string $message message
     * @param  string $subject email subject
     * @return boolean
     */
    public function send_simple_email($email, $subject, $message)
    {
        if (defined('DEMO') && DEMO) {
            return true;
        }

        $cnf = [
            'from_email' => get_option('smtp_email'),
            'from_name'  => get_option('companyname'),
            'email'      => $email,
            'subject'    => $subject,
            'message'    => $message,
        ];

        // Simulate fake template to be parsed
        $template           = new StdClass();
        $template->message  = get_option('email_header') . $cnf['message'] . get_option('email_footer');
        $template->fromname = $cnf['from_name'];
        $template->subject  = $cnf['subject'];

        $template = parse_email_template($template);

        $cnf['message']   = $template->message;
        $cnf['from_name'] = $template->fromname;
        $cnf['subject']   = $template->subject;

        $cnf['message'] = check_for_links($cnf['message']);

        $cnf = hooks()->apply_filters('before_send_simple_email', $cnf);

        if (isset($cnf['prevent_sending']) && $cnf['prevent_sending'] == true) {
            $this->clear_attachments();

            return false;
        }
        $this->load->config('email');
        $this->email->clear(true);
        $this->email->set_newline(config_item('newline'));
        $this->email->from($cnf['from_email'], $cnf['from_name']);
        $this->email->to($cnf['email']);

        $bcc = '';
        // Used for action hooks
        if (isset($cnf['bcc'])) {
            $bcc = $cnf['bcc'];
            if (is_array($bcc)) {
                $bcc = implode(', ', $bcc);
            }
        }

        $systemBCC = get_option('bcc_emails');
        if ($systemBCC != '') {
            if ($bcc != '') {
                $bcc .= ', ' . $systemBCC;
            } else {
                $bcc .= $systemBCC;
            }
        }
        if ($bcc != '') {
            $this->email->bcc($bcc);
        }

        if (isset($cnf['cc'])) {
            $this->email->cc($cnf['cc']);
        }

        if (isset($cnf['reply_to'])) {
            $this->email->reply_to($cnf['reply_to']);
        }

        $this->email->subject($cnf['subject']);
        $this->email->message($cnf['message']);

        $this->email->set_alt_message(strip_html_tags($cnf['message'], '<br/>, <br>, <br />'));

        if (count($this->attachment) > 0) {
            foreach ($this->attachment as $attach) {
                if (!isset($attach['read'])) {
                    $this->email->attach($attach['attachment'], 'attachment', $attach['filename'], $attach['type']);
                } else {
                    if (!isset($attach['filename']) || (isset($attach['filename']) && empty($attach['filename']))) {
                        $attach['filename'] = basename($attach['attachment']);
                    }
                    $this->email->attach($attach['attachment'], '', $attach['filename']);
                }
            }
        }

        $this->clear_attachments();
        if ($this->email->send()) {
            log_activity('Email sent to: ' . $cnf['email'] . ' Subject: ' . $cnf['subject']);

            return true;
        }

        return false;
    }

    /**
     * Send email template
     * @deprecated 2.3.0
     * @param  string $template_slug email template slug
     * @param  string $email         email to send
     * @param  array $merge_fields  merge field
     * @param  string $ticketid      used only when sending email templates linked to ticket / used for piping
     * @param  mixed $cc
     * @return boolean
     */
    public function send_email_template($template_slug, $email, $merge_fields, $ticketid = '', $cc = '')
    {
        $email = hooks()->apply_filters('send_email_template_to', $email);

        $template                     = get_email_template_for_sending($template_slug, $email);
        $staff_email_templates_slugs  = get_staff_email_templates_slugs();
        $client_email_templates_slugs = get_client_email_templates_slugs();

        $inactive_user_table_check = '';

        /**
         * Dont send email templates for non active contacts/staff
         * Do checking here
         */
        if (in_array($template_slug, $staff_email_templates_slugs)) {
            $inactive_user_table_check = db_prefix() . 'staff';
        } elseif (in_array($template_slug, $client_email_templates_slugs)) {
            $inactive_user_table_check = db_prefix() . 'contacts';
        }

        /**
         * Is really inactive?
         */
        if ($inactive_user_table_check != '') {
            $this->db->select('active')->where('email', $email);
            $user = $this->db->get($inactive_user_table_check)->row();
            if ($user && $user->active == 0) {
                $this->clear_attachments();
                $this->set_staff_id(null);

                return false;
            }
        }

        /**
         * Template not found?
         */
        if (!$template) {
            log_activity('Failed to send email template [Template not found]');
            $this->clear_attachments();
            $this->set_staff_id(null);

            return false;
        }

        /**
         * Template is disabled or invalid email?
         * Log activity
         */
        if ($template->active == 0 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->clear_attachments();

            $this->db->where('language', 'english');
            $this->db->where('slug', $template->slug);
            $this->scopeTemplates();
            $tmpTemplate = $this->db->get(db_prefix() . 'emailtemplates')->row();

            if ($tmpTemplate) {
                log_activity('Failed to send email template [<a href="' . admin_url('emails/email_template/' . $tmpTemplate->emailtemplateid) . '">' . $template->name . '</a>] [Reason: Email template is disabled.]');
            }

            return false;
        }

        $template = hooks()->apply_filters('before_parse_email_template_message', $template);

        $template = parse_email_template($template, $merge_fields);

        $template = hooks()->apply_filters('after_parse_email_template_message', $template);

        $template->message = get_option('email_header') . $template->message . get_option('email_footer');

        // Parse merge fields again in case there is merge fields found in email_header and email_footer option.
        // We cant parse this in parse_email_template function because in case the template content is send via $_POST wont work
        $template = parse_email_template_merge_fields($template, $merge_fields);

        /**
         * Template is plain text?
         */
        if ($template->plaintext == 1) {
            $this->config->set_item('mailtype', 'text');
            $template->message = strip_html_tags($template->message, '<br/>, <br>, <br />');
        }

        $fromemail = $template->fromemail;
        $fromname  = $template->fromname;

        if ($fromemail == '') {
            $fromemail = get_option('smtp_email');
        }

        if ($fromname == '') {
            $fromname = get_option('companyname');
        }

        /**
         * Ticket variables
        */
        $reply_to               = false;
        $from_header_dept_email = false;
        /**
         * Tickets template
         * For tickets there is different config
         */
        if (is_numeric($ticketid) && $template->type == 'ticket') {
            if (!class_exists('tickets_model')) {
                $this->load->model('tickets_model');
            }

            $this->db->select(db_prefix() . 'departments.email as department_email, email_from_header as dept_email_from_header')
            ->where('ticketid', $ticketid)
            ->join(db_prefix() . 'departments', db_prefix() . 'departments.departmentid=' . db_prefix() . 'tickets.department', 'left');

            $ticket = $this->db->get(db_prefix() . 'tickets')->row();

            if (!empty($ticket->department_email) && filter_var($ticket->department_email, FILTER_VALIDATE_EMAIL)) {
                $reply_to               = $ticket->department_email;
                $from_header_dept_email = $ticket->dept_email_from_header == 1;
            }
            /**
             * IMPORTANT
             * Do not change/remove this line, this is used for email piping so the software can recognize the ticket id.
             */
            if (substr($template->subject, 0, 10) != '[Ticket ID') {
                $template->subject = '[Ticket ID: ' . $ticketid . '] ' . $template->subject;
            }
        }

        $hook_data['template']    = $template;
        $hook_data['email']       = $email;
        $hook_data['attachments'] = $this->attachment;

        $hook_data['template']->message = check_for_links($hook_data['template']->message);

        $hook_data = hooks()->apply_filters('before_email_template_send', $hook_data);

        $template    = $hook_data['template'];
        $email       = $hook_data['email'];
        $attachments = $hook_data['attachments'];

        if (isset($template->prevent_sending) && $template->prevent_sending == true) {
            $this->clear_attachments();
            $this->set_staff_id(null);

            return false;
        }

        $this->load->config('email');
        $this->email->clear(true);
        $this->email->set_newline(config_item('newline'));
        $this->email->from(($from_header_dept_email ? $ticket->department_email : $fromemail), $fromname);
        $this->email->subject($template->subject);

        $this->email->message($template->message);
        $this->email->to($email);

        $bcc = '';
        // Used for action hooks
        if (isset($template->bcc)) {
            $bcc = $template->bcc;
            if (is_array($bcc)) {
                $bcc = implode(', ', $bcc);
            }
        }

        $systemBCC = get_option('bcc_emails');
        if ($systemBCC != '') {
            if ($bcc != '') {
                $bcc .= ', ' . $systemBCC;
            } else {
                $bcc .= $systemBCC;
            }
        }

        if ($bcc != '') {
            $bcc = array_map('trim', explode(',', $bcc));
            $bcc = array_unique($bcc);
            $bcc = implode(', ', $bcc);
            $this->email->bcc($bcc);
        }

        if ($reply_to != false) {
            $this->email->reply_to($reply_to);
        } elseif (isset($template->reply_to)) {
            $this->email->reply_to($template->reply_to);
        }

        if ($template->plaintext == 0) {
            $alt_message = strip_html_tags($template->message, '<br/>, <br>, <br />');
            // Replace <br /> with \n
            $alt_message = clear_textarea_breaks($alt_message, "\r\n");
            $this->email->set_alt_message($alt_message);
        }

        if (is_array($cc) || !empty($cc)) {
            $this->email->cc($cc);
        }

        if (count($attachments) > 0) {
            foreach ($attachments as $attach) {
                if (!isset($attach['read'])) {
                    $this->email->attach($attach['attachment'], 'attachment', $attach['filename'], $attach['type']);
                } else {
                    $this->email->attach($attach['attachment'], '', $attach['filename']);
                }
            }
        }

        $this->clear_attachments();
        $this->set_staff_id(null);

        if ($this->email->send()) {
            log_activity('Email Send To [Email: ' . $email . ', Template: ' . $template->name . ']');
            hooks()->do_action('email_template_sent', ['template' => $template, 'email' => $email]);

            return true;
        }

            log_activity('Failed to send email template - ' . $this->email->print_debugger());

        return false;
    }

    /**
     * @param resource
     * @param string
     * @param string (mime type)
     * @return none
     * Add attachment to property to check before an email is send
     */
    public function add_attachment($attachment)
    {
        $this->attachment[] = $attachment;
    }

    /**
     * @return none
     * Clear all attachment properties
     */
    private function clear_attachments()
    {
        $this->attachment = [];
    }

    /**
     * @deprecated 2.3.0
     */
    public function set_rel_id($rel_id)
    {
        $this->rel_id = $rel_id;
    }

    /**
     * @deprecated 2.3.0
     */
    public function set_rel_type($rel_type)
    {
        $this->rel_type = $rel_type;
    }

    /**
     * @deprecated 2.3.0
     */
    public function get_rel_id()
    {
        return $this->rel_id;
    }

    /**
     * @deprecated 2.3.0
     */
    public function get_rel_type()
    {
        return $this->rel_type;
    }

    /**
     * @deprecated 2.3.0
     */
    public function set_staff_id($id)
    {
        $this->staff_id = $id;
    }

    /**
     * @deprecated 2.3.0
     */
    public function get_staff_id()
    {
        return $this->staff_id;
    }
}