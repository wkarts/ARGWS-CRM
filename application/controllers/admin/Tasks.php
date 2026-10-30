<?php

use app\services\utilities\Date;
use app\services\tasks\TasksKanban;

defined('BASEPATH') or exit('No direct script access allowed');
require_once APPPATH.'services/NativeCompanySpecializedOperations.php';

/**
 * @property-read Staff_model $staff_model
 * @property-read Tasks_model $tasks_model
 * @property-read Projects_model $projects_model
 */
class Tasks extends AdminController
{
    use NativeCompanySpecializedOperations;

    public function copies() { $this->nativeTaskCopies(); }
    public function recurring() { $this->nativeTaskRecurring(); }
    public function standalone() { $this->nativeTaskStandalone(); }
    public function billing() { $this->nativeTaskBilling(); }
    public function time_batches() { $this->nativeTaskTimeBatches(); }
    public function __construct()
    {
        parent::__construct();
        if (!$this->nativeCompanyTasks()) { $this->load->model('projects_model'); }
    }

    private function nativeCompanyTasks(): bool
    {
        return class_exists('CompanyRequestScope', false) && CompanyRequestScope::nativeFor($this);
    }

    /* Open also all taks if user access this /tasks url */
    public function index($id = '')
    {
        $this->list_tasks($id);
    }

    /* List all tasks */
    public function list_tasks($id = '')
    {
        if ($this->nativeCompanyTasks()) {
            $this->nativeTaskIndex($id);
            return;
        }
        close_setup_menu();
        // If passed from url
        $data['custom_view'] = $this->input->get('custom_view') ? $this->input->get('custom_view') : '';
        $data['taskid']      = $id;

        if ($this->input->get('kanban')) {
            $this->switch_kanban(0, true);
        }

        $data['switch_kanban'] = false;
        $data['bodyclass']     = 'tasks-page';

        if ($this->session->userdata('tasks_kanban_view') == 'true') {
            $data['switch_kanban'] = true;
            $data['bodyclass']     = 'tasks-page kan-ban-body';
        }

        $data['title'] = _l('tasks');
        $data['tasks_table'] = App_table::find('tasks');
        $this->load->view('admin/tasks/manage', $data);
    }

    private function nativeTaskIndex($id, bool $forceBoard=false): void
    {
        $this->output->set_header('Cache-Control: no-store');
        try {
            if (!in_array(strtoupper($this->input->method(true)), ['GET', 'HEAD'], true)) throw new DomainException('native_task_method_invalid');
            $context = CompanyRequestScope::context();
            if ($context->issuerId() === null) throw new DomainException('native_company_task_denied');
            if ($this->input->get('project_id')) throw new DomainException('native_task_index_mode_not_composed');
            require_once APPPATH.'services/NativeCompanyTaskScopedDb.php';
            require_once APPPATH.'services/NativeCompanyTaskIndexProjection.php';
            require_once APPPATH.'services/NativeCompanyRelatedTaskTable.php';
            require_once APPPATH.'services/TaskPriorityPresentation.php';
            require_once APPPATH.'services/NativeCompanyFilters.php';
            require_once APPPATH.'services/NativeCompanyCustomFields.php';
            $scoped = new NativeCompanyTaskScopedDb($this->db, db_prefix());
            $issuer = $context->issuerId(); $actor = $context->staffId();
            $acl = new IssuerResourceAccess($scoped, db_prefix());
            if ($id !== '') NativeCompanyResourceReferences::resolve($scoped, db_prefix(), $issuer, $actor, 'task', $id, 'view');
            $projection = new NativeCompanyTaskIndexProjection($scoped, db_prefix());
            $items = $projection->captureForComparison($issuer, $actor);
            $statuses = WorkStatusPresentation::tasks('_l');
            $summary = [];
            foreach ($statuses as $status) $summary[$status['id']] = ['status_id'=>$status['id'], 'name'=>$status['name'], 'color'=>$status['color'], 'total_tasks'=>0, 'total_my_tasks'=>0];
            foreach ($items as $item) {
                $status = $item['fields']['workflow_status'];
                if (!isset($summary[$status])) throw new DomainException('native_work_status_unknown');
                $summary[$status]['total_tasks']++;
                if (in_array($actor, array_map('intval', array_column($item['assignees'], 'staffid')), true)) $summary[$status]['total_my_tasks']++;
            }
            $assigned = [];
            foreach ($items as $item) foreach ($item['assignees'] as $member) {
                $staffId = IssuerPolicy::id($member['staffid']);
                $assigned[$staffId] = ['value'=>(string)$staffId, 'label'=>trim($member['firstname'].' '.$member['lastname'])];
            }
            ksort($assigned, SORT_NUMERIC);
            $options = [
                'status'=>array_map(static fn($status)=>['value'=>(string)$status['id'],'label'=>$status['name']],$statuses),
                'priority'=>array_map(static fn($priority)=>['value'=>(string)$priority['id'],'label'=>$priority['name']],TaskPriorityPresentation::definitions('_l')),
                'assigned'=>array_values($assigned),
            ];
            $table = new NativeCompanyRelatedTaskTable($scoped, db_prefix(), $issuer, $actor, $options, [], 'tasks');
            $saved = (new NativeCompanyFilters($scoped, db_prefix(), $issuer, $actor, $table))->listing();
            $table = new NativeCompanyRelatedTaskTable($scoped, db_prefix(), $issuer, $actor, $options, $saved, 'tasks');
            $permissions = []; foreach (['view','create','edit','delete'] as $capability) $permissions[$capability] = $acl->can($issuer, $actor, 'tasks', $capability);
            $bulkStaff = $permissions['edit'] ? (new CompanyStandaloneTasks($scoped, db_prefix()))->staffOptions($issuer, $actor) : [];
            $definitions = (new NativeCompanyCustomFields($scoped, db_prefix()))->definitions($issuer, $actor, 'tasks', ['active'=>1,'show_on_table'=>1]);
            $data = ['title'=>_l('tasks'), 'taskid'=>$id, 'custom_view'=>'', 'switch_kanban'=>false, 'bodyclass'=>'tasks-page',
                'task_statuses'=>$statuses, 'tasks_table'=>$table, 'native_task_index'=>['summary'=>array_values($summary), 'permissions'=>$permissions, 'custom_fields'=>$definitions, 'bulk_staff'=>$bulkStaff]];
            require_once APPPATH.'services/NativeCompanyTaskBoard.php';
            $board = new \NativeCompanyTaskBoard($scoped, db_prefix());
            $data['native_task_board_preference'] = $board->preference($issuer, $actor);
            $data['native_task_context']=$context->token();$data['native_task_csrf']=CompanyRequestScope::nativeCsrfToken($this);
            if($forceBoard || $this->input->get('kanban')==='1' || $data['native_task_board_preference']['view']==='board'){
                $search=$this->input->get('search')??'';$offset=$this->input->get('offset')??'0';
                if(!is_string($search)||!is_string($offset)||!preg_match('/^(0|[1-9][0-9]{0,4})$/D',$offset))throw new DomainException('native_task_board_invalid');
                $data['native_task_board']=$board->read($issuer,$actor,$search,null,(int)$offset);
                $data['switch_kanban']=true;$data['bodyclass']='tasks-page kan-ban-body';
            }
            NativeCompanyAdminPresentation::prepare($this);
            $html = $this->load->view('admin/tasks/manage', $data, true);
            if ($projection->captureForComparison($issuer, $actor) !== $items) throw new DomainException('native_task_index_changed');
            $scoped->confirm();
            $this->output->set_output($html);
        } catch (DomainException $error) {
            show_error('Não foi possível confirmar a lista e as permissões das tarefas desta empresa.', str_contains($error->getMessage(), 'denied') ? 403 : 422);
        } catch (RuntimeException $error) {
            show_error('Não foi possível confirmar o armazenamento das tarefas.', 503);
        }
    }

    private function nativeTimesheetAction(string $action,$pathId=null):void
    {
        $this->output->set_header('Cache-Control: no-store');
        try{
            CompanyRequestScope::assertNativeMutation($this);$ctx=CompanyRequestScope::context();if($ctx->issuerId()===null)throw new DomainException('native_timesheet_denied');
            $wire=$this->input->post(null,false);if(!is_array($wire))throw new DomainException('native_timesheet_invalid');unset($wire['_company_context'],$wire['_native_company_csrf']);if(isset($this->security))unset($wire[$this->security->get_csrf_token_name()]);
            if(array_diff(array_keys($wire),['timesheet_task_id','timesheet_staff_id','native_revision','native_request_key','timer_id','native_timer_revision','start_time','end_time','note']))throw new DomainException('native_timesheet_invalid');
            foreach($wire as$value)if(!is_string($value))throw new DomainException('native_timesheet_invalid');
            require_once APPPATH.'services/NativeCompanyResourceReferences.php';require_once APPPATH.'services/CompanyTasks.php';require_once APPPATH.'services/CompanyStandaloneTasks.php';
            $ref=\NativeCompanyResourceReferences::resolve($this->db,db_prefix(),$ctx->issuerId(),$ctx->staffId(),'task',$wire['timesheet_task_id']??null,'view');$writer=$this->nativeTaskWriter($ref);
            $timer=null;$hash=null;if($action!=='add'){$canonical=$pathId??($wire['timer_id']??null);if(isset($wire['timer_id'])&&(string)$canonical!==$wire['timer_id'])throw new DomainException('native_timesheet_invalid');$decoded=\NativeCompanyResourceId::decode('timer',$canonical);if($decoded['source_kind']!==($ref['source_kind']==='company_task'?'company_task_timer':'company_standalone_timer'))throw new DomainException('native_timesheet_denied');$timer=$decoded['source_id'];$hash=$wire['native_timer_revision']??null;}
            $parse=static function($value):string{if(!is_string($value))throw new DomainException('native_timesheet_invalid');foreach(['!Y-m-d\TH:i:s','!Y-m-d\TH:i']as$format){$d=\DateTimeImmutable::createFromFormat($format,$value);if($d&&$d->format(substr($format,1))===$value)return$d->format('Y-m-d H:i:s');}throw new DomainException('native_timesheet_invalid');};
            $writer->changeNativeTimesheet($ctx->issuerId(),$ctx->staffId(),$ref['source_id'],\IssuerPolicy::id($wire['native_revision']??null),$action,$timer,$hash,\IssuerPolicy::id($wire['timesheet_staff_id']??null),$action==='delete'?null:$parse($wire['start_time']??null),$action==='delete'?null:$parse($wire['end_time']??null),$wire['note']??'',$wire['native_request_key']??'');
            $html=$this->input->get('single_task')==='true'?$this->nativeTaskDetailHtml($ref['canonical_id']):'';
            $this->output->set_content_type('application/json')->set_output(json_encode(['success'=>true,'taskHtml'=>$html],JSON_THROW_ON_ERROR));
        }catch(DomainException$error){show_error('Não foi possível confirmar o apontamento, a revisão ou suas permissões.',preg_match('/stale|conflict|immutable|running/',$error->getMessage())?409:(str_contains($error->getMessage(),'denied')?403:422));}
         catch(RuntimeException$error){show_error('Não foi possível confirmar o armazenamento do apontamento.',503);}
    }

    private function nativeTaskBoardMutation(string $action): void
    {
        $this->output->set_header('Cache-Control: no-store');
        try{
            CompanyRequestScope::assertNativeMutation($this);$context=CompanyRequestScope::context();
            if($context->issuerId()===null)throw new DomainException('native_task_board_denied');
            $wire=$this->input->post(null,false);if(!is_array($wire))throw new DomainException('native_task_board_invalid');
            unset($wire['_company_context'],$wire['_native_company_csrf']);if(isset($this->security))unset($wire[$this->security->get_csrf_token_name()]);
            $expected=$action==='view'?['board_revision','native_request_key','view']:['board_revision','native_request_key','task','before'];
            if(array_diff(array_keys($wire),$expected)||array_diff($expected,array_keys($wire)))throw new DomainException('native_task_board_invalid');
            foreach($wire as$value)if(!is_string($value))throw new DomainException('native_task_board_invalid');
            require_once APPPATH.'services/NativeCompanyTaskBoard.php';
            $command=$action==='view'?['action'=>'view','view'=>$wire['view']]:['action'=>'move','task'=>$wire['task'],'before'=>$wire['before']];
            (new \NativeCompanyTaskBoard($this->db,db_prefix()))->save($context->issuerId(),$context->staffId(),$wire['board_revision'],$wire['native_request_key'],$command);
            $this->output->set_status_header(303)->set_header('Location: '.admin_url('tasks'));
        }catch(DomainException$error){show_error('Não foi possível confirmar a revisão e as permissões do quadro de tarefas.',str_contains($error->getMessage(),'denied')?403:(preg_match('/stale|conflict/',$error->getMessage())?409:422));}
         catch(RuntimeException$error){show_error('Não foi possível confirmar o armazenamento do quadro de tarefas.',503);}
    }

