<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyLeads.php';
require_once __DIR__.'/NativeCompanyLeadInput.php';
require_once __DIR__.'/NativeCompanyLeadTableFactory.php';

/** Bounded original list/modal/Kanban adapters. Existing private workspace remains a separate surface. */
final class NativeCompanyLeadHttp
{
    private int $issuer;
    private int $actor;
    private CompanyLeads $leads;
    private IssuerResourceAccess $acl;
    public function __construct(private $ci){}
    private function prepare():void
    {
        if(!CompanyRequestScope::nativeFor($this->ci)||!CompanyRequestScope::sealedFor($this->ci))throw new DomainException('company_lead_access_denied');$ctx=CompanyRequestScope::context();$this->issuer=IssuerPolicy::id($ctx?->issuerId());$this->actor=IssuerPolicy::id($ctx?->staffId());$this->acl=new IssuerResourceAccess($this->ci->db,db_prefix());$this->authorize();$this->leads=new CompanyLeads($this->ci->db,db_prefix());$this->ci->output->set_header('Cache-Control: private, no-store')->set_header('X-Content-Type-Options: nosniff');
    }
    private function authorize():void
    {
        $ctx=CompanyRequestScope::context();if(!CompanyRequestScope::nativeFor($this->ci)||!CompanyRequestScope::sealedFor($this->ci)||$ctx?->issuerId()!==$this->issuer||$ctx?->staffId()!==$this->actor||!$this->acl->can($this->issuer,$this->actor,'leads','view'))throw new DomainException('company_lead_access_denied');
    }
    private function render(string $view,array $data):string
    {
        $level=ob_get_level();ob_start();try{$html=$this->ci->load->view($view,$data,true);if(!is_string($html))throw new RuntimeException('native_lead_render_invalid');$extra=ob_get_clean();$this->authorize();return$extra.$html;}finally{while(ob_get_level()>$level)ob_end_clean();}
    }
    private function token():string
    {
        $token=$this->ci->session->userdata('native_leads_csrf');if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/D',$token)){$token=bin2hex(random_bytes(32));$this->ci->session->set_userdata('native_leads_csrf',$token);}return$token;
    }
    private function capabilities():array
    {
        $caps=[];foreach(['view','create','edit','delete']as$cap)$caps[$cap]=$this->acl->can($this->issuer,$this->actor,'leads',$cap);$caps['convert']=$caps['edit']&&$this->acl->can($this->issuer,$this->actor,'customers','view')&&$this->acl->can($this->issuer,$this->actor,'customers','create');$caps['customers']=$this->acl->can($this->issuer,$this->actor,'customers','view');return$caps;
    }
    private function post():array
    {
        CompanyRequestScope::assertNativeMutation($this->ci);$post=$this->ci->input->post(null,false);$token=is_array($post)?($post['native_leads_csrf']??null):null;if(!is_array($post)||!is_string($token)||!hash_equals($this->token(),$token))throw new DomainException('company_lead_csrf_denied');unset($post['_company_context'],$post['_native_company_csrf'],$post['native_leads_csrf']);if(isset($this->ci->security))unset($post[$this->ci->security->get_csrf_token_name()]);return$post;
    }
    private function json(array $data,int $status=200):void{$this->ci->output->set_status_header($status)->set_content_type('application/json')->set_output(json_encode($data,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE));}
    private function failure(Throwable $error):void
    {
        $reason=$error->getMessage();$status=$error instanceof DomainException?(str_contains($reason,'denied')?403:(str_contains($reason,'stale')||str_contains($reason,'conflict')?409:422)):503;
        $message=match($reason){'company_lead_invalid_reminder'=>'Informe uma data, hora e fuso válidos para o lembrete.','company_lead_conversion_stage'=>'Mova o lead para um status do tipo Ganho antes de converter.','company_lead_closed'=>'O lead está arquivado ou já foi convertido.','company_lead_reference_in_use'=>'Mova ou arquive os leads antes de desativar esta configuração.',default=>$status===403?'Reabra o lead na empresa autorizada.':($status===409?'O lead ou este envio mudou. Reabra antes de salvar.':'Não foi possível confirmar a operação. Confira os dados e tente novamente.')};
        if(!$error instanceof DomainException&&function_exists('log_message'))log_message('error','Native original leads adapter failed closed; no lead data logged.');$this->json(['success'=>false,'message'=>$message],$status);
    }
    private function method(array $allowed):bool
    {
        if(in_array(strtoupper((string)$this->ci->input->method(true)),$allowed,true))return true;$this->ci->output->set_header('Allow: '.implode(', ',$allowed));$this->json(['success'=>false,'message'=>'Método não permitido.'],405);return false;
    }
    private function data(?int $id=null):array
    {
        $detail=$id===null?null:$this->leads->get($this->issuer,$this->actor,$id);$configuration=$this->leads->configuration($this->issuer,$this->actor);$staff=$this->leads->staffOptions($this->issuer,$this->actor);$history=$id===null?[]:$this->leads->nativeHistory($this->issuer,$this->actor,$id);
        return['native_original_leads'=>true,'issuer'=>$this->issuer,'actor'=>$this->actor,'detail'=>$detail,'configuration'=>$configuration,'staff_options'=>$staff,'history'=>$history,'capabilities'=>$this->capabilities(),'native_leads_csrf'=>$this->token(),'canonical_id'=>$id===null?'':NativeCompanyResourceId::encode('company_lead',$id),'request_key'=>bin2hex(random_bytes(32))];
    }
    public function index($canonical=''):void
    {
        try{$this->prepare();if(!$this->method(['GET','HEAD']))return;$id=NativeCompanyLeadInput::source($canonical,true);if($id!==null)$this->leads->get($this->issuer,$this->actor,$id);if($this->ci->input->method(true)==='HEAD'){$this->ci->output->set_output('');return;}
            $view=$this->ci->input->get('view',false)??'list';if(!in_array($view,['list','kanban'],true))throw new DomainException('company_lead_invalid_input');$stage=$this->ci->input->get('status',false);if($stage!==null&&$stage!=='')$stage=IssuerPolicy::id($stage);else$stage=null;
            $factory=new NativeCompanyLeadTableFactory($this->ci->db,db_prefix());$table=$factory->make($this->issuer,$this->actor);require_once __DIR__.'/NativeCompanyFilters.php';$saved=(new NativeCompanyFilters($this->ci->db,db_prefix(),$this->issuer,$this->actor,$table))->listing();$data=$this->data();$data+=['title'=>_l('leads'),'summary'=>$this->leads->nativeSummary($this->issuer,$this->actor),'isKanBan'=>$view==='kanban','table'=>$factory->make($this->issuer,$this->actor,'company_leads',$saved),'leadid'=>$id===null?'':NativeCompanyResourceId::encode('company_lead',$id),'initial_status'=>$stage];$this->ci->output->set_output($this->render('admin/leads/manage_leads',$data));
        }catch(Throwable $error){$this->failure($error);}
    }
    public function table():void
    {
        try{$this->prepare();if(!$this->method(['POST']))return;$post=$this->post();$table=(new NativeCompanyLeadTableFactory($this->ci->db,db_prefix()))->make($this->issuer,$this->actor);if(isset($post['filters'])){if(!is_array($post['filters']))throw new DomainException('native_lead_filter_invalid');if(array_keys($post['filters'])===['match_type'])$post['filters']['rules']=[];$post['filters']=$table->normalizeBuilder($post['filters']);}$page=$this->leads->nativeDirectory($this->issuer,$this->actor,$post);$capabilities=$this->capabilities();$data=[];
            foreach($page['rows']as$lead_row){$row=[];include __DIR__.'/../views/admin/leads/native_table_row.php';$data[]=$row;}if(isset($post['last_order_identifier'],$post['order'])&&get_option('save_last_order_for_tables')==='1'){require_once __DIR__.'/NativeCompanyTableOrder.php';(new NativeCompanyTableOrder($this->ci->db,db_prefix(),$this->issuer,$this->actor))->save('leads',$post['order'],count(CompanyLeads::NATIVE_COLUMNS));}$summary=array_map(static fn($r)=>['id'=>$r['id'],'total'=>$r['total']],$this->leads->nativeSummary($this->issuer,$this->actor));$this->authorize();$this->json(['draw'=>$page['draw'],'recordsTotal'=>$page['recordsTotal'],'recordsFiltered'=>$page['recordsFiltered'],'data'=>$data,'aaData'=>$data,'iTotalRecords'=>$page['recordsTotal'],'iTotalDisplayRecords'=>$page['recordsFiltered'],'summary'=>$summary]);
        }catch(Throwable $error){$this->failure($error);}
    }
    private function upload():array
    {
        $file=$_FILES['lead_file']??null;if(!is_array($file)||($file['error']??null)!==UPLOAD_ERR_OK||!is_int($file['size']??null)||$file['size']>2097152||!is_string($file['name']??null)||!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name']))throw new DomainException('company_lead_upload_invalid');$bytes=file_get_contents($file['tmp_name'],false,null,0,2097153);if(!is_string($bytes)||strlen($bytes)!==$file['size'])throw new DomainException('company_lead_upload_invalid');return[$file['name'],$bytes];
    }
    private function mutate(array $request):int
    {
        ['action'=>$action,'id'=>$id,'revision'=>$revision,'request_key'=>$key,'input'=>$input]=$request;$i=$this->issuer;$a=$this->actor;
        switch($action){
            case'create':$id=$this->leads->create($i,$a,$input,$key);break;
            case'update':$this->leads->update($i,$a,$id,$revision,$input,$key);break;
            case'move':$this->leads->move($i,$a,$id,$revision,$input['stage_id'],$key);break;
            case'note':$this->leads->note($i,$a,$id,$revision,$input['body'],$key);break;
            case'archive':case'restore':$this->leads->archive($i,$a,$id,$revision,$action==='archive',$key);break;
            case'convert':$this->leads->convert($i,$a,$id,$revision,$key);break;
            case'file_upload':[$name,$bytes]=$this->upload();$this->leads->addFile($i,$a,$id,$revision,$name,$bytes,$key);break;
            case'file_hide':$this->leads->hideFile($i,$a,$id,$revision,$input['file_key'],$key);break;
            default:throw new DomainException('company_lead_invalid_input');
        }return$id;
    }
    public function lead($canonical=''):void
    {
        try{$this->prepare();if(!$this->method(['GET','HEAD','POST']))return;$id=NativeCompanyLeadInput::source($canonical,true);$created=false;
            if($this->ci->input->method(true)==='POST'){$request=NativeCompanyLeadInput::decode($this->post(),$canonical);$created=$request['action']==='create';$id=$this->mutate($request);CompanyRequestScope::invalidateCaches($this->ci);}
            $data=$this->data($id);if($id===null&&!$data['capabilities']['create'])throw new DomainException('company_lead_access_denied');$stage=$this->ci->input->get('status',false);$data['initial_status']=$stage===null||$stage===''?null:IssuerPolicy::id($stage);$data['openEdit']=$id===null||$this->ci->input->get('edit',false)==='1';if($this->ci->input->method(true)==='HEAD'){$this->ci->output->set_output('');return;}
            $html=$this->render('admin/leads/lead',$data);$this->json(['success'=>true,'created'=>$created,'id'=>$data['canonical_id'],'revision'=>$data['detail']['revision']??null,'leadView'=>$html]);
        }catch(Throwable $error){$this->failure($error);}
    }
    public function move():void
    {
        try{$this->prepare();if(!$this->method(['POST']))return;$request=NativeCompanyLeadInput::decode($this->post());if($request['action']!=='move')throw new DomainException('company_lead_invalid_input');$id=$this->mutate($request);$row=$this->leads->get($this->issuer,$this->actor,$id);CompanyRequestScope::invalidateCaches($this->ci);$this->authorize();$this->json(['success'=>true,'id'=>NativeCompanyResourceId::encode('company_lead',$id),'revision'=>$row['revision'],'status'=>$row['stage_id']]);
        }catch(Throwable $error){$this->failure($error);}
    }
    public function kanban():void
    {
        try{$this->prepare();if(!$this->method(['GET','HEAD']))return;$search=$this->ci->input->get('search',false)??'';$stage=$this->ci->input->get('status',false);$stage=$stage===null||$stage===''?null:IssuerPolicy::id($stage);$offset=$this->ci->input->get('offset',false)??0;if((!is_string($offset)&&!is_int($offset))||!preg_match('/^(0|[1-9][0-9]{0,9})$/D',(string)$offset)||(float)$offset>2147483647||!is_string($search))throw new DomainException('company_lead_invalid_input');$direction=$this->ci->input->get('sort',false)??'desc';if(!is_string($direction))throw new DomainException('company_lead_invalid_input');$columns=$this->leads->nativeBoard($this->issuer,$this->actor,$search,$stage,(int)$offset,$direction);if($this->ci->input->method(true)==='HEAD'){$this->ci->output->set_output('');return;}
            $data=['native_original_leads'=>true,'columns'=>$columns,'capabilities'=>$this->capabilities(),'actor'=>$this->actor];$html=$this->render($stage===null?'admin/leads/kan-ban':'admin/leads/native_board_cards',$data);$this->json(['success'=>true,'html'=>$html,'next'=>$stage===null?null:($columns[0]['next']??null),'total'=>$stage===null?null:($columns[0]['total']??0)]);
        }catch(Throwable $error){$this->failure($error);}
    }
}
