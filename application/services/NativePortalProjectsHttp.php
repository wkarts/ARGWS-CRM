<?php

declare(strict_types=1);

require_once __DIR__.'/NativePortalIdentity.php';
require_once __DIR__.'/NativePortalPresentation.php';
require_once __DIR__.'/NativeCompanyResourceId.php';
require_once __DIR__.'/CompanyPortalOperations.php';

/** Contact-bound project workspace on the original Clients project routes. */
final class NativePortalProjectsHttp
{
    private const GROUPS=['project_overview','project_tasks','project_files','project_milestones','project_timesheets','project_gantt','project_discussions'];
    private int $issuer;
    private string $secret;
    private NativePortalIdentity $identities;
    private array $identity;
    private CompanyPortal $portal;
    private CompanyPortalOperations $reader;

    public function __construct(private $ci)
    {
        $method=strtolower((string)$ci->router->fetch_method());
        if(!PortalRequestScope::sealedFor($ci)||strtolower((string)$ci->router->fetch_class())!=='clients'||!in_array($method,['projects','project'],true))throw new DomainException('company_portal_operation_unavailable');
        $this->issuer=PortalRequestScope::issuerId();
        $secret=$ci->config->item('encryption_key');
        if(!is_string($secret)||strlen($secret)<32)throw new RuntimeException('issuer_portal_secret_required');
        $this->secret=$secret;$this->identities=new NativePortalIdentity($ci->db,db_prefix(),$secret);
        $identity=$this->identities->current($ci->session,$this->issuer);
        if(!$identity){redirect(NativePortalPresentation::url('authentication/login'),'location',303);throw new DomainException('company_portal_operation_unavailable');}
        $this->identity=$identity;$this->portal=new CompanyPortal($ci->db,db_prefix(),$secret);
        $this->reader=new CompanyPortalOperations($ci->db,db_prefix(),$this->portal);
    }

    public function index($status=''):void
    {
        try{
            $this->readVerb();$status=$status===null?'':(string)$status;
            if(!in_array($status,['','active','completed','cancelled'],true))throw new DomainException('company_portal_operation_unavailable');
            $after=$this->positive($this->ci->input->get('after',false)??0,true)??0;
            $page=$this->reader->page($this->identity['binding'],$this->issuer,'projects',$after);
            if($page===null)throw new DomainException('company_portal_operation_unavailable');
            $items=[];foreach($page['items']as$row){if($status!==''&&$row['status']!==$status)continue;$items[]=$this->project($row);}
            $this->confirm();$this->ci->data(['title'=>_l('clients_my_projects'),'native_portal_projects'=>['items'=>$items,'next'=>$page['next']],'native_portal_project_status'=>$status]);
            $this->ci->view('projects');$this->ci->layout();
        }catch(DomainException$error){$this->deny($error);}
    }

    public function detail($canonical):void
    {
        try{
            $project=$this->sourceProject($canonical);$verb=$this->verb();
            if($verb==='POST'){$this->mutate($project);return;}
            if(!in_array($verb,['GET','HEAD'],true))$this->methodDenied('GET, HEAD, POST');
            $group=$this->ci->input->get('group',false)??'project_overview';
            if(!is_string($group)||!in_array($group,self::GROUPS,true))throw new DomainException('company_portal_operation_unavailable');
            $record=$this->reader->detail($this->identity['binding'],$this->issuer,'projects',$project);
            $data=['native_portal_project'=>$this->project($record),'native_portal_project_group'=>$group,'native_portal_project_tasks'=>null,'native_portal_project_task'=>null,'native_portal_project_task_controls'=>null,'native_portal_project_task_create'=>null,'native_portal_project_author_tasks'=>null,'native_portal_project_task_receipt'=>null,'native_portal_project_file_controls'=>null,'native_portal_project_file_receipt'=>null,'native_portal_project_milestones'=>null,'native_portal_project_timesheets'=>null,'native_portal_project_gantt'=>null,'native_portal_project_discussions'=>null,'native_portal_project_discussion'=>null,'native_portal_project_discussion_receipt'=>null];
            if($group==='project_tasks')$this->tasks($project,$data);
            elseif($group==='project_files')$this->files($project,$data);
            elseif($group==='project_milestones'){$data['native_portal_project_milestones']=$this->milestones($project);$this->required($data['native_portal_project_milestones']);}
            elseif($group==='project_timesheets'){$data['native_portal_project_timesheets']=$this->timesheets($project);$this->required($data['native_portal_project_timesheets']);}
            elseif($group==='project_gantt'){$data['native_portal_project_gantt']=$this->gantt($project);$this->required($data['native_portal_project_gantt']);}
            elseif($group==='project_discussions')$this->discussions($project,$data);
            $this->confirm();$data['title']=$record['name'];$this->ci->data($data);$this->ci->view('project');$this->ci->layout();
        }catch(DomainException$error){$this->deny($error);}
    }

