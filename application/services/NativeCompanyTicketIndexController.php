<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTicketIndexPresentation.php';
require_once __DIR__.'/NativeCompanyTicketRowPresentation.php';
/** Original Tickets/index boundary. No endpoint alias and no legacy model fallback. */
final class NativeCompanyTicketIndexController
{
    private int$i;private int$a;
    public function __construct(private$ci){if(!CompanyRequestScope::nativeFor($ci)||!CompanyRequestScope::sealedFor($ci))throw new DomainException('native_ticket_access_denied');$c=CompanyRequestScope::context();$this->i=(int)$c->issuerId();$this->a=(int)$c->staffId();$this->authorize();}
    private function authorize():void{if(!CompanyRequestScope::nativeFor($this->ci)||!CompanyRequestScope::sealedFor($this->ci)||(int)CompanyRequestScope::context()->issuerId()!==$this->i||(int)CompanyRequestScope::context()->staffId()!==$this->a||!(new IssuerResourceAccess($this->ci->db,db_prefix()))->can($this->i,$this->a,'tickets','view'))throw new DomainException('native_ticket_access_denied');}
    public function index($status='',$userid=''):void
    {
        if(!is_string($status)&&!is_int($status))throw new DomainException('native_ticket_status_invalid');$status=(string)$status;
        // Do not silently turn a client/project/other-ticket view into the complete company list.
        $scope=[];if($userid!==false&&$userid!==''&&$userid!==null&&(string)$userid!=='0')$scope['customer']=$userid;
        $this->ci->output->set_header('Cache-Control: no-store');$method=strtoupper($this->ci->input->method());
        if($method==='GET'&&!$this->ci->input->is_ajax_request()){
            $project=method_exists($this->ci->input,'get')?$this->ci->input->get('project_id',false):false;if($project!==false&&$project!==''&&$project!==null&&(string)$project!=='0')$scope['project']=$project;
            $via=method_exists($this->ci->input,'get')?$this->ci->input->get('via_ticket',false):false;if($via!==false&&$via!==''&&$via!==null)$scope['via_ticket']=$via;
            $data=(new NativeCompanyTicketIndexPresentation($this->ci->db,db_prefix()))->read($this->i,$this->a,$status,$scope);$data['userid']=$scope['customer']??'';NativeCompanyAdminPresentation::prepare($this->ci);$this->authorize();$this->ci->load->view('admin/tickets/list',$data);return;
        }
        if($method!=='POST'||!$this->ci->input->is_ajax_request())throw new DomainException('native_ticket_method_invalid');CompanyRequestScope::assertNativeMutation($this->ci);$this->authorize();$input=$this->ci->input->post(null,false);if(!is_array($input))throw new DomainException('native_ticket_input_invalid');unset($input['_company_context'],$input['_native_company_csrf']);if(isset($this->ci->security))unset($input[$this->ci->security->get_csrf_token_name()]);
        $factory=new NativeCompanyTicketTableFactory($this->ci->db,db_prefix());$table=$factory->make($this->i,$this->a);$catalog=(new NativeCompanyTicketCatalog($this->ci->db,db_prefix()))->list($this->i,$this->a,'status');$ids=array_column($catalog,'ticketstatusid');if($status!==''&&!in_array($status,$ids,true))throw new DomainException('native_ticket_status_invalid');if($status!=='')$scope['status']=$status;
        foreach($input as$key=>$value)if(str_starts_with((string)$key,'ticket_status_')){if(!in_array(substr($key,14),$ids,true)||(!is_string($value)&&!is_int($value))||!in_array((string)$value,['','1',substr($key,14)],true))throw new DomainException('native_ticket_input_invalid');unset($input[$key]);}
        if(isset($input['userid'])&&$input['userid']!==''){
            if(isset($scope['customer'])&&NativeCompanyResourceId::decode('client',$scope['customer'])!==NativeCompanyResourceId::decode('client',$input['userid']))throw new DomainException('native_ticket_scope_invalid');
            $scope['customer']=$input['userid'];
        }
        unset($input['userid']);
        if(isset($input['project_id'])&&$input['project_id']!==''){
            if(isset($scope['project'])&&NativeCompanyResourceId::decode('project',$scope['project'])!==NativeCompanyResourceId::decode('project',$input['project_id']))throw new DomainException('native_ticket_scope_invalid');
            $scope['project']=$input['project_id'];
        }
        unset($input['project_id']);
        if(isset($input['via_ticket'])&&$input['via_ticket']!==''){
            $via=(new NativeCompanyTicketRead($this->ci->db,db_prefix()))->get($this->i,$this->a,$input['via_ticket']);$scope['via_ticket']=$via['ticketid'];
            if(isset($input['via_ticket_userid'])&&(string)$input['via_ticket_userid']!==(string)($via['userid']??0))throw new DomainException('native_ticket_scope_invalid');
            if(isset($input['via_ticket_email'])&&$input['via_ticket_email']!==$via['ticket_email']&&!($via['userid']!==null&&$input['via_ticket_email']===''))throw new DomainException('native_ticket_scope_invalid');
        }elseif(isset($input['via_ticket_userid'])||isset($input['via_ticket_email']))throw new DomainException('native_ticket_scope_invalid');
        unset($input['via_ticket'],$input['via_ticket_userid'],$input['via_ticket_email']);
        $order=$input['last_order_identifier']??null;if($order!==null&&$order!=='tickets')throw new DomainException('native_ticket_input_invalid');unset($input['last_order_identifier']);$builder=$input['filters']??['match_type'=>'and','rules'=>[]];unset($input['filters']);if(!is_array($builder))throw new DomainException('native_ticket_input_invalid');
        // jQuery form encoding omits an empty rules array when clearing the original Vue filter.
        // Normalize only its exact empty transport shape; domain/saved-filter validation stays strict.
        if(array_keys($builder)===['match_type']&&in_array($builder['match_type'],['and','or'],true))$builder['rules']=[];
        $result=(new NativeCompanyTicketIndexRead($this->ci->db,db_prefix()))->page($this->i,$this->a,$input,$builder,$table,$scope);$projection=new NativeCompanyTicketRowPresentation($this->ci->db,db_prefix());$rows=[];foreach($result['rows']as$entry){$variables=$projection->project($this->i,$this->a,$entry,$result['custom_fields']);$rows[]=self::row($variables);}
        if($order!==null)(new NativeCompanyTableOrder($this->ci->db,db_prefix(),$this->i,$this->a))->save('tickets',$input['order']??[['column'=>0,'dir'=>'asc']],count(NativeCompanyTicketIndexRead::COLUMNS)+count($result['custom_fields']));$this->authorize();$this->ci->output->set_content_type('application/json');echo json_encode(['draw'=>$result['draw'],'iTotalRecords'=>$result['iTotalRecords'],'iTotalDisplayRecords'=>$result['iTotalDisplayRecords'],'aaData'=>$rows],JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
    }
    private static function row(array$variables):array{extract($variables,EXTR_SKIP);include __DIR__.'/../views/admin/tickets/table_row.php';return$row;}
}
