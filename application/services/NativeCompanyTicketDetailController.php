<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTicketReadState.php';
final class NativeCompanyTicketDetailController
{
    private int$i;private int$a;
    public function __construct(private$ci){if(!CompanyRequestScope::nativeFor($ci)||!CompanyRequestScope::sealedFor($ci))throw new DomainException('native_ticket_access_denied');$c=CompanyRequestScope::context();$this->i=(int)$c->issuerId();$this->a=(int)$c->staffId();$this->authorize();}
    private function authorize():void{if(!CompanyRequestScope::nativeFor($this->ci)||!CompanyRequestScope::sealedFor($this->ci)||(int)CompanyRequestScope::context()->issuerId()!==$this->i||(int)CompanyRequestScope::context()->staffId()!==$this->a||!(new IssuerResourceAccess($this->ci->db,db_prefix()))->can($this->i,$this->a,'tickets','view'))throw new DomainException('native_ticket_access_denied');}
    public function markRead($id):void
    {
        if(strtoupper($this->ci->input->method())!=='POST')throw new DomainException('native_ticket_method_invalid');CompanyRequestScope::assertNativeMutation($this->ci);$this->authorize();$data=$this->ci->input->post(null,false);if(!is_array($data))throw new DomainException('native_ticket_input_invalid');unset($data['_company_context'],$data['_native_company_csrf']);if(isset($this->ci->security))unset($data[$this->ci->security->get_csrf_token_name()]);
        if(array_diff(array_keys($data),['native_ticket_command','revision','request_nonce'])||($data['native_ticket_command']??null)!=='mark_read'||!is_string($data['revision']??null)||!is_string($data['request_nonce']??null))throw new DomainException('native_ticket_input_invalid');$result=(new NativeCompanyTicketReadState($this->ci->db,db_prefix()))->mark($this->i,$this->a,$id,$data['revision'],$data['request_nonce']);$this->authorize();$this->ci->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode($result,JSON_THROW_ON_ERROR));
    }
}
