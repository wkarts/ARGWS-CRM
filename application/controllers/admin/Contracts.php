<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Contracts extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if (CompanyRequestScope::nativeFor($this)) {
            return;
        }
        $this->load->model('contracts_model');
    }

    private function nativeContracts(): NativeCompanyContractsController
    {
        require_once APPPATH.'services/NativeCompanyContractsController.php';
        return new NativeCompanyContractsController($this);
    }

    private function nativeContractAction(callable $action): void
    {
        $this->output->set_header('Cache-Control: private, no-store');
        try {
            $action($this->nativeContracts());
        } catch (DomainException $error) {
            $reason=$error->getMessage();
            $status=str_contains($reason,'stale')||str_contains($reason,'conflict')||str_contains($reason,'mismatch')?409:
                (str_contains($reason,'csrf')||str_contains($reason,'access')||str_contains($reason,'denied')||str_contains($reason,'reference')||in_array($reason,['company_portal_operation_unavailable','company_contract_discussion_unavailable'],true)?403:422);
            show_error('Não foi possível confirmar o contrato nesta empresa. Reabra a tela e tente novamente.',$status);
        } catch (Throwable $error) {
            log_message('error','Native contract action failed closed; no contract contents logged.');
            show_error('Não foi possível confirmar o contrato nesta empresa.',503);
        }
    }

    /** Add owned contract records to the primary legacy directory without rewriting either ID space. */
    private function primaryOwnedContracts(): ?array
    {
        $context=CompanyRequestScope::context();
        if(!$context||!$context->isPrimary()||$context->issuerId()===null)return null;
        require_once APPPATH.'services/CompanyContracts.php';
        require_once APPPATH.'services/NativeCompanyResourceId.php';
        $issuer=IssuerPolicy::id($context->issuerId());$actor=IssuerPolicy::id($context->staffId());
        $acl=new IssuerResourceAccess($this->db,db_prefix());
        if(!$acl->can($issuer,$actor,'contracts','view'))return null;
        $after=$this->input->get('company_after',false)??0;
        if(!is_int($after)&&(!is_string($after)||!preg_match('/^[1-9][0-9]*$/D',$after)))$after=0;
        $search=$this->input->get('company_search',false)??'';
        if(!is_string($search))$search='';
        if(function_exists('mb_substr'))$search=mb_substr($search,0,191,'UTF-8');else $search=substr($search,0,191);
        return [
            'directory'=>(new CompanyContracts($this->db,db_prefix()))->page($issuer,$actor,$after,$search),
            'can_create'=>$acl->can($issuer,$actor,'contracts','create'),
            'search'=>$search,
        ];
    }

    /* List all contracts */
    public function index()
    {
        if (CompanyRequestScope::nativeFor($this)) {
            $this->nativeContractAction(static fn($native)=>$native->index());
            return;
        }
        close_setup_menu();

        if (staff_cant('view', 'contracts') && staff_cant('view_own', 'contracts')) {
            access_denied('contracts');
        }

        $data['expiring']               = $this->contracts_model->get_contracts_about_to_expire(get_staff_user_id());
        $data['count_active']           = count_active_contracts();
        $data['count_expired']          = count_expired_contracts();
        $data['count_recently_created'] = count_recently_created_contracts();
        $data['count_trash']            = count_trash_contracts();
        $data['chart_types']            = json_encode($this->contracts_model->get_contracts_types_chart_data());
        $data['chart_types_values']     = json_encode($this->contracts_model->get_contracts_types_values_chart_data());
        $data['contract_types']         = $this->contracts_model->get_contract_types();
        $data['years']                  = $this->contracts_model->get_contracts_years();
        $this->load->model('currencies_model');
        $data['base_currency'] = $this->currencies_model->get_base_currency();
        $data['title']         = _l('contracts');
        $data['table'] = App_table::find('contracts');
        $data['company_owned_contracts']=$this->primaryOwnedContracts();
        $this->load->view('admin/contracts/manage', $data);
    }

    public function table($clientid = '')
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeContractAction(static fn($native)=>$native->table($clientid));return;}
        if (staff_cant('view', 'contracts') && staff_cant('view_own', 'contracts')) {
            ajax_access_denied();
        }

       App_table::find('contracts')->output([
            'clientid' => $clientid,
       ]);
    }

    /* Edit contract or add new contract */
    public function contract($id = '')
    {
        if (CompanyRequestScope::nativeFor($this)) {
            $this->nativeContractAction(static fn($native)=>$native->contract($id));
            return;
        }
        if ($this->input->post()) {
            if ($id == '') {
                if (staff_cant('create', 'contracts')) {
                    access_denied('contracts');
                }
                $id = $this->contracts_model->add($this->input->post());
                if ($id) {
                    set_alert('success', _l('added_successfully', _l('contract')));
                    redirect(admin_url('contracts/contract/' . $id));
                }
            } else {
                if (staff_cant('edit', 'contracts')) {
                    access_denied('contracts');
                }
                $contract = $this->contracts_model->get($id);
                $data     = $this->input->post();

                if ($contract->signed == 1) {
                    unset($data['contract_value'],$data['clientid'], $data['datestart'], $data['dateend']);
                }

                $success = $this->contracts_model->update($data, $id);
                if ($success) {
                    set_alert('success', _l('updated_successfully', _l('contract')));
                }
                redirect(admin_url('contracts/contract/' . $id));
            }
        }
        if ($id == '') {
            $title = _l('add_new', _l('contract'));
        } else {
            $data['contract']                 = $this->contracts_model->get($id, [], true);
            $data['contract_renewal_history'] = $this->contracts_model->get_contract_renewal_history($id);
            $data['totalNotes']               = total_rows(db_prefix() . 'notes', ['rel_id' => $id, 'rel_type' => 'contract']);
            if (!$data['contract'] || (staff_cant('view', 'contracts') && $data['contract']->addedfrom != get_staff_user_id())) {
                blank_page(_l('contract_not_found'));
            }

            $data['contract_merge_fields'] = $this->app_merge_fields->get_flat('contract', ['other', 'client'], '{email_signature}');

            $title = $data['contract']->subject;

            $data = array_merge($data, prepare_mail_preview_data('contract_send_to_customer', $data['contract']->client));
        }

        if ($this->input->get('customer_id')) {
            $data['customer_id'] = $this->input->get('customer_id');
        }

        $this->load->model('currencies_model');
        $data['base_currency'] = $this->currencies_model->get_base_currency();
        $data['types']         = $this->contracts_model->get_contract_types();
        $data['title']         = $title;
        $data['bodyclass']     = 'contract';
        $this->load->view('admin/contracts/contract', $data);
    }

    public function get_template()
    {
        $name = $this->input->get('name');
        echo $this->load->view('admin/contracts/templates/' . $name, [], true);
    }

    public function mark_as_signed($id)
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeContractAction(static fn($native)=>$native->action('mark_as_signed',$id));return;}
        if (staff_cant('edit', 'contracts')) {
            access_denied('mark contract as signed');
        }

        $this->contracts_model->mark_as_signed($id);

        redirect(admin_url('contracts/contract/' . $id));
    }

    public function unmark_as_signed($id)
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeContractAction(static fn($native)=>$native->action('unmark_as_signed',$id));return;}
        if (staff_cant('edit', 'contracts')) {
            access_denied('mark contract as signed');
        }

        $this->contracts_model->unmark_as_signed($id);

        redirect(admin_url('contracts/contract/' . $id));
    }

    public function pdf($id)
    {
        if (CompanyRequestScope::nativeFor($this)) {
            $this->nativeContractAction(static fn($native)=>$native->pdf($id));
            return;
        }
        if (staff_cant('view', 'contracts') && staff_cant('view_own', 'contracts')) {
            access_denied('contracts');
        }

        if (!$id) {
            redirect(admin_url('contracts'));
        }

        $contract = $this->contracts_model->get($id);

        try {
            $pdf = contract_pdf($contract);
        } catch (Exception $e) {
            echo $e->getMessage();
            die;
        }

        $type = 'D';

        if ($this->input->get('output_type')) {
            $type = $this->input->get('output_type');
        }

        if ($this->input->get('print')) {
            $type = 'I';
        }

        $pdf->Output(slug_it($contract->subject) . '.pdf', $type);
    }

    /** Private company-owned attachment; no public filesystem path is accepted. */
    public function file($id, $key)
    {
        if (!CompanyRequestScope::nativeFor($this)) {
            show_404();
            return;
        }
        $this->nativeContractAction(static fn($native)=>$native->file($id,(string)$key));
    }

    public function send_to_email($id)
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeContractAction(static fn($native)=>$native->action('send_to_email',$id));return;}
        if (staff_cant('view', 'contracts') && staff_cant('view_own', 'contracts')) {
            access_denied('contracts');
        }
        $success = $this->contracts_model->send_contract_to_client($id, $this->input->post('attach_pdf'), $this->input->post('cc'));
        if ($success) {
            set_alert('success', _l('contract_sent_to_client_success'));
        } else {
            set_alert('danger', _l('contract_sent_to_client_fail'));
        }
        redirect(admin_url('contracts/contract/' . $id));
    }

    public function add_note($rel_id)
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeContractAction(static fn($native)=>$native->action('add_note',$rel_id));return;}
        if ($this->input->post() && (staff_can('view',  'contracts') || staff_can('view_own',  'contracts'))) {
            $this->misc_model->add_note($this->input->post(), 'contract', $rel_id);
            echo $rel_id;
        }
    }

    public function get_notes($id)
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeContractAction(static fn($native)=>$native->notes($id));return;}
        if ((staff_can('view',  'contracts') || staff_can('view_own',  'contracts'))) {
            $data['notes'] = $this->misc_model->get_notes($id, 'contract');
            $this->load->view('admin/includes/sales_notes_template', $data);
        }
    }

    public function clear_signature($id)
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeContractAction(static fn($native)=>$native->action('clear_signature',$id));return;}
        if (staff_can('delete',  'contracts')) {
            $this->contracts_model->clear_signature($id);
        }

        redirect(admin_url('contracts/contract/' . $id));
    }

    public function save_contract_data()
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeContractAction(static fn($native)=>$native->action('save_contract_data'));return;}
        if (staff_cant('edit', 'contracts')) {
            header('HTTP/1.0 400 Bad error');
            echo json_encode([
                'success' => false,
                'message' => _l('access_denied'),
            ]);
            die;
        }

        $success = false;
        $message = '';

        $this->db->where('id', $this->input->post('contract_id'));
        $this->db->update(db_prefix() . 'contracts', [
                'content' => html_purify($this->input->post('content', false)),
        ]);

        $success = $this->db->affected_rows() > 0;
        $message = _l('updated_successfully', _l('contract'));

        echo json_encode([
            'success' => $success,
            'message' => $message,
        ]);
    }

    public function add_comment()
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeContractAction(static fn($native)=>$native->action('add_comment'));return;}
        if ($this->input->post()) {
            echo json_encode([
                'success' => $this->contracts_model->add_comment($this->input->post()),
            ]);
        }
    }

    public function edit_comment($id)
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeContractAction(static fn($native)=>$native->action('edit_comment',null,$id));return;}
        if ($this->input->post()) {
            echo json_encode([
                'success' => $this->contracts_model->edit_comment($this->input->post(), $id),
                'message' => _l('comment_updated_successfully'),
            ]);
        }
    }

    public function get_comments($id)
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeContractAction(static fn($native)=>$native->comments($id));return;}
        $data['comments'] = $this->contracts_model->get_comments($id);
        $this->load->view('admin/contracts/comments_template', $data);
    }

    public function remove_comment($id)
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeContractAction(static fn($native)=>$native->action('remove_comment',null,$id));return;}
        $this->db->where('id', $id);
        $comment = $this->db->get(db_prefix() . 'contract_comments')->row();
        if ($comment) {
            if ($comment->staffid != get_staff_user_id() && !is_admin()) {
                echo json_encode([
                    'success' => false,
                ]);
                die;
            }
            echo json_encode([
                'success' => $this->contracts_model->remove_comment($id),
            ]);
        } else {
            echo json_encode([
                'success' => false,
            ]);
        }
    }

    public function renew()
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeContractAction(static fn($native)=>$native->action('renew'));return;}
        if (staff_cant('edit', 'contracts')) {
            access_denied('contracts');
        }
        if ($this->input->post()) {
            $data    = $this->input->post();
            $success = $this->contracts_model->renew($data);
            if ($success) {
                set_alert('success', _l('contract_renewed_successfully'));
            } else {
                set_alert('warning', _l('contract_renewed_fail'));
            }
            redirect(admin_url('contracts/contract/' . $data['contractid'] . '?tab=renewals'));
        }
    }

    public function delete_renewal($renewal_id, $contractid)
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeContractAction(static fn($native)=>$native->action('delete_renewal',$contractid,$renewal_id));return;}
        $success = $this->contracts_model->delete_renewal($renewal_id, $contractid);
        if ($success) {
            set_alert('success', _l('contract_renewal_deleted'));
        } else {
            set_alert('warning', _l('contract_renewal_delete_fail'));
        }
        redirect(admin_url('contracts/contract/' . $contractid . '?tab=renewals'));
    }

    public function copy($id)
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeContractAction(static fn($native)=>$native->action('copy',$id));return;}
        if (staff_cant('create', 'contracts')) {
            access_denied('contracts');
        }
        if (!$id) {
            redirect(admin_url('contracts'));
        }
        $newId = $this->contracts_model->copy($id);
        if ($newId) {
            set_alert('success', _l('contract_copied_successfully'));
        } else {
            set_alert('warning', _l('contract_copied_fail'));
        }
        redirect(admin_url('contracts/contract/' . $newId));
    }

    /* Delete contract from database */
    public function delete($id)
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeContractAction(static fn($native)=>$native->action('delete',$id));return;}
        if (staff_cant('delete', 'contracts')) {
            access_denied('contracts');
        }
        if (!$id) {
            redirect(admin_url('contracts'));
        }
        $response = $this->contracts_model->delete($id);
        if ($response == true) {
            set_alert('success', _l('deleted', _l('contract')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('contract_lowercase')));
        }
        
        redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
    }

    /* Manage contract types Since Version 1.0.3 */
    public function type($id = '')
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeContractAction(static fn($native)=>$native->type($id));return;}
        if (!is_admin() && get_option('staff_members_create_inline_contract_types') == '0') {
            access_denied('contracts');
        }
        if ($this->input->post()) {
            if (!$this->input->post('id')) {
                $id = $this->contracts_model->add_contract_type($this->input->post());
                if ($id) {
                    $success = true;
                    $message = _l('added_successfully', _l('contract_type'));
                }
                echo json_encode([
                    'success' => $success,
                    'message' => $message,
                    'id'      => $id,
                    'name'    => $this->input->post('name'),
                ]);
            } else {
                $data = $this->input->post();
                $id   = $data['id'];
                unset($data['id']);
                $success = $this->contracts_model->update_contract_type($data, $id);
                $message = '';
                if ($success) {
                    $message = _l('updated_successfully', _l('contract_type'));
                }
                echo json_encode([
                    'success' => $success,
                    'message' => $message,
                ]);
            }
        }
    }

    public function types()
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeContractAction(static fn($native)=>$native->types());return;}
        if (!is_admin()) {
            access_denied('contracts');
        }
        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data('contract_types');
        }
        $data['title'] = _l('contract_types');
        $this->load->view('admin/contracts/manage_types', $data);
    }

    /* Delete announcement from database */
    public function delete_contract_type($id)
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeContractAction(static fn($native)=>$native->type($id,true));return;}
        if (!$id) {
            redirect(admin_url('contracts/types'));
        }
        if (!is_admin()) {
            access_denied('contracts');
        }
        $response = $this->contracts_model->delete_contract_type($id);
        if (is_array($response) && isset($response['referenced'])) {
            set_alert('warning', _l('is_referenced', _l('contract_type_lowercase')));
        } elseif ($response == true) {
            set_alert('success', _l('deleted', _l('contract_type')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('contract_type_lowercase')));
        }
        redirect(admin_url('contracts/types'));
    }

    public function add_contract_attachment($id)
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeContractAction(static fn($native)=>$native->action('add_contract_attachment',$id));return;}
        handle_contract_attachment($id);
    }

    public function add_external_attachment()
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeContractAction(static fn($native)=>$native->action('add_external_attachment'));return;}
        if ($this->input->post()) {
            $this->misc_model->add_attachment_to_database(
                $this->input->post('contract_id'),
                'contract',
                $this->input->post('files'),
                $this->input->post('external')
            );
        }
    }

    public function delete_contract_attachment($attachment_id)
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeContractAction(static fn($native)=>$native->action('delete_contract_attachment',null,$attachment_id));return;}
        $file = $this->misc_model->get_file($attachment_id);
        if ($file->staffid == get_staff_user_id() || is_admin()) {
            echo json_encode([
                'success' => $this->contracts_model->delete_contract_attachment($attachment_id),
            ]);
        }
    }
}
