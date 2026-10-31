<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyProjectCopies.php';
require_once __DIR__.'/NativeCompanyProjectForm.php';
/** Original POST /projects/copy/{id}; keeps the original payload and canonical redirect. */
final class NativeCompanyProjectCopyHttp
{
    public static function copy($ci,$canonical):void
    {
        $method=strtoupper($ci->input->method(true));
        if(!in_array($method,['GET','HEAD','POST'],true)){$ci->output->set_header('Allow: GET, HEAD, POST');show_error('Método não permitido.',405);return;}
        try{
            $ctx=CompanyRequestScope::context();$issuer=$ctx->issuerId();$actor=$ctx->staffId();
            $ref=NativeCompanyResourceId::decode('project',$canonical);if($ref['source_kind']!=='company_project')throw new DomainException('company_project_copy_denied');
            $copies=new CompanyProjectCopies($ci->db,db_prefix());
            if($method!=='POST'){$copies->preview($issuer,$actor,$ref['source_id'],true);redirect(CompanyRequestScope::adminUrl($ci,'projects/copies?source='.$ref['source_id']),'location',303);return;}
            CompanyRequestScope::assertNativeMutation($ci);$post=$ci->input->post(null,false);$csrf=$ci->security->get_csrf_token_name();
            $allowed=['tasks','tasks_include_checklist_items','task_include_assignees','task_include_followers','milestones','members','copy_project_task_status','name','clientid_copy_project','start_date','deadline','native_copy_request_key','_company_context','_native_company_csrf',$csrf];
            if(!is_array($post)||array_diff(array_keys($post),$allowed)||!is_string($post['native_copy_request_key']??null)||!preg_match('/^[a-f0-9]{64}$/D',$post['native_copy_request_key']))throw new DomainException('company_project_copy_invalid');
            $flag=static function(string $key)use($post):int{if(!array_key_exists($key,$post))return 0;if(!in_array($post[$key],['on','1',1],true))throw new DomainException('company_project_copy_invalid');return 1;};
            $preview=$copies->preview($issuer,$actor,$ref['source_id'],true);$header=$preview['source']['header'];$form=new NativeCompanyProjectForm($ci->db,db_prefix());
            $tasks=$flag('tasks');$assignees=[];if($tasks)foreach($preview['source']['tasks']as$t)$assignees[$t['id']]=$t['assignee_id'];
            $review=['source_revision'=>$preview['revision'],'name'=>$post['name']??null,'start_date'=>$form->parseDate($issuer,$post['start_date']??null),'due_date'=>$form->parseDate($issuer,$post['deadline']??null,true),'responsible_id'=>$header['responsible_id'],'copy_tasks'=>$tasks,'copy_milestones'=>$flag('milestones'),'copy_dependencies'=>0,'assignees'=>$assignees,
                'native'=>['client_id'=>$post['clientid_copy_project']??null,'checklist'=>$tasks?$flag('tasks_include_checklist_items'):0,'assignees'=>$tasks?$flag('task_include_assignees'):0,'followers'=>$tasks?$flag('task_include_followers'):0,'members'=>$flag('members'),'task_status'=>$post['copy_project_task_status']??'1']];
            $result=$copies->create($issuer,$actor,$ref['source_id'],$review,$post['native_copy_request_key']);CompanyRequestScope::invalidateCaches($ci);
            redirect(CompanyRequestScope::adminUrl($ci,'projects/view/'.NativeCompanyResourceId::encode('company_project',$result['project_id'])),'location',303);
        }catch(DomainException $e){show_error('Não foi possível copiar o projeto. Confira os campos e as permissões e reabra o formulário.',str_contains($e->getMessage(),'stale')||str_contains($e->getMessage(),'conflict')?409:(str_contains($e->getMessage(),'denied')?403:422));}
        catch(RuntimeException $e){if(!str_starts_with($e->getMessage(),'issuer_'))throw$e;show_error('Não foi possível confirmar a cópia do projeto.',503);}
    }
}
