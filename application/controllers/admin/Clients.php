<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Clients extends AdminController
{
    /** Server-attested native company route; no request parameter can select this adapter. */
    private function nativeCompanyClients(): ?NativeCompanyClients
    {
        if (!class_exists('CompanyRequestScope', false) || !method_exists('CompanyRequestScope', 'nativeFor')
            || !CompanyRequestScope::nativeFor($this)) return null;
        $context = CompanyRequestScope::context();
        if (!$context) return null;
        // The principal company uses the same scoped adapter as every named
        // company. NativeCompanyClients preserves the historical principal
        // lineage through its source-aware readers, so falling back to
        // clients_model here would recreate the second application.
        require_once APPPATH.'services/NativeCompanyClients.php';
        return new NativeCompanyClients($this->db, db_prefix(), $context->issuerId(), $context->staffId());
    }

    private function nativeCompanyClientFailure(Throwable $error): void
    {
        $reason = $error instanceof DomainException ? $error->getMessage() : '';
        $status = $reason === 'company_client_access_denied' ? 403 : ($reason === 'company_client_stale' ? 409 : ($reason !== '' ? 422 : 503));
        show_error($status === 409 ? 'Este cadastro mudou. Reabra antes de salvar.' : 'Não foi possível concluir esta operação nesta empresa.', $status);
    }

    /** Original client tabs backed by issuer-owned relation readers. */
    private function nativeClientSectionData(int $issuer,int $actor,int $client,string $group):array
    {
        require_once APPPATH.'services/issuers/IssuerStorage.php';
        $p=db_prefix();$acl=new IssuerResourceAccess($this->db,$p);$e=static fn($sql,$args=[])=>IssuerStorage::query($this->db,$sql,$args)->result_array();
        if($group==='notes'){require_once APPPATH.'services/NativeCompanyAuxiliaryUi.php';return['view'=>'admin/clients/groups/native_notes','user_notes'=>(new NativeCompanyAuxiliaryUi($this->db,$p,$issuer,$actor,$this))->notes('client',$client)];}
        if($group==='statement'){
            $from=$this->input->get('from',false);$to=$this->input->get('to',false);$from=is_string($from)&&$from!==''?$from:'2000-01-01';$to=is_string($to)&&$to!==''?$to:'2100-12-31';
            foreach([$from,$to] as $date)if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)||!DateTimeImmutable::createFromFormat('!Y-m-d',$date)||DateTimeImmutable::createFromFormat('!Y-m-d',$date)->format('Y-m-d')!==$date)throw new DomainException('company_client_invalid_input');
            if($from>$to)throw new DomainException('company_client_invalid_input');
            return['view'=>'admin/clients/groups/native_statement','native_statement'=>['from'=>$from,'to'=>$to,'rows'=>$e('SELECT id,formatted_number,date,duedate,total,status,currency FROM '.$p.'invoices WHERE issuer_id=? AND clientid=? AND date BETWEEN ? AND ? ORDER BY date,id LIMIT 500',[$issuer,$client,$from,$to]),'contacts'=>$e('SELECT id,firstname,lastname,email FROM '.$p.'contacts WHERE userid=? AND active=1 ORDER BY id',[$client])]];
        }
        if($group==='payments'){
            require_once APPPATH.'services/NativeCompanyPaymentRecords.php';
            $pageValue=$this->input->get('page',false);$page=is_string($pageValue)&&preg_match('/^[1-9][0-9]{0,5}$/D',$pageValue)?(int)$pageValue:1;$length=25;$search=$this->input->get('search',false);$search=is_string($search)?trim($search):'';if(strlen($search)>200)throw new DomainException('company_client_invalid_input');
            $result=(new NativeCompanyPaymentRecords($this->db,$p))->listingPage($issuer,$actor,$client,($page-1)*$length,$length,$search,[['field'=>'id','dir'=>'desc']]);
            return['view'=>'admin/clients/groups/native_payments','native_payments_page'=>$result,'native_payments_page_no'=>$page,'native_payments_length'=>$length,'native_payments_search'=>$search];
        }
        $map=['expenses'=>['title'=>'Despesas','cap'=>'expenses','columns'=>['id','number','expense_date','category_name','description','currency','total_cents'],'sql'=>'SELECT e.id,e.number,e.expense_date,c.name category_name,e.description,e.currency,e.total_cents FROM '.$p.'company_expenses e JOIN '.$p.'company_expense_categories c ON c.id=e.category_id AND c.issuer_id=e.issuer_id WHERE e.issuer_id=? AND e.client_id=? ORDER BY e.id DESC LIMIT 500','args'=>[$issuer,$client]],'contracts'=>['title'=>'Contratos','cap'=>'contracts','columns'=>['id','number','name','status','start_date','due_date'],'sql'=>'SELECT id,number,name,status,start_date,due_date FROM '.$p.'company_contracts WHERE issuer_id=? AND client_id=? ORDER BY id DESC LIMIT 200','args'=>[$issuer,$client]],'tasks'=>['title'=>'Tarefas','cap'=>'tasks','columns'=>['id','name','status','due_date','project_id'],'sql'=>'SELECT t.id,t.name,t.status,t.due_date,t.project_id FROM '.$p.'company_tasks t JOIN '.$p.'company_projects p ON p.id=t.project_id AND p.issuer_id=t.issuer_id WHERE t.issuer_id=? AND p.client_id=? ORDER BY t.id DESC LIMIT 500','args'=>[$issuer,$client]],'estimates'=>['title'=>'Orçamentos','cap'=>'estimates','columns'=>['id','number','state','total','created_at'],'sql'=>'SELECT id,number,state,total,created_at FROM '.$p.'company_sales_documents WHERE issuer_id=? AND client_id=? AND kind=? ORDER BY id DESC LIMIT 200','args'=>[$issuer,$client,'estimates']], 'proposals'=>['title'=>'Propostas','cap'=>'proposals','columns'=>['id','number','state','total','created_at'],'sql'=>'SELECT id,number,state,total,created_at FROM '.$p.'company_sales_documents WHERE issuer_id=? AND client_id=? AND kind=? ORDER BY id DESC LIMIT 200','args'=>[$issuer,$client,'proposals']], 'credit_notes'=>['title'=>'Notas de crédito','cap'=>'credit_notes','columns'=>['id','formatted_number','date','total','status'],'sql'=>'SELECT id,formatted_number,date,total,status FROM '.$p.'creditnotes WHERE issuer_id=? AND clientid=? ORDER BY id DESC LIMIT 200','args'=>[$issuer,$client]], 'subscriptions'=>['title'=>'Assinaturas','cap'=>'subscriptions','columns'=>['id','next_date','state','created_at'],'sql'=>'SELECT id,next_date,state,created_at FROM '.$p.'company_recurring WHERE issuer_id=? AND payload_json LIKE ? ORDER BY id DESC LIMIT 200','args'=>[$issuer,'%"client_id":'.$client.'%']], 'reminders'=>['title'=>'Lembretes','cap'=>'customers','columns'=>['id','description','date','isnotified'],'sql'=>'SELECT id,description,date,isnotified FROM '.$p.'company_reminders WHERE issuer_id=? AND rel_type=? AND rel_id=? ORDER BY date,id DESC LIMIT 200','args'=>[$issuer,'client',$client]]];
        $entry=$map[$group]??null;if(!$entry||!$acl->can($issuer,$actor,$entry['cap'],'view'))throw new DomainException('company_client_access_denied');
        $tableMap=['expenses'=>['company_expenses','company_expense_categories'],'contracts'=>['company_contracts'],'tasks'=>['company_tasks','company_projects'],'estimates'=>['company_sales_documents'],'proposals'=>['company_sales_documents'],'credit_notes'=>['creditnotes'],'subscriptions'=>['company_recurring'],'reminders'=>['company_reminders']];
        foreach($tableMap[$group]??[] as $table)if(!$this->db->table_exists($p.$table))return['view'=>'admin/clients/groups/native_section','native_client_section'=>['title'=>$entry['title'],'columns'=>$entry['columns'],'rows'=>[]]];
        return['view'=>'admin/clients/groups/native_section','native_client_section'=>['title'=>$entry['title'],'columns'=>$entry['columns'],'rows'=>$e($entry['sql'],$entry['args'])]];
    }

    private function nativeCompanyContactFailure(Throwable $error):void
    {
        $reason=$error instanceof DomainException?$error->getMessage():'';$status=$reason==='company_client_access_denied'?403:($reason==='company_client_stale'?409:($reason!==''?422:503));
        $this->output->set_status_header($status)->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode(['success'=>false,'message'=>$status===409?'Este contato mudou. Reabra antes de salvar.':'Não foi possível concluir esta operação nesta empresa.'],JSON_THROW_ON_ERROR));
    }
    public function contact_email_exists():void
    {
        if(!$this->nativeCompanyClients()){show_404();return;}
        try{
            if($this->input->method(true)!=='POST'){show_error('Use POST.',405);return;}CompanyRequestScope::assertNativeMutation($this);
            require_once APPPATH.'services/CompanyNativeContacts.php';$ctx=CompanyRequestScope::context();$client=IssuerPolicy::id($this->input->post('client_id',false));$id=$this->input->post('userid',false);$email=$this->input->post('email',false);if(!is_string($email))throw new DomainException('company_contact_invalid_input');
            $available=(new CompanyNativeContacts($this->db,db_prefix()))->emailAvailable($ctx->issuerId(),$ctx->staffId(),$client,$email,$id===null||$id===''?null:IssuerPolicy::id($id));
            $this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode($available));
        }catch(Throwable $error){$this->nativeCompanyContactFailure($error);}
    }
    public function contact_profile_image($client,$contact):void
    {
        $native=$this->nativeCompanyClients();if(!$native){show_404();return;}
        try{
            if(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Use GET.',405);return;}
            require_once APPPATH.'services/NativeCompanyContactImage.php';$ctx=CompanyRequestScope::context();$image=(new NativeCompanyContactImage($this->db,db_prefix()))->read($ctx->issuerId(),$ctx->staffId(),IssuerPolicy::id($client),IssuerPolicy::id($contact));if(!$image){show_404();return;}
            $this->output->set_header('Cache-Control: private, no-store')->set_header('X-Content-Type-Options: nosniff')->set_header("Content-Security-Policy: default-src 'none'; sandbox")->set_content_type($image['mime'])->set_output($this->input->method(true)==='HEAD'?'':$image['content']);
        }catch(Throwable $error){$this->nativeCompanyContactFailure($error);}
    }

    public function lookup(): void
    {
        $native=$this->nativeCompanyClients();$context=class_exists('CompanyRequestScope',false)&&CompanyRequestScope::nativeFor($this)?CompanyRequestScope::context():null;
        if(!$native&&(!$context||!$context->isPrimary())){show_error('Contexto indisponível.',503);return;}
        $this->output->set_header('Cache-Control: no-store');
        $reply=function(int $status,array $body):void{$body['csrf']=['name'=>$this->security->get_csrf_token_name(),'hash'=>$this->security->get_csrf_hash()];$this->output->set_status_header($status)->set_content_type('application/json')->set_output(json_encode($body));};
        if($this->input->method(true)!=='POST'){$reply(405,['error'=>'Use POST para consultar.']);return;}
        try{CompanyRequestScope::assertNativeMutation($this);}catch(Throwable $error){$reply(403,['error'=>'Recarregue o formulário antes de consultar.']);return;}
        try{
            require_once APPPATH.'services/CompanyClientProfile.php';require_once APPPATH.'services/CompanyRegistryLookup.php';
            $issuer=CompanyRequestScope::context()->issuerId();$actor=CompanyRequestScope::context()->staffId();$id=$this->input->post('client_id',false);
            $allowed=$id===null||$id===''?(new IssuerResourceAccess($this->db,db_prefix()))->can($issuer,$actor,'customers','create'):(new CompanyClientProfile($this->db,db_prefix()))->allowed($issuer,$actor,IssuerPolicy::id($id),'edit');
            if(!$allowed){$reply(403,['error'=>'Sem permissão para consultar neste cadastro.']);return;}
            $last=$this->session->userdata('company_registry_lookup_at');if(is_numeric($last)&&time()-(int)$last<3){$reply(429,['error'=>'Aguarde alguns segundos para consultar novamente.']);return;}
            $kind=$this->input->post('kind',false);$value=$this->input->post('value',false);
            if(!is_string($kind)||!in_array($kind,['cnpj','cep'],true))throw new DomainException('company_client_invalid_input');
            CompanyRegistryLookup::request($kind,$value);$this->session->set_userdata('company_registry_lookup_at',time());
            $result=$this->queryRegistry($kind,$value);
            $mapping=['legal_name'=>'company','company_trade_name'=>'trade_name','invoice_company_address'=>'address','company_address_number'=>'address_number','company_address_complement'=>'address_complement','company_address_district'=>'address_district','invoice_company_city'=>'city','company_state'=>'state','invoice_company_postal_code'=>'zip','invoice_company_phonenumber'=>'phonenumber','invoice_company_country_code'=>'country_code'];
            $fields=[];foreach($mapping as $key=>$target)if(isset($result['fields'][$key]))$fields[$target]=$result['fields'][$key];
            $reply(200,['provider'=>$result['provider'],'fields'=>$fields]);
        }catch(DomainException $error){$reply(422,['error'=>'Confira CNPJ numérico ou CEP. Você pode preencher manualmente.']);}
        catch(Throwable $error){$reply(503,['error'=>'Consulta indisponível. Nenhum campo foi alterado; preencha manualmente ou tente depois.']);}
    }
    protected function queryRegistry(string $kind,string $value):array{return CompanyRegistryLookup::lookup($kind,$value);}

    /** Owner-only identity sharing from the original customer profile. */
    public function share_identity($id): void
    {
        if(!$this->nativeCompanyClients()){show_404();return;}
        if($this->input->method(true)!=='POST'){show_error('Use POST.',405);return;}
        try{
            CompanyRequestScope::assertNativeMutation($this);$ctx=CompanyRequestScope::context();$client=IssuerPolicy::id($id);
            $targets=$this->input->post('targets',false)??[];$revision=$this->input->post('revision',false);
            if(!is_array($targets)||!is_string($revision))throw new DomainException('company_client_invalid_input');
            (new CompanyClients($this->db,db_prefix()))->setSharing($ctx->issuerId(),$ctx->staffId(),$client,$targets,$revision);
            CompanyRequestScope::invalidateCaches($this);set_alert('success',_l('updated_successfully',_l('client')));redirect(admin_url('clients/client/'.$client),'location',303);
        }catch(Throwable$error){$this->nativeCompanyClientFailure($error);}
    }

    /** Explicit contact grant for the current company's invoice portal. */
    public function contact_invoice_access($client,$contact): void
    {
        if(!$this->nativeCompanyClients()){show_404();return;}
        if($this->input->method(true)!=='POST'){show_error('Use POST.',405);return;}
        try{
            CompanyRequestScope::assertNativeMutation($this);$ctx=CompanyRequestScope::context();$client=IssuerPolicy::id($client);$contact=IssuerPolicy::id($contact);
            $revision=$this->input->post('revision',false);$allowed=$this->input->post('allowed',false);
            if(!is_string($revision)||!is_string($allowed))throw new DomainException('company_contact_invalid_input');
            require_once APPPATH.'services/CompanyContactInvoiceAccess.php';(new CompanyContactInvoiceAccess($this->db,db_prefix()))->set($ctx->issuerId(),$ctx->staffId(),$client,$contact,$revision,$allowed);
            CompanyRequestScope::invalidateCaches($this);set_alert('success',_l('updated_successfully',_l('contact')));redirect(admin_url('clients/client/'.$client.'?group=contacts'),'location',303);
        }catch(Throwable$error){$this->nativeCompanyClientFailure($error);}
    }

    /* List all clients */
    public function index()
    {
        if ($native = $this->nativeCompanyClients()) {
            if (!in_array($this->input->method(true), ['GET','HEAD'], true)) { show_error('Use GET.',405); return; }
            try {
                $capabilities=$native->capabilities();
                if(!$capabilities['view']&&!$capabilities['create'])throw new DomainException('company_client_access_denied');
                $data = ['title'=>_l('clients'), 'native_customers'=>true,
                    'native_customer_summary'=>$capabilities['view']?$native->summary():[],
                    'contacts_logged_in_today'=>[], 'groups'=>[], 'native_customer_capabilities'=>$capabilities];
                $this->load->view('admin/clients/manage', $data);
            } catch (Throwable $error) { $this->nativeCompanyClientFailure($error); }
            return;
        }
        if (staff_cant('view', 'customers')) {
            if (!have_assigned_customers() && staff_cant('create', 'customers')) {
                access_denied('customers');
            }
        }

        $this->load->model('contracts_model');
        $data['contract_types'] = $this->contracts_model->get_contract_types();
        $data['groups']         = $this->clients_model->get_groups();
        $data['title']          = _l('clients');

        $this->load->model('proposals_model');
        $data['proposal_statuses'] = $this->proposals_model->get_statuses();

        $this->load->model('invoices_model');
        $data['invoice_statuses'] = $this->invoices_model->get_statuses();

        $this->load->model('estimates_model');
        $data['estimate_statuses'] = $this->estimates_model->get_statuses();

        $this->load->model('projects_model');
        $data['project_statuses'] = $this->projects_model->get_project_statuses();

        $data['customer_admins'] = $this->clients_model->get_customers_admin_unique_ids();

        $whereContactsLoggedIn = '';
        if (staff_cant('view', 'customers')) {
            $whereContactsLoggedIn = ' AND userid IN (SELECT customer_id FROM ' . db_prefix() . 'customer_admins WHERE staff_id=' . get_staff_user_id() . ')';
        }

        $data['contacts_logged_in_today'] = $this->clients_model->get_contacts('', 'last_login LIKE "' . date('Y-m-d') . '%"' . $whereContactsLoggedIn);

        $data['countries'] = $this->clients_model->get_clients_distinct_countries();
        $data['table'] = App_table::find('clients');
        $this->load->view('admin/clients/manage', $data);
    }

    public function table()
    {
        if ($native = $this->nativeCompanyClients()) {
            if ($this->input->method(true) !== 'POST') { show_error('Use POST.',405); return; }
            try {
                $page = $native->table($this->input->post() ?: []); $data=[];
                foreach ($page['rows'] as $row) {
                    $id=(int)$row['userid'];
                    $display=trim((string)$row['company']);
                    if($display==='')$display=trim(($row['primary_firstname']??'').' '.($row['primary_lastname']??''));
                    if($display==='')$display=_l('client').' #'.$id;
                    $data[]=['', $id, '<a href="'.e(admin_url('clients/client/'.$id)).'">'.e($display).'</a>',
                        e(trim(($row['primary_firstname']??'').' '.($row['primary_lastname']??''))), e($row['primary_email']??''), e($row['phonenumber']??''), (int)$row['active']===1?_l('yes'):_l('no'), e(implode(', ',array_column($row['groups']??[],'name'))), e($row['datecreated']??'')];
                }
                unset($page['rows']); $page['data']=$data;
                $this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode($page,JSON_THROW_ON_ERROR));
            } catch (Throwable $error) { $this->nativeCompanyClientFailure($error); }
            return;
        }
        if (staff_cant('view', 'customers')) {
            if (!have_assigned_customers() && staff_cant('create', 'customers')) {
                ajax_access_denied();
            }
        }

        App_table::find('clients')->output();
    }

    public function all_contacts()
    {
        $native=$this->nativeCompanyClients();if($native&&CompanyRequestScope::context()->isPrimary()){show_404();return;}if($native){
            try{
                require_once APPPATH.'services/NativeCompanyContactDirectory.php';$ctx=CompanyRequestScope::context();$i=$ctx->issuerId();$a=$ctx->staffId();$directory=new NativeCompanyContactDirectory($this->db,db_prefix());$meta=$directory->metadata($i,$a);
                if($this->input->is_ajax_request()){
                    if($this->input->method(true)!=='POST')throw new DomainException('company_client_invalid_input');
                    $page=$directory->page($i,$a,$this->input->post()?:[]);$data=[];$access=new CompanyCustomerAccess($this->db,db_prefix());$fields=new NativeCompanyCustomFields($this->db,db_prefix());
                    foreach($page['rows']as$r){
                        $id=(int)$r['id'];$client=(int)$r['userid'];$edit=$access->can($i,$a,$client,'edit');$image=str_starts_with((string)$r['profile_image'],'native:')?admin_url('clients/contact_profile_image/'.$client.'/'.$id):base_url('assets/images/user-placeholder.jpg');
                        $name='<img src="'.e($image).'" class="client-profile-image-small mright5"><a href="#" class="tw-font-medium" onclick="contact('.$client.','.$id.');return false;">'.e($r['firstname']).'</a>';
                        $actions=[];if($edit)$actions[]='<a href="#" onclick="contact('.$client.','.$id.');return false;">'.e(_l('edit')).'</a>';
                        if($access->can($i,$a,$client,'delete_contact')&&((int)$r['is_primary']!==1||(int)$r['total_contacts']===1))$actions[]='<a href="'.e(admin_url('clients/delete_contact/'.$client.'/'.$id)).'" class="native-contact-delete" data-native-revision="'.e($r['revision']).'">'.e(_l('delete')).'</a>';
                        if($actions)$name.='<div class="row-options tw-ml-9">'.implode(' | ',$actions).'</div>';
                        $cells=[$name,e($r['lastname'])];
                        if($page['consent_enabled']){$html='<p class="bold"><a href="#" onclick="view_contact_consent('.$id.');return false;">'.e(_l('view_consent')).'</a></p>';foreach((new NativeCompanyConsents($this->db,db_prefix()))->purposes($i,$a,$id)as$purpose)$html.='<p>'.e($purpose['name']).(!empty($purpose['consent_given'])?'<i class="fa fa-check text-success pull-right"></i>':'<i class="fa fa-remove text-danger pull-right"></i>').'</p>';$cells[]=$html;}
                        $switch='<div class="onoffswitch"><input type="checkbox" class="onoffswitch-checkbox native-contact-status" id="c_'.$id.'" data-native-contact="'.$id.'" data-native-client="'.$client.'" data-native-revision="'.e($r['revision']).'"'.((int)$r['active']===1?' checked':'').(!$edit||(int)$r['client_active']!==1||(int)$r['registration_confirmed']!==1?' disabled':'').'><label class="onoffswitch-label" for="c_'.$id.'"></label></div><span class="hide">'.e(_l((int)$r['active']===1?'is_active_export':'is_not_active_export')).'</span>';
                        $cells=array_merge($cells,['<a href="mailto:'.e($r['email']).'">'.e($r['email']).'</a>','<a href="'.e(admin_url('clients/client/'.$client)).'">'.e($r['company']).'</a>','<a href="tel:'.e($r['phonenumber']).'">'.e($r['phonenumber']).'</a>',e($r['title']),empty($r['last_login'])?'':e(_dt($r['last_login'])),$switch]);
                        $values=$fields->values($i,$a,'contacts',$id);foreach($page['custom_fields']as$field)$cells[]=e($values[$field['id']]??'');$cells['DT_RowClass']='has-row-options has-border-left'.((int)$r['registration_confirmed']===0?' row-border-warning requires-confirmation':'');$data[]=$cells;
                    }
                    unset($page['rows'],$page['custom_fields'],$page['consent_enabled'],$page['purposes']);$page['data']=$data;$this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode($page,JSON_THROW_ON_ERROR));return;
                }
                if($this->input->method(true)!=='GET')throw new DomainException('company_client_invalid_input');
                $data=['title'=>_l('customer_contacts'),'native_customers'=>true,'native_contact_consent_enabled'=>$meta['consent_enabled'],'native_contact_table_fields'=>$meta['custom_fields'],'company_clients_csrf'=>CompanyRequestScope::nativeCsrfToken($this)];if($meta['consent_enabled'])$data['consent_purposes']=$meta['purposes'];
                $this->load->view('admin/clients/all_contacts',$data);
            }catch(Throwable$error){$this->nativeCompanyClientFailure($error);}return;
        }
        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data('all_contacts');
        }

        if (is_gdpr() && get_option('gdpr_enable_consent_for_contacts') == '1') {
            $this->load->model('gdpr_model');
            $data['consent_purposes'] = $this->gdpr_model->get_consent_purposes();
        }

        $data['title'] = _l('customer_contacts');
        $this->load->view('admin/clients/all_contacts', $data);
    }

    /* Edit client or add new client*/
    public function client($id = '')
    {
        if ($native = $this->nativeCompanyClients()) {
            try {
                $group=$this->input->get('group')?:'profile';
                $sourceCompany=$this->input->get('source',false)==='company';
                require_once APPPATH.'services/NativeCompanyClientsSettings.php';
                if(!isset(NativeCompanyClientsSettings::TABS[$group])){show_404();return;}
                if($id===''&&$group!=='profile'){show_404();return;}
                $token=CompanyRequestScope::nativeCsrfToken($this);
                $capabilities=$native->capabilities($id===''?null:IssuerPolicy::id($id));
                if($this->input->method(true)==='POST'){
                    CompanyRequestScope::assertNativeMutation($this);
                    // The scoped writer validates plain-text fields and the native
                    // views escape them. Preserve the submitted identity for the
                    // durable receipt instead of rewriting it with global XSS filtering.
                    $input=$this->input->post(null,false)?:[];
                    // Native CI CSRF metadata is validated by the framework, not persisted as profile data.
                    unset($input[$this->security->get_csrf_token_name()],$input['_native_company_csrf'],$input['_company_context']);
                    // Keep the original create-and-add-contact flow. The marker
                    // controls only the destination, never the saved customer.
                    $addContact=$id===''&&isset($input['save_and_add_contact']);
                    unset($input['save_and_add_contact']);
                    $saved=$native->save($input,$id===''?null:$id);
                    CompanyRequestScope::invalidateCaches($this);
                    $query=$addContact?'?group=contacts&new_contact=true':'';
                    if($sourceCompany)$query.=($query?'&':'?').'source=company';
                    redirect(admin_url('clients/client/'.$saved.$query),'location',303);return;
                }
                if(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Use GET ou POST.',405);return;}
                $data=['group'=>$group,'native_customers'=>true,'native_customer_capabilities'=>$capabilities,
                    'company_clients_csrf'=>$token,'native_customer_request_key'=>bin2hex(random_bytes(32)),
                    'native_customer_revision'=>'','native_customer_profile'=>[], 'bodyclass'=>'customer-profile',
                    'title'=>_l('add_new',_l('client'))]+$native->references($id===''?null:IssuerPolicy::id($id));
                $data['native_customer_action']=CompanyRequestScope::adminUrl($this,'clients/client'.($id===''?'':'/'.IssuerPolicy::id($id)).($sourceCompany?'?source=company':''));
                $data['native_customer_languages']=array_values(array_filter(array_map('basename',glob(APPPATH.'language/*',GLOB_ONLYDIR)?:[]),static fn($name)=>preg_match('/^[a-z][a-z0-9_-]{0,39}$/D',$name)));
                $data['native_customer_owned']=true;
                if($id!==''){
                    $ctx=CompanyRequestScope::context();
                    $details=$native->details($id);if(!$details){show_404();return;}
                    $data['client']=(object)($details['client']+$details['profile']+($details['native_profile']??[])+['registration_confirmed'=>null,'leadid'=>null,'show_primary_contact'=>0,'default_currency'=>0,'country'=>0,'website'=>'','default_language'=>'']);
                    $companyName=trim((string)$details['client']['company']);
                    $data['title']=$companyName!==''?$companyName:_l('client').' #'.(int)$id;
                    $data['native_customer_revision']=$details['revision'];
                    $data['native_customer_profile']=$details['profile'];
                    $data['native_customer_owned']=$details['client']['owner_issuer_id']===CompanyRequestScope::context()->issuerId();
                    $data['native_customer_sharing_targets']=[];$data['native_customer_sharing_options']=[];
                    if($data['native_customer_owned']&&!empty($capabilities['share'])){$sharing=new CompanyClients($this->db,db_prefix());$data['native_customer_sharing_targets']=$details['targets'];$data['native_customer_sharing_options']=$sharing->sharingOptions($ctx->issuerId(),$ctx->staffId());}
                    $data['customer_groups']=$data['native_customer_owned']?$native->groups($id):[];
                    $data['native_customer_admin_manage']=false;
                    if($data['native_customer_owned']){
                        $ctx=CompanyRequestScope::context();$acl=new IssuerResourceAccess($this->db,db_prefix());
                        $data['native_customer_admin_manage']=$acl->can($ctx->issuerId(),$ctx->staffId(),'customers','create')||$acl->can($ctx->issuerId(),$ctx->staffId(),'customers','edit');
                        if($data['native_customer_admin_manage']){$access=new CompanyCustomerAccess($this->db,db_prefix());$admins=$access->assignments($ctx->issuerId(),$ctx->staffId(),(int)$id);$data['customer_admins']=$admins['rows'];$data['native_customer_admin_revision']=$admins['revision'];$data['staff']=$access->candidates($ctx->issuerId(),$ctx->staffId(),(int)$id);}
                    }

                    $data['native_registration_authorized']=$data['native_customer_owned']&&(new IssuerResourceAccess($this->db,db_prefix()))->administrative($ctx->issuerId(),$ctx->staffId());
                    if($data['native_registration_authorized']&&(int)$data['client']->registration_confirmed===0){require_once APPPATH.'services/NativeCompanyCustomerRegistration.php';$data['native_registration_revision']=(new NativeCompanyCustomerRegistration($this->db,db_prefix(),(string)$this->config->item('encryption_key')))->revision($ctx->issuerId(),$ctx->staffId(),(int)$id);}
                    $data['customer_tabs']=['profile'=>['name'=>_l('client_add_edit_profile'),'view'=>'admin/clients/groups/profile','icon'=>'fa-regular fa-user']];
                    if($data['native_customer_owned']){$data['customer_tabs']['vault']=['name'=>_l('vault'),'view'=>'admin/clients/groups/vault','icon'=>'fa-solid fa-lock'];if($group==='vault'){require_once APPPATH.'services/NativeCompanyCustomerVault.php';$data['vault_entries']=(new NativeCompanyCustomerVault($this->db,db_prefix()))->page($ctx->issuerId(),$ctx->staffId(),(int)$id);}}elseif($group==='vault')throw new DomainException('company_client_access_denied');
                    if($data['native_customer_owned']){
                        $data['customer_tabs']['contacts']=['name'=>_l('customer_contacts'),'view'=>'admin/clients/groups/contacts','icon'=>'fa-regular fa-address-book'];
                        if($group==='contacts'){
                            require_once APPPATH.'services/CompanyNativeContacts.php';
                            require_once APPPATH.'services/NativeCompanyCustomFields.php';
                            $data['native_contact_metadata']=(new CompanyNativeContacts($this->db,db_prefix()))->metadata($ctx->issuerId(),$ctx->staffId(),(int)$id);
                            $data['native_contact_create']=(int)$data['client']->active===1&&(new CompanyCustomerAccess($this->db,db_prefix()))->can($ctx->issuerId(),$ctx->staffId(),(int)$id,'create_contact');
                            $data['native_contact_consent_enabled']=(new CompanyNativeContacts($this->db,db_prefix()))->consentEnabled($ctx->issuerId(),$ctx->staffId(),(int)$id);
                            $data['native_contact_table_fields']=(new NativeCompanyCustomFields($this->db,db_prefix()))->definitionsForClientContacts($ctx->issuerId(),$ctx->staffId(),(int)$id,['show_on_table'=>1]);
                            $data['native_contact_invoice_access']=[];$data['native_contact_invoice_rows']=[];$data['native_contact_invoice_manage']=false;
                            $acl=new IssuerResourceAccess($this->db,db_prefix());if($acl->can($ctx->issuerId(),$ctx->staffId(),'invoices','view')){require_once APPPATH.'services/CompanyContactInvoiceAccess.php';$data['native_contact_invoice_access']=(new CompanyContactInvoiceAccess($this->db,db_prefix()))->page($ctx->issuerId(),$ctx->staffId(),(int)$id);$data['native_contact_invoice_rows']=$native->contacts((int)$id);$data['native_contact_invoice_manage']=(new CompanyCustomerAccess($this->db,db_prefix()))->can($ctx->issuerId(),$ctx->staffId(),(int)$id,'edit');}
                        }
                    }elseif($group==='contacts')throw new DomainException('company_client_access_denied');
                    if($data['native_customer_owned']){
                        $data['customer_tabs']['attachments']=['name'=>_l('customer_attachments'),'view'=>'admin/clients/groups/attachments','icon'=>'fa-solid fa-paperclip'];
                        if($group==='attachments'){
                            require_once APPPATH.'services/NativeCompanyCustomerFiles.php';$data['attachments']=['customer'=>(new NativeCompanyCustomerFiles($this->db,db_prefix()))->page($ctx->issuerId(),$ctx->staffId(),(int)$id)];
                            $data['contacts']=IssuerStorage::query($this->db,'SELECT id,firstname,lastname,is_primary FROM '.db_prefix().'contacts WHERE userid=? AND active=1 ORDER BY id',[(int)$id])->result_array();
                            $data['native_file_primary_contact']=0;foreach($data['contacts']as$contact)if((int)$contact['is_primary']===1){if($data['native_file_primary_contact']!==0)throw new DomainException('company_contact_invalid_primary');$data['native_file_primary_contact']=(int)$contact['id'];}
                            $access=new CompanyCustomerAccess($this->db,db_prefix());$data['native_file_edit']=$access->can($ctx->issuerId(),$ctx->staffId(),(int)$id,'edit');$data['native_file_delete']=$access->can($ctx->issuerId(),$ctx->staffId(),(int)$id,'delete_contact');$data['native_file_signature']=(new CompanySettingsStorage($this->db,db_prefix()))->get($ctx->issuerId(),'email_signature');
                        }
                    }elseif($group==='attachments')throw new DomainException('company_client_access_denied');
                    if($data['native_customer_owned']){$data['customer_tabs']['map']=['name'=>_l('customer_map'),'view'=>'admin/clients/groups/map','icon'=>'fa-solid fa-map-marker'];if($group==='map'){$coordinates=$native->maps((int)$id);$data['client']->latitude=$coordinates['latitude']??'';$data['client']->longitude=$coordinates['longitude']??'';$data['native_map_key']=$coordinates['google_api_key'];$data['native_map_revision']=$coordinates['revision'];$data['native_map_edit']=(new CompanyCustomerAccess($this->db,db_prefix()))->can($ctx->issuerId(),$ctx->staffId(),(int)$id,'edit');}}elseif($group==='map')throw new DomainException('company_client_access_denied');
                    $relationsAcl=new IssuerResourceAccess($this->db,db_prefix());
                    foreach(['invoices'=>['client_invoices_tab','fa-regular fa-file-lines'],'projects'=>['projects','fa-solid fa-diagram-project']] as$key=>[$label,$icon]){
                        if($relationsAcl->can($ctx->issuerId(),$ctx->staffId(),'customers','view')&&$relationsAcl->can($ctx->issuerId(),$ctx->staffId(),$key,'view')){
                            $data['customer_tabs'][$key]=['name'=>_l($label),'view'=>'admin/clients/groups/'.$key,'icon'=>$icon];
                            if($group===$key){
                                if($key==='invoices'){require_once APPPATH.'services/NativeCompanyInvoiceCustomColumns.php';$data['native_invoice_custom_fields']=(new NativeCompanyInvoiceCustomColumns($this->db,db_prefix(),$ctx->issuerId(),$ctx->staffId()))->definitions(true);}
                                else {require_once APPPATH.'services/NativeCompanyCustomFields.php';require_once APPPATH.'services/NativeCompanyTableOrder.php';$data['native_project_custom_fields']=(new NativeCompanyCustomFields($this->db,db_prefix()))->definitions($ctx->issuerId(),$ctx->staffId(),'projects',['active'=>1,'show_on_table'=>1]);$data['native_project_table_order']=(new NativeCompanyTableOrder($this->db,db_prefix(),$ctx->issuerId(),$ctx->staffId()))->read('projects');}
                            }
                        }elseif($group===$key)throw new DomainException('company_client_access_denied');
                    }
                    if($relationsAcl->can($ctx->issuerId(),$ctx->staffId(),'customers','view')&&$relationsAcl->can($ctx->issuerId(),$ctx->staffId(),'tickets','view')){
                        $data['customer_tabs']['tickets']=['name'=>_l('contracts_tickets_tab'),'view'=>'admin/clients/groups/tickets','icon'=>'fa-solid fa-life-ring'];
                        if($group==='tickets'){
                            require_once APPPATH.'services/NativeCompanyTicketIndexPresentation.php';
                            $ticketIndex=(new NativeCompanyTicketIndexPresentation($this->db,db_prefix()))->read($ctx->issuerId(),$ctx->staffId(),'', ['customer'=>(int)$id]);
                            $data['native_ticket_structure']=$ticketIndex['native_ticket_index']['structure'];
                        }
                    }elseif($group==='tickets')throw new DomainException('company_client_access_denied');
                    $relations=['notes'=>['contracts_notes_tab','fa-regular fa-note-sticky','customers'],'statement'=>['customer_statement','fa-solid fa-file-invoice-dollar','invoices'],'payments'=>['client_payments_tab','fa-solid fa-money-bill-wave','payments'],'proposals'=>['proposals','fa-regular fa-file-lines','proposals'],'credit_notes'=>['credit_notes','fa-regular fa-file-lines','credit_notes'],'estimates'=>['estimates','fa-regular fa-file-lines','estimates'],'subscriptions'=>['subscriptions','fa-solid fa-rotate','subscriptions'],'expenses'=>['expenses','fa-solid fa-receipt','expenses'],'contracts'=>['contracts','fa-solid fa-file-contract','contracts'],'tasks'=>['tasks','fa-regular fa-square-check','tasks'],'reminders'=>['client_reminders_tab','fa-regular fa-bell','customers']];
                    foreach($relations as$key=>[$label,$icon,$feature])if($relationsAcl->can($ctx->issuerId(),$ctx->staffId(),'customers','view')&&$relationsAcl->can($ctx->issuerId(),$ctx->staffId(),$feature,'view')){$data['customer_tabs'][$key]=['name'=>_l($label),'view'=>'admin/clients/groups/native_section','icon'=>$icon];if($key==='notes')$data['customer_tabs'][$key]['view']='admin/clients/groups/native_notes';if($key==='statement')$data['customer_tabs'][$key]['view']='admin/clients/groups/native_statement';if($group===$key)$data+=$this->nativeClientSectionData($ctx->issuerId(),$ctx->staffId(),(int)$id,$key);}
                    $data['customer_tabs']=$native->filterProfileTabs((int)$id,$data['customer_tabs']);if(!isset($data['customer_tabs'][$group]))throw new DomainException('company_client_access_denied');$data['tab']=$data['customer_tabs'][$group];
                } elseif(!$capabilities['create']) {show_error('Sem permissão para criar clientes.',403);return;}
                if($data['native_customer_owned'])$data+=$native->fieldData($id===''?null:$id);
                $this->load->view('admin/clients/client',$data);
            } catch(Throwable $error){$this->nativeCompanyClientFailure($error);}
            return;
        }
        if (staff_cant('view', 'customers')) {
            if ($id != '' && !is_customer_admin($id)) {
                access_denied('customers');
            }
        }

        if ($this->input->post() && !$this->input->is_ajax_request()) {
            if ($id == '') {
                if (staff_cant('create', 'customers')) {
                    access_denied('customers');
                }

                $data = $this->input->post();

                $save_and_add_contact = false;
                if (isset($data['save_and_add_contact'])) {
                    unset($data['save_and_add_contact']);
                    $save_and_add_contact = true;
                }
                try { $id = $this->clients_model->add($data); }
                catch (DomainException $error) { set_alert('danger','Confira o tipo de pessoa, CPF/CNPJ e dados complementares. O cadastro não foi salvo.'); redirect(admin_url('clients/client')); return; }
                catch (RuntimeException $error) { if(!array_key_exists('brazil_profile',$data))throw $error; log_message('error','Client profile save failed; no submitted data logged.'); set_alert('danger','Não foi possível confirmar o cadastro. Recarregue e tente novamente.'); redirect(admin_url('clients/client')); return; }
                if (staff_cant('view', 'customers')) {
                    $assign['customer_admins']   = [];
                    $assign['customer_admins'][] = get_staff_user_id();
                    $this->clients_model->assign_admins($assign, $id);
                }
                if ($id) {
                    set_alert('success', _l('added_successfully', _l('client')));
                    if ($save_and_add_contact == false) {
                        redirect(admin_url('clients/client/' . $id));
                    } else {
                        redirect(admin_url('clients/client/' . $id . '?group=contacts&new_contact=true'));
                    }
                }
            } else {
                if (staff_cant('edit', 'customers')) {
                    if (!is_customer_admin($id)) {
                        access_denied('customers');
                    }
                }
                try { $success = $this->clients_model->update($this->input->post(), $id); }
                catch (DomainException $error) { set_alert('danger','Confira o tipo de pessoa, CPF/CNPJ e dados complementares. A alteração não foi salva.'); redirect(admin_url('clients/client/' . $id)); return; }
                catch (RuntimeException $error) { if(!$this->input->post('brazil_profile'))throw $error; log_message('error','Client profile update failed; no submitted data logged.'); set_alert('danger','Não foi possível confirmar a alteração. Recarregue e tente novamente.'); redirect(admin_url('clients/client/' . $id)); return; }
                if ($success == true) {
                    set_alert('success', _l('updated_successfully', _l('client')));
                }
                redirect(admin_url('clients/client/' . $id));
            }
        }

        $group         = !$this->input->get('group') ? 'profile' : $this->input->get('group');
        $data['group'] = $group;

        if ($group != 'contacts' && $contact_id = $this->input->get('contactid')) {
            redirect(admin_url('clients/client/' . $id . '?group=contacts&contactid=' . $contact_id));
        }

        // Customer groups
        $data['groups'] = $this->clients_model->get_groups();

        if ($id == '') {
            $title = _l('add_new', _l('client'));
        } else {
            $client                = $this->clients_model->get($id);
            $data['customer_tabs'] = get_customer_profile_tabs($id);

            if (!$client) {
                show_404();
            }

            $data['contacts'] = $this->clients_model->get_contacts($id);
            $data['tab']      = isset($data['customer_tabs'][$group]) ? $data['customer_tabs'][$group] : null;

            if (!$data['tab']) {
                show_404();
            }

            // Fetch data based on groups
            if ($group == 'profile') {
                $data['customer_groups'] = $this->clients_model->get_customer_groups($id);
                $data['customer_admins'] = $this->clients_model->get_admins($id);
            } elseif ($group == 'attachments') {
                $data['attachments'] = get_all_customer_attachments($id);
            } elseif ($group == 'vault') {
                $data['vault_entries'] = hooks()->apply_filters('check_vault_entries_visibility', $this->clients_model->get_vault_entries($id));

                if ($data['vault_entries'] === -1) {
                    $data['vault_entries'] = [];
                }
            } elseif ($group == 'estimates') {
                $this->load->model('estimates_model');
                $data['estimate_statuses'] = $this->estimates_model->get_statuses();
            } elseif ($group == 'invoices') {
                $this->load->model('invoices_model');
                $data['invoice_statuses'] = $this->invoices_model->get_statuses();
            } elseif ($group == 'credit_notes') {
                $this->load->model('credit_notes_model');
                $data['credit_notes_statuses'] = $this->credit_notes_model->get_statuses();
                $data['credits_available']     = $this->credit_notes_model->total_remaining_credits_by_customer($id);
            } elseif ($group == 'payments') {
                $this->load->model('payment_modes_model');
                $data['payment_modes'] = $this->payment_modes_model->get();
            } elseif ($group == 'notes') {
                $data['user_notes'] = $this->misc_model->get_notes($id, 'customer');
            } elseif ($group == 'projects') {
                $this->load->model('projects_model');
                $data['project_statuses'] = $this->projects_model->get_project_statuses();
            } elseif ($group == 'statement') {
                if (staff_cant('view', 'invoices') && staff_cant('view', 'payments')) {
                    set_alert('danger', _l('access_denied'));
                    redirect(admin_url('clients/client/' . $id));
                }

                $data = array_merge($data, prepare_mail_preview_data('customer_statement', $id));
            } elseif ($group == 'map') {
                if (get_option('google_api_key') != '' && !empty($client->latitude) && !empty($client->longitude)) {
                    $this->app_scripts->add('map-js', base_url($this->app_scripts->core_file('assets/js', 'map.js')) . '?v=' . $this->app_css->core_version());

                    $this->app_scripts->add('google-maps-api-js', [
                        'path'       => 'https://maps.googleapis.com/maps/api/js?key=' . get_option('google_api_key') . '&callback=initMap',
                        'attributes' => [
                            'async',
                            'defer',
                            'latitude'       => "$client->latitude",
                            'longitude'      => "$client->longitude",
                            'mapMarkerTitle' => "$client->company",
                        ],
                        ]);
                }
            }

            $data['staff'] = $this->staff_model->get('', ['active' => 1]);

            $data['client'] = $client;
            $title          = $client->company;

            // Get all active staff members (used to add reminder)
            $data['members'] = $data['staff'];

            if (!empty($data['client']->company)) {
                // Check if is realy empty client company so we can set this field to empty
                // The query where fetch the client auto populate firstname and lastname if company is empty
                if (is_empty_customer_company($data['client']->userid)) {
                    $data['client']->company = '';
                }
            }
        }

        $this->load->model('currencies_model');
        $data['currencies'] = $this->currencies_model->get();

        if ($id != '') {
            $customer_currency = $data['client']->default_currency;

            foreach ($data['currencies'] as $currency) {
                if ($customer_currency != 0) {
                    if ($currency['id'] == $customer_currency) {
                        $customer_currency = $currency;

                        break;
                    }
                } else {
                    if ($currency['isdefault'] == 1) {
                        $customer_currency = $currency;

                        break;
                    }
                }
            }

            if (is_array($customer_currency)) {
                $customer_currency = (object) $customer_currency;
            }

            $data['customer_currency'] = $customer_currency;

            $slug_zip_folder = (
                $client->company != ''
                ? $client->company
                : get_contact_full_name(get_primary_contact_user_id($client->userid))
            );

            $data['zip_in_folder'] = slug_it($slug_zip_folder);
        }

        $data['bodyclass'] = 'customer-profile dynamic-create-groups';
        $data['title']     = $title;

        $this->load->view('admin/clients/client', $data);
    }

    public function export($contact_id)
    {
        if($this->nativeCompanyClients()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'clients','export',[$contact_id]);return;}
        if (!\app\services\ClientIssuerScope::isLegacyContact($this->db, db_prefix(), $contact_id)) { show_404(); return; }
        if (is_admin()) {
            $this->load->library('gdpr/gdpr_contact');
            $this->gdpr_contact->export($contact_id);
        }
    }

    // Used to give a tip to the user if the company exists when new company is created
    public function check_duplicate_customer_name()
    {
        if($native=$this->nativeCompanyClients()){
            if($this->input->method(true)!=='POST'){show_error('Use POST.',405);return;}
            try{CompanyRequestScope::assertNativeMutation($this);$name=$this->input->post('company',false);$exists=$native->duplicateName($name);
                $this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode(['exists'=>$exists,'message'=>_l('company_exists_info','<b>'.e($name).'</b>')],JSON_THROW_ON_ERROR));
            }catch(Throwable $error){$this->nativeCompanyClientFailure($error);}return;
        }
        if (staff_can('create',  'customers')) {
            $companyName = trim($this->input->post('company'));
            $response    = [
                'exists'  => count($this->clients_model->get('', [db_prefix() . 'clients.company' => $companyName])) > 0,
                'message' => _l('company_exists_info', '<b>' . $companyName . '</b>'),
            ];
            echo json_encode($response);
        }
    }

    public function save_longitude_and_latitude($client_id)
    {
        if($native=$this->nativeCompanyClients()){
            try{CompanyRequestScope::assertNativeMutation($this);$revision=$this->input->post('native_coordinates_revision');if(!is_string($revision))throw new DomainException('company_client_stale');$native->saveCoordinates(IssuerPolicy::id($client_id),$this->input->post('latitude'),$this->input->post('longitude'),$revision);$this->output->set_header('Cache-Control: no-store')->set_content_type('text/plain')->set_output('success');}catch(Throwable$error){$this->nativeCompanyClientFailure($error);}return;
        }
        if (!\app\services\ClientIssuerScope::isLegacyClient($this->db, db_prefix(), $client_id)) { show_404(); return; }
        if (staff_cant('edit', 'customers')) {
            if (!is_customer_admin($client_id)) {
                ajax_access_denied();
            }
        }

        $this->db->where('userid', $client_id);
        $this->db->update(db_prefix() . 'clients', [
            'longitude' => $this->input->post('longitude'),
            'latitude'  => $this->input->post('latitude'),
        ]);
        if ($this->db->affected_rows() > 0) {
            echo 'success';
        } else {
            echo 'false';
        }
    }

    public function form_contact($customer_id, $contact_id = '')
    {
        if($this->nativeCompanyClients()){
            try{
                require_once APPPATH.'services/NativeCompanyContactForm.php';
                $context=CompanyRequestScope::context();$client=IssuerPolicy::id($customer_id);$id=$contact_id===''?null:IssuerPolicy::id($contact_id);
                $form=new NativeCompanyContactForm($this->db,db_prefix(),$context->issuerId(),$context->staffId());
                if($this->input->method(true)==='POST'){
                    CompanyRequestScope::assertNativeMutation($this);$input=$this->input->post()?:[];$input['password']=$this->input->post('password',false)??'';
                    unset($input[$this->security->get_csrf_token_name()],$input['_native_company_csrf'],$input['_company_context']);
                    $bytes=null;
                    if(array_diff(array_keys($_FILES),['profile_image']))throw new DomainException('company_contact_image_invalid');
                    if(isset($_FILES['profile_image'])&&($_FILES['profile_image']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){$file=$_FILES['profile_image'];if(($file['error']??null)!==UPLOAD_ERR_OK||!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name'])||!is_int($file['size']??null)||$file['size']<16||$file['size']>NativeCompanyContactImage::MAX_BYTES)throw new DomainException('company_contact_image_invalid');$bytes=file_get_contents($file['tmp_name'],false,null,0,NativeCompanyContactImage::MAX_BYTES+1);if(!is_string($bytes)||strlen($bytes)!==$file['size'])throw new DomainException('company_contact_image_invalid');}
                    $this->load->library('encryption');
                    $result=$form->save($client,$id,$input,(string)$this->config->item('encryption_key'),site_url(),fn($v)=>$this->encryption->decrypt($v),fn($v)=>app_hash_password($v),$bytes);
                    $result['message']=_l($id===null?'added_successfully':'updated_successfully',_l('contact'));CompanyRequestScope::invalidateCaches($this);
                    $this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode($result,JSON_THROW_ON_ERROR));return;
                }
                if(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Use GET ou POST.',405);return;}
                $data=$form->data($client,$id);$data['company_clients_csrf']=CompanyRequestScope::nativeCsrfToken($this);$data['title']=$id===null?_l('add_new',_l('contact')):$data['contact']->firstname.' '.$data['contact']->lastname;
                $data['customer_permissions']=[];foreach([1=>['invoice','invoices'],2=>['estimate','estimates'],3=>['contract','contracts'],4=>['proposal','proposals'],5=>['support','support'],6=>['projects','projects']]as$permission=>$label)$data['customer_permissions'][]=['id'=>$permission,'name'=>_l('customer_permission_'.$label[0]),'short_name'=>$label[1]];
                $this->output->set_header('Cache-Control: no-store');$this->load->view('admin/clients/modals/contact',$data);
            }catch(Throwable $error){$this->nativeCompanyContactFailure($error);}return;
        }

        if ($contact_id !== '') {
            $contact = $this->clients_model->get_contact($contact_id);
            if (!$contact || (int) $contact->userid !== (int) $customer_id) { show_404(); return; }
        }
        if (!\app\services\ClientIssuerScope::isLegacyClient($this->db, db_prefix(), $customer_id)) { show_404(); return; }
        if (staff_cant('view', 'customers')) {
            if (!is_customer_admin($customer_id)) {
                echo _l('access_denied');
                die;
            }
        }
        $data['customer_id'] = $customer_id;
        $data['contactid']   = $contact_id;

        if (is_automatic_calling_codes_enabled()) {
            $clientCountryId = $this->db->select('country')
                ->where('userid', $customer_id)
                ->get('clients')->row()->country ?? null;

            $clientCountry = get_country($clientCountryId);
            
            $callingCode   = $clientCountry && $clientCountry->calling_code ?
                ($clientCountry ? '+' . ltrim($clientCountry->calling_code, '+') : null) : 
                null;
        } else {
            $callingCode = null;
        }

        if ($this->input->post()) {
            $data             = $this->input->post();
            $data['password'] = $this->input->post('password', false);

            if ($callingCode && !empty($data['phonenumber']) && $data['phonenumber'] == $callingCode) {
                $data['phonenumber'] = '';
            }

            unset($data['contactid']);

            if ($contact_id == '') {
                if (staff_cant('create', 'customers')) {
                    if (!is_customer_admin($customer_id)) {
                        header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad error');
                        echo json_encode([
                            'success' => false,
                            'message' => _l('access_denied'),
                        ]);
                        die;
                    }
                }
                $id      = $this->clients_model->add_contact($data, $customer_id);
                $message = '';
                $success = false;
                if ($id) {
                    handle_contact_profile_image_upload($id);
                    $success = true;
                    $message = _l('added_successfully', _l('contact'));
                }
                echo json_encode([
                    'success'             => $success,
                    'message'             => $message,
                    'has_primary_contact' => (total_rows(db_prefix() . 'contacts', ['userid' => $customer_id, 'is_primary' => 1]) > 0 ? true : false),
                    'is_individual'       => is_empty_customer_company($customer_id) && total_rows(db_prefix() . 'contacts', ['userid' => $customer_id]) == 1,
                ]);
                die;
            }
            if (staff_cant('edit', 'customers')) {
                if (!is_customer_admin($customer_id)) {
                    header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad error');
                    echo json_encode([
                            'success' => false,
                            'message' => _l('access_denied'),
                        ]);
                    die;
                }
            }
            $original_contact = $this->clients_model->get_contact($contact_id);
            $success          = $this->clients_model->update_contact($data, $contact_id);
            $message          = '';
            $proposal_warning = false;
            $original_email   = '';
            $updated          = false;
            if (is_array($success)) {
                if (isset($success['set_password_email_sent'])) {
                    $message = _l('set_password_email_sent_to_client');
                } elseif (isset($success['set_password_email_sent_and_profile_updated'])) {
                    $updated = true;
                    $message = _l('set_password_email_sent_to_client_and_profile_updated');
                }
            } else {
                if ($success == true) {
                    $updated = true;
                    $message = _l('updated_successfully', _l('contact'));
                }
            }
            if (handle_contact_profile_image_upload($contact_id) && !$updated) {
                $message = _l('updated_successfully', _l('contact'));
                $success = true;
            }
            if ($updated == true) {
                $contact = $this->clients_model->get_contact($contact_id);
                if (total_rows(db_prefix() . 'proposals', [
                        'rel_type' => 'customer',
                        'rel_id' => $contact->userid,
                        'email' => $original_contact->email,
                    ]) > 0 && ($original_contact->email != $contact->email)) {
                    $proposal_warning = true;
                    $original_email   = $original_contact->email;
                }
            }
            echo json_encode([
                    'success'             => $success,
                    'proposal_warning'    => $proposal_warning,
                    'message'             => $message,
                    'original_email'      => $original_email,
                    'has_primary_contact' => (total_rows(db_prefix() . 'contacts', ['userid' => $customer_id, 'is_primary' => 1]) > 0 ? true : false),
                ]);
            die;
        }


        $data['calling_code'] = $callingCode;

        if ($contact_id == '') {
            $title = _l('add_new', _l('contact'));
        } else {
            $data['contact'] = $this->clients_model->get_contact($contact_id);

            if (!$data['contact']) {
                header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad error');
                echo json_encode([
                    'success' => false,
                    'message' => 'Contact Not Found',
                ]);
                die;
            }
            $title = $data['contact']->firstname . ' ' . $data['contact']->lastname;
        }

        $data['customer_permissions'] = get_contact_permissions();
        $data['title']                = $title;
        $this->load->view('admin/clients/modals/contact', $data);
    }

    public function confirm_registration($client_id)
    {
        if($this->nativeCompanyClients()){
            if($this->input->method(true)!=='POST'){show_error('Use POST.',405);return;}
            try{
                CompanyRequestScope::assertNativeMutation($this);
                require_once APPPATH.'services/NativeCompanyCustomerRegistration.php';$ctx=CompanyRequestScope::context();$id=IssuerPolicy::id($client_id);$revision=$this->input->post('revision',false);if(!is_string($revision))throw new DomainException('company_client_stale');$this->load->library('encryption');
                (new NativeCompanyCustomerRegistration($this->db,db_prefix(),(string)$this->config->item('encryption_key')))->confirm($ctx->issuerId(),$ctx->staffId(),$id,$revision,site_url(),fn($v)=>$this->encryption->decrypt($v));CompanyRequestScope::invalidateCaches($this);set_alert('success',_l('customer_registration_successfully_confirmed'));redirect(admin_url('clients/client/'.$id),'location',303);
            }catch(Throwable$error){$this->nativeCompanyClientFailure($error);}return;
        }

        if (!is_admin()) {
            access_denied('Customer Confirm Registration, ID: ' . $client_id);
        }
        $this->clients_model->confirm_registration($client_id);
        set_alert('success', _l('customer_registration_successfully_confirmed'));
        redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
    }

    public function update_file_share_visibility()
    {
        if($this->nativeCompanyClients()){
            if($this->input->method(true)!=='POST'){show_error('Use POST.',405);return;}
            try{CompanyRequestScope::assertNativeMutation($this);require_once APPPATH.'services/NativeCompanyCustomerFiles.php';$ctx=CompanyRequestScope::context();$visible=$this->input->post('visible',false);$contacts=$this->input->post('share_contacts_id',false)??[];if(!in_array($visible,['0','1'],true)||!is_array($contacts))throw new DomainException('native_customer_file_invalid');$next=(new NativeCompanyCustomerFiles($this->db,db_prefix()))->share($ctx->issuerId(),$ctx->staffId(),IssuerPolicy::id($this->input->post('file_id',false)),IssuerPolicy::id($this->input->post('revision',false)),$visible==='1',$contacts,IssuerPolicy::id($this->input->post('customer_id',false)));$this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode(['success'=>true,'revision'=>$next],JSON_THROW_ON_ERROR));}catch(Throwable$error){$this->nativeCompanyClientFailure($error);}return;
        }

        if ($this->input->post()) {
            $file_id           = $this->input->post('file_id');
            $share_contacts_id = [];

            if ($this->input->post('share_contacts_id')) {
                $share_contacts_id = $this->input->post('share_contacts_id');
            }

            \app\services\ClientIssuerScope::applyLegacy($this->db, db_prefix(), db_prefix() . 'files', 'rel_id');
            $file = $this->db->where('id', $file_id)->where('rel_type', 'customer')->get(db_prefix() . 'files')->row();
            if (!$file || !is_array($share_contacts_id)
                || (staff_cant('edit', 'customers') && !is_customer_admin($file->rel_id))) { show_404(); return; }
            // Validate every recipient before removing existing sharing links.
            foreach ($share_contacts_id as $share_contact_id) {
                $contact = $this->clients_model->get_contact($share_contact_id);
                if (!$contact || (int) $contact->userid !== (int) $file->rel_id) { show_404(); return; }
            }

            $this->db->where('file_id', $file_id);
            $this->db->delete(db_prefix() . 'shared_customer_files');

            foreach ($share_contacts_id as $share_contact_id) {
                $this->db->insert(db_prefix() . 'shared_customer_files', [
                    'file_id'    => $file_id,
                    'contact_id' => $share_contact_id,
                ]);
            }
        }
    }

    public function delete_contact_profile_image($contact_id)
    {
        $native=$this->nativeCompanyClients();if($native){
            try{
                if($this->input->method(true)!=='POST'){show_error('Use POST.',405);return;}CompanyRequestScope::assertNativeMutation($this);
                require_once APPPATH.'services/NativeCompanyContactImage.php';$ctx=CompanyRequestScope::context();$client=IssuerPolicy::id($this->input->post('client_id',false));$id=IssuerPolicy::id($contact_id);$revision=$this->input->post('revision',false);if(!is_string($revision))throw new DomainException('company_client_stale');
                (new NativeCompanyContactImage($this->db,db_prefix()))->remove($ctx->issuerId(),$ctx->staffId(),$client,$id,$revision);
                $fresh=(new CompanyNativeContacts($this->db,db_prefix()))->get($ctx->issuerId(),$ctx->staffId(),$client,$id);
                $this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode(['success'=>true,'revision'=>$fresh['revision']],JSON_THROW_ON_ERROR));
            }catch(Throwable $error){$this->nativeCompanyContactFailure($error);}return;
        }

        $this->clients_model->delete_contact_profile_image($contact_id);
    }

    public function mark_as_active($id)
    {
        if($this->nativeCompanyClients()){$this->change_client_status($id,'1');redirect(admin_url('clients/client/'.$id),'location',303);return;}
        if (!\app\services\ClientIssuerScope::isLegacyClient($this->db, db_prefix(), $id)) { show_404(); return; }
        $this->db->where('userid', $id);
        $this->db->update(db_prefix() . 'clients', [
            'active' => 1,
        ]);
        redirect(admin_url('clients/client/' . $id));
    }

    public function consents($id)
    {
        if(class_exists('CompanyRequestScope',false)&&CompanyRequestScope::nativeFor($this)){
            try{
                if(!in_array($this->input->method(true),['GET','HEAD'],true))throw new DomainException('native_consent_invalid');
                require_once APPPATH.'services/NativeCompanyConsents.php';require_once APPPATH.'services/NativePortalConsentCapability.php';
                $ctx=CompanyRequestScope::context();$issuer=$ctx->issuerId();$actor=$ctx->staffId();$contact=IssuerPolicy::id($id);$service=new NativeCompanyConsents($this->db,db_prefix());$history=$service->history($issuer,$actor,$contact);$url='';
                $store=new CompanySettingsStorage($this->db,db_prefix());if($store->get($issuer,'enable_gdpr')==='1'&&$store->get($issuer,'gdpr_enable_consent_for_contacts')==='1'){try{$key=(new NativePortalConsentCapability($this->db,db_prefix(),$this->config->item('encryption_key')))->issueForStaff($issuer,$actor,$contact);$url=site_url('consent/contact/'.$key).'?issuer_id='.$issuer;}catch(DomainException$e){if($e->getMessage()!=='native_consent_capability_denied')throw$e;}}
                $data=['contact_id'=>$contact,'purposes'=>$service->purposes($issuer,$actor,$contact),'consents'=>$history,'native_company_consent'=>true,'native_consent_url'=>$url,'native_consent_csrf'=>CompanyRequestScope::nativeCsrfToken($this),'native_consent_revision'=>hash('sha256',json_encode($history,JSON_THROW_ON_ERROR))];
                $this->output->set_header('Cache-Control: private, no-store');$this->load->view('admin/gdpr/contact_consent',$data);return;
            }catch(Throwable$e){$this->nativeCompanyClientFailure($e);return;}
        }
        if (!\app\services\ClientIssuerScope::isLegacyContact($this->db, db_prefix(), $id)) { show_404(); return; }
        if (staff_cant('view', 'customers')) {
            if (!is_customer_admin(get_user_id_by_contact_id($id))) {
                echo _l('access_denied');
                die;
            }
        }

        $this->load->model('gdpr_model');
        $data['purposes']   = $this->gdpr_model->get_consent_purposes($id, 'contact');
        $data['consents']   = $this->gdpr_model->get_consents(['contact_id' => $id]);
        $data['contact_id'] = $id;
        $this->load->view('admin/gdpr/contact_consent', $data);
    }

    public function update_all_proposal_emails_linked_to_customer($contact_id)
    {
        if($this->nativeCompanyClients()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'clients','update_all_proposal_emails_linked_to_customer',[$contact_id]);return;}
        if (!\app\services\ClientIssuerScope::isLegacyContact($this->db, db_prefix(), $contact_id)) { show_404(); return; }
        $success = false;
        $email   = '';
        if ($this->input->post('update')) {
            $this->load->model('proposals_model');

            $this->db->select('email,userid');
            $this->db->where('id', $contact_id);
            $contact = $this->db->get(db_prefix() . 'contacts')->row();

            $proposals = $this->proposals_model->get('', [
                'rel_type' => 'customer',
                'rel_id'   => $contact->userid,
                'email'    => $this->input->post('original_email'),
            ]);
            $affected_rows = 0;

            foreach ($proposals as $proposal) {
                $this->db->where('id', $proposal['id']);
                $this->db->update(db_prefix() . 'proposals', [
                    'email' => $contact->email,
                ]);
                if ($this->db->affected_rows() > 0) {
                    $affected_rows++;
                }
            }

            if ($affected_rows > 0) {
                $success = true;
            }
        }
        echo json_encode([
            'success' => $success,
            'message' => _l('proposals_emails_updated', [
                _l('contact_lowercase'),
                $contact->email,
            ]),
        ]);
    }

    public function assign_admins($id)
    {
        if($this->nativeCompanyClients()){
            try{CompanyRequestScope::assertNativeMutation($this);$ctx=CompanyRequestScope::context();$client=IssuerPolicy::id($id);$access=new CompanyCustomerAccess($this->db,db_prefix());$revision=$this->input->post('revision');if(!is_string($revision))throw new DomainException('company_client_stale');$staff=$this->input->post('customer_admins')??[];if(!is_array($staff))throw new DomainException('company_client_invalid_input');
                $access->assign($ctx->issuerId(),$ctx->staffId(),$client,$staff,$revision);CompanyRequestScope::invalidateCaches($this);redirect(admin_url('clients/client/'.$client.'?tab=customer_admins'),'location',303);
            }catch(Throwable$error){$this->nativeCompanyClientFailure($error);}return;
        }

        if (staff_cant('create', 'customers') && staff_cant('edit', 'customers')) {
            access_denied('customers');
        }
        $success = $this->clients_model->assign_admins($this->input->post(), $id);
        if ($success == true) {
            set_alert('success', _l('updated_successfully', _l('client')));
        }

        redirect(admin_url('clients/client/' . $id . '?tab=customer_admins'));
    }

    public function delete_customer_admin($customer_id, $staff_id)
    {
        if($this->nativeCompanyClients()){
            try{CompanyRequestScope::assertNativeMutation($this);$ctx=CompanyRequestScope::context();$client=IssuerPolicy::id($customer_id);$access=new CompanyCustomerAccess($this->db,db_prefix());$revision=$this->input->post('revision');if(!is_string($revision))throw new DomainException('company_client_stale');$current=$access->assignments($ctx->issuerId(),$ctx->staffId(),$client);$staff=array_values(array_diff(array_map('intval',array_column($current['rows'],'staff_id')),[IssuerPolicy::id($staff_id)]));
                $access->assign($ctx->issuerId(),$ctx->staffId(),$client,$staff,$revision);CompanyRequestScope::invalidateCaches($this);redirect(admin_url('clients/client/'.$client.'?tab=customer_admins'),'location',303);
            }catch(Throwable$error){$this->nativeCompanyClientFailure($error);}return;
        }

        if (!\app\services\ClientIssuerScope::isLegacyClient($this->db, db_prefix(), $customer_id)) { show_404(); return; }
        if (staff_cant('create', 'customers') && staff_cant('edit', 'customers')) {
            access_denied('customers');
        }

        $this->db->where('customer_id', $customer_id);
        $this->db->where('staff_id', $staff_id);
        $this->db->delete(db_prefix() . 'customer_admins');
        redirect(admin_url('clients/client/' . $customer_id) . '?tab=customer_admins');
    }

    public function delete_contact($customer_id, $id)
    {
        if($this->nativeCompanyClients()){
            try{CompanyRequestScope::assertNativeMutation($this);require_once APPPATH.'services/CompanyNativeContacts.php';$ctx=CompanyRequestScope::context();$revision=$this->input->post('revision');if(!is_string($revision))throw new DomainException('company_client_stale');
                (new CompanyNativeContacts($this->db,db_prefix()))->delete($ctx->issuerId(),$ctx->staffId(),IssuerPolicy::id($customer_id),IssuerPolicy::id($id),$revision);
                CompanyRequestScope::invalidateCaches($this);$this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode(['success'=>true],JSON_THROW_ON_ERROR));
            }catch(Throwable$error){$this->nativeCompanyContactFailure($error);}return;
        }
        if (staff_cant('delete', 'customers')) {
            if (!is_customer_admin($customer_id)) {
                access_denied('customers');
            }
        }
        $contact      = $this->clients_model->get_contact($id);
        $hasProposals = false;
        if ($contact && is_gdpr()) {
            if (total_rows(db_prefix() . 'proposals', ['email' => $contact->email]) > 0) {
                $hasProposals = true;
            }
        }

        $this->clients_model->delete_contact($id);
        if ($hasProposals) {
            $this->session->set_flashdata('gdpr_delete_warning', true);
        }
        redirect(admin_url('clients/client/' . $customer_id . '?group=contacts'));
    }

    public function contacts($client_id)
    {
        if($this->nativeCompanyClients()){
            try{
                if($this->input->method(true)!=='POST')throw new DomainException('company_client_invalid_input');
                require_once APPPATH.'services/CompanyNativeContacts.php';require_once APPPATH.'services/NativeCompanyCustomFields.php';
                $ctx=CompanyRequestScope::context();$issuer=$ctx->issuerId();$actor=$ctx->staffId();$client=IssuerPolicy::id($client_id);
                $service=new CompanyNativeContacts($this->db,db_prefix());$consentEnabled=$service->consentEnabled($issuer,$actor,$client);$tableInput=$this->input->post()?:[];if($consentEnabled&&isset($tableInput['order'][0]['column'])){$column=$tableInput['order'][0]['column'];if(!is_scalar($column)||!preg_match('/^(0|[1-9][0-9]{0,3})$/D',(string)$column))throw new DomainException('company_client_invalid_page');$tableInput['order'][0]['column']=(int)$column<=1?0:(int)$column-1;}$page=$service->page($issuer,$actor,$client,$tableInput,true);$meta=$service->metadata($issuer,$actor,$client);
                $fields=new NativeCompanyCustomFields($this->db,db_prefix());$definitions=$page['custom_fields'];unset($page['custom_fields']);
                $edit=(new CompanyCustomerAccess($this->db,db_prefix()))->can($issuer,$actor,$client,'edit');$data=[];
                foreach($page['rows']as$row){
                    $id=(int)$row['id'];$name=e(trim($row['firstname'].' '.$row['lastname']));
                    $imageUrl=str_starts_with((string)$row['profile_image'],'native:')?admin_url('clients/contact_profile_image/'.$client.'/'.$id):base_url('assets/images/user-placeholder.jpg');
                    $name='<img src="'.e($imageUrl).'" class="client-profile-image-small mright5"><a href="#" onclick="contact('.$client.','.$id.');return false;">'.$name.'</a>';
                    if($edit)$name.='<div class="row-options"><a href="#" onclick="contact('.$client.','.$id.');return false;">'.e(_l('edit')).'</a></div>';
                    $switch='<div class="onoffswitch"><input type="checkbox" class="onoffswitch-checkbox native-contact-status" id="c_'.$id.'" data-native-contact="'.$id.'" data-native-client="'.$client.'" data-native-revision="'.e($row['revision']).'"'.((int)$row['active']===1?' checked':'').(!$edit||(int)$meta['registration_confirmed']!==1||(int)$meta['active']!==1?' disabled':'').'><label class="onoffswitch-label" for="c_'.$id.'"></label></div><span class="hide">'.e(_l((int)$row['active']===1?'is_active_export':'is_not_active_export')).'</span>';
                    $cells=[$name,'<a href="mailto:'.e($row['email']).'">'.e($row['email']).'</a>',e($row['title']),'<a href="tel:'.e($row['phonenumber']).'">'.e($row['phonenumber']).'</a>',$switch,empty($row['last_login'])?'':e(_dt($row['last_login']))];
                    if($consentEnabled){require_once APPPATH.'services/NativeCompanyConsents.php';$consentHtml='<p class="bold"><a href="#" onclick="view_contact_consent('.$id.');return false;">'.e(_l('view_consent')).'</a></p>';foreach((new NativeCompanyConsents($this->db,db_prefix()))->purposes($issuer,$actor,$id)as$purpose)$consentHtml.='<p style="margin-bottom:0px;">'.e($purpose['name']).(!empty($purpose['consent_given'])?'<i class="fa fa-check text-success pull-right"></i>':'<i class="fa fa-remove text-danger pull-right"></i>').'</p>';array_splice($cells,1,0,[$consentHtml]);}
                    $values=$fields->values($issuer,$actor,'contacts',$id);foreach($definitions as$definition)$cells[]=e($values[(string)$definition['id']]??'');$cells['DT_RowClass']='has-row-options';$data[]=$cells;
                }
                unset($page['rows']);$page['data']=$data;$this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode($page,JSON_THROW_ON_ERROR));
            }catch(Throwable$error){$this->nativeCompanyClientFailure($error);}return;
        }
        $this->app->get_table_data('contacts', [
            'client_id' => $client_id,
        ]);
    }

    protected function nativeCustomerUploadBytes(array$file):string
    {
        if(($file['error']??null)!==UPLOAD_ERR_OK||!is_string($file['name']??null)||!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name'])||!is_int($file['size']??null)||$file['size']<1||$file['size']>NativeCompanyCustomerFiles::MAX_BYTES)throw new DomainException('native_customer_file_invalid');$bytes=file_get_contents($file['tmp_name'],false,null,0,NativeCompanyCustomerFiles::MAX_BYTES+1);if(!is_string($bytes)||strlen($bytes)!==$file['size'])throw new DomainException('native_customer_file_invalid');return$bytes;
    }
    public function upload_attachment($id)
    {
        if($this->nativeCompanyClients()){
            if($this->input->method(true)!=='POST'){show_error('Use POST.',405);return;}
            try{CompanyRequestScope::assertNativeMutation($this);require_once APPPATH.'services/NativeCompanyCustomerFiles.php';$ctx=CompanyRequestScope::context();$key=$this->input->post('request_key',false);if(!is_string($key)||array_diff(array_keys($_FILES),['file']))throw new DomainException('native_customer_file_invalid');$upload=$_FILES['file']??null;if(!is_array($upload))throw new DomainException('native_customer_file_invalid');if(!(new CompanyCustomerAccess($this->db,db_prefix()))->can($ctx->issuerId(),$ctx->staffId(),IssuerPolicy::id($id),'edit'))throw new DomainException('company_client_access_denied');$bytes=$this->nativeCustomerUploadBytes($upload);$file=(new NativeCompanyCustomerFiles($this->db,db_prefix()))->upload($ctx->issuerId(),$ctx->staffId(),IssuerPolicy::id($id),$key,$upload['name'],$bytes);$this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode(['success'=>true,'id'=>$file],JSON_THROW_ON_ERROR));}catch(Throwable$error){$this->nativeCompanyClientFailure($error);}return;
        }

        handle_client_attachments_upload($id);
    }

    public function add_external_attachment()
    {
        if($this->nativeCompanyClients()){
            if($this->input->method(true)!=='POST'){show_error('Use POST.',405);return;}
            try{CompanyRequestScope::assertNativeMutation($this);require_once APPPATH.'services/NativeCompanyCustomerFiles.php';$ctx=CompanyRequestScope::context();$files=$this->input->post('files',false);$provider=$this->input->post('external',false);$key=$this->input->post('request_key',false);if(!is_array($files)||count($files)!==1||!is_array($files[0]??null)||!is_string($files[0]['name']??null)||!is_string($files[0]['link']??null)||!is_string($provider)||!is_string($key))throw new DomainException('native_customer_file_invalid');$id=(new NativeCompanyCustomerFiles($this->db,db_prefix()))->addExternal($ctx->issuerId(),$ctx->staffId(),IssuerPolicy::id($this->input->post('clientid',false)),$key,$files[0]['name'],$provider,$files[0]['link']);$this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode(['success'=>true,'id'=>$id],JSON_THROW_ON_ERROR));}catch(Throwable$error){$this->nativeCompanyClientFailure($error);}return;
        }

        if ($this->input->post()) {
            $this->misc_model->add_attachment_to_database($this->input->post('clientid'), 'customer', $this->input->post('files'), $this->input->post('external'));
        }
    }

    public function delete_attachment($customer_id, $id)
    {
        if($this->nativeCompanyClients()){
            if($this->input->method(true)!=='POST'){show_error('Use POST.',405);return;}
            try{CompanyRequestScope::assertNativeMutation($this);require_once APPPATH.'services/NativeCompanyCustomerFiles.php';$ctx=CompanyRequestScope::context();(new NativeCompanyCustomerFiles($this->db,db_prefix()))->delete($ctx->issuerId(),$ctx->staffId(),IssuerPolicy::id($customer_id),IssuerPolicy::id($id),IssuerPolicy::id($this->input->post('revision',false)));$this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output('{"success":true}');}catch(Throwable$error){$this->nativeCompanyClientFailure($error);}return;
        }

        if (staff_can('delete',  'customers') || is_customer_admin($customer_id)) {
            $this->clients_model->delete_attachment($id);
        }
        redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
    }

    /* Delete client */
    public function delete($id)
    {
        if($this->nativeCompanyClients()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'clients','delete',[$id]);return;}
        // The legacy cascade also detaches or removes financial and other historical
        // records. A secondary company must never reach it through this controller,
        // even if the route catalogue is accidentally broadened in the future.
        if ($this->nativeCompanyClients()) {
            show_404();
            return;
        }
        if (staff_cant('delete', 'customers')) {
            access_denied('customers');
        }
        if (!$id) {
            redirect(admin_url('clients'));
        }
        $response = $this->clients_model->delete($id);
        if (is_array($response) && isset($response['referenced'])) {
            set_alert('warning', _l('customer_delete_transactions_warning', _l('invoices') . ', ' . _l('estimates') . ', ' . _l('credit_notes')));
        } elseif ($response == true) {
            set_alert('success', _l('deleted', _l('client')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('client_lowercase')));
        }
        redirect(admin_url('clients'));
    }

    /* Staff can login as client */
    public function login_as_client($id)
    {
        if (is_admin()) {
            login_as_client($id);
        }
        hooks()->do_action('after_contact_login');
        redirect(site_url());
    }

    public function get_customer_billing_and_shipping_details($id)
    {
        if($native=$this->nativeCompanyClients()){
            if(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Use GET.',405);return;}
            try{$this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode($native->billing($id),JSON_THROW_ON_ERROR));}
            catch(Throwable $error){$this->nativeCompanyClientFailure($error);}return;
        }
        echo json_encode($this->clients_model->get_customer_billing_and_shipping_details($id));
    }

    /* Change client status / active / inactive */
    public function change_contact_status($id, $status)
    {
        if($this->nativeCompanyClients()){
            try{
                CompanyRequestScope::assertNativeMutation($this);if(!in_array((string)$status,['0','1'],true))throw new DomainException('company_contact_invalid_input');
                require_once APPPATH.'services/CompanyNativeContacts.php';$ctx=CompanyRequestScope::context();$client=IssuerPolicy::id($this->input->post('client_id'));$contact=IssuerPolicy::id($id);$revision=$this->input->post('revision');if(!is_string($revision))throw new DomainException('company_client_stale');
                $service=new CompanyNativeContacts($this->db,db_prefix());$service->setActive($ctx->issuerId(),$ctx->staffId(),$client,$contact,(string)$status==='1',$revision);
                CompanyRequestScope::invalidateCaches($this);$this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode(['success'=>true],JSON_THROW_ON_ERROR));
            }catch(Throwable$error){$this->nativeCompanyClientFailure($error);}return;
        }
        if (staff_can('edit',  'customers') || is_customer_admin(get_user_id_by_contact_id($id))) {
            if ($this->input->is_ajax_request()) {
                $this->clients_model->change_contact_status($id, $status);
            }
        }
    }

    /* Change client status / active / inactive */
    public function change_client_status($id, $status)
    {
        if($this->nativeCompanyClients()){
            try{CompanyRequestScope::assertNativeMutation($this);if(!in_array((string)$status,['0','1'],true))throw new DomainException('company_client_invalid_input');$revision=$this->input->post('revision');if(!is_string($revision))throw new DomainException('company_client_stale');
                $ctx=CompanyRequestScope::context();(new CompanyClients($this->db,db_prefix()))->setActive($ctx->issuerId(),$ctx->staffId(),IssuerPolicy::id($id),(string)$status==='1',$revision);
                CompanyRequestScope::invalidateCaches($this);$this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode(['success'=>true],JSON_THROW_ON_ERROR));
            }catch(Throwable$error){$this->nativeCompanyClientFailure($error);}return;
        }
        if (staff_cant('edit',  'customers') && !is_customer_admin(get_user_id_by_contact_id($id))) {
            ajax_access_denied();
        }

        if ($this->input->is_ajax_request()) {
            $this->clients_model->change_client_status($id, $status);
        }
    }

    /* Zip function for credit notes */
    public function zip_credit_notes($id)
    {
        if($this->nativeCompanyClients()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'clients','zip_credit_notes',[$id]);return;}
        $has_permission_view = staff_can('view',  'credit_notes');

        if (!$has_permission_view && staff_cant('view_own', 'credit_notes')) {
            access_denied('Zip Customer Credit Notes');
        }

        if ($this->input->post()) {
            $this->load->library('app_bulk_pdf_export', [
                'export_type'       => 'credit_notes',
                'status'            => $this->input->post('credit_note_zip_status'),
                'date_from'         => $this->input->post('zip-from'),
                'date_to'           => $this->input->post('zip-to'),
                'redirect_on_error' => admin_url('clients/client/' . $id . '?group=credit_notes'),
            ]);

            $this->app_bulk_pdf_export->set_client_id($id);
            $this->app_bulk_pdf_export->in_folder($this->input->post('file_name'));
            $this->app_bulk_pdf_export->export();
        }
    }

    public function zip_invoices($id)
    {
        if($this->nativeCompanyClients()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'clients','zip_invoices',[$id]);return;}
        $has_permission_view = staff_can('view',  'invoices');
        if (!$has_permission_view && staff_cant('view_own', 'invoices')
            && get_option('allow_staff_view_invoices_assigned') == '0') {
            access_denied('Zip Customer Invoices');
        }

        if ($this->input->post()) {
            $this->load->library('app_bulk_pdf_export', [
                'export_type'       => 'invoices',
                'status'            => $this->input->post('invoice_zip_status'),
                'date_from'         => $this->input->post('zip-from'),
                'date_to'           => $this->input->post('zip-to'),
                'redirect_on_error' => admin_url('clients/client/' . $id . '?group=invoices'),
            ]);

            $this->app_bulk_pdf_export->set_client_id($id);
            $this->app_bulk_pdf_export->in_folder($this->input->post('file_name'));
            $this->app_bulk_pdf_export->export();
        }
    }

    /* Since version 1.0.2 zip client estimates */
    public function zip_estimates($id)
    {
        if($this->nativeCompanyClients()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'clients','zip_estimates',[$id]);return;}
        $has_permission_view = staff_can('view',  'estimates');
        if (!$has_permission_view && staff_cant('view_own', 'estimates')
            && get_option('allow_staff_view_estimates_assigned') == '0') {
            access_denied('Zip Customer Estimates');
        }

        if ($this->input->post()) {
            $this->load->library('app_bulk_pdf_export', [
                'export_type'       => 'estimates',
                'status'            => $this->input->post('estimate_zip_status'),
                'date_from'         => $this->input->post('zip-from'),
                'date_to'           => $this->input->post('zip-to'),
                'redirect_on_error' => admin_url('clients/client/' . $id . '?group=estimates'),
            ]);

            $this->app_bulk_pdf_export->set_client_id($id);
            $this->app_bulk_pdf_export->in_folder($this->input->post('file_name'));
            $this->app_bulk_pdf_export->export();
        }
    }

    public function zip_payments($id)
    {
        if($this->nativeCompanyClients()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'clients','zip_payments',[$id]);return;}
        $has_permission_view = staff_can('view',  'payments');

        if (!$has_permission_view && staff_cant('view_own', 'invoices')
            && get_option('allow_staff_view_invoices_assigned') == '0') {
            access_denied('Zip Customer Payments');
        }

        $this->load->library('app_bulk_pdf_export', [
                'export_type'       => 'payments',
                'payment_mode'      => $this->input->post('paymentmode'),
                'date_from'         => $this->input->post('zip-from'),
                'date_to'           => $this->input->post('zip-to'),
                'redirect_on_error' => admin_url('clients/client/' . $id . '?group=payments'),
            ]);

        $this->app_bulk_pdf_export->set_client_id($id);
        $this->app_bulk_pdf_export->set_client_id_column(db_prefix() . 'clients.userid');
        $this->app_bulk_pdf_export->in_folder($this->input->post('file_name'));
        $this->app_bulk_pdf_export->export();
    }

    public function import()
    {
        if (class_exists('CompanyRequestScope', false) && CompanyRequestScope::nativeFor($this)) {
            require_once APPPATH . 'services/NativeCompanyClientImportHttp.php';
            (new NativeCompanyClientImportHttp($this))->handle();
            return;
        }
        if (staff_cant('create', 'customers')) {
            access_denied('customers');
        }

        $dbFields = $this->db->list_fields(db_prefix() . 'contacts');
        foreach ($dbFields as $key => $contactField) {
            if ($contactField == 'phonenumber') {
                $dbFields[$key] = 'contact_phonenumber';
            }
        }

        $dbFields = array_merge($dbFields, $this->db->list_fields(db_prefix() . 'clients'));

        $this->load->library('import/import_customers', [], 'import');

        $this->import->setDatabaseFields($dbFields)
                     ->setCustomFields(get_custom_fields('customers'));

        if ($this->input->post('download_sample') === 'true') {
            $this->import->downloadSample();
        }

        if ($this->input->post()
            && isset($_FILES['file_csv']['name']) && $_FILES['file_csv']['name'] != '') {
            $this->import->setSimulation($this->input->post('simulate'))
                          ->setTemporaryFileLocation($_FILES['file_csv']['tmp_name'])
                          ->setFilename($_FILES['file_csv']['name'])
                          ->perform();


            $data['total_rows_post'] = $this->import->totalRows();

            if (!$this->import->isSimulation()) {
                set_alert('success', _l('import_total_imported', $this->import->totalImported()));
            }
        }

        $data['groups']    = $this->clients_model->get_groups();
        $data['title']     = _l('import');
        $data['bodyclass'] = 'dynamic-create-groups';
        $this->load->view('admin/clients/import', $data);
    }

    public function groups()
    {
        if($native=$this->nativeCompanyClients()){
            try{
                $context=CompanyRequestScope::context();$service=new CompanyCustomerGroups($this->db,db_prefix());
                if($this->input->is_ajax_request()){
                    if($this->input->method(true)!=='POST'){show_error('Use POST.',405);return;}
                    CompanyRequestScope::assertNativeMutation($this);
                    $q=NativeCompanyClients::request($this->input->post()?:[]);$all=$service->all($context->issuerId(),$context->staffId());
                    $filtered=array_values(array_filter($all,static fn($row)=>$q['search']===''||stripos($row['name'],$q['search'])!==false));
                    if($q['direction']==='desc')$filtered=array_reverse($filtered);$data=[];
                    foreach(array_slice($filtered,$q['start'],$q['length']) as $row){
                        $options='<a href="#" data-toggle="modal" data-target="#customer_group_modal" data-id="'.(int)$row['id'].'" data-revision="'.e($row['revision']).'" class="btn btn-default btn-icon"><i class="fa-regular fa-pen-to-square"></i><span class="sr-only">'.e(_l('edit')).'</span></a>';
                        $options.=form_open(admin_url('clients/delete_group/'.(int)$row['id']),['class'=>'inline-block']).form_hidden('revision',$row['revision']).'<button type="submit" class="btn btn-danger btn-icon"><i class="fa fa-remove"></i><span class="sr-only">'.e(_l('delete')).'</span></button>'.form_close();
                        $data[]=[e($row['name']),$options];
                    }
                    $this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode(['draw'=>$q['draw'],'recordsTotal'=>count($all),'recordsFiltered'=>count($filtered),'data'=>$data],JSON_THROW_ON_ERROR));return;
                }
                if(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Use GET.',405);return;}
                $native->capabilities();$this->load->view('admin/clients/groups_manage',['title'=>_l('customer_groups'),'native_customers'=>true]);
            }catch(Throwable $error){$this->nativeCompanyClientFailure($error);}return;
        }
        if (!is_admin()) {
            access_denied('Customer Groups');
        }
        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data('customers_groups');
        }
        $data['title'] = _l('customer_groups');
        $this->load->view('admin/clients/groups_manage', $data);
    }

    public function group()
    {
        if($native=$this->nativeCompanyClients()){
            try{
                CompanyRequestScope::assertNativeMutation($this);$context=CompanyRequestScope::context();
                $id=$this->input->post('id',false);$name=$this->input->post('name',false);$revision=$this->input->post('revision',false);
                if(!is_string($name)||($revision!==null&&!is_string($revision)))throw new DomainException('company_client_invalid_input');
                $saved=(new CompanyCustomerGroups($this->db,db_prefix()))->save($context->issuerId(),$context->staffId(),$name,$id===null||$id===''?null:IssuerPolicy::id($id),$revision);
                $this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode(['success'=>true,'message'=>_l('updated_successfully',_l('customer_group')),'id'=>$saved,'name'=>$name],JSON_THROW_ON_ERROR));
            }catch(Throwable $error){$this->nativeCompanyClientFailure($error);}return;
        }
        if (!is_admin() && get_option('staff_members_create_inline_customer_groups') == '0') {
            access_denied('Customer Groups');
        }

        if ($this->input->is_ajax_request()) {
            $data = $this->input->post();
            if ($data['id'] == '') {
                $id      = $this->clients_model->add_group($data);
                $message = $id ? _l('added_successfully', _l('customer_group')) : '';
                echo json_encode([
                    'success' => $id ? true : false,
                    'message' => $message,
                    'id'      => $id,
                    'name'    => $data['name'],
                ]);
            } else {
                $success = $this->clients_model->edit_group($data);
                $message = '';
                if ($success == true) {
                    $message = _l('updated_successfully', _l('customer_group'));
                }
                echo json_encode([
                    'success' => $success,
                    'message' => $message,
                ]);
            }
        }
    }

    public function delete_group($id)
    {
        if($native=$this->nativeCompanyClients()){
            try{
                CompanyRequestScope::assertNativeMutation($this);$context=CompanyRequestScope::context();$revision=$this->input->post('revision',false);
                if(!is_string($revision))throw new DomainException('company_client_stale');
                (new CompanyCustomerGroups($this->db,db_prefix()))->delete($context->issuerId(),$context->staffId(),IssuerPolicy::id($id),$revision);
                redirect(admin_url('clients/groups'),'location',303);
            }catch(Throwable $error){$this->nativeCompanyClientFailure($error);}return;
        }
        if (!is_admin()) {
            access_denied('Delete Customer Group');
        }
        if (!$id) {
            redirect(admin_url('clients/groups'));
        }
        $response = $this->clients_model->delete_group($id);
        if ($response == true) {
            set_alert('success', _l('deleted', _l('customer_group')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('customer_group_lowercase')));
        }
        redirect(admin_url('clients/groups'));
    }

    public function bulk_action()
    {
        if($this->nativeCompanyClients()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'clients','bulk_action',[]);return;}
        hooks()->do_action('before_do_bulk_action_for_customers');
        $total_deleted = 0;
        if ($this->input->post()) {
            $ids    = $this->input->post('ids');
            $groups = $this->input->post('groups');

            if (is_array($ids)) {
                foreach ($ids as $id) {
                    if ($this->input->post('mass_delete')) {
                        if ($this->clients_model->delete($id)) {
                            $total_deleted++;
                        }
                    } else {
                        if (!is_array($groups)) {
                            $groups = false;
                        }
                        $this->client_groups_model->sync_customer_groups($id, $groups);
                    }
                }
            }
        }

        if ($this->input->post('mass_delete')) {
            set_alert('success', _l('total_clients_deleted', $total_deleted));
        }
    }

    private function nativeVaultAction(string $action,$id=null):bool
    {
        if(!$this->nativeCompanyClients())return false;
        try{
            require_once APPPATH.'services/NativeCompanyCustomerVault.php';$ctx=CompanyRequestScope::context();$service=new NativeCompanyCustomerVault($this->db,db_prefix());$issuer=$ctx->issuerId();$actor=$ctx->staffId();
            if($action==='get'){
                if(!in_array($this->input->method(true),['GET','HEAD'],true))throw new DomainException('native_vault_invalid');$data=$service->get($issuer,$actor,IssuerPolicy::id($id));$this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode($data,JSON_THROW_ON_ERROR));return true;
            }
            CompanyRequestScope::assertNativeMutation($this);$this->load->library('encryption');$revision=$this->input->post('revision');
            if($action==='reveal'){
                if(!is_string($revision))throw new DomainException('company_client_stale');$password=$this->input->post('user_password',false);if(!is_string($password))throw new DomainException('native_vault_reauthentication_required');
                $value=$service->reveal($issuer,$actor,IssuerPolicy::id($this->input->post('id')),$revision,$password,fn($plain,$hash)=>app_hasher()->CheckPassword($plain,$hash),fn($cipher)=>$this->encryption->decrypt($cipher));
                $this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode(['password'=>e($value)],JSON_THROW_ON_ERROR));return true;
            }
            $entry=$action==='create'?null:$service->get($issuer,$actor,IssuerPolicy::id($id));$client=$entry?(int)$entry['customer_id']:IssuerPolicy::id($id);
            if($action==='delete'){if(!is_string($revision))throw new DomainException('company_client_stale');$service->delete($issuer,$actor,(int)$id,$revision);}
            else{$input=$this->input->post()?:[];$input['password']=$this->input->post('password',false)??'';foreach(['id','revision','_native_company_csrf','_company_context','fakeusernameremembered','fakepasswordremembered',$this->security->get_csrf_token_name()]as$key)unset($input[$key]);$service->save($issuer,$actor,$client,$input,fn($plain)=>$this->encryption->encrypt($plain),$entry?(int)$id:null,is_string($revision)?$revision:null);}
            CompanyRequestScope::invalidateCaches($this);redirect(admin_url('clients/client/'.$client.'?group=vault'),'location',303);
        }catch(Throwable$error){$this->nativeCompanyClientFailure($error);}return true;
    }

    public function vault_entry_create($customer_id)
    {
        if($this->nativeVaultAction('create',$customer_id))return;
        if (!\app\services\ClientIssuerScope::isLegacyClient($this->db, db_prefix(), $customer_id)) { show_404(); return; }
        $data = $this->input->post();

        if (isset($data['fakeusernameremembered'])) {
            unset($data['fakeusernameremembered']);
        }

        if (isset($data['fakepasswordremembered'])) {
            unset($data['fakepasswordremembered']);
        }

        unset($data['id']);
        $data['creator']      = get_staff_user_id();
        $data['creator_name'] = get_staff_full_name($data['creator']);
        $data['description']  = nl2br($data['description']);
        $data['password']     = $this->encryption->encrypt($this->input->post('password', false));

        if (empty($data['port'])) {
            unset($data['port']);
        }

        $this->clients_model->vault_entry_create($data, $customer_id);
        set_alert('success', _l('added_successfully', _l('vault_entry')));
        redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
    }

    public function vault_entry_update($entry_id)
    {
        if($this->nativeVaultAction('update',$entry_id))return;
        $entry = $this->clients_model->get_vault_entry($entry_id);
        if (!$entry) { show_404(); return; }

        if ($entry->creator == get_staff_user_id() || is_admin()) {
            $data = $this->input->post();

            if (isset($data['fakeusernameremembered'])) {
                unset($data['fakeusernameremembered']);
            }
            if (isset($data['fakepasswordremembered'])) {
                unset($data['fakepasswordremembered']);
            }

            $data['last_updated_from'] = get_staff_full_name(get_staff_user_id());
            $data['description']       = nl2br($data['description']);

            if (!empty($data['password'])) {
                $data['password'] = $this->encryption->encrypt($this->input->post('password', false));
            } else {
                unset($data['password']);
            }

            if (empty($data['port'])) {
                unset($data['port']);
            }

            $this->clients_model->vault_entry_update($entry_id, $data);
            set_alert('success', _l('updated_successfully', _l('vault_entry')));
        }
        redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
    }

    public function vault_entry_delete($id)
    {
        if($this->nativeVaultAction('delete',$id))return;
        $entry = $this->clients_model->get_vault_entry($id);
        if (!$entry) { show_404(); return; }
        if ($entry->creator == get_staff_user_id() || is_admin()) {
            $this->clients_model->vault_entry_delete($id);
        }
        redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
    }

    public function vault_encrypt_password()
    {
        if($this->nativeVaultAction('reveal',null))return;
        $id            = $this->input->post('id');
        $user_password = $this->input->post('user_password', false);
        $user          = $this->staff_model->get(get_staff_user_id());

        if (!app_hasher()->CheckPassword($user_password, $user->password)) {
            header('HTTP/1.1 401 Unauthorized');
            echo json_encode(['error_msg' => _l('vault_password_user_not_correct')]);
            die;
        }

        $vault    = $this->clients_model->get_vault_entry($id);
        if (!$vault) { show_404(); return; }
        $password = $this->encryption->decrypt($vault->password);

        $password = html_escape($password);

        // Failed to decrypt
        if (!$password) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad error');
            echo json_encode(['error_msg' => _l('failed_to_decrypt_password')]);
            die;
        }

        echo json_encode(['password' => $password]);
    }

    public function get_vault_entry($id)
    {
        if($this->nativeVaultAction('get',$id))return;
        $entry = $this->clients_model->get_vault_entry($id);
        if (!$entry) { show_404(); return; }
        unset($entry->password);
        $entry->description = clear_textarea_breaks($entry->description);
        echo json_encode($entry);
    }

    public function statement_pdf()
    {
        if($this->nativeCompanyClients()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'clients','statement_pdf',[]);return;}
        $customer_id = $this->input->get('customer_id');
        if (!\app\services\ClientIssuerScope::isLegacyClient($this->db, db_prefix(), $customer_id)) { show_404(); return; }

        if (staff_cant('view', 'invoices') && staff_cant('view', 'payments')) {
            set_alert('danger', _l('access_denied'));
            redirect(admin_url('clients/client/' . $customer_id));
        }

        $from = $this->input->get('from');
        $to   = $this->input->get('to');

        $data['statement'] = $this->clients_model->get_statement($customer_id, to_sql_date($from), to_sql_date($to));

        try {
            $pdf = statement_pdf($data['statement']);
        } catch (Exception $e) {
            $message = $e->getMessage();
            echo $message;
            if (strpos($message, 'Unable to get the size of the image') !== false) {
                show_pdf_unable_to_get_image_size_error();
            }
            die;
        }

        $type = 'D';
        if ($this->input->get('print')) {
            $type = 'I';
        }

        $pdf->Output(slug_it(_l('customer_statement') . '-' . $data['statement']['client']->company) . '.pdf', $type);
    }

    public function send_statement()
    {
        if($this->nativeCompanyClients()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'clients','send_statement',[]);return;}
        $customer_id = $this->input->get('customer_id');
        if (!\app\services\ClientIssuerScope::isLegacyClient($this->db, db_prefix(), $customer_id)) { show_404(); return; }

        if (staff_cant('view', 'invoices') && staff_cant('view', 'payments')) {
            set_alert('danger', _l('access_denied'));
            redirect(admin_url('clients/client/' . $customer_id));
        }

        $from = $this->input->get('from');
        $to   = $this->input->get('to');

        $send_to = $this->input->post('send_to');
        $cc      = $this->input->post('cc');

        $success = $this->clients_model->send_statement_to_email($customer_id, $send_to, $from, $to, $cc);
        // In case client use another language
        load_admin_language();
        if ($success) {
            set_alert('success', _l('statement_sent_to_client_success'));
        } else {
            set_alert('danger', _l('statement_sent_to_client_fail'));
        }

        redirect(admin_url('clients/client/' . $customer_id . '?group=statement'));
    }

    public function statement()
    {
        if($this->nativeCompanyClients()){require_once APPPATH.'services/NativeCompanyParityHttp.php';NativeCompanyParityHttp::handle($this,'clients','statement',[]);return;}
        if (staff_cant('view', 'invoices') && staff_cant('view', 'payments')) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad error');
            echo _l('access_denied');
            die;
        }

        $customer_id = $this->input->get('customer_id');
        if (!\app\services\ClientIssuerScope::isLegacyClient($this->db, db_prefix(), $customer_id)) { show_404(); return; }
        $from        = $this->input->get('from');
        $to          = $this->input->get('to');

        $data['statement'] = $this->clients_model->get_statement($customer_id, to_sql_date($from), to_sql_date($to));

        $data['from'] = $from;
        $data['to']   = $to;

        $viewData['html'] = $this->load->view('admin/clients/groups/_statement', $data, true);

        echo json_encode($viewData);
    }
}