    private function nativeTaskBoardFragment(): void
    {
        $this->output->set_header('Cache-Control: no-store');
        try{
            if(!in_array(strtoupper($this->input->method(true)),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
            $context=CompanyRequestScope::context();if($context->issuerId()===null)throw new DomainException('native_task_board_denied');
            $search=$this->input->get('search')??'';$status=$this->input->get('status');$offset=$this->input->get('offset')??'0';
            if(!is_string($search)||!is_string($offset)||!preg_match('/^(0|[1-9][0-9]{0,4})$/D',$offset)||($status!==null&&(!is_string($status)||!in_array($status,['1','2','3','4','5'],true))))throw new DomainException('native_task_board_invalid');
            require_once APPPATH.'services/NativeCompanyTaskScopedDb.php';require_once APPPATH.'services/NativeCompanyTaskBoard.php';
            $scoped=new \NativeCompanyTaskScopedDb($this->db,db_prefix());$board=new \NativeCompanyTaskBoard($scoped,db_prefix());
            $snapshot=$board->read($context->issuerId(),$context->staffId(),$search,$status===null?null:(int)$status,(int)$offset);
            $data=['native_task_board'=>$snapshot,'native_task_board_preference'=>$snapshot['preference'],'native_task_context'=>$context->token(),'native_task_csrf'=>CompanyRequestScope::nativeCsrfToken($this),'task_statuses'=>\WorkStatusPresentation::tasks('_l')];
            $html=$this->load->view('admin/tasks/kan_ban',$data,true);
            if($board->read($context->issuerId(),$context->staffId(),$search,$status===null?null:(int)$status,(int)$offset)!==$snapshot)throw new DomainException('native_task_board_stale');
            $scoped->confirm();$this->output->set_output($html);
        }catch(DomainException$error){show_error('Não foi possível confirmar as tarefas deste quadro.',str_contains($error->getMessage(),'denied')?403:422);}
         catch(RuntimeException$error){show_error('Não foi possível confirmar o armazenamento do quadro de tarefas.',503);}
    }

    private function nativeTaskDetailHtml($taskid): string
    {
        $context = CompanyRequestScope::context();
        if ($context->issuerId() === null) throw new DomainException('native_company_task_denied');
        require_once APPPATH.'services/NativeCompanyTaskDetailPresentation.php';
        $clock = time();
        $presentation = new \NativeCompanyTaskDetailPresentation($this->db, db_prefix());
        $snapshot = $presentation->read($context->issuerId(), $context->staffId(), $taskid, $clock, '_l');
        require_once APPPATH.'services/NativeCompanyAuxiliaryUi.php';require_once APPPATH.'services/NativeCompanyDateInput.php';
        $auxiliary=new \NativeCompanyAuxiliaryUi($this->db,db_prefix(),$context->issuerId(),$context->staffId(),$this);
        $reminders=$auxiliary->reminders('task',$taskid);$dates=new \NativeCompanyDateInput($this->db,db_prefix(),$context->issuerId());
        $data = [
            'native_task_detail'=>$snapshot,
            'native_task_reminders'=>$reminders,'native_task_reminder_dates'=>$dates,
            'native_task_context'=>$context->token(),
            'native_task_csrf'=>CompanyRequestScope::nativeCsrfToken($this),
            'native_task_request_key'=>bin2hex(random_bytes(32)),
        ];
        if($snapshot['detail']['permissions']['create']&&$this->db->table_exists(db_prefix().'company_task_copy_requests')){
            require_once APPPATH.'services/CompanyTaskCopies.php';$copyService=new \CompanyTaskCopies($this->db,db_prefix());$copyItem=$snapshot['detail']['item'];
            $copyKind=$copyItem['source_kind']==='company_task'?'project':'standalone';
            $data['native_task_copy']=$copyService->preview($context->issuerId(),$context->staffId(),$copyKind,$copyItem['source_id']);
        }
        $html = $this->load->view('admin/tasks/view_task_template', $data, true);
        if ($presentation->read($context->issuerId(), $context->staffId(), $taskid, $clock, '_l') !== $snapshot) {
            throw new DomainException('native_task_detail_changed');
        }
        if($auxiliary->reminders('task',$taskid)!==$reminders)throw new DomainException('native_task_detail_changed');
        if(isset($data['native_task_copy'])&&$copyService->preview($context->issuerId(),$context->staffId(),$copyKind,$copyItem['source_id'])!==$data['native_task_copy'])throw new DomainException('native_task_detail_changed');
        return $html;
    }

    private function nativeTaskWriter(array $reference)
    {
        require_once APPPATH.'services/CompanyTasks.php';
        require_once APPPATH.'services/CompanyStandaloneTasks.php';
        return match ($reference['source_kind']) {
            'company_task' => new CompanyTasks($this->db, db_prefix()),
            'company_standalone_task' => new CompanyStandaloneTasks($this->db, db_prefix()),
            default => throw new DomainException('native_task_legacy_writer_required'),
        };
    }

    private function nativeOriginalAction(string $action,$canonical=null,$argument=null):void
    {
        try{
            CompanyRequestScope::assertNativeMutation($this);$context=CompanyRequestScope::context();
            if($context->issuerId()===null)throw new DomainException('native_task_original_denied');
            $input=$this->input->post(null,false);if(!is_array($input))throw new DomainException('native_task_original_invalid');
            unset($input['_company_context'],$input['_native_company_csrf']);if(isset($this->security))unset($input[$this->security->get_csrf_token_name()]);
            $extras=match($action){'description'=>['description'],'inline'=>['startdate','duedate'],'comment_edit'=>['id','content','no_editor','native_comment_revision'],'comment_remove'=>['native_comment_revision'],'public'=>['value'],'tags'=>['tags','tags_revision'],'milestone'=>['milestone_revision'], 'assignee_add','assignee_remove'=>['staff_id','assignment_revision'],'follower_add','follower_remove'=>['staff_id'],default=>throw new DomainException('native_task_original_invalid')};
            if(array_diff(array_keys($input),array_merge(['taskid','task_id','native_revision','native_fields_revision','native_request_key'],$extras))||!isset($input['native_revision'],$input['native_request_key'])||!is_string($input['native_request_key'])||!preg_match('/^[a-f0-9]{64}$/D',$input['native_request_key']))throw new DomainException('native_task_original_invalid');
            $posted=$input['taskid']??$input['task_id']??null;if(isset($input['taskid'],$input['task_id'])&&(string)$input['taskid']!==(string)$input['task_id'])throw new DomainException('native_task_original_invalid');
            if($canonical===null)$canonical=$posted;elseif($posted!==null&&(string)$posted!==(string)$canonical)throw new DomainException('native_task_original_invalid');
            require_once APPPATH.'services/NativeCompanyResourceReferences.php';$reference=\NativeCompanyResourceReferences::resolve($this->db,db_prefix(),$context->issuerId(),$context->staffId(),'task',$canonical,in_array($action,['comment_edit','comment_remove'],true)?'view':'edit');
            $command=['action'=>$action];
            if($action==='description')$command['description']=$input['description']??null;
            elseif($action==='inline'){require_once APPPATH.'services/NativeCompanyTaskForm.php';$parser=new NativeCompanyTaskForm($this->db,db_prefix());$command['dates']=[];foreach(['startdate','duedate']as$field)if(array_key_exists($field,$input))$command['dates'][$field]=$parser->parseDate($context->issuerId(),$input[$field],$field==='duedate');}
            elseif(in_array($action,['comment_edit','comment_remove'],true)){if(isset($input['no_editor'])&&$input['no_editor']!=='1')throw new DomainException('native_task_original_invalid');$command+=['comment_id'=>$argument??$input['id']??null,'comment_revision'=>$input['native_comment_revision']??null];if($action==='comment_edit'){$content=$input['content']??null;if(!is_string($content))throw new DomainException('native_task_original_invalid');$command['body']=$content;}}
            elseif($action==='public')$command['value']=$input['value']??null;
            elseif($action==='tags'){
                $labels=$input['tags']??null;if(is_string($labels))$labels=trim($labels)===''?[]:array_map('trim',explode(',',$labels));
                $command+=['labels'=>$labels,'revision'=>$input['tags_revision']??null];
            }elseif($action==='milestone')$command+=['id'=>(string)$argument==='0'?null:$argument,'revision'=>$input['milestone_revision']??null];
            else{
                if($argument!==null&&isset($input['staff_id'])&&(string)$argument!==(string)$input['staff_id'])throw new DomainException('native_task_original_invalid');
                $command['staff_id']=$argument??$input['staff_id']??null;
                if(str_starts_with($action,'assignee_'))$command['revision']=$input['assignment_revision']??null;
            }
            $fields=$input['native_fields_revision']??null;if($fields==='')$fields=null;
            $this->nativeTaskWriter($reference)->changeNativeOriginal($context->issuerId(),$context->staffId(),$reference['source_id'],$input['native_revision'],$fields,$command,$input['native_request_key']);
            $html=$this->input->get('single_task')==='true'?$this->nativeTaskDetailHtml($reference['canonical_id']):'';
            $this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode(['success'=>true,'taskHtml'=>$html],JSON_THROW_ON_ERROR));
        }catch(DomainException $e){$reason=$e->getMessage();show_error('Não foi possível confirmar os dados ou as permissões desta tarefa.',str_contains($reason,'stale')||str_contains($reason,'conflict')?409:(str_contains($reason,'denied')?403:422));}
        catch(RuntimeException $e){show_error('Não foi possível confirmar o armazenamento da tarefa.',503);}
    }

    public function table()
    {
        if ($this->nativeCompanyTasks()) {
            $this->output->set_header('Cache-Control: no-store');
            try {
                CompanyRequestScope::assertNativeMutation($this);
                $context = CompanyRequestScope::context();
                if ($context->issuerId() === null) throw new DomainException('native_company_task_denied');
                require_once APPPATH . 'services/NativeCompanyRelatedTaskPage.php';
                require_once APPPATH . 'services/NativeCompanyRelatedTaskRows.php';
                require_once APPPATH . 'services/NativeCompanyTaskScopedDb.php';
                $scoped = new NativeCompanyTaskScopedDb($this->db, db_prefix());
                $input = $this->input->post(null, false) ?: [];
                unset($input['_company_context'], $input['_native_company_csrf']);
                if (isset($this->security)) unset($input[$this->security->get_csrf_token_name()]);
                $reader = new NativeCompanyRelatedTaskPage($scoped, db_prefix());
                $today = date('Y-m-d');
                $page = $reader->readIndex($context->issuerId(), $context->staffId(), $input, $today, true);
                $data = (new NativeCompanyRelatedTaskRows($scoped, db_prefix()))->render($context->issuerId(), $context->staffId(), $page);
                if ($reader->readIndex($context->issuerId(), $context->staffId(), $input, $today, true) !== $page) throw new DomainException('native_task_index_changed');
                $scoped->confirm();
                $this->output->set_header('Content-Type: application/json; charset=utf-8')->set_output(json_encode($data, JSON_THROW_ON_ERROR));
            } catch (DomainException $error) {
                show_error('Não foi possível confirmar as tarefas desta empresa.', str_contains($error->getMessage(), 'denied') ? 403 : 422);
            } catch (RuntimeException $error) {
                show_error('Não foi possível confirmar o armazenamento das tarefas.', 503);
            }
            return;
        }
        App_table::find('tasks')->output();
    }

    public function kanban()
    {
        if ($this->nativeCompanyTasks()) { $this->nativeTaskBoardFragment(); return; }
        echo $this->load->view('admin/tasks/kan_ban', [], true);
    }

    public function ajax_search_assign_task_to_timer()
    {
        if($this->nativeCompanyTasks()){
            try{CompanyRequestScope::assertNativeMutation($this);$ctx=CompanyRequestScope::context();if($ctx->issuerId()===null)throw new DomainException('native_company_task_denied');require_once APPPATH.'services/NativeCompanyTasksRead.php';$query=$this->input->post('q',false);if($query!==null&&!is_string($query))throw new DomainException('native_company_task_query_invalid');$rows=(new NativeCompanyTasksRead($this->db,db_prefix()))->timerAssignmentOptions($ctx->issuerId(),$ctx->staffId(),$query??'');$this->output->set_header('Cache-Control: no-store')->set_header('Content-Type: application/json; charset=utf-8')->set_output(json_encode($rows,JSON_THROW_ON_ERROR));}catch(DomainException$e){show_error('Tarefas indisponíveis neste contexto.',422);}return;
        }
        if ($this->input->is_ajax_request()) {
            $q = $this->input->post('q');
            $q = trim($q);
            $this->db->select('name, id,' . tasks_rel_name_select_query() . ' as subtext');
            $this->db->from(db_prefix() . 'tasks');
            $this->db->where('' . db_prefix() . 'tasks.id IN (SELECT taskid FROM ' . db_prefix() . 'task_assigned WHERE staffid = ' . get_staff_user_id() . ')');
            //   $this->db->where('id NOT IN (SELECT task_id FROM '.db_prefix().'taskstimers WHERE staff_id = ' . get_staff_user_id() . ' AND end_time IS NULL)');
            $this->db->where('status != ', 5);
            $this->db->where('billed', 0);
            $this->db->group_start();
            $this->db->like('name', $q);
            $this->db->or_like(tasks_rel_name_select_query(), $q);
            $this->db->group_end();
            echo json_encode($this->db->get()->result_array());
        }
    }

    public function tasks_kanban_load_more()
    {
        if ($this->nativeCompanyTasks()) { $this->nativeTaskBoardFragment(); return; }
        $status = $this->input->get('status');
        $page   = $this->input->get('page');

        $tasks = (new TasksKanban($status))
            ->search($this->input->get('search'))
            ->sortBy(
                $this->input->get('sort_by'),
                $this->input->get('sort')
            )
            ->forProject($this->input->get('project_id') ?: null)
            ->page($page)->get();


        foreach ($tasks as $task) {
            $this->load->view('admin/tasks/_kan_ban_card', [
                'task'   => $task,
                'status' => $status,
            ]);
        }
    }

    public function update_order()
    {
        if ($this->nativeCompanyTasks()) { $this->nativeTaskBoardMutation('move'); return; }
        $this->tasks_model->update_order($this->input->post());
    }

    public function switch_kanban($set = 0, $manual = false)
    {
        if ($this->nativeCompanyTasks()) { $this->nativeTaskBoardMutation('view'); return; }
        if ($set == 1) {
            $set = 'false';
        } else {
            $set = 'true';
        }

        $this->session->set_userdata([
            'tasks_kanban_view' => $set,
        ]);

        if ($manual == false) {
            redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
        }
    }

    // Used in invoice add/edit
    public function get_billable_tasks_by_project($project_id)
    {
        if ($this->nativeCompanyTasks()) { $this->nativeInvoiceBillableSelector('project', $project_id); return; }
        if ($this->input->is_ajax_request() && (staff_can('edit',  'invoices') || staff_can('create',  'invoices'))) {
            $customer_id = get_client_id_by_project_id($project_id);
            echo json_encode($this->tasks_model->get_billable_tasks($customer_id, $project_id));
        }
    }

    private function nativeInvoiceBillableSelector(string $kind,$id): void
    {
        $this->output->set_header('Cache-Control: no-store');
        if($this->input->method(true)!=='GET'){show_error('Método não permitido.',405);return;}
        try{
            require_once APPPATH.'services/NativeCompanyBillableTaskRead.php';$context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();$acl=new IssuerResourceAccess($this->db,db_prefix());
            $authorize=static function()use($acl,$issuer,$actor):void{if(!$acl->can($issuer,$actor,'invoices','view')||(!$acl->can($issuer,$actor,'invoices','create')&&!$acl->can($issuer,$actor,'invoices','edit')))throw new DomainException('native_billable_task_denied');};$authorize();
            $reader=new NativeCompanyBillableTaskRead($this->db,db_prefix());$result=$kind==='project'?$reader->forProject($issuer,$actor,$id):$reader->forCustomer($issuer,$actor,$id);$authorize();
            $this->output->set_content_type('application/json')->set_output(json_encode($result['items'],JSON_THROW_ON_ERROR));
        }catch(DomainException $error){show_error('Tarefas indisponíveis nesta empresa.',str_contains($error->getMessage(),'changed')?409:403);}catch(Throwable $error){show_error('Não foi possível confirmar as tarefas.',503);}
    }

    // Used in invoice add/edit
    public function get_billable_tasks_by_customer_id($customer_id)
    {
        if ($this->nativeCompanyTasks()) { $this->nativeInvoiceBillableSelector('customer', $customer_id); return; }
        if ($this->input->is_ajax_request() && (staff_can('edit',  'invoices') || staff_can('create',  'invoices'))) {
            echo json_encode($this->tasks_model->get_billable_tasks($customer_id));
        }
    }

    public function update_task_description($id)
    {
        if($this->nativeCompanyTasks()){$this->nativeOriginalAction('description',$id);return;}
        if (staff_can('edit',  'tasks')) {
            $data = hooks()->apply_filters('before_update_task', [
                'description' => html_purify($this->input->post('description', false)),
            ], $id);

            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'tasks', $data);

            hooks()->do_action('after_update_task', $id);
        }
    }

