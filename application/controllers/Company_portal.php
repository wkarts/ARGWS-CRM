<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Public portal identity/recovery; deliberately avoids the legacy theme, models and module hydration. */
class Company_portal extends CI_Controller
{
    public function index($issuer=null)
    {
        if(!PortalRequestScope::active()){show_404();return;}
        require_once APPPATH.'services/CompanyPortal.php';
        $this->output->set_header('Cache-Control: no-store');
        $this->output->set_header('Referrer-Policy: no-referrer');
        $this->output->set_header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'nonce-".($nonce=base64_encode(random_bytes(18)))."'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
        $this->output->set_header('X-Content-Type-Options: nosniff');
        try{$issuer=IssuerPolicy::id($issuer);$portal=new CompanyPortal($this->db,db_prefix(),(string)$this->config->item('encryption_key'));}
        catch(Throwable $error){show_404();return;}
        $invoices=null;$sales=[];
        $sessionKey='company_portal_identity';$identity=$this->session->userdata($sessionKey);$message=null;$error=null;
        $csrf=$this->session->userdata('company_portal_csrf');
        if(!is_string($csrf)||strlen($csrf)!==64){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_portal_csrf',$csrf);}
        try{
            $current=is_array($identity)?$portal->sessionIdentity($identity,$issuer):null;
            if(!$current&&is_array($identity)&&($identity['issuer_id']??null)===$issuer)$this->session->unset_userdata($sessionKey);
            if($this->input->method(true)==='POST'){
                $posted=$this->input->post('portal_csrf',false);
                if(!is_string($posted)||!hash_equals($csrf,$posted)){show_error('Recarregue o formulário antes de continuar.',403);return;}
                $action=$this->input->post('action',false);
                if($action==='logout'){
                    $this->session->unset_userdata($sessionKey);$this->session->sess_regenerate(true);
                    redirect($this->config->site_url('company_portal/index/'.$issuer),'location',303);return;
                }
                if($action==='invoice_attachment'){
                    if(!$current||!is_array($identity)){show_404();return;}
                    require_once APPPATH.'services/CompanyPortalInvoices.php';require_once APPPATH.'services/CompanyInvoiceAttachments.php';
                    $invoice=$this->input->post('invoice_id',false);$key=$this->input->post('file_key',false);if(!is_string($key)){show_404();return;}
                    try{$file=(new CompanyInvoiceAttachments($this->db,db_prefix()))->portalDownload(new CompanyPortalInvoices($this->db,db_prefix(),$portal),$identity,$issuer,$invoice,$key);}catch(DomainException $denied){show_404();return;}
                    $this->output->set_header("Content-Security-Policy: sandbox; default-src 'none'");
                    $this->output->set_header('Content-Disposition: attachment; filename="anexo.'.($file['mime']==='image/png'?'png':'pdf').'"; filename*=UTF-8\'\''.rawurlencode($file['filename']));$this->output->set_content_type($file['mime'])->set_output($file['content']);return;
                }
                if($action==='sales_file'){
                    if(!$current||!is_array($identity)){show_404();return;}require_once APPPATH.'services/CompanySalesExtrasHttp.php';
                    $id=$this->input->post('document_id',false);$key=$this->input->post('file_key',false);if(!is_string($key)){show_404();return;}
                    try{$file=(new CompanySalesFiles($this->db,db_prefix()))->portalDownload($portal,$identity,$issuer,$id,$key);}catch(DomainException $denied){show_404();return;}CompanySalesExtrasHttp::output($this,$file);return;
                }
                if($action==='sales_pdf'){
                    if(!$current||!is_array($identity)){show_404();return;}
                    require_once APPPATH.'services/CompanyPortalSales.php';require_once APPPATH.'services/CompanySalesPdf.php';
                    $documents=new CompanyPortalSales($this->db,db_prefix(),$portal);$id=$this->input->post('document_id',false);
                    try{$document=$documents->document($identity,$issuer,$id);if(!class_exists('TCPDF'))require_once APPPATH.'vendor/tecnickcom/tcpdf/tcpdf.php';$pdf=CompanySalesPdf::prepare($document)->Output('','S');$documents->confirmDownload($identity,$issuer,$id,hash('sha256',$pdf));}catch(DomainException $denied){show_404();return;}
                    $this->output->set_header('Content-Disposition: attachment; filename="documento-'.(int)$id.'.pdf"');$this->output->set_content_type('application/pdf')->set_output($pdf);return;
                }
                if($action==='invoice_pdf'){
                    if(!$current||!is_array($identity)){show_404();return;}
                    require_once APPPATH.'services/CompanyPortalInvoices.php';
                    $documents=new CompanyPortalInvoices($this->db,db_prefix(),$portal);$invoice=$this->input->post('invoice_id',false);
                    try{
                        $dto=$documents->document($identity,$issuer,$invoice);
                        if(!class_exists('TCPDF'))require_once APPPATH.'vendor/tecnickcom/tcpdf/tcpdf.php';
                        require_once APPPATH.'libraries/pdf/Issuer_invoice_pdf.php';
                        $pdf=(new Issuer_invoice_pdf($dto))->prepare()->Output('','S');
                        $documents->confirmDownload($identity,$issuer,$invoice,hash('sha256',$pdf));
                    }catch(DomainException $denied){show_404();return;}
                    $this->output->set_header('Content-Disposition: attachment; filename="fatura-'.(int)$invoice.'.pdf"');
                    $this->output->set_content_type('application/pdf')->set_output($pdf);return;
                }
                if(!in_array($action,['login','recovery','reset'],true))throw new DomainException('company_portal_invalid_input');
                $allowed=$portal->allowAttempt($issuer,$this->input->ip_address(),$action);
                $email=$this->input->post('email',false);$password=$this->input->post('password',false);
                if($action==='recovery'){
                    if($allowed&&is_string($email)){
                        $this->load->library('encryption');
                        $portal->requestRecovery($issuer,$email,$this->config->base_url(),fn($value)=>$this->encryption->decrypt($value));
                    }
                    $message='Se houver uma conta ativa nesta empresa e o envio estiver disponível, você receberá um link. Aguarde antes de solicitar novamente.';
                }elseif($action==='login'){
                    require_once APPPATH.'third_party/phpass.php';
                    $hasher=new PasswordHash(PHPASS_HASH_STRENGTH,PHPASS_HASH_PORTABLE);
                    $authenticated=$allowed&&is_string($email)&&is_string($password)?$portal->login($issuer,$email,$password,static fn($value,$hash)=>$hasher->CheckPassword($value,$hash)):null;
                    if($authenticated){
                        $this->session->sess_regenerate(true);$this->session->set_userdata($sessionKey,$authenticated);
                        $this->session->set_userdata('company_portal_csrf',bin2hex(random_bytes(32)));
                        redirect($this->config->site_url('company_portal/index/'.$issuer),'location',303);return;
                    }
                    $this->output->set_status_header(422);$error='Não foi possível entrar. Confira a empresa, o e-mail e a senha, ou aguarde antes de tentar novamente.';
                }else{
                    $token=$this->input->post('token',false);$repeat=$this->input->post('password_repeat',false);
                    require_once APPPATH.'third_party/phpass.php';
                    $hasher=new PasswordHash(PHPASS_HASH_STRENGTH,PHPASS_HASH_PORTABLE);
                    if($allowed&&is_string($password)&&$password===$repeat&&is_string($token)&&$portal->reset($issuer,$token,$password,static fn($value)=>$hasher->HashPassword($value))){
                        $this->session->unset_userdata($sessionKey);$this->session->sess_regenerate(true);$current=null;
                        $message='Senha alterada. Entre novamente nesta empresa.';
                    }else{$this->output->set_status_header(422);$error='Não foi possível alterar a senha. Use um link válido e uma senha de pelo menos 12 caracteres. Se necessário, solicite outro link.';}
                }
                // POST/refresh never echoes a bearer or password into HTML.
            }elseif(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
        }catch(Throwable $exception){
            log_message('error','Company portal operation unavailable; no identity, password or token logged.');
            // Public recovery has the same response for an unknown account and unavailable mail config.
            if($this->input->post('action',false)==='recovery')$message='Se houver uma conta ativa nesta empresa e o envio estiver disponível, você receberá um link. Aguarde antes de solicitar novamente.';
            else{$this->output->set_status_header(503);$error='Serviço indisponível. Tente novamente mais tarde.';}
        }
        if(($current??null)&&is_array($identity)){
            try{require_once APPPATH.'services/CompanyPortalInvoices.php';$invoices=(new CompanyPortalInvoices($this->db,db_prefix(),$portal))->page($identity,$issuer,$this->input->get('before',false)??0);}
            catch(Throwable $unavailable){$error='Faturas indisponíveis no momento. Tente novamente mais tarde.';}
            try{require_once APPPATH.'services/CompanyPortalSales.php';$salesReader=new CompanyPortalSales($this->db,db_prefix(),$portal);foreach(['estimates','proposals'] as $kind)$sales[$kind]=$salesReader->page($identity,$issuer,$kind);$ids=[];foreach($sales as $rows)foreach($rows??[] as $row)$ids[]=$row['id'];if($ids){require_once APPPATH.'services/CompanySalesFiles.php';$published=(new CompanySalesFiles($this->db,db_prefix()))->portalPages($portal,$identity,$issuer,$ids);foreach($sales as &$rows)if(is_array($rows))foreach($rows as &$row)$row['files']=$published[(int)$row['id']]??[];unset($rows,$row);}}
            catch(Throwable $unavailable){$error='Documentos indisponíveis no momento. Tente novamente mais tarde.';}

        }
        $this->load->view('company_portal/index',['sales'=>$sales,'invoices'=>$invoices,'issuer'=>$issuer,'identity'=>$current??null,'message'=>$message,'error'=>$error,'portal_csrf'=>$csrf,'script_nonce'=>$nonce]);
    }
}
