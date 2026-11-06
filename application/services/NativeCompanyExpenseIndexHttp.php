<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyExpenseIndex.php';
require_once __DIR__.'/NativeCompanyExpenseTableFactory.php';
require_once __DIR__.'/NativeCompanyDateInput.php';

/** Original list, selected preview and summary; the current audited workspace remains available. */
final class NativeCompanyExpenseIndexHttp
{
    private int $issuer;
    private int $actor;
    private IssuerResourceAccess $acl;
    private NativeCompanyExpenseIndex $index;
    private array $permissions;
    public function __construct(private $ci){}
    public static function workspace($ci):bool
    {
        if(strtoupper((string)$ci->input->method(true))==='POST'||$ci->input->get('workspace',false)==='1')return true;
        foreach(['expense','file','after','search','recurring']as$key)if($ci->input->get($key,false)!==null)return true;
        return false;
    }
    private function capabilities():array
    {
        $caps=[];foreach(['view','create','edit','delete']as$cap)$caps[$cap]=$this->acl->can($this->issuer,$this->actor,'expenses',$cap);
        foreach(['customers','projects','invoices','tasks']as$feature)$caps[$feature]=$this->acl->can($this->issuer,$this->actor,$feature,'view');
        $caps['invoice_create']=$this->acl->can($this->issuer,$this->actor,'invoices','create');
        $caps['tasks_create']=$this->acl->can($this->issuer,$this->actor,'tasks','create');
        $caps['bulk_pdf']=$this->acl->can($this->issuer,$this->actor,'bulk_pdf_exporter','view');return$caps;
    }
    private function prepare():void
    {
        if(!CompanyRequestScope::nativeFor($this->ci)||!CompanyRequestScope::sealedFor($this->ci))throw new DomainException('company_expense_denied');$ctx=CompanyRequestScope::context();
        $this->issuer=IssuerPolicy::id($ctx?->issuerId());$this->actor=IssuerPolicy::id($ctx?->staffId());$this->acl=new IssuerResourceAccess($this->ci->db,db_prefix());$this->permissions=$this->capabilities();$this->authorize();$this->index=new NativeCompanyExpenseIndex($this->ci->db,db_prefix());
        $this->ci->output->set_header('Cache-Control: private, no-store')->set_header('X-Content-Type-Options: nosniff');
    }
    private function authorize():void
    {
        $ctx=CompanyRequestScope::context();if(!CompanyRequestScope::nativeFor($this->ci)||!CompanyRequestScope::sealedFor($this->ci)||$ctx?->issuerId()!==$this->issuer||$ctx?->staffId()!==$this->actor||!$this->permissions['view']||$this->capabilities()!==$this->permissions)throw new DomainException('company_expense_denied');
    }
    private function render(string $view,array $data):string
    {
        $level=ob_get_level();ob_start();try{$html=$this->ci->load->view($view,$data,true);if(!is_string($html))throw new RuntimeException('native_expense_render_invalid');$extra=ob_get_clean();$this->authorize();return$extra.$html;}finally{while(ob_get_level()>$level)ob_end_clean();}
    }
    private function token():string
    {
        $token=$this->ci->session->userdata('native_expenses_csrf');if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/D',$token)){$token=bin2hex(random_bytes(32));$this->ci->session->set_userdata('native_expenses_csrf',$token);}return$token;
    }
    private function post():array
    {
        CompanyRequestScope::assertNativeMutation($this->ci);$post=$this->ci->input->post(null,false);$token=is_array($post)?($post['native_expenses_csrf']??null):null;if(!is_array($post)||!is_string($token)||!hash_equals($this->token(),$token))throw new DomainException('company_expense_csrf_denied');
        unset($post['_company_context'],$post['_native_company_csrf'],$post['native_expenses_csrf']);if(isset($this->ci->security))unset($post[$this->ci->security->get_csrf_token_name()]);return$post;
    }
    private function json(array $data,int $status=200):void{$this->ci->output->set_status_header($status)->set_content_type('application/json')->set_output(json_encode($data,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE));}
    private function method(array $allowed):bool
    {
        if(in_array(strtoupper((string)$this->ci->input->method(true)),$allowed,true))return true;$this->ci->output->set_header('Allow: '.implode(', ',$allowed));$this->json(['success'=>false,'message'=>'Método não permitido.'],405);return false;
    }
    private function failure(Throwable $error):void
    {
        $reason=$error->getMessage();$status=$error instanceof DomainException?(str_contains($reason,'denied')?403:(str_contains($reason,'changed')||str_contains($reason,'stale')||str_contains($reason,'conflict')?409:422)):503;
        if(!$error instanceof DomainException&&function_exists('log_message'))log_message('error','Native original expense index failed closed; no expense data logged.');
        $this->json(['success'=>false,'message'=>$status===403?'Reabra as despesas na empresa autorizada.':($status===409?'As despesas ou as permissões mudaram. Reabra a página.':'Não foi possível carregar as despesas. Reabra a página antes de tentar novamente.')],$status);
    }
    private function data():array{return['native_expense_index'=>true,'issuer'=>$this->issuer,'actor'=>$this->actor,'capabilities'=>$this->permissions,'native_expenses_csrf'=>$this->token(),'dates'=>new NativeCompanyDateInput($this->ci->db,db_prefix(),$this->issuer)];}
    public function index($canonical=''):void
    {
        try{$this->prepare();if(!$this->method(['GET','HEAD']))return;if($canonical!==''&&$canonical!==null)$this->index->preview($this->issuer,$this->actor,$canonical);
            if(strtoupper((string)$this->ci->input->method(true))==='HEAD'){$this->ci->output->set_output('');return;}
            $presentation=$this->index->presentation($this->issuer,$this->actor);$factory=new NativeCompanyExpenseTableFactory($this->ci->db,db_prefix());$table=$factory->make($this->issuer,$this->actor);require_once __DIR__.'/NativeCompanyFilters.php';$filters=(new NativeCompanyFilters($this->ci->db,db_prefix(),$this->issuer,$this->actor,$table))->listing();
            $data=$this->data()+$presentation+['table'=>$factory->make($this->issuer,$this->actor,'company_expenses',$filters),'expenseid'=>(string)$canonical,'title'=>_l('expenses')];$html=$this->render('admin/expenses/manage',$data);if($presentation!==$this->index->presentation($this->issuer,$this->actor))throw new DomainException('native_expense_snapshot_changed');$this->authorize();$this->ci->output->set_output($html);
        }catch(Throwable $error){$this->failure($error);}
    }
    public function table($client=''):void
    {
        try{$this->prepare();if(!$this->method(['POST']))return;$post=$this->post();$table=(new NativeCompanyExpenseTableFactory($this->ci->db,db_prefix()))->make($this->issuer,$this->actor);if(isset($post['filters'])){if(!is_array($post['filters']))throw new DomainException('native_expense_filter_invalid');if(array_keys($post['filters'])===['match_type'])$post['filters']['rules']=[];$post['filters']=$table->normalizeBuilder($post['filters']);}
            $page=$this->index->page($this->issuer,$this->actor,$post,$client);$currencyRows=(new NativeCompanyCurrencyReference($this->ci->db,db_prefix(),$this->issuer,$this->actor))->currencies();$currencies=array_column($currencyRows,null,'name');$dates=new NativeCompanyDateInput($this->ci->db,db_prefix(),$this->issuer);$capabilities=$this->permissions;$rows=[];
            foreach($page['rows']as$expense_row){$row=[];include __DIR__.'/../views/admin/expenses/native_table_row.php';$rows[]=$row;}
            if(isset($post['last_order_identifier'],$post['order'])&&get_option('save_last_order_for_tables')==='1'){require_once __DIR__.'/NativeCompanyTableOrder.php';(new NativeCompanyTableOrder($this->ci->db,db_prefix(),$this->issuer,$this->actor))->save('expenses',$post['order'],count(NativeCompanyExpenseIndex::COLUMNS)+count($page['definitions']));}
            $this->authorize();$this->json(['draw'=>$page['draw'],'recordsTotal'=>$page['recordsTotal'],'recordsFiltered'=>$page['recordsFiltered'],'data'=>$rows,'aaData'=>$rows,'iTotalRecords'=>$page['recordsTotal'],'iTotalDisplayRecords'=>$page['recordsFiltered']]);
        }catch(Throwable $error){$this->failure($error);}
    }
    public function totals():void
    {
        try{$this->prepare();if(!$this->method(['POST']))return;$post=$this->post();$summary=$this->index->totals($this->issuer,$this->actor,$post);$html=$this->render('admin/expenses/expenses_total_template',$this->data()+['summary'=>$summary]);if($this->index->totals($this->issuer,$this->actor,$post)!==$summary)throw new DomainException('native_expense_snapshot_changed');$this->authorize();$this->json(['success'=>true,'html'=>$html]);}catch(Throwable $error){$this->failure($error);}
    }
    private function related(array $data):array
    {
        require_once __DIR__.'/NativeCompanyAuxiliaryUi.php';$ui=new NativeCompanyAuxiliaryUi($this->ci->db,db_prefix(),$this->issuer,$this->actor,$this->ci);$members=$ui->members();$canonical=$data['expense_row']['id'];$pending=0;$offset=0;
        do{$page=$ui->reminderTable('expense',$canonical,$offset,100,'',true);foreach($page['rows']as$row)if((int)$row['staff']===$this->actor)$pending++;$offset+=count($page['rows']);if($offset>100000||($offset<$page['filtered']&&count($page['rows'])===0))throw new DomainException('native_expense_snapshot_changed');}while($offset<$page['filtered']);
        $creator='';foreach($members as$member)if((int)$member['staffid']===(int)$data['expense_row']['created_by'])$creator=trim($member['firstname'].' '.$member['lastname']);
        $schedules=[];if($data['expense_row']['source_kind']==='company_expense'){$service=new CompanyExpenseRecurring($this->ci->db,db_prefix());foreach($data['schedules']as$schedule)$schedules[]=$service->document($this->issuer,$this->actor,$schedule['id']);}
        return['members'=>$members,'pending_reminders'=>$pending,'creator_name'=>$creator,'recurring_details'=>$schedules];
    }
    public function preview($canonical):void
    {
        try{$this->prepare();if(!$this->method(['GET','HEAD']))return;$data=$this->index->preview($this->issuer,$this->actor,$canonical);if(strtoupper((string)$this->ci->input->method(true))==='HEAD'){$this->ci->output->set_output('');return;}
            $related=$this->related($data);$html=$this->render('admin/expenses/expense_preview_template',$this->data()+$data+$related);if($this->index->preview($this->issuer,$this->actor,$canonical)!==$data||$this->related($data)!==$related)throw new DomainException('native_expense_snapshot_changed');$this->authorize();$this->json(['success'=>true,'expenseid'=>$data['expense_row']['id'],'html'=>$html]);
        }catch(Throwable $error){$this->failure($error);}
    }
    /** Categories already have an explicit company owner; historical global categories keep their legacy route. */
    public function categories():void
    {
        try{
            $this->prepare();if(!$this->method(['GET','HEAD','POST']))return;$service=new CompanyExpenses($this->ci->db,db_prefix());$snapshot=$service->categories($this->issuer,$this->actor);
            if(strtoupper((string)$this->ci->input->method(true))==='HEAD'){$this->ci->output->set_output('');return;}
            if(strtoupper((string)$this->ci->input->method(true))==='GET'){
                $html=$this->render('admin/expenses/manage_categories',$this->data()+['native_expense_categories'=>true,'native_expense_category_inline'=>false,'title'=>_l('expense_categories')]);
                if($service->categories($this->issuer,$this->actor)!==$snapshot)throw new DomainException('company_expense_stale');$this->authorize();$this->ci->output->set_output($html);return;
            }
            $wire=NativeCompanyDataTableWire::request($this->post(),['id','name','description','options']);$rows=$snapshot;
            if($wire['search']!=='')$rows=array_values(array_filter($rows,static fn($row)=>mb_stripos($row['id'].' '.$row['name'].' '.($row['description']??''),$wire['search'],0,'UTF-8')!==false));
            $filtered=count($rows);$orders=$wire['orders'];usort($rows,static function($left,$right)use($orders){foreach($orders as$order){$field=$order['field'];if($field==='options')continue;$cmp=$field==='id'?(int)$left['id']<=>(int)$right['id']:strcmp(mb_strtolower((string)($left[$field]??''),'UTF-8'),mb_strtolower((string)($right[$field]??''),'UTF-8'));if($cmp!==0)return$order['direction']==='desc'?-$cmp:$cmp;}return(int)$left['id']<=>(int)$right['id'];});
            $rows=array_slice($rows,min($wire['start'],count($rows)),$wire['length']===-1?500:min(500,$wire['length']));$result=[];
            foreach($rows as$category){
                $id=NativeCompanyResourceId::encode('company_expense_category',$category['id']);$dto=['id'=>$id,'name'=>$category['name'],'description'=>$category['description']??'','active'=>(int)$category['active'],'revision'=>$category['revision_token']];
                $attrs=' data-native-expense-category="edit" data-category="'.e(json_encode($dto,JSON_THROW_ON_ERROR)).'"';$link=static fn($text)=>'<a href="#"'.$attrs.'>'.e($text).'</a>';
                $name=$this->permissions['edit']?$link($category['name']):e($category['name']);if((int)$category['active']!==1)$name.=' <span class="label label-default">Inativa</span>';
                $options=$this->permissions['edit']?'<a href="#"'.$attrs.' class="tw-text-neutral-500" title="'.e(_l('edit')).'"><i class="fa-regular fa-pen-to-square fa-lg"></i></a>':'';
                $result[]=[e($category['id']),$name,nl2br(e($category['description']??'')),$options,'DT_RowAttr'=>['data-category-id'=>$id]];
            }
            if($service->categories($this->issuer,$this->actor)!==$snapshot)throw new DomainException('company_expense_stale');$this->authorize();$this->json(['draw'=>$wire['draw'],'recordsTotal'=>count($snapshot),'recordsFiltered'=>$filtered,'data'=>$result,'aaData'=>$result,'iTotalRecords'=>count($snapshot),'iTotalDisplayRecords'=>$filtered]);
        }catch(Throwable $error){$this->failure($error);}
    }
    public function category():void
    {
        try{
            $this->prepare();if(!$this->method(['POST']))return;$post=$this->post();
            if(array_diff(array_keys($post),['native_expense_category','id','name','description','active','revision','request_key'])||($post['native_expense_category']??null)!=='original_expense_category_v1'||!is_string($post['id']??null)||!is_string($post['name']??null)||!is_string($post['description']??null)||!is_string($post['revision']??null)||!is_string($post['request_key']??null)||!in_array($post['active']??null,['0','1',0,1],true))throw new DomainException('native_expense_category_invalid');
            $source=null;$revision=null;if($post['id']!==''){$identity=NativeCompanyResourceId::decode('expense_category',$post['id']);if($identity['source_kind']!=='company_expense_category')throw new DomainException('company_expense_denied');$source=$identity['source_id'];$revision=$post['revision'];}elseif($post['revision']!=='')throw new DomainException('native_expense_category_invalid');
            $service=new CompanyExpenses($this->ci->db,db_prefix());$id=$service->saveCategory($this->issuer,$this->actor,$post['name'],$post['request_key'],$source,$revision,(string)$post['active']==='1',$post['description']);
            $this->authorize();CompanyRequestScope::invalidateCaches($this->ci);$this->json(['success'=>true,'id'=>NativeCompanyResourceId::encode('company_expense_category',$id),'name'=>trim($post['name']),'active'=>(int)$post['active'],'message'=>_l($source===null?'added_successfully':'updated_successfully',_l('expense_category'))]);
        }catch(Throwable $error){$this->failure($error);}
    }
    public function bulk():void
    {
        try{$this->prepare();if(!$this->method(['POST']))return;require_once __DIR__.'/NativeCompanyExpenseBulk.php';$result=(new NativeCompanyExpenseBulk($this->ci->db,db_prefix()))->apply($this->issuer,$this->actor,$this->post());$this->authorize();CompanyRequestScope::invalidateCaches($this->ci);$this->json(['success'=>true]+$result+['message'=>$result['operation']==='cancel'?'Despesas canceladas; o histórico foi preservado.':'Despesas atualizadas.']);}catch(Throwable $error){$this->failure($error);}
    }
}
