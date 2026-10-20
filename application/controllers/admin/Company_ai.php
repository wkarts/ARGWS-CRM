<?php

defined('BASEPATH') or exit('No direct script access allowed');
class Company_ai extends AdminController
{
    protected function service():CompanyAi{require_once APPPATH.'services/CompanyAi.php';return new CompanyAi($this->db,db_prefix());}
    public function index()
    {
        if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto indisponível.',503);return;}
        $ctx=CompanyRequestScope::context();$issuer=$ctx->issuerId();$actor=$ctx->staffId();$service=$this->service();$url=CompanyRequestScope::adminUrl($this,'company_ai');$this->output->set_header('Cache-Control: no-store')->set_header('Pragma: no-cache')->set_header('Referrer-Policy: no-referrer');$error=null;$preview=null;$result=null;$history=[];
        $csrf=$this->session->userdata('company_ai_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_ai_csrf',$csrf);}
        $ticket=$this->input->get('ticket',false);$operation=$this->input->get('operation',false)??'summary';
        if(($ticket!==null&&(!is_string($ticket)||!preg_match('/^[1-9][0-9]{0,8}$/D',$ticket)))||!in_array($operation,['summary','reply'],true)){show_error('Seleção inválida.',422);return;}$ticket=$ticket===null?null:(int)$ticket;
        try{
            $method=$this->input->method(true);
            if($method==='POST'){
                $token=$this->input->post('company_ai_csrf',false);if(!is_string($token)||!hash_equals($csrf,$token)){show_error('Reabra a página.',403);return;}$this->load->library('encryption');$action=$this->input->post('action',false);
                $revision=$this->input->post('revision',false);if(!is_string($revision)||!preg_match('/^(0|[1-9][0-9]{0,8})$/D',$revision))throw new DomainException('company_ai_invalid');if($service->profile($issuer,$actor)['revision']!==(int)$revision)throw new DomainException('company_ai_stale');
                if($action==='save'){$model=$this->input->post('model',false);$secret=$this->input->post('secret',false);$ops=$this->input->post('operations',false)??[];if(!is_string($model)||!is_string($secret)||!is_array($ops))throw new DomainException('company_ai_invalid');$service->save($issuer,$actor,(int)$revision,$model,$secret,$ops,fn($v)=>$this->encryption->encrypt($v));}
                elseif($action==='revoke')$service->revoke($issuer,$actor,(int)$revision);
                elseif($action==='queue'){$source=$this->input->post('source_hash',false);$key=$this->input->post('request_key',false);$consent=$this->input->post('consent',false);if(!$ticket||!is_string($source)||!is_string($key))throw new DomainException('company_ai_invalid');$service->queue($issuer,$actor,$ticket,$operation,$source,$key,$consent==='1');}
                elseif(in_array($action,['dispatch','forget'],true)){$key=$this->input->post('request_key',false);if(!is_string($key))throw new DomainException('company_ai_invalid');if($action==='dispatch')$service->dispatch($issuer,$actor,$key,fn($v)=>$this->encryption->decrypt($v),fn($v)=>$this->encryption->encrypt($v));else$service->forget($issuer,$actor,$key);}
                else throw new DomainException('company_ai_invalid');
                CompanyRequestScope::invalidateCaches($this);redirect($url.($ticket?'?ticket='.$ticket.'&operation='.$operation:''),'location',303);return;
            }
            if(!in_array($method,['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
            $profile=$service->profile($issuer,$actor);if($ticket){$history=$service->history($issuer,$actor,$ticket);if($profile['status']==='configured'&&in_array($operation,$profile['operations'],true))$preview=$service->preview($issuer,$actor,$ticket,$operation);}
            $key=$this->input->get('result',false);if($key!==null){if(!is_string($key))throw new DomainException('company_ai_invalid');$this->load->library('encryption');$result=$service->result($issuer,$actor,$key,fn($v)=>$this->encryption->decrypt($v));}
        }catch(DomainException$e){$status=match($e->getMessage()){'company_ai_denied'=>403,'company_ai_stale','company_ai_conflict'=>409,default=>422};$this->output->set_status_header($status);$error=$status===403?'Sem acesso ao chamado ou à ação.':($status===409?'A origem ou configuração mudou. Reabra a prévia.':'Confira os limites, as permissões e o consentimento.');}catch(Throwable$e){$this->output->set_status_header(503);$error='Tentativa não confirmada. Consulte o histórico; não repita um resultado incerto.';}
        try{$profile=$service->profile($issuer,$actor);$acl=new IssuerResourceAccess($this->db,db_prefix());$canConfigure=$acl->can($issuer,$actor,'company-ai','configure')&&$acl->can($issuer,$actor,'settings','view')&&$acl->can($issuer,$actor,'settings','edit');$this->load->view('admin/companies/ai',['profile'=>$profile,'ticket'=>$ticket,'operation'=>$operation,'preview'=>$preview,'history'=>$history,'result'=>$result,'error'=>$error,'csrf'=>$csrf,'can_configure'=>$canConfigure]);}catch(Throwable$e){show_error('Assistente privado indisponível.',403);}
    }
}
