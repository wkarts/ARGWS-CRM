<?php

declare(strict_types=1);

require_once __DIR__.'/NativePortalIdentity.php';
require_once __DIR__.'/NativePortalPresentation.php';
require_once __DIR__.'/NativeCompanyResourceId.php';
require_once __DIR__.'/CompanyPortalOperations.php';
require_once __DIR__.'/CompanyPortalSupportWrite.php';
require_once __DIR__.'/CompanyPortalSupportFiles.php';
require_once __DIR__.'/CompanyOperationFilesHttp.php';

/** Contact-bound support workflow on the original Clients ticket routes. */
final class NativePortalTicketsHttp
{
    private int $issuer;
    private string $secret;
    private NativePortalIdentity $identities;
    private array $identity;
    private CompanyPortal $portal;
    private CompanyPortalOperations $reader;

    public function __construct(private $ci)
    {
        $method=strtolower((string)$ci->router->fetch_method());
        if(!PortalRequestScope::sealedFor($ci)||strtolower((string)$ci->router->fetch_class())!=='clients'
            ||!in_array($method,['tickets','open_ticket','ticket'],true))throw new DomainException('company_portal_operation_unavailable');
        $this->issuer=PortalRequestScope::issuerId();
        $secret=$ci->config->item('encryption_key');
        if(!is_string($secret)||strlen($secret)<32)throw new RuntimeException('issuer_portal_secret_required');
        $this->secret=$secret;$this->identities=new NativePortalIdentity($ci->db,db_prefix(),$secret);
        $identity=$this->identities->current($ci->session,$this->issuer);
        if(!$identity){redirect(NativePortalPresentation::url('authentication/login'),'location',303);throw new DomainException('company_portal_operation_unavailable');}
        $this->identity=$identity;$this->portal=new CompanyPortal($ci->db,db_prefix(),$secret);
        $this->reader=new CompanyPortalOperations($ci->db,db_prefix(),$this->portal);
    }

    public function index($status=''):void
    {
        try{
            $this->readVerb();$status=$status===null?'':(string)$status;
            if(!in_array($status,['','open','resolved','closed'],true))throw new DomainException('company_portal_operation_unavailable');
            $after=$this->positive($this->ci->input->get('after',false)??0,true)??0;
            $page=$this->reader->page($this->identity['binding'],$this->issuer,'tickets',$after);
            if($page===null)throw new DomainException('company_portal_operation_unavailable');
            $items=[];foreach($page['items']as$row){if($status!==''&&$row['status']!==$status)continue;$items[]=$this->project($row);}
            $this->confirm();$this->ci->data(['title'=>_l('clients_tickets_heading'),'native_portal_tickets'=>['items'=>$items,'next'=>$page['next']], 'native_portal_ticket_status'=>$status]);
            $this->ci->view('tickets');$this->ci->layout();
        }catch(DomainException$error){$this->deny($error);}
    }

    public function open():void
    {
        try{
            $verb=$this->verb();
            if($verb==='POST'){
                PortalRequestScope::assertMutation($this->ci);$key=$this->ci->input->post('request_key',false);
                $subject=$this->ci->input->post('subject',false);$body=$this->ci->input->post('message',false);$priority=$this->ci->input->post('priority',false);
                if(!is_string($key)||!is_string($subject)||!is_string($body)||!is_string($priority))throw new DomainException('company_portal_support_invalid');
                $id=(new CompanyPortalSupportWrite($this->ci->db,db_prefix(),$this->portal))->open($this->identity['binding'],$this->issuer,['subject'=>$subject,'body'=>$body,'priority'=>$priority],$key);
                $this->confirm();redirect($this->ticketUrl($id),'location',303);return;
            }
            if(!in_array($verb,['GET','HEAD'],true))$this->methodDenied('GET, HEAD, POST');
            // A null page is the permission/publication gate; opening a ticket does not
            // require an already-published ticket, only the contact's native support grant.
            $page=$this->reader->page($this->identity['binding'],$this->issuer,'tickets',0);
            if($page===null)throw new DomainException('company_portal_operation_unavailable');
            $this->confirm();$this->ci->data(['title'=>_l('clients_ticket_open_subject'),'native_portal_open_ticket'=>true,'native_portal_ticket_request'=>bin2hex(random_bytes(32))]);
            $this->ci->view('open_ticket');$this->ci->layout();
        }catch(DomainException$error){$this->deny($error);}
    }

