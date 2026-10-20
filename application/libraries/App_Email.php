<?php

defined('BASEPATH') or exit('No direct script access allowed');

include_once(APPPATH . 'libraries/App_mailer.php');

class App_Email extends App_mailer
{
    // Email Queue Table
    private $email_queue_table;

    // Status (pending, sending, sent, failed)
    private $status;

    private ?int $companyOrigin = null;

    /**
     * Constructor
     */
    public function __construct($config = [])
    {
        $this->email_queue_table = db_prefix() . 'mail_queue';
        if (class_exists('CompanySettingsRuntime', false) && CompanySettingsRuntime::active() && !CompanySettingsRuntime::isPrimary()) {
            $this->companyOrigin = CompanySettingsRuntime::issuerId();
        }

        parent::__construct($config);
    }

    /**
     * Set the mail queue status
     *
     * @param string $status
     */
    public function set_status($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get
     *
     * Get queue emails.
     * @return  mixed
     */
    public function get_queue_emails($limit = null, $offset = null)
    {
        $this->scopeQueue('q.');
        if ($this->status != false) {
            $this->CI->db->where('q.status', $this->status);
        }

        $query = $this->CI->db->get("{$this->email_queue_table} q", $limit, $offset);

        return $query->result();
    }

    /**
     * Save
     *
     * Add queue email to database.
     * @return  mixed
     */
    public function send($skip_job = false)
    {
        $currentOrigin = class_exists('CompanySettingsRuntime', false) && CompanySettingsRuntime::active()
            && !CompanySettingsRuntime::isPrimary() ? CompanySettingsRuntime::issuerId() : null;
        if ($this->companyOrigin !== $currentOrigin) {
            throw new DomainException('company_mail_context_changed');
        }
        $attachments = $this->mailer_engine == 'codeigniter' ? $this->_attachments : $this->phpmailer->getAttachments();

        $emailQueue          = get_option('email_queue_enabled');
        $queueSkipAttachment = get_option('email_queue_skip_with_attachments');

        if ($skip_job === true
            || $emailQueue == '0'
            || ($emailQueue == '1' && $queueSkipAttachment == '1' && count($attachments) > 0)
            || (defined('CRON') && !is_staff_logged_in())) {
            return parent::send();
        }

        if ($this->mailer_engine == 'codeigniter') {
            $to      = is_array($this->_recipients) ? implode(', ', $this->_recipients) : $this->_recipients;
            $cc      = implode(', ', $this->_cc_array);
            $bcc     = implode(', ', $this->_bcc_array);
            $headers = serialize($this->_headers);
        } else {
            $to = $this->phpmailer->getToAddresses();
            $to = array_filter($to[0]);
            $to = is_array($to) ? implode(', ', $to) : $to;

            $ccMailer = $this->phpmailer->getCcAddresses();
            $cc       = '';
            foreach ($ccMailer as $ccAddress) {
                $cc .= $ccAddress[0] . ', ';
            }
            if ($cc != '') {
                $cc = rtrim($cc, ', ');
            }

            $bccMailer = $this->phpmailer->getBccAddresses();
            $bcc       = '';
            foreach ($bccMailer as $ccAddress) {
                $bcc .= $ccAddress[0] . ', ';
            }
            if ($bcc != '') {
                $bcc = rtrim($bcc, ', ');
            }

            $ReplyTo       = $this->phpmailer->getReplyToAddresses();
            $ReplyToString = '';
            foreach ($ReplyTo as $replyToEmail => $array) {
                $ReplyToString .= $replyToEmail . ', ';
            }

            $headers = $this->_headers;
            if ($ReplyToString != '') {
                $ReplyToString = rtrim($ReplyToString, ', ');

                $headers['replyTo'] = $ReplyToString;
            }

            $headers['from']     = $this->phpmailer->From;
            $headers['fromName'] = $this->phpmailer->FromName;
            $headers['subject']  = $this->phpmailer->Subject;

            $headers = serialize($headers);
        }

        $attachments = base64_encode(serialize($attachments));

        $dbdata = [
            'engine'      => $this->mailer_engine,
            'email'       => $to,
            'cc'          => $cc,
            'bcc'         => $bcc,
            'message'     => $this->_body,
            'alt_message' => $this->_get_alt_message(),
            'headers'     => $headers,
            'attachments' => $attachments,
            'status'      => 'pending',
            'date'        => date('Y-m-d H:i:s'),
        ];

        if ($this->companyOrigin !== null) {
            if (!$this->CI->db->field_exists('issuer_id', $this->email_queue_table)) {
                throw new DomainException('company_mail_queue_upgrade_required');
            }
            $dbdata['issuer_id'] = $this->companyOrigin;
        }
        return $this->CI->db->insert($this->email_queue_table, $dbdata);
    }

    /**
     * Send queue
     *
     * Send queue emails.
     * @return  void
     */
    public function send_queue()
    {
        // Reject interactive context before config loading, cleanup or any queue mutation.
        if (class_exists('CompanySettingsRuntime', false) && CompanySettingsRuntime::active()) {
            throw new DomainException('company_mail_worker_context_denied');
        }
        $this->CI->load->config('email');
        $this->clean_up_old_queue();
        // The worker enumerates explicit durable origins; never adopts a staff session.
        $emails = $this->CI->db->where('status', 'pending')->get($this->email_queue_table)->result();
        $legacyConfig = array_replace(self::$default_properties, array_intersect_key($this->CI->config->config, self::$default_properties));
        foreach ($emails as $email) {
            // Atomic claim: concurrent workers may read the same candidate, only one sends it.
            $this->CI->db->where('id', $email->id)->where('status', 'pending');
            $this->CI->db->update($this->email_queue_table, ['status'=>'sending','date'=>date('Y-m-d H:i:s')]);
            if ($this->CI->db->affected_rows() !== 1) { continue; }
            try {
                if (!in_array($email->engine, ['codeigniter','phpmailer'], true)) { throw new DomainException('company_mail_payload_invalid'); }
                $headers = @unserialize($email->headers, ['allowed_classes'=>false]);
                $attachments = $email->attachments ? @unserialize(base64_decode($email->attachments, true), ['allowed_classes'=>false]) : [];
                if (!is_array($headers) || !is_array($attachments)) { throw new DomainException('company_mail_payload_invalid'); }
                if ($email->engine === 'phpmailer' && (!isset($headers['from'],$headers['fromName'],$headers['subject'])
                    || !is_string($headers['from']) || !is_string($headers['fromName']) || !is_string($headers['subject']))) {
                    throw new DomainException('company_mail_payload_invalid');
                }
                require_once APPPATH.'services/CompanyPortalMailQueueConsumer.php';
                CompanyPortalMailQueueConsumer::assertQueueRouting($this->CI->db,db_prefix(),$email,$headers);
                require_once APPPATH.'services/CompanyGoogleMailOAuthDispatchSource.php';
                CompanyGoogleMailOAuthDispatchSource::assertQueueEnvelopeIfBound($this->CI->db,db_prefix(),$email,$headers);
                require_once APPPATH . 'services/CompanyExpenseRecurringDeliverySource.php';
                CompanyExpenseRecurringDeliverySource::assertQueueRouting($this->CI->db, db_prefix(), $email, $headers);
                $transport = $legacyConfig;
                if (isset($email->issuer_id)) {
                    require_once APPPATH . 'services/CompanyMailConfig.php';
                    if(array_key_exists(CompanyPortalRecoveryMail::HEADER,$headers)){
                        $portalKey=$headers[CompanyPortalRecoveryMail::HEADER];if(!is_string($portalKey))throw new DomainException('company_mail_payload_invalid');
                        $transport=CompanyPortalMailConfig::forQueue($this->CI->db,db_prefix(),$email,$portalKey,(string)$this->CI->config->item('encryption_key'),fn($v)=>$this->CI->encryption->decrypt($v),fn($v)=>$this->CI->encryption->encrypt($v));
                    }else{
                        $documentKind=array_key_exists('companyInvoiceDelivery',$headers)?'invoice':(array_key_exists('companySalesDelivery',$headers)?'sales':null);
                        $dispatchSource=null;
                        if($documentKind!==null){
                            require_once APPPATH.'services/CompanyGoogleMailOAuthDispatchSource.php';
                            $header=$documentKind==='invoice'?'companyInvoiceDelivery':'companySalesDelivery';
                            if(!is_string($headers[$header]))throw new DomainException('company_mail_payload_invalid');
                            $dispatchSource=new CompanyGoogleMailOAuthDispatchSource($this->CI->db,db_prefix(),$email,$documentKind,$headers[$header]);
                        }
                        $transport = CompanyMailConfig::build($this->CI->db, db_prefix(), $email->issuer_id,
                            fn($value) => $this->CI->encryption->decrypt($value),fn($value) => $this->CI->encryption->encrypt($value),$documentKind!==null?'document_notifications':'native',$dispatchSource);
                    }
                }
                if (isset($email->issuer_id) && $transport['useragent'] !== $email->engine) {
                    throw new DomainException('company_mail_engine_changed');
                }
                if (array_key_exists('companyInvoiceDelivery', $headers)) {
                    require_once APPPATH . 'services/CompanyInvoiceDelivery.php';
                    if (!is_string($headers[CompanyInvoiceDelivery::HEADER])) { throw new DomainException('company_mail_payload_invalid'); }
                    (new CompanyInvoiceDelivery($this->CI->db, db_prefix()))->assertDispatch($email, $headers[CompanyInvoiceDelivery::HEADER]);
                    require_once APPPATH . 'services/CompanyExpenseRecurringDeliverySource.php';
                    if (isset($headers[CompanyExpenseRecurringDeliverySource::HEADER]) && !is_string($headers[CompanyExpenseRecurringDeliverySource::HEADER])) { throw new DomainException('company_mail_payload_invalid'); }
                    CompanyExpenseRecurringDeliverySource::assertDispatchIfOwned($this->CI->db, db_prefix(), $email, $headers[CompanyInvoiceDelivery::HEADER], $headers[CompanyExpenseRecurringDeliverySource::HEADER] ?? null);
                    unset($headers[CompanyExpenseRecurringDeliverySource::HEADER]);
                    unset($headers[CompanyInvoiceDelivery::HEADER]);
                }
                if (array_key_exists('companySalesDelivery', $headers)) {
                    require_once APPPATH . 'services/CompanySalesDelivery.php';
                    if (!is_string($headers[CompanySalesDelivery::HEADER])) { throw new DomainException('company_mail_payload_invalid'); }
                    (new CompanySalesDelivery($this->CI->db, db_prefix()))->assertDispatch($email, $headers[CompanySalesDelivery::HEADER]);
                    unset($headers[CompanySalesDelivery::HEADER]);
                }
                if(array_key_exists(CompanyPortalRecoveryMail::HEADER,$headers)){
                    (new CompanyPortalMailQueueConsumer($this->CI->db,db_prefix(),(string)$this->CI->config->item('encryption_key')))->assertDispatch($email,$headers[CompanyPortalRecoveryMail::HEADER]);unset($headers[CompanyPortalRecoveryMail::HEADER]);
                }
                $this->reset_account_transport();
                $this->set_useragent($email->engine);
                $this->initialize($transport);
            } catch (Throwable $error) {
                $this->clear(true);
                $this->CI->db->where('id', $email->id)->where('status', 'sending')
                    ->update($this->email_queue_table, ['status'=>'failed','date'=>date('Y-m-d H:i:s')]);
                log_message('error', 'Queued email configuration unavailable; delivery deferred.');
                continue;
            }

            $recipients = explode(', ', $email->email);
            $cc         = !empty($email->cc) ? explode(', ', $email->cc) : [];

            $bcc     = !empty($email->bcc) ? explode(', ', $email->bcc) : [];

            if ($email->engine == 'codeigniter') {
                if ($email->attachments) {
                    foreach ($attachments as $attachment) {
                        $this->_attachments[] = $attachment;
                    }
                }
                $this->_headers = $headers;

                if (array_key_exists('Reply-To', $this->_headers) && !empty($this->_headers['Reply-To'])) {
                    $this->_replyto_flag = true;
                }
            } else {
                if ($email->attachments) {
                    foreach ($attachments as $attachment) {
                        $this->phpmailer->addStringAttachment($attachment[0], $attachment[1], 'base64', $attachment[4], $attachment[6]);
                    }
                }
                $this->from($headers['from'], $headers['fromName']);
                $this->subject($headers['subject']);
                if (isset($headers['replyTo'])) {
                    $replyTo = !empty($headers['replyTo']) ? explode(', ', $headers['replyTo']) : [];

                    foreach ($replyTo as $replyToEmail) {
                        $this->reply_to($replyToEmail);
                    }
                }
            }

            $this->set_newline(config_item('newline'));
            $this->set_crlf(config_item('crlf'));

            $this->to($recipients);
            $this->cc($cc);
            $this->bcc($bcc);

            $this->message($email->message);
            $this->set_alt_message($email->alt_message);

            $status = ($this->send(true) ? 'sent' : 'failed');
            $this->clear(true);

            if ($email->engine == 'codeigniter') {
                $this->_attachments = [];
            } else {
                $this->phpmailer->clearAttachments();
            }

            $this->clear(true);

            $this->CI->db->where('id', $email->id);
            $this->CI->db->set('status', $status);
            $this->CI->db->set('date', date('Y-m-d H:i:s'));
            $this->CI->db->update($this->email_queue_table);
        }
    }

    /**
     * Retry failed emails
     *
     * Resend failed or expired emails
     * @return void
     */
    public function retry_queue()
    {
        $this->scopeQueue();
        $expire      = (time() - (60 * 5));
        $date_expire = date('Y-m-d H:i:s', $expire);
        $this->CI->db->set('status', 'pending');
        $this->CI->db->group_start();
        $this->CI->db->where("(date < '{$date_expire}' AND status = 'sending')");
        $this->CI->db->or_where('status', 'failed');
        $this->CI->db->group_end();

        $this->CI->db->update($this->email_queue_table);
        log_message('debug', 'Email queue retrying...');
    }

    /**
     * Will remove queue rows from database that are sent and are older then 1 week
     * This function will remove also pending rows that are not sent for 3 days, probably the user cron job is not working properly or the email is not
     * working properly, removing these rows will help hundreds of stuck emails to be sent at once when user configure cron job or email.
     * @return null
     */
    public function clean_up_old_queue()
    {
        $this->scopeQueue();
        $this->CI->db->group_start();
        $this->CI->db->where('(status = "sent" AND date < ' . $this->CI->db->escape(date('Y-m-d H:i:s', strtotime('-1 week'))) . ')');
        if ($this->companyOrigin === null) {
            $this->CI->db->or_where('(status = "pending" AND date < ' . $this->CI->db->escape(date('Y-m-d H:i:s', strtotime('-3 day'))) . ')');
        }
        $this->CI->db->group_end()->delete($this->email_queue_table);
    }

    /**
     * Delete the given queued email
     *
     * @param  int $id
     *
     * @return void
     */
    public function delete_queued_email($id)
    {
        $this->scopeQueue();
        $this->CI->db->where('id', $id)->delete($this->email_queue_table);
    }

    /**
     * Deletes all queued email
     *
     * @return void
     */
    public function clear_queued_emails(): void
    {
        $this->scopeQueue();
        $this->CI->db->delete($this->email_queue_table);

    }
    private function scopeQueue(string $alias = ''): void
    {
        if (!$this->CI->db->field_exists('issuer_id', $this->email_queue_table)) {
            if ($this->companyOrigin !== null) { throw new DomainException('company_mail_queue_upgrade_required'); }
            return;
        }
        $this->CI->db->where($alias . 'issuer_id', $this->companyOrigin);
    }

}
