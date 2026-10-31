<?php

defined('BASEPATH') or exit('No direct script access allowed');
require_once APPPATH.'services/NativeCompanySpecializedOperations.php';

class Invoices extends AdminController
{
    private function nativeCompanyInvoices(): bool
    {
        return class_exists('CompanyRequestScope', false) && CompanyRequestScope::nativeFor($this);
    }

    use NativeCompanySpecializedOperations;

    public function standalone_billing() { $this->nativeInvoiceStandaloneBilling(); }
    public function __construct()
    {
        parent::__construct();
        if (CompanyRequestScope::nativeFor($this)) {
            return; // NativeCompanyRoutes must admit only completely adapted methods.
        }
        $this->load->model('invoices_model');
        $this->load->model('credit_notes_model');
    }

    /* Get all invoices in case user go on index page */
    public function index($id = '')
    {
        $this->list_invoices($id);
    }

    /* List all invoices datatables */
    public function list_invoices($id = '')
    {
        if (CompanyRequestScope::nativeFor($this)) {
            $this->output->set_header('Cache-Control: no-store');
            try {
                if(!in_array(strtoupper($this->input->method(true)),['GET','HEAD'],true))throw new DomainException('native_company_invoice_query_invalid');
                $status=$this->input->get('status');$notSent=$this->input->get('not_sent');
                if($status!==null&&$status!==''&&(!is_scalar($status)||!preg_match('/^[1-6]$/D',(string)$status)))throw new DomainException('native_company_invoice_query_invalid');
                if($notSent!==null&&!in_array($notSent,['',0,1,'0','1'],true))throw new DomainException('native_company_invoice_query_invalid');
                require_once APPPATH.'services/NativeCompanyInvoiceManage.php';
                $context=CompanyRequestScope::context();
                $data=(new NativeCompanyInvoiceManage($this->db,db_prefix()))->data($context->issuerId(),$context->staffId(),$id);
                $this->load->view('admin/invoices/manage',$data);
            } catch (DomainException $error) {
                show_error('Faturas indisponíveis ou solicitação inválida nesta empresa.',$error->getMessage()==='native_company_invoice_query_invalid'?422:403);
            } catch (Throwable $error) {
                show_error('Não foi possível confirmar a configuração das faturas.',503);
            }
            return;
        }

        if (staff_cant('view', 'invoices')
            && staff_cant('view_own', 'invoices')
            && get_option('allow_staff_view_invoices_assigned') == '0') {
            access_denied('invoices');
        }

        close_setup_menu();

        $this->load->model('payment_modes_model');
        $data['payment_modes']        = $this->payment_modes_model->get('', [], true);
        $data['invoiceid']            = $id;
        $data['title']                = _l('invoices');
        $data['invoices_years']       = $this->invoices_model->get_invoices_years();
        $data['invoices_sale_agents'] = $this->invoices_model->get_sale_agents();
        $data['invoices_statuses']    = $this->invoices_model->get_statuses();
        $data['invoices_table'] = App_table::find('invoices');
        
        $this->load->view('admin/invoices/manage', $data);
    }

    /* List all recurring invoices */
    public function recurring($id = '')
    {
        if (CompanyRequestScope::nativeFor($this)) {
            require_once APPPATH.'services/NativeCompanyInvoiceRecurringHttp.php';
            (new NativeCompanyInvoiceRecurringHttp($this))->index($id);
            return;
        }
        if (staff_cant('view', 'invoices')
            && staff_cant('view_own', 'invoices')
            && get_option('allow_staff_view_invoices_assigned') == '0') {
            access_denied('invoices');
        }

        close_setup_menu();

        $data['invoiceid']            = $id;
        $data['title']                = _l('invoices_list_recurring');
        $data['invoices_years']       = $this->invoices_model->get_invoices_years();
        $data['invoices_sale_agents'] = $this->invoices_model->get_sale_agents();
        $this->load->view('admin/invoices/recurring/list', $data);
    }

    public function table($clientid = '')
    {
        if (CompanyRequestScope::nativeFor($this)) {
            $this->output->set_header('Cache-Control: no-store');
            try {
                CompanyRequestScope::assertNativeMutation($this);
                require_once APPPATH.'services/NativeCompanyInvoiceDataTable.php';
                require_once APPPATH.'services/NativeCompanyInvoiceRows.php';
                if($this->input->get('recurring'))throw new DomainException('native_company_invoice_table_request_invalid');
                $input=$this->input->post(null,false)?:[];
                unset($input['_company_context'],$input['_native_company_csrf']);
                if(isset($this->security))unset($input[$this->security->get_csrf_token_name()]);
                $context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();
                $columns=new NativeCompanyInvoiceCustomColumns($this->db,db_prefix(),$issuer,$actor);
                $request=NativeCompanyInvoiceDataTable::request($input,$clientid,$columns->definitions(true));
                $page=(new NativeCompanyInvoiceRead($this->db,db_prefix()))->page($issuer,$actor,$request['query'],$request['scope']);
                $presenter=new NativeCompanyInvoiceRows($this->db,db_prefix());$rows=[];
                foreach($page['documents']as$document)$rows[]=$presenter->render($issuer,$actor,$document['invoice_id'],$request['scope']);
                $columns->recheck();
                if(isset($input['order'],$input['last_order_identifier'])&&get_option('save_last_order_for_tables')==='1'){
                    require_once APPPATH.'services/NativeCompanyTableOrder.php';
                    (new NativeCompanyTableOrder($this->db,db_prefix(),$issuer,$actor))->save($input['last_order_identifier'],$input['order'],count($input['columns']));
                }
                $response=NativeCompanyInvoiceDataTable::response($page,$rows);
                $this->output->set_content_type('application/json')->set_output(json_encode($response,JSON_THROW_ON_ERROR));
            } catch (DomainException $error) {
                $message=$error->getMessage();
                $status=in_array($message,['native_company_invoice_table_request_invalid','native_company_invoice_query_invalid','native_company_invoice_filter_invalid'],true)?422:
                    (in_array($message,['native_company_invoice_read_changed','native_company_invoice_columns_changed'],true)?409:403);
                show_error('Lista de faturas indisponível ou solicitação inválida nesta empresa.',$status);
            } catch (Throwable $error) {
                show_error('Não foi possível confirmar a lista de faturas.',503);
            }
            return;
        }

        if (staff_cant('view', 'invoices')
            && staff_cant('view_own', 'invoices')
            && get_option('allow_staff_view_invoices_assigned') == '0') {
            ajax_access_denied();
        }
        
        $this->load->model('payment_modes_model');
        $data['payment_modes'] = $this->payment_modes_model->get('', [], true);

        if($this->input->get('recurring')) {
            $this->app->get_table_data('recurring_invoices', [
                'data'     => $data,
            ]);
        } else {
            App_table::find('invoices')->output([
                'clientid' => $clientid,
                'data'     => $data,
            ]);
        }
    }

