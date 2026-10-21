<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Native company HR interface; it never delegates to hr_profile's shared tables. */
class Company_hr extends AdminController
{
    private function context():array
    {
        if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto indisponível.',503);exit;}
        require_once APPPATH.'services/CompanyHr.php';$c=CompanyRequestScope::context();$this->output->set_header('Cache-Control: no-store')->set_header('X-Content-Type-Options: nosniff')->set_header('Referrer-Policy: no-referrer');return [$c->issuerId(),$c->staffId(),new CompanyHr($this->db,db_prefix()),new IssuerResourceAccess($this->db,db_prefix())];
    }
    private function csrf():string
    {
        $csrf=$this->session->userdata('company_hr_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_hr_csrf',$csrf);}return $csrf;
    }
    private function postGuard(string $csrf):void
    {
        $submitted=$this->input->post('company_hr_csrf',false);if(!is_string($submitted)||!hash_equals($csrf,$submitted)){show_error('Recarregue o formulário antes de salvar.',403);exit;}
        if((int)($this->input->server('CONTENT_LENGTH')??0)>3000000)throw new DomainException('issuer_hr_invalid');
    }
    private function upload(string $field,int $limit):array
    {
        $f=$_FILES[$field]??null;if(!is_array($f)||($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_string($f['tmp_name']??null)||!is_uploaded_file($f['tmp_name'])||!is_string($f['name']??null)||(int)($f['size']??0)>$limit)throw new DomainException('issuer_hr_invalid');$bytes=file_get_contents($f['tmp_name'],false,null,0,$limit+1);if(!is_string($bytes)||strlen($bytes)>$limit)throw new DomainException('issuer_hr_invalid');return [$f['name'],$bytes];
    }
    private function key():string{$k=$this->input->post('request_key',false);if(!is_string($k)||!preg_match('/^[a-f0-9]{64}$/D',$k))throw new DomainException('issuer_hr_invalid');return $k;}
    private function fail(Throwable $e):string
    {
        if($e instanceof DomainException){$code=$e->getMessage();$this->output->set_status_header(str_ends_with($code,'denied')?403:(str_ends_with($code,'stale')||str_ends_with($code,'conflict')?409:422));return match($code){'issuer_hr_cycle'=>'A hierarquia não pode conter ciclos.','issuer_hr_reference_in_use'=>'Revise os vínculos ativos antes de encerrar esta referência.','issuer_hr_contract_overlap'=>'Há outro contrato ativo no mesmo intervalo.','issuer_hr_stale'=>'O registro mudou. Reabra a página e confira a revisão.','issuer_hr_limit'=>'O limite desta operação foi atingido.','issuer_hr_ambiguous_deadline'=>'A hora local é inexistente ou ambígua nesse fuso. Escolha outro horário.',default=>'Confira as permissões, o vínculo da empresa e os campos. Nenhuma alteração desta tentativa foi confirmada.'};}
        log_message('error','Company HR operation unconfirmed; sensitive fields, tokens and documents omitted.');$this->output->set_status_header(503);return 'Não foi possível confirmar a tentativa. Reabra o registro antes de repetir.';
    }
    public function index()
    {
        [$i,$a,$s,$acl]=$this->context();if(!$acl->can($i,$a,'hr','view')){show_error('RH indisponível nesta empresa.',403);return;}$verb=$this->input->method(true);if(!in_array($verb,['GET','HEAD','POST'],true)){show_error('Método não permitido.',405);return;}
        $csrf=$this->csrf();$section=$this->input->get('section',false)??'people';if(!in_array($section,['people','structure','courses','articles','reports','import'],true)){show_error('Seção inválida.',422);return;}$error=null;$issued=null;$preview=null;$employeeId=$this->input->get('employee',false);
        try{
            $employeeId=$employeeId===null?null:IssuerPolicy::id($employeeId);
            if($verb==='POST'){
                $this->postGuard($csrf);$key=$this->key();$action=$this->input->post('action',false);$id=$this->input->post('entity_id',false);$id=$id===null||$id===''?null:IssuerPolicy::id($id);$revision=$this->input->post('revision',false);$input=$this->input->post('input',false)??[];if(!is_array($input))throw new DomainException('issuer_hr_invalid');
                $redirect=true;
                if($action==='structure_save'){$kind=$this->input->post('kind',false);if(!is_string($kind))throw new DomainException('issuer_hr_invalid');$s->saveStructure($i,$a,$kind,$id,$revision,$input,$key);$section='structure';}
                elseif($action==='employee_save'){$employeeId=$s->saveEmployee($i,$a,$id,$revision,$input,$key);$section='people';}
                elseif($action==='profile_save'){$employeeId=IssuerPolicy::id($this->input->post('employee_id',false));$s->saveProfile($i,$a,$employeeId,$revision,$input,$key);}
                elseif($action==='record_save'){$employeeId=IssuerPolicy::id($this->input->post('employee_id',false));$kind=$this->input->post('kind',false);if(!is_string($kind))throw new DomainException('issuer_hr_invalid');$s->saveRecord($i,$a,$employeeId,$kind,$id,$revision,$input,$key);}
                elseif($action==='record_state'){$employeeId=IssuerPolicy::id($this->input->post('employee_id',false));$state=$this->input->post('state',false);if(!is_string($state))throw new DomainException('issuer_hr_invalid');$s->recordState($i,$a,IssuerPolicy::id($id),$revision,$state,$key);}
                elseif($action==='contract_save'){$employeeId=IssuerPolicy::id($input['employee_id']??null);$s->saveContract($i,$a,$id,$revision,$input,$key);}
                elseif($action==='contract_state'){$employeeId=IssuerPolicy::id($this->input->post('employee_id',false));$state=$this->input->post('state',false);if(!is_string($state))throw new DomainException('issuer_hr_invalid');$s->contractState($i,$a,IssuerPolicy::id($id),$revision,$state,$key);}
                elseif($action==='course_save'){
                    $questions=$input['questions']??null;if(!is_array($questions)||count($questions)>30)throw new DomainException('issuer_hr_invalid');$out=[];foreach($questions as $q){if(!is_array($q)||!is_string($q['question']??null)||!is_string($q['choices_text']??null))throw new DomainException('issuer_hr_invalid');if(trim($q['question'])===''&&trim($q['choices_text'])==='')continue;$answer=$q['correct']??null;if(!is_string($answer)||!preg_match('/^[1-8]$/D',$answer))throw new DomainException('issuer_hr_invalid');$out[]=['question'=>$q['question'],'choices'=>preg_split('/\R/',trim($q['choices_text'])),'correct'=>(int)$answer-1];}$input['questions']=$out;$s->saveCourse($i,$a,$id,$revision,$input,$key);$section='courses';
                }elseif($action==='course_assign'){$employeeId=IssuerPolicy::id($input['employee_id']??null);$s->assignCourse($i,$a,$employeeId,IssuerPolicy::id($input['course_id']??null),(string)($input['due_local']??''),(string)($input['timezone']??''),($input['notify_opt_in']??'0')==='1',$key);}
                elseif($action==='assignment_cancel'){$employeeId=IssuerPolicy::id($this->input->post('employee_id',false));$s->cancelAssignment($i,$a,IssuerPolicy::id($id),$revision,$key);}
                elseif($action==='access_issue'){$employeeId=IssuerPolicy::id($this->input->post('employee_id',false));$issued=$s->createAccess($i,$a,(string)($input['purpose']??''),IssuerPolicy::id($input['object_id']??null),(int)($input['hours']??0),$key);$redirect=false;}
                elseif($action==='access_revoke'){$employeeId=IssuerPolicy::id($this->input->post('employee_id',false));$s->revokeAccess($i,$a,IssuerPolicy::id($id),$revision,$key);}
                elseif($action==='file_upload'){[$name,$bytes]=$this->upload('document',2097152);$s->addFile($i,$a,(string)($input['purpose']??''),IssuerPolicy::id($input['object_id']??null),$revision,$name,$bytes,$key);}
                elseif($action==='file_hide'){$s->hideFile($i,$a,(string)($input['purpose']??''),IssuerPolicy::id($input['object_id']??null),IssuerPolicy::id($id),$revision,$key);}
                elseif($action==='article_save'){$s->saveArticle($i,$a,$id,$revision,$input,$key);$section='articles';}
                elseif($action==='import_review'){[$name,$csv]=$this->upload('csv',262144);$preview=$s->reviewImport($i,$a,$csv)+['content_base64'=>base64_encode($csv)];$section='import';$redirect=false;}
                elseif($action==='import_commit'){$encoded=$input['content_base64']??null;$hash=$input['review_hash']??null;if(!is_string($encoded)||strlen($encoded)>350000||!is_string($hash)||($csv=base64_decode($encoded,true))===false||base64_encode($csv)!==$encoded)throw new DomainException('issuer_hr_invalid');$s->importEmployees($i,$a,$csv,$hash,$key);$section='people';}
                else throw new DomainException('issuer_hr_invalid');
                CompanyRequestScope::invalidateCaches($this);if($redirect){$query=['section'=>$section];if($employeeId!==null)$query['employee']=$employeeId;if(in_array($action,['file_upload','file_hide'],true)){$query['files_purpose']=$input['purpose'];$query['files_object']=$input['object_id'];}redirect(CompanyRequestScope::adminUrl($this,'company_hr?'.http_build_query($query)),'location',303);return;}
            }
        }catch(Throwable $e){$error=$this->fail($e);if(!is_int($employeeId))$employeeId=null;}
        $data=['issuer'=>$i,'actor'=>$a,'section'=>$section,'error'=>$error,'issued'=>$issued,'preview'=>$preview,'company_hr_csrf'=>$csrf,'request_key'=>bin2hex(random_bytes(32)),'employee'=>null,'profile'=>null,'records'=>[],'contracts'=>[],'assignments'=>[],'accesses'=>[],'courses'=>[],'articles'=>[],'report'=>null,'files'=>[],'file_context'=>null,'staff_choices'=>[]];
        $can=static fn($feature,$cap)=>$acl->can($i,$a,$feature,$cap);$data['can']=$can;
        try{
            $structure=$s->structureSnapshot($i,$a);$data['structure']=$structure['structure'];$data['chart']=$structure['chart'];$data['directory']=$s->employees($i,$a,$this->input->get('after',false)??0);
            if($can('hr','create'))$data['staff_choices']=$s->staffChoices($i,$a);
            if($employeeId!==null){$data['employee']=$s->getEmployee($i,$a,$employeeId);if($can('hr_sensitive','view')){$data['profile']=$s->getProfile($i,$a,$employeeId);$data['records']=$s->records($i,$a,$employeeId);}if($can('hr_contracts','view')&&$can('hr_sensitive','view'))$data['contracts']=$s->contracts($i,$a,$employeeId);if($can('hr_training','view'))$data['assignments']=$s->assignments($i,$a,$employeeId);if($can('hr_portal','manage'))$data['accesses']=$s->accesses($i,$a,$employeeId);}
            if($can('hr_training','view'))$data['courses']=$s->courses($i,$a);if($section==='articles')$data['articles']=$s->articles($i,$a);
            if($section==='reports'){$reportName=$this->input->get('report',false)??'roster';if(!is_string($reportName))throw new DomainException('issuer_hr_invalid');$data['report']=$s->report($i,$a,$reportName);}
            $purpose=$this->input->get('files_purpose',false);$object=$this->input->get('files_object',false);if($purpose===null&&$employeeId!==null&&$can('hr_sensitive','view')){$purpose='employee';$object=$employeeId;}
            if($purpose!==null&&$can('hr_files','view')){if(!is_string($purpose))throw new DomainException('issuer_hr_invalid');$object=IssuerPolicy::id($object);$data['files']=$s->files($i,$a,$purpose,$object);$parent=match($purpose){'employee'=>$s->getEmployee($i,$a,$object),'contract'=>$s->getContract($i,$a,$object),'course'=>$this->find($data['courses'],$object),'record'=>$this->find($data['records'],$object),'article'=>$this->find($data['articles'],$object),default=>throw new DomainException('issuer_hr_invalid')};$data['file_context']=['purpose'=>$purpose,'object_id'=>$object,'revision'=>$parent['revision']];}
        }catch(Throwable $e){if($data['error']===null)$data['error']=$this->fail($e);$data+=['structure'=>['unit'=>[],'position'=>[],'workplace'=>[],'contract_type'=>[]],'directory'=>['items'=>[],'next'=>null],'chart'=>['employees'=>[],'count'=>0]];}
        $this->load->view('admin/companies/hr',$data);
    }
    private function find(array $rows,int $id):array{foreach($rows as $r)if((int)$r['id']===$id)return $r;throw new DomainException('issuer_hr_access_denied');}
    private function outputFile(array $blob):void
    {
        $this->output->set_header("Content-Security-Policy: sandbox; default-src 'none'")->set_header('Content-Disposition: attachment; filename="documento.'.($blob['mime']==='image/png'?'png':'pdf').'"; filename*=UTF-8\'\''.rawurlencode($blob['filename']))->set_header('Content-Length: '.strlen($blob['bytes']))->set_content_type($blob['mime'])->set_output($this->input->method(true)==='HEAD'?'':$blob['bytes']);
    }
    public function file()
    {
        [$i,$a,$s]=$this->context();if(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}try{$purpose=$this->input->get('purpose',false);if(!is_string($purpose))throw new DomainException('issuer_hr_invalid');$this->outputFile($s->getFile($i,$a,$purpose,$this->input->get('object',false),$this->input->get('file',false)));}catch(Throwable $e){show_error($this->fail($e),$e instanceof DomainException?403:503);}
    }
    public function pdf()
    {
        [$i,$a,$s]=$this->context();if(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}try{$id=$this->input->get('contract',false);$document=$s->contractDocument($i,$a,$id);require_once APPPATH.'services/CompanyHrPdf.php';$pdf=CompanyHrPdf::render($document);if($document!==$s->contractDocument($i,$a,$id))throw new DomainException('issuer_hr_stale');$this->outputFile(['mime'=>'application/pdf','filename'=>'contrato-rh-'.$document['number'].'.pdf','bytes'=>$pdf]);}catch(Throwable $e){show_error($this->fail($e),$e instanceof DomainException?403:503);}
    }
    public function export()
    {
        [$i,$a,$s]=$this->context();if(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}try{$name=$this->input->get('report',false);if(!is_string($name))throw new DomainException('issuer_hr_invalid');$csv=$s->reportCsv($i,$a,$name);$this->output->set_header('Content-Disposition: attachment; filename="rh.csv"')->set_content_type('text/csv','UTF-8')->set_output($this->input->method(true)==='HEAD'?'':$csv);}catch(Throwable $e){show_error($this->fail($e),$e instanceof DomainException?403:503);}
    }
    public function mine()
    {
        [$i,$a,$s]=$this->context();$verb=$this->input->method(true);if(!in_array($verb,['GET','HEAD','POST'],true)){show_error('Método não permitido.',405);return;}$csrf=$this->csrf();$error=null;$result=null;
        try{if($verb==='POST'){$this->postGuard($csrf);$key=$this->key();$action=$this->input->post('action',false);if($action==='training_submit'){$answers=$this->input->post('answers',false);if(!is_array($answers))throw new DomainException('issuer_hr_invalid');$result=$s->submitTraining($i,$a,$this->input->post('assignment_id',false),$this->input->post('revision',false),$answers,$key);}elseif($action==='notice_read')$s->readNotice($i,$a,$this->input->post('notice_id',false),$key);else throw new DomainException('issuer_hr_invalid');}$assignments=$s->assignments($i,$a,null,true);$notices=$s->myNotices($i,$a);$articles=$s->articles($i,$a,true);}
        catch(Throwable $e){$error=$this->fail($e);$assignments=[];$notices=[];$articles=[];}
        $this->load->view('admin/companies/hr_mine',['company_hr_csrf'=>$csrf,'request_key'=>bin2hex(random_bytes(32)),'error'=>$error,'result'=>$result,'assignments'=>$assignments,'notices'=>$notices,'articles'=>$articles]);
    }
    public function mine_file()
    {
        [$i,$a,$s]=$this->context();if(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}try{$this->outputFile($s->myTrainingFile($i,$a,$this->input->get('assignment',false),$this->input->get('file',false)));}catch(Throwable $e){show_error($this->fail($e),$e instanceof DomainException?403:503);}
    }
}