    public function detailed_overview()
    {
        $overview = [];

        $has_permission_create = staff_can('create',  'tasks');
        $has_permission_view   = staff_can('view',  'tasks');

        if (!$has_permission_view) {
            $staff_id = get_staff_user_id();
        } elseif ($this->input->post('member')) {
            $staff_id = $this->input->post('member');
        } else {
            $staff_id = '';
        }

        $month = ($this->input->post('month') ? $this->input->post('month') : date('m'));
        if ($this->input->post() && $this->input->post('month') == '') {
            $month = '';
        }

        $status = $this->input->post('status');

        $fetch_month_from = 'startdate';

        $year       = ($this->input->post('year') ? $this->input->post('year') : date('Y'));
        $project_id = $this->input->get('project_id');

        for ($m = 1; $m <= 12; $m++) {
            if ($month != '' && $month != $m) {
                continue;
            }

            // Task rel_name
            $sqlTasksSelect = '*,' . tasks_rel_name_select_query() . ' as rel_name';

            // Task logged time
            $selectLoggedTime = get_sql_calc_task_logged_time('tmp-task-id');
            // Replace tmp-task-id to be the same like tasks.id
            $selectLoggedTime = str_replace('tmp-task-id', db_prefix() . 'tasks.id', $selectLoggedTime);

            if (is_numeric($staff_id)) {
                $selectLoggedTime .= ' AND staff_id=' . $this->db->escape_str($staff_id);
                $sqlTasksSelect .= ',(' . $selectLoggedTime . ')';
            } else {
                $sqlTasksSelect .= ',(' . $selectLoggedTime . ')';
            }

            $sqlTasksSelect .= ' as total_logged_time';

            // Task checklist items
            $sqlTasksSelect .= ',' . get_sql_select_task_total_checklist_items();

            if (is_numeric($staff_id)) {
                $sqlTasksSelect .= ',(SELECT COUNT(id) FROM ' . db_prefix() . 'task_checklist_items WHERE taskid=' . db_prefix() . 'tasks.id AND finished=1 AND finished_from=' . $staff_id . ') as total_finished_checklist_items';
            } else {
                $sqlTasksSelect .= ',' . get_sql_select_task_total_finished_checklist_items();
            }

            // Task total comment and total files
            $selectTotalComments = ',(SELECT COUNT(id) FROM ' . db_prefix() . 'task_comments WHERE taskid=' . db_prefix() . 'tasks.id';
            $selectTotalFiles    = ',(SELECT COUNT(id) FROM ' . db_prefix() . 'files WHERE rel_id=' . db_prefix() . 'tasks.id AND rel_type="task"';

            if (is_numeric($staff_id)) {
                $sqlTasksSelect .= $selectTotalComments . ' AND staffid=' . $staff_id . ') as total_comments_staff';
                $sqlTasksSelect .= $selectTotalFiles . ' AND staffid=' . $staff_id . ') as total_files_staff';
            }

            $sqlTasksSelect .= $selectTotalComments . ') as total_comments';
            $sqlTasksSelect .= $selectTotalFiles . ') as total_files';

            // Task assignees
            $sqlTasksSelect .= ',' . get_sql_select_task_asignees_full_names() . ' as assignees' . ',' . get_sql_select_task_assignees_ids() . ' as assignees_ids';

            $this->db->select($sqlTasksSelect);

            $this->db->where('MONTH(' . $fetch_month_from . ')', $m);
            $this->db->where('YEAR(' . $fetch_month_from . ')', $year);

            if ($project_id && $project_id != '') {
                $this->db->where('rel_id', $project_id);
                $this->db->where('rel_type', 'project');
            }

            if (!$has_permission_view) {
                $sqlWhereStaff = '(id IN (SELECT taskid FROM ' . db_prefix() . 'task_assigned WHERE staffid=' . $staff_id . ')';

                // User dont have permission for view but have for create
                // Only show tasks createad by this user.
                if ($has_permission_create) {
                    $sqlWhereStaff .= ' OR addedfrom=' . get_staff_user_id();
                }

                $sqlWhereStaff .= ')';
                $this->db->where($sqlWhereStaff);
            } elseif ($has_permission_view) {
                if (is_numeric($staff_id)) {
                    $this->db->where('(id IN (SELECT taskid FROM ' . db_prefix() . 'task_assigned WHERE staffid=' . $staff_id . '))');
                }
            }

            if ($status) {
                $this->db->where('status', $status);
            }

            $this->db->order_by($fetch_month_from, 'ASC');
            array_push($overview, $m);
            $overview[$m] = $this->db->get(db_prefix() . 'tasks')->result_array();
        }

        unset($overview[0]);

        $overview = [
            'staff_id' => $staff_id,
            'detailed' => $overview,
        ];

        $data['members']  = $this->staff_model->get();
        $data['overview'] = $overview['detailed'];
        $data['years']    = $this->tasks_model->get_distinct_tasks_years(($this->input->post('month_from') ? $this->input->post('month_from') : 'startdate'));
        $data['staff_id'] = $overview['staff_id'];
        $data['title']    = _l('detailed_overview');
        $this->load->view('admin/tasks/detailed_overview', $data);
    }

    public function init_relation_tasks($rel_id, $rel_type)
    {
        if ($this->nativeCompanyTasks()) {
            $this->output->set_header('Cache-Control: no-store');
            try {
                CompanyRequestScope::assertNativeMutation($this);$ctx=CompanyRequestScope::context();if($ctx->issuerId()===null)throw new DomainException('native_company_task_denied');
                require_once APPPATH.'services/NativeCompanyRelatedTaskPage.php';require_once APPPATH.'services/NativeCompanyRelatedTaskRows.php';
                $wire=$this->input->post(null,false)?:[];unset($wire['_company_context'],$wire['_native_company_csrf']);if(isset($this->security))unset($wire[$this->security->get_csrf_token_name()]);
                $today=date('Y-m-d');$scoped=null;$readDb=$this->db;if(in_array($rel_type,['project','invoice'],true)){require_once APPPATH.'services/NativeCompanyTaskScopedDb.php';$scoped=new NativeCompanyTaskScopedDb($this->db,db_prefix());$readDb=$scoped;}
                $reader=new NativeCompanyRelatedTaskPage($readDb,db_prefix());$page=$reader->read($ctx->issuerId(),$ctx->staffId(),$rel_type,$rel_id,$wire,$today);
                $data=(new NativeCompanyRelatedTaskRows($readDb,db_prefix()))->render($ctx->issuerId(),$ctx->staffId(),$page);
                if($reader->read($ctx->issuerId(),$ctx->staffId(),$rel_type,$rel_id,$wire,$today)!==$page)throw new DomainException('native_task_relation_stale');
                if($scoped!==null)$scoped->confirm();
                $this->output->set_header('Content-Type: application/json; charset=utf-8')->set_output(json_encode($data,JSON_THROW_ON_ERROR));return;
            } catch (DomainException $error) {show_error('Não foi possível confirmar as tarefas relacionadas desta empresa.',str_contains($error->getMessage(),'denied')?403:422);return;}
              catch (RuntimeException $error) {show_error('Não foi possível confirmar o armazenamento das tarefas.',503);return;}
        }
        if ($this->input->is_ajax_request()) {
           App_table::find('related_tasks')->output([
                'rel_id'   => $rel_id,
                'rel_type' => $rel_type,
           ]);
        }
    }

