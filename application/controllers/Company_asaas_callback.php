<?php

defined('BASEPATH') or exit('No direct script access allowed');
require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyService.php';

/** Public inbox. No staff scope, gateway hooks, API lookup or financial mutation. */
class Company_asaas_callback extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        if(!class_exists('CompanyAsaasCallbackScope',false)||!CompanyAsaasCallbackScope::active()){show_error('Webhook indisponível.',503);return;}
    }
    public function integration($publicId='')
    {
        $this->output->set_content_type('text/plain','utf-8')->set_header('Cache-Control: no-store')->set_header('X-Content-Type-Options: nosniff');
        if($this->input->method(true)!=='POST'){$this->output->set_status_header(405)->set_header('Allow: POST')->set_output('Método não permitido.');return;}
        try{$body=file_get_contents('php://input',false,null,0,1048577);if(!is_string($body))throw new RuntimeException();$result=(new \Asaas\CompanyService($this->db,db_prefix()))->receive((string)$publicId,$this->input->request_headers(),$body);$status=(int)$result['status'];$this->output->set_status_header($status)->set_output($status===200?'OK':'Evento não aceito.');}
        catch(Throwable $e){$this->output->set_status_header(503)->set_output('Falha temporária.');}
    }
}
