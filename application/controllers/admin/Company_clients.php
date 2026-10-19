<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Isolated customer identity directory. Does not load Clients_model or optional module hooks. */
class Company_clients extends AdminController
{
    public function index()
    {
        if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto de empresa indisponível.',503);return;}
        require_once APPPATH.'services/CompanyClients.php';
        $context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();
        $access=new IssuerResourceAccess($this->db,db_prefix());$service=new CompanyClients($this->db,db_prefix());
        $this->output->set_header('Cache-Control: no-store');
        if(!$access->can($issuer,$actor,'customers','view')){show_error('Sem permissão para consultar clientes desta empresa.',403);return;}
        $csrf=$this->session->userdata('company_clients_csrf');
        if(!is_string($csrf)||strlen($csrf)!==64){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_clients_csrf',$csrf);}
        $contacts=[];$invoiceAccess=[];$error=null;$details=null;$page=['items'=>[],'next'=>null];$options=[];
        try{
            if($this->input->method(true)==='POST'){
                $submitted=$this->input->post('company_clients_csrf',false);
                if(!is_string($submitted)||!hash_equals($csrf,$submitted)){show_error('Recarregue o formulário antes de salvar.',403);return;}
                $action=$this->input->post('action',false);
                if($action==='create'){
                    $request=$this->input->post('request_key',false);
                    if(!is_string($request)||!preg_match('/^[a-f0-9]{64}$/D',$request))throw new DomainException('company_client_invalid_input');
                    $id=$service->create($issuer,$actor,$this->clientInput(),$request);
                }else{
                    $id=$this->input->post('client_id',false);$revision=$this->input->post('revision',false);
                    if(!is_string($revision)||!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('company_client_stale');
                    if($action==='contact'){
                        require_once APPPATH.'services/CompanyContacts.php';
                        $contactInput=$this->input->post('contact',false);
                        if(!is_array($contactInput))throw new DomainException('company_contact_invalid_input');
                        // A current customer revision is mandatory before adding/editing any subresource.
                        $owner=$service->details($issuer,$actor,$id);
                        if(!$owner||$owner['client']['owner_issuer_id']!==$issuer)throw new DomainException('company_client_access_denied');
                        if(!hash_equals($owner['revision'],$revision))throw new DomainException('company_client_stale');
                        (new CompanyContacts($this->db,db_prefix()))->save($issuer,$actor,$id,$contactInput,$this->input->post('contact_id',false),$this->input->post('contact_revision',false));
                    }elseif($action==='contact_invoice_access'){
                        require_once APPPATH.'services/CompanyContactInvoiceAccess.php';
                        $owner=$service->details($issuer,$actor,$id);
                        if(!$owner||$owner['client']['owner_issuer_id']!==$issuer||!hash_equals($owner['revision'],$revision))throw new DomainException('company_client_stale');
                        $grantRevision=$this->input->post('grant_revision',false);$allowed=$this->input->post('invoice_access',false);
                        if(!is_string($grantRevision)||!is_string($allowed))throw new DomainException('company_contact_invalid_input');
                        (new CompanyContactInvoiceAccess($this->db,db_prefix()))->set($issuer,$actor,$id,$this->input->post('contact_id',false),$grantRevision,$allowed);
                    }elseif($action==='update')$service->update($issuer,$actor,$id,$this->clientInput(),$revision);
                    elseif($action==='share'){
                        $targets=$this->input->post('targets',false)??[];
                        if(!is_array($targets))throw new DomainException('company_client_invalid_input');
                        $service->setSharing($issuer,$actor,$id,$targets,$revision);
                    }else throw new DomainException('company_client_invalid_input');
                }
                CompanyRequestScope::invalidateCaches($this);
                redirect(CompanyRequestScope::adminUrl($this,'company_clients?client='.(int)$id),'location',303);return;
            }elseif(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
            $client=$this->input->get('client',false);
            if($client!==null){$details=$service->details($issuer,$actor,$client);if(!$details){show_error('Cliente indisponível nesta empresa.',404);return;}}
            $after=$this->input->get('after',false)??0;$search=$this->input->get('search',false)??'';
            if(!is_string($search))throw new DomainException('company_client_invalid_input');
            $page=$service->page($issuer,$actor,$after,50,$search);
            if($details&&$details['client']['owner_issuer_id']===$issuer){require_once APPPATH.'services/CompanyContacts.php';$contacts=(new CompanyContacts($this->db,db_prefix()))->page($issuer,$actor,$details['client']['userid']);
                if($access->can($issuer,$actor,'invoices','view')){require_once APPPATH.'services/CompanyContactInvoiceAccess.php';$invoiceAccess=(new CompanyContactInvoiceAccess($this->db,db_prefix()))->page($issuer,$actor,$details['client']['userid']);}}
            if($details&&$details['client']['owner_issuer_id']===$issuer&&$access->can($issuer,$actor,'customers','share'))$options=$service->sharingOptions($issuer,$actor);
        }catch(DomainException $exception){
            $reason=$exception->getMessage();
            if($reason==='company_client_access_denied'){show_error('Sem permissão para esta ação no cliente.',403);return;}
            $this->output->set_status_header(in_array($reason,['company_client_stale','company_client_request_conflict'],true)?409:422);
            $error=match($reason){
                'company_client_stale'=>'Este cliente mudou. Reabra o cadastro antes de salvar; esta tentativa não alterou dados.',
                'company_client_request_conflict'=>'Este envio já foi utilizado com outros dados. Reabra o formulário.',
                'company_contact_email_conflict'=>'Este e-mail já está cadastrado nesta empresa. Confira o contato existente.',
                'company_client_identity_conflict'=>'O documento fiscal já está cadastrado. Solicite ao responsável a revisão ou o compartilhamento da identidade.',
                default=>'Não foi possível salvar. Confira nome, CPF/CNPJ e empresas autorizadas. Nenhuma alteração desta tentativa foi aplicada.',
            };
        }catch(Throwable $exception){
            log_message('error','Company customer operation failed; no customer data logged.');
            $this->output->set_status_header(503);$error='Não foi possível confirmar a gravação. Recarregue antes de tentar novamente.';
        }
        $capabilities=[];foreach(['create','edit','share'] as $cap)$capabilities[$cap]=$access->can($issuer,$actor,'customers',$cap);
        $this->load->view('admin/companies/clients',[
            'invoice_access'=>$invoiceAccess,'contacts'=>$contacts,'portal_url'=>$this->config->site_url('company_portal/index/'.$issuer),
            'issuer'=>$issuer,'directory'=>$page,'details'=>$details,'sharing_options'=>$options,'capabilities'=>$capabilities,
            'error'=>$error,'company_clients_csrf'=>$csrf,'request_key'=>bin2hex(random_bytes(32)),
            'search'=>is_string($this->input->get('search',false))?$this->input->get('search',false):'',
        ]);
    }
    private function clientInput(): array
    {
        $input=$this->input->post('client',false);
        if(!is_array($input))throw new DomainException('company_client_invalid_input');
        return $input;
    }
}
