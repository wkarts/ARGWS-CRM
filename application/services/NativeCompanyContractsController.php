<?php

declare(strict_types=1);

require_once __DIR__.'/CompanyContracts.php';
require_once __DIR__.'/CompanyOperationFiles.php';
require_once __DIR__.'/CompanyOperationFilesHttp.php';
require_once __DIR__.'/CompanyOperationalPortalAdmin.php';
require_once __DIR__.'/CompanyContractDiscussions.php';
require_once __DIR__.'/NativeCompanyResourceId.php';

/**
 * Company-owned contract transport for the original /admin/contracts routes.
 *
 * Keeps ownership services behind the canonical controller and accepts only
 * typed company-contract identifiers.
 */
final class NativeCompanyContractsController
{
    private int $issuer;
    private int $actor;
    private CompanyContracts $contracts;
    private CompanyOperationFiles $files;
    private IssuerResourceAccess $acl;

    public function __construct(private $ci)
    {
        $context=CompanyRequestScope::context();
        if(!$context||$context->issuerId()===null)throw new DomainException('native_contract_denied');
        $this->issuer=IssuerPolicy::id($context->issuerId());
        $this->actor=IssuerPolicy::id($context->staffId());
        $this->contracts=new CompanyContracts($ci->db,db_prefix());
        $this->files=new CompanyOperationFiles($ci->db,db_prefix());
        $this->acl=new IssuerResourceAccess($ci->db,db_prefix());
    }

    private function authorize(string $cap='view'):void
    {
        if(!$this->acl->can($this->issuer,$this->actor,'contracts',$cap))throw new DomainException('native_contract_denied');
    }

    private function canonical(int $id):string
    {
        return NativeCompanyResourceId::encode('company_contract',(string)$id);
    }

    private function source($canonical,bool $optional=false):?int
    {
        if($optional&&($canonical===null||$canonical===''))return null;
        if(!is_int($canonical)&&!is_string($canonical))throw new DomainException('native_contract_invalid');
        $reference=NativeCompanyResourceId::decode('contract',$canonical);
        if($reference['source_kind']!=='company_contract')throw new DomainException('native_contract_denied');
        return IssuerPolicy::id($reference['source_id']);
    }

