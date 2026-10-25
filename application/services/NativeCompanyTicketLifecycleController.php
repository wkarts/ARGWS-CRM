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

    public function deleteRecord($ticket,$reply=null):void{
        CompanyRequestScope::assertNativeMutation($this->ci);$this->authorize();$d=$this->ci->input->post(null,false);if(!is_array($d))throw new DomainException('native_ticket_input_invalid');unset($d['_company_context'],$d['_native_company_csrf']);if(isset($this->ci->security))unset($d[$this->ci->security->get_csrf_token_name()]);
        if(array_diff(array_keys($d),$reply===null?['revision','request_nonce']:['revision','reply_revision','request_nonce'])||!is_string($d['revision']??null)||!is_string($d['request_nonce']??null)||($reply!==null&&!is_string($d['reply_revision']??null)))throw new DomainException('native_ticket_input_invalid');
        require_once __DIR__.'/NativeCompanyTicketDeletion.php';$r=(new NativeCompanyTicketDeletion($this->ci->db,db_prefix()))->delete($this->issuer,$this->actor,$ticket,$d['revision'],$d['request_nonce'],$reply,$d['reply_revision']??null);$this->authorize();$this->ci->output->set_content_type('application/json')->set_output(json_encode($r,JSON_THROW_ON_ERROR));
    }

    public function updateSettings():void{
        CompanyRequestScope::assertNativeMutation($this->ci);$this->authorize();$d=$this->ci->input->post(null,false);if(!is_array($d))throw new DomainException('native_ticket_input_invalid');unset($d['_company_context'],$d['_native_company_csrf']);if(isset($this->ci->security))unset($d[$this->ci->security->get_csrf_token_name()]);
        foreach(['revision','tags_revision','custom_fields_revision','request_nonce']as$k)if(!is_string($d[$k]??null))throw new DomainException('native_ticket_input_invalid');if(!isset($d['ticketid']))throw new DomainException('native_ticket_input_invalid');
        $merge=$d['merge_ticket_ids']??'';if(!is_string($merge))throw new DomainException('native_ticket_input_invalid');$children=[];
        if($merge!==''){
            if(!is_array($d['merge_revisions']??null))throw new DomainException('native_ticket_merge_revisions_required');$ids=explode(',',$merge);if(count($ids)>100||count(array_unique($ids))!==count($ids))throw new DomainException('native_ticket_input_invalid');
            foreach($ids as$id){if(trim($id)!==$id||NativeCompanyResourceId::decode('ticket',$id)['source_kind']!=='company_ticket'||$id===(string)$d['ticketid'])throw new DomainException('native_ticket_input_invalid');$children[$id]=$d['merge_revisions'][$id]??null;}
            if(array_diff(array_map('strval',array_keys($d['merge_revisions'])),$ids))throw new DomainException('native_ticket_input_invalid');
        }elseif(isset($d['merge_revisions']))throw new DomainException('native_ticket_input_invalid');
        $ticket=$d['ticketid'];$expected=$d['revision'];$tags=$d['tags_revision'];$fields=$d['custom_fields_revision'];$nonce=$d['request_nonce'];unset($d['ticketid'],$d['revision'],$d['tags_revision'],$d['custom_fields_revision'],$d['request_nonce'],$d['merge_ticket_ids'],$d['merge_revisions']);
        $updates=new NativeCompanyTicketUpdates($this->ci->db,db_prefix());
        if($children){$before=(new NativeCompanyTicketRead($this->ci->db,db_prefix()))->get($this->issuer,$this->actor,$ticket);$r=$updates->settingsAndMerge($this->issuer,$this->actor,$ticket,$d,$children,$before['status'],$expected,$tags,$fields,$nonce);}
        else$r=$updates->settings($this->issuer,$this->actor,$ticket,$d,$expected,$tags,$fields,$nonce);$this->authorize();$this->ci->output->set_content_type('application/json')->set_output(json_encode($r+['success'=>true],JSON_THROW_ON_ERROR));
    }

    /** Original detail form POST. A successful local reply is not an SMTP delivery claim. */
    public function reply($ticket):void{
        CompanyRequestScope::assertNativeMutation($this->ci);$this->authorize();$d=$this->ci->input->post(null,false);if(!is_array($d))throw new DomainException('native_ticket_input_invalid');unset($d['_company_context'],$d['_native_company_csrf']);if(isset($this->ci->security))unset($d[$this->ci->security->get_csrf_token_name()]);
        if(array_diff(array_keys($d),['ticketid','message','status','cc','assign_to_current_user','ticket_add_response_and_back_to_list','revision','request_nonce'])||(string)($d['ticketid']??'')!==(string)$ticket||!is_string($d['message']??null)||!is_string($d['revision']??null)||!is_string($d['request_nonce']??null))throw new DomainException('native_ticket_input_invalid');
        foreach(['assign_to_current_user','ticket_add_response_and_back_to_list']as$key)if(isset($d[$key])&&!in_array($d[$key],['1','on'],true))throw new DomainException('native_ticket_input_invalid');
        foreach($_FILES as$upload){if(!is_array($upload)||!array_key_exists('error',$upload))throw new DomainException('native_ticket_attachments_unavailable');foreach((array)$upload['error']as$error)if($error!==UPLOAD_ERR_NO_FILE)throw new DomainException('native_ticket_attachments_unavailable');}
        $options=['assign_to_current_user'=>isset($d['assign_to_current_user'])];if(array_key_exists('cc',$d))$options['cc']=$d['cc'];$status=$d['status']??null;if($status==='')$status=null;
        (new NativeCompanyTicketUpdates($this->ci->db,db_prefix()))->reply($this->issuer,$this->actor,$ticket,$d['message'],$status,$d['revision'],$d['request_nonce'],$options);$this->authorize();
        redirect(CompanyRequestScope::adminUrl($this->ci,isset($d['ticket_add_response_and_back_to_list'])?'tickets':'tickets/ticket/'.$ticket),'location',303);
    }
    public function mergePreview($ticket):void{
        if(strtoupper($this->ci->input->method())!=='GET')throw new DomainException('native_ticket_method_invalid');$this->authorize();$ids=$this->ci->input->get('merge_preview');if(!is_string($ids)||$ids==='')throw new DomainException('native_ticket_input_invalid');
        $r=(new NativeCompanyTicketUpdates($this->ci->db,db_prefix()))->mergePreview($this->issuer,$this->actor,$ticket,explode(',',$ids));$this->authorize();$this->ci->output->set_content_type('application/json')->set_output(json_encode($r,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
    }
    public function bulk():void{
        CompanyRequestScope::assertNativeMutation($this->ci);$this->authorize();$d=$this->ci->input->post(null,false);if(!is_array($d))throw new DomainException('native_ticket_input_invalid');unset($d['_company_context'],$d['_native_company_csrf']);if(isset($this->ci->security))unset($d[$this->ci->security->get_csrf_token_name()]);
        if(($d['mass_delete']??null)==='1'){
            if(array_diff(array_keys($d),['mass_delete','ids','revisions','request_nonce'])||!is_array($d['ids']??null)||!is_array($d['revisions']??null)||!is_string($d['request_nonce']??null))throw new DomainException('native_ticket_input_invalid');
            $ids=[];foreach($d['ids']as$id){if(!is_string($id)&&!is_int($id))throw new DomainException('native_ticket_input_invalid');if(NativeCompanyResourceId::decode('ticket',$id)['source_kind']!=='company_ticket')throw new DomainException('native_ticket_input_invalid');$ids[]=(string)$id;}
            if(!$ids||count($ids)>100||count(array_unique($ids))!==count($ids)||array_diff($ids,array_map('strval',array_keys($d['revisions'])))||array_diff(array_map('strval',array_keys($d['revisions'])),$ids))throw new DomainException('native_ticket_input_invalid');
            require_once __DIR__.'/NativeCompanyTicketDeletion.php';$r=(new NativeCompanyTicketDeletion($this->ci->db,db_prefix()))->deleteMany($this->issuer,$this->actor,$d['revisions'],$d['request_nonce']);$this->authorize();$this->ci->output->set_content_type('application/json')->set_output(json_encode($r,JSON_THROW_ON_ERROR));return;
        }
        if(!isset($d['merge_tickets'])){
            if(array_diff(array_keys($d),['ids','revisions','tag_revisions','status','department','priority','service','tags','request_nonce'])||!is_array($d['ids']??null)||!is_array($d['revisions']??null)||!is_array($d['tag_revisions']??[])||!is_string($d['request_nonce']??null))throw new DomainException('native_ticket_input_invalid');
            $ids=[];foreach($d['ids']as$id){if(!is_int($id)&&!is_string($id))throw new DomainException('native_ticket_input_invalid');$ids[]=(string)$id;}if(!$ids||count(array_unique($ids))!==count($ids)||array_diff($ids,array_map('strval',array_keys($d['revisions'])))||array_diff(array_map('strval',array_keys($d['revisions'])),$ids))throw new DomainException('native_ticket_input_invalid');
            $changes=array_intersect_key($d,array_flip(['status','department','priority','service','tags']));$r=(new NativeCompanyTicketUpdates($this->ci->db,db_prefix()))->bulkUpdate($this->issuer,$this->actor,$d['revisions'],$changes,$d['tag_revisions']??[],$d['request_nonce']);$this->authorize();$this->ci->output->set_content_type('application/json')->set_output(json_encode($r,JSON_THROW_ON_ERROR));return;
        }
        if($d['merge_tickets']!=='1')throw new DomainException('native_ticket_input_invalid');
        if(array_diff(array_keys($d),['merge_tickets','ids','primary_ticket','primary_ticket_status','revisions','request_nonce'])||!is_array($d['ids']??null)||!is_array($d['revisions']??null)||!is_string($d['request_nonce']??null)||!isset($d['primary_ticket'],$d['primary_ticket_status']))throw new DomainException('native_ticket_input_invalid');
        $ids=array_map(static function($id){if(!is_int($id)&&!is_string($id))throw new DomainException('native_ticket_input_invalid');$ref=NativeCompanyResourceId::decode('ticket',$id);if($ref['source_kind']!=='company_ticket')throw new DomainException('native_ticket_input_invalid');return(string)$id;},$d['ids']);
        if(count($ids)<2||count($ids)>101||count(array_unique($ids))!==count($ids)||!in_array((string)$d['primary_ticket'],$ids,true)||array_diff(array_map('strval',array_keys($d['revisions'])),$ids)||array_diff($ids,array_map('strval',array_keys($d['revisions']))))throw new DomainException('native_ticket_input_invalid');
        $children=$d['revisions'];$expected=$children[(string)$d['primary_ticket']];unset($children[(string)$d['primary_ticket']]);if(!is_string($expected))throw new DomainException('native_ticket_input_invalid');
        $r=(new NativeCompanyTicketUpdates($this->ci->db,db_prefix()))->merge($this->issuer,$this->actor,$d['primary_ticket'],$children,$d['primary_ticket_status'],$expected,$d['request_nonce']);$this->authorize();$this->ci->output->set_content_type('application/json')->set_output(json_encode($r+['success'=>true,'merged'=>count($children)],JSON_THROW_ON_ERROR));
    }

}
