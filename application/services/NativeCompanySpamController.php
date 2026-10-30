<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanySpamFilters.php';
require_once __DIR__.'/NativeCompanyDataTableWire.php';
final class NativeCompanySpamController
{
    private int$i;private int$a;private NativeCompanySpamFilters$service;
    public function __construct(private$ci){if(!CompanyRequestScope::nativeFor($ci)||!CompanyRequestScope::sealedFor($ci))throw new DomainException('native_spam_context_invalid');$c=CompanyRequestScope::context();$this->i=(int)$c->issuerId();$this->a=(int)$c->staffId();$this->service=new NativeCompanySpamFilters($ci->db,db_prefix());if(!(new IssuerResourceAccess($ci->db,db_prefix()))->administrative($this->i,$this->a))throw new DomainException('native_spam_denied');}
    private function input():array{CompanyRequestScope::assertNativeMutation($this->ci);$d=$this->ci->input->post(null,false);if(!is_array($d))throw new DomainException('native_spam_input_invalid');unset($d['_company_context'],$d['_native_company_csrf']);if(isset($this->ci->security))unset($d[$this->ci->security->get_csrf_token_name()]);return$d;}
    private function json(array$d):void{$this->ci->output->set_content_type('application/json')->set_output(json_encode($d,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));}
    public function view(string$rel,string$type=''):void{
        $all=$this->service->listing($this->i,$this->a,$rel);
        if(!$this->ci->input->is_ajax_request()){NativeCompanyAdminPresentation::prepare($this->ci);$this->ci->load->view('admin/spam_filters/list',['rel_type'=>$rel,'title'=>_l('spam_filters'),'native_spam'=>['context'=>CompanyRequestScope::token(),'csrf'=>CompanyRequestScope::nativeCsrfToken($this->ci),'nonce'=>bin2hex(random_bytes(32))]]);return;}
        if(!in_array($type,['sender','subject','phrase'],true))throw new DomainException('native_spam_input_invalid');$query=NativeCompanyDataTableWire::request($this->input(),['value','actions']);foreach($query['orders']as$o)if($o['field']!=='value')throw new DomainException('native_spam_input_invalid');
        $records=array_values(array_filter($all,fn($r)=>$r['type']===$type));$total=count($records);if($query['search']!=='')$records=array_values(array_filter($records,fn($r)=>mb_stripos($r['value'],$query['search'],0,'UTF-8')!==false));$filtered=count($records);$direction=$query['orders'][0]['direction'];usort($records,static fn($a,$b)=>($direction==='asc'?1:-1)*strnatcasecmp($a['value'],$b['value'])?:($a['id']<=>$b['id']));if($query['length']!==-1)$records=array_slice($records,$query['start'],$query['length']);
        $rows=[];foreach($records as$aRow){$filter_type=$type;$rel_type=$rel;$native_spam_row=true;$rows[]=require APPPATH.'views/admin/spam_filters/table_row.php';}
        if($all!==$this->service->listing($this->i,$this->a,$rel))throw new DomainException('native_spam_changed');$this->json(['draw'=>$query['draw'],'iTotalRecords'=>$total,'iTotalDisplayRecords'=>$filtered,'aaData'=>$rows]);
    }
    public function filter(string$rel):void{$d=$this->input();if(array_diff(array_keys($d),['id','type','value','revision','request_nonce'])||!is_string($d['type']??null)||!is_string($d['value']??null)||!is_string($d['request_nonce']??null))throw new DomainException('native_spam_input_invalid');$id=($d['id']??'')===''?null:$d['id'];$revision=($d['revision']??'')===''?null:$d['revision'];$r=$this->service->save($this->i,$this->a,$rel,$d['type'],$d['value'],$id,$revision,$d['request_nonce']);$this->json($r+['success'=>true,'message'=>_l($id===null?'added_successfully':'updated_successfully',_l('spam_filter'))]);}
    public function delete($id,string$rel):void{$d=$this->input();if(array_diff(array_keys($d),['revision','request_nonce'])||!is_string($d['revision']??null)||!is_string($d['request_nonce']??null))throw new DomainException('native_spam_input_invalid');$r=$this->service->delete($this->i,$this->a,$rel,$id,$d['revision'],$d['request_nonce']);$this->json($r+['success'=>true]);}
}
