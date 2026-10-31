<?php

use app\services\projects\AllProjectsGantt;
use app\services\projects\Gantt;
use app\services\projects\HoursOverviewChart;

defined('BASEPATH') or exit('No direct script access allowed');
require_once APPPATH.'services/NativeCompanySpecializedOperations.php';
require_once APPPATH.'services/CompanyOperationalPortalAdmin.php';
require_once APPPATH.'services/NativeCompanyResourceId.php';
require_once APPPATH.'services/NativeCompanyProjectOriginalHttp.php';
require_once APPPATH.'services/NativeProjectTabs.php';
require_once APPPATH.'services/NativeCompanyProjectPins.php';

class Projects extends AdminController
{
    use NativeCompanySpecializedOperations;
    use \NativeCompanyProjectOriginalHttp;

    public function extras() { $this->nativeProjectExtras(); }
    public function portal_rules() { $this->nativeProjectPortalRules(); }
    public function billing_rates() { $this->nativeProjectBillingRates(); }
    public function fixed_billing() { $this->nativeProjectFixedBilling(); }
    public function copies() { $this->nativeProjectCopies(); }
    public function time_billing() { $this->nativeProjectTimeBilling(); }
    public function __construct()
    {
        parent::__construct();
        if(!$this->nativeProjects()){
            $this->load->model('projects_model');
            $this->load->model('currencies_model');
        }
        $this->load->helper('date');
    }

    private function nativeProjects():bool
    {return class_exists('CompanyRequestScope',false)&&CompanyRequestScope::nativeFor($this);}

    private function nativeProjectIndex():void
    {
        if(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
        $ctx=CompanyRequestScope::context();if($ctx->issuerId()===null){show_error('Selecione uma empresa.',403);return;}
        require_once APPPATH.'services/NativeCompanyProjectTableFactory.php';
        require_once APPPATH.'services/NativeCompanyFilters.php';
        try{
            $issuer=$ctx->issuerId();$actor=$ctx->staffId();$factory=new NativeCompanyProjectTableFactory($this->db,db_prefix());$catalog=$factory->data($issuer,$actor);
            $table=$factory->make($issuer,$actor);$saved=(new NativeCompanyFilters($this->db,db_prefix(),$issuer,$actor,$table))->listing();$table=$factory->make($issuer,$actor,'projects',$saved);
            $acl=new IssuerResourceAccess($this->db,db_prefix());
            $data=['title'=>_l('projects'),'table'=>$table,'statuses'=>$catalog['summary']['statuses'],'native_project_summary'=>$catalog['summary'],
                'native_project_permissions'=>['create'=>$acl->can($issuer,$actor,'projects','create'),'edit'=>$acl->can($issuer,$actor,'projects','edit'),'delete'=>$acl->can($issuer,$actor,'projects','delete')],
                'native_project_custom_fields'=>array_values(array_filter($catalog['customDefinitions'],static fn($d)=>(int)$d['show_on_table']===1)),
                'task_statuses'=>NativeCompanyWorkStatusCatalog::owned('task','_l')];
            $html=$this->load->view('admin/projects/manage',$data,true);
            if($factory->data($issuer,$actor)!==$catalog||!$acl->can($issuer,$actor,'projects','view'))throw new DomainException('native_project_snapshot_changed');
            $this->output->set_header('Cache-Control: no-store');$this->output->set_output($html);
        }catch(DomainException$e){show_error('Não foi possível confirmar os projetos e permissões desta empresa.',403);}
    }

    public function index()
    {
        if($this->nativeProjects()){$this->nativeProjectIndex();return;}
        close_setup_menu();
        $data['statuses'] = $this->projects_model->get_project_statuses();
        $data['title']    = _l('projects');
        $data['table']    = App_table::find('projects');
        $this->load->view('admin/projects/manage', $data);
    }

    public function table($clientid = '')
    {
        if($this->nativeProjects()){
            $this->output->set_header('Cache-Control: no-store');
            try{
                CompanyRequestScope::assertNativeMutation($this);
                require_once APPPATH.'services/NativeCompanyProjectPage.php';require_once APPPATH.'services/NativeCompanyProjectRows.php';
                $input=$this->input->post(null,false)?:[];unset($input['_company_context'],$input['_native_company_csrf']);if(isset($this->security))unset($input[$this->security->get_csrf_token_name()]);
                $ctx=CompanyRequestScope::context();if($ctx->issuerId()===null)throw new DomainException('native_project_access_denied');
                $reader=new NativeCompanyProjectPage($this->db,db_prefix());$today=gmdate('Y-m-d');$page=$reader->read($ctx->issuerId(),$ctx->staffId(),$input,$clientid,$today);
                $result=(new NativeCompanyProjectRows($this->db,db_prefix()))->render($ctx->issuerId(),$ctx->staffId(),$page,$clientid);
                if($reader->read($ctx->issuerId(),$ctx->staffId(),$input,$clientid,$today)!==$page)throw new DomainException('native_project_snapshot_changed');
                $this->output->set_content_type('application/json')->set_output(json_encode($result,JSON_THROW_ON_ERROR));
            }catch(DomainException$e){show_error('Lista de projetos indisponível ou solicitação inválida nesta empresa.',str_contains($e->getMessage(),'invalid')?422:403);}
            catch(Throwable$e){show_error('Não foi possível confirmar a lista de projetos.',503);}
            return;
        }
        App_table::find('projects')->output([
            'clientid' => $clientid,
        ]);
    }

    public function staff_projects()
    {
        if($this->nativeProjects()){
            $this->output->set_header('Cache-Control: no-store');
            require_once APPPATH.'services/NativeCompanyStaffProjects.php';
            try{
                $data=NativeCompanyStaffProjects::table($this);
                $this->output->set_content_type('application/json')->set_output(json_encode($data,JSON_THROW_ON_ERROR));
            }catch(DomainException $e){show_error('Projetos do colaborador indisponíveis nesta empresa.',str_contains($e->getMessage(),'invalid')?422:403);}
            catch(Throwable $e){show_error('Não foi possível confirmar os projetos do colaborador.',503);}
            return;
        }
        $this->app->get_table_data('staff_projects');
    }

    public function expenses($id)
    {
        if($this->nativeCompanyProjects()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'projects','expenses',[$id]);return;}
        $this->load->model('expenses_model');
        $this->load->model('payment_modes_model');
        $data['payment_modes'] = $this->payment_modes_model->get('', [], true);

        App_table::find('project_expenses')->output([
            'project_id' => $id,
            'data'       => $data,
        ]);
    }

