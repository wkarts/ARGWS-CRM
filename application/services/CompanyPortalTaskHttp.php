<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyPortalTaskWrite.php';require_once __DIR__.'/CompanyPortalTaskReceipts.php';require_once __DIR__.'/CompanyOperationFilesHttp.php';
/** Finite native portal POST adapter, called only after both portal and framework CSRF checks. */
final class CompanyPortalTaskHttp
{
 public const ACTIONS=['project_task_create','project_task_edit','project_task_comment','project_task_upload','project_task_hide','project_task_receipt_file'];
 public static function handle($controller,array $session,int $issuer,string $action):bool
 {
  if(!in_array($action,self::ACTIONS,true))return false;$c=$controller;$project=$c->input->post('project_id',false);$key=$c->input->post($action==='project_task_receipt_file'?'receipt_key':'request_key',false);if(!is_string($key)){show_404();return true;}$secret=(string)$c->config->item('encryption_key');
  try{
   if($action==='project_task_receipt_file'){$r=new CompanyPortalTaskReceipts($c->db,db_prefix(),$secret);$file=$r->file($session,$issuer,$project,$key);$r->confirmFile($session,$issuer,$project,$key,$file['content_hash']);$c->output->set_header('Cache-Control: no-store');$c->output->set_header('X-Content-Type-Options: nosniff');$c->output->set_header("Content-Security-Policy: sandbox; default-src 'none'");$c->output->set_header('Content-Disposition: attachment; filename="anexo.'.($file['mime']==='image/png'?'png':'pdf').'"; filename*=UTF-8\'\''.rawurlencode($file['filename']));$c->output->set_content_type($file['mime'])->set_output($file['content']);return true;}
   $w=new CompanyPortalTaskWrite($c->db,db_prefix(),$secret);$revision=$c->input->post('revision',false);$task=$c->input->post('task_id',false);
   if(in_array($action,['project_task_create','project_task_edit'],true)){$input=$c->input->post('task',false);if(!is_array($input))throw new DomainException('company_portal_task_invalid');$result=$action==='project_task_create'?$w->create($session,$issuer,$project,$revision,$input,$key):$w->edit($session,$issuer,$project,$task,$revision,$input,$key);}
   elseif($action==='project_task_comment'){$body=$c->input->post('comment_body',false);if(!is_string($body))throw new DomainException('company_portal_task_invalid');$result=$w->comment($session,$issuer,$project,$task,$revision,$body,$key);}
   elseif($action==='project_task_upload'){[$name,$bytes]=CompanyOperationFilesHttp::upload();$result=$w->upload($session,$issuer,$project,$task,$revision,$name,$bytes,$key);}
   else{$file=$c->input->post('file_key',false);if(!is_string($file))throw new DomainException('company_portal_task_invalid');$result=$w->hide($session,$issuer,$project,$task,$revision,$file,$key);}
  }catch(DomainException $e){if(in_array($e->getMessage(),['company_portal_task_stale','company_portal_task_request_conflict'],true)){show_error('A tarefa mudou ou este envio já possui outro conteúdo. Reabra seu resultado antes de salvar.',409);return true;}if(in_array($e->getMessage(),['company_portal_task_invalid','company_operation_file_invalid'],true)){show_error('Confira texto, data e arquivo antes de enviar.',422);return true;}if(in_array($e->getMessage(),['company_portal_task_limit','company_operation_file_limit'],true)){show_error('Limite desta tarefa atingido. Consulte a equipe.',429);return true;}show_404();return true;}
  redirect($c->config->site_url('company_portal/index/'.$issuer).'?operation_kind=projects&resource_id='.(int)$project.'&task_receipt='.$key,'location',303);return true;
 }
}
