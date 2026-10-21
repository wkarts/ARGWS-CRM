<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Company_task_recurring extends AdminController
{
    public function index()
    {
        if (!CompanyRequestScope::sealedFor($this)) { show_error('Contexto indisponível.', 503); return; }
        require_once APPPATH.'services/CompanyTaskRecurring.php';
        $context = CompanyRequestScope::context();
        $issuer = $context->issuerId();
        $actor = $context->staffId();
        $acl = new IssuerResourceAccess($this->db, db_prefix());
        $this->output->set_header('Cache-Control: no-store');
        $verb = $this->input->method(true);
        if (!in_array($verb, ['GET','HEAD','POST'], true)) { show_error('Método não permitido.', 405); return; }
        $kind = $verb === 'POST' ? $this->input->post('kind', false) : ($this->input->get('kind', false) ?? 'standalone');
        if (!is_string($kind) || !in_array($kind, ['project','standalone'], true)) { show_error('Modalidade inválida.', 422); return; }
        if (!$acl->can($issuer, $actor, 'tasks', 'view') || ($kind === 'project' && !$acl->can($issuer, $actor, 'projects', 'view'))) { show_error('Agenda indisponível nesta empresa.', 403); return; }
        $csrf = $this->session->userdata('company_task_recurring_csrf');
        if (!is_string($csrf) || !preg_match('/^[a-f0-9]{64}$/D', $csrf)) {
            $csrf = bin2hex(random_bytes(32));
            $this->session->set_userdata('company_task_recurring_csrf', $csrf);
        }
        $service = new CompanyTaskRecurring($this->db, db_prefix());
        $source = null; $detail = null; $directory = ['items'=>[], 'next'=>null]; $error = null;
        $canEdit = $acl->can($issuer, $actor, 'tasks', 'edit');
        $canCreate = $canEdit && $acl->can($issuer, $actor, 'tasks', 'create');
        try {
            if ($verb === 'POST') {
                $token = $this->input->post('company_task_recurring_csrf', false);
                if (!is_string($token) || !hash_equals($csrf, $token)) { show_error('Recarregue antes de salvar.', 403); return; }
                $action = $this->input->post('action', false);
                $key = $this->input->post('request_key', false);
                if (!is_string($key) || !preg_match('/^[a-f0-9]{64}$/D', $key)) { throw new DomainException('company_task_recurring_invalid'); }
                if ($action === 'create') {
                    $calendar = $this->input->post('calendar', false);
                    if (!$canCreate) { throw new DomainException('company_task_recurring_access_denied'); }
                    if (!is_array($calendar)) { throw new DomainException('company_task_recurring_invalid'); }
                    $id = $service->create($issuer, $actor, $kind, $this->input->post('source_id', false), $this->input->post('source_revision', false), $calendar, $key);
                } elseif (in_array($action, ['pause','resume','cancel'], true)) {
                    if (!$canEdit) { throw new DomainException('company_task_recurring_access_denied'); }
                    $id = $service->transition($issuer, $actor, $kind, $this->input->post('schedule_id', false), $this->input->post('revision', false), $action, $key);
                } elseif ($action === 'run') {
                    if (!$canCreate) { throw new DomainException('company_task_recurring_access_denied'); }
                    $id = IssuerPolicy::id($this->input->post('schedule_id', false));
                    $cycle = $this->input->post('cycle', false);
                    if (!is_string($cycle) || !preg_match('/^(0|[1-9][0-9]{0,2})$/D', $cycle) || (int)$cycle > 119) { throw new DomainException('company_task_recurring_invalid'); }
                    // Attest kind before the writer derives its immutable origin.
                    $service->get($issuer, $actor, $kind, $id);
                    $service->run($issuer, $actor, $id, null, (int)$cycle);
                } else { throw new DomainException('company_task_recurring_invalid'); }
                CompanyRequestScope::invalidateCaches($this);
                redirect(CompanyRequestScope::adminUrl($this, 'company_task_recurring?kind='.$kind.'&schedule='.(int)$id), 'location', 303);
                return;
            }
            $schedule = $this->input->get('schedule', false);
            if ($schedule !== null) { $detail = $service->get($issuer, $actor, $kind, $schedule); }
            $sourceId = $detail === null ? $this->input->get('source', false) : null;
            if ($sourceId !== null) {
                $sourceId = IssuerPolicy::id($sourceId);
                $reader = $kind === 'project' ? new CompanyTasks($this->db, db_prefix()) : new CompanyStandaloneTasks($this->db, db_prefix());
                $source = $reader->get($issuer, $actor, $sourceId);
            }
            $directory = $service->page($issuer, $actor, $kind, $this->input->get('after', false) ?? 0, $sourceId);
        } catch (DomainException $exception) {
            if (str_contains($exception->getMessage(), 'denied')) { show_error('Agenda ou referência indisponível nesta empresa.', 403); return; }
            $this->output->set_status_header(str_contains($exception->getMessage(), 'stale') || str_contains($exception->getMessage(), 'conflict') ? 409 : 422);
            $error = match ($exception->getMessage()) {
                'company_task_recurring_stale'=>'A tarefa ou a agenda mudou. Reabra a página e confira a revisão atual.',
                'company_task_recurring_limit'=>'O limite desta agenda ou da empresa foi atingido. Consulte o histórico antes de continuar.',
                'company_task_recurring_state'=>'Esta ação não está disponível no estado atual da agenda.',
                default=>'Confira o calendário, o responsável e as referências. Nenhuma alteração desta tentativa foi confirmada.',
            };
        } catch (Throwable $exception) {
            log_message('error', 'Task recurrence operation unconfirmed; task contents and origin credentials omitted.');
            $this->output->set_status_header(503);
            $error = 'Não foi possível confirmar a tentativa. Reabra a agenda e confira os ciclos registrados antes de tentar novamente.';
        }
        $this->load->view('admin/companies/task_recurring', [
            'issuer'=>$issuer, 'actor'=>$actor, 'kind'=>$kind, 'source'=>$source, 'detail'=>$detail, 'directory'=>$directory,
            'error'=>$error, 'can_create'=>$canCreate, 'can_edit'=>$canEdit, 'can_project'=>$acl->can($issuer, $actor, 'projects', 'view'),
            'company_task_recurring_csrf'=>$csrf, 'request_key'=>bin2hex(random_bytes(32)),
        ]);
    }
}
