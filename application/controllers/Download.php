<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Download extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('download');
    }

    public function preview_video()
    {
        $path      = FCPATH . $this->input->get('path');
        $file_type = $this->input->get('type');
        if ($this->nativeInvoicePreview($path, true)) { return; }
        $this->assert_legacy_preview_path($path);

        $allowed_extensions = get_html5_video_extensions();

        $pathinfo = pathinfo($path);

        if (! file_exists($path) || ! isset($pathinfo['extension']) || ! in_array($pathinfo['extension'], $allowed_extensions)) {
            $file_type = 'image/jpg';
            $path      = FCPATH . 'assets/images/preview-not-available.jpg';
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Type: ' . $file_type);
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        if (ob_get_contents()) {
            ob_end_clean();
        }

        hooks()->do_action('before_output_preview_video');

        $file = fopen($path, 'rb');
        if ($file !== false) {
            while (! feof($file)) {
                echo fread($file, 1024);
            }
            fclose($file);
        }
    }

    public function preview_image()
    {
        $path      = FCPATH . $this->input->get('path');
        $file_type = $this->input->get('type');
        if ($this->nativeInvoicePreview($path, false)) { return; }
        $this->assert_legacy_preview_path($path);

        $allowed_extensions = [
            'jpg',
            'jpeg',
            'png',
            'bmp',
            'gif',
            'tif',
        ];

        $pathinfo = pathinfo($path);

        if (! file_exists($path) || ! isset($pathinfo['extension']) || ! in_array($pathinfo['extension'], $allowed_extensions)) {
            $file_type = 'image/jpg';
            $path      = FCPATH . 'assets/images/preview-not-available.jpg';
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Type: ' . $file_type);
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        if (ob_get_contents()) {
            ob_end_clean();
        }

        hooks()->do_action('before_output_preview_image');
        $file = fopen($path, 'rb');
        if ($file !== false) {
            while (! feof($file)) {
                echo fread($file, 1024);
            }
            fclose($file);
        }
    }

    public function file($folder_indicator, $attachmentid = '')
    {
        if ($folder_indicator === 'ticket' && class_exists('CompanyRequestScope', false)
            && method_exists('CompanyRequestScope', 'nativeFor') && CompanyRequestScope::nativeFor($this)) {
            $ctx = CompanyRequestScope::context();
            if (!$ctx || !in_array($this->input->method(true), ['GET', 'HEAD'], true)) { show_404(); return; }
            try {
                $ticket = $this->input->get('ticket_id', false);
                if ((!is_string($ticket) && !is_int($ticket)) || !preg_match('/^[1-9][0-9]{0,15}$/D', (string) $ticket)
                    || !is_string($attachmentid) || !preg_match('/^[a-f0-9]{64}$/D', $attachmentid)) { throw new DomainException('native_ticket_file_denied'); }
                require_once APPPATH . 'services/NativeCompanyTicketFiles.php';
                $file = (new NativeCompanyTicketFiles($this->db, db_prefix()))->read($ctx->issuerId(), $ctx->staffId(), (int) $ticket, $attachmentid);
                $this->output->set_header('Cache-Control: private, no-store')
                    ->set_header('X-Content-Type-Options: nosniff')
                    ->set_header("Content-Security-Policy: default-src 'none'; sandbox")
                    ->set_header('Content-Disposition: attachment; filename="download"; filename*=UTF-8\'\'' . rawurlencode($file['filename']))
                    ->set_content_type($file['mime'])
                    ->set_output($this->input->method(true) === 'HEAD' ? '' : $file['content']);
            } catch (Throwable $error) { show_404(); }
            return;
        }

        if($folder_indicator==='client'&&class_exists('CompanyRequestScope',false)&&method_exists('CompanyRequestScope','nativeFor')&&CompanyRequestScope::nativeFor($this)&&CompanyRequestScope::context()&&!CompanyRequestScope::context()->isPrimary()){
            if(!in_array($this->input->method(true),['GET','HEAD'],true)){show_404();return;}
            try{require_once APPPATH.'services/NativeCompanyCustomerFiles.php';$ctx=CompanyRequestScope::context();$file=(new NativeCompanyCustomerFiles($this->db,db_prefix()))->readKey($ctx->issuerId(),$ctx->staffId(),(string)$attachmentid);$inline=$this->input->get('inline')==='1'&&$file['mime']==='image/png';$this->output->set_header('Cache-Control: private, no-store')->set_header('X-Content-Type-Options: nosniff')->set_header("Content-Security-Policy: default-src 'none'; sandbox")->set_header('Content-Disposition: '.($inline?'inline':'attachment')."; filename=\"download\"; filename*=UTF-8''".rawurlencode($file['filename']))->set_content_type($file['mime'])->set_output($this->input->method(true)==='HEAD'?'':$file['content']);}catch(Throwable$error){show_404();}return;
        }

        if ($folder_indicator === 'sales_attachment' && class_exists('CompanyRequestScope', false)
            && method_exists('CompanyRequestScope', 'nativeFor') && CompanyRequestScope::nativeFor($this)) {
            $ctx = CompanyRequestScope::context();
            if (!$ctx || !in_array($this->input->method(true), ['GET', 'HEAD'], true)) { show_404(); return; }
            try {
                require_once APPPATH . 'services/NativeCompanyInvoiceFiles.php';
                $file = (new NativeCompanyInvoiceFiles($this->db, db_prefix()))->readKey($ctx->issuerId(), $ctx->staffId(), (string) $attachmentid);
                $this->output->set_header('Cache-Control: private, no-store')
                    ->set_header('X-Content-Type-Options: nosniff')
                    ->set_header("Content-Security-Policy: default-src 'none'; sandbox")
                    ->set_header('Content-Disposition: attachment; filename="download"; filename*=UTF-8\'\'' . rawurlencode($file['filename']))
                    ->set_content_type($file['mime'])
                    ->set_output($this->input->method(true) === 'HEAD' ? '' : $file['content']);
            } catch (Throwable $error) { show_404(); }
            return;
        }

        $this->load->model('tickets_model');
        if ($folder_indicator == 'ticket') {
            $ticket_key = $this->input->get('ticket_key');
            if (! is_logged_in() && ! $ticket_key) {
                show_404();
            }

            $this->db->where('id', $attachmentid);
            $attachment = $this->db->get(db_prefix() . 'ticket_attachments')->row();

            if (! $attachment) {
                show_404();
            }

            if (!\app\services\CustomerRelationBoundary::permitsRecord($this->db, db_prefix(), 'tickets', $attachment->ticketid)) { show_404(); }
            $ticket   = $this->tickets_model->get_ticket_by_id($attachment->ticketid);
            if (!$ticket) { show_404(); }
            $ticketid = $attachment->ticketid;

            if ($ticket->ticketkey == $ticket_key || $ticket->userid == get_client_user_id() || is_staff_logged_in()) {
                $path = get_upload_path_by_type('ticket') . $ticketid . '/' . $attachment->file_name;
            }
        } elseif ($folder_indicator == 'newsfeed') {
            if (is_staff_logged_in()) {
                if (! $attachmentid) {
                    show_404();
                }
                $this->db->where('id', $attachmentid);
                $attachment = $this->db->get(db_prefix() . 'files')->row();
                if (! $attachment) {
                    show_404();
                }
                $path = get_upload_path_by_type('newsfeed') . $attachment->rel_id . '/' . $attachment->file_name;
            }
        } elseif ($folder_indicator == 'contract') {
            if (! $attachmentid) {
                show_404();
            }

            $this->db->where('attachment_key', $attachmentid);
            $attachment = $this->db->get(db_prefix() . 'files')->row();
            if (! $attachment || $attachment->rel_type !== 'contract' || !\app\services\ClientIssuerScope::isLegacyDocumentClient($this->db, db_prefix(), 'contracts', $attachment->rel_id)) {
                show_404();
            }

            if (! is_staff_logged_in()) {
                $this->db->select('not_visible_to_client');
                $this->db->where('id', $attachment->rel_id);
                $contract = $this->db->get(db_prefix() . 'contracts')->row();
                if (!$contract || $contract->not_visible_to_client == 1) {
                    show_404();
                }
            }

            $path = get_upload_path_by_type('contract') . $attachment->rel_id . '/' . $attachment->file_name;
        } elseif ($folder_indicator == 'taskattachment') {
            if (! is_logged_in()) {
                show_404();
            }

            $this->db->where('attachment_key', $attachmentid);
            $attachment = $this->db->get(db_prefix() . 'files')->row();

            if (! $attachment) {
                show_404();
            }
            if ($attachment->rel_type !== 'task' || !\app\services\TaskClientBoundary::permitsTask($this->db, db_prefix(), $attachment->rel_id)) { show_404(); }
            $path = get_upload_path_by_type('task') . $attachment->rel_id . '/' . $attachment->file_name;
        } elseif ($folder_indicator == 'sales_attachment') {
            if (! is_staff_logged_in()) {
                $this->db->where('visible_to_customer', 1);
            }

            $this->db->where('attachment_key', $attachmentid);
            $attachment = $this->db->get(db_prefix() . 'files')->row();
            // This endpoint only serves sales documents. Customer/profile/task keys
            // must use their own authorization path, even when marked visible.
            if (!$attachment || !in_array($attachment->rel_type, ['invoice', 'estimate', 'proposal', 'credit_note', 'debit_note'], true)) {
                show_404();
            }

            if ($attachment->rel_type === 'invoice' && $this->nativeInvoiceProjectionPresent()) { show_404(); return; } // Owned projections require the explicit staff/portal branch, never this legacy shortcut.
            if ($attachment->rel_type === 'invoice' && !\app\services\InvoiceIssuerScope::isLegacyInvoice($this->db, db_prefix() . 'invoices', $attachment->rel_id)) { show_404(); }
            $path = get_upload_path_by_type($attachment->rel_type) . $attachment->rel_id . '/' . $attachment->file_name;
        } elseif ($folder_indicator == 'expense') {
            if (! is_staff_logged_in()) {
                show_404();
            }
            $this->db->where('rel_id', $attachmentid);
            $this->db->where('rel_type', 'expense');
            $file = $this->db->get(db_prefix() . 'files')->row();
            if (!$file || !\app\services\CustomerRelationBoundary::permitsRecord($this->db, db_prefix(), 'expenses', $file->rel_id)) { show_404(); }
            $path = get_upload_path_by_type('expense') . $file->rel_id . '/' . $file->file_name;
            // l_attachment_key is if request is coming from public form
        } elseif ($folder_indicator == 'lead_attachment' || $folder_indicator == 'l_attachment_key') {
            if (! is_staff_logged_in() && strpos($_SERVER['HTTP_REFERER'], 'forms/l/') === false) {
                show_404();
            }

            // admin area
            if ($folder_indicator == 'lead_attachment') {
                $this->db->where('id', $attachmentid);
            } else {
                // Lead public form
                $this->db->where('attachment_key', $attachmentid);
            }

            $attachment = $this->db->get(db_prefix() . 'files')->row();

            if (! $attachment) {
                show_404();
            }

            $path = get_upload_path_by_type('lead') . $attachment->rel_id . '/' . $attachment->file_name;
        } elseif ($folder_indicator == 'client') {
            \app\services\ClientIssuerScope::applyLegacy($this->db, db_prefix(), db_prefix() . 'files', 'rel_id');
            $this->db->where('rel_type', 'customer');
            $this->db->where('attachment_key', $attachmentid);
            $attachment = $this->db->get(db_prefix() . 'files')->row();
            if (! $attachment) {
                show_404();
            }
            $contactCanDownload = is_client_logged_in()
                && (int) $attachment->rel_id === (int) get_client_user_id()
                && (int) $attachment->visible_to_customer === 1
                && total_rows(db_prefix() . 'shared_customer_files', ['file_id' => $attachment->id, 'contact_id' => get_contact_user_id()]) > 0;
            if (staff_can('view', 'customers') || is_customer_admin($attachment->rel_id) || $contactCanDownload) {
                $path = get_upload_path_by_type('customer') . $attachment->rel_id . '/' . $attachment->file_name;
            } else {
                show_404();
                return;
            }
        } elseif ($folder_indicator == 'estimate_request_attachment') {
            if (! is_staff_logged_in() && strpos($_SERVER['HTTP_REFERER'], 'forms/l/') === false) {
                show_404();
            }

            // admin area
            if ($folder_indicator == 'estimate_request_attachment') {
                $this->db->where('id', $attachmentid);
            } else {
                // Lead public form
                $this->db->where('attachment_key', $attachmentid);
            }

            $attachment = $this->db->get(db_prefix() . 'files')->row();

            if (! $attachment) {
                show_404();
            }

            $path = get_upload_path_by_type('estimate_request') . $attachment->rel_id . '/' . $attachment->file_name;
        } else {
            exit('folder not specified');
        }

        $path = hooks()->apply_filters('download_file_path', $path, [
            'folder'       => $folder_indicator,
            'attachmentid' => $attachmentid,
        ]);

        force_download($path, null);
    }
    private function nativeInvoiceProjectionPresent(): bool
    {
        require_once APPPATH . 'services/issuers/IssuerStorage.php';
        $pdo = $this->db->pdo ?? $this->db->conn_id ?? null;
        $sql = $pdo instanceof PDO && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? "SELECT name FROM sqlite_master WHERE type='table' AND name=?"
            : 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?';
        return (bool) IssuerStorage::query($this->db, $sql, [db_prefix().'company_invoice_file_projection'])->result_array();
    }

    /** Original preview URL, but the supplied path only selects an exact native file record. */
    private function nativeInvoicePreview(string $path, bool $video): bool
    {
        $root = realpath(defined('INVOICE_ATTACHMENTS_FOLDER') ? INVOICE_ATTACHMENTS_FOLDER : get_upload_path_by_type('invoice'));
        $resolved = realpath($path);
        if (!$root || !$resolved || !str_starts_with($resolved, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) { return false; }
        if (!$this->nativeInvoiceProjectionPresent()) { return false; }
        try {
            $relative = substr($resolved, strlen(rtrim($root, DIRECTORY_SEPARATOR))+1);
            $parts = explode(DIRECTORY_SEPARATOR, $relative);
            if (count($parts)!==2 || !preg_match('/^[1-9][0-9]*$/D', $parts[0])
                || !in_array($this->input->method(true), ['GET','HEAD'], true)
                || !class_exists('CompanyRequestScope', false) || !method_exists('CompanyRequestScope','nativeFor')
                || !CompanyRequestScope::nativeFor($this) || !($ctx=CompanyRequestScope::context())) { throw new DomainException('native_invoice_file_denied'); }
            $rows=IssuerStorage::query($this->db, 'SELECT f.attachment_key FROM '.db_prefix().'files f JOIN '.db_prefix().'company_invoice_file_projection s ON s.file_id=f.id WHERE f.rel_type=? AND f.rel_id=? AND f.file_name=? AND s.issuer_id=? AND s.active=1', ['invoice',$parts[0],$parts[1],$ctx->issuerId()])->result_array();
            if(count($rows)!==1) { throw new DomainException('native_invoice_file_denied'); }
            require_once APPPATH.'services/NativeCompanyInvoiceFiles.php';
            $file=(new NativeCompanyInvoiceFiles($this->db,db_prefix()))->readKey($ctx->issuerId(),$ctx->staffId(),$rows[0]['attachment_key']);
            $allowed=$video?['video/mp4','video/x-m4v','video/webm','video/ogg','video/x-flv']:['image/png','image/jpeg','image/gif','image/bmp','image/tiff'];
            if(!in_array($file['mime'],$allowed,true)) { throw new DomainException('native_invoice_file_denied'); }
            $this->output->set_header('Cache-Control: private, no-store')->set_header('X-Content-Type-Options: nosniff')
                ->set_header("Content-Security-Policy: default-src 'none'; sandbox")->set_content_type($file['mime'])
                ->set_output($this->input->method(true)==='HEAD'?'':$file['content']);
            return true;
        } catch (Throwable $error) { show_404(); return true; }
    }

    private function assert_legacy_preview_path(string $path): void
    {
        $resolved = realpath($path);
        if ($resolved === false) { return; } // Existing missing-file placeholder behavior.
        $root = realpath(FCPATH);
        if (!$root || !str_starts_with($resolved, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) { show_404(); }
        $invoiceRoot = realpath(get_upload_path_by_type('invoice'));
        if ($invoiceRoot && str_starts_with($resolved, rtrim($invoiceRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            $relative = substr($resolved, strlen(rtrim($invoiceRoot, DIRECTORY_SEPARATOR)) + 1);
            $invoiceId = explode(DIRECTORY_SEPARATOR, $relative)[0];
            if (!\app\services\InvoiceIssuerScope::isLegacyInvoice($this->db, db_prefix() . 'invoices', $invoiceId)) { show_404(); }
        }
        foreach (['project'=>'projects', 'expense'=>'expenses', 'ticket'=>'tickets', 'task'=>'tasks', 'contract'=>'contracts'] as $kind=>$table) {
            $uploadRoot = realpath(get_upload_path_by_type($kind));
            if (!$uploadRoot || !str_starts_with($resolved, rtrim($uploadRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) continue;
            $relative = substr($resolved, strlen(rtrim($uploadRoot, DIRECTORY_SEPARATOR))+1);
            $recordId = explode(DIRECTORY_SEPARATOR, $relative)[0];
            $allowed = $kind === 'task'
                ? \app\services\TaskClientBoundary::permitsTask($this->db, db_prefix(), $recordId)
                : \app\services\CustomerRelationBoundary::permitsRecord($this->db, db_prefix(), $table, $recordId);
            if (!$allowed) { show_404(); }
        }
        $customerRoot = realpath(get_upload_path_by_type('customer'));
        if ($customerRoot && str_starts_with($resolved, rtrim($customerRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            $relative = substr($resolved, strlen(rtrim($customerRoot, DIRECTORY_SEPARATOR)) + 1);
            $parts = explode(DIRECTORY_SEPARATOR, $relative);
            if (count($parts) !== 2 || !\app\services\ClientIssuerScope::isLegacyClient($this->db, db_prefix(), $parts[0])) { show_404(); }
            $files = $this->db->query("SELECT id,file_name,visible_to_customer FROM " . db_prefix() . "files WHERE rel_type='customer' AND rel_id=?", [$parts[0]]);
            if ($files === false) { show_404(); }
            $attachment = null;
            foreach ($files->result_array() as $file) {
                $thumbnail = pathinfo($file['file_name'], PATHINFO_FILENAME) . '_thumb.' . pathinfo($file['file_name'], PATHINFO_EXTENSION);
                if ($parts[1] === $file['file_name'] || $parts[1] === $thumbnail) { $attachment = (object) $file; break; }
            }
            if (!$attachment) { show_404(); }
            $staffAllowed = is_staff_logged_in() && (staff_can('view', 'customers') || is_customer_admin($parts[0]));
            $contactAllowed = is_client_logged_in() && (int) $parts[0] === (int) get_client_user_id()
                && (int) $attachment->visible_to_customer === 1
                && total_rows(db_prefix() . 'shared_customer_files', ['file_id'=>$attachment->id, 'contact_id'=>get_contact_user_id()]) > 0;
            if (!$staffAllowed && !$contactAllowed) { show_404(); }
        }
    }
}