    private function tasks(int$project,array&$data):void
    {
        require_once __DIR__.'/CompanyPortalProjectTasks.php';require_once __DIR__.'/CompanyPortalTaskWriteControls.php';require_once __DIR__.'/CompanyPortalTaskReceipts.php';
        $after=$this->positive($this->ci->input->get('after',false)??0,true)??0;
        $reader=new CompanyPortalProjectTasks($this->ci->db,db_prefix(),$this->secret);
        $page=$reader->page($this->identity['binding'],$this->issuer,$project,$after);$this->required($page);$data['native_portal_project_tasks']=$page;
        $selected=$this->ci->input->get('taskid',false);
        if($selected!==null){$task=$this->sourceTask($selected);$data['native_portal_project_task']=$reader->detail($this->identity['binding'],$this->issuer,$project,$task);}
        $controls=new CompanyPortalTaskWriteControls($this->ci->db,db_prefix(),$this->secret);
        $data['native_portal_project_task_create']=$controls->get($this->identity['binding'],$this->issuer,$project);
        if(isset($task))$data['native_portal_project_task_controls']=$controls->get($this->identity['binding'],$this->issuer,$project,$task);
        $receipts=new CompanyPortalTaskReceipts($this->ci->db,db_prefix(),$this->secret);
        $authorAfter=$this->positive($this->ci->input->get('author_after',false)??0,true)??0;
        $data['native_portal_project_author_tasks']=$receipts->page($this->identity['binding'],$this->issuer,$project,$authorAfter);
        $receipt=$this->ci->input->get('task_receipt',false);
        if($receipt!==null){
            if(!is_string($receipt))throw new DomainException('company_portal_task_unavailable');
            $data['native_portal_project_task_receipt']=$receipts->get($this->identity['binding'],$this->issuer,$project,$receipt);
            $data['native_portal_project_task_controls']=$controls->get($this->identity['binding'],$this->issuer,$project,$data['native_portal_project_task_receipt']['task_id']);
        }
    }

    private function files(int$project,array&$data):void
    {
        require_once __DIR__.'/CompanyProjectContactFileReceipts.php';$reader=new CompanyProjectContactFileReceipts($this->ci->db,db_prefix(),$this->secret);
        $data['native_portal_project_file_controls']=$reader->controls($this->identity['binding'],$this->issuer,$project);
        $receipt=$this->ci->input->get('file_receipt',false);
        if($receipt!==null){if(!is_string($receipt))throw new DomainException('company_project_file_unavailable');$data['native_portal_project_file_receipt']=$reader->get($this->identity['binding'],$this->issuer,$project,$receipt);}
    }

    private function milestones(int$project):?array
    {
        require_once __DIR__.'/CompanyPortalProjectMilestones.php';return(new CompanyPortalProjectMilestones($this->ci->db,db_prefix(),$this->secret))->page($this->identity['binding'],$this->issuer,$project);
    }
    private function timesheets(int$project):?array
    {
        require_once __DIR__.'/CompanyPortalProjectTimesheets.php';$cursor=$this->ci->input->get('before',false);if($cursor!==null&&!is_string($cursor))throw new DomainException('company_portal_operation_unavailable');return(new CompanyPortalProjectTimesheets($this->ci->db,db_prefix(),$this->secret))->page($this->identity['binding'],$this->issuer,$project,$cursor);
    }
    private function gantt(int$project):?array
    {
        require_once __DIR__.'/CompanyPortalProjectGantt.php';return(new CompanyPortalProjectGantt($this->ci->db,db_prefix(),$this->secret))->page($this->identity['binding'],$this->issuer,$project);
    }

    private function discussions(int$project,array&$data):void
    {
        require_once __DIR__.'/CompanyPortalProjectDiscussions.php';$reader=new CompanyPortalProjectDiscussions($this->ci->db,db_prefix(),$this->secret);
        $page=$reader->page($this->identity['binding'],$this->issuer,$project);$this->required($page);$data['native_portal_project_discussions']=$page;
        $id=$this->ci->input->get('discussion_id',false);if($id!==null)$data['native_portal_project_discussion']=$reader->detail($this->identity['binding'],$this->issuer,$project,$this->positive($id));
        $receipt=$this->ci->input->get('discussion_receipt',false);if($receipt!==null){if(!is_string($receipt))throw new DomainException('company_project_discussion_unavailable');$data['native_portal_project_discussion_receipt']=$reader->receipt($this->identity['binding'],$this->issuer,$project,$receipt);}
        $reader->confirm($this->identity['binding'],$this->issuer,$project,$page,$data['native_portal_project_discussion'],$data['native_portal_project_discussion_receipt']);
    }

