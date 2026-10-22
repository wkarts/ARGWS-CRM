<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Issuers extends AdminController
{
    private $registry;

    public function __construct()
    {
        parent::__construct();
        if (class_exists('CompanyRequestScope', false) && CompanyRequestScope::sealedFor($this)) {
            require_once APPPATH.'services/CompanySettingsIdentity.php';
            $this->registry = new IssuerRegistry($this->db, db_prefix());
            return;
        }
        if (!issuer_core_ready()) {
            show_404();
        }
        require_once APPPATH.'services/CompanySettingsIdentity.php';
        $this->load->model('issuers_model');
        $this->registry = $this->issuers_model->registry();
    }

    public function index($issuerId = null)
    {
        if ($this->isolated()) { return $this->scopedIndex($issuerId); }
        $actor = get_staff_user_id();
        try {
            $available = $this->registry->available($actor);
            $catalog = $this->registry->catalog($actor);
            if (!is_admin() && !$available) {
                access_denied('issuers');
            }
            $selected = null;
            if ($issuerId !== null) {
                $issuerId = IssuerPolicy::id($issuerId);
                foreach ($catalog as $entry) {
                    if ((int) $entry['id'] === $issuerId) {
                        $selected = $entry;
                        break;
                    }
                }
                if ($selected === null) {
                    access_denied('issuers');
                }
            }
            $profile = [];
            if ($selected && (int)$selected['active'] === 1) {
                $canonical = (new CompanySettingsIdentity($this->db, db_prefix()))->read($selected['id']);
                $selected['legal_name'] = $canonical['legal_name'];
                $selected['tax_id'] = $canonical['tax_id'];
                $profile = $canonical['profile'];
            }
            $token = $this->session->userdata('issuers_csrf');
            if (!is_string($token) || strlen($token) !== 64) {
                $token = bin2hex(random_bytes(32));
                $this->session->set_userdata('issuers_csrf', $token);
            }
            $this->load->view('admin/issuers/manage', [
                'title' => _l('issuers_title'), 'catalog' => $catalog, 'available' => $available,
                'status_permissions' => $this->statusPermissions($catalog, $actor),
                'company_notice' => $this->session->flashdata('company_registry_notice'),
                'selected' => $selected,
                'can_create' => $this->registry->canCreate($actor),
                'can_manage' => $selected ? $this->registry->canManage($selected['id'], $actor) : $this->registry->canCreate($actor),
                'profile' => $profile,
                'default_issuer' => $this->registry->defaultForStaff($actor), 'issuers_csrf' => $token,
            ]);
        } catch (DomainException $exception) {
            access_denied('issuers');
        }
    }

    /** No mutation has a GET route; token is required even when the legacy app disables global CSRF. */
    public function save()
    {
        if ($this->input->post('action', false) === 'issuer_status') { return $this->saveStatus(); }
        if ($this->isolated()) { return $this->scopedSave(); }
        if ($this->input->method(true) !== 'POST') {
            show_error(_l('issuer_post_required'), 405);
            return;
        }
        $token = $this->session->userdata('issuers_csrf');
        $submitted = $this->input->post('issuers_csrf');
        if (!is_string($token) || strlen($token) !== 64 || !is_string($submitted) || !hash_equals($token, $submitted)) {
            show_error(_l('issuer_invalid_csrf'), 403);
            return;
        }
        $actor = get_staff_user_id();
        $issuer = null;
        try {
            $action = $this->input->post('action');
            if ($action === 'issuer') {
                $id = $this->input->post('issuer_id');
                $issuer = $this->registry->saveIssuer($actor, [
                    'legal_name' => $this->input->post('legal_name'), 'tax_id' => $this->input->post('tax_id'),
                    'active' => $this->input->post('active'), 'profile' => $this->input->post('company_profile') ?? [],
                ], $id === '' ? null : $id);
            } else {
                throw new DomainException('issuer_invalid_action');
            }
            set_alert('success', _l('issuer_saved'));
        } catch (DomainException $exception) {
            set_alert('danger', _l($exception->getMessage()));
        } catch (Throwable $exception) {
            log_message('error', 'Issuer registry operation failed; no customer values logged.');
            set_alert('danger', _l('issuer_storage_failed'));
        }
        redirect(admin_url('issuers' . ($issuer ? '/index/' . $issuer : '')));
    }
    /** Explicit preview only: no registry writes or provider-controlled destinations. */
    public function lookup(): void
    {
        $this->output->set_header('Cache-Control: no-store');
        $reply=function(int $status,array $body):void{$body['csrf']=['name'=>$this->security->get_csrf_token_name(),'hash'=>$this->security->get_csrf_hash()];$this->output->set_status_header($status)->set_content_type('application/json')->set_output(json_encode($body));};
        if($this->input->method(true)!=='POST'){$reply(405,['error'=>'Use POST para consultar.']);return;}
        $mode=$this->input->post('lookup_mode',false);
        $key=$mode==='onboarding'?'company_onboarding_csrf':'issuers_csrf';
        $token=$this->session->userdata($key);$submitted=$this->input->post('lookup_csrf',false);
        if(!is_string($token)||strlen($token)!==64||!is_string($submitted)||!hash_equals($token,$submitted)){$reply(403,['error'=>'Recarregue o formulário antes de consultar.']);return;}
        try{
            $actor=$this->isolated()?CompanyRequestScope::context()->staffId():get_staff_user_id();
            $id=$this->input->post('issuer_id',false);
            if($mode==='onboarding'||$id===''){$allowed=$this->registry->canCreate($actor);}
            else{$allowed=$this->registry->canManage(IssuerPolicy::id($id),$actor);}
            if(!$allowed){$reply(403,['error'=>'Você não tem permissão para consultar neste cadastro.']);return;}
            $last=$this->session->userdata('company_registry_lookup_at');
            if(is_numeric($last)&&time()-(int)$last<3){$reply(429,['error'=>'Aguarde alguns segundos antes de consultar novamente.']);return;}
            require_once APPPATH.'services/CompanyRegistryLookup.php';
            $kind=$this->input->post('kind',false);$value=$this->input->post('value',false);
            if(!is_string($kind)||!in_array($kind,['cnpj','cep'],true)){throw new DomainException('Consulta inválida.');}
            CompanyRegistryLookup::request($kind,$value);
            $this->session->set_userdata('company_registry_lookup_at',time());
            $reply(200,$this->queryRegistry($kind,$value));
        }catch(DomainException $error){$reply(422,['error'=>'Confira o CNPJ numérico (14 dígitos) ou CEP (8 dígitos). Você pode preencher os dados manualmente.']);}
        catch(Throwable $error){$reply(503,['error'=>'Consulta indisponível ou cadastro não encontrado. Tente depois ou preencha manualmente. Nenhum dado foi alterado.']);}
    }

    protected function queryRegistry(string $kind,string $value): array
    {
        return CompanyRegistryLookup::lookup($kind,$value);
    }

    private function statusPermissions(array $catalog, int $actor): array
    {
        $primary = (new IssuerResourceAccess($this->db, db_prefix()))->primaryId();
        $permissions = [];
        foreach ($catalog as $entry) {
            $permissions[(int)$entry['id']] = (int)$entry['id'] !== $primary && $this->registry->canManage($entry['id'], $actor);
        }
        return $permissions;
    }

    private function saveStatus(): void
    {
        if ($this->input->method(true) !== 'POST') { show_error('A alteração exige POST.', 405); return; }
        $token = $this->session->userdata('issuers_csrf');
        $submitted = $this->input->post('issuers_csrf', false);
        if (!is_string($token) || strlen($token) !== 64 || !is_string($submitted) || !hash_equals($token, $submitted)) {
            show_error('Recarregue o formulário antes de salvar.', 403); return;
        }
        $destination = 'issuers';
        try {
            $context = CompanyRequestScope::context();
            $actor = $context ? $context->staffId() : get_staff_user_id();
            $id = IssuerPolicy::id($this->input->post('issuer_id', false));
            $active = $this->input->post('active', false);
            $this->registry->setActive($actor, $id, $active);
            CompanyRequestScope::invalidateCaches($this);
            if ($context && $context->issuerId() === $id && (int)$active === 0) { $destination = 'company_context'; }
            $notice = ['type'=>'success', 'text'=>'Situação da empresa atualizada.'];
        } catch (DomainException $error) {
            $notice = ['type'=>'danger', 'text'=>'Não foi possível alterar a situação. Confira a empresa e suas permissões.'];
        } catch (Throwable $error) {
            log_message('error', 'Company status write failed; no submitted data logged.');
            $notice = ['type'=>'danger', 'text'=>'Não foi possível confirmar a alteração. Recarregue antes de tentar novamente.'];
        }
        $this->session->set_flashdata('company_registry_notice', $notice);
        redirect(CompanyRequestScope::adminUrl($this, $destination), 'location', 303);
    }

    private function isolated(): bool
    {
        return class_exists('CompanyRequestScope', false) && CompanyRequestScope::sealedFor($this);
    }

    private function scopedIndex($issuerId): void
    {
        if (!in_array($this->input->method(true), ['GET','HEAD'], true)) { show_error('Use GET para consultar empresas.',405); return; }
        $actor=CompanyRequestScope::context()->staffId();
        try {
            $catalog=$this->registry->catalog($actor);
            $selected=null;
            if ($issuerId !== null) {
                $issuerId=IssuerPolicy::id($issuerId);
                foreach($catalog as $entry){if((int)$entry['id']===$issuerId){$selected=$entry;break;}}
                if($selected===null){throw new DomainException('issuer_access_denied');}
            }
            $profile=[];
            if($selected && (int)$selected['active']===1){
                $identity=(new CompanySettingsIdentity($this->db,db_prefix()))->read($selected['id']);
                $selected['legal_name']=$identity['legal_name'];$selected['tax_id']=$identity['tax_id'];$profile=$identity['profile'];
            }
            $token=$this->session->userdata('issuers_csrf');
            if(!is_string($token)||strlen($token)!==64){$token=bin2hex(random_bytes(32));$this->session->set_userdata('issuers_csrf',$token);}
            $this->load->view('admin/companies/registry',[
                'catalog'=>$catalog,'selected'=>$selected,'profile'=>$profile,'issuers_csrf'=>$token,
                'status_permissions'=>$this->statusPermissions($catalog,$actor),
                'can_create'=>$this->registry->canCreate($actor),
                'can_manage'=>$selected?$this->registry->canManage($selected['id'],$actor):$this->registry->canCreate($actor),
                'company_notice'=>$this->session->flashdata('company_registry_notice'),
            ]);
        }catch(DomainException $error){show_error('Você não tem acesso a esta empresa.',403);}
    }

    private function scopedSave(): void
    {
        if($this->input->method(true)!=='POST'){show_error('A alteração exige POST.',405);return;}
        $token=$this->session->userdata('issuers_csrf');$submitted=$this->input->post('issuers_csrf',false);
        if(!is_string($token)||strlen($token)!==64||!is_string($submitted)||!hash_equals($token,$submitted)){
            show_error('Recarregue o formulário antes de salvar.',403);return;
        }
        $issuer=null;$notice=['type'=>'success','text'=>'Empresa salva.'];
        try{
            if($this->input->post('action',false)!=='issuer'){throw new DomainException('issuer_invalid_action');}
            $id=$this->input->post('issuer_id',false);
            $issuer=$this->registry->saveIssuer(CompanyRequestScope::context()->staffId(),[
                'legal_name'=>$this->input->post('legal_name',false),'tax_id'=>$this->input->post('tax_id',false),
                'active'=>$this->input->post('active',false),'profile'=>$this->input->post('company_profile',false)??[],
            ],$id===''?null:$id);
        }catch(DomainException $error){$notice=['type'=>'danger','text'=>'Não foi possível salvar. Confira os dados da empresa e suas permissões.'];}
        catch(Throwable $error){$notice=['type'=>'danger','text'=>'Não foi possível salvar a empresa. Tente novamente.'];log_message('error','Company registry write failed; no submitted data logged.');}
        $this->session->set_flashdata('company_registry_notice',$notice);
        redirect(CompanyRequestScope::adminUrl($this,'issuers'.($issuer?'/index/'.$issuer:'')),'location',303);
    }

}