    private function csrf():string
    {
        $token=$this->ci->session->userdata('native_contracts_csrf');
        if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/D',$token)){
            $token=bin2hex(random_bytes(32));
            $this->ci->session->set_userdata('native_contracts_csrf',$token);
        }
        return $token;
    }

    private function assertPost():void
    {
        CompanyRequestScope::assertNativeMutation($this->ci);
        $token=$this->ci->input->post('native_contracts_csrf',false);
        if(!is_string($token)||!hash_equals($this->csrf(),$token))throw new DomainException('native_contract_csrf_invalid');
    }

    public function index():void
    {
        $verb=strtoupper((string)$this->ci->input->method(true));
        if(!in_array($verb,['GET','HEAD'],true)){$this->methodNotAllowed('GET, HEAD');return;}
        $this->render(null,$verb==='HEAD');
    }

    public function contract($canonical=''):void
    {
        $verb=strtoupper((string)$this->ci->input->method(true));
        if(!in_array($verb,['GET','HEAD','POST'],true)){$this->methodNotAllowed('GET, HEAD, POST');return;}
        $id=$this->source($canonical,true);
        if($verb==='POST'){$this->mutate($id);return;}
        $this->render($id,$verb==='HEAD');
    }

    public function pdf($canonical):void
    {
        $verb=strtoupper((string)$this->ci->input->method(true));
        if(!in_array($verb,['GET','HEAD'],true)){$this->methodNotAllowed('GET, HEAD');return;}
        $id=$this->source($canonical);
        $contract=$this->contracts->get($this->issuer,$this->actor,$id);
        $bytes='';
        if($verb==='GET'){
            require_once __DIR__.'/CompanyContractsPdf.php';
            $bytes=CompanyContractsPdf::render($contract);
        }
        $this->contracts->confirmDownload($this->issuer,$this->actor,$id,$contract['revision']);
        $this->ci->output->set_header('Cache-Control: private, no-store');
        $this->ci->output->set_header('X-Content-Type-Options: nosniff');
        $this->ci->output->set_header('Content-Disposition: attachment; filename="contrato-'.(int)$contract['number'].'.pdf"');
        $this->ci->output->set_content_type('application/pdf');
        $this->ci->output->set_output($bytes);
    }

    public function file($canonical,string $key):void
    {
        $verb=strtoupper((string)$this->ci->input->method(true));
        if(!in_array($verb,['GET','HEAD'],true)){$this->methodNotAllowed('GET, HEAD');return;}
        $id=$this->source($canonical);
        if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_operation_file_invalid');
        CompanyOperationFilesHttp::respond($this->ci,$this->files->file($this->issuer,$this->actor,'contract',$id,$key));
    }

    private function mutate(?int $routeId):void
    {
        $this->assertPost();
        $action=$this->ci->input->post('action',false);
        $key=$this->ci->input->post('request_key',false);
        if(!is_string($action)||!is_string($key)||!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('native_contract_invalid');
        $posted=$this->source($this->ci->input->post('contract_id',false),true);
        if($routeId!==null&&$posted!==null&&$routeId!==$posted)throw new DomainException('native_contract_mismatch');
        $id=$routeId??$posted;
        $revision=$this->ci->input->post('revision',false);
        if(CompanyOperationalPortalAdmin::handle($this->ci,$this->issuer,$this->actor,'contracts',$action,$id,$revision,$key)){
            // handled by the owned publication service
        }elseif($action==='discussion_add'){
            $body=$this->ci->input->post('discussion_body',false);if(!is_string($body)||$id===null)throw new DomainException('company_contract_discussion_invalid');
            (new CompanyContractDiscussions($this->ci->db,db_prefix()))->staffAdd($this->issuer,$this->actor,$id,$revision,$body,$key);
        }elseif($action==='discussion_visibility'){
            $visible=$this->ci->input->post('visible',false)??'0';if(!in_array($visible,['0','1'],true)||$id===null)throw new DomainException('company_contract_discussion_invalid');
            (new CompanyContractDiscussions($this->ci->db,db_prefix()))->setVisible($this->issuer,$this->actor,$id,$revision,$this->ci->input->post('comment_id',false),$visible==='1',$key);
        }elseif($action==='file_upload'){
            if($id===null)throw new DomainException('company_operation_file_invalid');[$name,$bytes]=CompanyOperationFilesHttp::upload();
            $id=$this->files->upload($this->issuer,$this->actor,'contract',$id,$revision,$key,$name,$bytes);
        }elseif($action==='file_hide'){
            $file=$this->ci->input->post('file_key',false);if(!is_string($file)||$id===null)throw new DomainException('company_operation_file_invalid');
            $id=$this->files->hide($this->issuer,$this->actor,'contract',$id,$revision,$file,$key);
        }elseif(in_array($action,['create','update'],true)){
            $input=$this->ci->input->post('contract',false);if(!is_array($input))throw new DomainException('native_contract_invalid');
            if($action==='create')$id=$this->contracts->create($this->issuer,$this->actor,$input,$key);
            else {if($id===null)throw new DomainException('native_contract_invalid');$id=$this->contracts->update($this->issuer,$this->actor,$id,$revision,$input,$key);}
        }elseif(in_array($action,['activate','cancel','copy'],true)){
            if($id===null)throw new DomainException('native_contract_invalid');
            $id=$this->contracts->transition($this->issuer,$this->actor,$id,$revision,$action,$key);
        }else throw new DomainException('native_contract_invalid');
        CompanyRequestScope::invalidateCaches($this->ci);
        redirect(CompanyRequestScope::adminUrl($this->ci,'contracts/contract/'.$this->canonical($id)),'location',303);
    }

    private function render(?int $id,bool $head):void
    {
        $this->authorize('view');
        $this->ci->output->set_header('Cache-Control: private, no-store');
        $contract=null;$discussion=null;$operationFiles=[];$portalControls=null;
        if($id!==null){
            $contract=$this->contracts->get($this->issuer,$this->actor,$id);
            $discussion=(new CompanyContractDiscussions($this->ci->db,db_prefix()))->staffPage($this->issuer,$this->actor,$id);
            $operationFiles=$this->files->page($this->issuer,$this->actor,'contract',$id);
            $portalControls=CompanyOperationalPortalAdmin::controls($this->ci,$this->issuer,$this->actor,'contracts',$contract);
        }
        $after=$this->ci->input->get('after',false)??0;$search=$this->ci->input->get('search',false)??'';$clientSearch=$this->ci->input->get('client_search',false)??'';
        if(!is_string($search)||!is_string($clientSearch))throw new DomainException('native_contract_invalid');
        $directory=$this->contracts->page($this->issuer,$this->actor,$after,$search);$clients=[];
        if($this->acl->can($this->issuer,$this->actor,'customers','view')){
            require_once __DIR__.'/CompanyClients.php';$clients=(new CompanyClients($this->ci->db,db_prefix()))->page($this->issuer,$this->actor,0,100,$clientSearch)['items'];
        }
        if($head){$this->ci->output->set_output('');return;}
        $this->ci->load->view('admin/contracts/company_native',[
            'contract'=>$contract,'contract_id'=>$id===null?null:$this->canonical($id),'discussion'=>$discussion,
            'operation_files'=>$operationFiles,'portal_controls'=>$portalControls,'directory'=>$directory,'clients'=>$clients,
            'native_contracts_csrf'=>$this->csrf(),'request_key'=>bin2hex(random_bytes(32)),
            'can_create'=>$this->acl->can($this->issuer,$this->actor,'contracts','create'),
            'can_edit'=>$this->acl->can($this->issuer,$this->actor,'contracts','edit'),
            'title'=>_l('contracts'),'bodyclass'=>'contracts-page',
        ]);
    }

    private function methodNotAllowed(string $allow):void
    {
        $this->ci->output->set_header('Allow: '.$allow);show_error('Método não permitido.',405);
    }
}
