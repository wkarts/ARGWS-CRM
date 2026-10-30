<?php
defined('BASEPATH') OR exit('No direct script access allowed');


class Clients extends ClientsController {


    public function __construct()
    {

        parent::__construct();

    }


    /**
     * Setting up new session information is in progress.
     */
    public function change_auth($clientID = 0)
    {
        if (strtoupper($this->input->method()) !== 'POST') { show_error('Método não permitido.',405); return; }
        if (!is_client_logged_in()) { show_404(); return; }
        if ((!is_string($clientID)&&!is_int($clientID)) || !preg_match('/^[1-9][0-9]*$/D',(string)$clientID)) { show_404(); return; }
        require_once dirname(__DIR__).'/services/LegacyContactIdentity.php';
        $targets=MultipleCompaniesLegacyContactIdentity::switchTargets($this->db,db_prefix(),get_contact_user_id(),get_client_user_id());
        $matches=array_values(array_filter($targets,static fn($row)=>(string)$row['userid']===(string)$clientID));
        // Ambiguous duplicate contacts require explicit reconciliation, not first-row selection.
        if (count($matches)!==1) { show_404(); return; }
        $this->session->set_userdata(['client_user_id'=>(int)$matches[0]['userid'],'contact_user_id'=>(int)$matches[0]['id']]);
        $this->session->unset_userdata('all_clients');
        if (method_exists($this->session, 'sess_regenerate')) $this->session->sess_regenerate(true);
        unset($GLOBALS['contact'],$GLOBALS['client']);
        if (isset($this->app_object_cache)) $this->app_object_cache->flush();
        redirect(site_url());
    }
}
