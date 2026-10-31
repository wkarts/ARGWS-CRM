<?php

declare(strict_types=1);

require_once __DIR__.'/NativePortalIdentity.php';
require_once __DIR__.'/NativePortalPresentation.php';
require_once __DIR__.'/NativeCompanyResourceId.php';
require_once __DIR__.'/CompanyPortalOperations.php';
require_once __DIR__.'/CompanyContractDiscussions.php';

/** Contact-bound transport for company contracts on the original Clients route. */
final class NativePortalContractsHttp
{
    private int $issuer;
    private string $secret;
    private NativePortalIdentity $identities;
    private array $identity;
    private CompanyPortalOperations $contracts;

    public function __construct(private $ci)
    {
        if(!PortalRequestScope::sealedFor($ci)
            ||strtolower((string)$ci->router->fetch_class())!=='clients'
            ||strtolower((string)$ci->router->fetch_method())!=='contracts'){
            throw new DomainException('company_portal_contract_unavailable');
        }
        $this->issuer=PortalRequestScope::issuerId();
        $secret=$ci->config->item('encryption_key');
        if(!is_string($secret)||strlen($secret)<32)throw new RuntimeException('issuer_portal_secret_required');
        $this->secret=$secret;
        $this->identities=new NativePortalIdentity($ci->db,db_prefix(),$secret);
        $identity=$this->identities->current($ci->session,$this->issuer);
        if(!$identity){
            redirect(NativePortalPresentation::url('authentication/login'),'location',303);
            throw new DomainException('company_portal_contract_unavailable');
        }
        $this->identity=$identity;
        $portal=new CompanyPortal($ci->db,db_prefix(),$secret);
        $this->contracts=new CompanyPortalOperations($ci->db,db_prefix(),$portal);
    }

    public function index($canonical=''):void
    {
        try{
            $verb=strtoupper((string)$this->ci->input->method(true));
            if(!in_array($verb,['GET','HEAD','POST'],true)){
                $this->ci->output->set_header('Allow: GET, HEAD, POST');
                show_error('Método não permitido.',405);
                return;
            }
            $head=$verb==='HEAD';
            $id=$this->source($canonical,true);
            if($verb==='POST'){
                if($id===null)throw new DomainException('company_portal_contract_unavailable');
                $this->mutate($id);
                return;
            }
            if($id!==null){
                $record=$this->contracts->detail($this->identity['binding'],$this->issuer,'contracts',$id);
                $detail=$this->projectDetail($record);
                $page=['items'=>[],'next'=>null];
            }else{
                $after=$this->source($this->ci->input->get('after',false)??'',true)??0;
                $owned=$this->contracts->page($this->identity['binding'],$this->issuer,'contracts',$after);
                if($owned===null)throw new DomainException('company_portal_contract_unavailable');
                $page=['items'=>array_map(fn(array$row)=>$this->projectList($row),$owned['items']),'next'=>$owned['next']===null?null:$this->canonical((int)$owned['next'])];
                $detail=null;
            }
            $this->confirmIdentity();
            if($head){$this->ci->output->set_output('');return;}
            $this->ci->data([
                'title'=>_l('clients_contracts'),
                'native_portal_contracts'=>$page,
                'native_portal_contract'=>$detail,
                'native_portal_contract_request'=>bin2hex(random_bytes(32)),
                'native_portal_csrf'=>PortalRequestScope::csrfToken($this->ci),
            ]);
            $this->ci->view('contracts');
            $this->ci->layout();
        }catch(DomainException $error){
            $this->deny($error);
        }
    }

