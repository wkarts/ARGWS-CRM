<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyTasks.php';require_once __DIR__.'/CompanyStandaloneTasks.php';
require_once __DIR__.'/NativeCompanyResourceReferences.php';require_once __DIR__.'/CompanySettingsStorage.php';require_once __DIR__.'/CompanySettingsFormats.php';
/** Adapter for the original Tasks::task payload. Route admission requires every original auxiliary action as well. */
final class NativeCompanyTaskForm
{
    public function __construct(private$db,private string$p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function authorize(int$i,int$a,string$cap):void{$acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($i,$a,'tasks','view')||!$acl->can($i,$a,'tasks',$cap))throw new DomainException('native_task_form_denied');}
    private function date(int$i,$value,bool$nullable=false):?string
    {
        if($nullable&&($value===''||$value===null))return null;if(!is_string($value))throw new DomainException('native_task_form_date_invalid');
        $setting=(new CompanySettingsStorage($this->db,$this->p))->get($i,'dateformat');$format=CompanySettingsFormats::dates()[$setting]??throw new DomainException('native_task_form_date_invalid');
        $date=DateTimeImmutable::createFromFormat('!'.$format,$value);if(!$date||$date->format($format)!==$value)throw new DomainException('native_task_form_date_invalid');return$date->format('Y-m-d');
    }
    private function dateTimeFormat(int$i):string
    {
        $storage=new CompanySettingsStorage($this->db,$this->p);$date=CompanySettingsFormats::dates()[$storage->get($i,'dateformat')]??throw new DomainException('native_task_form_date_invalid');
        return$date.' '.match($storage->get($i,'time_format')){'24'=>'H:i:s','12'=>'g:i A',default=>throw new DomainException('native_task_form_date_invalid')};
    }
    private function dateTime(int$i,$value):string
    {
        if(!is_string($value))throw new DomainException('native_task_form_date_invalid');$format=$this->dateTimeFormat($i);$date=DateTimeImmutable::createFromFormat('!'.$format,$value);if(!$date||$date->format($format)!==$value)throw new DomainException('native_task_form_date_invalid');return$date->format('Y-m-d H:i:s');
    }
    /** Original new-task modal projection. Existing task editor uses the same projection after its complete history is resolved. */
    public function newForm(int$i,int$a,array$query,string$today):array{return$this->baseForm($i,$a,$query,$today,'create');}
    private function baseForm(int$i,int$a,array$query,string$today,string$cap):array
    {
        $this->authorize($i,$a,$cap);NativeCompanyTaskFormValues::date($today);
        if(array_diff(array_keys($query),['rel_type','rel_id','start_date'])||(!empty($query['rel_type'])&&!in_array($query['rel_type'],['project','ticket','invoice'],true)))throw new DomainException('native_task_relation_writer_required');
        require_once __DIR__.'/NativeCompanyTaskFormPreferences.php';require_once __DIR__.'/NativeCompanyUploadPreferences.php';require_once __DIR__.'/NativeCompanyTaskChecklistTemplates.php';require_once __DIR__.'/WorkStatusPresentation.php';
        $project=null;$selected=null;$milestoneRevision=null;$milestones=[];$end=[];$writer=new CompanyStandaloneTasks($this->db,$this->p);
        if(($query['rel_type']??'')==='project'){
            $selected=NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'project',$query['rel_id']??'','view');if($selected['source_kind']!=='company_project')throw new DomainException('native_task_relation_writer_required');
            $project=(new CompanyProjects($this->db,$this->p))->get($i,$a,$selected['source_id']);$writer=new CompanyTasks($this->db,$this->p);
            $milestoneState=$writer->nativeMilestoneSelection($i,$a,$selected['source_id']);$milestones=$milestoneState['items'];$milestoneRevision=$milestoneState['revision'];
            if($project['due_date']!==null)$end=['data-date-end-date'=>$project['due_date']];
        }elseif(($query['rel_type']??'')==='ticket'){
            $selected=NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'ticket',$query['rel_id']??'','view');
        }elseif(($query['rel_type']??'')==='invoice'){
            $selected=NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'invoice',$query['rel_id']??'','view');if(!in_array($selected['source_kind'],['invoice','company_invoice_draft'],true))throw new DomainException('native_task_relation_writer_required');
        }elseif(!empty($query['rel_id']))throw new DomainException('native_task_relation_writer_required');
        $preferences=IssuerTransaction::run($this->db,$this->p,(new CompanyTaskOperationPreferences($this->db,$this->p))->tables(),function()use($i,$a,$cap){(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($i,$a);$this->authorize($i,$a,$cap);return[CompanyTaskPreferences::readInOwnedTransaction($this->db,$this->p,$i),CompanyTaskOperationPreferences::readInOwnedTransaction($this->db,$this->p,$i)];});[$defaults,$operations]=$preferences;
        $options=NativeCompanyTaskFormPreferences::read($this->db,$this->p,$i,$a)+NativeCompanyUploadPreferences::read($this->db,$this->p,$i,$a)+[CompanyTaskPreferences::ASSIGN=>$defaults[CompanyTaskPreferences::ASSIGN],CompanyTaskOperationPreferences::PRIORITY=>$operations[CompanyTaskOperationPreferences::PRIORITY],CompanyTaskOperationPreferences::FOLLOW=>$operations[CompanyTaskOperationPreferences::FOLLOW],'default_task_status'=>CompanyTaskPreferences::toNativeStatus($defaults[CompanyTaskPreferences::STATUS])];
        $date=(new CompanySettingsStorage($this->db,$this->p))->get($i,'dateformat');$format=CompanySettingsFormats::dates()[$date]??throw new DomainException('native_task_form_date_invalid');$start=isset($query['start_date'])?$this->date($i,$query['start_date']):$today;$label=(new DateTimeImmutable($start))->format($format);
        $acl=new IssuerResourceAccess($this->db,$this->p);$permissions=[];foreach(['view','create','edit','delete']as$permission)$permissions[$permission]=$acl->can($i,$a,'tasks',$permission);
        $members=$writer->staffOptions($i,$a);$catalog=(new NativeCompanyTaskChecklistTemplates($this->db,$this->p))->catalog($i,$a);
        $this->authorize($i,$a,$cap);
        return ['id'=>'','title'=>_l('add_new',_l('task')),'start_date'=>$label,'milestones'=>$milestones,'members'=>$members,'checklistTemplates'=>$catalog,'project_end_date_attrs'=>$end,'task_statuses'=>WorkStatusPresentation::tasks('_l'),
            'native_task_form'=>['options'=>$options,'permissions'=>$permissions,'counts'=>['assignees'=>0,'followers'=>0,'checklist'=>0,'attachments'=>0], // No existing resource: these are absence, not substituted history.
            'selected_relation'=>$selected===null?null:['id'=>$selected['canonical_id'],'name'=>$selected['name']],'tags_input'=>'','start_date_label'=>$label,'due_date_label'=>'','finished_at_label'=>'','can_client_view'=>false,'project_task_rate'=>false,'fields'=>$milestoneRevision===null?[]:['native_milestone_revision'=>$milestoneRevision]]];
    }
    public function editForm(int$i,int$a,$canonical,string$today):array
    {
        $this->authorize($i,$a,'edit');$ref=NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'task',$canonical,'edit');
        if(!in_array($ref['source_kind'],['company_task','company_standalone_task'],true))throw new DomainException('native_task_legacy_writer_required');
        $projectTask=$ref['source_kind']==='company_task';$kind=$projectTask?'project':'standalone';$writer=$projectTask?new CompanyTasks($this->db,$this->p):new CompanyStandaloneTasks($this->db,$this->p);$row=$writer->get($i,$a,$ref['source_id']);
        if(!in_array($row['billing_state']??'none',['none',null],true)||($row['time_billing_state']??null)!==null)throw new DomainException('native_task_financial_editor_locked');
        $query=$projectTask?['rel_type'=>'project','rel_id'=>NativeCompanyResourceId::encode('company_project',$row['project_id'])]:[];if(!$projectTask&&$row['native_fields_json']!==null){$relationFields=json_decode($row['native_fields_json'],true,32,JSON_THROW_ON_ERROR);if(($relationFields['relation_type']??null)!==null){$relation=NativeCompanyTaskRelationAuthority::fields($this->db,$this->p,$i,$a,$relationFields);$query=['rel_type'=>$relationFields['relation_type'],'rel_id'=>$relation['canonical_id']];}}$data=$this->baseForm($i,$a,$query,$today,'edit');
        $declared=$row['native_fields_json']!==null;$workflow=match($row['status']){'todo'=>1,'awaiting_feedback'=>2,'testing'=>3,'in_progress'=>4,'done'=>5,default=>throw new DomainException('native_task_form_invalid')};
        $values=$declared?NativeCompanyTaskFormValues::normalize(json_decode($row['native_fields_json'],true,32,JSON_THROW_ON_ERROR)+['workflow_status'=>$workflow]):null;
        // Absence is proved by the real recurrence source, never a missing-table shortcut.
        $schedules=IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.'company_task_schedules WHERE issuer_id=? AND kind=? AND source_id=? ORDER BY id',[$i,$kind,$ref['source_id']])->result_array();
        if($schedules)throw new DomainException('native_task_schedule_projection_required');
        require_once __DIR__.'/NativeCompanyWorkAssignments.php';require_once __DIR__.'/NativeCompanyResourceTags.php';
        $members=(new NativeCompanyWorkAssignments($this->db,$this->p))->read($i,$a,$projectTask?'task':'standalone',$ref['source_id']);$memberRows=IssuerStorage::query($this->db,'SELECT staff_id,active FROM '.$this->p.($projectTask?'company_task_assignees':'company_standalone_assignees').' WHERE issuer_id=? AND task_id=? ORDER BY staff_id',[$i,$ref['source_id']])->result_array();$memberIds=array_map('intval',array_column(array_filter($memberRows,static fn($r)=>(int)$r['active']===1),'staff_id'));if($row['assignee_id']!==null&&!in_array((int)$row['assignee_id'],array_map('intval',array_column($memberRows,'staff_id')),true))$memberIds[]=(int)$row['assignee_id'];$tags=(new NativeCompanyResourceTags($this->db,$this->p))->read($i,$a,'task',$canonical);
        $followers=[];$attachments=[];$milestone=null;$clientView=false;$taskRate=null;
        if(!$projectTask)$followers=IssuerStorage::query($this->db,'SELECT staff_id FROM '.$this->p.'company_standalone_followers WHERE issuer_id=? AND task_id=? ORDER BY staff_id',[$i,$ref['source_id']])->result_array();
        if($projectTask){
            $followers=IssuerStorage::query($this->db,'SELECT staff_id FROM '.$this->p.'company_task_followers WHERE issuer_id=? AND task_id=? ORDER BY staff_id',[$i,$ref['source_id']])->result_array();
            $attachments=IssuerStorage::query($this->db,'SELECT file_key FROM '.$this->p.'company_project_files WHERE issuer_id=? AND project_id=? AND task_id=? AND active=1 ORDER BY file_key',[$i,$row['project_id'],$ref['source_id']])->result_array();
            $mapping=IssuerStorage::query($this->db,'SELECT milestone_id FROM '.$this->p.'company_task_milestones WHERE issuer_id=? AND project_id=? AND task_id=?',[$i,$row['project_id'],$ref['source_id']])->row_array();$milestone=$mapping['milestone_id']??null;
            if($milestone!==null&&!in_array((int)$milestone,array_map('intval',array_column($data['milestones'],'id')),true))throw new DomainException('native_task_form_invalid');
            require_once __DIR__.'/CompanyProjectPortalRules.php';$clientView=(new CompanyProjectPortalRules($this->db,$this->p))->get($i,$a,$row['project_id'])['rules']['view_tasks']===1;
            $meta=IssuerStorage::query($this->db,'SELECT billing_type FROM '.$this->p.'company_project_native_metadata WHERE issuer_id=? AND project_id=?',[$i,$row['project_id']])->row_array();if($meta){if(!in_array((string)$meta['billing_type'],['1','2','3'],true))throw new DomainException('native_task_form_invalid');$taskRate=(int)$meta['billing_type']===3;}
        }
        $format=CompanySettingsFormats::dates()[(new CompanySettingsStorage($this->db,$this->p))->get($i,'dateformat')]??throw new DomainException('native_task_form_date_invalid');$dateLabel=static fn($date)=>$date===null?'':(new DateTimeImmutable($date))->format($format);
        $data['id']=(string)$canonical;$data['title']=_l('edit',_l('task')).' '.$row['name'];
        $data['task']=(object)['id'=>(string)$canonical,'name'=>$row['name'],'description'=>$row['description'],'rel_type'=>$query['rel_type']??'','rel_id'=>$query['rel_id']??'','billed'=>0,'invoice_id'=>null,'is_public'=>$values['is_public']??null,'billable'=>$values['billable']??null,'visible_to_client'=>$values['visible_to_client']??null,'hourly_rate'=>$values['hourly_rate']??null,'milestone'=>$milestone,'startdate'=>$values['start_date']??null,'duedate'=>$row['due_date'],'recurring'=>0,'last_recurring_date'=>null,'priority'=>$row['priority'],'repeat_every'=>0,'recurring_type'=>null,'custom_recurring'=>0,'cycles'=>0,'total_cycles'=>0,'status'=>$workflow,'datefinished'=>$values['date_finished']??null];
        $data['native_task_form']=array_replace($data['native_task_form'],['counts'=>['assignees'=>count(array_unique($memberIds)),'followers'=>count($followers),'checklist'=>count($row['checklist']),'attachments'=>count($attachments)],'tags_input'=>implode(',',array_column($tags['tags'],'name')),'start_date_label'=>$dateLabel($values['start_date']??null),'due_date_label'=>$dateLabel($row['due_date']),'finished_at_label'=>isset($values['date_finished'])?(new DateTimeImmutable($values['date_finished']))->format($this->dateTimeFormat($i)):'','can_client_view'=>$clientView,'project_task_rate'=>$taskRate,'undeclared_fields'=>$declared?[]:array_merge(['startdate','hourly_rate','is_public','billable','visible_to_client'],$workflow===5?['datefinished']:[]),'fields'=>$data['native_task_form']['fields']+['native_revision'=>$row['revision'],'native_fields_revision'=>$row['native_fields_revision']??'','native_tags_revision'=>$tags['revision']]]);
        if($writer->get($i,$a,$ref['source_id'])!==$row)throw new DomainException('native_task_form_changed');$this->authorize($i,$a,'edit');return$data;
    }
    /** The original projects/get_rel_project_data consumer, with the same option markup and explicit source snapshots. */
    public function projectData(int$i,int$a,$canonicalProject,$canonicalTask=''):array
    {
        require_once __DIR__.'/CompanyProjectPortalRules.php';require_once __DIR__.'/NativeCompanyWorkAssignments.php';
        $existing=$canonicalTask!==''&&$canonicalTask!=='undefined';$data=$existing?$this->editForm($i,$a,$canonicalTask,gmdate('Y-m-d')):$this->newForm($i,$a,['rel_type'=>'project','rel_id'=>$canonicalProject],gmdate('Y-m-d'));
        $selected=$data['native_task_form']['selected_relation'];if($selected===null||(string)$selected['id']!==(string)$canonicalProject)throw new DomainException('company_project_reference_denied');
        $project=NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'project',$canonicalProject,'view');$source=(new CompanyProjects($this->db,$this->p))->get($i,$a,$project['source_id']);
        $members=(new NativeCompanyWorkAssignments($this->db,$this->p))->read($i,$a,'project',$project['source_id']);$eligible=array_column($data['members'],null,'staffid');$ids=array_map('intval',array_column($members['members'],'staffid'));
        // A pre-membership canonical responsible is a demonstrated assignment, not an inferred follower.
        $memberRows=IssuerStorage::query($this->db,'SELECT staff_id,active FROM '.$this->p.'company_project_members WHERE issuer_id=? AND project_id=? ORDER BY staff_id',[$i,$project['source_id']])->result_array();
        if($source['responsible_id']!==null&&(int)$source['responsible_id']>0&&!in_array((int)$source['responsible_id'],array_map('intval',array_column($memberRows,'staff_id')),true))$ids[]=(int)$source['responsible_id'];
        $assigned=[];if($existing){$task=NativeCompanyResourceId::decode('task',$canonicalTask);$assigned=array_map('intval',array_column((new NativeCompanyWorkAssignments($this->db,$this->p))->read($i,$a,'task',$task['source_id'])['members'],'staffid'));}
        $escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$options=[];foreach(array_unique($ids)as$id)if(isset($eligible[$id])){$member=$eligible[$id];$options[]='<option value="'.$id.'" '.(in_array($id,$assigned,true)?'selected':'').'>'.$escape(trim($member['firstname'].' '.$member['lastname'])).'</option>';}
        $milestones=['<option value=""></option>'];foreach($data['milestones']as$m)$milestones[]='<option value="'.$escape($m['id']).'" '.((string)($data['task']->milestone??'')===(string)$m['id']?'selected':'').'>'.$escape($m['name']).'</option>';
        $meta=IssuerStorage::query($this->db,'SELECT billing_type FROM '.$this->p.'company_project_native_metadata WHERE issuer_id=? AND project_id=?',[$i,$project['source_id']])->row_array();$billing=$meta?(int)$meta['billing_type']:null;if($billing!==null&&!in_array($billing,[1,2,3],true))throw new DomainException('native_task_form_invalid');
        $date=CompanySettingsFormats::dates()[(new CompanySettingsStorage($this->db,$this->p))->get($i,'dateformat')]??throw new DomainException('native_task_form_date_invalid');
        $rules=(new CompanyProjectPortalRules($this->db,$this->p))->get($i,$a,$project['source_id']);$deadline=$source['due_date'];
        $out=['deadline'=>$deadline,'deadline_formatted'=>$deadline===null?null:(new DateTimeImmutable($deadline))->format($date),'allow_to_view_tasks'=>$rules['rules']['view_tasks'],'billing_type'=>$billing,'milestones'=>$milestones,'assignees'=>$options,'native_milestone_revision'=>$data['native_task_form']['fields']['native_milestone_revision']];
        $this->authorize($i,$a,$existing?'edit':'create');if((new CompanyProjects($this->db,$this->p))->get($i,$a,$project['source_id'])!==$source||(new NativeCompanyWorkAssignments($this->db,$this->p))->read($i,$a,'project',$project['source_id'])!==$members)throw new DomainException('native_task_form_changed');
        $check=$existing?$this->editForm($i,$a,$canonicalTask,gmdate('Y-m-d')):$this->newForm($i,$a,['rel_type'=>'project','rel_id'=>$canonicalProject],gmdate('Y-m-d'));if(json_encode($check,JSON_THROW_ON_ERROR)!==json_encode($data,JSON_THROW_ON_ERROR)||IssuerStorage::query($this->db,'SELECT billing_type FROM '.$this->p.'company_project_native_metadata WHERE issuer_id=? AND project_id=?',[$i,$project['source_id']])->row_array()!==$meta||(new CompanyProjectPortalRules($this->db,$this->p))->get($i,$a,$project['source_id'])!==$rules)throw new DomainException('native_task_form_changed');
        if((new CompanyTasks($this->db,$this->p))->nativeMilestoneSelection($i,$a,$project['source_id'])['revision']!==$out['native_milestone_revision'])throw new DomainException('native_task_milestone_stale');return$out;
    }
    private static function check(array$wire,string$key):int{if(!array_key_exists($key,$wire))return 0;if(!in_array($wire[$key],['on','1',1],true))throw new DomainException('native_task_form_invalid');return 1;}
    public function save(int$i,int$a,$canonicalId,array$wire):string
    {
        $new=$canonicalId===null||$canonicalId==='';$this->authorize($i,$a,$new?'create':'edit');NativeCompanyTaskFormSchema::assertValid($this->db,$this->p);
        $allowed=['name','description','startdate','duedate','priority','hourly_rate','is_public','billable','visible_to_client','rel_type','rel_id','assignees','followers','milestone','repeat_every','repeat_every_custom','repeat_type_custom','cycles','checklist_items','tags','custom_fields','datefinished','native_request_key','native_revision','native_fields_revision','native_tags_revision','native_declaration_confirmed','native_declaration_flags','native_milestone_revision','_native_company_context','_native_company_csrf'];
        if(array_diff(array_keys($wire),$allowed))throw new DomainException('native_task_form_invalid');
        // Never silently discard an original action while its transaction compositor is unfinished.
        foreach(['repeat_every']as$field)if(!empty($wire[$field]))throw new DomainException('native_task_form_action_not_composed');
        $old=null;$identity=null;$type=$wire['rel_type']??'';$relation=$wire['rel_id']??'';
        if(!$new){$identity=NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'task',$canonicalId,'edit');if(!in_array($identity['source_kind'],['company_task','company_standalone_task'],true))throw new DomainException('native_task_legacy_writer_required');$writer=$identity['source_kind']==='company_task'?new CompanyTasks($this->db,$this->p):new CompanyStandaloneTasks($this->db,$this->p);$old=$writer->get($i,$a,$identity['source_id']);}
        if($old!==null&&$old['native_fields_json']===null){
            if(($wire['native_declaration_confirmed']??null)!=='1'||!is_array($wire['native_declaration_flags']??null)||array_keys($wire['native_declaration_flags'])!==['is_public','billable','visible_to_client'])throw new DomainException('native_task_declaration_required');
            foreach(['is_public','billable','visible_to_client']as$flag)if(!in_array($wire['native_declaration_flags'][$flag],['0','1'],true)||(int)$wire['native_declaration_flags'][$flag]!==self::check($wire,$flag))throw new DomainException('native_task_declaration_required');
        }
        if($type==='project'){
            $ref=NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'project',$relation,'view');if($ref['source_kind']!=='company_project')throw new DomainException('native_task_relation_writer_required');$writer=new CompanyTasks($this->db,$this->p);$source='company_task';$project=$ref['source_id'];
        }elseif($type==='ticket'){
            $ref=NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'ticket',$relation,'view');$writer=new CompanyStandaloneTasks($this->db,$this->p);$source='company_standalone_task';$project=null;
        }elseif($type==='invoice'){
            $ref=NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'invoice',$relation,'view');if(!in_array($ref['source_kind'],['invoice','company_invoice_draft'],true))throw new DomainException('native_task_relation_writer_required');$writer=new CompanyStandaloneTasks($this->db,$this->p);$source='company_standalone_task';$project=null;
        }elseif($type===''&&($relation===''||$relation===null)){$writer=new CompanyStandaloneTasks($this->db,$this->p);$source='company_standalone_task';$project=null;}
        else throw new DomainException('native_task_relation_writer_required');
        if($identity!==null&&$identity['source_kind']!==$source)throw new DomainException('company_project_reference_denied');
        $creationPreferences=$old===null?$writer->creationDefaults($i,$a):null;$startDate=$this->date($i,$wire['startdate']??null);$status=$old['status']??CompanyTaskPreferences::forStartDate($creationPreferences[CompanyTaskPreferences::STATUS],$startDate);$workflow=match($status){'todo'=>1,'awaiting_feedback'=>2,'testing'=>3,'in_progress'=>4,'done'=>5,default=>throw new DomainException('native_task_form_invalid')};
        if($workflow!==5&&!empty($wire['datefinished']))throw new DomainException('native_task_form_invalid');$finished=$workflow===5?$this->dateTime($i,$wire['datefinished']??null):null;
        $input=['assignee_id'=>$old===null?null:($old['assignee_id']===null?null:(int)$old['assignee_id']),'name'=>$wire['name']??null,'description'=>$wire['description']??'','due_date'=>$this->date($i,$wire['duedate']??null,true),'status'=>$status,'checklist'=>$old['checklist']??[],'priority'=>$wire['priority']??null];if($project!==null)$input['project_id']=$project;
        $fields=['start_date'=>$startDate,'billable'=>self::check($wire,'billable'),'hourly_rate'=>$wire['hourly_rate']??null,'is_public'=>self::check($wire,'is_public'),'visible_to_client'=>self::check($wire,'visible_to_client'),'workflow_status'=>$workflow,'date_finished'=>$finished,'relation_type'=>$type===''?null:$type,'relation_source_kind'=>$type===''?null:$ref['source_kind'],'relation_source_id'=>$type===''?null:$ref['source_id']];
        $tags=$wire['tags']??'';if(!is_string($tags))throw new DomainException('native_task_form_invalid');$tags=$tags===''?[]:array_map('trim',explode(',',$tags));$custom=$wire['custom_fields']??[];if(!is_array($custom)||array_diff(array_keys($custom),['tasks'])||!is_array($custom['tasks']??[]))throw new DomainException('native_task_form_invalid');$decorations=['tags'=>$tags,'custom_fields'=>$custom['tasks']??[]];
        $templates=$wire['checklist_items']??[];if(!is_array($templates)||!array_is_list($templates)||count($templates)>30||count(array_unique($templates,SORT_STRING))!==count($templates))throw new DomainException('native_task_form_invalid');
        if(!$new&&!empty($wire['followers']))throw new DomainException('native_task_form_invalid');if(!$new&&$templates)throw new DomainException('native_task_form_invalid');$templateSources=[];
        if($new&&$templates){require_once __DIR__.'/NativeCompanyTaskChecklistTemplates.php';$catalog=new NativeCompanyTaskChecklistTemplates($this->db,$this->p);$input['checklist']=[];foreach($templates as$templateId){$t=$catalog->get($i,$a,$templateId);$input['checklist'][]=['text'=>$t['description'],'done'=>0];$templateSources[]=['id'=>$t['id'],'revision'=>$t['revision']];}}
        $milestone=[];if($project!==null)$milestone=['milestone'=>['id'=>($wire['milestone']??'')===''?null:IssuerPolicy::id($wire['milestone']),'revision'=>$wire['native_milestone_revision']??'']];elseif(!empty($wire['milestone'])||isset($wire['native_milestone_revision']))throw new DomainException('native_task_form_invalid');
        $key=$wire['native_request_key']??'';
        if($new){$assignees=$wire['assignees']??[];if(!is_array($assignees))throw new DomainException('native_task_form_invalid');$id=$writer->createNative($i,$a,$input,$fields,$key,['assignees'=>$assignees,'followers'=>$wire['followers']??[],'template_sources'=>$templateSources,'creation_preferences'=>$creationPreferences]+$decorations+$milestone);}
        else{if(array_key_exists('assignees',$wire))throw new DomainException('native_task_form_invalid');$revision=$wire['native_revision']??null;$fieldRevision=$wire['native_fields_revision']??null;if($fieldRevision==='')$fieldRevision=null;$id=$writer->updateNative($i,$a,$identity['source_id'],$revision,$fieldRevision,$input,$fields,$key,['tags'=>$tags,'tags_revision'=>$wire['native_tags_revision']??'','custom_fields'=>$custom['tasks']??[]]+$milestone);}
        $this->authorize($i,$a,$new?'create':'edit');return NativeCompanyResourceId::encode($source,$id);
    }
}
