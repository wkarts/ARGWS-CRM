<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Gdpr extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if(CompanyRequestScope::nativeFor($this))return;
        $notAdminAllowed = ['lead_consent_opt_action', 'contact_consent_opt_action'];
        if (!is_admin() && !in_array($this->uri->segment(3), $notAdminAllowed)) {
            access_denied('GDPR');
        }
        $this->load->model('gdpr_model');
    }

    private function nativeConsents():?NativeCompanyConsents
    {
        if(!CompanyRequestScope::nativeFor($this))return null;
        require_once APPPATH.'services/NativeCompanyConsents.php';return new NativeCompanyConsents($this->db,db_prefix());
    }
    private function nativeConsentFailure(Throwable $error):void
    {
        $reason=$error instanceof DomainException?$error->getMessage():'';
        show_error('Não foi possível concluir esta operação nesta empresa.',$reason==='company_client_stale'?409:(in_array($reason,['company_client_access_denied','native_gdpr_denied'],true)?403:422));
    }
    public function index()
    {
        if(CompanyRequestScope::nativeFor($this)){
            try{
                require_once APPPATH.'services/NativeCompanyGdprSettings.php';
                $ctx=CompanyRequestScope::context();$service=new NativeCompanyGdprSettings($this->db,db_prefix());
                $service->authorize($ctx->issuerId(),$ctx->staffId());
                $page=$this->input->get('page')?:'general';if(!is_string($page))throw new DomainException('native_gdpr_invalid');NativeCompanyGdprSettings::fields($page);
                $data=['page'=>$page,'save'=>true,'title'=>_l('gdpr'),'native_company_gdpr'=>true,'native_gdpr_csrf'=>CompanyRequestScope::nativeCsrfToken($this)];
                if($page==='consent')$data['consent_purposes']=$this->nativeConsents()->purposes($ctx->issuerId(),$ctx->staffId());
                // Never replace pending requests with an empty list or query the global model.
                if($page==='forgotten'){
                    require_once APPPATH.'services/NativeCompanyPrivacyRequests.php';
                    $data['requests']=(new NativeCompanyPrivacyRequests($this->db,db_prefix()))->list($ctx->issuerId(),$ctx->staffId());
                    $data['not_pending_requests']=count(array_filter($data['requests'],static fn(array $row):bool=>$row['status']==='pending'));
                }
                $this->load->view('admin/gdpr/index',$data);
            }catch(Throwable $error){$this->nativeConsentFailure($error);}return;
        }
        $data['page'] = $this->input->get('page') ? $this->input->get('page') : 'general';
        $data['save'] = true;
        if ($data['page'] == 'forgotten') {
            $data['requests'] = $this->gdpr_model->get_removal_requests();
            $data['not_pending_requests'] = total_rows(db_prefix().'gdpr_requests', array('status '=>'pending'));
        } elseif ($data['page'] == 'consent') {
            $data['consent_purposes'] = $this->gdpr_model->get_consent_purposes();
        }
        $data['title'] = _l('gdpr');
        $this->load->view('admin/gdpr/index', $data);
    }

    public function save()
    {
        if(CompanyRequestScope::nativeFor($this)){
            try{
                CompanyRequestScope::assertNativeMutation($this);require_once APPPATH.'services/NativeCompanyGdprSettings.php';
                $ctx=CompanyRequestScope::context();$page=$this->input->get('page')?:'general';$input=$this->input->post('settings',false);
                if(!is_string($page)||!is_array($input))throw new DomainException('native_gdpr_invalid');
                (new NativeCompanyGdprSettings($this->db,db_prefix()))->save($ctx->issuerId(),$ctx->staffId(),$page,$input);
                redirect(admin_url('gdpr/index?page='.rawurlencode($page)),'location',303);
            }catch(Throwable $error){$this->nativeConsentFailure($error);}return;
        }
        $page = $this->input->get('page') ? $this->input->get('page') : 'general';
        $data = $this->input->post('settings');

        //XSS filtered from tinymce
        $noXSS = ['terms_and_conditions', 'privacy_policy', 'gdpr_consent_public_page_top_block', 'gdpr_page_top_information_block'];

        if($page == 'portability') {
            $data['gdpr_lead_data_portability_allowed'] = isset($data['gdpr_lead_data_portability_allowed']) ? $data['gdpr_lead_data_portability_allowed'] : array();
            $data['gdpr_lead_data_portability_allowed'] = serialize($data['gdpr_lead_data_portability_allowed']);

            $data['gdpr_contact_data_portability_allowed'] = isset($data['gdpr_contact_data_portability_allowed']) ? $data['gdpr_contact_data_portability_allowed'] : array();
            $data['gdpr_contact_data_portability_allowed'] = serialize($data['gdpr_contact_data_portability_allowed']);
        }

        foreach ($data as $name => $val) {
            if (in_array($name, $noXSS)) {
                $val = html_purify($this->input->post('settings', false)[$name]);
            }
            update_option($name, $val);
        }

        redirect(admin_url('gdpr/index?page=' . $page));
    }

    public function change_removal_request_status($id, $status)
    {
        if(CompanyRequestScope::nativeFor($this)){
            try{
                CompanyRequestScope::assertNativeMutation($this);
                require_once APPPATH.'services/NativeCompanyPrivacyRequests.php';
                $ctx=CompanyRequestScope::context();$revision=$this->input->post('revision');
                if(!is_scalar($revision)||!preg_match('/^(?:[1-9][0-9]{0,8}|[a-f0-9]{64})$/D',(string)$revision))throw new DomainException('company_client_stale');
                $id=IssuerPolicy::id($id);$service=new NativeCompanyPrivacyRequests($this->db,db_prefix());
                $service->status($ctx->issuerId(),$ctx->staffId(),$id,(string)$status,(string)$revision);
                // Obtain the committed revision, including an idempotent same-status save.
                foreach($service->list($ctx->issuerId(),$ctx->staffId()) as $row)if((int)$row['id']===$id){
                    echo json_encode(['success'=>true,'revision'=>(string)$row['revision'],'status'=>$row['status']],JSON_THROW_ON_ERROR);return;
                }
                throw new RuntimeException('issuer_privacy_write_unconfirmed');
            }catch(Throwable $error){$this->nativeConsentFailure($error);}return;
        }
        $this->gdpr_model->update($id, ['status' => $status]);
    }

    public function consent_purpose($id = false)
    {
        if($service=$this->nativeConsents()){
            try{$ctx=CompanyRequestScope::context();$issuer=$ctx->issuerId();$actor=$ctx->staffId();
                if($this->input->method(true)==='POST'){
                    CompanyRequestScope::assertNativeMutation($this);$input=$this->input->post()?:[];$revision=$input['native_consent_revision']??null;
                    unset($input['native_consent_revision'],$input['_native_company_csrf'],$input['_company_context'],$input[$this->security->get_csrf_token_name()]);
                    if($id!==false&&$id!==''&&(!is_scalar($revision)||!preg_match('/^[1-9][0-9]{0,8}$/D',(string)$revision)))throw new DomainException('company_client_stale');
                    $service->savePurpose($issuer,$actor,$input,$id===false||$id===''?null:$id,$revision===null?null:(int)$revision);
                    redirect(admin_url('gdpr/index?page=consent'),'location',303);return;
                }
                if(!in_array($this->input->method(true),['GET','HEAD'],true))throw new DomainException('native_consent_invalid');
                $data=['native_company_consent'=>true,'native_consent_csrf'=>CompanyRequestScope::nativeCsrfToken($this)];
                if($id!==false&&$id!=='')$data['purpose']=(object)$service->getPurpose($issuer,$actor,$id);else$service->purposes($issuer,$actor);
                $this->load->view('admin/gdpr/pages/includes/consent',$data);
            }catch(Throwable$error){$this->nativeConsentFailure($error);}return;
        }
        if ($this->input->post()) {
            $data = $this->input->post();

            $data['description'] = nl2br($data['description']);

            if (!$id) {
                $this->gdpr_model->add_consent_purpose(['name' => $data['name'], 'description' => $data['description']]);
            } else {
                $update = ['description' => $data['description']];
                if (isset($data['name'])) {
                    $update['name'] = $data['name'];
                }
                $this->gdpr_model->update_consent_purpose($id, $update);
            }
            redirect(admin_url('gdpr/index?page=consent'));
        }

        $data = [];
        if (!empty($id)) {
            $data['purpose'] = $this->gdpr_model->get_consent_purpose($id);
        }
        $this->load->view('admin/gdpr/pages/includes/consent', $data);
    }

    public function delete_consent_purpose($id)
    {
        if($service=$this->nativeConsents()){
            try{CompanyRequestScope::assertNativeMutation($this);$ctx=CompanyRequestScope::context();$revision=$this->input->post('native_consent_revision');if(!is_scalar($revision)||!preg_match('/^[1-9][0-9]{0,8}$/D',(string)$revision))throw new DomainException('company_client_stale');
                $service->deletePurpose($ctx->issuerId(),$ctx->staffId(),$id,(int)$revision);redirect(admin_url('gdpr/index?page=consent'),'location',303);
            }catch(Throwable$error){$this->nativeConsentFailure($error);}return;
        }
        $this->gdpr_model->delete_consent_purpose($id);
        redirect(admin_url('gdpr/index?page=consent'));
    }

    public function enable()
    {
        if(CompanyRequestScope::nativeFor($this)){
            try{CompanyRequestScope::assertNativeMutation($this);require_once APPPATH.'services/NativeCompanyGdprSettings.php';$ctx=CompanyRequestScope::context();
                (new NativeCompanyGdprSettings($this->db,db_prefix()))->save($ctx->issuerId(),$ctx->staffId(),'general',['enable_gdpr'=>'1']);
                redirect(admin_url('gdpr'),'location',303);
            }catch(Throwable $error){$this->nativeConsentFailure($error);}return;
        }
        update_option('enable_gdpr', 1);
        redirect(admin_url('gdpr'));
    }

    public function contact_consent_opt_action()
    {
        if($service=$this->nativeConsents()){
            try{CompanyRequestScope::assertNativeMutation($this);$ctx=CompanyRequestScope::context();$input=$this->input->post()?:[];$contact=IssuerPolicy::id($input['contact_id']??null);$revision=$input['native_consent_revision']??null;if(!is_string($revision)||!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('company_client_stale');
                unset($input['contact_id'],$input['native_consent_revision'],$input['_native_company_csrf'],$input['_company_context'],$input[$this->security->get_csrf_token_name()]);
                $service->record($ctx->issuerId(),$ctx->staffId(),$contact,$input,$revision,$this->input->ip_address());
                $owner=IssuerStorage::query($this->db,'SELECT userid FROM '.db_prefix().'contacts WHERE id=?',[$contact])->row_array();redirect(admin_url('clients/client/'.(int)$owner['userid'].'?group=contacts&consents='.$contact),'location',303);
            }catch(Throwable$error){$this->nativeConsentFailure($error);}return;
        }
        if ($this->input->post()) {
            $data       = $this->input->post();
            $contact_id = $data['contact_id'];
            $client_id  = get_user_id_by_contact_id($contact_id);

            if (staff_cant('view', 'customers')) {
                if (!is_customer_admin($client_id)) {
                    access_denied('Contact Consents Action');
                }
            }

            $data               = $this->prepare_consent_opt_action_data($data);
            $data['contact_id'] = $contact_id;
            $this->gdpr_model->add_consent($data);

            if (strpos($_SERVER['HTTP_REFERER'], 'all_contacts') !== false) {
                redirect(admin_url('clients/all_contacts?&consents=' . $contact_id));
            } else {
                redirect(admin_url('clients/client/' . $client_id . '?group=contacts&consents=' . $contact_id));
            }
        }
    }

    public function lead_consent_opt_action()
    {
        if ($this->input->post()) {
            $data    = $this->input->post();
            $lead_id = $data['lead_id'];

            $this->load->model('leads_model');
            if (!is_staff_member() || !$this->leads_model->staff_can_access_lead($lead_id)) {
                ajax_access_denied();
            }

            $data            = $this->prepare_consent_opt_action_data($data);
            $data['lead_id'] = $lead_id;
            $this->gdpr_model->add_consent($data);
            echo json_encode(['lead_id' => $lead_id]);
        }
    }

    private function prepare_consent_opt_action_data($data)
    {
        return [
            'action'                     => $data['action'],
            'purpose_id'                 => $data['purpose_id'],
            'description'                => nl2br($data['description']),
            'opt_in_purpose_description' => isset($data['opt_in_purpose_description']) ? nl2br($data['opt_in_purpose_description']) : '',
            'staff_name'                 => get_staff_full_name(),
        ];
    }
}