    private function mutate(int$project):void
    {
        PortalRequestScope::assertMutation($this->ci);$posted=$this->positive($this->ci->input->post('project_id',false));
        if($posted!==$project)throw new DomainException('company_portal_operation_unavailable');
        $action=$this->ci->input->post('action',false);if(!is_string($action))throw new DomainException('company_portal_operation_unavailable');
        require_once __DIR__.'/CompanyPortalTaskHttp.php';
        if(in_array($action,CompanyPortalTaskHttp::ACTIONS,true)){CompanyPortalTaskHttp::handle($this->ci,$this->identity['binding'],$this->issuer,$action);return;}
        if($action==='project_task_file'){
            require_once __DIR__.'/CompanyPortalProjectTasks.php';$task=$this->positive($this->ci->input->post('task_id',false));$key=$this->ci->input->post('file_key',false);
            if(!is_string($key))throw new DomainException('company_portal_task_unavailable');$reader=new CompanyPortalProjectTasks($this->ci->db,db_prefix(),$this->secret);
            $file=$reader->file($this->identity['binding'],$this->issuer,$project,$task,$key);$reader->confirmFile($this->identity['binding'],$this->issuer,$project,$task,$key,$file['content_hash']);$this->fileResponse($file);return;
        }
        require_once __DIR__.'/CompanyProjectContactFilesHttp.php';
        if(in_array($action,CompanyProjectContactFilesHttp::ACTIONS,true)){CompanyProjectContactFilesHttp::handle($this->ci,$this->identity['binding'],$this->issuer,$action);return;}
        if(in_array($action,['project_discussion_open','project_discussion_comment'],true)){require_once __DIR__.'/CompanyProjectDiscussionsHttp.php';CompanyProjectDiscussionsHttp::portal($this->ci,$this->identity['binding'],$this->issuer,$action);return;}
        if(in_array($action,['project_discussion_edit','project_discussion_delete','project_discussion_file_upload','project_discussion_file_hide','project_discussion_file_download'],true)){require_once __DIR__.'/CompanyProjectDiscussionExtrasHttp.php';CompanyProjectDiscussionExtrasHttp::portal($this->ci,$this->identity['binding'],$this->issuer,$action);return;}
        throw new DomainException('company_portal_operation_unavailable');
    }

    private function fileResponse(array$file):void
    {
        $this->confirm();$this->ci->output->set_header('Cache-Control: private, no-store')->set_header('X-Content-Type-Options: nosniff')->set_header("Content-Security-Policy: sandbox; default-src 'none'")
            ->set_header('Content-Disposition: attachment; filename="download"; filename*=UTF-8\'\''.rawurlencode($file['filename']))->set_content_type($file['mime'])->set_output($file['content']);
    }
    private function project(array$row):array{return$row+['canonical_id'=>NativeCompanyResourceId::encode('company_project',(string)$row['id'])];}
    private function sourceProject($value):int
    {
        try{$d=NativeCompanyResourceId::decode('project',$value);}catch(DomainException$e){throw new DomainException('company_portal_operation_unavailable',0,$e);}
        if($d['source_kind']!=='company_project')throw new DomainException('company_portal_operation_unavailable');return IssuerPolicy::id($d['source_id']);
    }
    private function sourceTask($value):int
    {
        try{$d=NativeCompanyResourceId::decode('task',$value);}catch(DomainException$e){throw new DomainException('company_portal_task_unavailable',0,$e);}
        if($d['source_kind']!=='company_task')throw new DomainException('company_portal_task_unavailable');return IssuerPolicy::id($d['source_id']);
    }
    private function positive($value,bool$optional=false):?int
    {
        if($optional&&($value===0||$value==='0'||$value===null||$value===''))return null;
        if((!is_int($value)&&!is_string($value))||!preg_match('/^[1-9][0-9]{0,9}$/D',(string)$value))throw new DomainException('company_portal_operation_unavailable');return(int)$value;
    }
    private function required($value):void{if($value===null)throw new DomainException('company_portal_operation_unavailable');}
    private function verb():string{return strtoupper((string)$this->ci->input->method(true));}
    private function readVerb():void{if(!in_array($this->verb(),['GET','HEAD'],true))$this->methodDenied('GET, HEAD');}
    private function methodDenied(string$allow):void{$this->ci->output->set_header('Allow: '.$allow);show_error('Método não permitido.',405);}
    private function confirm():void{if($this->identities->current($this->ci->session,$this->issuer)!==$this->identity)throw new DomainException('company_portal_operation_unavailable');}
    private function deny(DomainException$error):void
    {
        $reason=$error->getMessage();if(str_contains($reason,'unavailable')){show_404();return;}
        $status=match($reason){'native_portal_csrf_denied'=>403,'company_portal_task_stale','company_portal_task_request_conflict','company_project_file_stale','company_project_file_request_conflict','company_project_discussion_stale','company_project_discussion_request_conflict'=>409,'company_portal_task_limit','company_project_file_limit','company_project_discussion_limit','company_operation_file_limit'=>429,default=>422};
        show_error($status===409?'O projeto mudou. Reabra antes de salvar.':($status===429?'O limite deste projeto foi atingido.':'Confira os campos e o arquivo antes de enviar.'),$status);
    }
}