    public function client_change_data($customer_id, $current_invoice = '')
    {
        if (CompanyRequestScope::nativeFor($this)) {
            $this->output->set_header('Cache-Control: no-store');
            if ($this->input->method(true) !== 'GET') { return show_error('Method not allowed', 405); }
            try {
                require_once APPPATH . 'services/NativeCompanyInvoiceClientData.php';
                $context = CompanyRequestScope::context();
                $data = (new NativeCompanyInvoiceClientData($this->db, db_prefix()))->read($context->issuerId(), $context->staffId(), $customer_id, $current_invoice);
                $data['merge_info'] = $this->load->view('admin/invoices/merge_invoice', ['invoices_to_merge'=>$data['invoices_to_merge']], true);
                $data['expenses_bill_info'] = $this->load->view('admin/invoices/bill_expenses', ['expenses_to_bill'=>$data['expenses_to_bill']], true);
                unset($data['invoices_to_merge'], $data['expenses_to_bill']);
                $this->output->set_content_type('application/json')->set_output(json_encode($data, JSON_THROW_ON_ERROR));
                return;
            } catch (DomainException $error) { return show_error('Invoice customer selection denied', 403); }
              catch (Throwable $error) { return show_error('Invoice customer selection unavailable', 503); }
        }

        if ($current_invoice !== '' && !\app\services\InvoiceIssuerScope::isLegacyInvoice($this->db, db_prefix() . 'invoices', $current_invoice)) { show_404(); }
        if ($this->input->is_ajax_request()) {
            $this->load->model('projects_model');
            $data                     = [];
            $data['billing_shipping'] = $this->clients_model->get_customer_billing_and_shipping_details($customer_id);
            $data['client_currency']  = $this->clients_model->get_customer_default_currency($customer_id);

            $data['customer_has_projects'] = customer_has_projects($customer_id);
            $data['billable_tasks']        = $this->tasks_model->get_billable_tasks($customer_id);

            if ($current_invoice != '') {
                $this->db->select('status');
                $this->db->where('id', $current_invoice);
                $current_invoice_status = $this->db->get(db_prefix() . 'invoices')->row()->status;
            }

            $_data['invoices_to_merge'] = !isset($current_invoice_status) || (isset($current_invoice_status) && $current_invoice_status != Invoices_model::STATUS_CANCELLED) ? $this->invoices_model->check_for_merge_invoice($customer_id, $current_invoice) : [];

            $data['merge_info'] = $this->load->view('admin/invoices/merge_invoice', $_data, true);

            $this->load->model('currencies_model');

            $__data['expenses_to_bill'] = !isset($current_invoice_status) || (isset($current_invoice_status) && $current_invoice_status != Invoices_model::STATUS_CANCELLED) ? $this->invoices_model->get_expenses_to_bill($customer_id) : [];

            $data['expenses_bill_info'] = $this->load->view('admin/invoices/bill_expenses', $__data, true);
            echo json_encode($data);
        }
    }

    public function update_number_settings($id)
    {
        if (!\app\services\InvoiceIssuerScope::isLegacyInvoice($this->db, db_prefix() . 'invoices', $id)) { show_404(); }
        $response = [
            'success' => false,
            'message' => '',
        ];

        if (staff_can('edit',  'invoices')) {
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'invoices', [
                'prefix' => $this->input->post('prefix'),
            ]);
            
            if ($this->db->affected_rows() > 0) {
                $this->invoices_model->save_formatted_number($id);
               
                $response['success'] = true;
                $response['message'] = _l('updated_successfully', _l('invoice'));
            }
        }
        