    /* Add new task or update existing */
    public function task($id = '')
    {
        if ($this->nativeCompanyTasks()) {
            $this->output->set_header('Cache-Control: no-store');
            try {
                $ctx=CompanyRequestScope::context();if($ctx->issuerId()===null)throw new DomainException('native_task_form_denied');
                require_once APPPATH.'services/NativeCompanyTaskForm.php';$form=new NativeCompanyTaskForm($this->db,db_prefix());
                if(strtoupper($this->input->method(true))==='POST') {
                    CompanyRequestScope::assertNativeMutation($this);$wire=$this->input->post(null,false)?:[];unset($wire['_company_context'],$wire['_native_company_csrf']);if(isset($this->security))unset($wire[$this->security->get_csrf_token_name()]);
                    foreach($_FILES as$upload){$errors=$upload['error']??null;if($errors===null)throw new DomainException('native_task_attachments_not_composed');$iterator=new RecursiveIteratorIterator(new RecursiveArrayIterator(is_array($errors)?$errors:[$errors]));foreach($iterator as$error)if((int)$error!==UPLOAD_ERR_NO_FILE)throw new DomainException('native_task_attachments_not_composed');}
                    if(isset($wire['description']))$wire['description']=html_purify($wire['description']);$saved=$form->save($ctx->issuerId(),$ctx->staffId(),$id,$wire);
                    $this->output->set_header('Content-Type: application/json; charset=utf-8')->set_output(json_encode(['success'=>true,'id'=>$saved,'message'=>_l($id===''?'added_successfully':'updated_successfully',_l('task'))],JSON_THROW_ON_ERROR));return;
                }
                if(!in_array(strtoupper($this->input->method(true)),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
                $query=[];foreach(['rel_type','rel_id','start_date']as$key)if($this->input->get($key)!==null)$query[$key]=$this->input->get($key);
                foreach(['milestone_id','opened_from_lead_id']as$key)if($this->input->get($key))throw new DomainException('native_task_relation_writer_required');
                $today=date('Y-m-d');$snapshot=$id===''?$form->newForm($ctx->issuerId(),$ctx->staffId(),$query,$today):$form->editForm($ctx->issuerId(),$ctx->staffId(),$id,$today);$data=$snapshot;
                $data['native_task_form']['fields']+=['_company_context'=>$ctx->token(),'_native_company_csrf'=>CompanyRequestScope::nativeCsrfToken($this),'native_request_key'=>bin2hex(random_bytes(32))];
                // AJAX forms do not render the admin header, where this pure
                // PHP upload-size parser is otherwise loaded.
                require_once APPPATH.'helpers/files_helper.php';
                $html=$this->load->view('admin/tasks/task',$data,true);if(json_encode($id===''?$form->newForm($ctx->issuerId(),$ctx->staffId(),$query,$today):$form->editForm($ctx->issuerId(),$ctx->staffId(),$id,$today),JSON_THROW_ON_ERROR)!==json_encode($snapshot,JSON_THROW_ON_ERROR))throw new DomainException('native_task_form_changed');$this->output->set_output($html);return;
            } catch (DomainException $error) { show_error('Não foi possível confirmar os dados e a ação desta tarefa.',422);return; }
              catch (RuntimeException $error) { show_error('Não foi possível confirmar o armazenamento da tarefa.',503);return; }
        }

        if (staff_cant('edit', 'tasks') && staff_cant('create', 'tasks')) {
            ajax_access_denied();
        }

        $data = [];
        // FOr new task add directly from the projects milestones
        if ($this->input->get('milestone_id')) {
            $this->db->where('id', $this->input->get('milestone_id'));
            $milestone = $this->db->get(db_prefix() . 'milestones')->row();
            if ($milestone) {
                $data['_milestone_selected_data'] = [
                    'id'       => $milestone->id,
                    'due_date' => _d($milestone->due_date),
                ];
            }
        }
        if ($this->input->get('start_date')) {
            $data['start_date'] = $this->input->get('start_date');
        }
        if ($this->input->post()) {
            $data                = $this->input->post();
            $data['description'] = html_purify($this->input->post('description', false));
            if ($id == '') {
                if (staff_cant('create', 'tasks')) {
                    header('HTTP/1.0 400 Bad error');
                    echo json_encode([
                        'success' => false,
                        'message' => _l('access_denied'),
                    ]);
                    die;
                }
                $id      = $this->tasks_model->add($data);
                $_id     = false;
                $success = false;
                $message = '';
                if ($id) {
                    $success       = true;
                    $_id           = $id;
                    $message       = _l('added_successfully', _l('task'));
                    $uploadedFiles = handle_task_attachments_array($id);
                    if ($uploadedFiles && is_array($uploadedFiles)) {
                        foreach ($uploadedFiles as $file) {
                            $this->misc_model->add_attachment_to_database($id, 'task', [$file]);
                        }
                    }
                }
                echo json_encode([
                    'success' => $success,
                    'id'      => $_id,
                    'message' => $message,
                ]);
            } else {
                if (staff_cant('edit', 'tasks')) {
                    header('HTTP/1.0 400 Bad error');
                    echo json_encode([
                        'success' => false,
                        'message' => _l('access_denied'),
                    ]);
                    die;
                }
                $success = $this->tasks_model->update($data, $id);
                $message = '';
                if ($success) {
                    $message = _l('updated_successfully', _l('task'));
                }
                echo json_encode([
                    'success' => $success,
                    'message' => $message,
                    'id'      => $id,
                ]);
            }
            die;
        }

        $data['milestones']         = [];
        $data['checklistTemplates'] = $this->tasks_model->get_checklist_templates();
        if ($id == '') {
            $title = _l('add_new', _l('task'));
        } else {
            $data['task'] = $this->tasks_model->get($id);
            if ($data['task']->rel_type == 'project') {
                $data['milestones'] = $this->projects_model->get_milestones($data['task']->rel_id);
            }
            $title = _l('edit', _l('task')) . ' ' . $data['task']->name;
        }

        $data['project_end_date_attrs'] = [];
        if ($this->input->get('rel_type') == 'project' && $this->input->get('rel_id') || ($id !== '' && $data['task']->rel_type == 'project')) {
            $project = $this->projects_model->get($id === '' ? $this->input->get('rel_id') : $data['task']->rel_id);

            if ($project->deadline) {
                $data['project_end_date_attrs'] = [
                    'data-date-end-date' => $project->deadline,
                ];
            }
        }
        $data['members'] = $this->staff_model->get('', ['active' => 1]);
        $data['id']      = $id;
        $data['title']   = $title;
        $this->load->view('admin/tasks/task', $data);
    }

    public function copy()
    {
        if($this->nativeCompanyTasks()){
            $this->output->set_header('Cache-Control: no-store');
            try{
                CompanyRequestScope::assertNativeMutation($this);$context=CompanyRequestScope::context();if($context->issuerId()===null)throw new DomainException('company_task_copy_denied');
                $wire=$this->input->post(null,false);if(!is_array($wire))throw new DomainException('company_task_copy_invalid');unset($wire['_company_context'],$wire['_native_company_csrf']);if(isset($this->security))unset($wire[$this->security->get_csrf_token_name()]);
                if(array_diff(array_keys($wire),['copy_from','source_revision','native_request_key','name','start_date','due_date','copy_task_status','copy_task_assignees','copy_task_followers','copy_task_checklist_items','copy_task_attachments']))throw new DomainException('company_task_copy_invalid');
                require_once APPPATH.'services/NativeCompanyResourceReferences.php';require_once APPPATH.'services/CompanyTaskCopies.php';
                $ref=\NativeCompanyResourceReferences::resolve($this->db,db_prefix(),$context->issuerId(),$context->staffId(),'task',$wire['copy_from']??null,'view');
                $kind=match($ref['source_kind']){'company_task'=>'project','company_standalone_task'=>'standalone',default=>throw new DomainException('company_task_copy_denied')};
                $copies=new \CompanyTaskCopies($this->db,db_prefix());$preview=$copies->preview($context->issuerId(),$context->staffId(),$kind,$ref['source_id']);$source=$preview['source']['task'];
                $options=[];foreach(['assignees'=>'copy_task_assignees','followers'=>'copy_task_followers','checklist'=>'copy_task_checklist_items','attachments'=>'copy_task_attachments']as$key=>$name){$value=$wire[$name]??'0';if(!in_array($value,['0','1'],true))throw new DomainException('company_task_copy_invalid');$options[$key]=(int)$value;}
                $options+=['status'=>$wire['copy_task_status']??null,'start_date'=>$wire['start_date']??null];
                $review=['source_revision'=>$wire['source_revision']??null,'name'=>$wire['name']??null,'due_date'=>($wire['due_date']??null)===''?null:($wire['due_date']??null),'assignee_id'=>$source['assignee_id'],'target_project_id'=>$kind==='project'?(int)$source['project_id']:0,'native'=>$options];
                $result=$copies->create($context->issuerId(),$context->staffId(),$kind,$ref['source_id'],$review,$wire['native_request_key']??'');
                $canonical=\NativeCompanyResourceId::encode($ref['source_kind'],$result['task_id']);$this->output->set_status_header(303)->set_header('Location: '.admin_url('tasks/list_tasks/'.$canonical));
            }catch(DomainException$error){show_error('Não foi possível confirmar a origem, as opções ou a revisão da cópia.',preg_match('/stale|conflict/',$error->getMessage())?409:(str_contains($error->getMessage(),'denied')?403:422));}
             catch(RuntimeException$error){show_error('Não foi possível confirmar o armazenamento da cópia.',503);}
            return;
        }

        if (staff_can('create',  'tasks')) {
            $new_task_id = $this->tasks_model->copy($this->input->post());
            $response    = [
                'new_task_id' => '',
                'alert_type'  => 'warning',
                'message'     => _l('failed_to_copy_task'),
                'success'     => false,
            ];
            if ($new_task_id) {
                $response['message']     = _l('task_copied_successfully');
                $response['new_task_id'] = $new_task_id;
                $response['success']     = true;
                $response['alert_type']  = 'success';
            }
            echo json_encode($response);
        }
    }

    public function get_billable_task_data($task_id)
    {
        if ($this->nativeCompanyTasks()) {
            $this->output->set_header('Cache-Control: no-store');
            if($this->input->method(true)!=='GET'){show_error('Método não permitido.',405);return;}
            try{require_once APPPATH.'services/NativeCompanyBillableTaskPreview.php';$context=CompanyRequestScope::context();$data=(new NativeCompanyBillableTaskPreview($this->db,db_prefix()))->read($context->issuerId(),$context->staffId(),$task_id);$this->output->set_content_type('application/json')->set_output(json_encode($data,JSON_THROW_ON_ERROR));}
            catch(DomainException $error){show_error('Tarefa indisponível nesta empresa.',str_contains($error->getMessage(),'changed')?409:403);}catch(Throwable $error){show_error('Não foi possível confirmar a tarefa.',503);}return;
        }
        $task              = $this->tasks_model->get_billable_task_data($task_id);
        $task->description = seconds_to_time_format($task->total_seconds) . ' ' . _l('hours');
        echo json_encode($task);
    }

    /**
     * Task ajax request modal
     * @param  mixed $taskid
     * @return mixed
     */
    public function get_task_data($taskid, $return = false)
    {
        if($this->nativeCompanyTasks()){
            $this->output->set_header('Cache-Control: no-store');
            try {
                if ($return !== false || strtoupper($this->input->method(true)) !== 'GET') throw new DomainException('native_task_method_invalid');
                $this->output->set_output($this->nativeTaskDetailHtml($taskid));
            } catch (DomainException $error) {
                show_error('Não foi possível confirmar os detalhes e as permissões desta tarefa.', str_contains($error->getMessage(), 'denied') ? 403 : 422);
            } catch (RuntimeException $error) {
                show_error('Não foi possível confirmar o armazenamento da tarefa.', 503);
            }
            return;
        }
        $tasks_where = [];

        if (staff_cant('view', 'tasks')) {
            $tasks_where = get_tasks_where_string(false);
        }

        $task = $this->tasks_model->get($taskid, $tasks_where);

        if (!$task) {
            header('HTTP/1.0 404 Not Found');
            echo 'Task not found';
            die();
        }

        $data['checklistTemplates'] = $this->tasks_model->get_checklist_templates();
        $data['task']               = $task;
        $data['id']                 = $task->id;
        $data['staff']              = $this->staff_model->get('', ['active' => 1]);
        $data['reminders']          = $this->tasks_model->get_reminders($taskid);

        $data['task_staff_members'] = $this->tasks_model->get_staff_members_that_can_access_task($taskid);
        // For backward compatibilities
        $data['staff_reminders'] = $data['task_staff_members'];

        $data['hide_completed_items'] = get_staff_meta(get_staff_user_id(), 'task-hide-completed-items-' . $taskid);

        $data['project_deadline'] = null;
        if ($task->rel_type == 'project') {
            $data['project_deadline'] = get_project_deadline($task->rel_id);
        }

        if ($return == false) {
            $this->load->view('admin/tasks/view_task_template', $data);
        } else {
            return $this->load->view('admin/tasks/view_task_template', $data, true);
        }
    }

    private function nativeTaskReminder(string $action,$canonical=null,$reminder=null):void
    {
        try{
            CompanyRequestScope::assertNativeMutation($this);$ctx=CompanyRequestScope::context();if($ctx->issuerId()===null)throw new DomainException('native_task_reminder_denied');
            $input=$this->input->post(null,false);if(!is_array($input))throw new DomainException('native_task_reminder_invalid');unset($input['_company_context'],$input['_native_company_csrf']);if(isset($this->security))unset($input[$this->security->get_csrf_token_name()]);
            if(array_diff(array_keys($input),['taskid','native_revision','native_request_key','revision','description','date','staff','notify_by_email'])||!is_string($input['native_request_key']??null)||!preg_match('/^[a-f0-9]{64}$/D',$input['native_request_key']))throw new DomainException('native_task_reminder_invalid');
            $posted=$input['taskid']??null;if($canonical===null)$canonical=$posted;elseif($posted!==null&&(string)$posted!==(string)$canonical)throw new DomainException('native_task_reminder_invalid');
            require_once APPPATH.'services/NativeCompanyResourceReferences.php';require_once APPPATH.'services/NativeCompanyAuxiliary.php';
            $ref=\NativeCompanyResourceReferences::resolve($this->db,db_prefix(),$ctx->issuerId(),$ctx->staffId(),'task',$canonical,'edit');
            $service=new \NativeCompanyAuxiliary($this->db,db_prefix(),$ctx->issuerId(),$ctx->staffId());$id=null;
            if($reminder!==null){$r=\NativeCompanyResourceId::decode('reminder',$reminder);if($r['source_kind']!=='company_reminder')throw new DomainException('native_task_reminder_denied');$id=$r['source_id'];}
            $parentRevision=\IssuerPolicy::id($input['native_revision']??null);
            if($action==='delete')$service->removeTaskReminder($ref['canonical_id'],$id,$input['revision']??null,$input['native_request_key'],$parentRevision);
            else{
                require_once APPPATH.'services/NativeCompanyDateInput.php';$date=(new \NativeCompanyDateInput($this->db,db_prefix(),$ctx->issuerId()))->parse($input['date']??null);
                $id=$service->saveReminder('task',$ref['canonical_id'],['description'=>$input['description']??null,'date'=>$date,'staff'=>$input['staff']??null,'notify_by_email'=>$input['notify_by_email']??'0'],$id,$input['revision']??null,$input['native_request_key'],$parentRevision);
            }
            $this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode(['success'=>true,'id'=>\NativeCompanyResourceId::encode('company_reminder',$id),'taskHtml'=>$this->input->get('single_task')==='true'?$this->nativeTaskDetailHtml($ref['canonical_id']):'','alert_type'=>'success'],JSON_THROW_ON_ERROR));
        }catch(DomainException$e){$reason=$e->getMessage();show_error('Não foi possível confirmar o lembrete desta tarefa.',str_contains($reason,'stale')||str_contains($reason,'conflict')?409:(str_contains($reason,'denied')||str_contains($reason,'csrf')?403:422));}
        catch(RuntimeException$e){show_error('Não foi possível confirmar o armazenamento do lembrete.',503);}
    }

    public function add_reminder($task_id)
    {
        if($this->nativeCompanyTasks()){$this->nativeTaskReminder('add',$task_id);return;}
        $message    = '';
        $alert_type = 'warning';
        if ($this->input->post()) {
            $success = $this->misc_model->add_reminder($this->input->post(), $task_id);
            if ($success) {
                $alert_type = 'success';
                $message    = _l('reminder_added_successfully');
            }
        }
        echo json_encode([
            'taskHtml'   => $this->get_task_data($task_id, true),
            'alert_type' => $alert_type,
            'message'    => $message,
        ]);
    }

    public function edit_reminder($id)
    {
        if($this->nativeCompanyTasks()){$this->nativeTaskReminder('edit',null,$id);return;}
        $reminder = $this->misc_model->get_reminders($id);
        if ($reminder && ($reminder->creator == get_staff_user_id() || is_admin()) && $reminder->isnotified == 0) {
            $success = $this->misc_model->edit_reminder($this->input->post(), $id);
            echo json_encode([
                'taskHtml'   => $this->get_task_data($reminder->rel_id, true),
                'alert_type' => 'success',
                'message'    => ($success ? _l('updated_successfully', _l('reminder')) : ''),
            ]);
        }
    }

    public function delete_reminder($rel_id, $id)
    {
        if($this->nativeCompanyTasks()){$this->nativeTaskReminder('delete',$rel_id,$id);return;}
        $success    = $this->misc_model->delete_reminder($id);
        $alert_type = 'warning';
        $message    = _l('reminder_failed_to_delete');
        if ($success) {
            $alert_type = 'success';
            $message    = _l('reminder_deleted');
        }
        echo json_encode([
            'taskHtml'   => $this->get_task_data($rel_id, true),
            'alert_type' => $alert_type,
            'message'    => $message,
        ]);
    }

    public function get_staff_started_timers($return = false)
    {
        if ($this->nativeCompanyTasks()) {
            $ctx=CompanyRequestScope::context();
            if($ctx->issuerId()===null){show_error('Selecione uma empresa.',403);return;}
            if(!$return && $this->input->method(true)!=='GET'){show_error('Método não permitido.',405);return;}
            require_once APPPATH.'services/NativeCompanyTasksRead.php';require_once APPPATH.'services/NativeCompanyBlankTimers.php';require_once APPPATH.'services/NativeCompanyTimerPresentation.php';require_once APPPATH.'services/CompanySettingsStorage.php';
            try {
                $reader=new NativeCompanyTasksRead($this->db,db_prefix());$clock=time();
                $timers=$reader->startedTimers($ctx->issuerId(),$ctx->staffId(),$clock);
                $policyReader=new NativeCompanyTimerPolicy($this->db,db_prefix());$policy=$policyReader->read($ctx->issuerId(),$ctx->staffId());
                $hasBlank=(bool)array_filter($timers,static fn($timer)=>(string)$timer['task_id']==='0');
                $settings=new CompanySettingsStorage($this->db,db_prefix());$formats=['dateformat'=>$settings->get($ctx->issuerId(),'dateformat'),'time_format'=>$settings->get($ctx->issuerId(),'time_format')];$displayTimezone=date_default_timezone_get();
                $renderedTimers=$timers;foreach($renderedTimers as &$renderedTimer)$renderedTimer['started_at_label']=NativeCompanyTimerPresentation::startedLabel($renderedTimer,$formats,$displayTimezone);unset($renderedTimer);
                $nativeTimerPolicy=$policy+['can_start_blank'=>NativeCompanyTimerPolicy::canStartBlank($policy,$timers),'policy_hash'=>NativeCompanyBlankTimers::policyHash($policy)];
                $html=$this->load->view('admin/tasks/started_timers',['startedTimers'=>$renderedTimers,'nativeTimerPolicy'=>$nativeTimerPolicy],true);
                if(['dateformat'=>$settings->get($ctx->issuerId(),'dateformat'),'time_format'=>$settings->get($ctx->issuerId(),'time_format')]!==$formats||date_default_timezone_get()!==$displayTimezone)throw new DomainException('native_timer_policy_changed');
                if($policyReader->read($ctx->issuerId(),$ctx->staffId())!==$policy)throw new DomainException('native_timer_policy_changed');
                if($reader->startedTimers($ctx->issuerId(),$ctx->staffId(),$clock)!==$timers)throw new DomainException('native_company_task_read_changed');
                $json=json_encode(['html'=>$html,'total_timers'=>count($timers)],JSON_THROW_ON_ERROR);
                if($return)return $json;
                $this->output->set_header('Cache-Control: no-store');$this->output->set_content_type('application/json');$this->output->set_output($json);return;
            } catch(DomainException $error){show_error('Não foi possível confirmar os temporizadores desta empresa.',403);return;}
        }

        $data['startedTimers'] = $this->misc_model->get_staff_started_timers();
        $_data['html']         = $this->load->view('admin/tasks/started_timers', $data, true);
        $_data['total_timers'] = count($data['startedTimers']);

        $timers = json_encode($_data);
        if ($return) {
            return $timers;
        }

        echo $timers;
    }

    public function save_checklist_item_template()
    {
        if ($this->nativeCompanyTasks()) {
            try {
                CompanyRequestScope::assertNativeMutation($this);$ctx=CompanyRequestScope::context();if($ctx->issuerId()===null)throw new DomainException('native_task_template_denied');
                require_once APPPATH.'services/NativeCompanyTaskChecklistTemplates.php';$service=new NativeCompanyTaskChecklistTemplates($this->db,db_prefix());
                $result=$service->create($ctx->issuerId(),$ctx->staffId(),(string)$this->input->post('description',false),(string)$this->input->post('native_request_key'));
                $this->output->set_header('Content-Type: application/json; charset=utf-8')->set_output(json_encode($result,JSON_THROW_ON_ERROR));return;
            } catch (Throwable $error) { show_error('Não foi possível alterar o modelo de checklist.',422);return; }
        }

        if (staff_can('create',  'checklist_templates')) {
            $id = $this->tasks_model->add_checklist_template($this->input->post('description'));
            echo json_encode(['id' => $id]);
        }
    }

    public function remove_checklist_item_template($id)
    {
        if ($this->nativeCompanyTasks()) {
            try {
                CompanyRequestScope::assertNativeMutation($this);$ctx=CompanyRequestScope::context();if($ctx->issuerId()===null)throw new DomainException('native_task_template_denied');
                require_once APPPATH.'services/NativeCompanyTaskChecklistTemplates.php';$service=new NativeCompanyTaskChecklistTemplates($this->db,db_prefix());
                $result=$service->delete($ctx->issuerId(),$ctx->staffId(),$id,(string)$this->input->post('native_revision'),(string)$this->input->post('native_request_key'));
                $this->output->set_header('Content-Type: application/json; charset=utf-8')->set_output(json_encode($result,JSON_THROW_ON_ERROR));return;
            } catch (Throwable $error) { show_error('Não foi possível alterar o modelo de checklist.',422);return; }
        }

        if (staff_can('delete',  'checklist_templates')) {
            $success = $this->tasks_model->remove_checklist_item_template($id);
            echo json_encode(['success' => $success]);
        }
    }

    public function init_checklist_items()
    {
        if($this->nativeCompanyTasks()){
            try{CompanyRequestScope::assertNativeMutation($this);$ctx=CompanyRequestScope::context();$data=$this->input->post(null,false);if(!is_array($data))throw new DomainException('native_task_checklist_invalid');unset($data['_company_context'],$data['_native_company_csrf']);if(isset($this->security))unset($data[$this->security->get_csrf_token_name()]);if(array_keys($data)!==['taskid'])throw new DomainException('native_task_checklist_invalid');require_once APPPATH.'services/NativeCompanyTaskChecklistPresentation.php';$reader=new NativeCompanyTaskChecklistPresentation($this->db,db_prefix());$view=$reader->read($ctx->issuerId(),$ctx->staffId(),$data['taskid']);$html=$this->load->view('admin/tasks/checklist_items_template',$view,true);if($reader->read($ctx->issuerId(),$ctx->staffId(),$data['taskid'])!==$view)throw new DomainException('native_task_checklist_changed');$this->output->set_header('Cache-Control: no-store')->set_output($html);}
            catch(DomainException $error){show_error('Não foi possível confirmar os itens desta tarefa.',str_contains($error->getMessage(),'denied')?403:422);}catch(RuntimeException $error){show_error('Não foi possível confirmar os itens desta tarefa.',503);}return;
        }
        if ($this->input->is_ajax_request()) {
            if ($this->input->post()) {
                $post_data                       = $this->input->post();
                $data['task_id']                 = $post_data['taskid'];
                $data['checklists']              = $this->tasks_model->get_checklist_items($post_data['taskid']);
                $data['task_staff_members']      = $this->tasks_model->get_staff_members_that_can_access_task($data['task_id']);
                $data['current_user_is_creator'] = $this->tasks_model->is_task_creator(get_staff_user_id(), $data['task_id']);
                $data['hide_completed_items']    = get_staff_meta(get_staff_user_id(), 'task-hide-completed-items-' . $data['task_id']);

                $this->load->view('admin/tasks/checklist_items_template', $data);
            }
        }
    }

    public function task_tracking_stats($task_id)
    {
        if(class_exists('CompanyRequestScope',false)&&CompanyRequestScope::nativeFor($this)){
            if($this->input->method(true)!=='GET'){show_error('Método não permitido.',405);return;}
            try{$ctx=CompanyRequestScope::context();if($ctx->issuerId()===null)throw new DomainException('native_resource_reference_denied');require_once APPPATH.'services/NativeCompanyTasksRead.php';require_once APPPATH.'services/utilities/Format.php';$chart=(new NativeCompanyTasksRead($this->db,db_prefix()))->trackingStats($ctx->issuerId(),$ctx->staffId(),$task_id,'_l',[\app\services\utilities\Format::class,'sec2qty']);$data=['stats'=>json_encode($chart,JSON_THROW_ON_ERROR|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)];$html=$this->load->view('admin/tasks/tracking_stats',$data,true);$this->output->set_header('Cache-Control: no-store');$this->output->set_output($html);}catch(DomainException$e){show_error('Tarefa indisponível neste contexto.',422);}return;
        }

        $data['stats'] = json_encode($this->tasks_model->task_tracking_stats($task_id));
        $this->load->view('admin/tasks/tracking_stats', $data);
    }

    public function checklist_visibility($taskId)
    {
        if(!$this->nativeCompanyTasks()){show_error('Método não permitido.',405);return;}
        try{CompanyRequestScope::assertNativeMutation($this);$context=CompanyRequestScope::context();$data=$this->input->post(null,false);if(!is_array($data))throw new DomainException('native_checklist_visibility_invalid');unset($data['_company_context'],$data['_native_company_csrf']);if(isset($this->security))unset($data[$this->security->get_csrf_token_name()]);if(array_keys($data)!==['hide'])throw new DomainException('native_checklist_visibility_invalid');require_once APPPATH.'services/NativeCompanyTaskChecklistVisibility.php';$value=(new NativeCompanyTaskChecklistVisibility($this->db,db_prefix()))->save($context->issuerId(),$context->staffId(),$taskId,$data['hide']);$this->output->set_header('Cache-Control: no-store')->set_header('Content-Type: application/json; charset=utf-8')->set_output(json_encode(['success'=>true,'hide'=>$value],JSON_THROW_ON_ERROR));}
        catch(DomainException $error){show_error('Não foi possível confirmar a preferência desta tarefa.',str_contains($error->getMessage(),'denied')?403:422);}catch(RuntimeException $error){show_error('Não foi possível salvar a preferência desta tarefa.',503);}
    }

    /** Finite scoped checklist command used by the original task detail. */
    public function checklist_command($taskId)
    {
        $this->nativeChecklistCommand($taskId);
    }

    private function nativeChecklistCommand($taskId,?array $commandInput=null)
    {
        if(!$this->nativeCompanyTasks()){show_error('Método não permitido.',405);return;}
        try{
            CompanyRequestScope::assertNativeMutation($this);$context=CompanyRequestScope::context();if($context->issuerId()===null)throw new DomainException('native_task_checklist_denied');
            $data=$commandInput??$this->input->post(null,false);if(!is_array($data))throw new DomainException('native_task_checklist_invalid');unset($data['_company_context'],$data['_native_company_csrf']);if(isset($this->security))unset($data[$this->security->get_csrf_token_name()]);
            if(array_diff(array_keys($data),['action','task_revision','checklist_revision','request_key','item_id','item_revision','text','finished','assigned_id','ids'])||!is_string($data['action']??null)||!isset($data['task_revision'])||!is_string($data['request_key']??null)||!preg_match('/^[a-f0-9]{64}$/D',$data['request_key']))throw new DomainException('native_task_checklist_invalid');
            require_once APPPATH.'services/NativeCompanyResourceReferences.php';require_once APPPATH.'services/CompanyTasks.php';require_once APPPATH.'services/CompanyStandaloneTasks.php';require_once APPPATH.'services/NativeCompanyTaskItems.php';
            $ref=NativeCompanyResourceReferences::resolve($this->db,db_prefix(),$context->issuerId(),$context->staffId(),'task',$taskId,'edit');$writer=match($ref['source_kind']){'company_task'=>new CompanyTasks($this->db,db_prefix()),'company_standalone_task'=>new CompanyStandaloneTasks($this->db,db_prefix()),default=>throw new DomainException('native_task_legacy_writer_required')};
            $action=$data['action'];if($action==='initialize'){
                if(array_diff(array_keys($data),['action','task_revision','request_key']))throw new DomainException('native_task_checklist_invalid');$writer->initializeNativeChecklist($context->issuerId(),$context->staffId(),$ref['source_id'],$data['task_revision'],$data['request_key']);
            }else{
                if(!isset($data['checklist_revision']))throw new DomainException('native_task_checklist_invalid');$map=match($action){'add'=>['text'=>'text'],'edit'=>['item_id'=>'id','item_revision'=>'revision','text'=>'text'],'complete'=>['item_id'=>'id','item_revision'=>'revision','finished'=>'finished'],'assign'=>['item_id'=>'id','item_revision'=>'revision','assigned_id'=>'assigned_id'],'delete'=>['item_id'=>'id','item_revision'=>'revision'],'reorder'=>['ids'=>'ids'],default=>throw new DomainException('native_task_checklist_invalid')};
                if(array_diff(array_keys($data),array_merge(['action','task_revision','checklist_revision','request_key'],array_keys($map))))throw new DomainException('native_task_checklist_invalid');$command=['action'=>$action];foreach($map as$wire=>$field){if(!array_key_exists($wire,$data))throw new DomainException('native_task_checklist_invalid');$command[$field]=$data[$wire]===''&&$field==='assigned_id'?null:$data[$wire];}
                $writer->changeNativeChecklist($context->issuerId(),$context->staffId(),$ref['source_id'],$data['task_revision'],$data['checklist_revision'],$command,$data['request_key']);
            }
            $reader=new NativeCompanyTaskItems($this->db,db_prefix());$snapshot=$reader->read($context->issuerId(),$context->staffId(),$taskId);$row=$writer->get($context->issuerId(),$context->staffId(),$ref['source_id']);
            if($reader->read($context->issuerId(),$context->staffId(),$taskId)!==$snapshot||!hash_equals($snapshot['source_hash'],hash('sha256',$row['checklist_json']))||NativeCompanyResourceReferences::resolve($this->db,db_prefix(),$context->issuerId(),$context->staffId(),'task',$taskId,'edit')!==$ref)throw new DomainException('native_task_checklist_stale');
            $this->output->set_header('Cache-Control: no-store')->set_header('Content-Type: application/json; charset=utf-8')->set_output(json_encode(['success'=>true,'task_revision'=>(int)$row['revision'],'checklist'=>$snapshot],JSON_THROW_ON_ERROR));
        }catch(DomainException $error){$reason=$error->getMessage();show_error('Não foi possível confirmar os itens desta tarefa.',str_contains($reason,'stale')?409:(str_contains($reason,'denied')?403:422));}
         catch(RuntimeException $error){show_error('Não foi possível confirmar os itens desta tarefa.',503);}
    }

    /** Original checklist endpoints share the existing transactional command. */
    private function nativeChecklistOriginal(string $action,$item=null,$value=null):void
    {
        try{
            CompanyRequestScope::assertNativeMutation($this);$data=$this->input->post(null,false);
            if(!is_array($data))throw new DomainException('native_task_checklist_invalid');
            foreach(['taskid'=>'task_id','taskId'=>'task_id','listid'=>'item_id','checklistId'=>'item_id','description'=>'text','staffId'=>'assigned_id']as$old=>$new){
                if(array_key_exists($old,$data)){if(array_key_exists($new,$data))throw new DomainException('native_task_checklist_invalid');$data[$new]=$data[$old];unset($data[$old]);}
            }
            $task=$data['task_id']??null;unset($data['task_id']);
            if(!is_string($task)&&!is_int($task))throw new DomainException('native_task_checklist_invalid');
            if(isset($data['action'])&&$data['action']!==$action)throw new DomainException('native_task_checklist_invalid');$data['action']=$action;
            foreach(['item_id'=>$item,'finished'=>$value]as$field=>$route){if($route!==null){if(isset($data[$field])&&(string)$data[$field]!== (string)$route)throw new DomainException('native_task_checklist_invalid');$data[$field]=(string)$route;}}
            $this->nativeChecklistCommand($task,$data);
        }catch(DomainException $error){show_error('Solicitação de checklist inválida ou não autorizada.',str_contains($error->getMessage(),'denied')?403:422);}
    }

    public function checkbox_action($listid, $value)
    {
        if($this->nativeCompanyTasks()){$this->nativeChecklistOriginal('complete',$listid,$value);return;}
        $this->db->where('id', $listid);
        $this->db->update(db_prefix() . 'task_checklist_items', [
            'finished' => $value,
        ]);

        if ($this->db->affected_rows() > 0) {
            if ($value == 1) {
                $this->db->where('id', $listid);
                $this->db->update(db_prefix() . 'task_checklist_items', [
                    'finished_from' => get_staff_user_id(),
                ]);
                hooks()->do_action('task_checklist_item_finished', $listid);
            }
        }
    }

    public function add_checklist_item()
    {
        if($this->nativeCompanyTasks()){$this->nativeChecklistOriginal('add',null,null);return;}
        if ($this->input->is_ajax_request()) {
            if ($this->input->post()) {
                echo json_encode([
                    'success' => $this->tasks_model->add_checklist_item($this->input->post()),
                ]);
            }
        }
    }

    public function update_checklist_order()
    {
        if($this->nativeCompanyTasks()){$this->nativeChecklistOriginal('reorder',null,null);return;}
        if ($this->input->is_ajax_request()) {
            if ($this->input->post()) {
                $this->tasks_model->update_checklist_order($this->input->post());
            }
        }
    }

    public function delete_checklist_item($id)
    {
        if($this->nativeCompanyTasks()){$this->nativeChecklistOriginal('delete',$id,null);return;}
        $list = $this->tasks_model->get_checklist_item($id);
        if (staff_can('delete',  'tasks') || $list->addedfrom == get_staff_user_id()) {
            if ($this->input->is_ajax_request()) {
                echo json_encode([
                    'success' => $this->tasks_model->delete_checklist_item($id),
                ]);
            }
        }
    }

    public function update_checklist_item()
    {
        if($this->nativeCompanyTasks()){$this->nativeChecklistOriginal('edit',null,null);return;}
        if ($this->input->is_ajax_request()) {
            if ($this->input->post()) {
                $desc = $this->input->post('description');
                $desc = trim($desc);
                $this->tasks_model->update_checklist_item($this->input->post('listid'), $desc);
                echo json_encode(['can_be_template' => (total_rows(db_prefix() . 'tasks_checklist_templates', ['description' => $desc]) == 0)]);
            }
        }
    }

    public function make_public($task_id)
    {
        if($this->nativeCompanyTasks()){$this->nativeOriginalAction('public',$task_id);return;}
        if (staff_cant('edit', 'tasks')) {
            json_encode([
                'success' => false,
            ]);
            die;
        }
        echo json_encode([
            'success'  => $this->tasks_model->make_public($task_id),
            'taskHtml' => $this->get_task_data($task_id, true),
        ]);
    }

    public function add_external_attachment()
    {
        if ($this->input->post()) {
            $this->tasks_model->add_attachment_to_database(
                $this->input->post('task_id'),
                $this->input->post('files'),
                $this->input->post('external')
            );
        }
    }

    /* Add new task comment / ajax */
    public function add_task_comment()
    {
        if ($this->nativeCompanyTasks()) {
            $this->output->set_header('Cache-Control: no-store');
            try {
                CompanyRequestScope::assertNativeMutation($this);$context=CompanyRequestScope::context();if($context->issuerId()===null)throw new DomainException('native_company_task_denied');
                $input=$this->input->post(null,false);if(!is_array($input))throw new DomainException('native_task_comment_invalid');unset($input['_company_context'],$input['_native_company_csrf']);if(isset($this->security))unset($input[$this->security->get_csrf_token_name()]);
                if(array_diff(array_keys($input),['taskid','content','no_editor','native_revision','native_request_key'])||!isset($input['taskid'],$input['content'],$input['native_revision'],$input['native_request_key'])||!is_string($input['content'])||!is_string($input['native_request_key'])||!preg_match('/^[a-f0-9]{64}$/D',$input['native_request_key']))throw new DomainException('native_task_comment_invalid');
                foreach($_FILES as$upload){$errors=$upload['error']??null;if($errors===null)throw new DomainException('native_task_comment_invalid');$iterator=new RecursiveIteratorIterator(new RecursiveArrayIterator(is_array($errors)?$errors:[$errors]));foreach($iterator as$error)if((int)$error!==UPLOAD_ERR_NO_FILE)throw new DomainException('native_task_comment_attachments_separate');}
                if(isset($input['no_editor'])&&!in_array($input['no_editor'],['0','1'],true))throw new DomainException('native_task_comment_invalid');
                // The original no-editor control submits literal text. The
                // shared detail escapes it; rich editor requests are purified.
                $body=($input['no_editor']??'0')==='1'?trim($input['content']):trim(strip_tags(html_purify($input['content'])));if($body==='')throw new DomainException('native_task_comment_invalid');
                require_once APPPATH.'services/NativeCompanyResourceReferences.php';$reference=NativeCompanyResourceReferences::resolve($this->db,db_prefix(),$context->issuerId(),$context->staffId(),'task',$input['taskid'],'edit');$writer=$this->nativeTaskWriter($reference);$writer->activity($context->issuerId(),$context->staffId(),$reference['source_id'],$input['native_revision'],'comment',$input['native_request_key'],$body);
                $this->output->set_content_type('application/json')->set_output(json_encode(['success'=>true,'taskHtml'=>$this->nativeTaskDetailHtml($reference['canonical_id'])],JSON_THROW_ON_ERROR));
            } catch (DomainException $error) {show_error('Não foi possível confirmar o comentário, a revisão ou as permissões desta tarefa.',str_contains($error->getMessage(),'stale')?409:(str_contains($error->getMessage(),'denied')?403:422));}
              catch (RuntimeException $error) {show_error('Não foi possível armazenar o comentário da tarefa.',503);}
            return;
        }
        $data            = $this->input->post();
        $data['content'] = html_purify($this->input->post('content', false));
        if ($this->input->post('no_editor')) {
            $data['content'] = nl2br($this->input->post('content'));
        }
        $comment_id = false;
        if (
            $data['content'] != ''
            || (isset($_FILES['file']['name']) && is_array($_FILES['file']['name']) && count($_FILES['file']['name']) > 0)
        ) {
            $comment_id = $this->tasks_model->add_task_comment($data);
            if ($comment_id) {
                $commentAttachments = handle_task_attachments_array($data['taskid'], 'file');
                if ($commentAttachments && is_array($commentAttachments)) {
                    foreach ($commentAttachments as $file) {
                        $file['task_comment_id'] = $comment_id;
                        $this->misc_model->add_attachment_to_database($data['taskid'], 'task', [$file]);
                    }

                    if (count($commentAttachments) > 0) {
                        $this->db->query('UPDATE ' . db_prefix() . "task_comments SET content = CONCAT(content, '[task_attachment]')
                            WHERE id = " . $this->db->escape_str($comment_id));
                    }
                }
            }
        }
        echo json_encode([
            'success'  => $comment_id ? true : false,
            'taskHtml' => $this->get_task_data($data['taskid'], true),
        ]);
    }

    public function download_files($task_id, $comment_id = null)
    {
        $taskWhere = 'external IS NULL';

        if ($comment_id) {
            $taskWhere .= ' AND task_comment_id=' . $this->db->escape_str($comment_id);
        }

        if (staff_cant('view', 'tasks')) {
            $taskWhere .= ' AND ' . get_tasks_where_string(false);
        }

        $files = $this->tasks_model->get_task_attachments($task_id, $taskWhere);

        if (count($files) == 0) {
            redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
        }

        $path = get_upload_path_by_type('task') . $task_id;

        $this->load->library('zip');

        foreach ($files as $file) {
            $this->zip->read_file($path . '/' . $file['file_name']);
        }

        $this->zip->download('files.zip');
        $this->zip->clear_data();
    }

    /* Add new task follower / ajax */
    public function add_task_followers()
    {
        if($this->nativeCompanyTasks()){$this->nativeOriginalAction('follower_add');return;}
        $task = $this->tasks_model->get($this->input->post('taskid'));

        if (staff_can('edit', 'tasks') ||
                ($task->current_user_is_creator && staff_can('create', 'tasks'))) {
            echo json_encode([
                'success'  => $this->tasks_model->add_task_followers($this->input->post()),
                'taskHtml' => $this->get_task_data($this->input->post('taskid'), true),
            ]);
        }
    }

    /* Add task assignees / ajax */
    public function add_task_assignees()
    {
        if($this->nativeCompanyTasks()){$this->nativeOriginalAction('assignee_add');return;}
        $task = $this->tasks_model->get($this->input->post('taskid'));

        if (staff_can('edit', 'tasks') ||
                ($task->current_user_is_creator && staff_can('create', 'tasks'))) {
            echo json_encode([
                'success'  => $this->tasks_model->add_task_assignees($this->input->post()),
                'taskHtml' => $this->get_task_data($this->input->post('taskid'), true),
            ]);
        }
    }

    public function edit_comment()
    {
        if($this->nativeCompanyTasks()){$this->nativeOriginalAction('comment_edit');return;}
        if ($this->input->post()) {
            $data            = $this->input->post();
            $data['content'] = html_purify($this->input->post('content', false));
            if ($this->input->post('no_editor')) {
                $data['content'] = nl2br(clear_textarea_breaks($this->input->post('content')));
            }
            $success = $this->tasks_model->edit_comment($data);
            $message = '';
            if ($success) {
                $message = _l('task_comment_updated');
            }
            echo json_encode([
                'success'  => $success,
                'message'  => $message,
                'taskHtml' => $this->get_task_data($data['task_id'], true),
            ]);
        }
    }

    /* Remove task comment / ajax */
    public function remove_comment($id)
    {
        if($this->nativeCompanyTasks()){$this->nativeOriginalAction('comment_remove',null,$id);return;}
        echo json_encode([
            'success' => $this->tasks_model->remove_comment($id),
        ]);
    }

    /* Remove assignee / ajax */
    public function remove_assignee($id, $taskid)
    {
        if($this->nativeCompanyTasks()){$this->nativeOriginalAction('assignee_remove',$taskid,$id);return;}
        $task = $this->tasks_model->get($taskid);

        if (staff_can('edit', 'tasks') ||
                ($task->current_user_is_creator && staff_can('create', 'tasks'))) {
            $success = $this->tasks_model->remove_assignee($id, $taskid);
            $message = '';
            if ($success) {
                $message = _l('task_assignee_removed');
            }
            echo json_encode([
                'success'  => $success,
                'message'  => $message,
                'taskHtml' => $this->get_task_data($taskid, true),
            ]);
        }
    }

    /* Remove task follower / ajax */
    public function remove_follower($id, $taskid)
    {
        if($this->nativeCompanyTasks()){$this->nativeOriginalAction('follower_remove',$taskid,$id);return;}
        $task = $this->tasks_model->get($taskid);

        if (staff_can('edit', 'tasks') ||
                ($task->current_user_is_creator && staff_can('create', 'tasks'))) {
            $success = $this->tasks_model->remove_follower($id, $taskid);
            $message = '';
            if ($success) {
                $message = _l('task_follower_removed');
            }
            echo json_encode([
                'success'  => $success,
                'message'  => $message,
                'taskHtml' => $this->get_task_data($taskid, true),
            ]);
        }
    }

    /* Unmark task as complete / ajax*/
    public function unmark_complete($id)
    {
        if($this->nativeCompanyTasks()){$this->mark_as('1',$id);return;}
        if (
            $this->tasks_model->is_task_assignee(get_staff_user_id(), $id)
            || $this->tasks_model->is_task_creator(get_staff_user_id(), $id)
            || staff_can('edit',  'tasks')
        ) {
            $success = $this->tasks_model->unmark_complete($id);

            // Don't do this query if the action is not performed via task single
            $taskHtml = $this->input->get('single_task') === 'true' ? $this->get_task_data($id, true) : '';

            $message = '';
            if ($success) {
                $message = _l('task_unmarked_as_complete');
            }
            echo json_encode([
                'success'  => $success,
                'message'  => $message,
                'taskHtml' => $taskHtml,
            ]);
        } else {
            echo json_encode([
                'success'  => false,
                'message'  => '',
                'taskHtml' => '',
            ]);
        }
    }

    public function mark_as($status, $id)
    {
        if ($this->nativeCompanyTasks()) {
            try {
                CompanyRequestScope::assertNativeMutation($this);
                $context = CompanyRequestScope::context();
                if ($context->issuerId() === null) throw new DomainException('native_task_status_denied');
                $input = $this->input->post(null, false);
                if (!is_array($input)) throw new DomainException('native_task_status_invalid');
                unset($input['_company_context'], $input['_native_company_csrf']);
                if (isset($this->security)) unset($input[$this->security->get_csrf_token_name()]);
                if (array_diff(array_keys($input), ['native_revision','native_fields_revision','native_request_key'])
                    || !isset($input['native_revision'], $input['native_request_key'])
                    || !is_string($input['native_request_key']) || !preg_match('/^[a-f0-9]{64}$/D', $input['native_request_key'])) {
                    throw new DomainException('native_task_status_invalid');
                }
                require_once APPPATH.'services/NativeCompanyResourceReferences.php';
                require_once APPPATH.'services/CompanyTasks.php';
                require_once APPPATH.'services/CompanyStandaloneTasks.php';
                $reference = NativeCompanyResourceReferences::resolve($this->db, db_prefix(), $context->issuerId(), $context->staffId(), 'task', $id, 'view');
                $writer = match ($reference['source_kind']) {
                    'company_task' => new CompanyTasks($this->db, db_prefix()),
                    'company_standalone_task' => new CompanyStandaloneTasks($this->db, db_prefix()),
                    default => throw new DomainException('native_task_legacy_writer_required'),
                };
                $fieldsRevision = $input['native_fields_revision'] ?? null;
                if ($fieldsRevision === '') $fieldsRevision = null;
                $writer->markNativeStatus($context->issuerId(), $context->staffId(), $reference['source_id'], $input['native_revision'], $fieldsRevision, $status, $input['native_request_key']);
                $taskHtml=$this->input->get('single_task')==='true'?$this->nativeTaskDetailHtml($reference['canonical_id']):'';
                $this->output->set_header('Cache-Control: no-store')->set_header('Content-Type: application/json; charset=utf-8')
                    ->set_output(json_encode(['success'=>true,'message'=>_l('updated_successfully',_l('task')),'taskHtml'=>$taskHtml],JSON_THROW_ON_ERROR));
            } catch (DomainException $error) {
                $reason = $error->getMessage();
                show_error('Não foi possível confirmar o estado ou as permissões desta tarefa.', str_contains($reason,'stale') ? 409 : (str_contains($reason,'denied') ? 403 : 422));
            } catch (RuntimeException $error) {
                show_error('Não foi possível confirmar o armazenamento da tarefa.',503);
            }
            return;
        }
        if (
            $this->tasks_model->is_task_assignee(get_staff_user_id(), $id)
            || $this->tasks_model->is_task_creator(get_staff_user_id(), $id)
            || staff_can('edit',  'tasks')
        ) {
            $success = $this->tasks_model->mark_as($status, $id);

            // Don't do this query if the action is not performed via task single
            $taskHtml = $this->input->get('single_task') === 'true' ? $this->get_task_data($id, true) : '';

            $message = '';

            if ($success) {
                $message = _l('task_marked_as_success', format_task_status($status, true, true));
            }

            echo json_encode([
                'success'  => $success,
                'message'  => $message,
                'taskHtml' => $taskHtml,
            ]);
        } else {
            echo json_encode([
                'success'  => false,
                'message'  => '',
                'taskHtml' => '',
            ]);
        }
    }

    public function change_priority($priority_id, $id)
    {
        if ($this->nativeCompanyTasks()) {
            try {
                CompanyRequestScope::assertNativeMutation($this);$context=CompanyRequestScope::context();
                if($context->issuerId()===null)throw new DomainException('native_task_priority_denied');
                $input=$this->input->post(null,false);if(!is_array($input))throw new DomainException('native_task_priority_invalid');
                unset($input['_company_context'],$input['_native_company_csrf']);if(isset($this->security))unset($input[$this->security->get_csrf_token_name()]);
                if(array_diff(array_keys($input),['native_revision','native_fields_revision','native_request_key'])||!isset($input['native_revision'],$input['native_request_key'])||!is_string($input['native_request_key'])||!preg_match('/^[a-f0-9]{64}$/D',$input['native_request_key']))throw new DomainException('native_task_priority_invalid');
                require_once APPPATH.'services/NativeCompanyResourceReferences.php';require_once APPPATH.'services/CompanyTasks.php';require_once APPPATH.'services/CompanyStandaloneTasks.php';
                $reference=NativeCompanyResourceReferences::resolve($this->db,db_prefix(),$context->issuerId(),$context->staffId(),'task',$id,'view');
                $writer=match($reference['source_kind']){'company_task'=>new CompanyTasks($this->db,db_prefix()),'company_standalone_task'=>new CompanyStandaloneTasks($this->db,db_prefix()),default=>throw new DomainException('native_task_legacy_writer_required')};
                $fieldsRevision=$input['native_fields_revision']??null;if($fieldsRevision==='')$fieldsRevision=null;
                $writer->changeNativePriority($context->issuerId(),$context->staffId(),$reference['source_id'],$input['native_revision'],$fieldsRevision,$priority_id,$input['native_request_key']);
                $taskHtml=$this->input->get('single_task')==='true'?$this->nativeTaskDetailHtml($reference['canonical_id']):'';
                $this->output->set_header('Cache-Control: no-store')->set_header('Content-Type: application/json; charset=utf-8')->set_output(json_encode(['success'=>true,'taskHtml'=>$taskHtml],JSON_THROW_ON_ERROR));
            }catch(DomainException $error){$reason=$error->getMessage();show_error('Não foi possível confirmar a prioridade ou as permissões desta tarefa.',str_contains($reason,'stale')?409:(str_contains($reason,'denied')?403:422));}
             catch(RuntimeException $error){show_error('Não foi possível confirmar o armazenamento da tarefa.',503);}
            return;
        }
        if (staff_can('edit',  'tasks')) {
            $data = hooks()->apply_filters('before_update_task', ['priority' => $priority_id], $id);

            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'tasks', $data);

            $success = $this->db->affected_rows() > 0 ? true : false;

            hooks()->do_action('after_update_task', $id);

            // Don't do this query if the action is not performed via task single
            $taskHtml = $this->input->get('single_task') === 'true' ? $this->get_task_data($id, true) : '';
            echo json_encode([
                'success'  => $success,
                'taskHtml' => $taskHtml,
            ]);
        } else {
            echo json_encode([
                'success'  => false,
                'taskHtml' => $taskHtml,
            ]);
        }
    }

    public function change_milestone($milestone_id, $id)
    {
        if($this->nativeCompanyTasks()){$this->nativeOriginalAction('milestone',$id,$milestone_id);return;}
        if (staff_can('edit',  'tasks')) {
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'tasks', ['milestone' => $milestone_id]);

            $success = $this->db->affected_rows() > 0 ? true : false;
            // Don't do this query if the action is not performed via task single
            $taskHtml = $this->input->get('single_task') === 'true' ? $this->get_task_data($id, true) : '';
            echo json_encode([
                'success'  => $success,
                'taskHtml' => $taskHtml,
            ]);
        } else {
            echo json_encode([
                'success'  => false,
                'taskHtml' => $taskHtml,
            ]);
        }
    }

