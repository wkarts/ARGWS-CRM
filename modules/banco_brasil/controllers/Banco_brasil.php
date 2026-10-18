<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once dirname(__DIR__).'/libraries/BancoBrasil/ModuleStore.php';
require_once dirname(__DIR__).'/libraries/BancoBrasil/CapabilityPolicy.php';
class Banco_brasil extends AdminController
{
    private $store;
    public function __construct()
    {
        parent::__construct();
        $this->store=new \BancoBrasil\ModuleStore($this->db,db_prefix());
    }
    public function index()
    {
        $data=['title'=>'Banco do Brasil','issuers'=>[],'selected'=>null,'connections'=>[],
            'returns'=>[],'events'=>[],'layout_bindings'=>[],'layouts'=>\BancoBrasil\LayoutCatalog::all(),'can_manage'=>false,'can_import'=>false,'resource_acl_ready'=>false,'storage_error'=>false,'csrf'=>$this->csrfToken(),'matrix'=>\BancoBrasil\CapabilityPolicy::matrix()];
        if($this->issuerModuleReady()){
            $data['resource_acl_ready']=true;
            try{
                $available=\BancoBrasil\Transaction::quiet($this->db,fn()=>$this->issuers_model->available(get_staff_user_id()));
                $data['issuers']=array_values(array_filter($available,fn($row)=>$this->allowed((int)((array)$row)['id'],'view')));
            }catch(Throwable $e){$data['storage_error']=true;}
            $raw=$this->input->get('issuer_id');
            if($raw!==null && $raw!==''){
                $id=$this->positiveId($raw);$issuer=$this->issuer($id,'view');
                $data['selected']=$issuer;
                try{
                $data['connections']=$this->store->connections($id);
                $data['layout_bindings']=$this->store->layoutBindings($id);
                $data['returns']=$this->store->recentReturns($id);$data['events']=$this->store->recentEvents($id);
                }catch(Throwable $e){$data['storage_error']=true;$data['connections']=$data['layout_bindings']=$data['returns']=$data['events']=[];}
                $data['can_manage']=!$data['storage_error']&&$this->allowed($id,'manage');$data['can_import']=!$data['storage_error']&&$this->allowed($id,'import');
            }
        }
        $this->output->set_header('Cache-Control: no-store');
        $this->load->view('banco_brasil/index',$data);
    }
    public function register_connection()
    {
        $this->post();$id=$this->positiveId($this->input->post('issuer_id'));$issuer=$this->issuer($id,'manage');
        if(!$this->allowed($id,'manage'))access_denied('banco_brasil');
        try{
            $posted=$this->input->post();
            $allowed=['csrf_bb','issuer_id','account_id','environment','agreement_number','wallet','variation'];
            if(array_diff(array_keys($posted),array_merge($allowed,[$this->security->get_csrf_token_name()])))throw new InvalidArgumentException('unexpected_fields');
            $input=[];foreach(['account_id','environment','agreement_number','wallet','variation'] as $key)$input[$key]=$posted[$key]??null;
            $this->store->createConnection($id,get_staff_user_id(),$issuer,$input);
            set_alert('success','Conexão cadastrada para revisão. A titularidade, o contrato e a homologação ainda precisam ser verificados.');
        }catch(Throwable $e){set_alert('warning','Cadastro não confirmado. Verifique campos e histórico; a conexão existente não pode ser reatribuída.');}
        redirect(admin_url('banco_brasil?issuer_id='.$id));
    }
    public function register_layout()
    {
        $this->post();$id=$this->positiveId($this->input->post('issuer_id'));$this->issuer($id,'manage');
        if(!$this->allowed($id,'manage'))access_denied('banco_brasil');
        try{
            $posted=$this->input->post();
            if(array_diff(array_keys($posted),['csrf_bb','issuer_id','connection_id','layout_id',$this->security->get_csrf_token_name()]))throw new InvalidArgumentException('unexpected_fields');
            $connection=$this->store->connection($this->positiveId($this->input->post('connection_id')),$id);
            $layout=$this->input->post('layout_id');if(!is_string($layout))throw new InvalidArgumentException('layout_invalid');
            $this->store->registerLayout($connection,get_staff_user_id(),$layout);
            set_alert('success','Perfil declarado e registrado. Isso não confirma homologação, ambiente remoto ou autenticidade de arquivos.');
        }catch(Throwable $e){set_alert('warning','Perfil não confirmado. Confira a conexão e o histórico; vínculos anteriores não serão alterados.');}
        redirect(admin_url('banco_brasil?issuer_id='.$id));
    }
    public function import_return()
    {
        $this->post();$id=$this->positiveId($this->input->post('issuer_id'));$this->issuer($id,'import');
        if(!$this->allowed($id,'import'))access_denied('banco_brasil');
        try{
            $connection=$this->store->connection($this->positiveId($this->input->post('connection_id')),$id);
            $file=$_FILES['return_file']??[];
            if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??'')
                ||($file['size']??0)<1||$file['size']>16*1024*1024)throw new InvalidArgumentException('return_file_invalid');
            $bytes=file_get_contents($file['tmp_name'],false,null,0,16*1024*1024+1);
            if($bytes===false||strlen($bytes)>16*1024*1024)throw new InvalidArgumentException('return_file_invalid');
            $result=$this->store->importReturn($connection,get_staff_user_id(),$bytes,$this->positiveId($this->input->post('layout_binding_id')));
            set_alert('success',$result['duplicate']?'Arquivo já recebido. Nenhum pagamento foi criado.':'Retorno validado e registrado para revisão. Nenhum pagamento foi criado.');
        }catch(Throwable $e){set_alert('warning','Importação não confirmada. Confira conta, ambiente, convênio, leiaute e histórico antes de tentar novamente.');}
        redirect(admin_url('banco_brasil?issuer_id='.$id));
    }
    private function allowed(int $issuer,string $permission):bool
    {
        if(!function_exists('issuer_resource_can'))return false;
        try{return \BancoBrasil\Transaction::quiet($this->db,fn()=>issuer_resource_can($issuer,get_staff_user_id(),'banco_brasil',$permission))===true;}
        catch(Throwable $e){show_error('Armazenamento indisponível. Tente novamente após verificar a instalação.',503);return false;}
    }
    private function issuerModuleReady():bool
    {
        if(!$this->app_modules->is_active('issuers'))return false;
        $this->load->helper('issuers/issuers');
        if(!function_exists('issuer_resource_can'))return false;
        $this->load->model('issuers/issuers_model');return true;
    }
    private function issuer(int $id,string $capability):array
    {
        if(!$this->issuerModuleReady()||!$this->allowed($id,$capability))access_denied('banco_brasil');
        try{$row=\BancoBrasil\Transaction::quiet($this->db,fn()=>$this->issuers_model->get($id));}
        catch(Throwable $e){show_error('Armazenamento indisponível. Tente novamente após verificar a instalação.',503);return [];}
        if(!$row)access_denied('banco_brasil');
        $row=(array)$row;
        return ['id'=>(int)$row['id'],'legal_name'=>(string)$row['legal_name'],'tax_id'=>(string)$row['tax_id']];
    }
    private function positiveId($raw):int
    {
        if(!is_scalar($raw)||!preg_match('/^[1-9][0-9]{0,9}$/D',(string)$raw))show_error('Identificador inválido.',400);
        return (int)$raw;
    }
    private function csrfToken():string
    {
        $token=$this->session->userdata('bb_admin_csrf');
        if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/D',$token)){
            $token=bin2hex(random_bytes(32));$this->session->set_userdata('bb_admin_csrf',$token);
        }
        return $token;
    }
    private function post():void
    {
        if(strtoupper($this->input->method())!=='POST')show_error('Método não permitido.',405);
        $expected=$this->session->userdata('bb_admin_csrf');$actual=$this->input->post('csrf_bb');
        if(!is_string($expected)||!is_string($actual)||strlen($expected)!==64||!hash_equals($expected,$actual))show_error('Solicitação inválida. Atualize a tela.',403);
    }
}
