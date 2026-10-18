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
