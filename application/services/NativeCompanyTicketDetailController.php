<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTicketReadState.php';
require_once __DIR__.'/NativeCompanyTicketThreadPresentation.php';
require_once __DIR__.'/NativeCompanyTicketSettingsPresentation.php';
require_once __DIR__.'/CompanyTickets.php';
require_once __DIR__.'/CompanyOperationalPortalAdmin.php';
final class NativeCompanyTicketDetailController
{
    private int$i;private int$a;
    public function __construct(private$ci){if(!CompanyRequestScope::nativeFor($ci)||!CompanyRequestScope::sealedFor($ci))throw new DomainException('native_ticket_access_denied');$c=CompanyRequestScope::context();$this->i=(int)$c->issuerId();$this->a=(int)$c->staffId();$this->authorize();}
    private function authorize():void{if(!CompanyRequestScope::nativeFor($this->ci)||!CompanyRequestScope::sealedFor($this->ci)||(int)CompanyRequestScope::context()->issuerId()!==$this->i||(int)CompanyRequestScope::context()->staffId()!==$this->a||!(new IssuerResourceAccess($this->ci->db,db_prefix()))->can($this->i,$this->a,'tickets','view'))throw new DomainException('native_ticket_access_denied');}
    private function portalCsrf():string{$token=$this->ci->session->userdata('company_tickets_csrf');if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/D',$token)){$token=bin2hex(random_bytes(32));$this->ci->session->set_userdata('company_tickets_csrf',$token);}return $token;}
    private function portalSource($id):int{$ref=NativeCompanyResourceId::decode('ticket',$id);if($ref['source_kind']!=='company_ticket')throw new DomainException('company_portal_operation_unavailable');return IssuerPolicy::id($ref['source_id']);}
    public function portalAction($id):void{if(strtoupper($this->ci->input->method())!=='POST')throw new DomainException('native_ticket_method_invalid');CompanyRequestScope::assertNativeMutation($this->ci);$csrf=$this->ci->input->post('company_tickets_csrf',false);if(!is_string($csrf)||!hash_equals($this->portalCsrf(),$csrf))throw new DomainException('company_portal_csrf_invalid');$source=$this->portalSource($id);$action=$this->ci->input->post('action',false);$revision=$this->ci->input->post('revision',false);$key=$this->ci->input->post('request_key',false);if(!is_string($action)||!is_string($revision)||!is_string($key))throw new DomainException('company_operation_invalid_input');if(!CompanyOperationalPortalAdmin::handle($this->ci,$this->i,$this->a,'tickets',$action,$source,$revision,$key))throw new DomainException('company_operation_invalid_input');CompanyRequestScope::invalidateCaches($this->ci);redirect(CompanyRequestScope::adminUrl($this->ci,'tickets/ticket/'.$id),'location',303);}
    /**
     * Render the existing Tickets/single route from issuer-owned projections.
     * The original template has a finite native branch; no global ticket model,
     * option lookup, hook, note or reminder query is allowed into this request.
     */
    public function show($id):void
    {
        if(!in_array(strtoupper($this->ci->input->method()),['GET','HEAD'],true))throw new DomainException('native_ticket_method_invalid');
        $this->authorize();
        $thread=(new NativeCompanyTicketThreadPresentation($this->ci->db,db_prefix()))->read($this->i,$this->a,$id);
        $ticket=$thread['ticket'];$metadata=(array)($ticket->metadata??[]);$status=(array)($ticket->catalog['status']??[]);
        $historical=$thread['native_ticket_presentation']['historical'];
        if($historical)$status=['native_id'=>null,'name'=>match($ticket->historical_status){'open'=>'Aberto (histórico)','resolved'=>'Resolvido (histórico)','closed'=>'Fechado (histórico)',default=>throw new DomainException('native_ticket_status_unavailable')},'statuscolor'=>'#475569'];
        elseif(!$status||!isset($status['native_id'],$status['name'],$status['statuscolor']))throw new DomainException('native_ticket_status_unavailable');
        $ticket->ticketstatusid=$status['native_id'];$ticket->statuscolor=$status['statuscolor'];$ticket->status_name=$status['name'];
        $ticket->merged_ticket_id=$metadata['merged_ticket_id']??null;$merged=$metadata['merged_tickets']??[];
        if(!is_array($merged))throw new DomainException('native_ticket_merge_provenance_invalid');
        $ticket->staff_id_replying=isset($metadata['staff_replying'])&&$metadata['staff_replying']!==null?(int)$metadata['staff_replying']:null;
        $ticket->adminread=isset($metadata['admin_read'])?(int)$metadata['admin_read']:0;
        $settings=null;if(!$historical)try{$settings=(new NativeCompanyTicketSettingsPresentation($this->ci->db,db_prefix()))->read($this->i,$this->a,$id);}catch(DomainException$error){if(!in_array($error->getMessage(),['native_ticket_update_denied'],true))throw$error;}
        $catalog=new NativeCompanyTicketCatalog($this->ci->db,db_prefix());$statuses=$catalog->list($this->i,$this->a,'status');
        $portalControls=null;$portalCsrf=null;$portalRevision=null;if(($ticket->source_kind??null)==='company_ticket'){$source=IssuerPolicy::id($ticket->source_id);$portalRow=(new CompanyTickets($this->ci->db,db_prefix()))->get($this->i,$this->a,$source);$portalControls=CompanyOperationalPortalAdmin::controls($this->ci,$this->i,$this->a,'tickets',$portalRow);$portalRevision=(int)$portalRow['revision'];$portalCsrf=$this->portalCsrf();}$data=$thread+['ticket'=>$ticket,'merged_tickets'=>$merged,'statuses'=>$statuses,'native_ticket_settings'=>$settings,'portal_controls'=>$portalControls,'portal_revision'=>$portalRevision,'company_tickets_csrf'=>$portalCsrf,'title'=>$ticket->subject,
            'native_ticket_page'=>['context_token'=>CompanyRequestScope::token(),'csrf_token'=>CompanyRequestScope::nativeCsrfToken($this->ci),'request_nonce'=>bin2hex(random_bytes(32))]];
        if($settings!==null){$settings['ticket']->merged_ticket_id=$ticket->merged_ticket_id;$data['native_ticket_settings']=$settings;foreach(['staff','departments','priorities','services']as$key)$data[$key]=$settings[$key];$data['native_ticket_presentation']=array_replace($thread['native_ticket_presentation'],$settings['native_ticket_presentation']);}
        NativeCompanyAdminPresentation::prepare($this->ci);$this->authorize();
        $this->ci->load->view('admin/tickets/single',$data);
        $this->authorize();
    }
    public function markRead($id):void
    {
        if(strtoupper($this->ci->input->method())!=='POST')throw new DomainException('native_ticket_method_invalid');CompanyRequestScope::assertNativeMutation($this->ci);$this->authorize();$data=$this->ci->input->post(null,false);if(!is_array($data))throw new DomainException('native_ticket_input_invalid');unset($data['_company_context'],$data['_native_company_csrf']);if(isset($this->ci->security))unset($data[$this->ci->security->get_csrf_token_name()]);
        if(array_diff(array_keys($data),['native_ticket_command','revision','request_nonce'])||($data['native_ticket_command']??null)!=='mark_read'||!is_string($data['revision']??null)||!is_string($data['request_nonce']??null))throw new DomainException('native_ticket_input_invalid');$result=(new NativeCompanyTicketReadState($this->ci->db,db_prefix()))->mark($this->i,$this->a,$id,$data['revision'],$data['request_nonce']);$this->authorize();$this->ci->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode($result,JSON_THROW_ON_ERROR));
    }
}
