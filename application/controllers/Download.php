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
