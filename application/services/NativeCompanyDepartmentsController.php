<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyDepartments.php';
require_once __DIR__.'/NativeCompanyDataTableWire.php';
final class NativeCompanyDepartmentsController
{
    private int $issuer;private int $actor;private NativeCompanyDepartments $service;
    public function __construct(private $ci){if(!CompanyRequestScope::nativeFor($ci)||!CompanyRequestScope::sealedFor($ci))throw new DomainException('native_department_denied');$ctx=CompanyRequestScope::context();$this->issuer=(int)$ctx->issuerId();$this->actor=(int)$ctx->staffId();$this->authorize();$this->service=new NativeCompanyDepartments($ci->db,db_prefix());}
    private function authorize():void{if(!(new IssuerResourceAccess($this->ci->db,db_prefix()))->administrative($this->issuer,$this->actor))throw new DomainException('native_department_denied');}
    private function json($value):void{$this->ci->output->set_content_type('application/json')->set_output(json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));}
    private function mutation():array{CompanyRequestScope::assertNativeMutation($this->ci);$this->authorize();$data=$this->ci->input->post(null,false);if(!is_array($data))throw new DomainException('native_department_input_invalid');unset($data['_company_context'],$data['_native_company_csrf']);if(isset($this->ci->security))unset($data[$this->ci->security->get_csrf_token_name()]);return$data;}
    private function records():array{$out=[];foreach($this->service->catalog($this->issuer,$this->actor)as$row)$out[]=$this->service->get($this->issuer,$this->actor,$row['departmentid'],true);return$out;}
    private function warnings(array $rows):array
    {
        $collision=false;$gmail=false;foreach($rows as$row){if($row['has_password']&&str_ends_with(strtolower($row['email']),'@gmail.com'))$gmail=true;if($row['email']==='')continue;
            $staff=IssuerStorage::query($this->ci->db,'SELECT s.staffid FROM '.db_prefix().'staff s JOIN '.db_prefix().'issuers_staff m ON m.staff_id=s.staffid AND m.issuer_id=? AND m.can_view=1 WHERE s.active=1 AND s.email=?',[$this->issuer,$row['email']])->result_array();if($staff)$collision=true;
        }return['email_exist_as_staff'=>$collision,'native_department_gmail_warning'=>$gmail];
    }
    public function index():void
    {
        $input=$this->ci->input->is_ajax_request()?$this->mutation():null;$rows=$this->records();if($input===null){NativeCompanyAdminPresentation::prepare($this->ci);$data=$this->warnings($rows)+['native_departments'=>true,'native_department_context'=>CompanyRequestScope::token(),'native_department_csrf'=>CompanyRequestScope::nativeCsrfToken($this->ci),'title'=>_l('departments')];$this->authorize();$this->ci->load->view('admin/departments/manage',$data);return;}
        $query=NativeCompanyDataTableWire::request($input,['departmentid','name','email','calendar_id','options']);foreach($query['orders']as$order)if($order['field']==='options')throw new DomainException('native_department_input_invalid');$total=count($rows);$rows=array_values(array_filter($rows,static function($r)use($query){if($query['search']==='')return true;foreach(['departmentid','name','email','calendar_id']as$key)if(mb_stripos((string)$r[$key],$query['search'],0,'UTF-8')!==false)return true;return false;}));$filtered=count($rows);
        usort($rows,static function($a,$b)use($query){foreach($query['orders']as$order){$c=strnatcasecmp((string)$a[$order['field']],(string)$b[$order['field']]);if($c!==0)return$order['direction']==='asc'?$c:-$c;}return strcmp($a['departmentid'],$b['departmentid']);});if($query['length']!==-1)$rows=array_slice($rows,$query['start'],$query['length']);
        $aColumns=['departmentid','name','email','calendar_id'];$native_departments=true;$output=[];foreach($rows as$aRow)$output[]=require APPPATH.'views/admin/departments/table_row.php';$this->authorize();$this->json(['draw'=>$query['draw'],'iTotalRecords'=>$total,'iTotalDisplayRecords'=>$filtered,'aaData'=>$output]);
    }
    public function save():void{$data=$this->mutation();$id=($data['id']??'')!==''?$data['id']:null;$revision=$data['revision']??null;unset($data['id'],$data['revision'],$data['fakeusernameremembered'],$data['fakepasswordremembered']);$this->ci->load->library('encryption');$record=$this->service->save($this->issuer,$this->actor,$data,$this->ci->encryption,$id,$revision);$this->json(['success'=>true,'message'=>_l($id===null?'added_successfully':'updated_successfully',_l('department')),'revision'=>$record['revision_token']]+$this->warnings($this->records()));}
    public function emailExists():void{$data=$this->mutation();if(array_diff(array_keys($data),['departmentid','email'])||!is_string($data['email']??null))throw new DomainException('native_department_input_invalid');$id=$data['departmentid']??'';if($id!=='')$this->service->get($this->issuer,$this->actor,$id,true);$valid=true;foreach($this->records()as$row)if($row['departmentid']!==(string)$id&&$data['email']!==''&&strcasecmp($row['email'],$data['email'])===0)$valid=false;$this->json($valid);}
    public function delete($id):void{$data=$this->mutation();$this->service->delete($this->issuer,$this->actor,$id,$data['revision']??'');$this->json(['success'=>true]);}
    public function probe(bool $folders):void
    {
        $data=$this->mutation();$this->ci->load->library('encryption');
        $factory=static function($username,$password,$host,$encryption){if(!function_exists('imap_open'))throw new RuntimeException('native_department_imap_unavailable');return new \app\services\imap\Imap($username,$password,$host,$encryption,'',true);};
        try{$result=$this->service->probe($this->issuer,$this->actor,$data,$this->ci->encryption,$factory,$folders);$this->json($folders?$result:['alert_type'=>'success','message'=>_l('lead_email_connection_ok')]);}
        catch(DomainException$e){throw$e;}catch(Throwable$e){$this->json(['alert_type'=>'warning','message'=>_l('lead_email_connection_not_ok')]);}
    }

}