    private function mutate(int $id):void
    {
        try{
            PortalRequestScope::assertMutation($this->ci);
            $action=$this->ci->input->post('native_contract_action',false);
            if(!is_string($action))throw new DomainException('company_portal_contract_invalid');
            if($action==='comment'){
                $key=$this->ci->input->post('request_key',false);
                $body=$this->ci->input->post('discussion_body',false);
                if(!is_string($key)||!is_string($body))throw new DomainException('company_contract_discussion_invalid');
                $discussion=new CompanyContractDiscussions($this->ci->db,db_prefix(),new CompanyPortal($this->ci->db,db_prefix(),$this->secret));
                $discussion->contactAdd($this->identity['binding'],$this->issuer,$id,$this->ci->input->post('revision',false),$body,$key);
                $this->confirmIdentity();
                redirect(NativePortalPresentation::url('clients/contracts/'.$this->canonical($id)),'location',303);
                return;
            }
            if($action==='file'){
                $key=$this->ci->input->post('file_key',false);
                if(!is_string($key))throw new DomainException('company_portal_contract_unavailable');
                $file=$this->contracts->file($this->identity['binding'],$this->issuer,'contracts',$id,$key);
                $this->confirmIdentity();
                $this->ci->output->set_header('Cache-Control: private, no-store')
                    ->set_header('X-Content-Type-Options: nosniff')
                    ->set_header("Content-Security-Policy: sandbox; default-src 'none'")
                    ->set_header('Content-Disposition: attachment; filename="anexo.'.($file['mime']==='image/png'?'png':'pdf').'"; filename*=UTF-8\'\''.rawurlencode($file['filename']))
                    ->set_content_type($file['mime'])->set_output($file['content']);
                return;
            }
            if($action==='pdf'){
                $record=$this->contracts->detail($this->identity['binding'],$this->issuer,'contracts',$id);
                require_once __DIR__.'/CompanyContractsPdf.php';
                $bytes=CompanyContractsPdf::render($record);
                $this->contracts->confirmPdf($this->identity['binding'],$this->issuer,$id,$record['revision'],hash('sha256',$record['snapshot_json']),hash('sha256',$bytes));
                $this->confirmIdentity();
                $this->ci->output->set_header('Cache-Control: private, no-store')
                    ->set_header('X-Content-Type-Options: nosniff')
                    ->set_header("Content-Security-Policy: sandbox; default-src 'none'")
                    ->set_header('Content-Disposition: attachment; filename="contrato-'.(int)$record['number'].'.pdf"')
                    ->set_content_type('application/pdf')->set_output($bytes);
                return;
            }
            throw new DomainException('company_portal_contract_invalid');
        }catch(DomainException $error){
            $this->deny($error);
        }
    }

    private function source($canonical,bool $optional=false):?int
    {
        if($optional&&($canonical===null||$canonical===''||$canonical===false))return null;
        if(!is_int($canonical)&&!is_string($canonical))throw new DomainException('company_portal_contract_unavailable');
        try{$reference=NativeCompanyResourceId::decode('contract',$canonical);}
        catch(DomainException $error){throw new DomainException('company_portal_contract_unavailable',0,$error);}
        if($reference['source_kind']!=='company_contract')throw new DomainException('company_portal_contract_unavailable');
        return IssuerPolicy::id($reference['source_id']);
    }

    private function canonical(int $id):string
    {
        return NativeCompanyResourceId::encode('company_contract',(string)$id);
    }

    private function projectList(array $row):array
    {
        return [
            'canonical_id'=>$this->canonical((int)$row['id']),
            'number'=>$row['number'],'name'=>$row['name'],'status'=>$row['status'],
            'start_date'=>$row['start_date'],'due_date'=>$row['due_date'],'files'=>$row['files'],
        ];
    }

    private function projectDetail(array $row):array
    {
        return [
            'canonical_id'=>$this->canonical((int)$row['id']),
            'number'=>$row['number'],'name'=>$row['name'],'body'=>$row['body'],'status'=>$row['status'],
            'start_date'=>$row['start_date'],'due_date'=>$row['due_date'],'revision'=>$row['revision'],
            'files'=>$row['files'],'discussion'=>$row['discussion'],
        ];
    }

    private function confirmIdentity():void
    {
        if($this->identities->current($this->ci->session,$this->issuer)!==$this->identity)throw new DomainException('company_portal_contract_unavailable');
    }

    private function deny(DomainException $error):void
    {
        $reason=$error->getMessage();
        if(str_contains($reason,'unavailable')){show_404();return;}
        $status=match($reason){
            'native_portal_csrf_denied'=>403,
            'company_contract_discussion_stale','company_contract_discussion_request_conflict'=>409,
            'company_contract_discussion_limit'=>429,
            default=>422,
        };
        show_error($status===409?'O contrato ou a conversa mudou. Reabra antes de comentar.':($status===429?'O contrato atingiu o limite de 500 comentários.':'Não foi possível confirmar esta operação.'),$status);
    }
}