    public function task_single_inline_update($task_id)
    {
        if($this->nativeCompanyTasks()){$this->nativeOriginalAction('inline',$task_id);return;}
        if (staff_can('edit',  'tasks')) {
            $post_data = $this->input->post();
            foreach ($post_data as $key => $val) {
                $data = hooks()->apply_filters('before_update_task', [
                    $key => to_sql_date($val),
                ], $task_id);

                $this->db->where('id', $task_id);
                $this->db->update(db_prefix() . 'tasks', $data);

                hooks()->do_action('after_update_task', $task_id);
            }
        }
    }

    /* Delete task from database */
    public function delete_task($id)
    {
        if (staff_cant('delete', 'tasks')) {
            access_denied('tasks');
        }
        $success = $this->tasks_model->delete_task($id);
        $message = _l('problem_deleting', _l('task_lowercase'));
        if ($success) {
            $message = _l('deleted', _l('task'));
            set_alert('success', $message);
        } else {
            set_alert('warning', $message);
        }

        if (empty($_SERVER['HTTP_REFERER']) ||
            strpos($_SERVER['HTTP_REFERER'], 'tasks/index') !== false ||
            strpos($_SERVER['HTTP_REFERER'], 'tasks/view') !== false) {
            redirect(admin_url('tasks'));
        } else {
            redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
        }
    }

