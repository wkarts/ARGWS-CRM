<?php
declare(strict_types=1);

/** Shared commands for the original project tabs and the existing portal configuration. */
final class NativeCompanyProjectRuleCommands
{
    public const ROUTES = [
        'view'=>['save_rules','hide_tasks','save_write_rules','save_discussion_rules','save_project_file_upload_rules','save_gantt_rules','save_gantt_date','save_timesheet_rules','save_milestone_rules','publish_milestone','publish','discussion_file_upload','discussion_file_download','discussion_file_audience','discussion_post_audience','discussion_thread_state'],
        'discussion'=>['discussion_open','discussion_thread_state'],
        'add_discussion_comment'=>['discussion_comment'],
        'update_discussion_comment'=>['discussion_edit'],
        'delete_discussion_comment'=>['discussion_delete'],
        'delete_discussion'=>['discussion_thread_state'],
        'change_activity_visibility'=>['discussion_post_audience'],
        'milestone'=>['save_milestone_details'],
        'update_task_milestone'=>['save_milestone_tasks'],
    ];
    public static function submit($ci,int $issuer,int $actor,int $project,string $action,string $key):array
    {
        $post=static fn(string $name,$default=null)=>$ci->input->post($name,false)??$default;
        $revision=$post('project_revision');$db=$ci->db;$p=db_prefix();$selection=[];
        if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_project_portal_invalid');
        if(in_array($action,['save_rules','hide_tasks'],true)){
            $flags=[];foreach(CompanyProjectPortalRulesSchema::FLAGS as $flag)$flags[$flag]=$action==='hide_tasks'?'0':$post($flag,'0');
            (new CompanyProjectPortalRules($db,$p))->save($issuer,$actor,$project,$revision,$post('rules_revision'),$flags,$key);
        }elseif($action==='save_write_rules'){
            $flags=[];foreach(CompanyPortalTaskWriteSchema::FLAGS as $flag)$flags[$flag]=$post($flag,'0');
            (new CompanyPortalTaskWriteRules($db,$p))->save($issuer,$actor,$project,$revision,$post('write_revision'),$flags,$key);
        }elseif($action==='save_discussion_rules'){
            (new CompanyProjectDiscussions($db,$p))->saveRules($issuer,$actor,$project,$revision,$post('discussion_collection_revision'),$post('discussion_rules_revision'),['open_discussions'=>$post('open_discussions','0')],['project_discussions'=>$post('project_discussions','0')],$key);
        }elseif(in_array($action,['discussion_open','discussion_comment','discussion_thread_state','discussion_post_audience'],true)){
            $selection['discussion']=CompanyProjectDiscussionsHttp::staff($ci,$issuer,$actor,$project,$action,$key);
        }elseif(in_array($action,['discussion_edit','discussion_delete','discussion_file_upload','discussion_file_audience'],true)){
            $selection['discussion']=CompanyProjectDiscussionExtrasHttp::staff($ci,$issuer,$actor,$project,$action,$key);
        }elseif($action==='discussion_file_download'){
            CompanyProjectDiscussionExtrasHttp::staffDownload($ci,$issuer,$actor,$project);$selection['download']=true;
        }elseif($action==='save_project_file_upload_rules'){
            (new CompanyProjectContactFileRules($db,$p))->save($issuer,$actor,$project,$revision,$post('project_file_rules_revision'),['upload_files'=>$post('upload_files','0')],['project_files'=>$post('project_files','0')],$key);
        }elseif($action==='save_gantt_rules'){
            (new CompanyProjectGantt($db,$p))->save($issuer,$actor,$project,$revision,$post('gantt_rules_revision'),['view_gantt'=>$post('view_gantt','0')],['project_gantt'=>$post('project_gantt','0')],$key);
        }elseif($action==='save_gantt_date'){
            $task=IssuerPolicy::id($post('task_id'));$start=$post('start_date');$visible=$post('visible','0');
            if(!is_string($start)||!in_array($visible,['0','1'],true))throw new DomainException('company_project_portal_invalid');
            (new CompanyProjectGantt($db,$p))->taskDate($issuer,$actor,$project,$revision,$task,$post('task_revision'),$post('gantt_date_revision'),$start,$visible==='1',$key);$selection['task']=$task;
        }elseif(in_array($action,['save_milestone_rules','save_milestone_details','save_milestone_tasks','publish_milestone'],true)){
            $service=new CompanyProjectMilestones($db,$p);
            if($action==='save_milestone_rules')$service->saveRules($issuer,$actor,$project,$revision,$post('milestone_rules_revision'),['view_milestones'=>$post('view_milestones','0')],['project_milestones'=>$post('project_milestones','0')],$key);
            else{
                $mid=IssuerPolicy::id($post('milestone_id'));$mr=$post('milestone_revision');$selection['milestone']=$mid;
                if($action==='save_milestone_details')$service->details($issuer,$actor,$project,$revision,$mid,$mr,['start_date'=>$post('start_date'),'description'=>$post('description'),'description_visible'=>$post('description_visible','0'),'sort_order'=>$post('sort_order')],$key);
                elseif($action==='save_milestone_tasks'){$ids=$post('milestone_tasks',[]);if(!is_array($ids)||!array_is_list($ids))throw new DomainException('company_project_portal_invalid');$service->tasks($issuer,$actor,$project,$revision,$mid,$mr,$ids,$key);}
                else{$visible=$post('visible','0');if(!in_array($visible,['0','1'],true))throw new DomainException('company_project_portal_invalid');$service->publish($issuer,$actor,$project,$revision,$mid,$mr,$visible==='1',$key);}
            }
        }elseif($action==='save_timesheet_rules'){
            (new CompanyProjectTimesheets($db,$p))->save($issuer,$actor,$project,$revision,$post('timesheet_rules_revision'),['view_timesheets'=>$post('view_timesheets','0')],['project_timesheets'=>$post('project_timesheets','0')],$key);
        }elseif($action==='publish'){
            $task=IssuerPolicy::id($post('task_id'));$kind=$post('kind');$visible=$post('visible','0');
            if(!is_string($kind)||!in_array($visible,['0','1'],true))throw new DomainException('company_project_portal_invalid');
            (new CompanyProjectPortalRules($db,$p))->publish($issuer,$actor,$project,$revision,$task,$post('task_revision'),$kind,$post('child_key'),$visible==='1',$key);$selection['task']=$task;
        }else throw new DomainException('company_project_portal_invalid');
        return $selection;
    }
}