        echo json_encode($response);
        die;
    }

    public function validate_invoice_number()
    {
        if (CompanyRequestScope::nativeFor($this)) {
            $this->output->set_header('Cache-Control: no-store');
            try {
                CompanyRequestScope::assertNativeMutation($this);
                require_once APPPATH.'services/NativeCompanyInvoiceNumberValidation.php';
                $input=$this->input->post(null,false)?:[];unset($input['_company_context'],$input['_native_company_csrf']);
                if(isset($this->security))unset($input[$this->security->get_csrf_token_name()]);
                $context=CompanyRequestScope::context();
                $valid=(new NativeCompanyInvoiceNumberValidation($this->db,db_prefix()))->validate($context->issuerId(),$context->staffId(),$input);
                $this->output->set_content_type('application/json')->set_output(json_encode($valid));
            } catch (DomainException $error) {
                show_error('Número inválido ou fatura indisponível nesta empresa.',str_contains($error->getMessage(),'stale')?409:($error->getMessage()==='native_invoice_number_invalid'?422:403));
            } catch (Throwable $error) { show_error('Não foi possível confirmar o número da fatura.',503); }
            return;
        }
        $isedit          = $this->input->post('isedit');
        $number          = $this->input->post('number');
        $date            = $this->input->post('date');
        $original_number = $this->input->post('original_number');
        $number          = trim($number);
        $number          = ltrim($number, '0');

        if ($isedit == 'true') {
            if ($number == $original_number) {
                echo json_encode(true);
                die;
            }
        }

        \app\services\InvoiceIssuerScope::applyLegacy($this->db, db_prefix() . 'invoices');
        $this->db->where('YEAR(date)', date('Y', strtotime(to_sql_date($date))))->where('number', $number)->where('status !=', Invoices_model::STATUS_DRAFT);
        if ($this->db->count_all_results(db_prefix() . 'invoices') > 0) {
            echo 'false';
        } else {
            echo 'true';
        }
    }

    public function add_note($rel_id)
    {
        if (CompanyRequestScope::nativeFor($this)) {
            $this->companyNotes($rel_id, true);
            return;
        }
        if ($this->input->post() && user_can_view_invoice($rel_id)) {
            $this->misc_model->add_note($this->input->post(), 'invoice', $rel_id);
            echo $rel_id;
        }
    }

    public function get_notes($id)
    {
        if (CompanyRequestScope::nativeFor($this)) {
            $this->companyNotes($id, false);
            return;
        }
        if (user_can_view_invoice($id)) {
            $data['notes'] = $this->misc_model->get_notes($id, 'invoice');
            $this->load->view('admin/includes/sales_notes_template', $data);
        }
    }

    private function companyNotes($id, bool $write): void
    {
        require_once APPPATH.'services/NativeCompanyAuxiliaryUi.php';
        $context=CompanyRequestScope::context();
        $this->output->set_header('Cache-Control: no-store');
        try {
            $service=new NativeCompanyAuxiliaryUi($this->db,db_prefix(),$context->issuerId(),$context->staffId());
            if ($write) {
                CompanyRequestScope::assertNativeMutation($this);
                $service->saveNote('invoice',$id,['description'=>$this->input->post('description',false)]);
                $this->output->set_content_type('text/plain')->set_output((string)$id);
            } else {
                if(!in_array($this->input->method(true),['GET','HEAD'],true)){
                    show_error('Método não permitido.',405);return;
                }
                $notes=$service->notes('invoice',$id);
                if($this->input->method(true)==='HEAD'){$this->output->set_output('');return;}
                $this->load->view('admin/includes/sales_notes_template',['notes'=>$notes,'native_company_notes'=>true]);
            }
        } catch (DomainException $error) {
            $reason=$error->getMessage();
            $status=str_contains($reason,'stale')?409:(str_contains($reason,'invalid_input')?422:403);
            show_error('Nota indisponível ou solicitação não autorizada nesta empresa.',$status);
        } catch (Throwable $error) {
            show_error('Não foi possível confirmar a operação. Recarregue a fatura.',503);
        }
    }

    /** Existing native action URLs, with company-bound POST authority and durable replay. */
    private function nativeCommunicationCommand($id, string $operation): void
    {
        $this->output->set_header('Cache-Control: no-store');
        try {
            CompanyRequestScope::assertNativeMutation($this);
            require_once APPPATH.'services/NativeCompanyInvoiceCommunicationState.php';
            $input=$this->input->post(null,false)?:[];
            unset($input['_company_context'],$input['_native_company_csrf']);
            if(isset($this->security))unset($input[$this->security->get_csrf_token_name()]);
            if(array_diff(array_keys($input),['revision','request_key'])||!is_string($input['revision']??null)||!is_string($input['request_key']??null))throw new DomainException('native_invoice_communication_invalid');
            $reference=NativeCompanyResourceId::decode('invoice',$id);
            if($reference['source_kind']!=='invoice')throw new DomainException('native_invoice_draft_issue_required');
            $context=CompanyRequestScope::context();
            $result=(new NativeCompanyInvoiceCommunicationState($this->db,db_prefix()))->change($context->issuerId(),$context->staffId(),$reference['source_id'],$operation,$input['revision'],$input['request_key']);
            $this->output->set_content_type('application/json')->set_output(json_encode(['success'=>true,'redirect'=>admin_url('invoices/list_invoices/'.$reference['canonical_id'])]+$result,JSON_THROW_ON_ERROR));
        } catch(DomainException $error) {
            $reason=$error->getMessage();show_error('Não foi possível confirmar a alteração desta fatura.',str_contains($reason,'stale')||str_contains($reason,'conflict')?409:(str_contains($reason,'invalid')&&!str_contains($reason,'csrf')?422:403));
        } catch(Throwable $error) { show_error('Não foi possível confirmar a alteração desta fatura.',503); }
    }

    public function pause_overdue_reminders($id)
    {
        if (CompanyRequestScope::nativeFor($this)) { $this->nativeCommunicationCommand($id, 'pause_overdue_reminders'); return; }
        if (!\app\services\InvoiceIssuerScope::isLegacyInvoice($this->db, db_prefix() . 'invoices', $id)) { show_404(); }
        if (staff_can('edit',  'invoices')) {
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'invoices', ['cancel_overdue_reminders' => 1]);
        }
        redirect(admin_url('invoices/list_invoices/' . $id));
    }

    public function resume_overdue_reminders($id)
    {
        if (CompanyRequestScope::nativeFor($this)) { $this->nativeCommunicationCommand($id, 'resume_overdue_reminders'); return; }
        if (!\app\services\InvoiceIssuerScope::isLegacyInvoice($this->db, db_prefix() . 'invoices', $id)) { show_404(); }
        if (staff_can('edit',  'invoices')) {
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'invoices', ['cancel_overdue_reminders' => 0]);
        }
        redirect(admin_url('invoices/list_invoices/' . $id));
    }

    public function mark_as_cancelled($id)
    {
        if (CompanyRequestScope::nativeFor($this)) {
            $this->output->set_header('Cache-Control: no-store');
            try {
                CompanyRequestScope::assertNativeMutation($this);
                require_once APPPATH.'services/NativeCompanyResourceId.php';
                require_once APPPATH.'services/CompanyInvoices.php';
                $input=$this->input->post(null,false)?:[];unset($input['_company_context'],$input['_native_company_csrf']);
                if(isset($this->security))unset($input[$this->security->get_csrf_token_name()]);
                $context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();
                $reference=NativeCompanyResourceId::decode('invoice',$id);
                if($reference['source_kind']==='company_invoice_draft'){
                    if(array_diff(array_keys($input),['revision','request_key'])||!is_string($input['request_key']??null))throw new DomainException('company_invoice_invalid_input');
                    require_once APPPATH.'services/CompanyInvoiceDrafts.php';
                    (new CompanyInvoiceDrafts($this->db,db_prefix()))->change($issuer,$actor,$reference['source_id'],$input['revision']??null,null,$input['request_key']);
                }else{
                    if(array_keys($input)!==['revision']||!is_string($input['revision']))throw new DomainException('company_invoice_invalid_input');
                    require_once APPPATH.'services/NativeCompanyInvoiceIdentity.php';
                    $identity=(new NativeCompanyInvoiceIdentity($this->db,db_prefix()))->resolve($issuer,$actor,$reference['source_id'],'edit');
                    if($identity['lineage']!=='named_company')throw new DomainException('native_legacy_invoice_review_required');
                    (new CompanyInvoices($this->db,db_prefix()))->change($issuer,$actor,$reference['source_id'],$input['revision']);
                }
                $this->output->set_content_type('application/json')->set_output(json_encode(['success'=>true,'redirect'=>admin_url('invoices/list_invoices/'.$reference['canonical_id'])],JSON_THROW_ON_ERROR));
            }catch(DomainException$error){$reason=$error->getMessage();show_error('Não foi possível cancelar esta fatura nesta empresa.',str_contains($reason,'csrf')||str_contains($reason,'context')?403:(str_contains($reason,'stale')||str_contains($reason,'conflict')?409:(str_contains($reason,'invalid')?422:403)));}
            catch(Throwable$error){show_error('Não foi possível confirmar o cancelamento.',503);}
            return;
        }
        if (staff_cant('edit', 'invoices') && staff_cant('create', 'invoices')) {
            access_denied('invoices');
        }

        $success = $this->invoices_model->mark_as_cancelled($id);

        if ($success) {
            set_alert('success', _l('invoice_marked_as_cancelled_successfully'));
        }

        redirect(admin_url('invoices/list_invoices/' . $id));
    }

    public function unmark_as_cancelled($id)
    {
        if (CompanyRequestScope::nativeFor($this)) {
            $this->output->set_header('Cache-Control: no-store');
            try {
                CompanyRequestScope::assertNativeMutation($this);
                require_once APPPATH.'services/NativeCompanyResourceId.php';
                require_once APPPATH.'services/NativeCompanyInvoiceIdentity.php';
                require_once APPPATH.'services/CompanyInvoices.php';
                $input=$this->input->post(null,false)?:[];unset($input['_company_context'],$input['_native_company_csrf']);
                if(isset($this->security))unset($input[$this->security->get_csrf_token_name()]);
                if(array_keys($input)!==['revision']||!is_string($input['revision']))throw new DomainException('company_invoice_invalid_input');
                $context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();
                $reference=NativeCompanyResourceId::decode('invoice',$id);
                if($reference['source_kind']==='company_invoice_draft')throw new DomainException('company_invoice_reopen_review_required');
                $identity=(new NativeCompanyInvoiceIdentity($this->db,db_prefix()))->resolve($issuer,$actor,$reference['source_id'],'edit');
                if($identity['lineage']!=='named_company')throw new DomainException('native_legacy_invoice_review_required');
                (new CompanyInvoices($this->db,db_prefix()))->reopen($issuer,$actor,$reference['source_id'],$input['revision']);
                $this->output->set_content_type('application/json')->set_output(json_encode(['success'=>true,'redirect'=>admin_url('invoices/list_invoices/'.$reference['canonical_id'])],JSON_THROW_ON_ERROR));
            }catch(DomainException$error){$reason=$error->getMessage();show_error('Não foi possível reabrir esta fatura nesta empresa.',str_contains($reason,'csrf')||str_contains($reason,'context')?403:(str_contains($reason,'stale')||str_contains($reason,'conflict')?409:(str_contains($reason,'invalid')?422:403)));}
            catch(Throwable$error){show_error('Não foi possível confirmar a reabertura.',503);}
            return;
        }
        if (staff_cant('edit', 'invoices') && staff_cant('create', 'invoices')) {
            access_denied('invoices');
        }
        $success = $this->invoices_model->unmark_as_cancelled($id);
        if ($success) {
            set_alert('success', _l('invoice_unmarked_as_cancelled'));
        }
        redirect(admin_url('invoices/list_invoices/' . $id));
    }

    public function copy($id = '')
    {
        if (CompanyRequestScope::nativeFor($this)) {
            require_once APPPATH.'services/NativeCompanyInvoiceCopyHttp.php';
            (new NativeCompanyInvoiceCopyHttp($this))->index($id);
            return;
        }
        if (!$id) {
            redirect(admin_url('invoices'));
        }
        if (staff_cant('create', 'invoices')) {
            access_denied('invoices');
        }
        $new_id = $this->invoices_model->copy($id);
        if ($new_id) {
            set_alert('success', _l('invoice_copy_success'));
            redirect(admin_url('invoices/invoice/' . $new_id));
        } else {
            set_alert('success', _l('invoice_copy_fail'));
        }
        redirect(admin_url('invoices/invoice/' . $id));
    }

    /** Private drafts retain their own identity until explicit issuance. */
    public function drafts()
    {
        if (func_num_args() !== 0) { show_404(); return; }
        if (CompanyRequestScope::nativeFor($this)) {
            require_once APPPATH.'services/NativeCompanyInvoiceDraftHttp.php';
            (new NativeCompanyInvoiceDraftHttp($this))->index();
            return;
        }
        show_404();
    }

    public function get_merge_data($id)
    {
        if (!\app\services\InvoiceIssuerScope::isLegacyInvoice($this->db, db_prefix() . 'invoices', $id)) { show_404(); }
        $invoice = $this->invoices_model->get($id);
        $cf      = get_custom_fields('items');

        $i = 0;

        foreach ($invoice->items as $item) {
            $invoice->items[$i]['taxname']          = get_invoice_item_taxes($item['id']);
            $invoice->items[$i]['long_description'] = clear_textarea_breaks($item['long_description']);
            $this->db->where('item_id', $item['id']);
            $rel              = $this->db->get(db_prefix() . 'related_items')->result_array();
            $item_related_val = '';
            $rel_type         = '';
            foreach ($rel as $item_related) {
                $rel_type = $item_related['rel_type'];
                $item_related_val .= $item_related['rel_id'] . ',';
            }
            if ($item_related_val != '') {
                $item_related_val = substr($item_related_val, 0, -1);
            }
            $invoice->items[$i]['item_related_formatted_for_input'] = $item_related_val;
            $invoice->items[$i]['rel_type']                         = $rel_type;

            $invoice->items[$i]['custom_fields'] = [];

            foreach ($cf as $custom_field) {
                $custom_field['value']                 = get_custom_field_value($item['id'], $custom_field['id'], 'items');
                $invoice->items[$i]['custom_fields'][] = $custom_field;
            }
            $i++;
        }
        echo json_encode($invoice);
    }

    public function get_bill_expense_data($id)
    {
        $this->load->model('expenses_model');
        $expense = $this->expenses_model->get($id);

        $expense->qty              = 1;
        $expense->long_description = clear_textarea_breaks($expense->description);
        $expense->description      = $expense->name;
        $expense->rate             = $expense->amount;
        if ($expense->tax != 0) {
            $expense->taxname = [];
            array_push($expense->taxname, $expense->tax_name . '|' . $expense->taxrate);
        }
        if ($expense->tax2 != 0) {
            array_push($expense->taxname, $expense->tax_name2 . '|' . $expense->taxrate2);
        }
        echo json_encode($expense);
    }

    /* Add new invoice or update existing */
    public function invoice($id = '')
    {
        if (CompanyRequestScope::nativeFor($this)) {
            $this->output->set_header('Cache-Control: no-store');
            try {
                require_once APPPATH.'services/NativeCompanyInvoiceForm.php';
                $context=CompanyRequestScope::context();$form=new NativeCompanyInvoiceForm($this->db,db_prefix());
                $method=$this->input->method(true);
                if ($method==='POST') {
                    CompanyRequestScope::assertNativeMutation($this);
                    $input=$this->input->post(null,false)?:[];unset($input['_company_context'],$input['_native_company_csrf']);
                    if(isset($this->security))unset($input[$this->security->get_csrf_token_name()]);
                    $result=$id===''?$form->create($context->issuerId(),$context->staffId(),$input):$form->update($context->issuerId(),$context->staffId(),$id,$input);
                    if($result['action']==='save_and_record_payment')$this->session->set_userdata('record_payment',true);
                    set_alert('success',_l($id===''?'added_successfully':'updated_successfully',_l('invoice')));
                    redirect(admin_url('invoices/list_invoices/'.$result['result']['canonical_id']));
                } elseif (in_array($method,['GET','HEAD'],true)) {
                    $query=$this->input->get(null,false)?:[];
                    if(array_diff(array_keys($query),['customer_id']))throw new DomainException('native_invoice_form_invalid');
                    $data=$id===''?$form->createData($context->issuerId(),$context->staffId(),$query['customer_id']??null):$form->editData($context->issuerId(),$context->staffId(),$id);
                    if($method==='HEAD'){$this->output->set_output('');return;}
                    $this->load->view('admin/invoices/invoice',$data);
                } else { show_error('Método não permitido.',405); }
            } catch (DomainException $error) {
                $key=$error->getMessage();$status=str_contains($key,'stale')||str_contains($key,'conflict')?409:(str_starts_with($key,'native_invoice_form_')?422:403);
                show_error('Não foi possível confirmar esta operação da fatura nesta empresa.',$status);
            } catch (Throwable $error) {  show_error('Não foi possível confirmar a fatura.',503); }
            return;
        }
        if ($this->input->post()) {
            $invoice_data = $this->input->post();
            if ($id == '') {
                if (staff_cant('create', 'invoices')) {
                    access_denied('invoices');
                }

                if (hooks()->apply_filters('validate_invoice_number', true)) {
                    $number = ltrim($invoice_data['number'], '0');
                    if (total_rows('invoices', [
                        'YEAR(date)' => (int) date('Y', strtotime(to_sql_date($invoice_data['date']))),
                        'number'     => $number,
                        'status !='  => Invoices_model::STATUS_DRAFT,
                    ])) {
                        set_alert('warning', _l('invoice_number_exists'));

                        redirect(admin_url('invoices/invoice'));
                    }
                }

                $id = $this->invoices_model->add($invoice_data);
                if ($id) {
                    set_alert('success', _l('added_successfully', _l('invoice')));
                    $redUrl = admin_url('invoices/list_invoices/' . $id);

                    if (isset($invoice_data['save_and_record_payment'])) {
                        $this->session->set_userdata('record_payment', true);
                    } elseif (isset($invoice_data['save_and_send_later'])) {
                        $this->session->set_userdata('send_later', true);
                    }

                    redirect($redUrl);
                }
            } else {
                if (staff_cant('edit', 'invoices')) {
                    access_denied('invoices');
                }

                // If number not set, is draft
                if (hooks()->apply_filters('validate_invoice_number', true) && isset($invoice_data['number'])) {
                    $number = trim(ltrim($invoice_data['number'], '0'));
                    if (total_rows('invoices', [
                        'YEAR(date)' => (int) date('Y', strtotime(to_sql_date($invoice_data['date']))),
                        'number'     => $number,
                        'status !='  => Invoices_model::STATUS_DRAFT,
                        'id !='      => $id,
                    ])) {
                        set_alert('warning', _l('invoice_number_exists'));

                        redirect(admin_url('invoices/invoice/' . $id));
                    }
                }
                $success = $this->invoices_model->update($invoice_data, $id);
                if ($success) {
                    set_alert('success', _l('updated_successfully', _l('invoice')));
                }

                redirect(admin_url('invoices/list_invoices/' . $id));
            }
        }
        if ($id == '') {
            $title                  = _l('create_new_invoice');
            $data['billable_tasks'] = [];
        } else {
            $invoice = $this->invoices_model->get($id);

            if (!$invoice || !user_can_view_invoice($id)) {
                blank_page(_l('invoice_not_found'));
            }

            $data['invoices_to_merge'] = $this->invoices_model->check_for_merge_invoice($invoice->clientid, $invoice->id);
            $data['expenses_to_bill']  = $this->invoices_model->get_expenses_to_bill($invoice->clientid);

            $data['invoice']        = $invoice;
            $data['edit']           = true;
            $data['billable_tasks'] = $this->tasks_model->get_billable_tasks($invoice->clientid, !empty($invoice->project_id) ? $invoice->project_id : '');

            $title = _l('edit', _l('invoice')) . ' - ' . format_invoice_number($invoice->id);
        }

        if ($this->input->get('customer_id')) {
            $data['customer_id'] = $this->input->get('customer_id');
        }

        $this->load->model('payment_modes_model');
        $data['payment_modes'] = $this->payment_modes_model->get('', [
            'expenses_only !=' => 1,
        ]);

        $this->load->model('taxes_model');
        $data['taxes'] = $this->taxes_model->get();
        $this->load->model('invoice_items_model');

        $data['ajaxItems'] = false;
        if (total_rows(db_prefix() . 'items') <= ajax_on_total_items()) {
            $data['items'] = $this->invoice_items_model->get_grouped();
        } else {
            $data['items']     = [];
            $data['ajaxItems'] = true;
        }
        $data['items_groups'] = $this->invoice_items_model->get_groups();

        $this->load->model('currencies_model');
        $data['currencies'] = $this->currencies_model->get();

        $data['base_currency'] = $this->currencies_model->get_base_currency();

        $data['staff']     = $this->staff_model->get('', ['active' => 1]);
        $data['title']     = $title;
        $data['bodyclass'] = 'invoice';
        $this->load->view('admin/invoices/invoice', $data);
    }

    /* Get all invoice data used when user click on invoiec number in a datatable left side*/
    public function get_invoice_data_ajax($id)
    {
        if (CompanyRequestScope::nativeFor($this)) {
            $this->output->set_header('Cache-Control: no-store');
            try {
                if(!in_array(strtoupper($this->input->method(true)),['GET','HEAD'],true))throw new DomainException('native_invoice_preview_method_denied');
                require_once APPPATH.'services/NativeCompanyInvoicePreviewData.php';
                $context=CompanyRequestScope::context();
                $data=(new NativeCompanyInvoicePreviewData($this->db,db_prefix()))->presentation($context->issuerId(),$context->staffId(),$id);
                $data['native_invoice_reminder_sources']=(new NativeCompanyInvoiceCommunicationState($this->db,db_prefix()))->reminderSources($context->issuerId(),$context->staffId());
                $acl=new IssuerResourceAccess($this->db,db_prefix());
                if($acl->can($context->issuerId(),$context->staffId(),'invoices','edit')&&$acl->can($context->issuerId(),$context->staffId(),'customers','view')){
                    require_once APPPATH.'services/CompanyInvoiceDelivery.php';
                    $data['native_invoice_delivery']=(new CompanyInvoiceDelivery($this->db,db_prefix()))->state($context->issuerId(),$context->staffId(),$id);
                    $data['native_invoice_delivery_key']=bin2hex(random_bytes(32));
                }
                $data+=['invoices_to_merge'=>[],'credits_available'=>0,'open_credits'=>[],'customer_currency'=>(object)['name'=>$data['invoice']->currency_name],
                    'invoice_recurring_invoices'=>[],'members'=>[],'activity'=>[],'record_payment'=>false,'send_later'=>false];
                if($this->session->has_userdata('record_payment')){$data['record_payment']=true;$this->session->unset_userdata('record_payment');}
                elseif($this->session->has_userdata('send_later')){$this->session->unset_userdata('send_later');}
                if(strtoupper($this->input->method(true))==='HEAD'){$this->output->set_output('');return;}
                $this->load->view('admin/invoices/invoice_preview_template',$data);
            } catch (DomainException $error) {
                $reason=$error->getMessage();$status=$reason==='native_invoice_preview_method_denied'?405:(str_contains($reason,'changed')||str_contains($reason,'stale')?409:403);
                show_error('Fatura indisponível ou solicitação não autorizada nesta empresa.',$status);
            } catch (Throwable $error) {  show_error('Não foi possível confirmar a visualização da fatura.',503); }
            return;
        }
        if (staff_cant('view', 'invoices')
            && staff_cant('view_own', 'invoices')
            && get_option('allow_staff_view_invoices_assigned') == '0') {
            echo _l('access_denied');
            die;
        }

        if (!$id) {
            die(_l('invoice_not_found'));
        }

        $invoice = $this->invoices_model->get($id);

        if (!$invoice || !user_can_view_invoice($id)) {
            echo _l('invoice_not_found');
            die;
        }

        $template_name = 'invoice_send_to_customer';

        if ($invoice->sent == 1) {
            $template_name = 'invoice_send_to_customer_already_sent';
        }

        $data = prepare_mail_preview_data($template_name, $invoice->clientid);

        // Check for recorded payments
        $this->load->model('payments_model');
        $data['invoices_to_merge']          = $this->invoices_model->check_for_merge_invoice($invoice->clientid, $id);
        $data['members']                    = $this->staff_model->get('', ['active' => 1]);
        $data['payments']                   = $this->payments_model->get_invoice_payments($id);
        $data['activity']                   = $this->invoices_model->get_invoice_activity($id);
        $data['totalNotes']                 = total_rows(db_prefix() . 'notes', ['rel_id' => $id, 'rel_type' => 'invoice']);
        $data['invoice_recurring_invoices'] = $this->invoices_model->get_invoice_recurring_invoices($id);

        $data['applied_credits'] = $this->credit_notes_model->get_applied_invoice_credits($id);
        // This data is used only when credit can be applied to invoice
        if (credits_can_be_applied_to_invoice($invoice->status)) {
            $data['credits_available'] = $this->credit_notes_model->total_remaining_credits_by_customer($invoice->clientid);

            if ($data['credits_available'] > 0) {
                $data['open_credits'] = $this->credit_notes_model->get_open_credits($invoice->clientid);
            }

            $customer_currency = $this->clients_model->get_customer_default_currency($invoice->clientid);
            $this->load->model('currencies_model');

            if ($customer_currency != 0) {
                $data['customer_currency'] = $this->currencies_model->get($customer_currency);
            } else {
                $data['customer_currency'] = $this->currencies_model->get_base_currency();
            }
        }

        $data['invoice'] = $invoice;

        $data['record_payment'] = false;
        $data['send_later']     = false;

        if ($this->session->has_userdata('record_payment')) {
            $data['record_payment'] = true;
            $this->session->unset_userdata('record_payment');
        } elseif ($this->session->has_userdata('send_later')) {
            $data['send_later'] = true;
            $this->session->unset_userdata('send_later');
        }

        $this->load->view('admin/invoices/invoice_preview_template', $data);
    }

    public function apply_credits($invoice_id)
    {
        if($this->nativeCompanyInvoices()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'invoices','apply_credits',[$invoice_id]);return;}
        if (!\app\services\InvoiceIssuerScope::isLegacyInvoice($this->db, db_prefix() . 'invoices', $invoice_id)) { show_404(); }
        $total_credits_applied = 0;
        foreach ($this->input->post('amount') as $credit_id => $amount) {
            $success = $this->credit_notes_model->apply_credits($credit_id, [
            'invoice_id' => $invoice_id,
            'amount'     => $amount,
        ]);
            if ($success) {
                $total_credits_applied++;
            }
        }

        if ($total_credits_applied > 0) {
            update_invoice_status($invoice_id, true);
            set_alert('success', _l('invoice_credits_applied'));
        }
        redirect(admin_url('invoices/list_invoices/' . $invoice_id));
    }

    public function get_invoices_total()
    {
        if (CompanyRequestScope::nativeFor($this)) {
            $this->output->set_header('Cache-Control: no-store');
            try {
                CompanyRequestScope::assertNativeMutation($this);
                require_once APPPATH.'services/NativeCompanyInvoiceTotals.php';
                require_once APPPATH.'services/NativeCompanyInvoiceRead.php';
                $input=$this->input->post(null,false)?:[];
                // The native CSRF/context pair has already been captured and checked by the boundary.
                unset($input['_company_context'],$input['_native_company_csrf']);
                if(isset($this->security))unset($input[$this->security->get_csrf_token_name()]);
                $request=NativeCompanyInvoiceTotals::request($input);
                $context=CompanyRequestScope::context();
                $data=(new NativeCompanyInvoiceRead($this->db,db_prefix()))->summary(
                    $context->issuerId(),$context->staffId(),$request['currency'],$request['years'],$request['scope']
                );
                $data['_currency']=$data['total_result']['currencyid'];
                if(count($data['invoices_total_currencies'])<=1)unset($data['invoices_total_currencies']);
                if($data['invoices_years']&&!in_array((int)date('Y'),array_column($data['invoices_years'],'year'),true))
                    array_unshift($data['invoices_years'],['year'=>(int)date('Y')]);
                $this->load->view('admin/invoices/invoices_total_template',$data);
            } catch (DomainException $error) {
                $status=$error->getMessage()==='native_company_invoice_query_invalid'?422:
                    ($error->getMessage()==='native_company_invoice_read_changed'?409:403);
                show_error('Totais indisponíveis ou solicitação inválida nesta empresa.',$status);
            } catch (Throwable $error) {
                show_error('Não foi possível confirmar os totais das faturas.',503);
            }
            return;
        }
        if ($this->input->post()) {
            load_invoices_total_template();
        }
    }

    /* Record new inoice payment view */
    public function record_invoice_payment_ajax($id)
    {
        if (CompanyRequestScope::nativeFor($this)) {
            $this->output->set_header('Cache-Control: no-store');
            try {
                if(!in_array(strtoupper($this->input->method(true)),['GET','HEAD'],true))throw new DomainException('company_payment_invalid_input');
                require_once APPPATH.'services/NativeCompanyInvoicePaymentForm.php';require_once APPPATH.'services/CompanyEmailTemplates.php';
                $context=CompanyRequestScope::context();$data=(new NativeCompanyInvoicePaymentForm($this->db,db_prefix()))->read($context->issuerId(),$context->staffId(),$id);
                $payments=[];foreach($data['payments'] as $p)$payments[]=['paymentid'=>$p['id'],'invoiceid'=>$data['invoice']['invoice_id'],'name'=>$p['paymentmethod']?:$p['paymentmode'],'paymentmethod'=>'','transactionid'=>$p['transactionid'],'date'=>$p['date'],'amount'=>$p['effective_amount']];
                $invoice=(object)['id'=>$data['invoice']['invoice_id'],'allowed_payment_modes'=>array_column($data['payment_modes'],'id'),'currency_name'=>$data['invoice']['currency'],'total_left_to_pay'=>$data['balance']['receivable'],'payments'=>$payments];
                $template=CompanyEmailTemplates::findForIssuer($this->db,db_prefix(),$context->issuerId(),'invoice-payment-recorded','portuguese_br');
                $this->load->view('admin/invoices/record_payment_template',['invoice'=>$invoice,'payments'=>$payments,'payment_modes'=>$data['payment_modes'],'native_payment_form_data'=>$data,'native_payment_email_enabled'=>$template&&(int)$template->active===1]);
            }catch(DomainException $error){show_error('Pagamento indisponível ou não autorizado nesta empresa.',403);}
            catch(Throwable $error){show_error('Não foi possível confirmar o formulário de pagamento.',503);}
            return;
        }
        if (!\app\services\InvoiceIssuerScope::isLegacyInvoice($this->db, db_prefix() . 'invoices', $id)) { show_404(); }
        $this->load->model('payment_modes_model');
        $this->load->model('payments_model');
        $data['payment_modes'] = $this->payment_modes_model->get('', [
            'expenses_only !=' => 1,
        ]);
        $data['invoice']  = $this->invoices_model->get($id);
        $data['payments'] = $this->payments_model->get_invoice_payments($id);
        $this->load->view('admin/invoices/record_payment_template', $data);
    }

    /* This is where invoice payment record $_POST data is send */
    public function record_payment()
    {
        if (CompanyRequestScope::nativeFor($this)) {
            $this->output->set_header('Cache-Control: no-store');
            try {
                CompanyRequestScope::assertNativeMutation($this);
                require_once APPPATH.'services/NativeCompanyInvoicePaymentAction.php';
                $input=$this->input->post(null,false)?:[];unset($input['_company_context'],$input['_native_company_csrf']);
                if(isset($this->security))unset($input[$this->security->get_csrf_token_name()]);
                $context=CompanyRequestScope::context();$this->load->library('encryption');
                $result=(new NativeCompanyInvoicePaymentAction($this->db,db_prefix()))->submit($context->issuerId(),$context->staffId(),$input,$this->config->base_url(),fn($value)=>$this->encryption->decrypt($value));
                set_alert('success',_l('invoice_payment_recorded'));
                if($result['email_status']==='pending')set_alert('warning','Pagamento registrado. Uma ou mais notificações por e-mail requerem revisão; outras podem já estar na fila.');
                if($result['sms_status']==='unavailable')set_alert('warning','Pagamento registrado. SMS indisponível para esta empresa pela política atual de provedores.');
                redirect(admin_url('invoices/list_invoices/'.$result['invoice_id']));
            }catch(DomainException $error){$reason=$error->getMessage();$status=str_contains($reason,'csrf')||str_contains($reason,'context')?403:(str_contains($reason,'stale')||str_contains($reason,'conflict')?409:(str_contains($reason,'invalid')?422:403));show_error('Pagamento indisponível ou dados desatualizados nesta empresa.',$status);}
            catch(Throwable $error){show_error('Não foi possível confirmar o pagamento.',503);}
            return;
        }
        if (staff_cant('create', 'payments')) {
            access_denied('Record Payment');
        }
        if ($this->input->post()) {
            $this->load->model('payments_model');
            $id = $this->payments_model->process_payment($this->input->post(), '');
            if ($id) {
                set_alert('success', _l('invoice_payment_recorded'));
                redirect(admin_url('payments/payment/' . $id));
            } else {
                set_alert('danger', _l('invoice_payment_record_failed'));
            }
            redirect(admin_url('invoices/list_invoices/' . $this->input->post('invoiceid')));
        }
    }

    /* Send invoice to email */
    public function send_to_email($id)
    {
        if(CompanyRequestScope::nativeFor($this)){
            $this->output->set_header('Cache-Control: no-store');
            try{
                CompanyRequestScope::assertNativeMutation($this);require_once APPPATH.'services/CompanyInvoiceDelivery.php';
                $context=CompanyRequestScope::context();$input=$this->input->post(null,false)?:[];unset($input['_company_context'],$input['_native_company_csrf']);if(isset($this->security))unset($input[$this->security->get_csrf_token_name()]);
                if(array_diff(array_keys($input),['sent_to','_native_invoice_revision','request_key'])||!is_array($input['sent_to']??null)||!array_is_list($input['sent_to'])||count($input['sent_to'])!==1||!is_string($input['_native_invoice_revision']??null)||!is_string($input['request_key']??null))throw new DomainException('company_invoice_delivery_invalid');
                $this->load->library('encryption');
                (new CompanyInvoiceDelivery($this->db,db_prefix()))->queue($context->issuerId(),$context->staffId(),$id,$input['sent_to'][0],$input['_native_invoice_revision'],$input['request_key'],$this->config->base_url(),fn($value)=>$this->encryption->decrypt($value));
                set_alert('success','Notificação registrada na fila de envio.');redirect(admin_url('invoices/list_invoices/'.$id));
            }catch(DomainException $error){$reason=$error->getMessage();show_error('Não foi possível registrar a notificação desta empresa.',str_contains($reason,'stale')||str_contains($reason,'conflict')?409:(str_contains($reason,'invalid')?422:403));}
            catch(Throwable $error){show_error('Não foi possível confirmar a fila de envio.',503);}return;
        }
        $canView = user_can_view_invoice($id);
        if (!$canView) {
            access_denied('Invoices');
        } else {
            if (staff_cant('view', 'invoices') && staff_cant('view_own', 'invoices') && $canView == false) {
                access_denied('Invoices');
            }
        }

        try {
            $statementData = [];
            if ($this->input->post('attach_statement')) {
                $statementData['attach'] = true;
                $statementData['from']   = to_sql_date($this->input->post('statement_from'));
                $statementData['to']     = to_sql_date($this->input->post('statement_to'));
            }

            $success = $this->invoices_model->send_invoice_to_client(
                $id,
                '',
                $this->input->post('attach_pdf'),
                $this->input->post('cc'),
                false,
                $statementData
            );
        } catch (Exception $e) {
            $message = $e->getMessage();
            echo $message;
            if (strpos($message, 'Unable to get the size of the image') !== false) {
                show_pdf_unable_to_get_image_size_error();
            }
            die;
        }

        // In case client use another language
        load_admin_language();
        if ($success) {
            set_alert('success', _l('invoice_sent_to_client_success'));
        } else {
            set_alert('danger', _l('invoice_sent_to_client_fail'));
        }
        redirect(admin_url('invoices/list_invoices/' . $id));
    }

    /* Delete invoice payment*/
    public function delete_payment($id, $invoiceid)
    {
        if(CompanyRequestScope::nativeFor($this)){
            try{
                CompanyRequestScope::assertNativeMutation($this);
                $input=$this->input->post(null,false)?:[];unset($input['_company_context'],$input['_native_company_csrf']);
                if(isset($this->security))unset($input[$this->security->get_csrf_token_name()]);
                require_once APPPATH.'services/NativeCompanyInvoicePaymentReversal.php';$context=CompanyRequestScope::context();
                (new NativeCompanyInvoicePaymentReversal($this->db,db_prefix()))->submit($context->issuerId(),$context->staffId(),$id,$invoiceid,$input);
                CompanyRequestScope::invalidateCaches($this);
                redirect(admin_url('invoices/list_invoices/'.IssuerPolicy::id($invoiceid)),'location',303);
            }catch(DomainException $error){$key=$error->getMessage();show_error('Estorno não confirmado. Confira o recebimento, a revisão e as permissões.',str_contains($key,'stale')||str_contains($key,'conflict')?409:(str_contains($key,'invalid')?422:403));}
            catch(Throwable $error){show_error('Não foi possível confirmar o estorno.',503);}
            return;
        }
        if (staff_cant('delete', 'payments')) {
            access_denied('payments');
        }
        $this->load->model('payments_model');
        if (!$id) {
            redirect(admin_url('payments'));
        }
        $response = $this->payments_model->delete($id);
        if ($response == true) {
            set_alert('success', _l('deleted', _l('payment')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('payment_lowercase')));
        }
        redirect(admin_url('invoices/list_invoices/' . $invoiceid));
    }

    /* Delete invoice */
    public function delete($id)
    {
        if($this->nativeCompanyInvoices()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'invoices','delete',[$id]);return;}
        if (staff_cant('delete', 'invoices')) {
            access_denied('invoices');
        }
        if (!$id) {
            redirect(admin_url('invoices/list_invoices'));
        }
        $success = $this->invoices_model->delete($id);

        if ($success) {
            set_alert('success', _l('deleted', _l('invoice')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('invoice_lowercase')));
        }
        redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
    }

    public function delete_attachment($id)
    {
        if (CompanyRequestScope::nativeFor($this)) {
            $this->output->set_header('Cache-Control: no-store');
            try {
                CompanyRequestScope::assertNativeMutation($this);
                require_once APPPATH.'services/NativeCompanyInvoiceFiles.php';
                $input=$this->input->post(null,false)?:[];unset($input['_company_context'],$input['_native_company_csrf']);
                if(isset($this->security))unset($input[$this->security->get_csrf_token_name()]);
                if(array_keys($input)!==['revision'])throw new DomainException('native_invoice_file_invalid');
                $revision=IssuerPolicy::id($input['revision']);$file=IssuerPolicy::id($id);$context=CompanyRequestScope::context();
                (new NativeCompanyInvoiceFiles($this->db,db_prefix()))->remove($context->issuerId(),$context->staffId(),$file,$revision);
                $this->output->set_content_type('application/json')->set_output('true');
            }catch(DomainException$error){$reason=$error->getMessage();show_error('Anexo indisponível ou solicitação não autorizada nesta empresa.',str_contains($reason,'csrf')||str_contains($reason,'context')?403:(str_contains($reason,'stale')||str_contains($reason,'conflict')?409:(str_contains($reason,'invalid')?422:403)));}
            catch(Throwable$error){show_error('Não foi possível confirmar a remoção do anexo.',503);}
            return;
        }
        $file = $this->misc_model->get_file($id);
        if ($file->staffid == get_staff_user_id() || is_admin()) {
            echo $this->invoices_model->delete_attachment($id);
        } else {
            header('HTTP/1.0 400 Bad error');
            echo _l('access_denied');
            die;
        }
    }

    /* Will send overdue notice to client */
    public function send_overdue_notice($id)
    {
        if($this->nativeCompanyInvoices()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'invoices','send_overdue_notice',[$id]);return;}
        $canView = user_can_view_invoice($id);
        if (!$canView) {
            access_denied('Invoices');
        } else {
            if (staff_cant('view', 'invoices') && staff_cant('view_own', 'invoices') && $canView == false) {
                access_denied('Invoices');
            }
        }

        $send = $this->invoices_model->send_invoice_overdue_notice($id);
        if ($send) {
            set_alert('success', _l('invoice_overdue_reminder_sent'));
        } else {
            set_alert('warning', _l('invoice_reminder_send_problem'));
        }
        redirect(admin_url('invoices/list_invoices/' . $id));
    }

    /* Generates invoice PDF and senting to email of $send_to_email = true is passed */
    public function pdf($id)
    {
        if (CompanyRequestScope::nativeFor($this)) {
            $this->companyPdf($id);
            return;
        }
        if (!$id) {
            redirect(admin_url('invoices/list_invoices'));
        }

        $canView = user_can_view_invoice($id);
        if (!$canView) {
            access_denied('Invoices');
        } else {
            if (staff_cant('view', 'invoices') && staff_cant('view_own', 'invoices') && $canView == false) {
                access_denied('Invoices');
            }
        }

        $invoice        = $this->invoices_model->get($id);
        $invoice        = hooks()->apply_filters('before_admin_view_invoice_pdf', $invoice);
        $invoice_number = format_invoice_number($invoice->id);

        try {
            $pdf = invoice_pdf($invoice);
        } catch (Exception $e) {
            $message = $e->getMessage();
            echo $message;
            if (strpos($message, 'Unable to get the size of the image') !== false) {
                show_pdf_unable_to_get_image_size_error();
            }
            die;
        }

        $type = 'D';

        if ($this->input->get('output_type')) {
            $type = $this->input->get('output_type');
        }

        if ($this->input->get('print')) {
            $type = 'I';
        }

        $pdf->Output(mb_strtoupper(slug_it($invoice_number)) . '.pdf', $type);
    }

    private function companyPdf($id): void
    {
        require_once APPPATH.'services/NativeCompanyInvoicePdf.php';
        $context = CompanyRequestScope::context();
        try {
            $response = (new NativeCompanyInvoicePdf($this->db, db_prefix()))->response(
                $context->issuerId(), $context->staffId(), $id,
                $this->input->method(true), $this->input->get(null, false) ?: []
            );
            foreach ($response['headers'] as $header) { $this->output->set_header($header); }
            $this->output->set_content_type($response['content_type'])->set_output($response['body']);
        } catch (DomainException $error) {
            $status = $error->getMessage() === 'native_company_invoice_method_denied' ? 405
                : ($error->getMessage() === 'native_company_invoice_pdf_input' ? 422 : 403);
            $this->output->set_header('Cache-Control: no-store');
            show_error('Fatura indisponível ou solicitação não autorizada nesta empresa.', $status);
        } catch (Throwable $error) {
            $this->output->set_header('Cache-Control: no-store');
            show_error('Não foi possível preparar o documento. Tente novamente.', 503);
        }
    }

    public function mark_as_sent($id)
    {
        if (CompanyRequestScope::nativeFor($this)) { $this->nativeCommunicationCommand($id, 'mark_as_sent'); return; }
        if (!$id) {
            redirect(admin_url('invoices/list_invoices'));
        }
        if (!user_can_view_invoice($id)) {
            access_denied('Invoice Mark As Sent');
        }

        $success = $this->invoices_model->set_invoice_sent($id, true);

        if ($success) {
            set_alert('success', _l('invoice_marked_as_sent'));
        } else {
            set_alert('warning', _l('invoice_marked_as_sent_failed'));
        }

        redirect(admin_url('invoices/list_invoices/' . $id));
    }

    public function get_due_date()
    {
        if (CompanyRequestScope::nativeFor($this)) {
            $this->output->set_header('Cache-Control: no-store');
            try {
                CompanyRequestScope::assertNativeMutation($this);
                require_once APPPATH.'services/NativeCompanyInvoiceDates.php';
                $context=CompanyRequestScope::context();
                $date=(new NativeCompanyInvoiceDates($this->db,db_prefix()))->dueDate(
                    $context->issuerId(),$context->staffId(),$this->input->post('date',false)
                );
                $this->output->set_content_type('text/plain')->set_output($date);
            } catch (DomainException $error) {
                show_error('Data inválida ou operação não autorizada nesta empresa.',
                    $error->getMessage()==='native_company_invoice_date_invalid'?422:403);
            } catch (Throwable $error) {
                show_error('Não foi possível calcular o vencimento.',503);
            }
            return;
        }
        if ($this->input->post()) {
            $date    = $this->input->post('date');
            $duedate = '';
            if (get_option('invoice_due_after') != 0) {
                $date    = to_sql_date($date);
                $d       = date('Y-m-d', strtotime('+' . get_option('invoice_due_after') . ' DAY', strtotime($date)));
                $duedate = _d($d);
                echo $duedate;
            }
        }
    }
}