    /**
     * Remove task attachment
     * @since  Version 1.0.1
     * @param  mixed $id attachment it
     * @return json
     */
    public function remove_task_attachment($id)
    {
        if ($this->nativeCompanyTasks()) {
            $this->output->set_header('Cache-Control: no-store');
            try {
                CompanyRequestScope::assertNativeMutation($this);$context=CompanyRequestScope::context();if($context->issuerId()===null)throw new DomainException('native_company_task_denied');
                $input=$this->input->post(null,false);if(!is_array($input))throw new DomainException('company_project_file_invalid');unset($input['_company_context'],$input['_native_company_csrf']);if(isset($this->security))unset($input[$this->security->get_csrf_token_name()]);
                if(array_diff(array_keys($input),['taskid','native_revision','native_request_key'])||!isset($input['taskid'],$input['native_revision'])||!is_string($input['native_request_key']??null)||!preg_match('/^[a-f0-9]{64}$/D',$input['native_request_key']))throw new DomainException('company_project_file_invalid');
                require_once APPPATH.'services/NativeCompanyResourceReferences.php';$reference=NativeCompanyResourceReferences::resolve($this->db,db_prefix(),$context->issuerId(),$context->staffId(),'task',$input['taskid'],'edit');if($reference['source_kind']!=='company_task')throw new DomainException('company_project_access_denied');
                require_once APPPATH.'services/CompanyProjectExtras.php';(new CompanyProjectExtras($this->db,db_prefix()))->hide($context->issuerId(),$context->staffId(),'task',$reference['source_id'],$input['native_revision'],(string)$id,$input['native_request_key']);
                $this->output->set_content_type('application/json')->set_output(json_encode(['success'=>true,'taskHtml'=>$this->nativeTaskDetailHtml($reference['canonical_id'])],JSON_THROW_ON_ERROR));
            } catch (DomainException $error) {show_error('Não foi possível confirmar o arquivo, a revisão ou as permissões desta tarefa.',str_contains($error->getMessage(),'stale')?409:(str_contains($error->getMessage(),'denied')?403:422));}
              catch (RuntimeException $error) {show_error('Não foi possível atualizar o arquivo da tarefa.',503);}
            return;
        }
        if ($this->input->is_ajax_request()) {
            echo json_encode($this->tasks_model->remove_task_attachment($id));
        }
    }

