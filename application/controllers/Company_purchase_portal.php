<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** Native supplier portal; no legacy vendor session, public attachment path or global purchase model. */
class Company_purchase_portal extends CI_Controller
{
    public function index()
    {
        if (!class_exists('CompanyPurchasesScope',false)||!CompanyPurchasesScope::activePortal()) { show_404(); return; }
        require_once APPPATH.'services/CompanyPurchases.php'; require_once APPPATH.'services/CompanyOperationFilesHttp.php';
        $nonce=base64_encode(random_bytes(18));
        $this->output->set_header('Cache-Control: no-store')->set_header('Referrer-Policy: no-referrer')->set_header('X-Content-Type-Options: nosniff')->set_header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'nonce-".$nonce."'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
        $csrf=$this->session->userdata('company_purchase_portal_csrf');
        if (!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)) { $csrf=bin2hex(random_bytes(32)); $this->session->set_userdata('company_purchase_portal_csrf',$csrf); }
        $identity=$this->session->userdata('company_purchase_portal_identity'); $signingKey=(string)$this->config->item('encryption_key'); $data=null; $files=[]; $error=null; $service=null; $who=null;
        try {
            $verb=$this->input->method(true);
            if (!in_array($verb,['GET','HEAD','POST'],true)) { show_error('Método não permitido.',405); return; }
            if ($verb==='POST') {
                $posted=$this->input->post('purchase_portal_csrf',false);
                if (!is_string($posted)||!hash_equals($csrf,$posted)) { show_error('Recarregue o formulário.',403); return; }
                $action=$this->input->post('action',false);
                if ($action==='enter') {
                    $secret=$this->input->post('invitation',false); if (!is_string($secret)) { throw new DomainException('company_purchase_portal_denied'); }
                    [$service,$who]=CompanyPurchases::forPortal($this->db,db_prefix(),$secret); $identity=$service->portalIdentity($signingKey);
                    $this->session->sess_regenerate(true); $this->session->set_userdata('company_purchase_portal_identity',$identity);
                    redirect($this->config->site_url('company_purchase_portal'),'location',303); return;
                }
                if ($action==='logout') { $this->session->unset_userdata('company_purchase_portal_identity'); $this->session->sess_regenerate(true); redirect($this->config->site_url('company_purchase_portal'),'location',303); return; }
                if (!is_array($identity)) { throw new DomainException('company_purchase_portal_denied'); }
                [$service,$who]=CompanyPurchases::forPortalSession($this->db,db_prefix(),$identity,$signingKey); $data=$service->portalView();
                if ($action==='download') { $file=$service->download($who['issuer_id'],$who['actor_id'],$this->input->post('file_id',false)); CompanyOperationFilesHttp::respond($this,$file); return; }
                if ($action==='order_pdf') { $pdf=$service->orderPdf($who['issuer_id'],$who['actor_id'],$this->input->post('order_id',false)); $this->output->set_header('Content-Disposition: attachment; filename="pedido.pdf"')->set_content_type('application/pdf')->set_output($pdf); return; }
                $key=$this->input->post('request_key',false); if (!is_string($key)) { throw new DomainException('company_purchase_invalid'); }
                if ($action==='quote') {
                    $input=$this->input->post('quote',false);
                    if (!is_array($input)) { throw new DomainException('company_purchase_invalid'); }
                    CompanyPurchasesValues::fields($input,['valid_until','unit_prices','tax_rate','note']);
                    $service->quote($who['issuer_id'],$who['actor_id'],['request_id'=>(int)$data['request']['id'],'supplier_id'=>$data['supplier']['id']]+$input,$key);
                } elseif ($action==='upload') {
                    [$name,$content]=CompanyOperationFilesHttp::upload();
                    $service->upload($who['issuer_id'],$who['actor_id'],(int)$data['request']['id'],$data['supplier']['id'],$name,$content,true,$key);
                } else { throw new DomainException('company_purchase_invalid'); }
                redirect($this->config->site_url('company_purchase_portal'),'location',303); return;
            }
            if (is_array($identity)) {
                [$service,$who]=CompanyPurchases::forPortalSession($this->db,db_prefix(),$identity,$signingKey); $data=$service->portalView(); $files=$service->files($who['issuer_id'],$who['actor_id'],(int)$data['request']['id']);
            }
        } catch (DomainException|InvalidArgumentException $e) {
            $denied=str_ends_with($e->getMessage(),'denied');
            if ($denied) { $this->session->unset_userdata('company_purchase_portal_identity'); $data=null; $files=[]; }
            $this->output->set_status_header($denied?403:(str_contains($e->getMessage(),'conflict')?409:422));
            $error=$denied?'O convite está indisponível, expirou ou foi revogado. Peça um novo convite à empresa.':'Confira validade, preços, taxa e anexo. O pedido pode ter sido fechado desde a abertura do formulário.';
        } catch (Throwable $e) {
            log_message('error','Company purchase portal unconfirmed; invitation and supplier data omitted.'); $this->output->set_status_header(503); $error='Não foi possível confirmar a operação. Reabra o portal para conferir o resultado.';
        }
        $this->load->view('companies/purchase_portal',['data'=>$data,'files'=>$files,'error'=>$error,'purchase_portal_csrf'=>$csrf,'nonce'=>$nonce]);
    }
}
