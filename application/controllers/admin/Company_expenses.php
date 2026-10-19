<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Sealed expenses UI; legacy expense controllers and hooks are never loaded. */
class Company_expenses extends AdminController
{
    public function index()
    {
        if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto de empresa indisponível.',503);return;}
        require_once APPPATH.'services/CompanyExpenses.php';require_once APPPATH.'services/CompanyExpenseFiles.php';require_once APPPATH.'services/CompanyClients.php';
        $context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();$acl=new IssuerResourceAccess($this->db,db_prefix());$s=new CompanyExpenses($this->db,db_prefix());
        $this->output->set_header('Cache-Control: no-store');if(!$acl->can($issuer,$actor,'expenses','view')){show_error('Sem permissão para consultar despesas desta empresa.',403);return;}
        $csrf=$this->session->userdata('company_expenses_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_expenses_csrf',$csrf);}
        $error=null;$detail=null;$page=['items'=>[],'next'=>null];$categories=[];$clients=[];$projects=[];$files=[];$search='';
        try{
            if($this->input->method(true)==='POST'){
                $posted=$this->input->post('company_expenses_csrf',false);if(!is_string($posted)||!hash_equals($csrf,$posted)){show_error('Recarregue o formulário antes de salvar.',403);return;}
                $action=$this->input->post('action',false);$key=$this->input->post('request_key',false);if(!is_string($key))throw new DomainException('company_expense_invalid');$id=null;
                if($action==='category'){$name=$this->input->post('category_name',false);if(!is_string($name))throw new DomainException('company_expense_invalid');$category=$this->input->post('category_id',false);$revision=$this->input->post('revision',false);$s->saveCategory($issuer,$actor,$name,$key,$category?:null,is_string($revision)?$revision:null,$this->input->post('category_active',false)==='1');}
                elseif(in_array($action,['file_upload','file_hide'],true)){
                    $id=$this->input->post('expense_id',false);$fileService=new CompanyExpenseFiles($this->db,db_prefix());
                    if($action==='file_upload'){$upload=$_FILES['expense_file']??null;if(!is_array($upload)||($upload['error']??null)!==UPLOAD_ERR_OK||!is_int($upload['size']??null)||$upload['size']>2097152||!is_string($upload['name']??null)||!is_string($upload['tmp_name']??null)||!is_uploaded_file($upload['tmp_name']))throw new DomainException('company_expense_file_invalid');$bytes=file_get_contents($upload['tmp_name'],false,null,0,2097153);if(!is_string($bytes))throw new DomainException('company_expense_file_invalid');$fileService->upload($issuer,$actor,$id,$key,$upload['name'],$bytes);}
                    else{$fileKey=$this->input->post('file_key',false);$hash=$this->input->post('content_hash',false);if(!is_string($fileKey)||!is_string($hash))throw new DomainException('company_expense_file_invalid');$fileService->hide($issuer,$actor,$id,$fileKey,$hash);}
                }
                elseif($action==='create'){$input=$this->input->post('expense',false);if(!is_array($input))throw new DomainException('company_expense_invalid');$id=$s->create($issuer,$actor,$input,$key);}
                elseif(in_array($action,['update','cancel'],true)){$id=$this->input->post('expense_id',false);$revision=$this->input->post('revision',false);if(!is_string($revision))throw new DomainException('company_expense_invalid');$input=$action==='update'?$this->input->post('expense',false):null;if($action==='update'&&!is_array($input))throw new DomainException('company_expense_invalid');$s->change($issuer,$actor,$id,$revision,$key,$input);}
                else throw new DomainException('company_expense_invalid');
                CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'company_expenses'.($id?'?expense='.(int)$id:'')),'location',303);return;
            }elseif(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
            $id=$this->input->get('expense',false);if($id!==null){$detail=$s->get($issuer,$actor,$id);if(!$detail){show_error('Despesa indisponível nesta empresa.',404);return;}$fileKey=$this->input->get('file',false);if($fileKey!==null){if(!is_string($fileKey))throw new DomainException('company_expense_file_invalid');$file=(new CompanyExpenseFiles($this->db,db_prefix()))->download($issuer,$actor,$id,$fileKey);$this->output->set_header('X-Content-Type-Options: nosniff');$this->output->set_header("Content-Security-Policy: sandbox; default-src 'none'");$this->output->set_header('Content-Disposition: attachment; filename="comprovante.'.($file['mime']==='image/png'?'png':'pdf').'"; filename*=UTF-8\'\''.rawurlencode($file['filename']));$this->output->set_content_type($file['mime'])->set_output($file['content']);return;}$files=(new CompanyExpenseFiles($this->db,db_prefix()))->page($issuer,$actor,$id);}
            $search=$this->input->get('search',false)??'';if(!is_string($search))throw new DomainException('company_expense_invalid');$page=$s->page($issuer,$actor,$this->input->get('after',false)??0,50,$search);$categories=$s->categories($issuer,$actor);$projects=$s->projectOptions($issuer,$actor);
            if($acl->can($issuer,$actor,'customers','view'))$clients=(new CompanyClients($this->db,db_prefix()))->page($issuer,$actor,0,100)['items'];
        }catch(DomainException $e){$reason=$e->getMessage();if($reason==='company_expense_denied'){show_error('Sem permissão para esta ação na despesa.',403);return;}$this->output->set_status_header(in_array($reason,['company_expense_stale','company_expense_conflict'],true)?409:422);$error=match($reason){'company_expense_stale'=>'Este cadastro mudou. Reabra antes de salvar.','company_expense_conflict'=>'Este envio já foi utilizado com outros dados. Reabra o formulário.','company_expense_immutable'=>'A despesa cancelada e a moeda original são preservadas.','company_expense_provider_source_immutable'=>'Esta despesa pertence à conciliação de uma tarifa do Asaas e não pode ser alterada ou cancelada pelo fluxo manual.','company_expense_category_conflict'=>'Já existe uma categoria com este nome na empresa.','company_expense_reference_denied'=>'A categoria, o projeto ou o cliente não está autorizado nesta empresa.','company_expense_file_invalid'=>'Envie PDF de até 2 MiB ou PNG/JPEG de até 512 KiB e 1600 pixels. Use nome sem caminhos.','company_expense_file_limit'=>'Limite por despesa: 20 uploads e 20 MiB no histórico, incluindo ocultos.','company_expense_file_conflict'=>'Este comprovante ou envio mudou. Reabra a despesa.',default=>'Não foi possível salvar. Confira categoria, data, moeda e valores com ponto decimal. Nenhuma alteração desta tentativa foi aplicada.'};}
        catch(Throwable $e){log_message('error','Company expense operation failed; no expense data logged.');$this->output->set_status_header(503);$error='Não foi possível confirmar a gravação. Recarregue antes de tentar novamente.';}
        $caps=[];foreach(['view','create','edit','delete'] as $cap)$caps[$cap]=$acl->can($issuer,$actor,'expenses',$cap);
        $this->load->view('admin/companies/expenses',['issuer'=>$issuer,'directory'=>$page,'detail'=>$detail,'files'=>$files,'categories'=>$categories,'clients'=>$clients,'projects'=>$projects,'capabilities'=>$caps,'error'=>$error,'search'=>$search,'company_expenses_csrf'=>$csrf,'request_key'=>bin2hex(random_bytes(32))]);
    }
}