    /**
     * Upload task attachment
     * @since  Version 1.0.1
     */
    public function upload_file()
    {
        if ($this->nativeCompanyTasks()) {
            $this->output->set_header('Cache-Control: no-store');
            try {
                CompanyRequestScope::assertNativeMutation($this);$context=CompanyRequestScope::context();if($context->issuerId()===null)throw new DomainException('native_company_task_denied');
                $input=$this->input->post(null,false);if(!is_array($input))throw new DomainException('company_project_file_invalid');unset($input['_company_context'],$input['_native_company_csrf']);if(isset($this->security))unset($input[$this->security->get_csrf_token_name()]);
                if(array_diff(array_keys($input),['taskid','native_revision','native_request_key'])||!isset($input['taskid'],$input['native_revision'])||!is_string($input['native_request_key']??null)||!preg_match('/^[a-f0-9]{64}$/D',$input['native_request_key']))throw new DomainException('company_project_file_invalid');
                require_once APPPATH.'services/NativeCompanyResourceReferences.php';$reference=NativeCompanyResourceReferences::resolve($this->db,db_prefix(),$context->issuerId(),$context->staffId(),'task',$input['taskid'],'edit');if($reference['source_kind']!=='company_task')throw new DomainException('company_project_access_denied');
                $file=$_FILES['file']??null;if(!is_array($file)||is_array($file['name']??null)||!isset($file['name'],$file['tmp_name'],$file['error'])||(int)$file['error']!==UPLOAD_ERR_OK||!is_string($file['name'])||!is_string($file['tmp_name'])||(!is_uploaded_file($file['tmp_name'])&&PHP_SAPI!=='cli'))throw new DomainException('company_project_file_invalid');$bytes=file_get_contents($file['tmp_name']);if(!is_string($bytes))throw new DomainException('company_project_file_invalid');
                require_once APPPATH.'services/CompanyProjectExtras.php';(new CompanyProjectExtras($this->db,db_prefix()))->uploadNative($context->issuerId(),$context->staffId(),'task',$reference['source_id'],$input['native_revision'],$input['native_request_key'],$file['name'],$bytes);
                $this->output->set_content_type('application/json')->set_output(json_encode(['success'=>true,'taskHtml'=>$this->nativeTaskDetailHtml($reference['canonical_id'])],JSON_THROW_ON_ERROR));
            } catch (DomainException $error) {show_error('Não foi possível confirmar o arquivo, a revisão ou as permissões desta tarefa.',str_contains($error->getMessage(),'stale')?409:(str_contains($error->getMessage(),'denied')?403:422));}
              catch (RuntimeException $error) {show_error('Não foi possível armazenar o arquivo da tarefa.',503);}
            return;
        }
        if ($this->input->post()) {
            $taskid  = $this->input->post('taskid');
            $files   = handle_task_attachments_array($taskid, 'file');
            $success = false;

            if ($files) {
                $i   = 0;
                $len = count($files);
                foreach ($files as $file) {
                    $success = $this->tasks_model->add_attachment_to_database($taskid, [$file], false, ($i == $len - 1 ? true : false));
                    $i++;
                }
            }

            echo json_encode([
                'success'  => $success,
                'taskHtml' => $this->get_task_data($taskid, true),
            ]);
        }
    }

