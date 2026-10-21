<?php
defined('BASEPATH') or exit('No direct script access allowed');
/** Reviewed independent cost only; native CI CSRF and sealed company context remain mandatory. */
class Company_expense_copies extends AdminController
{
 public function index()
 {
  if(func_num_args()!==0){show_404();return;}if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto indisponível.',503);return;}
  require_once APPPATH.'services/CompanyExpenseCopies.php';$ctx=CompanyRequestScope::context();$issuer=$ctx->issuerId();$actor=$ctx->staffId();$acl=new IssuerResourceAccess($this->db,db_prefix());foreach(['view','create']as$cap)if(!$acl->can($issuer,$actor,'expenses',$cap)){show_error('Sem permissão para copiar despesas nesta empresa.',403);return;}
  $this->output->set_header('Cache-Control: no-store');$service=new CompanyExpenseCopies($this->db,db_prefix());$csrf=$this->session->userdata('company_expense_copies_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_expense_copies_csrf',$csrf);}$preview=$review=$receipt=$error=$attempt=null;$source=null;$values=null;
  try{$verb=$this->input->method(true);
   if($verb==='POST'){
    $token=$this->input->post('company_expense_copies_csrf',false);if(!is_string($token)||!hash_equals($csrf,$token)){show_error('Reabra a revisão antes de copiar.',403);return;}$action=$this->input->post('action',false);if(!in_array($action,['review_expense_copy','copy_reviewed_expense'],true)){show_404();return;}$source=IssuerPolicy::id($this->input->post('source_expense_id',false));
    if($action==='copy_reviewed_expense'){$data=$this->input->post('review',false);$key=$this->input->post('request_key',false);if(!is_array($data)||!is_string($key)||!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_expense_copy_invalid');$attempt=$key;$result=$service->create($issuer,$actor,$source,$data,$key);CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'company_expense_copies?receipt='.$result['request_key']),'location',303);return;}
    $values=$this->input->post('expense',false);$revision=$this->input->post('source_revision',false);if(!is_array($values)||!is_string($revision))throw new DomainException('company_expense_copy_invalid');$preview=$service->preview($issuer,$actor,$source);$review=$service->review($issuer,$actor,$source,$revision,$values);
   }elseif(in_array($verb,['GET','HEAD'],true)){$id=$this->input->get('source',false);$key=$this->input->get('receipt',false);if(($id===null)===($key===null)){show_404();return;}if($id!==null){if(!is_scalar($id)||$key!==null){show_404();return;}$source=IssuerPolicy::id($id);$preview=$service->preview($issuer,$actor,$source);$values=$preview['input'];$values['date']=date('Y-m-d');}else{if(!is_string($key)){show_404();return;}$receipt=$service->receipt($issuer,$actor,$key);}}
   else{show_error('Método não permitido.',405);return;}
  }catch(DomainException$e){if(in_array($e->getMessage(),['company_expense_copy_denied','company_expense_denied','company_expense_reference_denied','issuer_access_denied'],true)){show_error('Cópia indisponível para esta empresa, origem ou referências atuais.',403);return;}$this->output->set_status_header(str_contains($e->getMessage(),'stale')||str_contains($e->getMessage(),'conflict')?409:422);$error='A revisão mudou ou contém valores inválidos. Reabra a origem e confirme os dados atuais.';
  }catch(Throwable$e){log_message('error','Reviewed expense copy unconfirmed; no source contents logged.');$this->output->set_status_header(503);$error='Não foi possível confirmar esta tentativa. Consulte o recibo desta chave antes de iniciar outra cópia.';}
  $this->load->view('admin/companies/expense_copies',compact('issuer','source','preview','review','receipt','error','attempt','values')+['request_key'=>bin2hex(random_bytes(32)),'company_expense_copies_csrf'=>$csrf]);
 }
}
