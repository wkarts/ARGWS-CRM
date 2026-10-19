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
        $companyId=$this->companyContextId();
        $data=['title'=>'Banco do Brasil','issuers'=>[],'selected'=>null,'connections'=>[],
            'returns'=>[],'events'=>[],'layout_bindings'=>[],'layouts'=>\BancoBrasil\LayoutCatalog::all(),'can_manage'=>false,'can_import'=>false,'resource_acl_ready'=>false,'storage_error'=>false,'isolated'=>false,'company_context_token'=>null,'api_ready'=>false,'api_profiles'=>[],'api_origins'=>[],'api_operations'=>[],'api_receipts'=>[],'can_api_issue'=>false,'can_api_query'=>false,'can_api_cancel'=>false,'reconciliations'=>[],'reconciliation_ready'=>false,'can_reconcile'=>false,'can_settle'=>false,'batches'=>[],'origination_ready'=>false,'can_generate'=>false,'can_download'=>false,'request_key'=>bin2hex(random_bytes(32)),'admin_base_url'=>$this->url(''),'notice'=>$this->session->userdata('bb_notice'),'csrf'=>$this->csrfToken(),'matrix'=>\BancoBrasil\CapabilityPolicy::matrix()];
        if(class_exists('CompanyRequestScope',false)){
            $data['isolated']=\CompanyRequestScope::sealedFor($this);
            $data['company_context_token']=\CompanyRequestScope::token();
        }
        if($this->issuerCoreReady()){
            $data['resource_acl_ready']=true;
            try{
                $registry = new \IssuerRegistry($this->db, db_prefix());
                $available=\BancoBrasil\Transaction::quiet($this->db,fn()=>$registry->available($this->staffId()));
                $data['issuers']=array_values(array_filter($available,fn($row)=>($companyId===null||(int)((array)$row)['id']===$companyId)&&$this->allowed((int)((array)$row)['id'],'view')));
            }catch(Throwable $e){$data['storage_error']=true;}
            $raw=$this->input->get('issuer_id');
            if($companyId!==null){
                if($raw!==null&&$raw!==''&&$this->positiveId($raw)!==$companyId)$this->denied();
                $raw=(string)$companyId;
            }
            if($raw!==null && $raw!==''){
                $id=$this->positiveId($raw);$issuer=$this->issuer($id,'view');
                $data['selected']=$issuer;
                try{
                $data['connections']=$this->store->connections($id);
                $data['layout_bindings']=$this->store->layoutBindings($id);
                $data['returns']=$this->store->recentReturns($id);$data['events']=$this->store->recentEvents($id);
                }catch(Throwable $e){$data['storage_error']=true;$data['connections']=$data['layout_bindings']=$data['returns']=$data['events']=[];}
                $data['can_manage']=!$data['storage_error']&&$this->allowed($id,'manage');$data['can_import']=!$data['storage_error']&&$this->allowed($id,'import');
                try{$operations=$this->origination();$data['origination_ready']=$operations->ready();if($data['origination_ready'])$data['batches']=$operations->batches($id,$this->staffId());}catch(Throwable $e){$data['origination_ready']=false;}
                try{require_once dirname(__DIR__).'/libraries/BancoBrasil/Reconciliation.php';$data['reconciliations']=(new \BancoBrasil\Reconciliation($this->db,db_prefix()))->summaries($id,$this->staffId());$data['reconciliation_ready']=true;$access=new \IssuerResourceAccess($this->db,db_prefix());$data['can_reconcile']=$this->allowed($id,'reconcile')&&$access->can($id,$this->staffId(),'invoices','view');$data['can_settle']=$data['can_reconcile']&&$access->can($id,$this->staffId(),'payments','create');}catch(Throwable $e){$data['reconciliation_ready']=false;}
                try{$api=$this->api();$overview=$api->overview($id,$this->staffId());$data['api_ready']=true;$data['api_profiles']=$overview['profiles'];$data['api_origins']=$overview['origins'];$data['api_operations']=$overview['operations'];$data['api_receipts']=$overview['receipts'];$access=new \IssuerResourceAccess($this->db,db_prefix());foreach(['issue','query','cancel'] as $op)$data['can_api_'.$op]=$this->allowed($id,'api_'.$op)&&$access->can($id,$this->staffId(),'invoices','view');}catch(Throwable $e){$data['api_ready']=false;}
                $data['can_generate']=$data['origination_ready']&&$this->allowed($id,'generate');$data['can_download']=$data['origination_ready']&&$this->allowed($id,'download');
            }
        }
        $this->output->set_header('Cache-Control: no-store');
        $this->session->unset_userdata('bb_notice');
        $this->load->view('banco_brasil/index',$data);
    }
    public function register_connection()
    {
        $this->post();$id=$this->positiveId($this->input->post('issuer_id'));$issuer=$this->issuer($id,'manage');
        if(!$this->allowed($id,'manage'))$this->denied();
        try{
            $posted=$this->input->post();
            $allowed=['csrf_bb','issuer_id','account_id','environment','agreement_number','wallet','variation'];
            if(array_diff(array_keys($posted),array_merge($allowed,[$this->security->get_csrf_token_name()])))throw new InvalidArgumentException('unexpected_fields');
            $input=[];foreach(['account_id','environment','agreement_number','wallet','variation'] as $key)$input[$key]=$posted[$key]??null;
            $this->store->createConnection($id,$this->staffId(),$issuer,$input);
            $this->flash('success','Conexão cadastrada para revisão. A titularidade, o contrato e a homologação ainda precisam ser verificados.');
        }catch(Throwable $e){$this->flash('warning','Cadastro não confirmado. Verifique campos e histórico; a conexão existente não pode ser reatribuída.');}
        redirect($this->url('banco_brasil?issuer_id='.$id));
    }
    public function register_layout()
    {
        $this->post();$id=$this->positiveId($this->input->post('issuer_id'));$this->issuer($id,'manage');
        if(!$this->allowed($id,'manage'))$this->denied();
        try{
            $posted=$this->input->post();
            if(array_diff(array_keys($posted),['csrf_bb','issuer_id','connection_id','layout_id',$this->security->get_csrf_token_name()]))throw new InvalidArgumentException('unexpected_fields');
            $connection=$this->store->connection($this->positiveId($this->input->post('connection_id')),$id);
            $layout=$this->input->post('layout_id');if(!is_string($layout))throw new InvalidArgumentException('layout_invalid');
            $this->store->registerLayout($connection,$this->staffId(),$layout);
            $this->flash('success','Perfil declarado e registrado. Isso não confirma homologação, ambiente remoto ou autenticidade de arquivos.');
        }catch(Throwable $e){$this->flash('warning','Perfil não confirmado. Confira a conexão e o histórico; vínculos anteriores não serão alterados.');}
        redirect($this->url('banco_brasil?issuer_id='.$id));
    }
    public function import_return()
    {
        $this->post();$id=$this->positiveId($this->input->post('issuer_id'));$this->issuer($id,'import');
        if(!$this->allowed($id,'import'))$this->denied();
        try{
            $connection=$this->store->connection($this->positiveId($this->input->post('connection_id')),$id);
            $file=$_FILES['return_file']??[];
            if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??'')
                ||($file['size']??0)<1||$file['size']>16*1024*1024)throw new InvalidArgumentException('return_file_invalid');
            $bytes=file_get_contents($file['tmp_name'],false,null,0,16*1024*1024+1);
            if($bytes===false||strlen($bytes)>16*1024*1024)throw new InvalidArgumentException('return_file_invalid');
            $result=$this->store->importReturn($connection,$this->staffId(),$bytes,$this->positiveId($this->input->post('layout_binding_id')));
            $this->flash('success',$result['duplicate']?'Arquivo já recebido. Nenhum pagamento foi criado.':'Retorno validado e registrado para revisão. Nenhum pagamento foi criado.');
        }catch(Throwable $e){$this->flash('warning','Importação não confirmada. Confira conta, ambiente, convênio, leiaute e histórico antes de tentar novamente.');}
        redirect($this->url('banco_brasil?issuer_id='.$id));
    }
    private function origination():\BancoBrasil\Origination
    {
        require_once dirname(__DIR__).'/libraries/BancoBrasil/Origination.php';return new \BancoBrasil\Origination($this->db,db_prefix());
    }
    public function declare_contract()
    {
        $this->post();$id=$this->positiveId($this->input->post('issuer_id'));$this->issuer($id,'manage');
        try{
            $input=$this->input->post('contract',false);if(!is_array($input))throw new InvalidArgumentException('invalid_contract');
            $this->origination()->declareContract($id,$this->staffId(),$this->positiveId($this->input->post('connection_id')),$this->positiveId($this->input->post('layout_binding_id')),$input);
            $this->flash('success','Declaração do operador registrada. Isso não comprova contrato ou homologação pelo banco. A faixa reservada não pode ser reiniciada.');
        }catch(Throwable $e){$this->flash('warning','Declaração não confirmada. Confira contrato, perfil, isenção, intercâmbio de teste e faixa exclusiva já utilizada; não reduza nem reutilize números.');}
        redirect($this->url('banco_brasil?issuer_id='.$id).'#bb-remittance');
    }
    public function generate_remittance()
    {
        $this->post();$id=$this->positiveId($this->input->post('issuer_id'));$this->issuer($id,'generate');
        try{
            $titles=$this->input->post('titles',false);$date=$this->input->post('generation_date',false);$key=$this->input->post('request_key',false);
            if(!is_array($titles)||!is_string($date)||!is_string($key))throw new InvalidArgumentException('invalid_batch');
            $batch=$this->origination()->generate($id,$this->staffId(),$this->positiveId($this->input->post('connection_id')),$this->positiveId($this->input->post('layout_binding_id')),$date,$titles,$key);
            $this->flash('success','Lote #'.$batch.' gerado e armazenado. Não foi enviado ao banco; não há aceite nem pagamento confirmado.');
        }catch(Throwable $e){$this->flash('warning','Geração não confirmada. Confira fatura, moeda BRL, identidade, endereço ASCII, perfil e contrato. Se o lote já foi reservado, retome pelo lote; os números permanecem reservados.');}
        redirect($this->url('banco_brasil?issuer_id='.$id).'#bb-remittance');
    }
    public function retry_remittance()
    {
        $this->post();$id=$this->positiveId($this->input->post('issuer_id'));$this->issuer($id,'generate');
        try{$this->origination()->finish($id,$this->staffId(),$this->positiveId($this->input->post('batch_id')));$this->flash('success','Lote retomado com a mesma origem e numeração. Nenhum envio bancário foi realizado.');}
        catch(Throwable $e){$this->flash('warning','Não foi possível confirmar a geração. A reserva e o histórico foram preservados.');}
        redirect($this->url('banco_brasil?issuer_id='.$id).'#bb-remittance');
    }
    public function download_remittance()
    {
        $this->post();$id=$this->positiveId($this->input->post('issuer_id'));$this->issuer($id,'download');
        try{
            $file=$this->origination()->download($id,$this->staffId(),$this->positiveId($this->input->post('batch_id')));
            $this->output->set_header('Cache-Control: no-store');$this->output->set_header('X-Content-Type-Options: nosniff');$this->output->set_header('Content-Disposition: attachment; filename="'.$file['filename'].'"');$this->output->set_content_type('application/octet-stream')->set_output($file['content']);
        }catch(Throwable $e){show_error('Arquivo indisponível ou download não confirmado. Nenhum envio bancário foi realizado.',403);}
    }
    public function report_transmission()
    {
        $this->post();$id=$this->positiveId($this->input->post('issuer_id'));$this->issuer($id,'generate');
        try{$reference=$this->input->post('submission_reference',false);if(!is_string($reference))throw new InvalidArgumentException('invalid_reference');$this->origination()->reportTransmission($id,$this->staffId(),$this->positiveId($this->input->post('batch_id')),$reference);$this->flash('success','Envio manual declarado pelo operador. Aceite, rejeição e liquidação continuam sem confirmação bancária.');}
        catch(Throwable $e){$this->flash('warning','Declaração de envio não confirmada. Confira o lote baixado e a evidência; nenhum estado bancário foi inferido.');}
        redirect($this->url('banco_brasil?issuer_id='.$id).'#bb-remittance');
    }
    public function link_event()
    {
        $this->post();$id=$this->positiveId($this->input->post('issuer_id'));$this->issuer($id,'reconcile');
        try{require_once dirname(__DIR__).'/libraries/BancoBrasil/Reconciliation.php';(new \BancoBrasil\Reconciliation($this->db,db_prefix()))->link($id,$this->staffId(),$this->positiveId($this->input->post('event_id')));$this->flash('success','Origem vinculada por empresa, conta, ambiente, convênio, perfil, Nosso Número, controle e documento. Nenhum pagamento foi criado.');}
        catch(Throwable $e){$this->flash('warning','Vínculo não confirmado. Confira a origem imutável; valor ou nome não permitem forçar um vínculo.');}
        redirect($this->url('banco_brasil?issuer_id='.$id).'#bb-return');
    }
    public function review_event()
    {
        $this->post();$id=$this->positiveId($this->input->post('issuer_id'));$this->issuer($id,'reconcile');
        try{require_once dirname(__DIR__).'/libraries/BancoBrasil/Reconciliation.php';$input=$this->input->post('review',false);if(!is_array($input))throw new InvalidArgumentException('invalid_review');(new \BancoBrasil\Reconciliation($this->db,db_prefix()))->review($id,$this->staffId(),$this->positiveId($this->input->post('event_id')),$input);$this->flash('success','Revisão e evidência registradas no histórico. A revisão não criou nem estornou pagamento.');}
        catch(Throwable $e){$this->flash('warning','Revisão não confirmada. Confira empresa, evento, permissões, motivo e referência.');}
        redirect($this->url('banco_brasil?issuer_id='.$id).'#bb-return');
    }
    public function settle_event()
    {
        $this->post();$id=$this->positiveId($this->input->post('issuer_id'));$this->issuer($id,'reconcile');
        try{require_once dirname(__DIR__).'/libraries/BancoBrasil/ReviewedReceipt.php';$input=$this->input->post('settlement',false);if(!is_array($input))throw new InvalidArgumentException('invalid_settlement');$payment=(new \app\services\InvoicePaymentWriter($this->db))->recordCompanyProviderReceipt($id,$this->staffId(),new \BancoBrasil\ReviewedReceipt($this->positiveId($this->input->post('event_id')),$input));$this->flash('success','Recebimento #'.$payment.' registrado por conciliação assistida. A fonte importada não foi autenticada automaticamente; a confirmação e evidência do operador estão auditadas.');}
        catch(Throwable $e){$this->flash('warning','Recebimento não confirmado. Exige origem vinculada, remessa com envio declarado, crédito conferido fora do arquivo, código06 T/U, BRL, datas válidas, saldo e ausência de encargos/divergências/eventos adversos. Registre revisão; não force baixa manual.');}
        redirect($this->url('banco_brasil?issuer_id='.$id).'#bb-return');
    }
    private function api():\BancoBrasil\ApiService
    {
        require_once dirname(__DIR__).'/libraries/BancoBrasil/ApiService.php';$this->load->library('encryption');
        return new \BancoBrasil\ApiService($this->db,db_prefix(),fn($value)=>$this->encryption->encrypt($value),fn($value)=>$this->encryption->decrypt($value));
    }
    public function declare_api_profile()
    {
        $this->post();$id=$this->positiveId($this->input->post('issuer_id'));$this->issuer($id,'manage');
        try{$input=$this->input->post('api_profile',false);if(!is_array($input))throw new InvalidArgumentException('invalid_api_profile');$this->api()->declareProfile($id,$this->staffId(),$this->positiveId($this->input->post('connection_id')),$input);$this->flash('success','Perfil API declarado. Ambiente e origem ficam imutáveis. A declaração não comprova contrato, titularidade ou homologação.');}
        catch(Throwable $error){$this->flash('warning','Perfil API não confirmado. Confira conexão, ambiente, contrato e faixa exclusiva compartilhada com CNAB; números não podem ser reiniciados.');}
        redirect($this->url('banco_brasil?issuer_id='.$id).'#bb-auth');
    }
    public function save_api_credentials()
    {
        $this->post();$id=$this->positiveId($this->input->post('issuer_id'));$this->issuer($id,'manage');
        try{$input=$this->input->post('api_credentials',false);if(!is_array($input))throw new InvalidArgumentException('invalid_api_credentials');$this->api()->saveCredentials($id,$this->staffId(),$this->positiveId($this->input->post('profile_id')),$input);$this->flash('success','Nova versão de credenciais protegida para o perfil e ambiente indicados. Nenhum token ou operação bancária foi solicitado ao salvar.');}
        catch(Throwable $error){$this->flash('warning','Credenciais não confirmadas. Confira perfil, ambiente, permissão e criptografia nativa. Nenhuma credencial será mostrada na resposta.');}
        redirect($this->url('banco_brasil?issuer_id='.$id).'#bb-auth');
    }
    public function issue_api()
    {
        $this->post();$id=$this->positiveId($this->input->post('issuer_id'));$this->issuer($id,'api_issue');
        try{$input=$this->input->post('api_title',false);$key=$this->input->post('request_key',false);if(!is_array($input)||!is_string($key))throw new InvalidArgumentException('invalid_api_title');$origin=$this->api()->issue($id,$this->staffId(),$this->positiveId($this->input->post('profile_id')),$this->positiveId($this->input->post('invoice_id')),$input,$key);$this->flash('success','Origem API #'.$origin.' registrada. Confira o estado e a resposta validada abaixo; intenção, registro, pedido de baixa e liquidação são estados distintos.');}
        catch(Throwable $error){$this->flash('warning','Registro API não confirmado. A origem e o Nosso Número reservados são preservados. Em resultado desconhecido, consulte a origem; não crie outra cobrança para substituir um timeout.');}
        redirect($this->url('banco_brasil?issuer_id='.$id).'#bb-billing');
    }
    public function resume_api_issue()
    {
        $this->post();$id=$this->positiveId($this->input->post('issuer_id'));$this->issuer($id,'api_issue');
        try{$this->api()->resumeIssue($id,$this->staffId(),$this->positiveId($this->input->post('origin_id')));$this->flash('success','Origem conferida. Somente falha anterior ao envio permite retomar autenticação; requisição já enviada ou desconhecida não é reenviada automaticamente. Confira o estado.');}
        catch(Throwable $error){$this->flash('warning','Retomada não confirmada. A numeração e a origem permanecem preservadas; consulte o banco pela origem antes de outra ação.');}
        redirect($this->url('banco_brasil?issuer_id='.$id).'#bb-billing');
    }
    public function query_api()
    {
        $this->post();$id=$this->positiveId($this->input->post('issuer_id'));$this->issuer($id,'api_query');
        try{$key=$this->input->post('request_key',false);if(!is_string($key))throw new InvalidArgumentException('invalid_api_query');$this->api()->query($id,$this->staffId(),$this->positiveId($this->input->post('origin_id')),$key);$this->flash('success','Consulta pela origem registrada. Confira a resposta validada e seus estados. Esta ação não cria pagamento financeiro.');}
        catch(Throwable $error){$this->flash('warning','Consulta não confirmada. Nenhum estado bancário ou pagamento foi inferido. Confira perfil e credenciais da origem.');}
        redirect($this->url('banco_brasil?issuer_id='.$id).'#bb-billing');
    }
    public function cancel_api()
    {
        $this->post();$id=$this->positiveId($this->input->post('issuer_id'));$this->issuer($id,'api_cancel');
        try{$this->api()->cancel($id,$this->staffId(),$this->positiveId($this->input->post('origin_id')));$this->flash('success','Solicitação processada pela origem API. Pedido de baixa não é confirmação final nem cancelamento local de fatura. Consulte novamente para confirmar o estado bancário.');}
        catch(Throwable $error){$this->flash('warning','Pedido de baixa não confirmado. Exige consulta validada NORMAL nos últimos5 minutos. Origem CNAB, liquidação, protesto e estado desconhecido exigem o fluxo de origem; não há redirecionamento para a conta atual.');}
        redirect($this->url('banco_brasil?issuer_id='.$id).'#bb-billing');
    }
    public function settle_api()
    {
        $this->post();$id=$this->positiveId($this->input->post('issuer_id'));$this->issuer($id,'reconcile');
        try{
            if($this->input->post('credit_confirmed',false)!=='1')throw new DomainException('bb_api_credit_confirmation_required');
            require_once dirname(__DIR__).'/libraries/BancoBrasil/ApiCreditedReceipt.php';
            $source=new \BancoBrasil\ApiCreditedReceipt($this->positiveId($this->input->post('origin_id')),$this->positiveId($this->input->post('operation_id')));
            (new \app\services\InvoicePaymentWriter($this->db))->recordCompanyProviderReceipt($id,$this->staffId(),$source);
            $this->flash('success','Crédito API registrado pelo escritor financeiro e recibo canônico. Repetir a confirmação não duplica pagamento.');
        }catch(Throwable $error){$this->flash('warning','Crédito não confirmado. Exige consulta recente, estado16 CREDITADO, conta e snapshot íntegros, valor integral e sem ajustes. Liquidação6, parcial, cheque, encargos, consulta obsoleta ou evento adverso exigem revisão. Nenhum pagamento desta tentativa foi confirmado.');}
        redirect($this->url('banco_brasil?issuer_id='.$id).'#bb-billing');
    }
    private function staffId():int
    {
        if(class_exists('CompanyRequestScope',false)){$context=\CompanyRequestScope::context();if(is_object($context)&&method_exists($context,'staffId'))return $context->staffId();}
        return get_staff_user_id();
    }
    private function url(string $path=''):string
    {
        if(function_exists('admin_url'))return admin_url($path);
        return $this->config->site_url((defined('ADMIN_URI')?ADMIN_URI:'admin').'/'.$path);
    }
    private function flash(string $kind,string $message):void
    {
        if(function_exists('set_alert'))set_alert($kind,$message);
        $this->session->set_userdata('bb_notice',['kind'=>$kind,'message'=>$message]);
    }
    private function denied():void
    {
        if(function_exists('access_denied'))access_denied('banco_brasil');
        show_error('Acesso negado para este recurso da empresa.',403);throw new RuntimeException('bb_access_denied');
    }
    private function allowed(int $issuer,string $permission):bool
    {
        if(!function_exists('issuer_resource_can'))return false;
        try{return \BancoBrasil\Transaction::quiet($this->db,fn()=>issuer_resource_can($issuer,$this->staffId(),'banco_brasil',$permission))===true;}
        catch(Throwable $e){show_error('Armazenamento indisponível. Tente novamente após verificar a instalação.',503);return false;}
    }
    private function issuerCoreReady():bool
    {
        if(!class_exists('IssuerCore',false))require_once APPPATH.'services/issuers/IssuerCore.php';
        if(!class_exists('IssuerRegistry',false))require_once APPPATH.'services/issuers/IssuerRegistry.php';
        if(!function_exists('issuer_resource_can'))$this->load->helper('issuers');
        return \IssuerCore::ready($this->db,db_prefix())&&function_exists('issuer_resource_can');
    }
    private function companyContextId():?int
    {
        if(!class_exists('CompanyRequestScope',false))return null;
        $context=\CompanyRequestScope::context();
        if(!is_object($context)||!method_exists($context,'issuerId'))$this->denied();
        $id=$context->issuerId();
        if(!is_int($id)||$id<1)$this->denied();
        return $id;
    }
    private function issuer(int $id,string $capability):array
    {
        $companyId=$this->companyContextId();
        if(($companyId!==null&&$companyId!==$id)||!$this->issuerCoreReady()||!$this->allowed($id,$capability))$this->denied();
        try{$row=\BancoBrasil\Transaction::quiet($this->db,fn()=>(new \IssuerRegistry($this->db,db_prefix()))->get($id,$this->staffId()));}
        catch(Throwable $e){show_error('Armazenamento indisponível. Tente novamente após verificar a instalação.',503);return [];}
        if(!$row)$this->denied();
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
