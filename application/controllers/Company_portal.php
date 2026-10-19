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
        $invoices=null;$sales=[];$hostedCheckout=[];$operations=[];$operation_detail=null;$operation_kind=null;
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
                if($action==='support_open'||$action==='support_reply'){
                    if(!$current||!is_array($identity)){show_404();return;}require_once APPPATH.'services/CompanyPortalSupportWrite.php';$support=new CompanyPortalSupportWrite($this->db,db_prefix(),$portal);$key=$this->input->post('request_key',false);
                    try{if(!is_string($key))throw new DomainException('company_portal_support_invalid');if($action==='support_open'){$fields=$this->input->post('support',false);if(!is_array($fields))throw new DomainException('company_portal_support_invalid');$id=$support->open($identity,$issuer,$fields,$key);}else{$body=$this->input->post('reply',false);if(!is_string($body))throw new DomainException('company_portal_support_invalid');$id=$support->reply($identity,$issuer,$this->input->post('resource_id',false),$this->input->post('revision',false),$body,$key);}}
                    catch(DomainException $denied){$reason=$denied->getMessage();if($reason==='company_portal_operation_unavailable'){show_404();return;}$status=match($reason){'company_portal_support_stale','company_portal_support_request_conflict','company_portal_support_closed'=>409,'company_portal_support_limit'=>429,default=>422};show_error($status===409?'O chamado mudou ou foi encerrado. Reabra antes de enviar.':($status===429?'Limite de mensagens ou chamados atingido. Tente mais tarde.':'Confira o assunto, a mensagem e a prioridade antes de enviar.'),$status);return;}
                    redirect($this->config->site_url('company_portal/index/'.$issuer).'?operation_kind=tickets&resource_id='.(int)$id,'location',303);return;
                }
                if($action==='operation_file'||$action==='operation_contract_pdf'){
                    if(!$current||!is_array($identity)){show_404();return;}require_once APPPATH.'services/CompanyPortalOperations.php';$reader=new CompanyPortalOperations($this->db,db_prefix(),$portal);$id=$this->input->post('resource_id',false);
                    if($action==='operation_file'){$kind=$this->input->post('operation_kind',false);$fileKey=$this->input->post('file_key',false);if(!is_string($kind)||!is_string($fileKey)){show_404();return;}try{$file=$reader->file($identity,$issuer,$kind,$id,$fileKey);}catch(DomainException $denied){show_404();return;}$this->output->set_header("Content-Security-Policy: sandbox; default-src 'none'");$this->output->set_header('Content-Disposition: attachment; filename="anexo.'.($file['mime']==='image/png'?'png':'pdf').'"; filename*=UTF-8\'\''.rawurlencode($file['filename']));$this->output->set_content_type($file['mime'])->set_output($file['content']);return;}
                    try{$record=$reader->detail($identity,$issuer,'contracts',$id);require_once APPPATH.'services/CompanyContractsPdf.php';$pdf=CompanyContractsPdf::render($record);$reader->confirmPdf($identity,$issuer,$id,$record['revision'],hash('sha256',$record['snapshot_json']),hash('sha256',$pdf));}catch(DomainException $denied){show_404();return;}$this->output->set_header('Content-Disposition: attachment; filename="contrato-'.(int)$record['number'].'.pdf"');$this->output->set_content_type('application/pdf')->set_output($pdf);return;
                }
                if($action==='invoice_checkout'){
                    if(!$current||!is_array($identity)){show_404();return;}
                    require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyHostedLinks.php';
                    try{$link=(new \Asaas\CompanyHostedLinks($this->db,db_prefix()))->portalLink($portal,$identity,$issuer,$this->input->post('invoice_id',false));}catch(DomainException $denied){show_404();return;}
                    $this->output->set_header('Referrer-Policy: no-referrer');redirect($link['url'],'location',303);return;
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
            if(is_array($invoices)&&$this->db->table_exists(db_prefix().'asaas_company_hosted_links')){try{require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyHostedLinks.php';$hostedCheckout=(new \Asaas\CompanyHostedLinks($this->db,db_prefix()))->portalPage($portal,$identity,$issuer,array_column($invoices['items'],'id'));}catch(Throwable $unavailable){$hostedCheckout=[];}}
            try{require_once APPPATH.'services/CompanyPortalSales.php';$salesReader=new CompanyPortalSales($this->db,db_prefix(),$portal);foreach(['estimates','proposals'] as $kind)$sales[$kind]=$salesReader->page($identity,$issuer,$kind);$ids=[];foreach($sales as $rows)foreach($rows??[] as $row)$ids[]=$row['id'];if($ids){require_once APPPATH.'services/CompanySalesFiles.php';$published=(new CompanySalesFiles($this->db,db_prefix()))->portalPages($portal,$identity,$issuer,$ids);foreach($sales as &$rows)if(is_array($rows))foreach($rows as &$row)$row['files']=$published[(int)$row['id']]??[];unset($rows,$row);}}
            catch(Throwable $unavailable){$error='Documentos indisponíveis no momento. Tente novamente mais tarde.';}
            try{require_once APPPATH.'services/CompanyPortalOperations.php';$reader=new CompanyPortalOperations($this->db,db_prefix(),$portal);foreach(['contracts','tickets','projects'] as $kind)$operations[$kind]=$reader->page($identity,$issuer,$kind,$this->input->get('op_after_'.$kind,false)??0);$requestedKind=$this->input->get('operation_kind',false);$requestedId=$this->input->get('resource_id',false);if($requestedKind!==null||$requestedId!==null){if(!is_string($requestedKind)||!in_array($requestedKind,['contracts','tickets','projects'],true)){show_404();return;}try{$operation_detail=$reader->detail($identity,$issuer,$requestedKind,$requestedId);$operation_kind=$requestedKind;}catch(DomainException $denied){show_404();return;}}}
            catch(Throwable $unavailable){$operations=[];$operation_detail=null;$operation_kind=null;$error='Operações indisponíveis no momento. Tente novamente mais tarde.';}


        }
        $this->load->view('company_portal/index',['hosted_checkout'=>$hostedCheckout,'operations'=>$operations,'operation_detail'=>$operation_detail,'operation_kind'=>$operation_kind,'sales'=>$sales,'invoices'=>$invoices,'issuer'=>$issuer,'identity'=>$current??null,'message'=>$message,'error'=>$error,'portal_csrf'=>$csrf,'script_nonce'=>$nonce]);
    }
}
