<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyExpenseOriginalForm.php';

/** Original expense routes keep the same selected-company, POST and CSRF boundaries. */
final class NativeCompanyExpenseOriginalHttp
{
    private int $issuer;
    private int $actor;
    private NativeCompanyExpenseOriginalForm $form;
    public function __construct(private $ci){}
    private function prepare():void
    {
        $ci=$this->ci;if(!CompanyRequestScope::sealedFor($ci)||!CompanyRequestScope::nativeFor($ci))throw new DomainException('company_expense_denied');
        $context=CompanyRequestScope::context();$this->issuer=IssuerPolicy::id($context?->issuerId());$this->actor=IssuerPolicy::id($context?->staffId());
        $this->form=new NativeCompanyExpenseOriginalForm($ci->db,db_prefix());$ci->output->set_header('Cache-Control: private, no-store');
    }
    private function token():string
    {
        $token=$this->ci->session->userdata('native_expenses_csrf');
        if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/D',$token)){$token=bin2hex(random_bytes(32));$this->ci->session->set_userdata('native_expenses_csrf',$token);}
        return $token;
    }
    private function post():array
    {
        CompanyRequestScope::assertNativeMutation($this->ci);
        if(strtoupper((string)$this->ci->input->method(true))!=='POST')throw new DomainException('native_expense_form_method_invalid');
        $post=$this->ci->input->post(null,false);$token=$post['native_expenses_csrf']??null;
        if(!is_array($post)||!is_string($token)||!hash_equals($this->token(),$token))throw new DomainException('native_expense_form_csrf_denied');
        unset($post['_company_context'],$post['_native_company_csrf'],$post['native_expenses_csrf']);
        if(isset($this->ci->security))unset($post[$this->ci->security->get_csrf_token_name()]);
        return $post;
    }
    private function url($canonical):string{return CompanyRequestScope::adminUrl($this->ci,'expenses/list_expenses/'.$canonical.'?source=company');}
    private function assertContext():void
    {
        $context=CompanyRequestScope::context();
        if(!CompanyRequestScope::sealedFor($this->ci)||!CompanyRequestScope::nativeFor($this->ci)
            ||$context?->issuerId()!==$this->issuer||$context?->staffId()!==$this->actor){
            throw new DomainException('company_expense_denied');
        }
    }
    private function renderFrame(array $data):string
    {
        $fields=new NativeCompanyCustomFields($this->ci->db,db_prefix());
        $id=$data['native_expense_id'];
        $definitions=$id===''?$fields->definitions($this->issuer,$this->actor,'expenses',['active'=>1],'create')
            :$fields->definitionsForResource($this->issuer,$this->actor,'expenses',$id,['active'=>1],'edit');
        $values=$id===''?[]:$fields->values($this->issuer,$this->actor,'expenses',$id,'edit');
        return hash('sha256',json_encode([$data,$definitions,$values],JSON_THROW_ON_ERROR));
    }
    private function json(array $data,int $status=200):void
    {
        $this->ci->output->set_status_header($status)->set_content_type('application/json')->set_output(json_encode($data,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE));
    }
    private function failure(Throwable $error):void
    {
        $reason=$error->getMessage();$status=$error instanceof DomainException?(str_contains($reason,'denied')||str_contains($reason,'csrf')?403:(str_contains($reason,'stale')||str_contains($reason,'conflict')?409:422)):503;
        $message=match($reason){
            'native_custom_field_required'=>'Preencha os campos personalizados obrigatórios.',
            'native_expense_form_due_days_required'=>'Informe o prazo em dias para as faturas recorrentes.',
            'native_expense_form_schedule_control_required'=>'Esta despesa já possui uma agenda. Use os controles da recorrência no detalhe.',
            'native_expense_form_profile_required'=>'Esta despesa conserva o formato anterior de impostos. Use o formulário do detalhe para preservar seus valores.',
            default=>$status===403?'Reabra a despesa na empresa autorizada antes de salvar.':($status===409?'A despesa ou este envio mudou. Reabra o formulário antes de salvar.':'Não foi possível confirmar a gravação. Confira os campos e reabra a despesa antes de tentar novamente.'),
        };
        if(!$error instanceof DomainException)log_message('error','Native original expense form failed closed; no expense data logged.');
        $this->json(['success'=>false,'message'=>$message],$status);
    }
    /** False retains the existing editor for scalar/immutable/scheduled source contracts. */
    public function expense($canonical=''):bool
    {
        $bufferLevel=ob_get_level();
        try{
            $this->prepare();
            $verb=strtoupper((string)$this->ci->input->method(true));
            if($verb==='POST'){
                $post=$this->post();$base=isset($this->ci->config)?$this->ci->config->base_url():'';
                $decrypt=function($value){$this->ci->load->library('encryption');return $this->ci->encryption->decrypt($value);};
                $result=$this->form->save($this->issuer,$this->actor,$canonical,$post,$base,$decrypt);
                CompanyRequestScope::invalidateCaches($this->ci);
                $this->json(['success'=>true,'expenseid'=>$result['expenseid'],'url'=>$this->url($result['expenseid']),'upload_key'=>hash('sha256','original-expense-file|'.$post['request_key'])]);return true;
            }
            if(!in_array($verb,['GET','HEAD'],true)){$this->ci->output->set_header('Allow: GET, HEAD, POST');$this->json(['success'=>false,'message'=>'Método não permitido.'],405);return true;}
            $customer=$this->ci->input->get('customer_id',false)??'';
            $data=$this->form->form($this->issuer,$this->actor,$canonical,$customer);
            if($data===null)return false;
            if($verb==='HEAD'){$this->ci->output->set_output('');return true;}
            $frame=$this->renderFrame($data);
            $data['native_expenses_csrf']=$this->token();$data['native_expense_request_key']=bin2hex(random_bytes(32));
            $data['native_expense_form_action']=CompanyRequestScope::adminUrl($this->ci,'expenses/expense'.($canonical===''?'':'/'.$canonical).'?source=company');
            ob_start();
            $html=$this->ci->load->view('admin/expenses/expense',$data,true);
            while(ob_get_level()>$bufferLevel)ob_end_clean();
            $this->assertContext();
            $current=$this->form->form($this->issuer,$this->actor,$canonical,$customer);
            if(!is_string($html)||$current===null||!hash_equals($frame,$this->renderFrame($current)))throw new DomainException('company_expense_stale');
            $this->assertContext();
            $this->ci->output->set_output($html);return true;
        }catch(Throwable $error){while(ob_get_level()>$bufferLevel)ob_end_clean();$this->failure($error);return true;}
    }
    public function customer($id=''):void
    {
        try{
            $this->prepare();
            $verb=strtoupper((string)$this->ci->input->method(true));if(!in_array($verb,['GET','HEAD'],true)){$this->ci->output->set_header('Allow: GET, HEAD');$this->json(['success'=>false,'message'=>'Método não permitido.'],405);return;}
            $result=$this->form->customer($this->issuer,$this->actor,$id);
            if($verb==='HEAD'){$this->ci->output->set_output('');return;}$this->json($result);
        }catch(Throwable $error){$this->failure($error);}
    }
    public function attachment($canonical):void
    {
        try{
            $this->prepare();
            $post=$this->post();$id=NativeCompanyExpenseOriginalForm::source($canonical);
            if(($post['native_expense_form']??null)!==NativeCompanyExpenseOriginalForm::WIRE||(string)($post['expense_id']??'')!==(string)$canonical||!is_string($post['request_key']??null))throw new DomainException('company_expense_denied');
            $file=$_FILES['file']??null;
            if(!is_array($file)||($file['error']??null)!==UPLOAD_ERR_OK||!is_int($file['size']??null)||$file['size']>2097152||!is_string($file['name']??null)||!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name']))throw new DomainException('company_expense_file_invalid');
            $bytes=file_get_contents($file['tmp_name'],false,null,0,2097153);if(!is_string($bytes))throw new DomainException('company_expense_file_invalid');
            (new CompanyExpenseFiles($this->ci->db,db_prefix()))->upload($this->issuer,$this->actor,$id,$post['request_key'],$file['name'],$bytes);
            CompanyRequestScope::invalidateCaches($this->ci);$this->json(['success'=>true,'expenseid'=>(string)$canonical,'url'=>$this->url($canonical)]);
        }catch(Throwable $error){$this->failure($error);}
    }
}
