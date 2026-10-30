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
        if(!CompanyRequestScope::sealedFor($ci))throw new DomainException('native_contract_denied');
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

    public function types():void
    {
        $verb=strtoupper($this->ci->input->method(true));if(!in_array($verb,['GET','HEAD'],true)){$this->methodNotAllowed('GET, HEAD');return;}
        $this->authorize();$rows=(new NativeCompanyContractTypes($this->ci->db,db_prefix()))->listing($this->issuer,$this->actor,false);
        if($verb==='HEAD'){$this->ci->output->set_output('');return;}
        $this->ci->load->view('admin/contracts/manage_types',['title'=>_l('contract_types'),'native_contract_types'=>$rows,'can_manage'=>$this->acl->administrative($this->issuer,$this->actor),'native_contracts_csrf'=>$this->csrf()]);
    }
    public function type($canonical='',bool $remove=false):void
    {
        if(strtoupper($this->ci->input->method(true))!=='POST'){$this->methodNotAllowed('POST');return;}$this->assertPost();
        $data=$this->ci->input->post(null,false);if(!is_array($data))throw new DomainException('native_contract_type_invalid');unset($data['_company_context'],$data['_native_company_csrf'],$data['native_contracts_csrf']);if(isset($this->ci->security))unset($data[$this->ci->security->get_csrf_token_name()]);
        if(array_diff(array_keys($data),['id','name','revision','request_key'])||!is_string($data['request_key']??null))throw new DomainException('native_contract_type_invalid');
        $posted=$data['id']??'';if($canonical!==''&&$posted!==''&&(string)$canonical!==(string)$posted)throw new DomainException('native_contract_mismatch');$id=$canonical!==''?(string)$canonical:($posted!==''?(string)$posted:null);
        $revision=$id===null?null:IssuerPolicy::id($data['revision']??null);$name=$remove?null:($data['name']??null);if(!$remove&&!is_string($name))throw new DomainException('native_contract_type_invalid');
        $row=(new NativeCompanyContractTypes($this->ci->db,db_prefix()))->save($this->issuer,$this->actor,$id,$revision,$name,$data['request_key']);
        CompanyRequestScope::invalidateCaches($this->ci);
        if(method_exists($this->ci->input,'is_ajax_request')&&$this->ci->input->is_ajax_request()){$this->ci->output->set_content_type('application/json')->set_output(json_encode(['success'=>true]+$row,JSON_THROW_ON_ERROR));return;}
        redirect(CompanyRequestScope::adminUrl($this->ci,'contracts/types?source=company'),'location',303);
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

    public function table($client=''):void
    {
        CompanyRequestScope::assertNativeMutation($this->ci);$this->authorize();$post=$this->ci->input->post(null,false)?:[];
        $start=$post['start']??0;$length=$post['length']??25;$search=$post['search']['value']??'';
        if(!preg_match('/^(0|[1-9][0-9]{0,5})$/D',(string)$start)||!preg_match('/^[1-9][0-9]{0,2}$/D',(string)$length)||(int)$length>100||!is_string($search)||strlen($search)>191)throw new DomainException('native_contract_invalid');
        $p=db_prefix();$where="c.issuer_id=? AND c.status<>'deleted'";$args=[$this->issuer];if($client!==''){$where.=' AND c.client_id=?';$args[]=IssuerPolicy::id($client);}
        $base=$where;$baseArgs=$args;if($search!==''){$where.=" AND c.name LIKE ? ESCAPE '!'";$args[]='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';}
        $total=(int)IssuerStorage::query($this->ci->db,"SELECT COUNT(*) n FROM {$p}company_contracts c WHERE $base",$baseArgs)->row()->n;$filtered=(int)IssuerStorage::query($this->ci->db,"SELECT COUNT(*) n FROM {$p}company_contracts c WHERE $where",$args)->row()->n;
        $rows=IssuerStorage::query($this->ci->db,"SELECT c.id,c.name,c.start_date,c.due_date,c.status,u.company FROM {$p}company_contracts c JOIN {$p}clients u ON u.userid=c.client_id WHERE $where ORDER BY c.id LIMIT ".(int)$length.' OFFSET '.(int)$start,$args)->result_array();
        $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$data=[];foreach($rows as$row){$id=$this->canonical((int)$row['id']);$data[]=[$id,'<a href="'.$e(CompanyRequestScope::adminUrl($this->ci,'contracts/contract/'.$id)).'">'.$e($row['name']).'</a>',$e($row['company']),'','',$e($row['start_date']),$e($row['due_date']),'',$e($row['status'])];}$this->authorize();
        $this->ci->output->set_content_type('application/json')->set_output(json_encode(['draw'=>(int)($post['draw']??0),'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$data,'iTotalRecords'=>$total,'iTotalDisplayRecords'=>$filtered,'aaData'=>$data],JSON_THROW_ON_ERROR));
    }
    public function notes($canonical):void
    {
        $verb=strtoupper($this->ci->input->method(true));if(!in_array($verb,['GET','HEAD'],true)){$this->methodNotAllowed('GET, HEAD');return;}
        $id=$this->source($canonical);$this->contracts->get($this->issuer,$this->actor,$id);require_once __DIR__.'/NativeCompanyAuxiliaryUi.php';
        $notes=(new NativeCompanyAuxiliaryUi($this->ci->db,db_prefix(),$this->issuer,$this->actor,$this->ci))->notes('contract',$this->canonical($id));
        $html=$this->ci->load->view('admin/includes/sales_notes_template',['notes'=>$notes],true);$this->ci->output->set_content_type('text/html')->set_output($verb==='HEAD'?'':$html);
    }
    public function comments($canonical):void
    {
        $verb=strtoupper($this->ci->input->method(true));if(!in_array($verb,['GET','HEAD'],true)){$this->methodNotAllowed('GET, HEAD');return;}
        $id=$this->source($canonical);$page=(new CompanyContractDiscussions($this->ci->db,db_prefix()))->staffPage($this->issuer,$this->actor,$id);
        $html=$this->ci->load->view('admin/contracts/native_comments',['comments'=>$page['items']],true);$this->ci->output->set_content_type('text/html')->set_output($verb==='HEAD'?'':$html);
    }
    /** Original action URLs require the same sealed POST, dedicated CSRF and optimistic revision as the form. */
    public function action(string $action,$canonical=null,$comment=null):void
    {
        if(strtoupper($this->ci->input->method(true))!=='POST'){$this->methodNotAllowed('POST');return;}$this->assertPost();
        $allowed=['action','contract_id','contractid','revision','request_key','native_contracts_csrf','_native_company_csrf','_company_context'];
        if(isset($this->ci->security))$allowed[]=$this->ci->security->get_csrf_token_name();
        $allowed=array_merge($allowed,match($action){'add_external_attachment'=>['files','external'],'add_note'=>['description'],'renew'=>['new_start_date','new_end_date','new_value','currency'],'send_to_email'=>['contact_id','cc','attach_pdf'],'save_contract_data'=>['content'],'delete_renewal'=>['renewal_revision'],'delete_contract_attachment'=>['file_key'],'add_comment','edit_comment'=>['content','discussion_body'],default=>[]});
        if(array_diff(array_keys($this->ci->input->post(null,false)?:[]),$allowed))throw new DomainException('native_contract_invalid');
        $posted=$this->ci->input->post('contract_id',false)??$this->ci->input->post('contractid',false);$id=$this->source($canonical??$posted);if($posted!==null&&$this->source($posted)!==$id)throw new DomainException('native_contract_mismatch');
        $revision=$this->ci->input->post('revision',false);$key=$this->ci->input->post('request_key',false);if(!is_string($key)||!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('native_contract_invalid');
        if($action==='add_external_attachment'){if(!is_array($this->ci->input->post('files',false))||!is_string($this->ci->input->post('external',false)))throw new DomainException('native_contract_invalid');$this->contracts->attachExternal($this->issuer,$this->actor,$id,IssuerPolicy::id($revision),$this->ci->input->post('external',false),$this->ci->input->post('files',false),$key);}
        elseif($action==='add_note'){require_once __DIR__.'/NativeCompanyAuxiliaryUi.php';$description=$this->ci->input->post('description',false);if(!is_string($description))throw new DomainException('native_contract_invalid');(new NativeCompanyAuxiliaryUi($this->ci->db,db_prefix(),$this->issuer,$this->actor,$this->ci))->saveNote('contract',$this->canonical($id),['description'=>$description],null,null,$key,IssuerPolicy::id($revision));}
        elseif(in_array($action,['mark_as_signed','unmark_as_signed','clear_signature'],true))$this->contracts->markSignature($this->issuer,$this->actor,$id,$revision,$action,$key);
        elseif($action==='save_contract_data'){
            $content=$this->ci->input->post('content',false);if(!is_string($content))throw new DomainException('native_contract_invalid');$this->contracts->saveContent($this->issuer,$this->actor,$id,$revision,$content,$key);
        }elseif($action==='delete_renewal')$this->contracts->cancelRenewal($this->issuer,$this->actor,$id,$revision,$comment,$this->ci->input->post('renewal_revision',false),$key);
        elseif($action==='add_contract_attachment'){[$name,$bytes]=CompanyOperationFilesHttp::upload();$this->files->upload($this->issuer,$this->actor,'contract',$id,$revision,$key,$name,$bytes);}
        elseif($action==='delete_contract_attachment'){
            $file=$this->ci->input->post('file_key',false);if(!is_string($comment)||!preg_match('/^[a-f0-9]{64}$/D',$comment)||($file!==null&&$file!==$comment))throw new DomainException('native_contract_invalid');$external=array_column($this->contracts->externalFiles($this->issuer,$this->actor,$id,false),'file_key');if(in_array($comment,$external,true))$this->contracts->hideExternal($this->issuer,$this->actor,$id,IssuerPolicy::id($revision),$comment,$key);else $this->files->hide($this->issuer,$this->actor,'contract',$id,$revision,$comment,$key);
        }elseif($action==='copy')$id=$this->contracts->transition($this->issuer,$this->actor,$id,$revision,'copy',$key);
        elseif($action==='delete')$this->contracts->remove($this->issuer,$this->actor,$id,$revision,$key);
        elseif($action==='renew'){
            $start=$this->ci->input->post('new_start_date',false);$end=$this->ci->input->post('new_end_date',false);if(!is_string($start)||!is_string($end))throw new DomainException('native_contract_invalid');
            $newValue=$this->ci->input->post('new_value',false);$currency=$this->ci->input->post('currency',false);if(($newValue!==null&&!is_string($newValue))||($currency!==null&&!is_string($currency)))throw new DomainException('native_contract_invalid');
            $id=$this->contracts->renew($this->issuer,$this->actor,$id,$revision,$start,$end,$key,$newValue,$currency);
        }elseif(in_array($action,['add_comment','edit_comment','remove_comment'],true)){
            $discussion=new CompanyContractDiscussions($this->ci->db,db_prefix());$body=$this->ci->input->post('content',false)??$this->ci->input->post('discussion_body',false);
            if($action==='add_comment'){if(!is_string($body))throw new DomainException('native_contract_invalid');$discussion->staffAdd($this->issuer,$this->actor,$id,$revision,$body,$key);}
            else{$comment=IssuerPolicy::id($comment);if($action==='edit_comment'&&!is_string($body))throw new DomainException('native_contract_invalid');$discussion->edit($this->issuer,$this->actor,$id,$revision,$comment,$action==='remove_comment'?null:$body,$key);}
            CompanyRequestScope::invalidateCaches($this->ci);if(method_exists($this->ci->input,'is_ajax_request')&&!$this->ci->input->is_ajax_request()){redirect(CompanyRequestScope::adminUrl($this->ci,'contracts/contract/'.$this->canonical($id)),'location',303);return;}$this->ci->output->set_content_type('application/json')->set_output(json_encode(['success'=>true,'revision'=>(int)$this->contracts->get($this->issuer,$this->actor,$id)['revision']],JSON_THROW_ON_ERROR));return;
        }elseif($action==='send_to_email'){
            require_once __DIR__.'/NativeCompanyContractMail.php';$this->ci->load->library('encryption');$cc=$this->ci->input->post('cc',false)??'';$attach=$this->ci->input->post('attach_pdf',false)??'0';if(!is_string($cc)||!in_array($attach,['0','1'],true))throw new DomainException('native_contract_invalid');
            (new NativeCompanyContractMail($this->ci->db,db_prefix()))->queue($this->issuer,$this->actor,$id,IssuerPolicy::id($this->ci->input->post('contact_id',false)),IssuerPolicy::id($revision),$key,base_url(),fn($v)=>$this->ci->encryption->decrypt($v),['cc'=>$cc,'attach_pdf'=>$attach==='1']);
        }else throw new DomainException('native_contract_invalid');
        CompanyRequestScope::invalidateCaches($this->ci);redirect(CompanyRequestScope::adminUrl($this->ci,$action==='delete'?'contracts':'contracts/contract/'.$this->canonical($id)),'location',303);
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
        $mailRecipients=[];if($contract&&($portalControls['visible']??false)&&$contract['status']==='active'&&$this->acl->can($this->issuer,$this->actor,'contracts','edit')&&$this->acl->can($this->issuer,$this->actor,'customers','view')){require_once __DIR__.'/NativeCompanyContractMail.php';$mailRecipients=(new NativeCompanyContractMail($this->ci->db,db_prefix()))->recipients($this->issuer,$this->actor,$id);}
        $history=$id===null?[]:$this->contracts->history($this->issuer,$this->actor,$id);$pendingRenewals=[];
        if($contract&&$this->acl->can($this->issuer,$this->actor,'contracts','delete'))foreach($history as$event){
            if($event['event']!=='native_contract_renewal_linked')continue;$metadata=json_decode($event['metadata_json'],true,32,JSON_THROW_ON_ERROR);$successor=$this->contracts->get($this->issuer,$this->actor,IssuerPolicy::id($metadata['successor_id']??null));
            if($successor['status']==='draft'&&$successor['number']===null)$pendingRenewals[]=['link_id'=>(int)$event['id'],'successor'=>$successor];
        }
        require_once __DIR__.'/NativeCompanyAuxiliaryUi.php';$notes=$id===null?[]:(new NativeCompanyAuxiliaryUi($this->ci->db,db_prefix(),$this->issuer,$this->actor,$this->ci))->notes('contract',$this->canonical($id));
        $this->ci->load->view('admin/contracts/native',[
            'contract_types'=>(new NativeCompanyContractTypes($this->ci->db,db_prefix()))->listing($this->issuer,$this->actor),
            'contract_type'=>$contract?NativeCompanyContractTypes::stored(json_decode($contract['snapshot_json'],true,32,JSON_THROW_ON_ERROR)):[],
            'notes'=>$notes,'external_files'=>$contract&&$contract['status']!=='deleted'?$this->contracts->externalFiles($this->issuer,$this->actor,$id):[],
            'signature_state'=>$contract?$this->contracts->signatureState($this->issuer,$this->actor,$id):null,
            'contract_value'=>$contract?CompanyContractValue::stored(json_decode($contract['snapshot_json'],true,32,JSON_THROW_ON_ERROR)):[],
            'contract_currencies'=>$this->ci->db->table_exists(db_prefix().'currencies')?IssuerStorage::query($this->ci->db,'SELECT name FROM '.db_prefix().'currencies ORDER BY name')->result_array():[],
            'mail_recipients'=>$mailRecipients,'comment_actor'=>$this->actor,'comment_admin'=>$this->acl->administrative($this->issuer,$this->actor),
            'pending_renewals'=>$pendingRenewals,'history'=>$history,'can_delete'=>$this->acl->can($this->issuer,$this->actor,'contracts','delete'),'contract'=>$contract,'contract_id'=>$id===null?null:$this->canonical($id),'discussion'=>$discussion,
            'operation_files'=>$operationFiles,'portal_controls'=>$portalControls,'directory'=>$directory,'clients'=>$clients,
            'native_contracts_csrf'=>$this->csrf(),'request_key'=>bin2hex(random_bytes(32)),
            'can_create'=>$this->acl->can($this->issuer,$this->actor,'contracts','create'),
            'can_edit'=>($contract['status']??'')!=='deleted'&&$this->acl->can($this->issuer,$this->actor,'contracts','edit'),
            'title'=>_l('contracts'),'bodyclass'=>'contracts-page',
        ]);
    }

    private function methodNotAllowed(string $allow):void
    {
        $this->ci->output->set_header('Allow: '.$allow);show_error('Método não permitido.',405);
    }
}
