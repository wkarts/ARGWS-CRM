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
        if ($this->input->get('edit_modal', false) === '1') { return $this->editIdentity($issuerId); }
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
                'edit_permissions' => $this->editPermissions($catalog, $actor),
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
        if ($this->input->post('action', false) === 'edit_identity') { return $this->saveIdentity(); }
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

    /** Technical company switch; the canonical Issuers controller owns the POST. */
    public function select(): void
    {
        if ($this->input->method(true) !== 'POST') { show_error('A troca de empresa exige POST.', 405); return; }
        $stage = 'context';
        try {
            $context = CompanyRequestScope::context();
            if (!($context instanceof CompanyContext)) { throw new LogicException('company_context_unavailable'); }
            $stage = 'authorization';
            $context->select(
                $this->input->post('company_id', false),
                $this->input->post('_company_switch_csrf', false),
                CompanyRequestScope::submittedToken()
            );
            $stage = 'cache_invalidation';
            CompanyRequestScope::invalidateCaches($this);
            $destination = CompanyRequestScope::adminUrl($this);
            $stage = 'request_release';
            CompanyRequestScope::finish();
            $stage = 'redirect';
            redirect($destination, 'location', 303);
        } catch (DomainException $error) {
            show_error('Não foi possível trocar a empresa. Recarregue a página e confirme seu acesso.', 403);
        } catch (Throwable $error) {
            $errorClass = get_class($error);
            if (!preg_match('/^[A-Za-z0-9_\\\\]{1,128}$/D', $errorClass)) { $errorClass = 'Throwable'; }
            $sourceFile = basename((string) $error->getFile());
            if (!preg_match('/^[A-Za-z0-9_.-]{1,128}$/D', $sourceFile)) { $sourceFile = 'unknown'; }
            $safeMessage = 'Native company selection failed [' . $stage . ']: ' . $errorClass
                . ' @ ' . $sourceFile . ':' . (int) $error->getLine() . '.';
            error_log($safeMessage);
            log_message('error', $safeMessage);
            show_error('Não foi possível trocar a empresa. Recarregue a página e tente novamente.', 500);
        }
    }

    /** Canonical issuer onboarding route; the modal is an Issuers presentation. */
    public function onboarding($key = null): void
    {
        if (!CompanyRequestScope::sealedFor($this)) { show_error('Contexto de empresa indisponível.', 503); return; }
        require_once APPPATH.'services/CompanyOnboarding.php';
        require_once APPPATH.'services/CompanySettingsFormats.php';
        require_once APPPATH.'services/CompanyResourceCatalog.php';
        $resources = CompanyResourceCatalog::definitions();
        $service = new CompanyOnboarding($this->db, db_prefix(), array_map(static fn($r) => array_keys($r['capabilities']), $resources));
        $actor = CompanyRequestScope::context()->staffId(); $error = null; $draft = null;
        $this->output->set_header('Cache-Control', 'no-store')->set_header('X-Frame-Options', 'SAMEORIGIN');
        $token = $this->session->userdata('company_onboarding_csrf');
        if (!is_string($token) || strlen($token) !== 64) { $token = bin2hex(random_bytes(32)); $this->session->set_userdata('company_onboarding_csrf', $token); }
        try {
            $draft = $key === null ? $service->pending($actor) : $service->read($actor, $key);
            if ($this->input->method(true) === 'POST' && $this->input->post('action', false) !== 'show') {
                $submitted = $this->input->post('company_onboarding_csrf', false);
                if (!is_string($submitted) || !hash_equals($token, $submitted)) { show_error('Recarregue o formulário antes de salvar.', 403); return; }
                $action = $this->input->post('action', false);
                if ($action === 'begin' && $key === null) {
                    $requestKey = $this->session->userdata('company_onboarding_request_key');
                    if (!is_string($requestKey) || !preg_match('/^[a-f0-9]{64}$/D', $requestKey)) { throw new DomainException('issuer_wizard_invalid_draft'); }
                    $draft = $service->begin($actor, $requestKey);
                } elseif ($action === 'advance' && $draft) {
                    $input = $this->input->post('wizard', false); if (!is_array($input)) { throw new DomainException('issuer_wizard_invalid_input'); }
                    if ($this->input->post('step', false) === '3') {
                        $selected = $input['selected_staff'] ?? []; unset($input['selected_staff']); $assignments = $input['staff'] ?? [];
                        if ($assignments === '') { $assignments = []; }
                        if (!is_array($selected) || !is_array($assignments) || array_diff_key($selected, $assignments)) { throw new DomainException('issuer_wizard_invalid_staff'); }
                        foreach ($selected as $enabled) { if ($enabled !== '1') { throw new DomainException('issuer_wizard_invalid_staff'); } }
                        $input = ['staff' => array_intersect_key($assignments, $selected)];
                    }
                    $draft = $service->advance($actor, $draft['draft_key'], $this->input->post('revision', false), $this->input->post('step', false), $input);
                } elseif ($action === 'complete' && $draft) {
                    $service->complete($actor, $draft['draft_key'], $this->input->post('revision', false));
                    $draft = $service->read($actor, $draft['draft_key']);
                } else { throw new DomainException('issuer_wizard_invalid_input'); }
                CompanyRequestScope::invalidateCaches($this);
                redirect(CompanyRequestScope::adminUrl($this, 'issuers/onboarding/'.$draft['draft_key'].'?modal=1'), 'location', 303); return;
            } elseif (!in_array($this->input->method(true), ['GET','HEAD'], true) && !($this->input->method(true) === 'POST' && $this->input->post('action', false) === 'show')) { show_error('Método não permitido.', 405); return; }
        } catch (DomainException $exception) {
            if ($exception->getMessage() === 'issuer_access_denied') { show_error('Somente o responsável ativo da instalação pode criar empresas.', 403); return; }
            $this->output->set_status_header($exception->getMessage() === 'issuer_wizard_stale' ? 409 : 422);
            $error = $exception->getMessage() === 'issuer_wizard_stale' ? 'Este rascunho mudou em outra aba. Recarregue antes de salvar. Nenhum dado desta tentativa foi aplicado.' : 'Não foi possível salvar. Confira os dados, o documento fiscal e as permissões. O rascunho salvo foi preservado.';
        } catch (Throwable $exception) {
            log_message('error', 'Issuer onboarding failed; no draft values logged.'); $this->output->set_status_header(503);
            $error = 'Não foi possível confirmar a gravação. Recarregue e tente novamente; a empresa só é criada quando toda a transação é confirmada.';
        }
        if ($error === null && $this->input->get('modal', false) !== '1' && in_array($this->input->method(true), ['GET','HEAD'], true)) {
            redirect(CompanyRequestScope::adminUrl($this, 'issuers?new_company=1'.($draft ? '&draft='.rawurlencode($draft['draft_key']) : '')), 'location', 303); return;
        }
        $step = $draft ? (int)$draft['step'] : 0; $requested = $this->input->get('step', false);
        if ($draft && $step < 6 && is_string($requested) && preg_match('/^[1-5]$/D', $requested) && (int)$requested <= $step) { $step = (int)$requested; }
        if (!$draft) { $this->session->set_userdata('company_onboarding_request_key', bin2hex(random_bytes(32))); }
        $this->load->view('admin/issuers/onboarding', ['draft'=>$draft,'step'=>$step,'error'=>$error,'company_onboarding_csrf'=>$token,'staff_catalog'=>$draft && $step === 3 ? $service->staffCatalog($actor) : [],'resources'=>$resources]);
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

    private function editPermissions(array $catalog, int $actor): array
    {
        $permissions=[];
        foreach($catalog as $entry){$permissions[(int)$entry['id']]=(int)$entry['active']===1&&$this->registry->canManage($entry['id'],$actor);}
        return $permissions;
    }

    private function editIdentity($issuerId): void
    {
        if(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Use GET para editar a empresa.',405);return;}
        $this->output->set_header('Cache-Control: no-store');
        try {
            $id=IssuerPolicy::id($issuerId);
            $actor=$this->isolated()?CompanyRequestScope::context()->staffId():get_staff_user_id();
            $selected=null;
            foreach($this->registry->catalog($actor) as $entry){if((int)$entry['id']===$id&&(int)$entry['active']===1){$selected=$entry;break;}}
            if(!$selected||!$this->registry->canManage($id,$actor)){throw new DomainException('issuer_access_denied');}
            $revision=$this->registry->identityRevision($actor,$id);
            $identity=(new CompanySettingsIdentity($this->db,db_prefix()))->read($id);
            $token=$this->session->userdata('issuers_csrf');
            if(!is_string($token)||strlen($token)!==64){$token=bin2hex(random_bytes(32));$this->session->set_userdata('issuers_csrf',$token);}
            $this->load->view('admin/issuers/identity_modal',[
                'selected'=>$selected,'identity_values'=>$identity,'identity_revision'=>$revision,
                'issuers_csrf'=>$token,'notice'=>$this->session->flashdata('company_identity_notice'),
            ]);
        } catch(DomainException $error){show_error('Empresa indisponível ou sem permissão para edição.',403);}
    }

    private function saveIdentity(): void
    {
        if($this->input->method(true)!=='POST'){show_error('A alteração exige POST.',405);return;}
        $token=$this->session->userdata('issuers_csrf');$submitted=$this->input->post('issuers_csrf',false);
        if(!is_string($token)||strlen($token)!==64||!is_string($submitted)||!hash_equals($token,$submitted)){show_error('Recarregue o formulário antes de salvar.',403);return;}
        try {
            $id=IssuerPolicy::id($this->input->post('issuer_id',false));
            $actor=$this->isolated()?CompanyRequestScope::context()->staffId():get_staff_user_id();
            $revision=$this->input->post('identity_revision',false);
            if(!is_string($revision)){throw new DomainException('company_identity_revision_invalid');}
            $this->registry->updateExistingIdentity($actor,[
                'legal_name'=>$this->input->post('legal_name',false),'tax_id'=>$this->input->post('tax_id',false),
                'profile'=>$this->input->post('company_profile',false)??[],
            ],$id,$revision);
            CompanyRequestScope::invalidateCaches($this);
            $this->session->set_flashdata('company_identity_notice','Identificação da empresa atualizada.');
        } catch(DomainException $error){
            show_error($error->getMessage()==='company_identity_revision_stale'?'A empresa mudou em outra sessão. Feche e reabra a edição para revisar os dados atuais. Nenhum dado deste formulário foi salvo.':'Não foi possível salvar. Confira seus dados e permissões; feche e reabra a edição.',409);return;
        } catch(Throwable $error){log_message('error','Company identity edit failed; no submitted data logged.');show_error('Não foi possível confirmar a alteração. Feche e reabra antes de tentar novamente.',500);return;}
        redirect(CompanyRequestScope::adminUrl($this,'issuers/index/'.$id.'?edit_modal=1'),'location',303);
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
            if ($context && $context->issuerId() === $id && (int)$active === 0) { $destination = ''; }
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
            // Keep the existing CRM company registry route and shell. Company
            // context changes the data and permissions, never the destination.
            $available=$this->registry->available($actor);
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
            $this->load->view('admin/issuers/manage',[
                'title'=>_l('issuers_title'),'available'=>$available,
                'catalog'=>$catalog,'selected'=>$selected,'profile'=>$profile,'issuers_csrf'=>$token,
                'status_permissions'=>$this->statusPermissions($catalog,$actor),
                'edit_permissions'=>$this->editPermissions($catalog,$actor),
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
