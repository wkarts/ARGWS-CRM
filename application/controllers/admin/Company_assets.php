<?php

defined('BASEPATH') or exit('No direct script access allowed');
class Company_assets extends AdminController
{
    public function index()
    {
        if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto indisponível.',503);return;}
        require_once APPPATH.'services/CompanyAssets.php';
        $context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();$service=new CompanyAssets($this->db,db_prefix());
        $this->output->set_header('Cache-Control: no-store');$this->output->set_header('X-Content-Type-Options: nosniff');
        $csrf=$this->session->userdata('company_assets_csrf');if(!is_string($csrf)||strlen($csrf)!==64){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_assets_csrf',$csrf);}
        $error=null;$state=null;
        try{
            $state=$service->view($issuer,$actor);
            if($this->input->method(true)==='POST'){
                $token=$this->input->post('company_assets_csrf',false);
                if(!is_string($token)||!hash_equals($csrf,$token)){show_error('Recarregue antes de salvar.',403);return;}
                $action=$this->input->post('action',false);$kind=$this->input->post('kind',false);$revision=$this->input->post('revision',false);$bytes=null;
                if(!is_string($kind)||!is_string($revision)||!in_array($action,['upload','remove'],true))throw new DomainException('company_asset_invalid_image');
                if($action==='upload'){
                    $file=$_FILES['company_asset']??null;
                    if(!is_array($file)||($file['error']??null)!==UPLOAD_ERR_OK||!is_int($file['size']??null)||$file['size']>524288||!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name']))throw new DomainException('company_asset_invalid_image');
                    $bytes=file_get_contents($file['tmp_name'],false,null,0,524289);if(!is_string($bytes))throw new DomainException('company_asset_invalid_image');
                }
                $service->save($issuer,$actor,$kind,$bytes,$revision);CompanyRequestScope::invalidateCaches($this);
                redirect(CompanyRequestScope::adminUrl($this,'company_assets'),'location',303);return;
            }elseif(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
            if($this->input->get('download',false)!==null){
                $kind=$this->input->get('download',false);$inline=is_string($kind)?($state['assets'][$kind]??null):null;
                if(!is_string($inline)){show_404();return;}
                // Read fresh ownership/ACL immediately before bytes leave the process.
                $bytes=$service->download($issuer,$actor,$kind,$state['revision']);
                $this->output->set_content_type('image/png');$this->output->set_header('Content-Disposition: attachment; filename="'.$kind.'.png"');$this->output->set_output($bytes);return;
            }
        }catch(DomainException $e){
            if($e->getMessage()==='company_asset_access_denied'){show_error('Sem permissão para os arquivos desta empresa.',403);return;}
            $this->output->set_status_header($e->getMessage()==='company_asset_stale'?409:422);$state=null;
            $error=$e->getMessage()==='company_asset_stale'?'A imagem mudou. Reabra antes de salvar.':'Envie PNG ou JPEG de até512 KiB,1600 pixels por dimensão e2 milhões de pixels.';
        }catch(Throwable $e){$this->output->set_status_header(503);$state=null;$error='Não foi possível confirmar a operação. Reabra a página antes de tentar novamente.';}
        $acl=new IssuerResourceAccess($this->db,db_prefix());
        $this->load->view('admin/companies/assets',['state'=>$state,'error'=>$error,'asset_csrf'=>$csrf,'can_edit'=>$acl->can($issuer,$actor,'settings','edit')]);
    }
}
