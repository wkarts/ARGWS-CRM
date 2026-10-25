<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Dashboard extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if (!$this->companyDashboard()) { $this->load->model('dashboard_model'); }
    }

    private function companyDashboard(): bool
    {
        return class_exists('CompanyRequestScope', false) && CompanyRequestScope::nativeFor($this);
    }

    private function renderCompanyDashboard(): void
    {
        if (!CompanyRequestScope::nativeFor($this)) { show_error('Contexto de empresa indisponível.', 503); return; }
        if (!in_array($this->input->method(true), ['GET','HEAD'], true)) { show_error('Método não permitido.',405); return; }
        require_once APPPATH.'services/CompanyDashboardPresenter.php';
        $ctx=CompanyRequestScope::context();
        if ($ctx->issuerId()===null) {
            $this->output->set_header('Cache-Control: no-store');
            $this->load->view('admin/dashboard/dashboard',['company_dashboard_selection_required'=>true,'title'=>_l('dashboard_string'),'bodyclass'=>'dashboard','dashboard'=>true]); return;
        }
        $presenter=new CompanyDashboardPresenter($this->db,db_prefix(),$ctx->issuerId(),$ctx->staffId());
        try {
            $snapshot=$presenter->snapshot();
            $data=$snapshot['view']+['company_dashboard'=>$snapshot,'title'=>_l('dashboard_string'),'bodyclass'=>'dashboard','dashboard'=>true];
            $html=$this->load->view('admin/dashboard/dashboard',$data,true);
            $presenter->confirm();
            $this->output->set_header('Cache-Control: no-store');
            $this->output->set_output($html);
        } catch (DomainException $e) {
            show_error('Não foi possível confirmar os dados e permissões desta empresa. Recarregue a página.', $e->getMessage()==='company_dashboard_stale'?409:403);
        }
    }

    private function companyPaymentChart($currency,string $period):void
    {
        $ctx=CompanyRequestScope::context();
        if(!CompanyRequestScope::nativeFor($this)||$ctx->issuerId()===null){show_error('Selecione uma empresa.',403);return;}
        if(!$this->input->is_ajax_request()||$this->input->method(true)!=='GET'){show_error('Método não permitido.',405);return;}
        require_once APPPATH.'services/CompanyDashboardPayments.php';
        try {
            $data=(new CompanyDashboardPayments($this->db,db_prefix()))->read($ctx->issuerId(),$ctx->staffId(),$currency,$period);
            $this->output->set_header('Cache-Control: no-store');$this->output->set_content_type('application/json');$this->output->set_output(json_encode($data,JSON_THROW_ON_ERROR));
        } catch(DomainException $error){show_error('Não foi possível confirmar os recebimentos desta empresa.',403);}
    }

    // This is admin dashboard view
    public function index()
    {
        if ($this->companyDashboard()) { $this->renderCompanyDashboard(); return; }
        // AdminController has already enforced current company membership and the
        // primary-only native dashboard boundary before consuming upgrade feedback.
        if ($this->input->method(true) === 'GET' && !$this->input->is_ajax_request()
            && is_staff_logged_in() && is_admin()) {
            require_once APPPATH . 'services/AdminDatabaseUpgrade.php';
            if (AdminDatabaseUpgrade::consumeSuccess($this->session, (int) get_staff_user_id(), (string) $this->current_db_version)) {
                set_alert('success', 'O CRM foi atualizado com sucesso.');
            }
        }
        close_setup_menu();
        $this->load->model('departments_model');
        $this->load->model('todo_model');
        $data['departments'] = $this->departments_model->get();

        $data['todos'] = $this->todo_model->get_todo_items(0);
        // Only show last 5 finished todo items
        $this->todo_model->setTodosLimit(5);
        $data['todos_finished']            = $this->todo_model->get_todo_items(1);
        $data['upcoming_events_next_week'] = $this->dashboard_model->get_upcoming_events_next_week();
        $data['upcoming_events']           = $this->dashboard_model->get_upcoming_events();
        $data['title']                     = _l('dashboard_string');

        $this->load->model('contracts_model');
        $data['expiringContracts'] = $this->contracts_model->get_contracts_about_to_expire(get_staff_user_id());

        $this->load->model('currencies_model');
        $data['currencies']    = $this->currencies_model->get();
        $data['base_currency'] = $this->currencies_model->get_base_currency();
        $data['activity_log']  = $this->misc_model->get_activity_log();
        // Tickets charts
        $tickets_awaiting_reply_by_status     = $this->dashboard_model->tickets_awaiting_reply_by_status();
        $tickets_awaiting_reply_by_department = $this->dashboard_model->tickets_awaiting_reply_by_department();

        $data['tickets_reply_by_status']              = json_encode($tickets_awaiting_reply_by_status);
        $data['tickets_awaiting_reply_by_department'] = json_encode($tickets_awaiting_reply_by_department);

        $data['tickets_reply_by_status_no_json']              = $tickets_awaiting_reply_by_status;
        $data['tickets_awaiting_reply_by_department_no_json'] = $tickets_awaiting_reply_by_department;

        $data['projects_status_stats'] = json_encode($this->dashboard_model->projects_status_stats());
        $data['leads_status_stats']    = json_encode($this->dashboard_model->leads_status_stats());
        $data['google_ids_calendars']  = $this->misc_model->get_google_calendar_ids();
        $data['bodyclass']             = 'dashboard invoices-total-manual';
        $this->load->model('announcements_model');
        $data['staff_announcements']             = $this->announcements_model->get();
        $data['total_undismissed_announcements'] = $this->announcements_model->get_total_undismissed_announcements();

        $this->load->model('projects_model');
        $data['projects_activity'] = $this->projects_model->get_activity('', hooks()->apply_filters('projects_activity_dashboard_limit', 20));
        add_calendar_assets();
        $this->load->model('utilities_model');
        $this->load->model('estimates_model');
        $data['estimate_statuses'] = $this->estimates_model->get_statuses();

        $this->load->model('proposals_model');
        $data['proposal_statuses'] = $this->proposals_model->get_statuses();

        $wps_currency = 'undefined';
        if (is_using_multiple_currencies()) {
            $wps_currency = $data['base_currency']->id;
        }
        $data['weekly_payment_stats'] = json_encode($this->dashboard_model->get_weekly_payments_statistics($wps_currency));

        $data['dashboard'] = true;

        $data['user_dashboard_visibility'] = get_staff_meta(get_staff_user_id(), 'dashboard_widgets_visibility');

        if (! $data['user_dashboard_visibility']) {
            $data['user_dashboard_visibility'] = [];
        } else {
            $data['user_dashboard_visibility'] = unserialize($data['user_dashboard_visibility']);
        }
        $data['user_dashboard_visibility'] = json_encode($data['user_dashboard_visibility']);

        $data['tickets_report'] = [];
        if (is_admin()) {
            $data['tickets_report'] = (new app\services\TicketsReportByStaff())->filterBy('this_month');
        }

        $data = hooks()->apply_filters('before_dashboard_render', $data);
        $this->load->view('admin/dashboard/dashboard', $data);
    }

    // Chart weekly payments statistics on home page / ajax
    public function weekly_payments_statistics($currency)
    {
        if ($this->companyDashboard()) { $this->companyPaymentChart($currency, 'weekly'); return; }
        if ($this->input->is_ajax_request()) {
            echo json_encode($this->dashboard_model->get_weekly_payments_statistics($currency));

            exit();
        }
    }

    // Chart monthly payments statistics on home page / ajax
    public function monthly_payments_statistics($currency)
    {
        if ($this->companyDashboard()) { $this->companyPaymentChart($currency, 'monthly'); return; }
        if ($this->input->is_ajax_request()) {
            echo json_encode($this->dashboard_model->get_monthly_payments_statistics($currency));

            exit();
        }
    }

    public function ticket_widget($type)
    {
        if ($this->companyDashboard()) {
            $ctx=CompanyRequestScope::context();
            if(!CompanyRequestScope::nativeFor($this)||$ctx->issuerId()===null){show_error('Selecione uma empresa.',403);return;}
            if(!$this->input->is_ajax_request()||$this->input->method(true)!=='GET'){show_error('Método não permitido.',405);return;}
            require_once APPPATH.'services/CompanyDashboardPresenter.php';
            $reader=new CompanyDashboardPresenter($this->db,db_prefix(),$ctx->issuerId(),$ctx->staffId());
            try {$data=['tickets_report'=>$reader->ticketReport((string)$type)];$html=$this->load->view('admin/dashboard/widgets/tickets_report_table',$data,true);$reader->confirm();$this->output->set_header('Cache-Control: no-store');$this->output->set_output($html);}
            catch(DomainException $error){show_error('Não foi possível confirmar o relatório desta empresa.',403);}return;
        }
        $data['tickets_report'] = (new app\services\TicketsReportByStaff())->filterBy($type);
        $this->load->view('admin/dashboard/widgets/tickets_report_table', $data);
    }
}
