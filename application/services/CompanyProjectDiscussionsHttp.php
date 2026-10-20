<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyProjectDiscussions.php';
/** Exact discussion actions after controller native/context/portal CSRF. No generic legacy dispatch. */
final class CompanyProjectDiscussionsHttp
{
    public static function staff($c,int $issuer,int $actor,int $project,string $action,string $key):int
    {
        $writer=new CompanyProjectDiscussions($c->db,db_prefix());$pr=$c->input->post('project_revision',false);$cr=$c->input->post('discussion_collection_revision',false);$body=$c->input->post('discussion_body',false);if($action==='discussion_open'){$subject=$c->input->post('discussion_subject',false);if(!is_string($subject)||!is_string($body))throw new DomainException('company_project_discussion_invalid');return $writer->staffOpen($issuer,$actor,$project,$pr,$cr,$subject,$body,$key)['id'];}
        $thread=IssuerPolicy::id($c->input->post('discussion_id',false));$revision=$c->input->post('discussion_revision',false);
        if($action==='discussion_comment'){if(!is_string($body))throw new DomainException('company_project_discussion_invalid');$writer->staffComment($issuer,$actor,$project,$pr,$cr,$thread,$revision,$body,$key,($c->input->post('discussion_reply_to',false)??'')===''?null:$c->input->post('discussion_reply_to',false));}
        elseif($action==='discussion_thread_state'){$kind=$c->input->post('discussion_state_kind',false);$value=$c->input->post('discussion_state_value',false);if(!is_string($kind)||!is_string($value)||($kind!=='status'&&!in_array($value,['0','1'],true)))throw new DomainException('company_project_discussion_invalid');$writer->threadState($issuer,$actor,$project,$pr,$cr,$thread,$revision,$kind,$kind==='status'?$value:$value==='1',$key);}
        elseif($action==='discussion_post_audience'){$audience=$c->input->post('discussion_audience',false);if(!is_string($audience))throw new DomainException('company_project_discussion_invalid');$writer->postAudience($issuer,$actor,$project,$pr,$cr,$thread,$revision,$c->input->post('discussion_post_id',false),$audience,$key);}
        else throw new DomainException('company_project_discussion_invalid');return $thread;
    }
    public static function portal($c,array $session,int $issuer,string $action):void
    {
        if(!in_array($action,['project_discussion_open','project_discussion_comment'],true)){show_404();return;}$project=$c->input->post('project_id',false);$key=$c->input->post('request_key',false);$body=$c->input->post('discussion_body',false);
        try{if(!is_string($key)||!is_string($body))throw new DomainException('company_project_discussion_invalid');$writer=new CompanyProjectDiscussions($c->db,db_prefix(),(string)$c->config->item('encryption_key'));$pr=$c->input->post('project_revision',false);$cr=$c->input->post('discussion_collection_revision',false);if($action==='project_discussion_open'){$subject=$c->input->post('discussion_subject',false);if(!is_string($subject))throw new DomainException('company_project_discussion_invalid');$writer->contactOpen($session,$issuer,$project,$pr,$cr,$subject,$body,$key);}else $writer->contactComment($session,$issuer,$project,$pr,$cr,$c->input->post('discussion_id',false),$c->input->post('discussion_revision',false),$body,$key,($c->input->post('discussion_reply_to',false)??'')===''?null:$c->input->post('discussion_reply_to',false));}
        catch(DomainException $denied){$status=match($denied->getMessage()){'company_project_discussion_stale','company_project_discussion_request_conflict'=>409,'company_project_discussion_limit'=>429,'company_project_discussion_invalid'=>422,default=>404};if($status===404){show_404();return;}show_error($status===409?'A conversa ou o projeto mudou. Reabra antes de enviar novamente.':($status===429?'Limite de 100 conversas ou 500 comentários atingido. Consulte a equipe.':'Preencha assunto de até 191 caracteres e mensagem de até 8000 bytes em texto.'),$status);return;}
        redirect($c->config->site_url('company_portal/index/'.$issuer).'?operation_kind=projects&resource_id='.IssuerPolicy::id($project).'&discussion_receipt='.$key,'location',303);
    }
}
