<?php

defined('BASEPATH') or exit('No direct script access allowed');
class Company_pusher extends AdminController
{
    public function index()
    {
        if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto de empresa indisponível.',503);return;}
        require_once APPPATH.'services/CompanyPusherTaskNotifications.php';$ctx=CompanyRequestScope::context();$issuer=$ctx->issuerId();$actor=$ctx->staffId();$profile=new CompanyPusherProfile($this->db,db_prefix());$service=new CompanyPusherTaskNotifications($this->db,db_prefix());$url=CompanyRequestScope::adminUrl($this,'company_pusher');$this->output->set_header('Cache-Control: no-store')->set_header('Pragma: no-cache')->set_header('Referrer-Policy: no-referrer');$csrf=$this->session->userdata('company_pusher_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_pusher_csrf',$csrf);}$error=null;
        try{
            $this->load->library('encryption');$decrypt=fn($v)=>$this->encryption->decrypt($v);$encrypt=fn($v)=>$this->encryption->encrypt($v);$method=$this->input->method(true);$reader=$this->input->get('reader',false);
            if($reader!==null){if($reader!=='1'||$method!=='GET')throw new DomainException('company_pusher_invalid');$this->output->set_content_type('application/json')->set_output(json_encode(['items'=>$service->inbox($issuer,$actor)],JSON_THROW_ON_ERROR));return;}
            if($method==='POST'){
                $posted=$this->input->post('company_pusher_csrf',false);if(!is_string($posted)||!hash_equals($csrf,$posted))throw new DomainException('company_pusher_denied');$action=$this->input->post('action',false);
                if($action==='authorize'){$channel=$this->input->post('channel_name',false);$socket=$this->input->post('socket_id',false);if(!is_string($channel)||!is_string($socket))throw new DomainException('company_pusher_denied');$this->output->set_content_type('application/json')->set_output(json_encode($service->authorizeChannel($issuer,$actor,$channel,$socket,$decrypt),JSON_THROW_ON_ERROR));return;}
                $revision=$this->input->post('revision',false);if(!is_string($revision)||!preg_match('/^(0|[1-9][0-9]{0,8})$/D',$revision))throw new DomainException('company_pusher_invalid');
                if($action==='save'){$values=[];foreach(['app_id','app_key','cluster','app_secret']as$f){$values[$f]=$this->input->post($f,false);if(!is_string($values[$f]))throw new DomainException('company_pusher_invalid');}$profile->save($issuer,$actor,(int)$revision,$values['app_id'],$values['app_key'],$values['cluster'],$values['app_secret'],$encrypt);}
                elseif($action==='revoke')$profile->revoke($issuer,$actor,(int)$revision);
                elseif(in_array($action,['queue','dispatch'],true)){$key=$this->input->post('request_key',false);if(!is_string($key))throw new DomainException('company_pusher_invalid');$current=$service->descriptor($issuer,$actor);if((int)$revision!==$current['revision'])throw new DomainException('company_pusher_stale');if($action==='queue'){$task=$this->input->post('task_id',false);$taskRevision=$this->input->post('task_revision',false);if(!is_string($task)||!preg_match('/^[1-9][0-9]{0,8}$/D',$task)||!is_string($taskRevision)||!preg_match('/^[1-9][0-9]{0,8}$/D',$taskRevision))throw new DomainException('company_pusher_invalid');$service->queue($issuer,$actor,(int)$task,(int)$taskRevision,(int)$revision,$key);}else $service->dispatch($issuer,$actor,$key,$decrypt);}
                else throw new DomainException('company_pusher_invalid');CompanyRequestScope::invalidateCaches($this);redirect($url,'location',303);return;
            }elseif(!in_array($method,['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
        }catch(DomainException $e){$status=match($e->getMessage()){'company_pusher_denied','company_pusher_disabled'=>403,'company_pusher_stale','company_pusher_conflict'=>409,default=>422};$this->output->set_status_header($status);$error=$status===403?'Seu acesso mudou. Reabra a empresa.':($status===409?'O perfil ou a tarefa mudou. Reabra a página.':'Confira o perfil próprio e sua ativação local.');}
        catch(Throwable $e){$this->output->set_status_header(503);$error='Operação não confirmada. Uma tentativa incerta não será repetida automaticamente.';}
        if(($this->input->post('action',false)==='authorize')||$this->input->get('reader',false)!==null){$this->output->set_content_type('application/json')->set_output('{"error":"company_pusher_unavailable"}');return;}
        try{
            $acl=new IssuerResourceAccess($this->db,db_prefix());
            $canView=$acl->can($issuer,$actor,'settings','view')&&$acl->can($issuer,$actor,'pusher','view');
            $canConfigure=$canView&&$acl->can($issuer,$actor,'settings','edit')&&$acl->can($issuer,$actor,'pusher','configure');
            $page=$canView?$profile->page($issuer,$actor):null;
            $receive=$acl->can($issuer,$actor,'pusher','view')&&$acl->can($issuer,$actor,'tasks','view')&&$acl->can($issuer,$actor,'projects','view');
            if(!$canView&&!$receive)throw new DomainException('company_pusher_denied');
            $activity=['tasks'=>[],'publish'=>false,'notices'=>[]];$descriptor=null;$inbox=[];
            if($receive)try{
                $descriptor=$service->descriptor($issuer,$actor);
                $inbox=$service->inbox($issuer,$actor);
                $activity=$service->page($issuer,$actor);
            }catch(DomainException $e){
                // A revoked credential owner must not strand the currently authorized
                // configurator. This renders configuration only; no old epoch is used.
                if($e->getMessage()!=='company_pusher_disabled'){
                    if(!$canConfigure||$e->getMessage()!=='company_pusher_denied')throw $e;
                    $page=$profile->page($issuer,$actor);
                    $profile->authorize($issuer,$actor,'configure');
                    $error??='O perfil anterior perdeu autorização. Salve explicitamente um perfil para iniciar uma nova revisão.';
                }
                $descriptor=null;$inbox=[];$activity=['tasks'=>[],'publish'=>false,'notices'=>[]];
            }
            $this->load->view('admin/companies/pusher',['profile'=>$page,'can_configure'=>$canConfigure,'activity'=>$activity,'descriptor'=>$descriptor,'inbox'=>$inbox,'csrf'=>$csrf,'error'=>$error]);
        }catch(DomainException $e){show_error('Sem acesso aos avisos próprios desta empresa.',403);}catch(Throwable $e){show_error('Avisos próprios indisponíveis. Confira a atualização do núcleo.',503);}
    }
}
