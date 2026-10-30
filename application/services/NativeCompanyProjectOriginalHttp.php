<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyProjectRuleCommands.php';
require_once __DIR__.'/NativeCompanyResourceReferences.php';

/** Composition of original project actions. Every route binds a finite command. */
trait NativeCompanyProjectOriginalHttp
{
    private function nativeProjectRulesCsrf():string
    {
        $key='company_project_rules_csrf';$token=$this->session->userdata($key);
        if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/D',$token)){$token=bin2hex(random_bytes(32));$this->session->set_userdata($key,$token);}
        return $token;
    }
    private function nativeProjectOriginalError(Throwable $error):void
    {
        $reason=$error->getMessage();$status=$error instanceof DomainException?(preg_match('/stale|conflict|has_replies/',$reason)?409:(preg_match('/denied|access|csrf|unavailable/',$reason)?403:422)):503;
        show_error('Não foi possível confirmar esta ação, a revisão ou as permissões do projeto.',$status);
    }
    private function nativeProjectRuleAction(string $route,$pathProject=null,$pathChild=null):void
    {
        try{
            CompanyRequestScope::assertNativeMutation($this);$ctx=CompanyRequestScope::context();$issuer=$ctx->issuerId();$actor=$ctx->staffId();
            if($issuer===null)throw new DomainException('native_project_access_denied');
            $token=$this->input->post('company_project_rules_csrf',false);
            if(!is_string($token)||!hash_equals($this->nativeProjectRulesCsrf(),$token))throw new DomainException('native_project_csrf_invalid');
            $action=$this->input->post('action',false);
            if(!is_string($action)||!in_array($action,NativeCompanyProjectRuleCommands::ROUTES[$route]??[],true))throw new DomainException('native_project_action_invalid');
            $postedProject=NativeCompanyResourceId::decode('project',$this->input->post('project_id',false));if($postedProject['source_kind']!=='company_project')throw new DomainException('native_project_access_denied');$project=$postedProject['source_id'];
            $canonical=NativeCompanyResourceId::encode('company_project',$project);
            NativeCompanyResourceReferences::resolve($this->db,db_prefix(),$issuer,$actor,'project',$canonical,'view');
            if($pathProject!==null&&NativeCompanyResourceId::decode('project',$pathProject)['canonical_id']!==$canonical)throw new DomainException('native_project_access_denied');
            if($pathChild!==null&&$pathChild!==''){
                $field=in_array($route,['update_discussion_comment','delete_discussion_comment','change_activity_visibility'],true)?'discussion_post_id':($route==='milestone'?'milestone_id':'discussion_id');
                if(IssuerPolicy::id($pathChild)!==IssuerPolicy::id($this->input->post($field,false)))throw new DomainException('native_project_access_denied');
            }
            if($route==='delete_discussion'&&($this->input->post('discussion_state_kind',false)!=='active'||$this->input->post('discussion_state_value',false)!=='0'))throw new DomainException('native_project_action_invalid');
            $key=$this->input->post('request_key',false);if(!is_string($key))throw new DomainException('native_project_action_invalid');
            $selection=NativeCompanyProjectRuleCommands::submit($this,$issuer,$actor,$project,$action,$key);
            if(isset($selection['download']))return;
            CompanyRequestScope::invalidateCaches($this);
            $group=str_contains($action,'discussion')?'project_discussions':(str_contains($action,'milestone')?'project_milestones':(str_contains($action,'timesheet')?'project_timesheets':(str_contains($action,'gantt')?'project_gantt':'project_overview')));
            $target='projects/view/'.$canonical.'?group='.$group;foreach($selection as$name=>$value)$target.='&'.$name.'='.(int)$value;
            redirect(CompanyRequestScope::adminUrl($this,$target),'location',303);
        }catch(Throwable $error){$this->nativeProjectOriginalError($error);}
    }
    private function nativeProjectGroupRead($id,string $group):void
    {
        if(!in_array(strtoupper($this->input->method(true)),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
        $this->nativeProjectView($id,$group);
    }
    private function nativeProjectDiscussionComments($id,string $type):void
    {
        try{
            if(!in_array(strtoupper($this->input->method(true)),['GET','HEAD'],true)||$type!=='regular')throw new DomainException('native_project_action_invalid');
            $ctx=CompanyRequestScope::context();if($ctx->issuerId()===null)throw new DomainException('native_project_access_denied');
            $reference=NativeCompanyResourceReferences::resolve($this->db,db_prefix(),$ctx->issuerId(),$ctx->staffId(),'project',$this->input->get('project_id',false),'view');
            if($reference['source_kind']!=='company_project')throw new DomainException('native_project_access_denied');
            $reader=new CompanyProjectDiscussions($this->db,db_prefix());$state=$reader->staffPage($ctx->issuerId(),$ctx->staffId(),$reference['source_id'],$id);$items=[];
            foreach($state['posts']as$post){
                if((int)$post['active']!==1)continue;
                $items[]=['id'=>(int)$post['id'],'parent'=>$post['reply_to']??null,'content'=>htmlspecialchars($post['body'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'),'fullname'=>$post['author_name'],'created'=>strtotime($post['created_at'])*1000,'created_by_current_user'=>$post['author_type']==='staff'&&(int)$post['staff_id']===$ctx->staffId(),'upvote_count'=>0,'user_has_upvoted'=>false,'native_revision'=>(int)$post['content_revision']];
            }
            if($reader->staffPage($ctx->issuerId(),$ctx->staffId(),$reference['source_id'],$id)!==$state)throw new DomainException('native_project_stale');
            $this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode($items,JSON_THROW_ON_ERROR));
        }catch(Throwable $error){$this->nativeProjectOriginalError($error);}
    }
    private function nativeProjectWorkData(int $issuer,int $actor,int $project,string $group,int $clock):array
    {
        $p=db_prefix();$selected=$this->input->get('discussion',false);$data=[];
        if($group==='project_discussions'){
            $state=(new CompanyProjectDiscussions($this->db,$p))->staffPage($issuer,$actor,$project,$selected);
            if($state['thread']&&(int)$state['thread']['active']===1){$files=new CompanyProjectDiscussionFiles($this->db,$p);foreach($state['posts']as&$post)$post['files']=(string)$post['active']==='1'?$files->staffList($issuer,$actor,$project,$state['thread']['id'],$post['id']):[];unset($post);}
            $data['discussion_state']=$state;
        }elseif($group==='project_milestones'){
            $data['milestone_state']=(new CompanyProjectMilestones($this->db,$p))->get($issuer,$actor,$project,$this->input->get('milestone',false));
            $data['milestone_basic']=(new CompanyProjectExtras($this->db,$p))->state($issuer,$actor,'project',$project,'milestones');
        }elseif($group==='project_timesheets'){
            require_once __DIR__.'/NativeCompanyTasksRead.php';
            $data['timesheet_state']=(new CompanyProjectTimesheets($this->db,$p))->get($issuer,$actor,$project);
            $data['native_project_timesheets']=(new NativeCompanyTasksRead($this->db,$p))->projectTimers($issuer,$actor,NativeCompanyResourceId::encode('company_project',$project),$clock);
        }elseif(in_array($group,['project_tasks','project_gantt'],true)){
            require_once __DIR__.'/NativeCompanyTaskIndexProjection.php';
            $ids=IssuerStorage::query($this->db,'SELECT id FROM '.$p.'company_tasks WHERE issuer_id=? AND project_id=? ORDER BY id LIMIT 1001',[$issuer,$project])->result_array();
            if(count($ids)>1000)throw new DomainException('native_project_task_limit');$reader=new NativeCompanyTaskIndexProjection($this->db,$p);$data['native_project_tasks']=[];
            foreach($ids as$id)$data['native_project_tasks'][]=$reader->readOne($issuer,$actor,NativeCompanyResourceId::encode('company_task',$id['id']));
            if($group==='project_gantt')$data['gantt_state']=(new CompanyProjectGantt($this->db,$p))->get($issuer,$actor,$project,$this->input->get('task',false));
        }
        return $data;
    }
    private function nativeProjectMilestoneAction($id=''):void
    {
        if($this->input->post('action',false)==='save_milestone_details'){$this->nativeProjectRuleAction('milestone',null,$id);return;}
        try{
            CompanyRequestScope::assertNativeMutation($this);$ctx=CompanyRequestScope::context();if($ctx->issuerId()===null)throw new DomainException('native_project_access_denied');
            $token=$this->input->post('company_project_rules_csrf',false);if(!is_string($token)||!hash_equals($this->nativeProjectRulesCsrf(),$token))throw new DomainException('native_project_csrf_invalid');
            if($this->input->post('action',false)!=='milestone')throw new DomainException('native_project_action_invalid');
            $postedProject=NativeCompanyResourceId::decode('project',$this->input->post('project_id',false));if($postedProject['source_kind']!=='company_project')throw new DomainException('native_project_access_denied');$project=$postedProject['source_id'];$mid=$id===''?null:IssuerPolicy::id($id);$key=$this->input->post('request_key',false);if(!is_string($key))throw new DomainException('native_project_action_invalid');
            (new CompanyProjectExtras($this->db,db_prefix()))->milestone($ctx->issuerId(),$ctx->staffId(),$project,$this->input->post('project_revision',false),$key,['name'=>$this->input->post('name',false),'due_date'=>$this->input->post('due_date',false),'done'=>$this->input->post('done',false)??'0'],$mid);
            CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'projects/view/'.NativeCompanyResourceId::encode('company_project',$project).'?group=project_milestones'.($mid?'&milestone='.$mid:'')),'location',303);
        }catch(Throwable $error){$this->nativeProjectOriginalError($error);}
    }
}