    public function timer_tracking()
    {
        if($this->nativeCompanyTasks()){
            try{
                CompanyRequestScope::assertNativeMutation($this);$ctx=CompanyRequestScope::context();$issuer=$ctx->issuerId();$actor=$ctx->staffId();if($issuer===null)throw new DomainException('native_company_task_denied');
                if($this->input->get('admin_stop'))throw new DomainException('native_timer_admin_stop_denied');
                require_once APPPATH.'services/CompanyTasks.php';require_once APPPATH.'services/CompanyStandaloneTasks.php';require_once APPPATH.'services/NativeCompanyBlankTimers.php';require_once APPPATH.'services/NativeCompanyResourceId.php';
                $body=$this->input->post(null,false);if(!is_array($body))throw new DomainException('native_timer_input_invalid');
                $integer=static function($value,bool$zero=false):int{if(!is_int($value)&&!is_string($value))throw new DomainException('native_timer_input_invalid');if(!preg_match($zero?'/^(0|[1-9][0-9]{0,9})$/D':'/^[1-9][0-9]{0,9}$/D',(string)$value)||(float)$value>2147483646)throw new DomainException('native_timer_input_invalid');return(int)$value;};
                $task=$body['task_id']??null;$timer=$body['timer_id']??null;$note=$body['note']??'';$key=$body['native_request_key']??null;$policy=$body['native_policy_hash']??null;if(!is_string($task)||!is_string($timer)||!is_string($note)||!is_string($key)||!preg_match('/^[a-f0-9]{64}$/D',$key)||!is_string($policy)||!preg_match('/^[a-f0-9]{64}$/D',$policy))throw new DomainException('native_timer_input_invalid');
                if($task==='0'&&$timer===''){(new NativeCompanyBlankTimers($this->db,db_prefix()))->start($issuer,$actor,$policy,$key,$note);}
                else{
                    $target=NativeCompanyResourceId::decode('task',$task);$writer=match($target['source_kind']){'company_task'=>new CompanyTasks($this->db,db_prefix()),'company_standalone_task'=>new CompanyStandaloneTasks($this->db,db_prefix()),default=>throw new DomainException('native_timer_legacy_adapter_required')};$taskRevision=$integer($body['native_task_revision']??null);
                    if($timer==='')$writer->nativeTimer($issuer,$actor,$target['source_id'],$taskRevision,'start',null,0,$note,$policy,$key);
                    else{
                        $source=NativeCompanyResourceId::decode('timer',$timer);
                        if($source['source_kind']==='company_blank_task_timer'){
                            // Assignment has no automatic policy effect; nevertheless revalidate the rendered policy.
                            if(!hash_equals($policy,NativeCompanyBlankTimers::policyHash((new NativeCompanyTimerPolicy($this->db,db_prefix()))->read($issuer,$actor))))throw new DomainException('native_timer_policy_changed');
                            (new NativeCompanyBlankTimers($this->db,db_prefix()))->assign($issuer,$actor,$source['source_id'],$integer($body['native_timer_revision']??null),$target['source_kind'],$target['source_id'],$taskRevision,$note,$key);
                        }else{
                            if($source['source_kind']!==($target['source_kind']==='company_task'?'company_task_timer':'company_standalone_timer'))throw new DomainException('native_timer_input_invalid');$writer->nativeTimer($issuer,$actor,$target['source_id'],$taskRevision,'stop',$source['source_id'],$integer($body['native_note_revision']??null,true),$note,$policy,$key);
                        }
                    }
                }
                $taskHtml=$this->input->get('single_task')==='true'&&$task!=='0'?$this->nativeTaskDetailHtml($task):'';
                $json=json_encode(['success'=>true,'taskHtml'=>$taskHtml,'timers'=>$this->get_staff_started_timers(true)],JSON_THROW_ON_ERROR);$this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output($json);
            }catch(DomainException$e){show_error('Não foi possível confirmar o temporizador nesta empresa.',422);}return;
        }
        $task_id   = $this->input->post('task_id');
        $adminStop = $this->input->get('admin_stop') && is_admin() ? true : false;

        if ($adminStop) {
            $this->session->set_flashdata('task_single_timesheets_open', true);
        }

        echo json_encode([
            'success' => $this->tasks_model->timer_tracking(
                $task_id,
                $this->input->post('timer_id'),
                nl2br($this->input->post('note')),
                $adminStop
            ),
            'taskHtml' => $this->input->get('single_task') === 'true' ? $this->get_task_data($task_id, true) : '',
            'timers'   => $this->get_staff_started_timers(true),
        ]);
    }

    public function delete_user_unfinished_timesheet($id)
    {
        if($this->nativeCompanyTasks()){
            try{CompanyRequestScope::assertNativeMutation($this);$ctx=CompanyRequestScope::context();require_once APPPATH.'services/NativeCompanyBlankTimers.php';require_once APPPATH.'services/NativeCompanyResourceId.php';
                $wire=$this->input->post(null,false);if(!is_array($wire))throw new DomainException('native_timer_invalid');unset($wire['_company_context'],$wire['_native_company_csrf']);if(isset($this->security))unset($wire[$this->security->get_csrf_token_name()]);
                if(array_diff(array_keys($wire),['native_timer_revision','native_request_key']))throw new DomainException('native_timer_invalid');$ref=\NativeCompanyResourceId::decode('timer',$id);if($ref['source_kind']!=='company_blank_task_timer')throw new DomainException('native_timer_denied');
                (new \NativeCompanyBlankTimers($this->db,db_prefix()))->discard($ctx->issuerId(),$ctx->staffId(),$ref['source_id'],$wire['native_timer_revision']??null,$wire['native_request_key']??'');$this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode(['success'=>true],JSON_THROW_ON_ERROR));
            }catch(DomainException$error){show_error('Não foi possível confirmar o temporizador pendente.',str_contains($error->getMessage(),'denied')?403:(preg_match('/stale|conflict/',$error->getMessage())?409:422));}catch(RuntimeException$error){show_error('Não foi possível confirmar o armazenamento do temporizador.',503);}return;
        }

        $this->db->where('id', $id);
        $timesheet = $this->db->get(db_prefix() . 'taskstimers')->row();
        if ($timesheet && $timesheet->end_time == null && $timesheet->staff_id == get_staff_user_id()) {
            $this->db->where('id', $id);
            $this->db->delete(db_prefix() . 'taskstimers');
        }
        echo json_encode(['timers' => $this->get_staff_started_timers(true)]);
    }

    public function delete_timesheet($id)
    {
        if($this->nativeCompanyTasks()){$this->nativeTimesheetAction('delete',$id);return;}
        if (staff_can('delete_timesheet', 'tasks') || staff_can('delete_own_timesheet', 'tasks') && total_rows(db_prefix() . 'taskstimers', ['staff_id' => get_staff_user_id(), 'id' => $id]) > 0) {
            $alert_type = 'warning';
            $success    = $this->tasks_model->delete_timesheet($id);
            if ($success) {
                $this->session->set_flashdata('task_single_timesheets_open', true);
                $message = _l('deleted', _l('project_timesheet'));
                set_alert('success', $message);
            }
            if (!$this->input->is_ajax_request()) {
                redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
            }
        }
    }

    public function update_timesheet()
    {
        if($this->nativeCompanyTasks()){$this->nativeTimesheetAction('edit');return;}
        if ($this->input->is_ajax_request()) {
            if (staff_can('edit_timesheet', 'tasks') || (staff_can('edit_own_timesheet', 'tasks') && total_rows(db_prefix() . 'taskstimers', ['staff_id' => get_staff_user_id(), 'id' => $this->input->post('timer_id')]) > 0)) {
                $success = $this->tasks_model->timesheet($this->input->post());
                if ($success === true) {
                    $this->session->set_flashdata('task_single_timesheets_open', true);
                    $message = _l('updated_successfully', _l('project_timesheet'));
                } else {
                    $message = _l('failed_to_update_timesheet');
                }

                echo json_encode([
                    'success' => $success,
                    'message' => $message,
                ]);
                die;
            }

            echo json_encode([
                'success' => false,
                'message' => _l('access_denied'),
            ]);
            die;
        }
    }

    public function log_time()
    {
        if($this->nativeCompanyTasks()){$this->nativeTimesheetAction('add');return;}
        $success = $this->tasks_model->timesheet($this->input->post());
        if ($success === true) {
            $this->session->set_flashdata('task_single_timesheets_open', true);
            $message = _l('added_successfully', _l('project_timesheet'));
        } elseif (is_array($success) && isset($success['end_time_smaller'])) {
            $message = _l('failed_to_add_project_timesheet_end_time_smaller');
        } else {
            $message = _l('project_timesheet_not_updated');
        }

        echo json_encode([
            'success' => $success,
            'message' => $message,
        ]);
        die;
    }

    public function update_tags()
    {
        if($this->nativeCompanyTasks()){$this->nativeOriginalAction('tags');return;}
        if (staff_can('create',  'tasks') || staff_can('edit',  'tasks')) {
            $id = $this->input->post('task_id');

            $data = hooks()->apply_filters('before_update_task', [
                'tags' => $this->input->post('tags'),
            ], $id);

            handle_tags_save($data['tags'], $id, 'task');

            hooks()->do_action('after_update_task', $id);
        }
    }

    public function bulk_action()
    {
        hooks()->do_action('before_do_bulk_action_for_tasks');
        $total_deleted = 0;
        if ($this->input->post()) {
            $status    = $this->input->post('status');
            $ids       = $this->input->post('ids');
            $tags      = $this->input->post('tags');
            $assignees = $this->input->post('assignees');
            $milestone = $this->input->post('milestone');
            $priority  = $this->input->post('priority');
            $billable  = $this->input->post('billable');
            $is_admin  = is_admin();
            if (is_array($ids)) {
                foreach ($ids as $id) {
                    if ($this->input->post('mass_delete')) {
                        if (staff_can('delete',  'tasks')) {
                            if ($this->tasks_model->delete_task($id)) {
                                $total_deleted++;
                            }
                        }
                    } else {
                        if ($status) {
                            if (
                                $this->tasks_model->is_task_creator(get_staff_user_id(), $id)
                                || $is_admin
                                || $this->tasks_model->is_task_assignee(get_staff_user_id(), $id)
                            ) {
                                $this->tasks_model->mark_as($status, $id);
                            }
                        }
                        if ($priority || $milestone || ($billable === 'billable' || $billable === 'not_billable')) {
                            $update = [];

                            if ($priority) {
                                $update['priority'] = $priority;
                            }

                            if ($milestone) {
                                $update['milestone'] = $milestone;
                            }

                            if ($billable) {
                                $update['billable'] = $billable === 'billable' ? 1 : 0;
                            }

                            $this->db->where('id', $id);
                            $this->db->update(db_prefix() . 'tasks', $update);
                        }
                        if ($tags) {
                            handle_tags_save($tags, $id, 'task');
                        }
                        if ($assignees) {
                            $notifiedUsers = [];
                            foreach ($assignees as $user_id) {
                                if (!$this->tasks_model->is_task_assignee($user_id, $id)) {
                                    $this->db->select('rel_type,rel_id');
                                    $this->db->where('id', $id);
                                    $task = $this->db->get(db_prefix() . 'tasks')->row();
                                    if ($task->rel_type == 'project') {
                                        // User is we are trying to assign the task is not project member
                                        if (total_rows(db_prefix() . 'project_members', ['project_id' => $task->rel_id, 'staff_id' => $user_id]) == 0) {
                                            $this->db->insert(db_prefix() . 'project_members', ['project_id' => $task->rel_id, 'staff_id' => $user_id]);
                                        }
                                    }
                                    $this->db->insert(db_prefix() . 'task_assigned', [
                                        'staffid'       => $user_id,
                                        'taskid'        => $id,
                                        'assigned_from' => get_staff_user_id(),
                                    ]);
                                    if ($user_id != get_staff_user_id()) {
                                        $notification_data = [
                                            'description' => 'not_task_assigned_to_you',
                                            'touserid'    => $user_id,
                                            'link'        => '#taskid=' . $id,
                                        ];

                                        $notification_data['additional_data'] = serialize([
                                            get_task_subject_by_id($id),
                                        ]);
                                        if (add_notification($notification_data)) {
                                            array_push($notifiedUsers, $user_id);
                                        }
                                    }
                                }
                            }
                            pusher_trigger_notification($notifiedUsers);
                        }
                    }
                }
            }
            if ($this->input->post('mass_delete')) {
                set_alert('success', _l('total_tasks_deleted', $total_deleted));
            }
        }
    }

    public function gantt_date_update($task_id)
    {
        if (staff_can('edit', 'tasks')) {
            $post_data = $this->input->post();
            foreach ($post_data as $key => $val) {
                $this->db->where('id', $task_id);
                $this->db->update(db_prefix() . 'tasks', [$key => $val]);
            }
        }
    }

    public function get_task_by_id($id)
    {
        if ($this->nativeCompanyTasks()) {
            if (strtoupper($this->input->method(true)) !== 'GET') {
                show_error('Método não permitido.', 405);
                return;
            }
            try {
                $ctx = CompanyRequestScope::context();
                if ($ctx->issuerId() === null) throw new DomainException('native_company_task_denied');
                require_once APPPATH . 'services/NativeCompanyTasksRead.php';
                $task = (new NativeCompanyTasksRead($this->db, db_prefix()))->relationOption(
                    $ctx->issuerId(), $ctx->staffId(), $id
                );
                // The native timer needs only the authorized identity and name.
                $this->output->set_header('Cache-Control: no-store');
                $this->output->set_header('Content-Type: application/json; charset=utf-8');
                $this->output->set_output(json_encode(['id' => $task['id'], 'name' => $task['name']], JSON_THROW_ON_ERROR));
            } catch (DomainException $error) {
                show_error('Tarefa indisponível neste contexto.', 422);
            }
            return;
        }
        if ($this->input->is_ajax_request()) {
            $tasks_where = [];
            if (staff_cant('view', 'tasks')) {
                $tasks_where = get_tasks_where_string(false);
            }
            $task = $this->tasks_model->get($id, $tasks_where);
            if (!$task) {
                header('HTTP/1.0 404 Not Found');
                echo 'Task not found';
                die();
            }
            echo json_encode($task);
        }
    }

    public function get_staff_names_for_mentions($taskid)
    {
        if(class_exists('CompanyRequestScope',false)&&CompanyRequestScope::nativeFor($this)){
            if($this->input->method(true)!=='GET'){show_error('Método não permitido.',405);return;}
            try{$ctx=CompanyRequestScope::context();if($ctx->issuerId()===null)throw new DomainException('native_resource_reference_denied');require_once APPPATH.'services/NativeCompanyTasksRead.php';$members=(new NativeCompanyTasksRead($this->db,db_prefix()))->mentionMembers($ctx->issuerId(),$ctx->staffId(),$taskid);$this->output->set_header('Cache-Control: no-store');$this->output->set_header('Content-Type: application/json');$this->output->set_output(json_encode($members,JSON_THROW_ON_ERROR));}catch(DomainException$e){show_error('Recurso indisponível neste contexto.',422);}return;
        }

        if ($this->input->is_ajax_request()) {
            $taskId = $this->db->escape_str($taskid);

            $members = $this->tasks_model->get_staff_members_that_can_access_task($taskId);
            $members = array_map(function ($member) {
                $_member['id'] = $member['staffid'];
                $_member['name'] = e($member['firstname'] . ' ' . $member['lastname']);

                return $_member;
            }, $members);

            echo json_encode($members);
        }
    }

    public function save_checklist_assigned_staff()
    {
        if($this->nativeCompanyTasks()){$this->nativeChecklistOriginal('assign',null,null);return;}
        if ($this->input->post() && $this->input->is_ajax_request()) {
            $payload = $this->input->post();
            $item    = $this->tasks_model->get_checklist_item($payload['checklistId']);
            if ($item->addedfrom == get_staff_user_id()
                || is_admin() ||
                $this->tasks_model->is_task_creator(get_staff_user_id(), $payload['taskId'])) {
                $this->tasks_model->update_checklist_assigned_staff($payload);
                die;
            }

            ajax_access_denied();
        }
    }
}