    public function detail($canonical):void
    {
        try{
            $id=$this->source($canonical);$verb=$this->verb();
            if($verb==='POST'){$this->mutate($id);return;}
            if(!in_array($verb,['GET','HEAD'],true))$this->methodDenied('GET, HEAD, POST');
            $row=$this->reader->detail($this->identity['binding'],$this->issuer,'tickets',$id);$this->confirm();
            $this->ci->data(['title'=>$row['name'],'native_portal_ticket'=>$this->project($row),'native_portal_ticket_request'=>bin2hex(random_bytes(32))]);
            $this->ci->view('single_ticket');$this->ci->layout();
        }catch(DomainException$error){$this->deny($error);}
    }

    private function mutate(int$id):void
    {
        PortalRequestScope::assertMutation($this->ci);$action=$this->ci->input->post('native_ticket_action',false);
        if(!is_string($action))throw new DomainException('company_portal_support_invalid');
        if($action==='file'){
            $key=$this->ci->input->post('file_key',false);if(!is_string($key))throw new DomainException('company_portal_operation_unavailable');
            $file=$this->reader->file($this->identity['binding'],$this->issuer,'tickets',$id,$key);$this->confirm();
            $this->ci->output->set_header('Cache-Control: private, no-store')->set_header('X-Content-Type-Options: nosniff')
                ->set_header("Content-Security-Policy: sandbox; default-src 'none'")
                ->set_header('Content-Disposition: attachment; filename="download"; filename*=UTF-8\'\''.rawurlencode($file['filename']))
                ->set_content_type($file['mime'])->set_output($file['content']);return;
        }
        $revision=$this->ci->input->post('revision',false);$key=$this->ci->input->post('request_key',false);
        if(!is_string($key))throw new DomainException('company_portal_support_invalid');
        if($action==='reply'){
            $body=$this->ci->input->post('message',false);if(!is_string($body))throw new DomainException('company_portal_support_invalid');
            (new CompanyPortalSupportWrite($this->ci->db,db_prefix(),$this->portal))->reply($this->identity['binding'],$this->issuer,$id,$revision,$body,$key);
        }elseif($action==='upload'){
            [$name,$bytes]=CompanyOperationFilesHttp::upload();
            (new CompanyPortalSupportFiles($this->ci->db,db_prefix(),$this->portal))->upload($this->identity['binding'],$this->issuer,$id,$revision,$key,$name,$bytes);
        }elseif($action==='hide'){
            $file=$this->ci->input->post('file_key',false);if(!is_string($file))throw new DomainException('company_portal_support_invalid');
            (new CompanyPortalSupportFiles($this->ci->db,db_prefix(),$this->portal))->hide($this->identity['binding'],$this->issuer,$id,$revision,$file,$key);
        }else throw new DomainException('company_portal_support_invalid');
        $this->confirm();redirect($this->ticketUrl($id),'location',303);
    }

    private function project(array$row):array
    {
        return $row+['canonical_id'=>NativeCompanyResourceId::encode('company_ticket',(string)$row['id'])];
    }
    private function source($value):int
    {
        try{$decoded=NativeCompanyResourceId::decode('ticket',$value);}catch(DomainException$error){throw new DomainException('company_portal_operation_unavailable',0,$error);}
        if($decoded['source_kind']!=='company_ticket')throw new DomainException('company_portal_operation_unavailable');return IssuerPolicy::id($decoded['source_id']);
    }
    private function positive($value,bool$optional=false):?int
    {
        if($optional&&($value===0||$value==='0'||$value===null||$value===''))return null;
        if((!is_int($value)&&!is_string($value))||!preg_match('/^[1-9][0-9]{0,9}$/D',(string)$value))throw new DomainException('company_portal_operation_unavailable');return(int)$value;
    }
    private function ticketUrl(int$id):string{return NativePortalPresentation::url('clients/ticket/'.NativeCompanyResourceId::encode('company_ticket',(string)$id));}
    private function verb():string{return strtoupper((string)$this->ci->input->method(true));}
    private function readVerb():void{if(!in_array($this->verb(),['GET','HEAD'],true))$this->methodDenied('GET, HEAD');}
    private function methodDenied(string$allow):void{$this->ci->output->set_header('Allow: '.$allow);show_error('Método não permitido.',405);}
    private function confirm():void{if($this->identities->current($this->ci->session,$this->issuer)!==$this->identity)throw new DomainException('company_portal_operation_unavailable');}
    private function deny(DomainException$error):void
    {
        $reason=$error->getMessage();if(str_contains($reason,'unavailable')){show_404();return;}
        $status=match($reason){'native_portal_csrf_denied'=>403,'company_portal_support_stale','company_portal_support_request_conflict','company_portal_support_closed'=>409,'company_portal_support_limit','company_operation_file_limit'=>429,default=>422};
        show_error($status===409?'O chamado mudou ou foi encerrado. Reabra antes de enviar.':($status===429?'O limite deste chamado foi atingido.':'Confira o assunto, a mensagem, a prioridade e o arquivo.'),$status);
    }
}
