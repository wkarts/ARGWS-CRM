<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyProjectFormStateSchema.php';
require_once __DIR__.'/CompanyProjectPortalRulesSchema.php';
require_once __DIR__.'/CompanyPortalTaskWriteSchema.php';
require_once __DIR__.'/CompanyProjectMilestonesSchema.php';
require_once __DIR__.'/CompanyProjectTimesheetsSchema.php';
require_once __DIR__.'/CompanyProjectGanttSchema.php';
require_once __DIR__.'/CompanyProjectDiscussionsSchema.php';
require_once __DIR__.'/CompanyProjectContactFilesSchema.php';

/** One native project form composes existing granular rule sources, never a parallel permission store. */
final class NativeCompanyProjectFormState
{
    public const EXTRA_FLAGS=['view_finance_overview','view_activity_log','hide_tasks_on_main_tasks_table'];
    private const MAP=[
        'company_project_portal_rules'=>['view_tasks','view_task_comments','view_task_checklist_items','view_task_attachments','view_task_total_logged_time','view_team_members'],
        'company_project_write_rules'=>['create_tasks','edit_tasks','comment_on_tasks','upload_on_tasks'],
        'company_milestone_portal_rules'=>['view_milestones','project_milestones'],
        'company_project_timesheet_rules'=>['view_timesheets','project_timesheets'],
        'company_project_gantt_rules'=>['view_gantt','project_gantt'],
        'company_project_discussion_rules'=>['open_discussions','project_discussions'],
        'company_project_file_upload_rules'=>['upload_files','project_files'],
    ];
    private const RULE_FEATURES=['project_milestones','project_timesheets','project_gantt','project_discussions','project_files'];
    public const FEATURES=['project_overview','project_tasks','project_timesheets','project_milestones','project_files','project_discussions','project_gantt','project_tickets','project_contracts','project_invoices','project_estimates','project_expenses','project_credit_notes','project_subscriptions','project_proposals','project_notes','project_activity'];
    public const FLAGS=['view_tasks','create_tasks','edit_tasks','comment_on_tasks','view_task_comments','view_task_attachments','view_task_checklist_items','upload_on_tasks','view_task_total_logged_time','view_finance_overview','upload_files','open_discussions','view_milestones','view_gantt','view_timesheets','view_activity_log','view_team_members','hide_tasks_on_main_tasks_table'];
    public function __construct(private$db,private string$p){NativeCompanyProjectFormStateSchema::definitions($p);}
    public static function tables():array{return array_merge(['issuers_lock','issuers_registry','issuers_staff','issuers_installation_identity','issuers_resource_permissions','staff','contacts','issuers_audit','company_projects','company_project_requests',NativeCompanyProjectFormStateSchema::TABLE],array_keys(self::MAP));}
    private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public static function normalize(array$input):array
    {
        $keys=['flags','features','contact_notification','notify_contacts','date_finished'];
        if(array_diff(array_keys($input),$keys)||array_diff($keys,array_keys($input))||!is_array($input['flags'])||array_diff(array_keys($input['flags']),self::FLAGS)||array_diff(self::FLAGS,array_keys($input['flags']))||!is_array($input['features'])||!array_is_list($input['features'])||array_diff($input['features'],self::FEATURES)||count(array_unique($input['features']))!==count($input['features'])||!in_array('project_overview',$input['features'],true))throw new DomainException('native_project_form_invalid');
        $flags=[];foreach(self::FLAGS as$f){if(!in_array($input['flags'][$f],[0,1,'0','1'],true))throw new DomainException('native_project_form_invalid');$flags[$f]=(int)$input['flags'][$f];}
        // Existing task exposure and mutation fences remain authoritative.
        if(!$flags['view_tasks'])foreach(array_merge(CompanyProjectPortalRulesSchema::FLAGS,CompanyPortalTaskWriteSchema::FLAGS)as$f)if($f!=='view_tasks'&&$flags[$f])throw new DomainException('native_project_form_invalid');
        if(($flags['comment_on_tasks']&&!$flags['view_task_comments'])||($flags['upload_on_tasks']&&!$flags['view_task_attachments']))throw new DomainException('native_project_form_invalid');
        if(!in_array($input['contact_notification'],[0,1,2,'0','1','2'],true)||!is_array($input['notify_contacts'])||!array_is_list($input['notify_contacts'])||count($input['notify_contacts'])>500)throw new DomainException('native_project_form_invalid');
        $contacts=array_map([IssuerPolicy::class,'id'],$input['notify_contacts']);if(count(array_unique($contacts))!==count($contacts))throw new DomainException('native_project_form_invalid');sort($contacts,SORT_NUMERIC);$mode=(int)$input['contact_notification'];
        if(($mode===2&&$contacts===[])||($mode!==2&&$contacts!==[]))throw new DomainException('native_project_form_invalid');
        $finished=$input['date_finished'];if($finished!==null&&(!is_string($finished)||!($date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$finished))||$date->format('Y-m-d H:i:s')!==$finished))throw new DomainException('native_project_form_invalid');
        $features=array_values(array_intersect(self::FEATURES,$input['features']));return['flags'=>$flags,'features'=>$features,'contact_notification'=>$mode,'notify_contacts'=>$contacts,'date_finished'=>$finished];
    }
    private function authorize(int$issuer,int$actor,int$project,string$cap,?object$proof):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,self::tables());NativeCompanyProjectFormStateSchema::assertValid($this->db,$this->p);foreach([CompanyProjectPortalRulesSchema::class,CompanyPortalTaskWriteSchema::class,CompanyProjectMilestonesSchema::class,CompanyProjectTimesheetsSchema::class,CompanyProjectGanttSchema::class,CompanyProjectDiscussionsSchema::class,CompanyProjectContactFilesSchema::class]as$schema)$schema::assertValid($this->db,$this->p);
        $acl=new IssuerResourceAccess($this->db,$this->p);$acl->lockFinancialIdentity($issuer,$actor);
        if(!$acl->can($issuer,$actor,'projects','view')||!$acl->can($issuer,$actor,'projects',$cap))throw new DomainException('native_project_access_denied');
        if($proof!==null)CompanyProjects::assertNativeCreationProof($proof,$this->db,$this->p,$issuer,$actor,$project);
        $source=$this->rows('SELECT * FROM '.$this->p.'company_projects WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$project])[0]??null;if(!$source)throw new DomainException('native_project_access_denied');return$source;
    }
    private function snapshot(int$issuer,int$project):array
    {
        $out=[];foreach(self::MAP as$table=>$fields){$rows=$this->rows('SELECT * FROM '.$this->p.$table.' WHERE issuer_id=? AND project_id=? FOR UPDATE',[$issuer,$project]);if(count($rows)>1)throw new RuntimeException('native_project_form_unconfirmed');$row=$rows[0]??null;if($row){foreach($fields as$f)if(!in_array((string)$row[$f],['0','1'],true))throw new RuntimeException('native_project_form_unconfirmed');if((int)$row['revision']<1)throw new RuntimeException('native_project_form_unconfirmed');}$out[$table]=$row;}
        $out[NativeCompanyProjectFormStateSchema::TABLE]=$this->rows('SELECT * FROM '.$this->p.NativeCompanyProjectFormStateSchema::TABLE.' WHERE issuer_id=? AND project_id=? FOR UPDATE',[$issuer,$project])[0]??null;return$out;
    }
    private static function hash(array$snapshot):string{return hash('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR));}
    public function readInOwnedTransaction(int$issuer,int$actor,int$project):array
    {
        $source=$this->authorize($issuer,$actor,$project,'view',null);$snapshot=$this->snapshot($issuer,$project);$flags=array_fill_keys(self::FLAGS,0);$features=['project_overview'];
        foreach(self::MAP as$table=>$fields)foreach($fields as$f){$value=(int)($snapshot[$table][$f]??0);if(in_array($f,self::FEATURES,true)){if($value)$features[]=$f;}else$flags[$f]=$value;}
        $state=$snapshot[NativeCompanyProjectFormStateSchema::TABLE];$extra=null;
        if($state){$extra=json_decode($state['payload_json'],true,32,JSON_THROW_ON_ERROR);if(!is_array($extra)||array_keys($extra)!==['flags','features','contact_notification','notify_contacts','date_finished']||!is_array($extra['flags'])||array_keys($extra['flags'])!==self::EXTRA_FLAGS||!is_array($extra['features'])||array_intersect($extra['features'],self::RULE_FEATURES)!==[])throw new RuntimeException('native_project_form_unconfirmed');foreach(self::EXTRA_FLAGS as$f)$flags[$f]=$extra['flags'][$f]??null;$features=array_values(array_unique(array_merge($features,$extra['features'])));}
        $data=self::normalize(['flags'=>$flags,'features'=>$features,'contact_notification'=>$extra['contact_notification']??0,'notify_contacts'=>$extra['notify_contacts']??[],'date_finished'=>$extra['date_finished']??null]);
        // Absence is reported, not relabelled as an explicitly saved native configuration.
        if($this->authorize($issuer,$actor,$project,'view',null)!==$source||$this->snapshot($issuer,$project)!==$snapshot)throw new DomainException('native_project_form_stale');return['values'=>$data,'revision'=>self::hash($snapshot),'declared'=>$state!==null];
    }
    public function saveInOwnedTransaction(int$issuer,int$actor,int$project,string$expected,array$input,?object$proof=null):void
    {
        $cap=$proof===null?'edit':'create';$source=$this->authorize($issuer,$actor,$project,$cap,$proof);$data=self::normalize($input);$snapshot=$this->snapshot($issuer,$project);
        if(!preg_match('/^[a-f0-9]{64}$/D',$expected)||!hash_equals($expected,self::hash($snapshot)))throw new DomainException('native_project_form_stale');
        if($data['date_finished']!==null&&$source['status']!=='completed')throw new DomainException('native_project_form_invalid');
        foreach($data['notify_contacts']as$contact)if(count($this->rows('SELECT id FROM '.$this->p.'contacts WHERE id=? AND userid=? AND active=1 FOR UPDATE',[$contact,$source['client_id']]))!==1)throw new DomainException('native_project_contact_denied');
        $at=gmdate('Y-m-d H:i:s');$after=[];
        foreach(self::MAP as$table=>$fields){$old=$snapshot[$table];$record=[];foreach($fields as$f)$record[$f]=in_array($f,self::FEATURES,true)?(int)in_array($f,$data['features'],true):$data['flags'][$f];
            $record+=['revision'=>(int)($old['revision']??0)+1,'actor_id'=>$actor,'updated_at'=>$at];if($record['revision']>2147483647)throw new DomainException('native_project_form_stale');$this->put($table,$issuer,$project,$old,$record);$after[$table]=$record;}
        $payload=$data;$payload['features']=array_values(array_diff($data['features'],self::RULE_FEATURES));$payload['flags']=array_intersect_key($data['flags'],array_fill_keys(self::EXTRA_FLAGS,true));$old=$snapshot[NativeCompanyProjectFormStateSchema::TABLE];$record=['revision'=>(int)($old['revision']??0)+1,'payload_json'=>json_encode($payload,JSON_THROW_ON_ERROR),'actor_id'=>$actor,'updated_at'=>$at];$this->put(NativeCompanyProjectFormStateSchema::TABLE,$issuer,$project,$old,$record);$after[NativeCompanyProjectFormStateSchema::TABLE]=$record;
        $audit=['actor_id'=>$actor,'event'=>'native_project_form_saved','issuer_id'=>$issuer,'subject_id'=>$project,'created_at'=>$at,'metadata_json'=>json_encode(['operation_nonce'=>bin2hex(random_bytes(16)),'before_hash'=>$expected],JSON_THROW_ON_ERROR)];IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit('.implode(',',array_keys($audit)).') VALUES(?,?,?,?,?,?)',array_values($audit));$auditId=(int)$this->db->insert_id();$receipt=$this->rows('SELECT '.implode(',',array_keys($audit)).' FROM '.$this->p.'issuers_audit WHERE id=?',[$auditId])[0]??[];
        if($auditId<1||array_map('strval',$receipt)!==array_map('strval',$audit)||$this->authorize($issuer,$actor,$project,$cap,$proof)!==$source)throw new RuntimeException('native_project_form_unconfirmed');
        $current=$this->snapshot($issuer,$project);foreach($after as$table=>$fields)foreach($fields as$f=>$v)if((string)($current[$table][$f]??'')!==(string)$v)throw new RuntimeException('native_project_form_unconfirmed');
        foreach($data['notify_contacts']as$contact)if(count($this->rows('SELECT id FROM '.$this->p.'contacts WHERE id=? AND userid=? AND active=1 FOR UPDATE',[$contact,$source['client_id']]))!==1)throw new DomainException('native_project_contact_denied');
    }
    private function put(string$table,int$issuer,int$project,?array$old,array$record):void
    {
        if($old)IssuerStorage::query($this->db,'UPDATE '.$this->p.$table.' SET '.implode(',',array_map(static fn($f)=>$f.'=?',array_keys($record))).' WHERE issuer_id=? AND project_id=? AND revision=?',array_merge(array_values($record),[$issuer,$project,$old['revision']]));
        else{$record=['issuer_id'=>$issuer,'project_id'=>$project]+$record;IssuerStorage::query($this->db,'INSERT INTO '.$this->p.$table.'('.implode(',',array_keys($record)).') VALUES('.implode(',',array_fill(0,count($record),'?')).')',array_values($record));}
    }
}