    public function add_expense()
    {
        if($this->nativeCompanyProjects()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'projects','add_expense',[]);return;}
        if ($this->input->post()) {
            $this->load->model('expenses_model');
            $id = $this->expenses_model->add($this->input->post());
            if ($id) {
                set_alert('success', _l('added_successfully', _l('expense')));
                echo json_encode([
                    'url'       => admin_url('projects/view/' . $this->input->post('project_id') . '/?group=project_expenses'),
                    'expenseid' => $id,
                ]);

                exit;
            }
            echo json_encode([
                'url' => admin_url('projects/view/' . $this->input->post('project_id') . '/?group=project_expenses'),
            ]);

            exit;
        }
    }

    private function nativeProjectForm($id):void
    {
        $this->output->set_header('Cache-Control: no-store');
        try{
            $ctx=CompanyRequestScope::context();if($ctx->issuerId()===null)throw new DomainException('native_project_access_denied');require_once APPPATH.'services/NativeCompanyProjectForm.php';$adapter=new NativeCompanyProjectForm($this->db,db_prefix());
            if(strtoupper($this->input->method(true))==='POST'){
                CompanyRequestScope::assertNativeMutation($this);$input=$this->input->post(null,false)?:[];unset($input['_company_context'],$input['_native_company_csrf']);if(isset($this->security))unset($input[$this->security->get_csrf_token_name()]);
                if(isset($input['description']))$input['description']=html_purify($input['description']);$saved=$adapter->save($ctx->issuerId(),$ctx->staffId(),$id,$input);set_alert('success',_l($id===''?'added_successfully':'updated_successfully',_l('project')));redirect(admin_url('projects/view/'.$saved),'location',303);return;
            }
            if(!in_array(strtoupper($this->input->method(true)),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
            if($this->input->get('via_estimate_id'))throw new DomainException('native_project_action_not_composed');$today=date('Y-m-d');$customer=$this->input->get('customer_id');$data=$adapter->read($ctx->issuerId(),$ctx->staffId(),$id,$customer,$today);$snapshot=$data;
            $data['native_project_form']['context_token']=$ctx->token();$data['native_project_form']['csrf_token']=CompanyRequestScope::nativeCsrfToken($this);$data['native_project_form']['request_key']=bin2hex(random_bytes(32));
            $html=$this->load->view('admin/projects/project',$data,true);if(json_encode($adapter->read($ctx->issuerId(),$ctx->staffId(),$id,$customer,$today),JSON_THROW_ON_ERROR)!==json_encode($snapshot,JSON_THROW_ON_ERROR))throw new DomainException('native_project_snapshot_changed');$this->output->set_output($html);
        }catch(DomainException$e){show_error('Não foi possível confirmar os dados, a ação ou as permissões deste projeto.',422);}catch(RuntimeException$e){show_error('Não foi possível confirmar o armazenamento do projeto.',503);}
    }

    private function nativeProjectPortalCsrf():string{$token=$this->session->userdata('company_projects_csrf');if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/D',$token)){$token=bin2hex(random_bytes(32));$this->session->set_userdata('company_projects_csrf',$token);}return $token;}
    private function nativeProjectView($id,?string $selectedGroup=null):void
    {
        $this->output->set_header('Cache-Control: no-store');
        try{
            $method=strtoupper($this->input->method(true));
            if($method==='POST'){
                if(in_array($this->input->post('action',false),NativeCompanyProjectRuleCommands::ROUTES['view'],true)){$this->nativeProjectRuleAction('view',$id);return;}
                CompanyRequestScope::assertNativeMutation($this);
                $ctx=CompanyRequestScope::context();if($ctx->issuerId()===null)throw new DomainException('native_project_access_denied');
                $token=$this->input->post('company_projects_csrf',false);if(!is_string($token)||!hash_equals($this->nativeProjectPortalCsrf(),$token))throw new DomainException('company_portal_csrf_invalid');
                $ref=NativeCompanyResourceId::decode('project',$id);if($ref['source_kind']!=='company_project')throw new DomainException('company_portal_operation_unavailable');
                $action=$this->input->post('action',false);$revision=$this->input->post('revision',false);$key=$this->input->post('request_key',false);if(!is_string($action)||!is_string($revision)||!is_string($key)||!CompanyOperationalPortalAdmin::handle($this,$ctx->issuerId(),$ctx->staffId(),'projects',$action,$ref['source_id'],$revision,$key))throw new DomainException('company_operation_invalid_input');
                CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'projects/view/'.$id),'location',303);return;
            }
            if(!in_array($method,['GET','HEAD'],true))throw new DomainException('native_project_method_invalid');
            $ctx=CompanyRequestScope::context();if($ctx->issuerId()===null)throw new DomainException('native_project_access_denied');
            $acl=new IssuerResourceAccess($this->db,db_prefix());
            $visibility=['project_tickets'=>$acl->can($ctx->issuerId(),$ctx->staffId(),'tickets','view'),'project_contracts'=>$acl->can($ctx->issuerId(),$ctx->staffId(),'contracts','view'),'project_invoices'=>$acl->can($ctx->issuerId(),$ctx->staffId(),'invoices','view'),'project_estimates'=>$acl->can($ctx->issuerId(),$ctx->staffId(),'estimates','view'),'project_expenses'=>$acl->can($ctx->issuerId(),$ctx->staffId(),'expenses','view'),'project_credit_notes'=>$acl->can($ctx->issuerId(),$ctx->staffId(),'credit_notes','view'),'project_subscriptions'=>$acl->can($ctx->issuerId(),$ctx->staffId(),'subscriptions','view'),'project_proposals'=>$acl->can($ctx->issuerId(),$ctx->staffId(),'proposals','view'),'sales'=>false];
            $visibility['sales']=in_array(true,[$visibility['project_invoices'],$visibility['project_estimates'],$visibility['project_expenses'],$visibility['project_credit_notes'],$visibility['project_subscriptions'],$visibility['project_proposals']],true);
            $nativeTabs=NativeProjectTabs::definitions('_l',$visibility);$allowedGroups=array_keys($nativeTabs);foreach($nativeTabs as$tab)foreach($tab['children']??[] as$child)$allowedGroups[]=$child['slug'];
            $group=$selectedGroup??(string)($this->input->get('group')?:'project_overview');if($group==='sales')$group=$nativeTabs['sales']['children'][0]['slug']??'project_overview';if(!in_array($group,$allowedGroups,true))throw new DomainException('native_project_group_not_composed');
            $period=(string)($this->input->get('overview_chart')?:'this_week');
            require_once APPPATH.'services/NativeCompanyProjectDetail.php';$detail=new NativeCompanyProjectDetail($this->db,db_prefix());
            $overview=$detail->overview($ctx->issuerId(),$ctx->staffId(),$id,$period,'_l');$files=$detail->files($ctx->issuerId(),$ctx->staffId(),$id);
            $facts=$overview['facts'];$canEdit=$acl->can($ctx->issuerId(),$ctx->staffId(),'projects','edit');
            $staff=$canEdit?(new CompanyProjects($this->db,db_prefix()))->staffOptions($ctx->issuerId(),$ctx->staffId()):[];
            $project=(object)['id'=>$facts['reference']['canonical_id'],'name'=>$facts['project']['name']];$pin=(new NativeCompanyProjectPins($this->db,db_prefix()))->state($ctx->issuerId(),$ctx->staffId(),(int)$facts['reference']['source_id']);
            $portalControls=null;$portalRevision=null;$portalCsrf=null;if(($facts['reference']['source_kind']??null)==='company_project'){$portalRow=(new CompanyProjects($this->db,db_prefix()))->get($ctx->issuerId(),$ctx->staffId(),(int)$facts['reference']['source_id']);$portalControls=CompanyOperationalPortalAdmin::controls($this,$ctx->issuerId(),$ctx->staffId(),'projects',$portalRow);$portalRevision=(int)$portalRow['revision'];$portalCsrf=$this->nativeProjectPortalCsrf();}$data=['title'=>$facts['project']['name'],'project'=>$project,'native_project_overview'=>$overview,'native_project_files'=>$files,'native_project_staff'=>$staff,'portal_controls'=>$portalControls,'portal_revision'=>$portalRevision,'company_projects_csrf'=>$portalCsrf,'native_project_tabs'=>$nativeTabs,'native_project_pin'=>$pin,
                'native_project_view'=>['group'=>$group,'context_token'=>$ctx->token(),'csrf_token'=>CompanyRequestScope::nativeCsrfToken($this),'members_revision'=>$facts['members']['revision'],'can_edit'=>$canEdit,'can_create_task'=>$acl->can($ctx->issuerId(),$ctx->staffId(),'tasks','view')&&$acl->can($ctx->issuerId(),$ctx->staffId(),'tasks','create'),'canonical_id'=>$facts['reference']['canonical_id']]];
            $clock=time();$work=$this->nativeProjectWorkData($ctx->issuerId(),$ctx->staffId(),(int)$facts['reference']['source_id'],$group,$clock);$data+=$work;$data['actor']=$ctx->staffId();$data['company_project_rules_csrf']=$this->nativeProjectRulesCsrf();
            // The admin shell is prepared once by the request scope. Rebuilding it
            // here repeats the same issuer checks and can observe a different
            // snapshot after the project facts have already been sealed.
            $html=$this->load->view('admin/projects/view',$data,true);
            if(!$acl->can($ctx->issuerId(),$ctx->staffId(),'projects','view')||$detail->read($ctx->issuerId(),$ctx->staffId(),$id)!==$facts)throw new DomainException('native_project_snapshot_changed');
            if($this->nativeProjectWorkData($ctx->issuerId(),$ctx->staffId(),(int)$facts['reference']['source_id'],$group,$clock)!==$work)throw new DomainException('native_project_snapshot_changed');$this->output->set_output($html);
        }catch(DomainException$e){$reason=$e->getMessage();$status=str_contains($reason,'stale')||str_contains($reason,'conflict')?409:(str_contains($reason,'csrf')||str_contains($reason,'denied')||str_contains($reason,'access')?403:422);show_error('Não foi possível confirmar o projeto, seus arquivos ou suas permissões.', $status);}
        catch(RuntimeException$e){show_error('Não foi possível confirmar o armazenamento do projeto.',503);}
    }

    public function project($id = '')
    {
        if($this->nativeProjects()){$this->nativeProjectForm($id);return;}
        if (staff_cant('edit', 'projects') && staff_cant('create', 'projects')) {
            access_denied('Projects');
        }

        if ($this->input->post()) {
            $data                = $this->input->post();
            $data['description'] = html_purify($this->input->post('description', false));
            if ($id == '') {
                if (staff_cant('create', 'projects')) {
                    access_denied('Projects');
                }
                $id = $this->projects_model->add($data);
                if ($id) {
                    set_alert('success', _l('added_successfully', _l('project')));
                    redirect(admin_url('projects/view/' . $id));
                }
            } else {
                if (staff_cant('edit', 'projects')) {
                    access_denied('Projects');
                }
                $success = $this->projects_model->update($data, $id);
                if ($success) {
                    set_alert('success', _l('updated_successfully', _l('project')));
                }
                redirect(admin_url('projects/view/' . $id));
            }
        }
        if ($id == '') {
            $title                            = _l('add_new', _l('project'));
            $data['auto_select_billing_type'] = $this->projects_model->get_most_used_billing_type();

            if ($this->input->get('via_estimate_id')) {
                $this->load->model('estimates_model');
                $data['estimate'] = $this->estimates_model->get($this->input->get('via_estimate_id'));
            }
        } else {
            $data['project']                               = $this->projects_model->get($id);
            $data['project']->settings->available_features = unserialize($data['project']->settings->available_features);

            $data['project_members'] = $this->projects_model->get_project_members($id);
            $title                   = _l('edit', _l('project'));
        }

        if ($this->input->get('customer_id')) {
            $data['customer_id'] = $this->input->get('customer_id');
        }

        $data['last_project_settings'] = $this->projects_model->get_last_project_settings();

        if (count($data['last_project_settings'])) {
            $key                                          = array_search('available_features', array_column($data['last_project_settings'], 'name'));
            $data['last_project_settings'][$key]['value'] = unserialize($data['last_project_settings'][$key]['value']);
        }

        $data['settings'] = $this->projects_model->get_settings();
        $data['statuses'] = $this->projects_model->get_project_statuses();
        $data['staff']    = $this->staff_model->get('', ['active' => 1]);

        $data['title'] = $title;
        $this->load->view('admin/projects/project', $data);
    }

    public function gantt()
    {
        if($this->nativeProjects()){ $this->nativeProjectGroupRead($this->input->get('project_id'),'project_gantt');return;}
        $data['title'] = _l('project_gant');

        $selected_statuses = [];
        $selectedMember    = null;
        $data['statuses']  = $this->projects_model->get_project_statuses();

        $appliedStatuses = $this->input->get('status');
        $appliedMember   = $this->input->get('member');

        $allStatusesIds = [];

        foreach ($data['statuses'] as $status) {
            if (
                ! isset($status['filter_default'])
                || (isset($status['filter_default']) && $status['filter_default'])
                && ! $appliedStatuses
            ) {
                $selected_statuses[] = $status['id'];
            } elseif ($appliedStatuses) {
                if (in_array($status['id'], $appliedStatuses)) {
                    $selected_statuses[] = $status['id'];
                }
            } else {
                // All statuses
                $allStatusesIds[] = $status['id'];
            }
        }

        if (count($selected_statuses) == 0) {
            $selected_statuses = $allStatusesIds;
        }

        $data['selected_statuses'] = $selected_statuses;

        if (staff_can('view', 'projects')) {
            $selectedMember          = $appliedMember;
            $data['selectedMember']  = $selectedMember;
            $data['project_members'] = $this->projects_model->get_distinct_projects_members();
        }

        $data['gantt_data'] = (new AllProjectsGantt([
            'status' => $selected_statuses,
            'member' => $selectedMember,
        ]))->get();

        $this->load->view('admin/projects/gantt', $data);
    }

    public function view($id)
    {
        if($this->nativeProjects()) {$this->nativeProjectView($id);return;}
        if (staff_can('view', 'projects') || $this->projects_model->is_member($id)) {
            close_setup_menu();
            $project = $this->projects_model->get($id);

            if (! $project) {
                blank_page(_l('project_not_found'));
            }

            $project->settings->available_features = unserialize($project->settings->available_features);
            $data['statuses']                      = $this->projects_model->get_project_statuses();

            $group = ! $this->input->get('group') ? 'project_overview' : $this->input->get('group');

            // Unable to load the requested file: admin/projects/project_tasks#.php - FIX
            if (strpos($group, '#') !== false) {
                $group = str_replace('#', '', $group);
            }

            $data['tabs'] = get_project_tabs_admin();
            $data['tab']  = $this->app_tabs->filter_tab($data['tabs'], $group);

            if (! $data['tab']) {
                show_404();
            }

            $this->load->model('payment_modes_model');
            $data['payment_modes'] = $this->payment_modes_model->get('', [], true);

            $data['project']  = $project;
            $data['currency'] = $this->projects_model->get_currency($id);

            $data['project_total_logged_time'] = $this->projects_model->total_logged_time($id);

            $data['staff']   = $this->staff_model->get('', ['active' => 1]);
            $percent         = $this->projects_model->calc_progress($id);
            $data['members'] = $this->projects_model->get_project_members($id);

            foreach ($data['members'] as $key => $member) {
                $data['members'][$key]['total_logged_time'] = 0;
                $member_timesheets                          = $this->tasks_model->get_unique_member_logged_task_ids($member['staff_id'], ' AND task_id IN (SELECT id FROM ' . db_prefix() . 'tasks WHERE rel_type="project" AND rel_id="' . $this->db->escape_str($id) . '")');

                foreach ($member_timesheets as $member_task) {
                    $data['members'][$key]['total_logged_time'] += $this->tasks_model->calc_task_total_time($member_task->task_id, ' AND staff_id=' . $member['staff_id']);
                }
            }
            $data['bodyclass'] = '';

            $this->app_scripts->add(
                'projects-js',
                base_url($this->app_scripts->core_file('assets/js', 'projects.js')) . '?v=' . $this->app_scripts->core_version(),
                'admin',
                ['app-js', 'jquery-comments-js', 'frappe-gantt-js', 'circle-progress-js']
            );

            if ($group == 'project_overview') {
                $data['project_total_days']        = round((human_to_unix($data['project']->deadline . ' 00:00') - human_to_unix($data['project']->start_date . ' 00:00')) / 3600 / 24);
                $data['project_days_left']         = $data['project_total_days'];
                $data['project_time_left_percent'] = 100;
                if ($data['project']->deadline) {
                    if (human_to_unix($data['project']->start_date . ' 00:00') < time() && human_to_unix($data['project']->deadline . ' 00:00') > time()) {
                        $data['project_days_left']         = round((human_to_unix($data['project']->deadline . ' 00:00') - time()) / 3600 / 24);
                        $data['project_time_left_percent'] = $data['project_days_left'] / $data['project_total_days'] * 100;
                        $data['project_time_left_percent'] = round($data['project_time_left_percent'], 2);
                    }
                    if (human_to_unix($data['project']->deadline . ' 00:00') < time()) {
                        $data['project_days_left']         = 0;
                        $data['project_time_left_percent'] = 0;
                    }
                }

                $__total_where_tasks = 'rel_type = "project" AND rel_id=' . $this->db->escape_str($id);
                if (staff_cant('view', 'tasks')) {
                    $__total_where_tasks .= ' AND ' . db_prefix() . 'tasks.id IN (SELECT taskid FROM ' . db_prefix() . 'task_assigned WHERE staffid = ' . get_staff_user_id() . ')';

                    if (get_option('show_all_tasks_for_project_member') == 1) {
                        $__total_where_tasks .= ' AND (rel_type="project" AND rel_id IN (SELECT project_id FROM ' . db_prefix() . 'project_members WHERE staff_id=' . get_staff_user_id() . '))';
                    }
                }

                $__total_where_tasks = hooks()->apply_filters('admin_total_project_tasks_where', $__total_where_tasks, $id);

                $where = ($__total_where_tasks == '' ? '' : $__total_where_tasks . ' AND ') . 'status != ' . Tasks_model::STATUS_COMPLETE;

                $data['tasks_not_completed'] = total_rows(db_prefix() . 'tasks', $where);
                $total_tasks                 = total_rows(db_prefix() . 'tasks', $__total_where_tasks);
                $data['total_tasks']         = $total_tasks;

                $where = ($__total_where_tasks == '' ? '' : $__total_where_tasks . ' AND ') . 'status = ' . Tasks_model::STATUS_COMPLETE . ' AND rel_type="project" AND rel_id="' . $id . '"';

                $data['tasks_completed'] = total_rows(db_prefix() . 'tasks', $where);

                $data['tasks_not_completed_progress'] = ($total_tasks > 0 ? number_format(($data['tasks_completed'] * 100) / $total_tasks, 2) : 0);
                $data['tasks_not_completed_progress'] = round($data['tasks_not_completed_progress'], 2);

                @$percent_circle        = $percent / 100;
                $data['percent_circle'] = $percent_circle;

                $data['project_overview_chart'] = (new HoursOverviewChart(
                    $id,
                    ($this->input->get('overview_chart') ? $this->input->get('overview_chart') : 'this_week')
                ))->get();
            } elseif ($group == 'project_invoices') {
                $this->load->model('invoices_model');

                $data['invoiceid']   = '';
                $data['status']      = '';
                $data['custom_view'] = '';

                $data['invoices_years']       = $this->invoices_model->get_invoices_years();
                $data['invoices_sale_agents'] = $this->invoices_model->get_sale_agents();
                $data['invoices_statuses']    = $this->invoices_model->get_statuses();
                $data['invoices_table']       = App_table::find('project_invoices');
            } elseif ($group == 'project_gantt') {
                $gantt_type         = (! $this->input->get('gantt_type') ? 'milestones' : $this->input->get('gantt_type'));
                $taskStatus         = (! $this->input->get('gantt_task_status') ? null : $this->input->get('gantt_task_status'));
                $data['gantt_data'] = (new Gantt($id, $gantt_type))->forTaskStatus($taskStatus)->get();
            } elseif ($group == 'project_milestones') {
                $data['bodyclass'] .= 'project-milestones ';
                $data['milestones_exclude_completed_tasks'] = $this->input->get('exclude_completed') && $this->input->get('exclude_completed') == 'yes' || ! $this->input->get('exclude_completed');

                $data['total_milestones'] = total_rows(db_prefix() . 'milestones', ['project_id' => $id]);
                $data['milestones_found'] = $data['total_milestones'] > 0 || (! $data['total_milestones'] && total_rows(db_prefix() . 'tasks', ['rel_id' => $id, 'rel_type' => 'project', 'milestone' => 0]) > 0);
            } elseif ($group == 'project_files') {
                $data['files'] = $this->projects_model->get_files($id);
            } elseif ($group == 'project_expenses') {
                $this->load->model('taxes_model');
                $this->load->model('expenses_model');
                $data['taxes']              = $this->taxes_model->get();
                $data['expense_categories'] = $this->expenses_model->get_category();
                $data['currencies']         = $this->currencies_model->get();
                $data['expenses_table']     = App_table::find('project_expenses');
            } elseif ($group == 'project_activity') {
                $data['activity'] = $this->projects_model->get_activity($id);
            } elseif ($group == 'project_notes') {
                $data['staff_notes'] = $this->projects_model->get_staff_notes($id, get_staff_user_id());
            } elseif ($group == 'project_contracts') {
                $this->load->model('contracts_model');
                $data['contract_types']  = $this->contracts_model->get_contract_types();
                $data['years']           = $this->contracts_model->get_contracts_years();
                $data['contracts_table'] = App_table::find('project_contracts');
            } elseif ($group == 'project_estimates') {
                $this->load->model('estimates_model');
                $data['estimates_years']       = $this->estimates_model->get_estimates_years();
                $data['estimates_sale_agents'] = $this->estimates_model->get_sale_agents();
                $data['estimate_statuses']     = $this->estimates_model->get_statuses();
                $data['estimates_table']       = App_table::find('project_estimates');
                $data['estimateid']            = '';
                $data['switch_pipeline']       = '';
            } elseif ($group == 'project_proposals') {
                $this->load->model('proposals_model');
                $data['proposal_statuses']     = $this->proposals_model->get_statuses();
                $data['proposals_sale_agents'] = $this->proposals_model->get_sale_agents();
                $data['years']                 = $this->proposals_model->get_proposals_years();
                $data['proposals_table']       = App_table::find('project_proposals');
                $data['proposal_id']           = '';
                $data['switch_pipeline']       = '';
            } elseif ($group == 'project_tickets') {
                $data['chosen_ticket_status'] = '';
                $this->load->model('tickets_model');
                $data['ticket_assignees'] = $this->tickets_model->get_tickets_assignes_disctinct();

                $this->load->model('departments_model');
                $data['staff_deparments_ids']          = $this->departments_model->get_staff_departments(get_staff_user_id(), true);
                $data['default_tickets_list_statuses'] = hooks()->apply_filters('default_tickets_list_statuses', [1, 2, 4]);
            } elseif ($group == 'project_timesheets') {
                // Tasks are used in the timesheet dropdown
                // Completed tasks are excluded from this list because you can't add timesheet on completed task.
                $data['tasks']                = $this->projects_model->get_tasks($id, 'status != ' . Tasks_model::STATUS_COMPLETE . ' AND billed=0');
                $data['timesheets_staff_ids'] = $this->projects_model->get_distinct_tasks_timesheets_staff($id);
            }

            // Discussions
            if ($this->input->get('discussion_id')) {
                $data['discussion_user_profile_image_url'] = staff_profile_image_url(get_staff_user_id());
                $data['discussion']                        = $this->projects_model->get_discussion($this->input->get('discussion_id'), $id);
                $data['current_user_is_admin']             = is_admin();
            }

            $data['percent'] = $percent;

            $this->app_scripts->add('circle-progress-js', 'assets/plugins/jquery-circle-progress/circle-progress.min.js');

            $other_projects       = [];
            $other_projects_where = 'id != ' . $id;

            $statuses = $this->projects_model->get_project_statuses();

            $other_projects_where .= ' AND (';

            foreach ($statuses as $status) {
                if (isset($status['filter_default']) && $status['filter_default']) {
                    $other_projects_where .= 'status = ' . $status['id'] . ' OR ';
                }
            }

            $other_projects_where = rtrim($other_projects_where, ' OR ');

            $other_projects_where .= ')';

            if (staff_cant('view', 'projects')) {
                $other_projects_where .= ' AND ' . db_prefix() . 'projects.id IN (SELECT project_id FROM ' . db_prefix() . 'project_members WHERE staff_id=' . get_staff_user_id() . ')';
            }

            $data['other_projects'] = $this->projects_model->get('', $other_projects_where);
            $data['title']          = $data['project']->name;
            $data['bodyclass'] .= 'project estimates-total-manual';
            $data['project_status'] = get_project_status_by_id($project->status);

            $this->load->view('admin/projects/view', $data);
        } else {
            access_denied('Project View');
        }
    }

    public function mark_as()
    {
        if($this->nativeCompanyProjects()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'projects','mark_as',[]);return;}
        $success = false;
        $message = '';
        if ($this->input->is_ajax_request()) {
            if (staff_can('create', 'projects') || staff_can('edit', 'projects')) {
                $status = get_project_status_by_id($this->input->post('status_id'));

                $message = _l('project_marked_as_failed', $status['name']);
                $success = $this->projects_model->mark_as($this->input->post());

                if ($success) {
                    $message = _l('project_marked_as_success', $status['name']);
                }
            }
        }
        echo json_encode([
            'success' => $success,
            'message' => $message,
        ]);
    }

    public function file($id, $project_id)
    {
        if($this->nativeCompanyProjects()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'projects','file',[$id,$project_id]);return;}
        $data['discussion_user_profile_image_url'] = staff_profile_image_url(get_staff_user_id());
        $data['current_user_is_admin']             = is_admin();

        $data['file'] = $this->projects_model->get_file($id, $project_id);

        if (! $data['file']) {
            header('HTTP/1.0 404 Not Found');

            exit;
        }

        $this->load->view('admin/projects/_file', $data);
    }

    public function update_file_data()
    {
        if($this->nativeCompanyProjects()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'projects','update_file_data',[]);return;}
        if ($this->input->post()) {
            $this->projects_model->update_file_data($this->input->post());
        }
    }

    public function add_external_file()
    {
        if($this->nativeCompanyProjects()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'projects','add_external_file',[]);return;}
        if ($this->input->post()) {
            $data                        = [];
            $data['project_id']          = $this->input->post('project_id');
            $data['files']               = $this->input->post('files');
            $data['external']            = $this->input->post('external');
            $data['visible_to_customer'] = ($this->input->post('visible_to_customer') == 'true' ? 1 : 0);
            $data['staffid']             = get_staff_user_id();
            $this->projects_model->add_external_file($data);
        }
    }

    public function download_all_files($id)
    {
        if($this->nativeCompanyProjects()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'projects','download_all_files',[$id]);return;}
        if ($this->projects_model->is_member($id) || staff_can('view', 'projects')) {
            $files = $this->projects_model->get_files($id);
            if (count($files) == 0) {
                set_alert('warning', _l('no_files_found'));
                redirect(admin_url('projects/view/' . $id . '?group=project_files'));
            }
            $path = get_upload_path_by_type('project') . $id;
            $this->load->library('zip');

            foreach ($files as $file) {
                if ($file['original_file_name'] != '') {
                    $this->zip->read_file($path . '/' . $file['file_name'], $file['original_file_name']);
                } else {
                    $this->zip->read_file($path . '/' . $file['file_name']);
                }
            }
            $this->zip->download(slug_it(get_project_name_by_id($id)) . '-files.zip');
            $this->zip->clear_data();
        }
    }

    public function export_project_data($id)
    {
        if($this->nativeCompanyProjects()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'projects','export_project_data',[$id]);return;}
        if (staff_can('create', 'projects')) {
            app_pdf('project-data', LIBSPATH . 'pdf/Project_data_pdf', $id);
        }
    }

    public function update_task_milestone()
    {
        if($this->nativeProjects()){ $this->nativeProjectRuleAction('update_task_milestone');return;}
        if ($this->input->post()) {
            $this->projects_model->update_task_milestone($this->input->post());
        }
    }

    public function update_milestones_order()
    {
        if($this->nativeCompanyProjects()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'projects','update_milestones_order',[]);return;}
        if ($post_data = $this->input->post()) {
            $this->projects_model->update_milestones_order($post_data);
        }
    }

    public function pin_action($project_id)
    {
        if($this->nativeCompanyProjects()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'projects','pin_action',[$project_id]);return;}
        $this->projects_model->pin_action($project_id);
        redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
    }

    private function nativeProjectMembers($canonicalProject,$removeStaff=null):void
    {
        $this->output->set_header('Cache-Control: no-store');
        try{
            CompanyRequestScope::assertNativeMutation($this);$ctx=CompanyRequestScope::context();if($ctx->issuerId()===null)throw new DomainException('native_assignment_denied');
            require_once APPPATH.'services/NativeCompanyResourceReferences.php';require_once APPPATH.'services/NativeCompanyWorkAssignments.php';
            $ref=\NativeCompanyResourceReferences::resolve($this->db,db_prefix(),$ctx->issuerId(),$ctx->staffId(),'project',$canonicalProject,'edit');if($ref['source_kind']!=='company_project')throw new DomainException('native_project_legacy_writer_required');
            $service=new \NativeCompanyWorkAssignments($this->db,db_prefix());$revision=$this->input->post('native_members_revision');if(!is_string($revision))throw new DomainException('native_assignment_invalid');
            if($removeStaff!==null)$service->remove($ctx->issuerId(),$ctx->staffId(),'project',$ref['source_id'],$revision,$removeStaff);
            else{$members=$this->input->post('project_members')??[];if(!is_array($members))throw new DomainException('native_assignment_invalid');$service->replace($ctx->issuerId(),$ctx->staffId(),'project',$ref['source_id'],$revision,$members);}
            set_alert('success',_l('updated_successfully',_l('project_members')));redirect(admin_url('projects/view/'.$ref['canonical_id']),'location',303);
        }catch(DomainException$e){show_error('Não foi possível confirmar os membros, a revisão ou as permissões deste projeto.',422);}catch(RuntimeException$e){show_error('Não foi possível confirmar a alteração dos membros do projeto.',503);}
    }

    public function add_edit_members($project_id)
    {
        if($this->nativeProjects()){$this->nativeProjectMembers($project_id);return;}
        if (staff_can('edit', 'projects')) {
            $this->projects_model->add_edit_members($this->input->post(), $project_id);
            redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
        }
    }

    public function discussions($project_id)
    {
        if($this->nativeProjects()){ $this->nativeProjectGroupRead($project_id,'project_discussions');return;}
        if ($this->projects_model->is_member($project_id) || staff_can('view', 'projects')) {
            if ($this->input->is_ajax_request()) {
                $this->app->get_table_data('project_discussions', [
                    'project_id' => $project_id,
                ]);
            }
        }
    }

    public function discussion($id = '')
    {
        if($this->nativeProjects()){ if(strtoupper($this->input->method(true))==='POST')$this->nativeProjectRuleAction('discussion',null,$id);else$this->nativeProjectGroupRead($this->input->get('project_id'),'project_discussions');return;}
        if ($this->input->post()) {
            $message = '';
            $success = false;
            if (! $this->input->post('id')) {
                $id = $this->projects_model->add_discussion($this->input->post());
                if ($id) {
                    $success = true;
                    $message = _l('added_successfully', _l('project_discussion'));
                }
                echo json_encode([
                    'success' => $success,
                    'message' => $message,
                ]);
            } else {
                $data = $this->input->post();
                $id   = $data['id'];
                unset($data['id']);
                $success = $this->projects_model->edit_discussion($data, $id);
                if ($success) {
                    $message = _l('updated_successfully', _l('project_discussion'));
                }
                echo json_encode([
                    'success' => $success,
                    'message' => $message,
                ]);
            }

            exit;
        }
    }

    public function get_discussion_comments($id, $type)
    {
        if($this->nativeProjects()){ $this->nativeProjectDiscussionComments($id,$type);return;}
        echo json_encode($this->projects_model->get_discussion_comments($id, $type));
    }

    public function add_discussion_comment($discussion_id, $type)
    {
        if($this->nativeProjects()){ if($type!=='regular'){show_error('Tipo de conversa inválido.',422);return;}$this->nativeProjectRuleAction('add_discussion_comment',null,$discussion_id);return;}
        echo json_encode($this->projects_model->add_discussion_comment(
            $this->input->post(null, false),
            $discussion_id,
            $type
        ));
    }

    public function update_discussion_comment()
    {
        if($this->nativeProjects()){ $this->nativeProjectRuleAction('update_discussion_comment');return;}
        echo json_encode($this->projects_model->update_discussion_comment($this->input->post(null, false)));
    }

    public function delete_discussion_comment($id)
    {
        if($this->nativeProjects()){ $this->nativeProjectRuleAction('delete_discussion_comment',null,$id);return;}
        echo json_encode($this->projects_model->delete_discussion_comment($id));
    }

    public function delete_discussion($id)
    {
        if($this->nativeProjects()){ $this->nativeProjectRuleAction('delete_discussion',null,$id);return;}
        $success = false;
        if (staff_can('delete', 'projects')) {
            $success = $this->projects_model->delete_discussion($id);
        }
        $alert_type = 'warning';
        $message    = _l('project_discussion_failed_to_delete');
        if ($success) {
            $alert_type = 'success';
            $message    = _l('project_discussion_deleted');
        }
        echo json_encode([
            'alert_type' => $alert_type,
            'message'    => $message,
        ]);
    }

    public function change_milestone_color()
    {
        if($this->nativeCompanyProjects()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'projects','change_milestone_color',[]);return;}
        if ($this->input->post()) {
            $this->projects_model->update_milestone_color($this->input->post());
        }
    }

    public function upload_file($project_id)
    {
        if($this->nativeProjects()){
            try{
                CompanyRequestScope::assertNativeMutation($this);$ctx=CompanyRequestScope::context();require_once APPPATH.'services/NativeCompanyResourceReferences.php';$ref=NativeCompanyResourceReferences::resolve($this->db,db_prefix(),$ctx->issuerId(),$ctx->staffId(),'project',$project_id,'edit');if($ref['source_kind']!=='company_project')throw new DomainException('company_project_access_denied');
                $input=$this->input->post(null,false);if(!is_array($input))throw new DomainException('company_project_file_invalid');unset($input['_company_context'],$input['_native_company_csrf']);if(isset($this->security))unset($input[$this->security->get_csrf_token_name()]);
                if(array_diff(array_keys($input),['native_project_revision','native_request_key'])||!isset($input['native_project_revision'])||!is_string($input['native_request_key']??null)||!preg_match('/^[a-f0-9]{64}$/D',$input['native_request_key']))throw new DomainException('company_project_file_invalid');
                $file=$_FILES['file']??null;if(!is_array($file)||is_array($file['name']??null)||!isset($file['name'],$file['tmp_name'],$file['error'])||(int)$file['error']!==UPLOAD_ERR_OK||!is_string($file['name'])||!is_string($file['tmp_name'])||(!is_uploaded_file($file['tmp_name'])&&PHP_SAPI!=='cli'))throw new DomainException('company_project_file_invalid');
                $bytes=file_get_contents($file['tmp_name']);if(!is_string($bytes))throw new DomainException('company_project_file_invalid');
                require_once APPPATH.'services/CompanyProjectExtras.php';(new CompanyProjectExtras($this->db,db_prefix()))->uploadNative($ctx->issuerId(),$ctx->staffId(),'project',$ref['source_id'],$input['native_project_revision'],$input['native_request_key'],$file['name'],$bytes);
                redirect(admin_url('projects/view/'.$ref['canonical_id'].'?group=project_files'),'location',303);return;
            }catch(DomainException$e){show_error('Não foi possível confirmar o arquivo, a revisão ou as permissões deste projeto.',str_contains($e->getMessage(),'stale')?409:422);return;}
            catch(RuntimeException$e){show_error('Não foi possível armazenar o arquivo do projeto.',503);return;}
        }
        handle_project_file_uploads($project_id);
    }

    public function change_file_visibility($id, $visible)
    {
        if($this->nativeCompanyProjects()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'projects','change_file_visibility',[$id,$visible]);return;}
        if ($this->input->is_ajax_request()) {
            $this->projects_model->change_file_visibility($id, $visible);
        }
    }

    public function change_activity_visibility($id, $visible)
    {
        if($this->nativeProjects()){ if($visible!=='0'&&$visible!=='1'){show_error('Visibilidade inválida.',422);return;}$this->nativeProjectRuleAction('change_activity_visibility',null,$id);return;}
        if (staff_can('create', 'projects')) {
            if ($this->input->is_ajax_request()) {
                $this->projects_model->change_activity_visibility($id, $visible);
            }
        }
    }

    public function remove_file($project_id, $id)
    {
        if($this->nativeProjects()){
            try{
                CompanyRequestScope::assertNativeMutation($this);$ctx=CompanyRequestScope::context();require_once APPPATH.'services/NativeCompanyResourceReferences.php';$ref=NativeCompanyResourceReferences::resolve($this->db,db_prefix(),$ctx->issuerId(),$ctx->staffId(),'project',$project_id,'edit');if($ref['source_kind']!=='company_project')throw new DomainException('company_project_access_denied');
                $input=$this->input->post(null,false);if(!is_array($input))throw new DomainException('company_project_file_invalid');unset($input['_company_context'],$input['_native_company_csrf']);if(isset($this->security))unset($input[$this->security->get_csrf_token_name()]);
                if(array_diff(array_keys($input),['native_project_revision','native_request_key'])||!isset($input['native_project_revision'])||!is_string($input['native_request_key']??null)||!preg_match('/^[a-f0-9]{64}$/D',$input['native_request_key']))throw new DomainException('company_project_file_invalid');
                require_once APPPATH.'services/CompanyProjectExtras.php';(new CompanyProjectExtras($this->db,db_prefix()))->hide($ctx->issuerId(),$ctx->staffId(),'project',$ref['source_id'],$input['native_project_revision'],(string)$id,$input['native_request_key']);
                redirect(admin_url('projects/view/'.$ref['canonical_id'].'?group=project_files'),'location',303);return;
            }catch(DomainException$e){show_error('Não foi possível confirmar o arquivo, a revisão ou as permissões deste projeto.',str_contains($e->getMessage(),'stale')?409:422);return;}
            catch(RuntimeException$e){show_error('Não foi possível atualizar o arquivo do projeto.',503);return;}
        }
        $this->projects_model->remove_file($id);
        redirect(admin_url('projects/view/' . $project_id . '?group=project_files'));
    }

    public function milestones_kanban()
    {
        if($this->nativeProjects()){ $this->nativeProjectGroupRead($this->input->get('project_id'),'project_milestones');return;}
        $data['milestones_exclude_completed_tasks'] = $this->input->get('exclude_completed_tasks') && $this->input->get('exclude_completed_tasks') == 'yes';

        $data['project_id'] = $this->input->get('project_id');
        $data['milestones'] = [];

        $data['milestones'][] = [
            'name'              => _l('milestones_uncategorized'),
            'id'                => 0,
            'total_logged_time' => $this->projects_model->calc_milestone_logged_time($data['project_id'], 0),
            'color'             => null,
        ];

        $_milestones = $this->projects_model->get_milestones($data['project_id']);

        foreach ($_milestones as $m) {
            $data['milestones'][] = $m;
        }

        echo $this->load->view('admin/projects/milestones_kan_ban', $data, true);
    }

    public function milestones_kanban_load_more()
    {
        if($this->nativeProjects()){ $this->nativeProjectGroupRead($this->input->get('project_id'),'project_milestones');return;}
        $milestones_exclude_completed_tasks = $this->input->get('exclude_completed_tasks') && $this->input->get('exclude_completed_tasks') == 'yes';

        $status     = $this->input->get('status');
        $page       = $this->input->get('page');
        $project_id = $this->input->get('project_id');
        $where      = [];
        if ($milestones_exclude_completed_tasks) {
            $where['status !='] = Tasks_model::STATUS_COMPLETE;
        }
        $tasks = $this->projects_model->do_milestones_kanban_query($status, $project_id, $page, $where);

        foreach ($tasks as $task) {
            $this->load->view('admin/projects/_milestone_kanban_card', ['task' => $task, 'milestone' => $status]);
        }
    }

    public function milestones($project_id)
    {
        if($this->nativeProjects()){ $this->nativeProjectGroupRead($project_id,'project_milestones');return;}
        if ($this->projects_model->is_member($project_id) || staff_can('view', 'projects')) {
            if ($this->input->is_ajax_request()) {
                $this->app->get_table_data('milestones', [
                    'project_id' => $project_id,
                ]);
            }
        }
    }

    public function milestone($id = '')
    {
        if($this->nativeProjects()){ $this->nativeProjectMilestoneAction($id);return;}
        if ($this->input->post()) {
            $message = '';
            $success = false;
            if (! $this->input->post('id')) {
                if (staff_cant('create_milestones', 'projects')) {
                    access_denied();
                }

                $id = $this->projects_model->add_milestone($this->input->post());
                if ($id) {
                    set_alert('success', _l('added_successfully', _l('project_milestone')));
                }
            } else {
                if (staff_cant('edit_milestones', 'projects')) {
                    access_denied();
                }

                $data = $this->input->post();
                $id   = $data['id'];
                unset($data['id']);
                $success = $this->projects_model->update_milestone($data, $id);
                if ($success) {
                    set_alert('success', _l('updated_successfully', _l('project_milestone')));
                }
            }
        }

        redirect(admin_url('projects/view/' . $this->input->post('project_id') . '?group=project_milestones'));
    }

    public function delete_milestone($project_id, $id)
    {
        if($this->nativeCompanyProjects()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'projects','delete_milestone',[$project_id,$id]);return;}
        if (staff_can('delete_milestones', 'projects')) {
            if ($this->projects_model->delete_milestone($id)) {
                set_alert('deleted', 'project_milestone');
            }
        }
        redirect(admin_url('projects/view/' . $project_id . '?group=project_milestones'));
    }

    public function bulk_action_files()
    {
        if($this->nativeCompanyProjects()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'projects','bulk_action_files',[]);return;}
        hooks()->do_action('before_do_bulk_action_for_project_files');
        $total_deleted       = 0;
        $hasPermissionDelete = staff_can('delete', 'projects');
        // bulk action for projects currently only have delete button
        if ($this->input->post()) {
            $fVisibility = $this->input->post('visible_to_customer') == 'true' ? 1 : 0;
            $ids         = $this->input->post('ids');
            if (is_array($ids)) {
                foreach ($ids as $id) {
                    if ($hasPermissionDelete && $this->input->post('mass_delete') && $this->projects_model->remove_file($id)) {
                        $total_deleted++;
                    } else {
                        $this->projects_model->change_file_visibility($id, $fVisibility);
                    }
                }
            }
        }
        if ($this->input->post('mass_delete')) {
            set_alert('success', _l('total_files_deleted', $total_deleted));
        }
    }

    public function timesheets($project_id)
    {
        if($this->nativeCompanyProjects()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'projects','timesheets',[$project_id]);return;}
        if ($this->projects_model->is_member($project_id) || staff_can('view', 'projects')) {
            if ($this->input->is_ajax_request()) {
                $this->app->get_table_data('timesheets', [
                    'project_id' => $project_id,
                ]);
            }
        }
    }

    public function timesheet()
    {
        if($this->nativeCompanyProjects()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'projects','timesheet',[]);return;}
        if ($this->input->post()) {
            if (
                $this->input->post('timer_id')
                && ! (staff_can('edit_timesheet', 'tasks') || (staff_can('edit_own_timesheet', 'tasks') && total_rows(db_prefix() . 'taskstimers', ['staff_id' => get_staff_user_id(), 'id' => $this->input->post('timer_id')]) > 0))
            ) {
                echo json_encode([
                    'success' => false,
                    'message' => _l('access_denied'),
                ]);

                exit;
            }
            $message = '';
            $success = false;
            $success = $this->tasks_model->timesheet($this->input->post());
            if ($success === true) {
                $langKey = $this->input->post('timer_id') ? 'updated_successfully' : 'added_successfully';
                $message = _l($langKey, _l('project_timesheet'));
            } elseif (is_array($success) && isset($success['end_time_smaller'])) {
                $message = _l('failed_to_add_project_timesheet_end_time_smaller');
            } else {
                $message = _l('project_timesheet_not_updated');
            }
            echo json_encode([
                'success' => $success,
                'message' => $message,
            ]);

            exit;
        }
    }

    public function timesheet_task_assignees($task_id, $project_id, $staff_id = 'undefined')
    {
        $assignees             = $this->tasks_model->get_task_assignees($task_id);
        $data                  = '';
        $has_permission_edit   = staff_can('edit', 'projects');
        $has_permission_create = staff_can('edit', 'projects');
        // The second condition if staff member edit their own timesheet
        if ($staff_id == 'undefined' || $staff_id != 'undefined' && (! $has_permission_edit || ! $has_permission_create)) {
            $staff_id     = get_staff_user_id();
            $current_user = true;
        }

        foreach ($assignees as $staff) {
            $selected = '';
            // maybe is admin and not project member
            if ($staff['assigneeid'] == $staff_id && $this->projects_model->is_member($project_id, $staff_id)) {
                $selected = ' selected';
            }
            if ((! $has_permission_edit || ! $has_permission_create) && isset($current_user)) {
                if ($staff['assigneeid'] != $staff_id) {
                    continue;
                }
            }
            $data .= '<option value="' . $staff['assigneeid'] . '"' . $selected . '>' . e(get_staff_full_name($staff['assigneeid'])) . '</option>';
        }
        echo $data;
    }

    public function remove_team_member($project_id, $staff_id)
    {
        if($this->nativeProjects()){$this->nativeProjectMembers($project_id,$staff_id);return;}
        if (staff_can('edit', 'projects')) {
            if ($this->projects_model->remove_team_member($project_id, $staff_id)) {
                set_alert('success', _l('project_member_removed'));
            }
        }

        redirect(admin_url('projects/view/' . $project_id));
    }

    public function save_note($project_id)
    {
        if($this->nativeCompanyProjects()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'projects','save_note',[$project_id]);return;}
        if ($this->input->post()) {
            $success = $this->projects_model->save_note($this->input->post(null, false), $project_id);

            if ($this->input->is_ajax_request()) {
                if ($success) {
                    echo json_encode([
                        'success' => true,
                        'message' => _l('added_successfully', _l('project_note')),
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => _l('something_went_wrong'),
                    ]);
                }
            } else {
                if ($success) {
                    set_alert('success', _l('added_successfully', _l('project_note')));
                } else {
                    set_alert('warning', _l('something_went_wrong'));
                }
                redirect(admin_url('projects/view/' . $project_id . '?group=project_notes'));
            }
        }
    }

    public function edit_note($note_id)
    {
        if($this->nativeCompanyProjects()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'projects','edit_note',[$note_id]);return;}
        if ($this->input->post()) {
            $success = $this->projects_model->update_note($this->input->post(null, false), $note_id);
            if ($success) {
                echo json_encode([
                    'success' => true,
                    'message' => _l('updated_successfully', _l('project_note')),
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => _l('something_went_wrong'),
                ]);
            }
        }
    }

    public function delete_note($note_id)
    {
        if($this->nativeCompanyProjects()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'projects','delete_note',[$note_id]);return;}
        $note = $this->projects_model->get_staff_note($note_id, get_staff_user_id());
        if (! $note) {
            show_404();
        }

        $project_id = $note->project_id;
        $success    = $this->projects_model->delete_note($note_id);

        if ($this->input->is_ajax_request()) {
            echo json_encode(['success' => $success]);
        } else {
            if ($success) {
                set_alert('success', _l('deleted', _l('project_note')));
            } else {
                set_alert('warning', _l('something_went_wrong'));
            }
            redirect(admin_url('projects/view/' . $project_id . '?group=project_notes'));
        }
    }

    public function delete($project_id)
    {
        if($this->nativeProjects()){
            try{
                CompanyRequestScope::assertNativeMutation($this);$ctx=CompanyRequestScope::context();
                $revision=$this->input->post('revision',false);$key=$this->input->post('request_key',false);
                if(!is_string($revision)||!is_string($key))throw new DomainException('company_project_invalid_input');
                require_once APPPATH.'services/CompanyProjects.php';
                (new CompanyProjects($this->db,db_prefix()))->delete($ctx->issuerId(),$ctx->staffId(),$project_id,$revision,$key);
                CompanyRequestScope::invalidateCaches($this);set_alert('success',_l('deleted',_l('project')));
                redirect(CompanyRequestScope::adminUrl($this,'projects'),'location',303);return;
            }catch(DomainException $error){show_error('Não foi possível cancelar o projeto ou confirmar sua revisão e permissões.',str_contains($error->getMessage(),'stale')?409:422);return;}
            catch(RuntimeException $error){show_error('Não foi possível confirmar o armazenamento do projeto.',503);return;}
        }
        if (staff_can('delete', 'projects')) {
            $project = $this->projects_model->get($project_id);
            $success = $this->projects_model->delete($project_id);
            if ($success) {
                set_alert('success', _l('deleted', _l('project')));
                redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
            } else {
                set_alert('warning', _l('problem_deleting', _l('project_lowercase')));
                redirect(admin_url('projects/view/' . $project_id));
            }
        }
    }

    public function copy($project_id)
    {
        if($this->nativeProjects()){
            require_once APPPATH.'services/NativeCompanyProjectCopyHttp.php';
            NativeCompanyProjectCopyHttp::copy($this,$project_id);return;
        }
        if (staff_can('create', 'projects')) {
            $id = $this->projects_model->copy($project_id, $this->input->post());
            if ($id) {
                set_alert('success', _l('project_copied_successfully'));
                redirect(admin_url('projects/view/' . $id));
            } else {
                set_alert('danger', _l('failed_to_copy_project'));
                redirect(admin_url('projects/view/' . $project_id));
            }
        }
    }

    public function mass_stop_timers($project_id, $billable = 'false')
    {
        if($this->nativeCompanyProjects()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'projects','mass_stop_timers',[$project_id]);return;}
        if (staff_can('create', 'invoices')) {
            $where = [
                'billed'       => 0,
                'startdate <=' => date('Y-m-d'),
            ];
            if ($billable == 'true') {
                $where['billable'] = true;
            }
            $tasks                = $this->projects_model->get_tasks($project_id, $where);
            $total_timers_stopped = 0;

            foreach ($tasks as $task) {
                $this->db->where('task_id', $task['id']);
                $this->db->where('end_time IS NULL');
                $this->db->update(db_prefix() . 'taskstimers', [
                    'end_time' => time(),
                ]);
                $total_timers_stopped += $this->db->affected_rows();
            }
            $message = _l('project_tasks_total_timers_stopped', $total_timers_stopped);
            $type    = 'success';
            if ($total_timers_stopped == 0) {
                $type = 'warning';
            }
            echo json_encode([
                'type'    => $type,
                'message' => $message,
            ]);
        }
    }

    public function get_pre_invoice_project_info($project_id)
    {
        if (staff_can('create', 'invoices')) {
            $data['billable_tasks'] = $this->projects_model->get_tasks($project_id, [
                'billable'     => 1,
                'billed'       => 0,
                'startdate <=' => date('Y-m-d'),
            ]);

            $data['not_billable_tasks'] = $this->projects_model->get_tasks($project_id, [
                'billable'    => 1,
                'billed'      => 0,
                'startdate >' => date('Y-m-d'),
            ]);

            $data['project_id']   = $project_id;
            $data['billing_type'] = get_project_billing_type($project_id);

            $this->load->model('expenses_model');
            $this->db->where('invoiceid IS NULL');
            $data['expenses'] = $this->expenses_model->get('', [
                'project_id' => $project_id,
                'billable'   => 1,
            ]);

            $this->load->view('admin/projects/project_pre_invoice_settings', $data);
        }
    }

    public function get_invoice_project_data()
    {
        if (staff_can('create', 'invoices')) {
            $type       = $this->input->post('type');
            $project_id = $this->input->post('project_id');
            // Check for all cases
            if ($type == '') {
                $type == 'single_line';
            }
            $this->load->model('payment_modes_model');
            $data['payment_modes'] = $this->payment_modes_model->get('', [
                'expenses_only !=' => 1,
            ]);
            $this->load->model('taxes_model');
            $data['taxes']         = $this->taxes_model->get();
            $data['currencies']    = $this->currencies_model->get();
            $data['base_currency'] = $this->currencies_model->get_base_currency();
            $this->load->model('invoice_items_model');

            $data['ajaxItems'] = false;
            if (total_rows(db_prefix() . 'items') <= ajax_on_total_items()) {
                $data['items'] = $this->invoice_items_model->get_grouped();
            } else {
                $data['items']     = [];
                $data['ajaxItems'] = true;
            }

            $data['items_groups'] = $this->invoice_items_model->get_groups();
            $data['staff']        = $this->staff_model->get('', ['active' => 1]);
            $project              = $this->projects_model->get($project_id);
            $data['project']      = $project;
            $items                = [];

            $project    = $this->projects_model->get($project_id);
            $item['id'] = 0;

            $default_tax     = unserialize(get_option('default_tax'));
            $item['taxname'] = $default_tax;

            $tasks = $this->input->post('tasks');
            if ($tasks) {
                $item['long_description'] = '';
                $item['qty']              = 0;
                $item['task_id']          = [];
                if ($type == 'single_line') {
                    $item['description'] = $project->name;

                    foreach ($tasks as $task_id) {
                        $task = $this->tasks_model->get($task_id);
                        $sec  = $this->tasks_model->calc_task_total_time($task_id);
                        $item['long_description'] .= $task->name . ' - ' . seconds_to_time_format(task_timer_round($sec)) . ' ' . _l('hours') . "\r\n";
                        $item['task_id'][] = $task_id;
                        if ($project->billing_type == 2) {
                            if ($sec < 60) {
                                $sec = 0;
                            }
                            $item['qty'] += sec2qty(task_timer_round($sec));
                        }
                    }
                    if ($project->billing_type == 1) {
                        $item['qty']  = 1;
                        $item['rate'] = $project->project_cost;
                    } elseif ($project->billing_type == 2) {
                        $item['rate'] = $project->project_rate_per_hour;
                    }
                    $item['unit'] = '';
                    $items[]      = $item;
                } elseif ($type == 'task_per_item') {
                    foreach ($tasks as $task_id) {
                        $task                     = $this->tasks_model->get($task_id);
                        $sec                      = $this->tasks_model->calc_task_total_time($task_id);
                        $item['description']      = $project->name . ' - ' . $task->name;
                        $item['qty']              = floatval(sec2qty(task_timer_round($sec)));
                        $item['long_description'] = seconds_to_time_format(task_timer_round($sec)) . ' ' . _l('hours');
                        if ($project->billing_type == 2) {
                            $item['rate'] = $project->project_rate_per_hour;
                        } elseif ($project->billing_type == 3) {
                            $item['rate'] = $task->hourly_rate;
                        }
                        $item['task_id'] = $task_id;
                        $item['unit']    = '';
                        $items[]         = $item;
                    }
                } elseif ($type == 'timesheets_individualy') {
                    $timesheets     = $this->projects_model->get_timesheets($project_id, $tasks);
                    $added_task_ids = [];

                    foreach ($timesheets as $timesheet) {
                        if ($timesheet['task_data']->billed == 0 && $timesheet['task_data']->billable == 1) {
                            $item['description'] = $project->name . ' - ' . $timesheet['task_data']->name;
                            if (! in_array($timesheet['task_id'], $added_task_ids)) {
                                $item['task_id'] = $timesheet['task_id'];
                            }

                            array_push($added_task_ids, $timesheet['task_id']);

                            $item['qty']              = floatval(sec2qty(task_timer_round($timesheet['total_spent'])));
                            $item['long_description'] = _l('project_invoice_timesheet_start_time', _dt($timesheet['start_time'], true)) . "\r\n" . _l('project_invoice_timesheet_end_time', _dt($timesheet['end_time'], true)) . "\r\n" . _l('project_invoice_timesheet_total_logged_time', seconds_to_time_format(task_timer_round($timesheet['total_spent']))) . ' ' . _l('hours');

                            if ($this->input->post('timesheets_include_notes') && $timesheet['note']) {
                                $item['long_description'] .= "\r\n\r\n" . _l('note') . ': ' . $timesheet['note'];
                            }

                            if ($project->billing_type == 2) {
                                $item['rate'] = $project->project_rate_per_hour;
                            } elseif ($project->billing_type == 3) {
                                $item['rate'] = $timesheet['task_data']->hourly_rate;
                            }
                            $item['unit'] = '';
                            $items[]      = $item;
                        }
                    }
                }
            }
            if ($project->billing_type != 1) {
                $data['hours_quantity'] = true;
            }
            if ($this->input->post('expenses')) {
                if (isset($data['hours_quantity'])) {
                    unset($data['hours_quantity']);
                }
                if (is_iterable($tasks) && count($tasks) > 0) {
                    $data['qty_hrs_quantity'] = true;
                }
                $expenses       = $this->input->post('expenses');
                $addExpenseNote = $this->input->post('expenses_add_note');
                $addExpenseName = $this->input->post('expenses_add_name');

                if (! $addExpenseNote) {
                    $addExpenseNote = [];
                }

                if (! $addExpenseName) {
                    $addExpenseName = [];
                }

                $this->load->model('expenses_model');

                foreach ($expenses as $expense_id) {
                    // reset item array
                    $item                     = [];
                    $item['id']               = 0;
                    $expense                  = $this->expenses_model->get($expense_id);
                    $item['expense_id']       = $expense->expenseid;
                    $item['description']      = _l('item_as_expense') . ' ' . $expense->name;
                    $item['long_description'] = $expense->description;

                    if (in_array($expense_id, $addExpenseNote) && ! empty($expense->note)) {
                        $item['long_description'] .= PHP_EOL . $expense->note;
                    }

                    if (in_array($expense_id, $addExpenseName) && ! empty($expense->expense_name)) {
                        $item['long_description'] .= PHP_EOL . $expense->expense_name;
                    }

                    $item['qty'] = 1;

                    $item['taxname'] = [];
                    if ($expense->tax != 0) {
                        array_push($item['taxname'], $expense->tax_name . '|' . $expense->taxrate);
                    }
                    if ($expense->tax2 != 0) {
                        array_push($item['taxname'], $expense->tax_name2 . '|' . $expense->taxrate2);
                    }
                    $item['rate']  = $expense->amount;
                    $item['order'] = 1;
                    $item['unit']  = '';
                    $items[]       = $item;
                }
            }
            $data['customer_id']          = $project->clientid;
            $data['invoice_from_project'] = true;
            $data['add_items']            = $items;
            $this->load->view('admin/projects/invoice_project', $data);
        }
    }

    public function get_rel_project_data($id, $task_id = '')
    {
        if($this->nativeProjects()){
            if($this->input->method(true)!=='GET'){show_error('Método não permitido.',405);return;}
            try{$ctx=CompanyRequestScope::context();if($ctx->issuerId()===null)throw new DomainException('native_task_form_denied');require_once APPPATH.'services/NativeCompanyTaskForm.php';$out=(new NativeCompanyTaskForm($this->db,db_prefix()))->projectData($ctx->issuerId(),$ctx->staffId(),$id,$task_id);$this->output->set_header('Cache-Control: no-store');$this->output->set_header('Content-Type: application/json');$this->output->set_output(json_encode($out,JSON_THROW_ON_ERROR));}catch(DomainException $e){show_error('Projeto indisponível neste contexto.',422);}return;
        }

        if ($this->input->is_ajax_request()) {
            $selected_milestone = '';
            $assigned           = [];
            if ($task_id != '' && $task_id != 'undefined') {
                $task               = $this->tasks_model->get($task_id);
                $selected_milestone = $task->milestone;

                $assigned = array_map(function ($member) {
                    return $member['assigneeid'];
                }, $this->tasks_model->get_task_assignees($task_id));
            }
            $allow_to_view_tasks = 0;
            $this->db->where('project_id', $id);
            $this->db->where('name', 'view_tasks');
            $project_settings = $this->db->get(db_prefix() . 'project_settings')->row();
            if ($project_settings) {
                $allow_to_view_tasks = $project_settings->value;
            }

            $deadline = get_project_deadline($id);

            echo json_encode([
                'deadline'            => $deadline,
                'deadline_formatted'  => $deadline ? _d($deadline) : null,
                'allow_to_view_tasks' => $allow_to_view_tasks,
                'billing_type'        => get_project_billing_type($id),
                'milestones'          => array_map(function ($milestone) use ($selected_milestone) {
                    return '<option value="' . $milestone['id'] . '" ' . ($milestone['id'] == $selected_milestone ? 'selected' : '') . '>' . $milestone['name'] . '</option>';
                }, $this->projects_model->get_milestones($id)),
                'assignees' => array_map(function ($member) use ($assigned) {
                    return '<option value="' . $member['staff_id'] . '" ' . (in_array($member['staff_id'], $assigned) ? 'selected' : '') . '>' . $member['firstname'] . ' ' . $member['lastname'] . '</option>';
                }, $this->projects_model->get_project_members($id, true)),
            ]);
        }
    }

    public function invoice_project($project_id)
    {
        if($this->nativeCompanyProjects()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'projects','invoice_project',[$project_id]);return;}
        if (staff_can('create', 'invoices')) {
            $this->load->model('invoices_model');
            $data               = $this->input->post();
            $data['project_id'] = $project_id;
            $invoice_id         = $this->invoices_model->add($data);
            if ($invoice_id) {
                $this->projects_model->log_activity($project_id, 'project_activity_invoiced_project', format_invoice_number($invoice_id));
                set_alert('success', _l('project_invoiced_successfully'));
            }
            redirect(admin_url('projects/view/' . $project_id . '?group=project_invoices'));
        }
    }

    public function view_project_as_client($id, $clientid)
    {
        if (is_admin()) {
            login_as_client($clientid);
            redirect(site_url('clients/project/' . $id));
        }
    }

    public function get_staff_names_for_mentions($projectId)
    {
        if(class_exists('CompanyRequestScope',false)&&CompanyRequestScope::nativeFor($this)){
            if($this->input->method(true)!=='GET'){show_error('Método não permitido.',405);return;}
            try{$ctx=CompanyRequestScope::context();if($ctx->issuerId()===null)throw new DomainException('native_resource_reference_denied');require_once APPPATH.'services/NativeCompanyProjectRead.php';$members=(new NativeCompanyProjectRead($this->db,db_prefix()))->mentionMembers($ctx->issuerId(),$ctx->staffId(),$projectId);$this->output->set_header('Cache-Control: no-store');$this->output->set_header('Content-Type: application/json');$this->output->set_output(json_encode($members,JSON_THROW_ON_ERROR));}catch(DomainException$e){show_error('Recurso indisponível neste contexto.',422);}return;
        }

        if ($this->input->is_ajax_request()) {
            $projectId = $this->db->escape_str($projectId);

            $members = $this->projects_model->get_project_members($projectId);
            $members = array_map(function ($member) {
                $staff = $this->staff_model->get($member['staff_id']);

                $_member['id']   = $member['staff_id'];
                $_member['name'] = $staff->firstname . ' ' . $staff->lastname;

                return $_member;
            }, $members);

            echo json_encode($members);
        }
    }
}
