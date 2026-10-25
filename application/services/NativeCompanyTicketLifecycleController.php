<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTicketUpdates.php';
/** Existing Tickets mutation routes; no legacy model hydration in an attested named context. */
final class NativeCompanyTicketLifecycleController
{
    private int $issuer;private int $actor;
    public function __construct(private $ci){
        if(!CompanyRequestScope::nativeFor($ci)||!CompanyRequestScope::sealedFor($ci))throw new DomainException('native_ticket_update_denied');
        $c=CompanyRequestScope::context();$this->issuer=(int)$c->issuerId();$this->actor=(int)$c->staffId();$this->authorize(strtolower((string)$ci->router->fetch_method())!=='check_staff_replying');
    }
    private function authorize(bool$edit=true):void{ $acl=new IssuerResourceAccess($this->ci->db,db_prefix());foreach($edit?['view','edit']:['view']as$cap)if(!$acl->can($this->issuer,$this->actor,'tickets',$cap))throw new DomainException('native_ticket_update_denied'); }
    public function changeStatus($id,$status):void{
        CompanyRequestScope::assertNativeMutation($this->ci);$this->authorize();$data=$this->ci->input->post(null,false);
        if(!is_array($data))throw new DomainException('native_ticket_input_invalid');
        unset($data['_company_context'],$data['_native_company_csrf']);if(isset($this->ci->security))unset($data[$this->ci->security->get_csrf_token_name()]);
        if(array_diff(array_keys($data),['revision','request_nonce'])||!is_string($data['revision']??null)||!is_string($data['request_nonce']??null))throw new DomainException('native_ticket_input_invalid');
        $result=(new NativeCompanyTicketUpdates($this->ci->db,db_prefix()))->changeStatus($this->issuer,$this->actor,$id,$status,$data['revision'],$data['request_nonce']);$this->authorize();
        $result+=['success'=>true,'alert'=>'success','message'=>_l('ticket_status_changed_successfully')];
        $this->ci->output->set_content_type('application/json')->set_output(json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
    }
    public function editMessage():void{
        CompanyRequestScope::assertNativeMutation($this->ci);$this->authorize();$data=$this->ci->input->post(null,false);
        if(!is_array($data))throw new DomainException('native_ticket_input_invalid');
        unset($data['_company_context'],$data['_native_company_csrf']);if(isset($this->ci->security))unset($data[$this->ci->security->get_csrf_token_name()]);
        if(array_diff(array_keys($data),['type','id','main_ticket','data','revision','reply_revision','request_nonce'])||!in_array($data['type']??null,['ticket','reply'],true)||!is_string($data['data']??null)||!is_string($data['revision']??null)||!is_string($data['request_nonce']??null))throw new DomainException('native_ticket_input_invalid');
        $service=new NativeCompanyTicketUpdates($this->ci->db,db_prefix());
        if($data['type']==='ticket'){if(isset($data['reply_revision'])||(string)($data['id']??'')!==(string)($data['main_ticket']??''))throw new DomainException('native_ticket_input_invalid');$result=$service->editMessage($this->issuer,$this->actor,$data['id'],$data['data'],$data['revision'],$data['request_nonce']);}
        else{if(!is_string($data['reply_revision']??null)||!isset($data['id'],$data['main_ticket']))throw new DomainException('native_ticket_input_invalid');$result=$service->editReply($this->issuer,$this->actor,$data['main_ticket'],$data['id'],$data['data'],$data['revision'],$data['reply_revision'],$data['request_nonce']);}$this->authorize();
        $result+=['success'=>true,'alert'=>'success','message'=>_l('ticket_message_updated_successfully')];
        $this->ci->output->set_content_type('application/json')->set_output(json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
    }

    public function blockSender():void{
        CompanyRequestScope::assertNativeMutation($this->ci);$this->authorize();$d=$this->ci->input->post(null,false);if(!is_array($d))throw new DomainException('native_ticket_input_invalid');unset($d['_company_context'],$d['_native_company_csrf']);if(isset($this->ci->security))unset($d[$this->ci->security->get_csrf_token_name()]);
        if(array_diff(array_keys($d),['ticketid','revision','request_nonce'])||!isset($d['ticketid'])||!is_string($d['revision']??null)||!is_string($d['request_nonce']??null))throw new DomainException('native_ticket_input_invalid');
        require_once __DIR__.'/NativeCompanySpamFilters.php';$result=(new NativeCompanySpamFilters($this->ci->db,db_prefix()))->blockTicketSender($this->issuer,$this->actor,$d['ticketid'],$d['revision'],$d['request_nonce']);$this->authorize();
        $this->ci->output->set_content_type('application/json')->set_output(json_encode($result+['success'=>true],JSON_THROW_ON_ERROR));
    }

    public function checkReplying($id):void{
        if(strtoupper($this->ci->input->method())!=='GET')throw new DomainException('native_ticket_method_invalid');$this->authorize(false);
        $r=(new NativeCompanyTicketUpdates($this->ci->db,db_prefix()))->replying($this->issuer,$this->actor,$id);$this->authorize(false);
        $this->ci->output->set_content_type('application/json')->set_output(json_encode(['is_other_staff_replying'=>$r['is_other_staff_replying'],'message'=>$r['is_other_staff_replying']?e(_l('staff_is_currently_replying',$r['name'])):'','state'=>$r['state']],JSON_THROW_ON_ERROR));
    }
    public function updateReplying($id,$userId):void{
        CompanyRequestScope::assertNativeMutation($this->ci);$this->authorize();if($userId!==''&&(string)$userId!==(string)$this->actor)throw new DomainException('native_ticket_actor_invalid');
        $d=$this->ci->input->post(null,false);if(!is_array($d))throw new DomainException('native_ticket_input_invalid');unset($d['_company_context'],$d['_native_company_csrf']);if(isset($this->ci->security))unset($d[$this->ci->security->get_csrf_token_name()]);if(array_diff(array_keys($d),['revision'])||!is_string($d['revision']??null))throw new DomainException('native_ticket_input_invalid');
        $r=(new NativeCompanyTicketUpdates($this->ci->db,db_prefix()))->updateReplying($this->issuer,$this->actor,$id,$d['revision'],$userId!=='');$this->authorize();$this->ci->output->set_content_type('application/json')->set_output(json_encode($r,JSON_THROW_ON_ERROR));
    }

    public function deleteAttachment(string$key):void{
        CompanyRequestScope::assertNativeMutation($this->ci);$this->authorize();$d=$this->ci->input->post(null,false);if(!is_array($d))throw new DomainException('native_ticket_input_invalid');unset($d['_company_context'],$d['_native_company_csrf']);if(isset($this->ci->security))unset($d[$this->ci->security->get_csrf_token_name()]);
        if(array_diff(array_keys($d),['revision','parent_revision','request_nonce'])||!is_string($d['revision']??null)||!is_string($d['request_nonce']??null)||!is_string($d['parent_revision']??null))throw new DomainException('native_ticket_input_invalid');$ticket=$this->ci->input->get('ticket_id');
        $r=(new NativeCompanyTicketUpdates($this->ci->db,db_prefix()))->hideFile($this->issuer,$this->actor,$ticket,$key,$d['revision'],$d['parent_revision'],$d['request_nonce']);$this->authorize();$this->ci->output->set_content_type('application/json')->set_output(json_encode($r+['success'=>true],JSON_THROW_ON_ERROR));
    }

}
