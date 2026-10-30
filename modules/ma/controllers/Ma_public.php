<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Ma_public extends ClientsController
{
    public function index($a)
    {
        show_404();
    }

    /**
     * email tracking open
     * @param  [type] $hash 
     * @return [type]       
     */
    public function images($hash = '')
    {
        http_response_code(204);
        exit;
    }

    /**
     * download asset
     * @param  [type] $folder_indicator [description]
     * @param  string $attachmentid     [description]
     * @return [type]                   [description]
     */
    public function download_file($folder_indicator, $attachmentid = '')
    {   
        $this->load->helper('download');
        $this->load->model('ma_model');

        $path = '';
        if ($folder_indicator == 'ma_asset') {
            $this->db->where('rel_id', $attachmentid);
            $this->db->where('rel_type', 'ma_asset');
            $file = $this->db->get(db_prefix() . 'files')->row();
            $path = MA_MODULE_UPLOAD_FOLDER . '/assets/' . $file->rel_id . '/' . $file->file_name;

            $this->ma_model->download_asset($attachmentid);
        }else {
            die('folder not specified');
        }

        force_download($path, null);
    }

    /**
     * email tracking click
     * @param  [type] $hash [description]
     * @return [type]       [description]
     */
    public function click($hash)
    {
        $url = (string) $this->input->get('href', false);
        $confirm = $this->input->get('confirm');

        // This compatibility route is used only by existing accept/decline email buttons.
        if ($confirm !== null && in_array((string) $confirm, ['0', '1'], true)) {
            $this->db->where('hash', $hash);
            $this->db->update(db_prefix() . 'ma_email_logs', ['confirm' => (string) $confirm]);
        }

        $parts = parse_url($url);
        if (!$parts || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            show_404();
        }

        redirect($url, 'location', 302);
    }

    /**
     * email tracking download asset
     * @param  string $hash
     * @return      
     */
    public function asset($hash)
    {   
        $this->db->where('hash', $hash);
        $asset_log = $this->db->get(db_prefix() . 'ma_asset_logs')->row();

        $path = '';
        if($asset_log){
            $this->load->helper('download');
            $this->load->model('ma_model');

            $this->db->where('hash', $hash);
            $this->db->where('rel_id', $asset_log->asset_id);
            $this->db->where('rel_type', 'ma_asset');
            $file = $this->db->get(db_prefix() . 'files')->row();
            $path = MA_MODULE_UPLOAD_FOLDER . '/assets/' . $file->rel_id . '/' . $file->file_name;

        }else {
            die('folder not specified');
        }

        force_download($path, null);
    }

    /**
     * email unsubscribe
     * @param  [type] $hash [description]
     * @return [type]       [description]
     */
    public function unsubscribe($hash)
    {
        $data = [];

        $this->db->where('hash', $hash);
        $email_log = $this->db->get(db_prefix() . 'ma_email_logs')->row();

        if($email_log){
            if ($email_log->lead_id) {
                $this->db->where('id', $email_log->lead_id);
                $this->db->update(db_prefix() . 'leads', ['ma_unsubscribed' => 1]);
            }else{
                $this->db->where('userid', $email_log->client_id);
                $this->db->update(db_prefix() . 'clients', ['ma_unsubscribed' => 1]);
            }
        }

        $this->load->view('unsubscribe', $data);
    }
}