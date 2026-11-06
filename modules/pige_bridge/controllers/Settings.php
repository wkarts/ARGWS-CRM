<?php
defined('BASEPATH') or exit('No direct script access allowed');
/** Native admin setup; CompanyRequestScope is deliberately not bypassed for named companies. */
class Settings extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if(!is_admin()){access_denied('pige_bridge');return;}
        require_once __DIR__.'/../libraries/PigeBridgeConnections.php';
        require_once APPPATH.'services/issuers/IssuerRegistry.php';
        $this->output->set_header('Cache-Control: no-store')->set_header('Pragma: no-cache');
        $this->load->library('encryption');
    }
    private function service():PigeBridgeConnections{return new PigeBridgeConnections($this->db,$this->encryption,db_prefix());}
    private function value(string $field):string{$value=$this->input->post($field,false);if(!is_string($value))throw new DomainException('pige_input_invalid');return $value;}
    private function integer(string $field,bool $zero=false):int{$value=$this->value($field);if(!preg_match($zero?'/^(0|[1-9][0-9]{0,9})$/D':'/^[1-9][0-9]{0,9}$/D',$value)||(float)$value>2147483647)throw new DomainException('pige_input_invalid');return (int)$value;}
    private function checked(string $field):bool{$value=$this->input->post($field,false);if($value===null||$value===false)return false;if($value!=='1')throw new DomainException('pige_input_invalid');return true;}
    private function writeAllowed():bool
    {
        if(!is_admin()){access_denied('pige_bridge');return false;}
        if($this->input->method(true)!=='POST'){$this->output->set_header('Allow: POST');show_error('Método não permitido.',405);return false;}
        $token=$this->session->userdata('pige_bridge_setup_csrf');$posted=$this->input->post('pige_csrf',false);
        if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/D',$token)||!is_string($posted)||!hash_equals($token,$posted)){show_error('Reabra a configuração antes de salvar.',403);return false;}
        return true;
    }
    public function index()
    {
        if(!is_admin()){access_denied('pige_bridge');return;}
        if(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
        $token=$this->session->userdata('pige_bridge_setup_csrf');if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/D',$token)){$token=bin2hex(random_bytes(32));$this->session->set_userdata('pige_bridge_setup_csrf',$token);}
        try{$caller=(int)get_staff_user_id();$acl=new IssuerResourceAccess($this->db,db_prefix());$issuers=array_values(array_filter((new IssuerRegistry($this->db,db_prefix()))->available($caller),fn($issuer)=>$acl->can((int)$issuer['id'],$caller,'settings','edit')));$connections=$this->service()->listed($caller);}
        catch(Throwable $e){show_error('Configuração PIGE indisponível. Confirme a atualização nativa do módulo.',503);return;}
        $external=[];try{require_once __DIR__.'/../libraries/PigeBridgeExternalConfiguration.php';$ext=new PigeBridgeExternalConfiguration($this->db,$this->encryption,db_prefix());foreach($connections as $connection)$external[$connection['connection_key']]=$ext->get($connection['connection_key']);}catch(Throwable $e){$external=[];}
        $choices=[];try{require_once __DIR__.'/../libraries/PigeBridgeSettingsChoices.php';$selector=new PigeBridgeSettingsChoices($this->db,db_prefix());foreach($connections as $connection)$choices[$connection['connection_key']]=$selector->forScope($caller,$connection['scope']);}catch(Throwable $e){$choices=[];}
        $this->load->view('pige_bridge/settings',['title'=>'Integração PIGE — ponte CRM','issuers'=>$issuers,'connections'=>$connections,'external'=>$external,'choices'=>$choices,'pige_csrf'=>$token,'company_context_token'=>CompanyRequestScope::token()]);
    }
    protected function discoveryService(callable $authorize):PigeBridgeDiscovery{return new PigeBridgeDiscovery($authorize);}
    /** Read-only remote discovery; POST prevents credentials entering URLs/history. */
    public function discover()
    {
        if(!$this->writeAllowed())return;
        try{
            require_once __DIR__.'/../libraries/PigeBridgeDiscovery.php';
            if(!((isset($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off'&&$_SERVER['HTTPS']!=='')||(string)($_SERVER['SERVER_PORT']??'')==='443'))throw new DomainException('pige_discovery_https');
            $issuer=$this->integer('issuer_id');$caller=(int)get_staff_user_id();
            $authorize=function()use($issuer,$caller):void{
                if(!is_admin()||(int)get_staff_user_id()!==$caller||!(new IssuerResourceAccess($this->db,db_prefix()))->can($issuer,$caller,'settings','edit'))throw new DomainException('pige_discovery_access');
                $module=IssuerStorage::query($this->db,'SELECT active,installed_version FROM '.db_prefix().'modules WHERE module_name=?',['pige_bridge'])->row_array();
                if((int)($module['active']??0)!==1||($module['installed_version']??null)!=='1.1.0')throw new DomainException('pige_discovery_upgrade');
            };
            $service=$this->discoveryService($authorize);$action=$this->value('discovery_action');
            if($action==='schools')$data=['schools'=>$service->schools($this->value('pige_url'),$this->value('admin_credential'))];
            elseif($action==='configuration')$data=['configuration'=>$service->configuration($this->value('pige_url'),$this->value('admin_credential'),$this->value('school_id'),$issuer,(string)$this->config->item('base_url'))];
            else throw new DomainException('pige_discovery_input');
            $authorize();$this->output->set_content_type('application/json')->set_output(json_encode(['success'=>true]+$data,JSON_THROW_ON_ERROR|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT));
        }catch(Throwable $error){
            // Do not echo remote bodies, URLs, JWTs or exception messages.
            $this->output->set_status_header(422)->set_content_type('application/json')->set_output(json_encode(['success'=>false,'message'=>'Consulta não confirmada. Confira a URL HTTPS pública, credencial administrativa, permissões, empresa e configuração existente no PIGE. Nenhuma configuração foi salva.']));
        }
    }
    public function prepare_remote()
    {
        if(!$this->writeAllowed())return;
        try{
            require_once __DIR__.'/../libraries/PigeBridgeDiscovery.php';
            if(!((isset($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off'&&$_SERVER['HTTPS']!=='')||(string)($_SERVER['SERVER_PORT']??'')==='443')||!$this->checked('confirm_transfer'))throw new DomainException('pige_preparation_confirmation');
            $caller=(int)get_staff_user_id();$key=$this->value('connection_key');$revision=$this->integer('revision');
            $store=$this->service();$snapshot=$store->preparationSnapshot($key,$revision,$caller);
            $authorize=function()use($store,$key,$revision,$caller,$snapshot):void{
                if(!is_admin()||(int)get_staff_user_id()!==$caller)throw new DomainException('pige_discovery_access');
                $current=$store->preparationSnapshot($key,$revision,$caller);
                if($current['scope']!==$snapshot['scope']||!hash_equals($current['secret'],$snapshot['secret']))throw new DomainException('pige_configuration_changed_reload');
            };
            $result=$this->discoveryService($authorize)->prepareRemote($this->value('pige_url'),$this->value('admin_credential'),$snapshot['scope'],$snapshot['secret'],(string)$this->config->item('base_url'));
            unset($snapshot,$_POST['admin_credential']);
            set_alert('success','Configuração preparada no PIGE, desativada. Autenticação HMAC não verificada. Nenhuma permissão financeira foi habilitada.');
        }catch(Throwable $error){
            unset($snapshot,$_POST['admin_credential']);
            set_alert('danger','Preparação não confirmada. Não repita sem consultar o PIGE: a conexão pode ter sido criada desativada. Configuração existente nunca é sobrescrita; confira URL, revisões, permissões e estado desativado.');
        }
        redirect(admin_url('pige_bridge/settings'));
    }
    public function save()
    {
        if(!$this->writeAllowed())return;
        try{
            $revision=$this->integer('revision',true);$secret=$this->value('secret');
            if($secret!==''&&$revision>0&&!$this->checked('confirm_rotation'))throw new DomainException('pige_rotation_confirmation_required');
            $scope=['school_id'=>$this->value('school_id'),'issuer_id'=>$this->value('issuer_id'),'account_id'=>$this->value('account_id'),'environment'=>$this->value('environment')];
            $this->service()->save($this->value('connection_key'),$scope,$secret===''?null:$secret,$this->checked('enabled'),$revision,(int)get_staff_user_id());set_alert('success','Conexão salva. Revalide o mapping antes de novas faturas; nenhuma cobrança foi enviada.');
        }catch(Throwable $e){set_alert('danger','Não foi possível confirmar a configuração. Reabra a tela, confira revisão, permissões e campos; o segredo não será exibido.');}
        redirect(admin_url('pige_bridge/settings'));
    }
    public function disable()
    {
        if(!$this->writeAllowed())return;
        try{
            $key=$this->value('connection_key');$revision=$this->integer('revision');$caller=(int)get_staff_user_id();$owned=null;
            foreach($this->service()->listed($caller) as $connection){if($connection['connection_key']===$key){$owned=$connection;break;}}
            if($owned===null)throw new DomainException('pige_configuration_access_denied');
            // Scope comes only from the authorized stored record. The writer locks and rechecks revision/ACL.
            $this->service()->save($key,$owned['scope'],null,false,$revision,$caller);
            set_alert('success','Integração desativada. Histórico preservado; nenhuma cobrança foi enviada.');
        }catch(Throwable $e){set_alert('danger','Não foi possível desativar. Reabra a lista e confira as permissões e a revisão.');}
        redirect(admin_url('pige_bridge/settings'));
    }
    public function mapping()
    {
        if(!$this->writeAllowed())return;
        try{$mapping=['enabled'=>$this->checked('mapping_enabled'),'actor_id'=>$this->integer('actor_id'),'currency_id'=>$this->integer('currency_id')];$this->service()->saveMapping($this->value('connection_key'),$this->integer('revision'),$mapping,(int)get_staff_user_id());set_alert('success','Configuração de fatura local salva. Cliente será validado por solicitação; nenhuma fatura foi criada.');}
        catch(Throwable $e){set_alert('danger','Mapping não confirmado. Confira revisão, funcionário, permissões e moeda BRL.');}
        redirect(admin_url('pige_bridge/settings'));
    }
    public function external()
    {
        if(!$this->writeAllowed())return;
        try{
            require_once __DIR__.'/../libraries/PigeBridgeExternalConfiguration.php';
            $mapping=['provider'=>$this->value('provider'),'account_row'=>$this->integer('account_row'),'actor_id'=>$this->integer('actor_id'),'enabled'=>$this->checked('external_enabled'),'allow_reconcile'=>$this->checked('allow_reconcile')];if($mapping['provider']==='banco_brasil')$mapping['profile_row']=$this->integer('profile_row');
            (new PigeBridgeExternalConfiguration($this->db,$this->encryption,db_prefix()))->save($this->value('connection_key'),$this->integer('connection_revision'),$this->integer('external_revision',true),$mapping,(int)get_staff_user_id());set_alert('success','Consentimento externo salvo. Nenhuma chamada ao provedor, emissão ou baixa foi realizada.');
        }catch(Throwable $e){set_alert('danger','Consentimento externo não confirmado. Revalide empresa, conta, ambiente, revisão e permissões.');}
        redirect(admin_url('pige_bridge/settings'));
    }

}
