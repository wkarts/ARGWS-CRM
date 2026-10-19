<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Company_onboarding extends AdminController
{
    public function index($key=null)
    {
        if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto de empresa indisponível.',503);return;}
        require_once APPPATH.'services/CompanyOnboarding.php';
        require_once APPPATH.'services/CompanySettingsFormats.php';
        $resources=[
            'settings'=>['name'=>'Definições','capabilities'=>['view'=>'Consultar','edit'=>'Editar']],
            'email_templates'=>['name'=>'Modelos de e-mail','capabilities'=>['view'=>'Consultar','edit'=>'Editar']],
            'banco_brasil'=>['name'=>'Banco do Brasil','capabilities'=>['view'=>'Consultar','manage'=>'Configurar','import'=>'Importar retorno observacional']],
        ];
        $service=new CompanyOnboarding($this->db,db_prefix(),array_map(static fn($r)=>array_keys($r['capabilities']),$resources));
        $actor=CompanyRequestScope::context()->staffId();$error=null;$draft=null;
        $this->output->set_header('Cache-Control: no-store');
        $token=$this->session->userdata('company_onboarding_csrf');
        if(!is_string($token)||strlen($token)!==64){$token=bin2hex(random_bytes(32));$this->session->set_userdata('company_onboarding_csrf',$token);}
        try{
            // Even GET requires a live installation-master identity, never an administrator flag alone.
            $draft=$key===null?$service->pending($actor):$service->read($actor,$key);
            if($this->input->method(true)==='POST'){
                $submitted=$this->input->post('company_onboarding_csrf',false);
                if(!is_string($submitted)||!hash_equals($token,$submitted)){show_error('Recarregue o formulário antes de salvar.',403);return;}
                $action=$this->input->post('action',false);
                if($action==='begin'&&$key===null){
                    $requestKey=$this->session->userdata('company_onboarding_request_key');
                    if(!is_string($requestKey)||!preg_match('/^[a-f0-9]{64}$/D',$requestKey)){throw new DomainException('issuer_wizard_invalid_draft');}
                    $draft=$service->begin($actor,$requestKey);
                }elseif($action==='advance'&&$draft){
                    $input=$this->input->post('wizard',false);
                    if(!is_array($input)){throw new DomainException('issuer_wizard_invalid_input');}
                    if($this->input->post('step',false)==='3'){
                        $selected=$input['selected_staff']??[];unset($input['selected_staff']);$assignments=$input['staff']??[];
                        if($assignments===''){$assignments=[];}
                        if(!is_array($selected)||!is_array($assignments)){throw new DomainException('issuer_wizard_invalid_staff');}
                        foreach($selected as $enabled){if($enabled!=='1'){throw new DomainException('issuer_wizard_invalid_staff');}}
                        if(array_diff_key($selected,$assignments)){throw new DomainException('issuer_wizard_invalid_staff');}
                        $input=['staff'=>array_intersect_key($assignments,$selected)];
                    }
                    $draft=$service->advance($actor,$draft['draft_key'],$this->input->post('revision',false),$this->input->post('step',false),$input);
                }elseif($action==='complete'&&$draft){
                    $service->complete($actor,$draft['draft_key'],$this->input->post('revision',false));
                    $draft=$service->read($actor,$draft['draft_key']);
                }else{throw new DomainException('issuer_wizard_invalid_input');}
                CompanyRequestScope::invalidateCaches($this);
                redirect(CompanyRequestScope::adminUrl($this,'company_onboarding/index/'.$draft['draft_key']),'location',303);return;
            }elseif(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
        }catch(DomainException $exception){
            if($exception->getMessage()==='issuer_access_denied'){show_error('Somente o responsável ativo da instalação pode criar empresas.',403);return;}
            if($exception->getMessage()==='issuer_wizard_stale'){$this->output->set_status_header(409);$error='Este rascunho mudou em outra aba. Recarregue antes de salvar. Nenhum dado desta tentativa foi aplicado.';}
            else{$this->output->set_status_header(422);$error='Não foi possível salvar. Confira os dados, o documento fiscal e as permissões dos colaboradores. O rascunho salvo foi preservado.';}
        }catch(Throwable $exception){
            log_message('error','Company onboarding failed; no draft values logged.');
            $this->output->set_status_header(503);$error='Não foi possível confirmar a gravação. Recarregue e tente novamente; a empresa só é criada quando toda a transação é confirmada.';
        }
        $step=$draft?(int)$draft['step']:0;
        $requested=$this->input->get('step',false);
        if($draft&&$step<6&&is_string($requested)&&preg_match('/^[1-5]$/D',$requested)&&(int)$requested<=$step){$step=(int)$requested;}
        if(!$draft){$this->session->set_userdata('company_onboarding_request_key',bin2hex(random_bytes(32)));}
        $this->load->view('admin/companies/onboarding',[
            'draft'=>$draft,'step'=>$step,'error'=>$error,'company_onboarding_csrf'=>$token,
            'staff_catalog'=>$draft&&$step===3?$service->staffCatalog($actor):[],'resources'=>$resources,
        ]);
    }
}
