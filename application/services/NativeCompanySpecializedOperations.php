<?php
declare(strict_types=1);
defined('BASEPATH') or exit('No direct script access allowed');
require_once APPPATH.'services/CompanyAi.php';
require_once APPPATH.'services/CompanyApiTokens.php';
require_once APPPATH.'services/CompanyConnectInstanceOperations.php';
require_once APPPATH.'services/CompanyConnectPairing.php';
require_once APPPATH.'services/CompanyGoogleMailOAuth.php';
require_once APPPATH.'services/CompanyHr.php';
require_once APPPATH.'services/CompanyHrPdf.php';
require_once APPPATH.'services/CompanyImports.php';
require_once APPPATH.'services/CompanyInvoiceConnectNotification.php';
require_once APPPATH.'services/CompanyMailTransportPolicy.php';
require_once APPPATH.'services/CompanyMicrosoftMailOAuth.php';
require_once APPPATH.'services/CompanyOperationFilesHttp.php';
require_once APPPATH.'services/CompanyPortalMailConfig.php';
require_once APPPATH.'services/CompanyPortalTaskAuthorship.php';
require_once APPPATH.'services/CompanyPortalTaskWriteRules.php';
require_once APPPATH.'services/CompanyProjectBillingRates.php';
require_once APPPATH.'services/CompanyProjectContactFileRules.php';
require_once APPPATH.'services/CompanyProjectCopies.php';
require_once APPPATH.'services/CompanyProjectDiscussionExtrasHttp.php';
require_once APPPATH.'services/CompanyProjectDiscussionFiles.php';
require_once APPPATH.'services/CompanyProjectDiscussions.php';
require_once APPPATH.'services/CompanyProjectDiscussionsHttp.php';
require_once APPPATH.'services/CompanyProjectExtras.php';
require_once APPPATH.'services/CompanyProjectFixedBilling.php';
require_once APPPATH.'services/CompanyProjectGantt.php';
require_once APPPATH.'services/CompanyProjectMilestones.php';
require_once APPPATH.'services/CompanyProjectPortalRules.php';
require_once APPPATH.'services/CompanyProjectTimesheets.php';
require_once APPPATH.'services/CompanyProjects.php';
require_once APPPATH.'services/CompanyPurchases.php';
require_once APPPATH.'services/CompanyPusherTaskNotifications.php';
require_once APPPATH.'services/CompanyStandaloneBilling.php';
require_once APPPATH.'services/CompanyStandaloneTasks.php';
require_once APPPATH.'services/CompanyTaskBilling.php';
require_once APPPATH.'services/CompanyTaskCopies.php';
require_once APPPATH.'services/CompanyTaskRecurring.php';
require_once APPPATH.'services/CompanyTaskTimeBatch.php';
require_once APPPATH.'services/CompanyTaskTimeBilling.php';
require_once APPPATH.'services/CompanyTasks.php';
require_once APPPATH.'services/CompanyTicketConnect.php';
require_once APPPATH.'services/CompanyUiTranslation.php';
require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyFeeExpense.php';
require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyHostedLinks.php';
require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyPayers.php';
require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyReceivedReceipt.php';
require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyRefunds.php';
require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyRemoteOperations.php';
require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyService.php';
require_once FCPATH.'modules/asaas/libraries/Asaas/ConfirmedFullRefund.php';
require_once FCPATH.'modules/asaas/libraries/Asaas/SettingsPresentation.php';
require_once FCPATH.'modules/asaas/libraries/Asaas/WebhookAdminPresentation.php';
require_once FCPATH.'modules/gerencianet_gateway/services/CompanyAccounts.php';
require_once FCPATH.'modules/gerencianet_gateway/services/CompanyCheckout.php';
require_once FCPATH.'modules/gerencianet_gateway/services/CompanyTransport.php';

/**
 * Native actions composed into the original CRM controllers.
 * Each method is reached through an allowlisted canonical controller action.
 */
trait NativeCompanySpecializedOperations
{
    protected function nativeSettingsPusher()
        {
            if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto de empresa indisponível.',503);return;}
            require_once APPPATH.'services/CompanyPusherTaskNotifications.php';$ctx=CompanyRequestScope::context();$issuer=$ctx->issuerId();$actor=$ctx->staffId();$profile=new CompanyPusherProfile($this->db,db_prefix());$service=new CompanyPusherTaskNotifications($this->db,db_prefix());$url=CompanyRequestScope::adminUrl($this,'settings/pusher');$this->output->set_header('Cache-Control: no-store')->set_header('Pragma: no-cache')->set_header('Referrer-Policy: no-referrer');$csrf=$this->session->userdata('company_pusher_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_pusher_csrf',$csrf);}$error=null;
            try{
                $this->load->library('encryption');$decrypt=fn($v)=>$this->encryption->decrypt($v);$encrypt=fn($v)=>$this->encryption->encrypt($v);$method=$this->input->method(true);$reader=$this->input->get('reader',false);
                if($reader!==null){if($reader!=='1'||$method!=='GET')throw new DomainException('company_pusher_invalid');$this->output->set_content_type('application/json')->set_output(json_encode(['items'=>$service->inbox($issuer,$actor)],JSON_THROW_ON_ERROR));return;}
                if($method==='POST'){
                    $posted=$this->input->post('company_pusher_csrf',false);if(!is_string($posted)||!hash_equals($csrf,$posted))throw new DomainException('company_pusher_denied');$action=$this->input->post('action',false);
                    if($action==='authorize'){$channel=$this->input->post('channel_name',false);$socket=$this->input->post('socket_id',false);if(!is_string($channel)||!is_string($socket))throw new DomainException('company_pusher_denied');$this->output->set_content_type('application/json')->set_output(json_encode($service->authorizeChannel($issuer,$actor,$channel,$socket,$decrypt),JSON_THROW_ON_ERROR));return;}
                    $revision=$this->input->post('revision',false);if(!is_string($revision)||!preg_match('/^(0|[1-9][0-9]{0,8})$/D',$revision))throw new DomainException('company_pusher_invalid');
                    if($action==='save'){$values=[];foreach(['app_id','app_key','cluster','app_secret']as$f){$values[$f]=$this->input->post($f,false);if(!is_string($values[$f]))throw new DomainException('company_pusher_invalid');}$profile->save($issuer,$actor,(int)$revision,$values['app_id'],$values['app_key'],$values['cluster'],$values['app_secret'],$encrypt);}
                    elseif($action==='revoke')$profile->revoke($issuer,$actor,(int)$revision);
                    elseif(in_array($action,['queue','dispatch'],true)){$key=$this->input->post('request_key',false);if(!is_string($key))throw new DomainException('company_pusher_invalid');$current=$service->descriptor($issuer,$actor);if((int)$revision!==$current['revision'])throw new DomainException('company_pusher_stale');if($action==='queue'){$task=$this->input->post('task_id',false);$taskRevision=$this->input->post('task_revision',false);if(!is_string($task)||!preg_match('/^[1-9][0-9]{0,8}$/D',$task)||!is_string($taskRevision)||!preg_match('/^[1-9][0-9]{0,8}$/D',$taskRevision))throw new DomainException('company_pusher_invalid');$service->queue($issuer,$actor,(int)$task,(int)$taskRevision,(int)$revision,$key);}else $service->dispatch($issuer,$actor,$key,$decrypt);}
                    else throw new DomainException('company_pusher_invalid');CompanyRequestScope::invalidateCaches($this);redirect($url,'location',303);return;
                }elseif(!in_array($method,['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
            }catch(DomainException $e){$status=match($e->getMessage()){'company_pusher_denied','company_pusher_disabled'=>403,'company_pusher_stale','company_pusher_conflict'=>409,default=>422};$this->output->set_status_header($status);$error=$status===403?'Seu acesso mudou. Reabra a empresa.':($status===409?'O perfil ou a tarefa mudou. Reabra a página.':'Confira o perfil próprio e sua ativação local.');}
            catch(Throwable $e){$this->output->set_status_header(503);$error='Operação não confirmada. Uma tentativa incerta não será repetida automaticamente.';}
            if(($this->input->post('action',false)==='authorize')||$this->input->get('reader',false)!==null){$this->output->set_content_type('application/json')->set_output('{"error":"company_pusher_unavailable"}');return;}
            try{
                $acl=new IssuerResourceAccess($this->db,db_prefix());
                $canView=$acl->can($issuer,$actor,'settings','view')&&$acl->can($issuer,$actor,'pusher','view');
                $canConfigure=$canView&&$acl->can($issuer,$actor,'settings','edit')&&$acl->can($issuer,$actor,'pusher','configure');
                $page=$canView?$profile->page($issuer,$actor):null;
                $receive=$acl->can($issuer,$actor,'pusher','view')&&$acl->can($issuer,$actor,'tasks','view')&&$acl->can($issuer,$actor,'projects','view');
                if(!$canView&&!$receive)throw new DomainException('company_pusher_denied');
                $activity=['tasks'=>[],'publish'=>false,'notices'=>[]];$descriptor=null;$inbox=[];
                if($receive)try{
                    $descriptor=$service->descriptor($issuer,$actor);
                    $inbox=$service->inbox($issuer,$actor);
                    $activity=$service->page($issuer,$actor);
                }catch(DomainException $e){
                    // A revoked credential owner must not strand the currently authorized
                    // configurator. This renders configuration only; no old epoch is used.
                    if($e->getMessage()!=='company_pusher_disabled'){
                        if(!$canConfigure||$e->getMessage()!=='company_pusher_denied')throw $e;
                        $page=$profile->page($issuer,$actor);
                        $profile->authorize($issuer,$actor,'configure');
                        $error??='O perfil anterior perdeu autorização. Salve explicitamente um perfil para iniciar uma nova revisão.';
                    }
                    $descriptor=null;$inbox=[];$activity=['tasks'=>[],'publish'=>false,'notices'=>[]];
                }
                $this->load->view('admin/settings/native_pusher',['profile'=>$page,'can_configure'=>$canConfigure,'activity'=>$activity,'descriptor'=>$descriptor,'inbox'=>$inbox,'csrf'=>$csrf,'error'=>$error]);
            }catch(DomainException $e){show_error('Sem acesso aos avisos próprios desta empresa.',403);}catch(Throwable $e){show_error('Avisos próprios indisponíveis. Confira a atualização do núcleo.',503);}
        }
    protected function nativeProjectExtras()
     {
      if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto indisponível.',503);return;}
      require_once APPPATH.'services/CompanyProjectExtras.php';require_once APPPATH.'services/CompanyTasks.php';$ctx=CompanyRequestScope::context();$issuer=$ctx->issuerId();$actor=$ctx->staffId();$acl=new IssuerResourceAccess($this->db,db_prefix());$s=new CompanyProjectExtras($this->db,db_prefix());$this->output->set_header('Cache-Control: no-store');$csrf=$this->session->userdata('company_project_extras_csrf');if(!is_string($csrf)||strlen($csrf)!==64){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_project_extras_csrf',$csrf);}$error=null;$resource=null;$kind='project';$id=null;
      try{
       $post=$this->input->method(true)==='POST';$kind=$post?$this->input->post('kind',false):($this->input->get('kind',false)??'project');$id=$post?$this->input->post('id',false):$this->input->get('id',false);if(!is_string($kind)||!in_array($kind,['project','task'],true))throw new DomainException('company_project_invalid_input');
       if($post){$token=$this->input->post('company_project_extras_csrf',false);if(!is_string($token)||!hash_equals($csrf,$token)){show_error('Recarregue antes de salvar.',403);return;}$action=$this->input->post('action',false);$key=$this->input->post('request_key',false);$rev=$this->input->post('revision',false);if(!is_string($key))throw new DomainException('company_project_invalid_input');
        if($action==='milestone'&&$kind==='project'){$input=$this->input->post('milestone',false);$mid=$this->input->post('milestone_id',false);if(!is_array($input))throw new DomainException('company_project_invalid_input');$s->milestone($issuer,$actor,$id,$rev,$key,$input,$mid===''?null:$mid);}
        elseif($action==='follow'){$follow=$this->input->post('follow',false);if(!in_array($follow,['0','1'],true))throw new DomainException('company_project_invalid_input');$s->follow($issuer,$actor,$kind,$id,$rev,$follow==='1',$key);}
        elseif($action==='dependencies'&&$kind==='task'){$deps=$this->input->post('dependencies',false)??[];if(!is_array($deps))throw new DomainException('company_project_invalid_input');$s->dependencies($issuer,$actor,$id,$rev,$deps,$key);}
        elseif($action==='move'&&$kind==='task'){$status=$this->input->post('status',false);if(!is_string($status))throw new DomainException('company_project_invalid_input');$tasks=new CompanyTasks($this->db,db_prefix());$row=$tasks->get($issuer,$actor,$id);$input=[];foreach(['project_id','assignee_id','name','description','due_date','checklist'] as $field)$input[$field]=$row[$field];$input['status']=$status;$tasks->update($issuer,$actor,$id,$rev,$input,$key);}
        elseif($action==='upload'){$file=$_FILES['project_file']??null;if(!is_array($file)||($file['error']??null)!==UPLOAD_ERR_OK||!is_int($file['size']??null)||$file['size']>2097152||!is_string($file['name']??null)||!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name']))throw new DomainException('company_project_file_invalid');$bytes=file_get_contents($file['tmp_name'],false,null,0,2097153);if(!is_string($bytes))throw new DomainException('company_project_file_invalid');$s->upload($issuer,$actor,$kind,$id,$rev,$key,$file['name'],$bytes);}
        elseif($action==='hide'){$file=$this->input->post('file_key',false);if(!is_string($file))throw new DomainException('company_project_file_invalid');$s->hide($issuer,$actor,$kind,$id,$rev,$file,$key);}
        else throw new DomainException('company_project_invalid_input');CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'projects/extras?kind='.$kind.'&id='.(int)$id),'location',303);return;
       }elseif(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
       $resource=$s->get($issuer,$actor,$kind,$id);$key=$this->input->get('file',false);if($key!==null){if(!is_string($key))throw new DomainException('company_project_file_invalid');$file=$s->file($issuer,$actor,$kind,$id,$key);$preview=$this->input->get('preview',false)==='1'&&$file['mime']==='image/png';$this->output->set_header('X-Content-Type-Options: nosniff');$this->output->set_header("Content-Security-Policy: sandbox; default-src 'none'");$this->output->set_header('Content-Disposition: '.($preview?'inline':'attachment').'; filename="anexo.'.($file['mime']==='image/png'?'png':'pdf').'"; filename*=UTF-8\'\''.rawurlencode($file['filename']));$this->output->set_content_type($file['mime']);$this->output->set_output($file['content']);return;}
      }catch(DomainException $e){$this->output->set_status_header(409);$error=match($e->getMessage()){'company_project_stale'=>'O projeto ou a tarefa mudou. Reabra antes de salvar.','company_project_dependency_cycle'=>'Esta dependência criaria um ciclo. Nenhuma alteração foi aplicada.','company_project_dependencies_open'=>'Conclua as dependências antes de concluir esta tarefa.','company_project_completed_dependents'=>'Reabra primeiro as tarefas dependentes concluídas.','company_project_milestones_open'=>'Conclua todos os marcos antes de encerrar o projeto.','company_project_closed'=>'O projeto ou a tarefa está encerrado e não aceita esta alteração.',default=>'Dados, referências ou permissões inválidos. Nenhuma alteração foi confirmada.'};}catch(Throwable $e){log_message('error','Company project subresource operation unconfirmed; no contents logged.');$this->output->set_status_header(503);$error='Não foi possível confirmar a operação. Reabra antes de tentar novamente.';}
      $feature=$kind==='task'?'tasks':'projects';$this->load->view('admin/projects/native_extras',['issuer'=>$issuer,'actor'=>$actor,'resource'=>$resource,'kind'=>$kind,'error'=>$error,'company_project_extras_csrf'=>$csrf,'request_key'=>bin2hex(random_bytes(32)),'can_edit'=>$acl->can($issuer,$actor,$feature,'edit'),'can_tasks_edit'=>$acl->can($issuer,$actor,'tasks','view')&&$acl->can($issuer,$actor,'tasks','edit'),'can_tasks_view'=>$acl->can($issuer,$actor,'tasks','view')]);
     }
    protected function nativeProjectPortalRules()
        {
            if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto indisponível.',503);return;}
            require_once APPPATH.'services/CompanyProjectPortalRules.php';require_once APPPATH.'services/CompanyPortalTaskWriteRules.php';require_once APPPATH.'services/CompanyPortalTaskAuthorship.php';require_once APPPATH.'services/CompanyProjectMilestones.php';require_once APPPATH.'services/CompanyProjectTimesheets.php';require_once APPPATH.'services/CompanyProjectGantt.php';require_once APPPATH.'services/CompanyProjectContactFileRules.php';require_once APPPATH.'services/CompanyProjectDiscussions.php';$context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();$acl=new IssuerResourceAccess($this->db,db_prefix());if(!$acl->can($issuer,$actor,'projects','view')){show_error('Sem permissão para projetos desta empresa.',403);return;}
            $this->output->set_header('Cache-Control: no-store');$this->output->set_header('X-Content-Type-Options: nosniff');$csrf=$this->session->userdata('company_project_rules_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_project_rules_csrf',$csrf);}
            $service=new CompanyProjectPortalRules($this->db,db_prefix());$state=null;$write_state=null;$task_authorship=null;$milestone_state=null;$timesheet_state=null;$gantt_state=null;$project_file_rules=null;$discussion_state=null;$error=null;$project=null;$task=null;
            try{
                if($this->input->method(true)==='POST'){
                    $sent=$this->input->post('company_project_rules_csrf',false);if(!is_string($sent)||!hash_equals($csrf,$sent)){show_error('Recarregue antes de salvar.',403);return;}
                    $project=IssuerPolicy::id($this->input->post('project_id',false));$action=$this->input->post('action',false);$key=$this->input->post('request_key',false);if(!is_string($key))throw new DomainException('company_project_portal_invalid');
                    if(in_array($action,['save_rules','hide_tasks'],true)){$flags=[];foreach(CompanyProjectPortalRulesSchema::FLAGS as $flag)$flags[$flag]=$action==='hide_tasks'?'0':($this->input->post($flag,false)??'0');$service->save($issuer,$actor,$project,$this->input->post('project_revision',false),$this->input->post('rules_revision',false),$flags,$key);}
                    elseif($action==='save_write_rules'){$writeFlags=[];foreach(CompanyPortalTaskWriteSchema::FLAGS as $flag)$writeFlags[$flag]=$this->input->post($flag,false)??'0';(new CompanyPortalTaskWriteRules($this->db,db_prefix()))->save($issuer,$actor,$project,$this->input->post('project_revision',false),$this->input->post('write_revision',false),$writeFlags,$key);}
                    elseif($action==='save_discussion_rules'){(new CompanyProjectDiscussions($this->db,db_prefix()))->saveRules($issuer,$actor,$project,$this->input->post('project_revision',false),$this->input->post('discussion_collection_revision',false),$this->input->post('discussion_rules_revision',false),['open_discussions'=>$this->input->post('open_discussions',false)??'0'],['project_discussions'=>$this->input->post('project_discussions',false)??'0'],$key);}
                    elseif($action==='discussion_file_download'){require_once APPPATH.'services/CompanyProjectDiscussionExtrasHttp.php';CompanyProjectDiscussionExtrasHttp::staffDownload($this,$issuer,$actor,$project);return;}
                    elseif(in_array($action,['discussion_edit','discussion_delete','discussion_file_upload','discussion_file_audience'],true)){require_once APPPATH.'services/CompanyProjectDiscussionExtrasHttp.php';$discussion=CompanyProjectDiscussionExtrasHttp::staff($this,$issuer,$actor,$project,$action,$key);CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'projects/portal_rules?project='.$project.'&discussion='.$discussion),'location',303);return;}
                    elseif(in_array($action,['discussion_open','discussion_comment','discussion_thread_state','discussion_post_audience'],true)){require_once APPPATH.'services/CompanyProjectDiscussionsHttp.php';$discussion=CompanyProjectDiscussionsHttp::staff($this,$issuer,$actor,$project,$action,$key);CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'projects/portal_rules?project='.$project.'&discussion='.$discussion),'location',303);return;}
                    elseif($action==='save_project_file_upload_rules'){(new CompanyProjectContactFileRules($this->db,db_prefix()))->save($issuer,$actor,$project,$this->input->post('project_revision',false),$this->input->post('project_file_rules_revision',false),['upload_files'=>$this->input->post('upload_files',false)??'0'],['project_files'=>$this->input->post('project_files',false)??'0'],$key);}
                    elseif($action==='save_gantt_rules'){(new CompanyProjectGantt($this->db,db_prefix()))->save($issuer,$actor,$project,$this->input->post('project_revision',false),$this->input->post('gantt_rules_revision',false),['view_gantt'=>$this->input->post('view_gantt',false)??'0'],['project_gantt'=>$this->input->post('project_gantt',false)??'0'],$key);}
                    elseif($action==='save_gantt_date'){$task=IssuerPolicy::id($this->input->post('task_id',false));$start=$this->input->post('start_date',false);$visible=$this->input->post('visible',false)??'0';if(!is_string($start)||!in_array($visible,['0','1'],true))throw new DomainException('company_project_portal_invalid');(new CompanyProjectGantt($this->db,db_prefix()))->taskDate($issuer,$actor,$project,$this->input->post('project_revision',false),$task,$this->input->post('task_revision',false),$this->input->post('gantt_date_revision',false),$start,$visible==='1',$key);}
                    elseif($action==='save_timesheet_rules'){(new CompanyProjectTimesheets($this->db,db_prefix()))->save($issuer,$actor,$project,$this->input->post('project_revision',false),$this->input->post('timesheet_rules_revision',false),['view_timesheets'=>$this->input->post('view_timesheets',false)??'0'],['project_timesheets'=>$this->input->post('project_timesheets',false)??'0'],$key);}
                    elseif(in_array($action,['save_milestone_rules','save_milestone_details','save_milestone_tasks','publish_milestone'],true)){
                        $milestones=new CompanyProjectMilestones($this->db,db_prefix());$pr=$this->input->post('project_revision',false);
                        if($action==='save_milestone_rules')$milestones->saveRules($issuer,$actor,$project,$pr,$this->input->post('milestone_rules_revision',false),['view_milestones'=>$this->input->post('view_milestones',false)??'0'],['project_milestones'=>$this->input->post('project_milestones',false)??'0'],$key);
                        else{$mid=$this->input->post('milestone_id',false);$mr=$this->input->post('milestone_revision',false);
                            if($action==='save_milestone_details')$milestones->details($issuer,$actor,$project,$pr,$mid,$mr,['start_date'=>$this->input->post('start_date',false),'description'=>$this->input->post('description',false),'description_visible'=>$this->input->post('description_visible',false)??'0','sort_order'=>$this->input->post('sort_order',false)],$key);
                            elseif($action==='save_milestone_tasks'){$ids=$this->input->post('milestone_tasks',false)??[];if(!is_array($ids)||!array_is_list($ids))throw new DomainException('company_project_portal_invalid');$milestones->tasks($issuer,$actor,$project,$pr,$mid,$mr,$ids,$key);}
                            else{$visible=$this->input->post('visible',false)??'0';if(!in_array($visible,['0','1'],true))throw new DomainException('company_project_portal_invalid');$milestones->publish($issuer,$actor,$project,$pr,$mid,$mr,$visible==='1',$key);}
                        }
                    }
                    elseif($action==='publish'){$task=IssuerPolicy::id($this->input->post('task_id',false));$kind=$this->input->post('kind',false);$child=$this->input->post('child_key',false);$visible=$this->input->post('visible',false)??'0';if(!is_string($kind)||!in_array($visible,['0','1'],true))throw new DomainException('company_project_portal_invalid');$service->publish($issuer,$actor,$project,$this->input->post('project_revision',false),$task,$this->input->post('task_revision',false),$kind,$child,$visible==='1',$key);}
                    else throw new DomainException('company_project_portal_invalid');
                    CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'projects/portal_rules?project='.$project.($task!==null?'&task='.$task:'').(isset($mid)?'&milestone='.IssuerPolicy::id($mid):'')),'location',303);return;
                }elseif(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
                $project=IssuerPolicy::id($this->input->get('project',false));$task=$this->input->get('task',false);$state=$service->get($issuer,$actor,$project,$task,$this->input->get('after',false)??0);if($state['can_edit'])$write_state=(new CompanyPortalTaskWriteRules($this->db,db_prefix()))->get($issuer,$actor,$project);$discussion_state=(new CompanyProjectDiscussions($this->db,db_prefix()))->staffPage($issuer,$actor,$project,$this->input->get('discussion',false));if($discussion_state['thread']&&(int)$discussion_state['thread']['active']===1){require_once APPPATH.'services/CompanyProjectDiscussionFiles.php';$discussionFilesReader=new CompanyProjectDiscussionFiles($this->db,db_prefix());foreach($discussion_state['posts'] as &$discussionPost){$discussionPost['files']=(string)$discussionPost['active']==='1'?$discussionFilesReader->staffList($issuer,$actor,$project,$discussion_state['thread']['id'],$discussionPost['id']):[];}unset($discussionPost);}$project_file_rules=(new CompanyProjectContactFileRules($this->db,db_prefix()))->get($issuer,$actor,$project);$gantt_state=(new CompanyProjectGantt($this->db,db_prefix()))->get($issuer,$actor,$project,$task);$timesheet_state=(new CompanyProjectTimesheets($this->db,db_prefix()))->get($issuer,$actor,$project);$milestone_state=(new CompanyProjectMilestones($this->db,db_prefix()))->get($issuer,$actor,$project,$this->input->get('milestone',false));if($state['task'])$task_authorship=(new CompanyPortalTaskAuthorship($this->db,db_prefix()))->get($issuer,$actor,$project,$state['task']['id']);
            }catch(DomainException $e){$this->output->set_status_header(409);$error=match($e->getMessage()){'company_project_portal_stale'=>'O projeto, a tarefa ou as regras mudaram. Reabra para revisar antes de publicar.','company_project_portal_request_conflict'=>'Esta tentativa já foi usada com outros dados. Reabra o formulário.',default=>'Referências, publicação ou permissões indisponíveis. Nenhuma alteração foi confirmada.'};}
            catch(Throwable $e){log_message('error','Project portal rules unconfirmed; contents omitted.');$this->output->set_status_header(503);$error='Não foi possível confirmar a operação. Reabra antes de tentar novamente.';}
            $this->load->view('admin/projects/native_portal_rules',['issuer'=>$issuer,'actor'=>$actor,'state'=>$state,'write_state'=>$write_state,'task_authorship'=>$task_authorship,'milestone_state'=>$milestone_state,'timesheet_state'=>$timesheet_state,'gantt_state'=>$gantt_state,'discussion_state'=>$discussion_state,'project_file_rules'=>$project_file_rules,'error'=>$error,'company_project_rules_csrf'=>$csrf,'request_key'=>bin2hex(random_bytes(32))]);
        }
    protected function nativeSettingsAsaas()
        {
            if(!class_exists('CompanyRequestScope',false)||!CompanyRequestScope::sealedFor($this)||!CompanySettingsRuntime::active()){show_error('Acesso negado.',403);return;}
            $translate=CompanyUiTranslation::translator();
            $this->output->set_header('Cache-Control: no-store, private, max-age=0');
            $this->output->set_header('Pragma: no-cache');
            $this->output->set_header('Referrer-Policy: no-referrer');
            $issuer=CompanySettingsRuntime::issuerId();$actor=CompanySettingsRuntime::staffId();$service=new \Asaas\CompanyService($this->db,db_prefix());$error=null;$saved=null;$oneTime=null;$revealedApiKey=null;
            try{$service->authorize($issuer,$actor,'view');}catch(Throwable $e){show_error('Integração indisponível ou acesso negado.',403);return;}
            $webhookCsrf=$this->session->userdata('company_asaas_webhook_csrf');if(!is_string($webhookCsrf)||!preg_match('/^[a-f0-9]{64}$/D',$webhookCsrf)){$webhookCsrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_asaas_webhook_csrf',$webhookCsrf);}
            $preferencesCsrf=$this->session->userdata('company_asaas_preferences_csrf');if(!is_string($preferencesCsrf)||!preg_match('/^[a-f0-9]{64}$/D',$preferencesCsrf)){$preferencesCsrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_asaas_preferences_csrf',$preferencesCsrf);}
            $refundCsrf=$this->session->userdata('company_asaas_refund_csrf');if(!is_string($refundCsrf)||!preg_match('/^[a-f0-9]{64}$/D',$refundCsrf)){$refundCsrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_asaas_refund_csrf',$refundCsrf);}
            try{
                if($this->input->method(true)==='POST'){
                    $post=$this->input->post(null,false);$action=$post['action']??'';
                    if(!is_string($action))throw new DomainException('asaas_company_invalid_input');
                    if(in_array($action,['rotate','reveal_webhook','reveal_api_key'],true)){
                        if(!is_string($post['company_asaas_webhook_csrf']??null)||!hash_equals($webhookCsrf,$post['company_asaas_webhook_csrf'])){show_error('Reabra o formulário antes de acessar o token.',403);return;}
                        if($action==='rotate'&&($post['confirm']??null)!=='1'){show_error('Confirme a substituição do token desta conta.',403);return;}
                        foreach(['token','webhook_token','one_time_token','api_key'] as $field)if(array_key_exists($field,$post)){show_error('O token é gerado exclusivamente pelo CRM.',400);return;}
                    }
                    if(in_array($action,['save_profile','select_environment','issue'],true)&&(!is_string($post['company_asaas_preferences_csrf']??null)||!hash_equals($preferencesCsrf,$post['company_asaas_preferences_csrf'])))throw new DomainException('asaas_company_preferences_stale');
                    if(str_starts_with($action,'refund_')){if(($post['confirm']??null)!=='1'||!is_string($post['company_asaas_refund_csrf']??null)||!hash_equals($refundCsrf,$post['company_asaas_refund_csrf'])){show_error('Reabra o formulário e confirme esta ação no reembolso.',403);return;}}
                    $decrypt=fn($value)=>$this->encryption->decrypt($value);
                    switch($action){
                        case 'save_profile':
                            if(!is_array($post['profile']??null))throw new DomainException('asaas_company_profile_invalid');
                            foreach(['environment','profile_revision','account_revision'] as $field)if(!is_string($post[$field]??null))throw new DomainException('asaas_company_profile_invalid');
                            (new \Asaas\CompanyPreferences($this->db,db_prefix()))->saveProfile($issuer,$actor,(string)($post['environment']??''),$post['profile'],(string)($post['profile_revision']??''),(string)($post['account_revision']??''));$saved=$translate('asaas_company_preferences_saved');break;
                        case 'select_environment':
                            foreach(['environment','selection_revision','account_revision'] as $field)if(!is_string($post[$field]??null))throw new DomainException('asaas_company_profile_invalid');
                            (new \Asaas\CompanyPreferences($this->db,db_prefix()))->selectEnvironment($issuer,$actor,(string)($post['environment']??''),(string)($post['selection_revision']??''),(string)($post['account_revision']??''));$saved=$translate('asaas_company_preferences_environment_saved');break;
                        case 'save_account':$service->saveAccount($issuer,$actor,(string)($post['environment']??''),(string)($post['account_id']??''),(string)($post['api_key']??''),(string)($post['revision']??''),fn($value)=>$this->encryption->encrypt($value));$saved='Credencial protegida. Confira a conta por API antes de emitir.';break;
                        case 'verify':$service->verifyAccount($issuer,$actor,$post['account_row']??null,(string)($post['revision']??''),$decrypt);$saved='A credencial confirmou a carteira configurada para este ambiente.';break;
                        case 'reveal_api_key':$revealedApiKey=$service->revealApiKey($issuer,$actor,$post['account_row']??null,(string)($post['revision']??''),$decrypt);$saved='Chave API recuperada desta conta e ambiente; a credencial não foi alterada.';break;
                        case 'reveal_webhook':$oneTime=$service->revealWebhook($issuer,$actor,$post['account_row']??null,(string)($post['revision']??''));$saved='Token recuperado desta conta e ambiente. Nenhum token foi alterado.';break;
                        case 'rotate':$oneTime=$service->rotateWebhook($issuer,$actor,$post['account_row']??null,(string)($post['revision']??''));$saved='Token local gerado. Cadastre o webhook manualmente no Asaas; nenhuma chamada de cadastro remoto foi executada.';break;
                        case 'disable':$service->disableAccount($issuer,$actor,$post['account_row']??null,(string)($post['revision']??''));$saved='Conta desabilitada. O token anterior foi revogado.';break;
                        case 'enable':$service->enableAccount($issuer,$actor,$post['account_row']??null,(string)($post['revision']??''));$saved='Conta habilitada para nova verificação; gere outro token após conferir a carteira.';break;
                        case 'automation':$service->configureAutomation($issuer,$actor,$post['account_row']??null,(string)($post['revision']??''),($post['enabled']??null)==='1');$saved='Política de conciliação automática atualizada para sua identidade e esta conta.';break;
                        case 'payer_register':
                            if(($post['confirm']??null)!=='1')throw new DomainException('asaas_company_confirmation_required');
                            $account=IssuerPolicy::id($post['account_row']??null);$client=IssuerPolicy::id($post['client_id']??null);(new \Asaas\CompanyPayers($this->db,db_prefix()))->register($issuer,$actor,$account,$client,(string)($post['revision']??''),hash('sha256','company-asaas-payer:'.$issuer.':'.$account.':'.$client),$decrypt);$saved='Intenção cadastral registrada. Confira a confirmação canônica; uma tentativa enviada não será repetida.';break;
                        case 'payer_recover':case 'payer_dispatch':
                            $payersService=new \Asaas\CompanyPayers($this->db,db_prefix());$payer=IssuerPolicy::id($post['payer_id']??null);
                            if($action==='payer_dispatch'){if(($post['confirm']??null)!=='1')throw new DomainException('asaas_company_confirmation_required');$payersService->dispatch($issuer,$actor,$payer,$decrypt);}else $payersService->recover($issuer,$actor,$payer,$decrypt,null,(string)($post['customer_id']??''));$saved='Estado do pagador atualizado. Consulta e retomada preservam a intenção original.';break;
                        case 'hosted_publish':
                            if(($post['confirm']??null)!=='1'||!in_array($post['enabled']??null,['0','1'],true))throw new DomainException('asaas_company_confirmation_required');
                            (new \Asaas\CompanyHostedLinks($this->db,db_prefix()))->publish($issuer,$actor,$post['origin_id']??null,$post['proof_id']??null,$post['enabled']==='1');$saved=$post['enabled']==='1'?'Checkout hospedado publicado somente ao contato com permissão financeira desta fatura.':'Checkout ocultado do portal.';break;
                        case 'remote_remove':case 'remote_dispatch':case 'remote_refresh':
                            if(($post['confirm']??null)!=='1')throw new DomainException('asaas_company_confirmation_required');
                            $remoteOperations=new \Asaas\CompanyRemoteOperations($this->db,db_prefix());$origin=IssuerPolicy::id($post['origin_id']??null);
                            $result=match($action){'remote_remove'=>$remoteOperations->remove($issuer,$actor,$origin,hash('sha256','company-asaas-delete:'.$issuer.':'.$origin),$decrypt),'remote_dispatch'=>$remoteOperations->dispatch($issuer,$actor,$origin,$decrypt),default=>$remoteOperations->refresh($issuer,$actor,$origin,$decrypt)};
                            $saved=$result['state']==='deleted'?'Exclusão da cobrança remota confirmada por consulta. A fatura local e o histórico foram preservados.':'Intenção remota registrada: '.$result['state'].'. Consulte para confirmar; uma tentativa enviada não será repetida.';break;
                        case 'refund_grant':
                            if(!in_array($post['enabled']??null,['0','1'],true))throw new DomainException('asaas_refund_invalid_input');
                            (new \Asaas\CompanyRefunds($this->db,db_prefix()))->setGrant($issuer,$actor,$post['account_row']??null,$actor,(string)($post['revision']??''),$post['enabled']==='1');$saved='Autorização explícita deste operador atualizada somente para esta conta e ambiente.';break;
                        case 'refund_prepare':
                            (new \Asaas\CompanyRefunds($this->db,db_prefix()))->prepare($issuer,$actor,$post['origin_id']??null,(string)($post['refund_amount']??''),(string)($post['reason']??''),(string)($post['request_key']??''),$decrypt);$saved='Intenção de reembolso reservada. Nenhum POST de devolução foi enviado por esta preparação.';break;
                        case 'refund_declare_full':
                            $refundService=new \Asaas\CompanyRefunds($this->db,db_prefix());$key=(string)($post['request_key']??'');$intent=$refundService->inspect($issuer,$actor,$key);if((int)$intent['origin_id']!==IssuerPolicy::id($post['origin_id']??null))throw new DomainException('asaas_refund_access_denied');$declaration=[];foreach(['external_refund_confirmed','full_cash_return_confirmed','returned_amount','date','reference','reason','independent_evidence_hash'] as $field)$declaration[$field]=$post['declaration'][$field]??null;
                            (new \app\services\InvoicePaymentWriter($this->db))->recordCompanyAsaasFullRefund($issuer,$actor,new \Asaas\ConfirmedFullRefund($this->db,db_prefix(),$key,IssuerPolicy::id($post['observation_id']??null),$declaration));$saved='Estorno integral externo declarado registrado no financeiro local com prova e confirmação independentes. Esta ação não executa devolução no Asaas nem reverte tarifas.';break;
                        case 'refund_dispatch':case 'refund_refresh':case 'refund_cancel':
                            $refundService=new \Asaas\CompanyRefunds($this->db,db_prefix());$key=(string)($post['request_key']??'');$intent=$refundService->inspect($issuer,$actor,$key);if((int)$intent['origin_id']!==IssuerPolicy::id($post['origin_id']??null))throw new DomainException('asaas_refund_access_denied');
                            if($action==='refund_cancel'){$refundService->cancelReserved($issuer,$actor,$key);$saved='Somente a intenção local ainda não enviada foi cancelada. Histórico preservado.';}
                            else{$result=$action==='refund_dispatch'?$refundService->dispatch($issuer,$actor,$key,$decrypt):$refundService->refresh($issuer,$actor,$key,$decrypt);$saved='Estado observado: '.$result['state'].'. POST enviado não será repetido. Consulta não comprova reflexo financeiro local.';}break;
                        case 'fee':if(($post['confirm']??null)!=='1')throw new DomainException('asaas_company_confirmation_required');(new \Asaas\CompanyFeeExpense($this->db,db_prefix()))->post($issuer,$actor,$post['origin_id']??null,$post['proof_id']??null,$post['category_id']??null);$saved='Tarifa registrada como despesa da empresa com evidência canônica e vínculo imutável.';break;
                        case 'issue':
                            $preferences=['selection_revision'=>$post['selection_revision']??null,'profile_revision'=>$post['profile_revision']??null,'account_revision'=>$post['account_revision']??null];
                            if(($post['billing_type']??null)==='CREDIT_CARD')$service->hostedCheckout($issuer,$actor,$post['invoice_id']??null,$post['account_row']??null,(string)($post['customer_id']??''),(string)($post['invoice_revision']??''),(string)($post['request_key']??''),$decrypt,null,1,$preferences);else $service->issue($issuer,$actor,$post['invoice_id']??null,$post['account_row']??null,(string)($post['customer_id']??''),(string)($post['billing_type']??''),(string)($post['invoice_revision']??''),(string)($post['request_key']??''),$decrypt,null,$preferences);$saved='Emissão observada. A criação da cobrança não comprova pagamento.';break;
                        case 'query':$service->query($issuer,$actor,$post['origin_id']??null,$decrypt,null,(string)($post['recovery_id']??''));$saved='Consulta canônica persistida; nenhum pagamento foi lançado por esta ação.';break;
                        case 'settle':
                            if(($post['confirm']??null)!=='1')throw new DomainException('asaas_company_confirmation_required');
                            (new \app\services\InvoicePaymentWriter($this->db))->recordCompanyProviderReceipt($issuer,$actor,new \Asaas\CompanyReceivedReceipt(IssuerPolicy::id($post['origin_id']??null),IssuerPolicy::id($post['proof_id']??null)));$saved='Recebimento nominal aplicado pelo escritor financeiro. Tarifas observadas não são despesas contabilizadas.';break;
                        default:throw new DomainException('asaas_company_invalid_input');
                    }
                }
                if($oneTime!==null||$revealedApiKey!==null){$webhookCsrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_asaas_webhook_csrf',$webhookCsrf);}
                $data=$service->overview($issuer,$actor);
            }catch(DomainException $e){$this->output->set_status_header(str_contains($e->getMessage(),'stale')?409:422);$error='Não foi possível concluir. Confira a empresa, permissões, ambiente, revisão e estado da operação. Recarregue antes de repetir.';$data=$service->overview($issuer,$actor);}
            catch(Throwable $e){$this->output->set_status_header(503);$error='Operação não confirmada. Uma operação remota incerta não deve ser repetida: consulte a cobrança para confirmar seu estado.';try{$data=$service->overview($issuer,$actor);}catch(Throwable $ignored){show_error('Integração indisponível. Confira a ativação e as migrations do módulo.',503);return;}}
            $checkoutReady=false;$payers=[];$canPayer=CompanySettingsRuntime::canResource('invoices','view')&&CompanySettingsRuntime::canResource('customers','view')&&CompanySettingsRuntime::canResource('customers','create');
            try{$service->checkoutModule();$checkoutReady=true;if($canPayer)$payers=(new \Asaas\CompanyPayers($this->db,db_prefix()))->page($issuer,$actor);}catch(DomainException $unavailable){}
            $invoice=null;$invoiceId=$this->input->get('invoice');if($invoiceId){try{$id=IssuerPolicy::id($invoiceId);$invoices=new CompanyInvoices($this->db,db_prefix());$invoice=['id'=>$id,'data'=>$invoices->document($issuer,$actor,$id)->data(),'revision'=>$invoices->revision($issuer,$actor,$id),'request_key'=>hash('sha256','company-asaas-issue:'.$issuer.':'.$id)];}catch(Throwable $ignored){show_error('Fatura indisponível nesta empresa.',403);return;}}
            $fullRefundReady=false;$refundReady=false;$refundPanel=null;$refundGrant=null;$canRefund=CompanySettingsRuntime::canResource('invoices','view')&&CompanySettingsRuntime::canResource('invoices','edit')&&CompanySettingsRuntime::canResource('payments','delete')&&CompanySettingsRuntime::canResource('settings','edit');
            try{$fullRefundReady=CompanyAsaasRefundSchema::present($this->db,db_prefix());if($fullRefundReady){$fullModule=IssuerStorage::query($this->db,'SELECT installed_version,active FROM '.db_prefix().'modules WHERE module_name=?',['asaas'])->row_array();$fullRefundReady=(int)($fullModule['active']??0)===1&&version_compare($fullModule['installed_version']??'0','1.5.3','>=');}$refundReady=\Asaas\CompanyRefundSchema::present($this->db,db_prefix());$selectedRefund=$this->input->get('refund_origin');if($selectedRefund===null&&$this->input->method(true)==='POST'&&is_string($post['action']??null)&&str_starts_with($post['action'],'refund_'))$selectedRefund=$post['origin_id']??null;
                if($refundReady&&$selectedRefund!==null){$refundService=new \Asaas\CompanyRefunds($this->db,db_prefix());$refundPanel=$refundService->page($issuer,$actor,$selectedRefund);if($canRefund){try{$refundGrant=$refundService->grantForActor($issuer,$actor,$refundPanel['source']['account_row']);}catch(DomainException $unavailable){$refundGrant=null;}}}
            }catch(DomainException $denied){show_error('Reembolso indisponível nesta empresa ou sem permissão.',403);return;}
            catch(Throwable $unavailable){$this->output->set_status_header(503);$error='Histórico de reembolso indisponível para conferência. Nenhum envio adicional deve ser iniciado.';}
            $preferences=(new \Asaas\CompanyPreferences($this->db,db_prefix()))->page($issuer,$actor);
            $base=defined('APP_BASE_URL')?(string)APP_BASE_URL:'';$indexPage=(string)$this->config->item('index_page');
            $callbackUrls=[];foreach($data['accounts'] as $account)$callbackUrls[(int)$account['id']]=\Asaas\WebhookAdminPresentation::companyCallbackUrl($base,$account['webhook_public_id'],$indexPage);
            $canWebhook=false;try{$service->authorizeWebhook($issuer,$actor);$canWebhook=true;}catch(DomainException $denied){}
            $this->load->view('admin/settings/native_asaas',$data+['translate'=>$translate,'preferences'=>$preferences,'preferences_active_tab'=>$post['environment']??null,'preferences_csrf'=>$preferencesCsrf,'revealed_api_key'=>$revealedApiKey,'api_urls'=>\Asaas\SettingsPresentation::apiUrls(),'webhook_csrf'=>$webhookCsrf,'can_webhook'=>$canWebhook,'callback_urls'=>$callbackUrls,'full_refund_ready'=>$fullRefundReady,'refund_ready'=>$refundReady,'refund_panel'=>$refundPanel,'refund_grant'=>$refundGrant,'can_refund'=>$canRefund,'refund_actor'=>$actor,'refund_csrf'=>$refundCsrf,'refund_request_key'=>bin2hex(random_bytes(32)),'checkout_ready'=>$checkoutReady,'can_payer'=>$canPayer,'payers'=>$payers,'error'=>$error,'saved'=>$saved,'one_time'=>$oneTime,'invoice'=>$invoice,'can_configure'=>CompanySettingsRuntime::canResource('settings','edit'),'can_remove'=>CompanySettingsRuntime::canResource('invoices','view')&&CompanySettingsRuntime::canResource('invoices','edit')&&CompanySettingsRuntime::canResource('invoices','delete')&&CompanySettingsRuntime::canResource('settings','edit'),'can_fee'=>CompanySettingsRuntime::canResource('expenses','view')&&CompanySettingsRuntime::canResource('expenses','create')&&CompanySettingsRuntime::canResource('payments','create'),'can_settle'=>CompanySettingsRuntime::canResource('invoices','view')&&CompanySettingsRuntime::canResource('payments','create'),'can_issue'=>CompanySettingsRuntime::canResource('invoices','edit')&&CompanySettingsRuntime::canResource('invoices','view')&&CompanySettingsRuntime::canResource('customers','view'),'context_token'=>CompanyRequestScope::token(),'action_url'=>CompanyRequestScope::adminUrl($this,'settings/asaas')]);
        }
    protected function nativeInvoiceStandaloneBilling()
        {
            if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto indisponível.',503);return;}
            require_once APPPATH.'services/CompanyStandaloneBilling.php';require_once APPPATH.'services/CompanyStandaloneTasks.php';
            $ctx=CompanyRequestScope::context();$issuer=$ctx->issuerId();$actor=$ctx->staffId();$acl=new IssuerResourceAccess($this->db,db_prefix());$this->output->set_header('Cache-Control: no-store');
            foreach([['tasks','view'],['tasks','edit'],['customers','view'],['invoices','view'],['invoices','create']] as [$feature,$cap])if(!$acl->can($issuer,$actor,$feature,$cap)){show_error('Sem permissão para faturar tarefas nesta empresa.',403);return;}
            $csrf=$this->session->userdata('company_standalone_billing_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_standalone_billing_csrf',$csrf);}
            $service=new CompanyStandaloneBilling($this->db,db_prefix());$task=null;$billing=null;$currencies=[];$tasks=[];$customers=['items'=>[],'next'=>null];$search='';$error=null;
            try{
                if($this->input->method(true)==='POST'){
                    $posted=$this->input->post('company_standalone_billing_csrf',false);if(!is_string($posted)||!hash_equals($csrf,$posted)){show_error('Recarregue antes de salvar.',403);return;}
                    $action=$this->input->post('action',false);$id=$this->input->post('task_id',false);$key=$this->input->post('request_key',false);if(!is_string($key))throw new DomainException('company_standalone_billing_invalid');
                    if($action==='prepare'){$input=$this->input->post('billing',false);if(!is_array($input)||$this->input->post('billable',false)!=='1')throw new DomainException('company_standalone_billing_invalid');if(isset($input['taxes'])&&is_array($input['taxes']))$input['taxes']=array_values(array_filter($input['taxes'],fn($t)=>!(is_array($t)&&($t['name']??null)===''&&($t['rate']??null)==='0.00')));$service->prepare($issuer,$actor,$id,$this->input->post('revision',false),$input,$key);}
                    elseif($action==='convert')$service->convert($issuer,$actor,$id,$this->input->post('origin_id',false));elseif($action==='disable')$service->disable($issuer,$actor,$id,$this->input->post('origin_id',false));else throw new DomainException('company_standalone_billing_invalid');
                    CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'invoices/standalone_billing?task='.(int)$id),'location',303);return;
                }elseif(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
                $id=$this->input->get('task',false);if($id!==null){$task=(new CompanyStandaloneTasks($this->db,db_prefix()))->get($issuer,$actor,$id);$billing=$service->status($issuer,$actor,$id);$currencies=(new CompanyInvoices($this->db,db_prefix()))->currencies($issuer,$actor);$search=$this->input->get('q',false)??'';if(!is_string($search))throw new DomainException('company_standalone_billing_invalid');$customers=$service->customers($issuer,$actor,$this->input->get('customer_after',false)??0,$search);}else{$after=$this->input->get('after',false)??0;$tasks=(new CompanyStandaloneTasks($this->db,db_prefix()))->page($issuer,$actor,$after==='0'?0:$after);}
            }catch(DomainException $e){$reason=$e->getMessage();if(in_array($reason,['company_standalone_billing_denied','company_project_access_denied','company_invoice_access_denied'],true)){show_error('Origem ou permissão indisponível nesta empresa.',403);return;}$this->output->set_status_header(in_array($reason,['company_standalone_billing_stale','company_standalone_billing_conflict','company_standalone_billing_immutable','company_sales_origin_changed'],true)?409:422);$error=match($reason){'company_standalone_billing_stale'=>'A tarefa avulsa ou seus timers mudaram. Reabra e prepare outra declaração.','company_standalone_billing_immutable'=>'A conversão reservada ou concluída preserva a tarefa e seus timers.','company_standalone_billing_not_ready'=>'Conclua a tarefa e pare todos os cronômetros antes de faturar.','company_standalone_billing_reference_denied'=>'Escolha um cliente ativo próprio desta empresa.','issuer_document_invalid_profile','issuer_document_profile_required'=>'Complete o endereço, cidade, estado, CEP e país nas configurações desta empresa antes de preparar a fatura.','company_sales_origin_changed'=>'A identidade, o cliente ou a configuração mudou. Revise a origem antes de emitir.',default=>'Confira valores com ponto decimal, moeda, datas, taxa horária e arredondamento. A tentativa não foi confirmada.'};}
            catch(Throwable $e){log_message('error','Company standalone billing unconfirmed; no contents logged.');$this->output->set_status_header(503);$error='Não foi possível confirmar a operação. Reabra para retomar o mesmo vínculo antes de tentar novamente.';}
            $this->load->view('admin/invoices/native_standalone_billing',['issuer'=>$issuer,'task'=>$task,'billing'=>$billing,'tasks'=>$tasks,'customers'=>$customers,'search'=>$search,'currencies'=>$currencies,'error'=>$error,'company_standalone_billing_csrf'=>$csrf,'request_key'=>bin2hex(random_bytes(32))]);
        }
    protected function nativeSettingsImports()
        {
            if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto de empresa indisponível.',503);return;}require_once APPPATH.'services/CompanyImports.php';$scope=CompanyRequestScope::context();$issuer=$scope->issuerId();$actor=$scope->staffId();$service=new CompanyImports($this->db,db_prefix());$acl=new IssuerResourceAccess($this->db,db_prefix());$this->output->set_header('Cache-Control: no-store');
            $csrf=$this->session->userdata('company_imports_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_imports_csrf',$csrf);}$error=null;$batch=null;$batches=[];$exportPage=['items'=>[],'next'=>null,'after'=>0];$canImport=$acl->can($issuer,$actor,'imports','manage')&&$acl->can($issuer,$actor,'expenses','create');
            try{if($this->input->method(true)==='POST'){$posted=$this->input->post('company_imports_csrf',false);if(!is_string($posted)||!hash_equals($csrf,$posted)){show_error('Recarregue o formulário antes de salvar.',403);return;}$action=$this->input->post('action',false);$key=$this->input->post('request_key',false);if(!is_string($key))throw new DomainException('company_import_invalid_input');
                    if($action==='preview'){$format=$this->input->post('format',false);$file=$_FILES['source_file']??null;if(!is_string($format)||!in_array($format,['csv','json'],true)||!is_array($file)||($file['error']??null)!==UPLOAD_ERR_OK||!is_int($file['size']??null)||$file['size']>262144||!is_string($file['name']??null)||!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name'])||strtolower(pathinfo($file['name'],PATHINFO_EXTENSION))!==$format||preg_match('/[\x00-\x1f\\\\\/]/',$file['name']))throw new DomainException('company_import_invalid_file');$bytes=file_get_contents($file['tmp_name'],false,null,0,262145);if(!is_string($bytes))throw new DomainException('company_import_invalid_file');$id=$service->preview($issuer,$actor,$format,$bytes,$key);}
                    elseif($action==='commit'){$id=$this->input->post('batch_id',false);$revision=$this->input->post('revision',false);if(!is_string($id)||!is_string($revision))throw new DomainException('company_import_invalid_input');$service->commit($issuer,$actor,$id,$revision,$key);}
                    else throw new DomainException('company_import_invalid_input');CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'settings/imports?batch='.$id),'location',303);return;
                }elseif(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
                if($this->input->get('export',false)==='expenses'){$export=$service->expenseExport($issuer,$actor,$this->input->get('after',false)??0);$this->output->set_header('X-Content-Type-Options: nosniff')->set_header('Content-Disposition: attachment; filename="despesas-empresa-'.(int)$issuer.'.csv"')->set_header('X-Next-After: '.($export['next']??''))->set_content_type('text/csv','UTF-8')->set_output($export['csv']);return;}
                if(!$acl->can($issuer,$actor,'expenses','view'))throw new DomainException('company_import_access_denied');$id=$this->input->get('batch',false);if($id!==null){if(!is_string($id))throw new DomainException('company_import_invalid_input');$batch=$service->get($issuer,$actor,$id);}$exportPage=$service->expensePage($issuer,$actor,$this->input->get('after',false)??0);if($canImport)$batches=$service->page($issuer,$actor);
            }catch(DomainException $e){$reason=$e->getMessage();if(in_array($reason,['company_import_access_denied','company_expense_denied'],true)){show_error('Lote indisponível ou ação não autorizada nesta empresa.',403);return;}$this->output->set_status_header(in_array($reason,['company_import_stale','company_import_request_conflict'],true)?409:422);$error=match($reason){'company_import_stale'=>'O preview ou uma referência mudou. Gere um novo preview antes de confirmar.','company_import_request_conflict'=>'Este envio já foi utilizado. Reabra o lote e confira o resultado antes de repetir.','company_import_expired'=>'O preview expirou. Envie novamente o arquivo para validar as referências atuais.','company_import_preview_limit'=>'Existem 10 previews válidos aguardando confirmação. Revise-os ou aguarde a expiração.','company_expense_reference_denied'=>'Uma categoria, cliente ou projeto não está autorizado nesta empresa ou o cliente não corresponde ao projeto.',default=>'Arquivo inválido. Confira formato, cabeçalho, limite de 100 linhas/256 KiB, IDs próprios e valores decimais com ponto. Fórmulas não são aceitas. Nenhuma despesa desta tentativa foi gravada.'};}
            catch(Throwable $e){log_message('error','Company expense import/export failed; no file contents or expense data logged.');$this->output->set_status_header(503);$error='Não foi possível confirmar a operação. Reabra o lote antes de repetir; a tentativa não foi parcialmente aplicada.';}
            $this->load->view('admin/settings/native_imports',['issuer'=>$issuer,'batch'=>$batch,'batches'=>$batches,'expense_page'=>$exportPage,'can_import'=>$canImport,'error'=>$error,'company_imports_csrf'=>$csrf]);
        }
    protected function nativeProjectBillingRates()
     {
      if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto indisponível.',503);return;}require_once APPPATH.'services/CompanyProjectBillingRates.php';require_once APPPATH.'services/CompanyProjects.php';$ctx=CompanyRequestScope::context();$issuer=$ctx->issuerId();$actor=$ctx->staffId();$acl=new IssuerResourceAccess($this->db,db_prefix());foreach([['projects','view'],['customers','view'],['invoices','view']] as [$f,$c])if(!$acl->can($issuer,$actor,$f,$c)){show_error('Sem permissão para consultar preços desta empresa.',403);return;}$this->output->set_header('Cache-Control: no-store');$csrf=$this->session->userdata('company_project_rates_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_project_rates_csrf',$csrf);}$project=null;$current=null;$projects=[];$error=null;$currencies=[];$s=new CompanyProjectBillingRates($this->db,db_prefix());
      try{if($this->input->method(true)==='POST'){$token=$this->input->post('company_project_rates_csrf',false);if(!is_string($token)||!hash_equals($csrf,$token)){show_error('Recarregue antes de salvar.',403);return;}$input=$this->input->post('rate',false);$key=$this->input->post('request_key',false);$id=$this->input->post('project_id',false);if(!is_array($input)||!is_string($key)||$this->input->post('confirmed',false)!=='1')throw new DomainException('company_project_rate_invalid');$s->declareHourly($issuer,$actor,$id,$this->input->post('revision',false),$input,$key);CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'projects/billing_rates?project='.(int)$id),'location',303);return;}elseif(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}$id=$this->input->get('project',false);if($id!==null){$project=(new CompanyProjects($this->db,db_prefix()))->get($issuer,$actor,$id);$current=$s->current($issuer,$actor,$id);$currencies=(new CompanyInvoices($this->db,db_prefix()))->currencies($issuer,$actor);}else$projects=(new CompanyProjects($this->db,db_prefix()))->page($issuer,$actor)['items'];}
      catch(DomainException $e){if(in_array($e->getMessage(),['company_project_rate_denied','company_project_access_denied'],true)){show_error('Origem ou permissão indisponível.',403);return;}$this->output->set_status_header(in_array($e->getMessage(),['company_project_rate_stale','company_project_rate_conflict'],true)?409:422);$error='Confira revisão, moeda, taxa e arredondamento. Preços anteriores permanecem preservados.';}
      catch(Throwable $e){log_message('error','Company project rate unconfirmed; no values logged.');$this->output->set_status_header(503);$error='Não foi possível confirmar o preço. Recarregue antes de repetir.';}
      $this->load->view('admin/projects/native_billing_rates',['issuer'=>$issuer,'project'=>$project,'projects'=>$projects,'current'=>$current,'currencies'=>$currencies,'error'=>$error,'can_edit'=>$acl->can($issuer,$actor,'projects','edit')&&$acl->can($issuer,$actor,'invoices','create'),'company_project_rates_csrf'=>$csrf,'request_key'=>bin2hex(random_bytes(32))]);
     }
    protected function nativeTaskCopies()
        {
            if(func_num_args()!==0){show_404();return;}
            if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto indisponível.',503);return;}
            require_once APPPATH.'services/CompanyTaskCopies.php';$context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();$acl=new IssuerResourceAccess($this->db,db_prefix());foreach([['tasks','view'],['tasks','create']] as [$feature,$cap])if(!$acl->can($issuer,$actor,$feature,$cap)){show_error('Sem permissão para copiar tarefas nesta empresa.',403);return;}
            $this->output->set_header('Cache-Control: no-store');$s=new CompanyTaskCopies($this->db,db_prefix());$csrf=$this->session->userdata('company_task_copies_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_task_copies_csrf',$csrf);}$kind=null;$source=null;$preview=null;$receipt=null;$error=null;$attempt=null;
            try{
                $verb=$this->input->method(true);
                if($verb==='POST'){
                    $token=$this->input->post('company_task_copies_csrf',false);if(!is_string($token)||!hash_equals($csrf,$token)){show_error('Reabra a revisão antes de copiar.',403);return;}
                    if($this->input->post('action',false)!=='copy_reviewed_task'){show_404();return;}$kind=$this->input->post('kind',false);if(!in_array($kind,['project','standalone'],true))throw new DomainException('company_task_copy_invalid');$source=IssuerPolicy::id($this->input->post('source_task_id',false));$review=$this->input->post('review',false);$key=$this->input->post('request_key',false);if(!is_array($review)||!is_string($key))throw new DomainException('company_task_copy_invalid');if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_task_copy_invalid');$attempt=$key;$result=$s->create($issuer,$actor,$kind,$source,$review,$attempt);CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'tasks/copies?receipt='.$result['request_key']),'location',303);return;
                }
                if(!in_array($verb,['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
                $candidate=$this->input->get('source',false);$key=$this->input->get('receipt',false);if(($candidate!==null&&$key!==null)||($candidate===null&&$key===null)||($candidate!==null&&!is_scalar($candidate))||($key!==null&&!is_string($key))){show_404();return;}
                if($key!==null)$receipt=$s->receipt($issuer,$actor,$key);else{$kind=$this->input->get('kind',false);if(!in_array($kind,['project','standalone'],true))throw new DomainException('company_task_copy_invalid');$source=IssuerPolicy::id($candidate);$preview=$s->preview($issuer,$actor,$kind,$source);}
            }catch(DomainException $e){
                if(in_array($e->getMessage(),['company_task_copy_denied','company_project_access_denied','company_project_reference_denied','issuer_access_denied'],true)){show_error('Cópia indisponível para esta empresa, origem ou responsável atual.',403);return;}
                $this->output->set_status_header(str_contains($e->getMessage(),'stale')||str_contains($e->getMessage(),'conflict')?409:422);$error=match($e->getMessage()){'company_task_copy_stale'=>'A tarefa ou seu projeto mudaram. Reabra a revisão.','company_task_copy_conflict'=>'Esta chave já pertence a uma tentativa diferente. Confira o recibo original.',default=>'Confira nome, datas, opções, responsáveis atuais e permissões. Nenhuma cópia foi confirmada.'};
            }catch(Throwable $e){log_message('error','Reviewed task copy unconfirmed; no source contents logged.');$this->output->set_status_header(503);$error='Não foi possível confirmar esta tentativa. Confira o recibo desta chave antes de iniciar outra cópia.';}
            $this->load->view('admin/tasks/native_copies',['issuer'=>$issuer,'kind'=>$kind,'source'=>$source,'preview'=>$preview,'receipt'=>$receipt,'error'=>$error,'attempt'=>$attempt,'request_key'=>bin2hex(random_bytes(32)),'company_task_copies_csrf'=>$csrf]);
        }
    protected function nativeProjectFixedBilling()
        {
            if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto indisponível.',503);return;}
            require_once APPPATH.'services/CompanyProjectFixedBilling.php';require_once APPPATH.'services/CompanyProjects.php';
            $ctx=CompanyRequestScope::context();$issuer=$ctx->issuerId();$actor=$ctx->staffId();$acl=new IssuerResourceAccess($this->db,db_prefix());$this->output->set_header('Cache-Control: no-store');
            foreach([['tasks','view'],['tasks','edit'],['projects','view'],['projects','edit'],['customers','view'],['invoices','view'],['invoices','create']] as [$feature,$cap])if(!$acl->can($issuer,$actor,$feature,$cap)){show_error('Sem permissão para faturar o projeto nesta empresa.',403);return;}
            $csrf=$this->session->userdata('company_project_fixed_billing_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_project_fixed_billing_csrf',$csrf);}
            $service=new CompanyProjectFixedBilling($this->db,db_prefix());$project=null;$billing=null;$currencies=[];$projects=[];$error=null;
            try{
                if($this->input->method(true)==='POST'){
                    $posted=$this->input->post('company_project_fixed_billing_csrf',false);if(!is_string($posted)||!hash_equals($csrf,$posted)){show_error('Recarregue antes de salvar.',403);return;}
                    $action=$this->input->post('action',false);$id=$this->input->post('project_id',false);$key=$this->input->post('request_key',false);if(!is_string($key))throw new DomainException('company_project_fixed_billing_invalid');
                    if($action==='prepare'){$input=$this->input->post('billing',false);if(!is_array($input)||$this->input->post('billable',false)!=='1')throw new DomainException('company_project_fixed_billing_invalid');if(isset($input['taxes'])&&is_array($input['taxes']))$input['taxes']=array_values(array_filter($input['taxes'],fn($t)=>!(is_array($t)&&($t['name']??null)===''&&($t['rate']??null)==='0.00')));$service->prepare($issuer,$actor,$id,$this->input->post('revision',false),$input,$key);}
                    elseif($action==='convert')$service->convert($issuer,$actor,$id,$this->input->post('origin_id',false));elseif($action==='disable')$service->disable($issuer,$actor,$id,$this->input->post('origin_id',false));else throw new DomainException('company_project_fixed_billing_invalid');
                    CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'projects/fixed_billing?project='.(int)$id),'location',303);return;
                }elseif(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
                $id=$this->input->get('project',false);if($id!==null){$project=(new CompanyProjects($this->db,db_prefix()))->get($issuer,$actor,$id);$billing=$service->status($issuer,$actor,$id);$currencies=(new CompanyInvoices($this->db,db_prefix()))->currencies($issuer,$actor);}else $projects=(new CompanyProjects($this->db,db_prefix()))->page($issuer,$actor)['items'];
            }catch(DomainException $e){$reason=$e->getMessage();if(in_array($reason,['company_task_billing_denied','company_project_fixed_billing_denied','company_project_access_denied','company_invoice_access_denied'],true)){show_error('Origem ou permissão indisponível nesta empresa.',403);return;}$this->output->set_status_header(in_array($reason,['company_time_billing_overlap','company_time_billing_immutable','company_project_fixed_billing_stale','company_project_fixed_billing_conflict','company_project_fixed_billing_immutable','company_project_fixed_billing_overlap','company_sales_origin_changed'],true)?409:422);$error=match($reason){'company_time_billing_overlap','company_time_billing_immutable'=>'Há horas reservadas ou já alocadas em lotes. Use os lotes e apenas os timers restantes.','company_project_fixed_billing_stale'=>'O projeto ou suas tarefas mudaram. Reabra e revise a declaração.','company_project_fixed_billing_immutable'=>'A conversão reservada ou concluída preserva a tarefa e seus timers.','company_project_fixed_billing_not_ready'=>'Conclua o projeto e todas as tarefas antes de faturar.','company_project_fixed_billing_reference_denied'=>'O projeto e o cliente próprio devem pertencer à empresa atual.','issuer_document_invalid_profile','issuer_document_profile_required'=>'Complete o endereço, cidade, estado, CEP e país nas configurações desta empresa antes de preparar a fatura.','company_sales_origin_changed'=>'A identidade, o cliente ou a configuração mudou. Revise a origem antes de emitir.',default=>'Confira valores com ponto decimal, moeda, datas, preço fixo e tributos. A tentativa não foi confirmada.'};}
            catch(Throwable $e){log_message('error','Company fixed project billing unconfirmed; no contents logged.');$this->output->set_status_header(503);$error='Não foi possível confirmar a operação. Reabra para retomar o mesmo vínculo antes de tentar novamente.';}
            $this->load->view('admin/projects/native_fixed_billing',['issuer'=>$issuer,'project'=>$project,'billing'=>$billing,'projects'=>$projects,'currencies'=>$currencies,'error'=>$error,'company_project_fixed_billing_csrf'=>$csrf,'request_key'=>bin2hex(random_bytes(32))]);
        }
    protected function nativeTaskRecurring()
        {
            if (!CompanyRequestScope::sealedFor($this)) { show_error('Contexto indisponível.', 503); return; }
            require_once APPPATH.'services/CompanyTaskRecurring.php';
            $context = CompanyRequestScope::context();
            $issuer = $context->issuerId();
            $actor = $context->staffId();
            $acl = new IssuerResourceAccess($this->db, db_prefix());
            $this->output->set_header('Cache-Control: no-store');
            $verb = $this->input->method(true);
            if (!in_array($verb, ['GET','HEAD','POST'], true)) { show_error('Método não permitido.', 405); return; }
            $kind = $verb === 'POST' ? $this->input->post('kind', false) : ($this->input->get('kind', false) ?? 'standalone');
            if (!is_string($kind) || !in_array($kind, ['project','standalone'], true)) { show_error('Modalidade inválida.', 422); return; }
            if (!$acl->can($issuer, $actor, 'tasks', 'view') || ($kind === 'project' && !$acl->can($issuer, $actor, 'projects', 'view'))) { show_error('Agenda indisponível nesta empresa.', 403); return; }
            $csrf = $this->session->userdata('company_task_recurring_csrf');
            if (!is_string($csrf) || !preg_match('/^[a-f0-9]{64}$/D', $csrf)) {
                $csrf = bin2hex(random_bytes(32));
                $this->session->set_userdata('company_task_recurring_csrf', $csrf);
            }
            $service = new CompanyTaskRecurring($this->db, db_prefix());
            $source = null; $detail = null; $directory = ['items'=>[], 'next'=>null]; $error = null;
            $canEdit = $acl->can($issuer, $actor, 'tasks', 'edit');
            $canCreate = $canEdit && $acl->can($issuer, $actor, 'tasks', 'create');
            try {
                if ($verb === 'POST') {
                    $token = $this->input->post('company_task_recurring_csrf', false);
                    if (!is_string($token) || !hash_equals($csrf, $token)) { show_error('Recarregue antes de salvar.', 403); return; }
                    $action = $this->input->post('action', false);
                    $key = $this->input->post('request_key', false);
                    if (!is_string($key) || !preg_match('/^[a-f0-9]{64}$/D', $key)) { throw new DomainException('company_task_recurring_invalid'); }
                    if ($action === 'create') {
                        $calendar = $this->input->post('calendar', false);
                        if (!$canCreate) { throw new DomainException('company_task_recurring_access_denied'); }
                        if (!is_array($calendar)) { throw new DomainException('company_task_recurring_invalid'); }
                        $id = $service->create($issuer, $actor, $kind, $this->input->post('source_id', false), $this->input->post('source_revision', false), $calendar, $key);
                    } elseif (in_array($action, ['pause','resume','cancel'], true)) {
                        if (!$canEdit) { throw new DomainException('company_task_recurring_access_denied'); }
                        $id = $service->transition($issuer, $actor, $kind, $this->input->post('schedule_id', false), $this->input->post('revision', false), $action, $key);
                    } elseif ($action === 'run') {
                        if (!$canCreate) { throw new DomainException('company_task_recurring_access_denied'); }
                        $id = IssuerPolicy::id($this->input->post('schedule_id', false));
                        $cycle = $this->input->post('cycle', false);
                        if (!is_string($cycle) || !preg_match('/^(0|[1-9][0-9]{0,2})$/D', $cycle) || (int)$cycle > 119) { throw new DomainException('company_task_recurring_invalid'); }
                        // Attest kind before the writer derives its immutable origin.
                        $service->get($issuer, $actor, $kind, $id);
                        $service->run($issuer, $actor, $id, null, (int)$cycle);
                    } else { throw new DomainException('company_task_recurring_invalid'); }
                    CompanyRequestScope::invalidateCaches($this);
                    redirect(CompanyRequestScope::adminUrl($this, 'tasks/recurring?kind='.$kind.'&schedule='.(int)$id), 'location', 303);
                    return;
                }
                $schedule = $this->input->get('schedule', false);
                if ($schedule !== null) { $detail = $service->get($issuer, $actor, $kind, $schedule); }
                $sourceId = $detail === null ? $this->input->get('source', false) : null;
                if ($sourceId !== null) {
                    $sourceId = IssuerPolicy::id($sourceId);
                    $reader = $kind === 'project' ? new CompanyTasks($this->db, db_prefix()) : new CompanyStandaloneTasks($this->db, db_prefix());
                    $source = $reader->get($issuer, $actor, $sourceId);
                }
                $directory = $service->page($issuer, $actor, $kind, $this->input->get('after', false) ?? 0, $sourceId);
            } catch (DomainException $exception) {
                if (str_contains($exception->getMessage(), 'denied')) { show_error('Agenda ou referência indisponível nesta empresa.', 403); return; }
                $this->output->set_status_header(str_contains($exception->getMessage(), 'stale') || str_contains($exception->getMessage(), 'conflict') ? 409 : 422);
                $error = match ($exception->getMessage()) {
                    'company_task_recurring_stale'=>'A tarefa ou a agenda mudou. Reabra a página e confira a revisão atual.',
                    'company_task_recurring_limit'=>'O limite desta agenda ou da empresa foi atingido. Consulte o histórico antes de continuar.',
                    'company_task_recurring_state'=>'Esta ação não está disponível no estado atual da agenda.',
                    default=>'Confira o calendário, o responsável e as referências. Nenhuma alteração desta tentativa foi confirmada.',
                };
            } catch (Throwable $exception) {
                log_message('error', 'Task recurrence operation unconfirmed; task contents and origin credentials omitted.');
                $this->output->set_status_header(503);
                $error = 'Não foi possível confirmar a tentativa. Reabra a agenda e confira os ciclos registrados antes de tentar novamente.';
            }
            $this->load->view('admin/tasks/native_recurring', [
                'issuer'=>$issuer, 'actor'=>$actor, 'kind'=>$kind, 'source'=>$source, 'detail'=>$detail, 'directory'=>$directory,
                'error'=>$error, 'can_create'=>$canCreate, 'can_edit'=>$canEdit, 'can_project'=>$acl->can($issuer, $actor, 'projects', 'view'),
                'company_task_recurring_csrf'=>$csrf, 'request_key'=>bin2hex(random_bytes(32)),
            ]);
        }
    protected function nativeTicketConnectApi()
        {
            if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto de empresa indisponível.',503);return;}require_once APPPATH.'services/CompanyInvoiceConnectNotification.php';require_once APPPATH.'services/CompanyConnectInstanceOperations.php';require_once APPPATH.'services/CompanyConnectPairing.php';$ctx=CompanyRequestScope::context();$issuer=$ctx->issuerId();$actor=$ctx->staffId();$config=new CompanyConnectApi($this->db,db_prefix());$service=new CompanyInvoiceConnectNotification($this->db,db_prefix());$this->output->set_header('Cache-Control: no-store')->set_header('Pragma: no-cache')->set_header('Referrer-Policy: no-referrer');$url=CompanyRequestScope::adminUrl($this,'tickets/connect_api');$csrf=$this->session->userdata('company_connect_api_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_connect_api_csrf',$csrf);}$qrCredential=null;$error=null;$invoice=0;$after=0;$afterValue=$this->input->get('after',false);if($afterValue!==null){if(!is_string($afterValue)||!preg_match('/^[1-9][0-9]{0,8}$/D',$afterValue)){show_error('Página inválida.',422);return;}$after=(int)$afterValue;}$query=$this->input->get('invoice',false);if($query!==null){if(!is_string($query)||!preg_match('/^[1-9][0-9]{0,8}$/D',$query)){show_error('Documento inválido.',422);return;}$invoice=(int)$query;}
            try{$profile=$config->page($issuer,$actor);$this->load->library('encryption');$encrypt=fn($v)=>$this->encryption->encrypt($v);$decrypt=fn($v)=>$this->encryption->decrypt($v);$method=$this->input->method(true);if($method==='POST'){$posted=$this->input->post('company_connect_api_csrf',false);if(!is_string($posted)||!hash_equals($csrf,$posted)){show_error('Reabra a página antes de continuar.',403);return;}$action=$this->input->post('action',false);$revision=$this->input->post('revision',false);if(!is_string($revision)||!preg_match('/^(0|[1-9][0-9]{0,8})$/D',$revision))throw new DomainException('company_connect_api_invalid');
                    if($action==='save'){$endpoint=$this->input->post('endpoint_id',false);$instance=$this->input->post('instance_name',false);$token=$this->input->post('instance_token',false);if(!is_string($endpoint)||!is_string($instance)||!is_string($token))throw new DomainException('company_connect_api_invalid');$config->save($issuer,$actor,(int)$revision,$endpoint,$instance,$token,$encrypt);}
                    elseif($action==='check')$config->check($issuer,$actor,(int)$revision,$decrypt);elseif($action==='revoke')$config->revoke($issuer,$actor,(int)$revision);
                    elseif(in_array($action,['reserve_pairing','request_pairing','reveal_pairing','complete_pairing','observe_pairing'],true)){if((int)$revision!==$profile['revision'])throw new DomainException('company_connect_api_stale');$key=$this->input->post('request_key',false);if(!is_string($key))throw new DomainException('company_connect_api_invalid');$pairing=new CompanyConnectPairing($this->db,db_prefix());if($action==='reserve_pairing'){$number=$this->input->post('expected_number',false);if(!is_string($number))throw new DomainException('company_connect_api_invalid');$pairing->reserve($issuer,$actor,(int)$revision,$key,$number,$encrypt,$decrypt);}elseif($action==='request_pairing')$pairing->request($issuer,$actor,$key,$encrypt,$decrypt);elseif($action==='reveal_pairing')$qrCredential=$pairing->reveal($issuer,$actor,$key,$decrypt);elseif($action==='complete_pairing')$pairing->complete($issuer,$actor,$key,$decrypt);else$pairing->observeExpired($issuer,$actor,$key,$decrypt);}
                    elseif(in_array($action,['reserve_restart','execute_restart','observe_restart'],true)){if((int)$revision!==$profile['revision'])throw new DomainException('company_connect_api_stale');$key=$this->input->post('request_key',false);if(!is_string($key))throw new DomainException('company_connect_api_invalid');$operations=new CompanyConnectInstanceOperations($this->db,db_prefix());if($action==='reserve_restart')$operations->reserve($issuer,$actor,(int)$revision,$key);elseif($action==='execute_restart')$operations->restart($issuer,$actor,(int)$revision,$key,$decrypt);else$operations->observe($issuer,$actor,$key,$decrypt);}
                    elseif($action==='phone'){if(!$invoice||(int)$revision!==$profile['revision'])throw new DomainException('company_connect_api_stale');$contact=$this->input->post('contact_id',false);$phoneRevision=$this->input->post('phone_revision',false);$phone=$this->input->post('phone',false);if(!is_string($contact)||!preg_match('/^[1-9][0-9]{0,8}$/D',$contact)||!is_string($phoneRevision)||!is_string($phone))throw new DomainException('company_connect_api_invalid');$service->changePhone($issuer,$actor,$invoice,(int)$contact,$phoneRevision,$phone);}
                    elseif(in_array($action,['queue','dispatch'],true)){if((int)$revision!==$profile['revision'])throw new DomainException('company_connect_api_stale');$key=$this->input->post('request_key',false);if(!is_string($key))throw new DomainException('company_connect_api_invalid');if($action==='queue'){$contact=$this->input->post('contact_id',false);$documentRevision=$this->input->post('invoice_revision',false);if(!$invoice||!is_string($contact)||!preg_match('/^[1-9][0-9]{0,8}$/D',$contact)||!is_string($documentRevision))throw new DomainException('company_connect_api_invalid');$service->queue($issuer,$actor,$invoice,(int)$contact,$documentRevision,$key,$this->config->base_url(),$encrypt);}else$service->dispatch($issuer,$actor,$key,$decrypt);}
                    else throw new DomainException('company_connect_api_invalid');CompanyRequestScope::invalidateCaches($this);if($qrCredential===null){redirect($url.($invoice?'?invoice='.$invoice:''),'location',303);return;}
                }elseif(!in_array($method,['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
            }catch(DomainException$e){$status=match($e->getMessage()){'company_connect_api_denied'=>403,'company_connect_api_stale','company_connect_api_conflict','company_connect_api_identity_changed','company_connect_pairing_expired','company_connect_pairing_already_open'=>409,default=>422};$this->output->set_status_header($status);$error=$status===403?'Sua permissão mudou. Reabra a empresa.':($status===409?'A configuração ou a solicitação mudou. Reabra a página.':'Confira o perfil e a instância conectada. Nenhuma entrega foi confirmada.');}catch(Throwable$e){$this->output->set_status_header(503);$error='Operação não confirmada. Consulte a intenção antes de solicitar novo envio.';}
            try{$profile=$config->page($issuer,$actor);$acl=new IssuerResourceAccess($this->db,db_prefix());$canConfigure=$acl->can($issuer,$actor,'settings','edit')&&$acl->can($issuer,$actor,'connect-api-connector','configure');$canPair=$acl->can($issuer,$actor,'settings','edit')&&$acl->can($issuer,$actor,'connect-api-connector','configure')&&$acl->can($issuer,$actor,'connect-api-connector','reconnect');$pairAttempts=$canPair?(new CompanyConnectPairing($this->db,db_prefix()))->page($issuer,$actor):[];$canReconnect=$acl->can($issuer,$actor,'settings','edit')&&$acl->can($issuer,$actor,'connect-api-connector','reconnect');$operations=$canReconnect?(new CompanyConnectInstanceOperations($this->db,db_prefix()))->page($issuer,$actor):[];$canSend=$acl->can($issuer,$actor,'connect-api-connector','send')&&$acl->can($issuer,$actor,'invoices','view')&&$acl->can($issuer,$actor,'invoices','edit')&&$acl->can($issuer,$actor,'customers','view');$invoicePage=$canSend?(new CompanyInvoices($this->db,db_prefix()))->page($issuer,$actor,$after):['items'=>[],'next'=>null];$documents=$invoicePage['items'];$document=$invoice&&$canSend?$service->page($issuer,$actor,$invoice):null;$this->load->view('admin/tickets/native_connect_api',['profile'=>$profile,'csrf'=>$csrf,'error'=>$error,'endpoints'=>$config->choices(),'can_configure'=>$canConfigure,'can_pair'=>$canPair,'pair_attempts'=>$pairAttempts,'qr_credential'=>$qrCredential,'can_reconnect'=>$canReconnect,'operations'=>$operations,'can_send'=>$canSend,'can_edit_phone'=>$acl->can($issuer,$actor,'customers','edit'),'documents'=>$documents,'next_invoice'=>$invoicePage['next'],'document'=>$document,'invoice'=>$invoice]);}catch(DomainException$e){show_error('Sem acesso a este documento ou configuração.',403);}catch(Throwable$e){show_error('Configuração indisponível. Confira a atualização do núcleo.',503);}
        }
    protected function nativeTaskTimeBatches()
        {
            if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto indisponível.',503);return;}require_once APPPATH.'services/CompanyTaskTimeBatch.php';$context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();$acl=new IssuerResourceAccess($this->db,db_prefix());$this->output->set_header('Cache-Control: no-store');foreach([['tasks','view'],['tasks','edit'],['customers','view'],['invoices','view'],['invoices','create']] as[$f,$c])if(!$acl->can($issuer,$actor,$f,$c)){show_error('Sem permissão para faturar horas nesta empresa.',403);return;}
            $csrf=$this->session->userdata('company_time_batches_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_time_batches_csrf',$csrf);}$service=new CompanyTaskTimeBatch($this->db,db_prefix());$billing=null;$choices=null;$customers=[];$currencies=[];$error=null;$kind='standalone';
            try{
                if($this->input->method(true)==='POST'){$token=$this->input->post('company_time_batches_csrf',false);if(!is_string($token)||!hash_equals($csrf,$token)){show_error('Recarregue antes de salvar.',403);return;}$key=$this->input->post('request_key',false);if(!is_string($key))throw new DomainException('company_time_batch_invalid');CompanySalesBase::key($key);$action=$this->input->post('action',false);
                    if($action==='prepare'){$input=$this->input->post('billing',false);if(!is_array($input)||$this->input->post('billable',false)!=='1'||!is_array($input['timers']??null)||!array_is_list($input['timers'])||count($input['timers'])>500)throw new DomainException('company_time_batch_invalid');$selected=[];foreach($input['timers'] as$code){if(!is_string($code)||!preg_match('/^([1-9][0-9]*):([1-9][0-9]*):(0|[1-9][0-9]*)$/D',$code,$m))throw new DomainException('company_time_batch_invalid');$selected[]=['id'=>$m[1],'task_revision'=>$m[2],'project_revision'=>$m[3]];}$input['timers']=$selected;
                        $source=$input['rate_source']??'explicit';if($source==='project')unset($input['rate'],$input['round_mode'],$input['round_minutes'],$input['prices']);elseif($source==='per_task'){$input['rate_source']='explicit';unset($input['rate'],$input['round_mode'],$input['round_minutes'],$input['project_rates']);foreach($input['prices']??[] as$id=>$price)if(is_array($price)&&($price['rate']??'')==='')unset($input['prices'][$id]);}else unset($input['prices'],$input['project_rates']);if(isset($input['taxes'])&&is_array($input['taxes']))$input['taxes']=array_values(array_filter($input['taxes'],fn($tax)=>!(is_array($tax)&&($tax['name']??null)===''&&($tax['rate']??null)==='0.00')));$origin=$service->prepare($issuer,$actor,$input,$key);
                    }elseif(in_array($action,['convert','disable'],true)){$origin=$this->input->post('origin_id',false);if($action==='convert')$service->convert($issuer,$actor,$origin);else$service->disable($issuer,$actor,$origin);}else throw new DomainException('company_time_batch_invalid');CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'tasks/time_batches?origin='.(int)$origin),'location',303);return;
                }elseif(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
                $origin=$this->input->get('origin',false);$kind=$this->input->get('kind',false)??'standalone';$client=$this->input->get('client',false);if($origin!==null){$billing=$service->status($issuer,$actor,$origin);$kind=$billing['source_kind'];$client=$billing['client_id'];}if(!is_string($kind)||!in_array($kind,['projects','standalone'],true))throw new DomainException('company_time_batch_invalid');
                if($client!==null&&(!$billing||$billing['can_prepare_more'])){$choices=$service->choices($issuer,$actor,$kind,$client,$this->input->get('timer_after',false)??0);$currencies=(new CompanyInvoices($this->db,db_prefix()))->currencies($issuer,$actor);}elseif(!$billing){$search=$this->input->get('search',false)??'';if(!is_string($search))throw new DomainException('company_time_batch_invalid');$customers=$service->customers($issuer,$actor,$this->input->get('after',false)??0,$search);}
            }catch(DomainException $e){$reason=$e->getMessage();if(in_array($reason,['company_time_batch_denied','company_time_batch_reference_denied','company_task_billing_denied','company_standalone_billing_denied','company_invoice_access_denied'],true)){show_error('Origem ou permissão indisponível nesta empresa.',403);return;}$conflict=str_ends_with($reason,'_conflict')||str_ends_with($reason,'_stale')||str_ends_with($reason,'_immutable')||str_ends_with($reason,'_overlap')||$reason==='company_sales_origin_changed';$this->output->set_status_header($conflict?409:422);$error=match($reason){'company_time_batch_overlap','company_time_billing_overlap'=>'Um timer já está reservado ou utilizado. As horas preservadas não serão cobradas novamente.','company_time_batch_immutable'=>'Uma reserva está pendente ou já possui recibo. Retome a mesma emissão.','company_time_batch_target_mismatch'=>'Os timers devem pertencer à mesma empresa, cliente e moeda preservados. Avulsa com histórico conserva seu cliente e moeda.','company_time_batch_stale'=>'A tarefa ou o timer mudou desde a seleção. Reabra e revise.','company_time_batch_conflict'=>'Outro envio ou operador reservou esta origem. Reabra o lote.','company_sales_origin_changed'=>'A identidade fiscal, cliente ou configuração mudou. Revise antes de emitir.',default=>'Confira timers encerrados, preços, moeda, datas, arredondamento e valores decimais. Nenhuma emissão foi confirmada nesta tentativa.'};}
            catch(Throwable $e){log_message('error','Company multi-source time billing unconfirmed; input and contents omitted.');$this->output->set_status_header(503);$error='Não foi possível confirmar. Reabra e retome o mesmo lote.';}
            $this->load->view('admin/tasks/native_time_batches',['issuer'=>$issuer,'kind'=>$kind,'billing'=>$billing,'choices'=>$choices,'customers'=>$customers,'currencies'=>$currencies,'error'=>$error,'can_projects'=>$acl->can($issuer,$actor,'projects','view'),'company_time_batches_csrf'=>$csrf,'request_key'=>bin2hex(random_bytes(32))]);
        }
    protected function nativeExpensePurchases()
        {
            if (!CompanyRequestScope::sealedFor($this)) { show_error('Contexto de empresa indisponível.',503); return; }
            require_once APPPATH.'services/CompanyPurchases.php'; require_once APPPATH.'services/CompanyOperationFilesHttp.php';
            $context=CompanyRequestScope::context(); $issuer=$context->issuerId(); $actor=$context->staffId(); $service=new CompanyPurchases($this->db,db_prefix()); $acl=new IssuerResourceAccess($this->db,db_prefix());
            $this->output->set_header('Cache-Control: no-store');
            if (!$acl->can($issuer,$actor,'purchases','view')) { show_error('Sem acesso às compras desta empresa.',403); return; }
            $csrf=$this->session->userdata('company_purchases_csrf');
            if (!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)) { $csrf=bin2hex(random_bytes(32)); $this->session->set_userdata('company_purchases_csrf',$csrf); }
            $caps=[]; foreach (['view','create','edit','approve','receive','pay','portal','report','settings'] as $cap) { $caps[$cap]=$acl->can($issuer,$actor,'purchases',$cap); }
            $tab=$this->input->get('tab',false)??'requests'; $error=null; $workspace=null; $page=['items'=>[],'next'=>null]; $settings=null; $categories=[]; $files=[]; $invites=[]; $report=null; $inviteSecret=null; $editSupplier=null;
            try {
                if (!is_string($tab)||!in_array($tab,['suppliers','requests','orders','payables','report','settings'],true)) { throw new DomainException('company_purchase_invalid'); }
                $verb=$this->input->method(true);
                if ($verb==='POST') {
                    $posted=$this->input->post('company_purchases_csrf',false);
                    if (!is_string($posted)||!hash_equals($csrf,$posted)) { show_error('Recarregue o formulário antes de salvar.',403); return; }
                    $action=$this->input->post('action',false); $key=$this->input->post('request_key',false); $input=$this->input->post('purchase',false)??[]; $id=$this->input->post('object_id',false); $revision=$this->input->post('revision',false);
                    if (!is_string($action)||!is_string($key)||!is_array($input)) { throw new DomainException('company_purchase_invalid'); }
                    $request=$this->input->post('request_id',false); $destination='?tab='.$tab;
                    switch ($action) {
                        case 'configure': if (is_array($input['rates']??null)) { $input['rates']=array_filter($input['rates'],static fn($v)=>$v!==''); } $service->configure($issuer,$actor,$revision===null||$revision===''?null:CompanyPurchasesValues::integer($revision,1),$input,$key); break;
                        case 'supplier': $result=$service->saveSupplier($issuer,$actor,$id===null||$id===''?null:IssuerPolicy::id($id),$revision===null||$revision===''?null:CompanyPurchasesValues::integer($revision,1),$input,$key); $destination='?tab=suppliers&supplier='.$result; break;
                        case 'request': $request=$service->createRequest($issuer,$actor,$input,$key); break;
                        case 'approve': $request=$service->approveRequest($issuer,$actor,$id,$revision,$key); break;
                        case 'cancel_request': $request=$service->cancel($issuer,$actor,'request',$id,$revision,$key); break;
                        case 'quote': $service->quote($issuer,$actor,$input,$key); break;
                        case 'order': $service->createOrder($issuer,$actor,$input,$key); break;
                        case 'cancel_order': $service->cancel($issuer,$actor,'order',$id,$revision,$key); break;
                        case 'receive': if (is_array($input['lines']??null)) { $input['lines']=array_values(array_filter($input['lines'],static fn($v)=>!is_array($v)||($v['quantity']??null)!=='')); } $service->receive($issuer,$actor,$id,$revision,$input,$key); break;
                        case 'document': $service->postDocument($issuer,$actor,$input,$key); break;
                        case 'settle': if (!is_string($revision)) { throw new DomainException('company_purchase_invalid'); } $service->settle($issuer,$actor,$id,$revision,$input,$key); break;
                        case 'reverse': if (!is_string($revision)) { throw new DomainException('company_purchase_invalid'); } $service->reverseSettlement($issuer,$actor,$id,$revision,$input,$key); break;
                        case 'invite': CompanyPurchasesValues::fields($input,['supplier_id','days']); $result=$service->issueInvite($issuer,$actor,$request,$input['supplier_id']??null,CompanyPurchasesValues::integer($input['days']??null,1,30),$key); $inviteSecret=$result['secret']; $tab='requests'; break;
                        case 'revoke_invite': $service->revokeInvite($issuer,$actor,$id,$revision,$key); break;
                        case 'upload': [$name,$bytes]=CompanyOperationFilesHttp::upload(); CompanyPurchasesValues::fields($input,['supplier_id','portal_visible']); $supplier=empty($input['supplier_id'])?null:IssuerPolicy::id($input['supplier_id']); $service->upload($issuer,$actor,$request,$supplier,$name,$bytes,($input['portal_visible']??'0')==='1',$key); break;
                        case 'hide_file': $service->hideFile($issuer,$actor,$id,$revision,$key); break;
                        default: throw new DomainException('company_purchase_invalid');
                    }
                    if ($request!==null&&$request!=='') { $request=IssuerPolicy::id($request); $destination='?tab=requests&request='.$request; }
                    CompanyRequestScope::invalidateCaches($this);
                    if ($action!=='invite') { redirect(CompanyRequestScope::adminUrl($this,'expenses/purchases'.$destination),'location',303); return; }
                    // The invitation secret is shown once in this successful POST, never in session flash or a query string.
                } elseif (!in_array($verb,['GET','HEAD'],true)) { show_error('Método não permitido.',405); return; }
                else { $request=$this->input->get('request',false); }
                $download=$this->input->get('download',false);
                if ($download!==null) { $file=$service->download($issuer,$actor,$download); CompanyOperationFilesHttp::respond($this,$file); return; }
                $pdf=$this->input->get('order_pdf',false);
                if ($pdf!==null) { $bytes=$service->orderPdf($issuer,$actor,$pdf); $this->output->set_header('Content-Disposition: attachment; filename="pedido.pdf"')->set_content_type('application/pdf')->set_output($verb==='HEAD'?'':$bytes); return; }
                $settings=$service->configuration($issuer,$actor);
                if ($tab==='report') {
                    $from=$this->input->get('from',false)??gmdate('Y-m-01'); $asOf=$this->input->get('as_of',false)??gmdate('Y-m-d');
                    if (!is_string($from)||!is_string($asOf)) { throw new DomainException('company_purchase_invalid'); }
                    if ($this->input->get('format',false)==='csv') { $csv=$service->export($issuer,$actor,$from,$asOf); $this->output->set_header('Content-Disposition: attachment; filename="contas-a-pagar.csv"')->set_content_type('text/csv','UTF-8')->set_output($verb==='HEAD'?'':$csv); return; }
                    $report=$service->report($issuer,$actor,$from,$asOf,CompanyPurchasesValues::integer($this->input->get('after',false)??0));
                } elseif ($tab!=='settings') {
                    $kind=['suppliers'=>'supplier','requests'=>'request','orders'=>'order','payables'=>'payable'][$tab]; $page=$service->page($issuer,$actor,$kind,CompanyPurchasesValues::integer($this->input->get('after',false)??0),50);
                }
                $supplier=$this->input->get('supplier',false); if ($supplier!==null) { $editSupplier=$service->get($issuer,$actor,'supplier',$supplier); }
                if ($request!==null&&$request!=='') {
                    $request=IssuerPolicy::id($request); $workspace=$service->workspace($issuer,$actor,$request); $files=$service->files($issuer,$actor,$request);
                    if ($caps['portal']) { $invites=$service->invites($issuer,$actor,$request); }
                    if ($acl->can($issuer,$actor,'expenses','view')) { $categories=(new CompanyExpenses($this->db,db_prefix()))->categories($issuer,$actor); }
                }
            } catch (DomainException|InvalidArgumentException $e) {
                $reason=$e->getMessage(); $this->output->set_status_header(str_ends_with($reason,'denied')?403:((str_contains($reason,'stale')||str_contains($reason,'conflict'))?409:422));
                $error=match($reason) {
                    'company_purchase_settings_required'=>'Configure primeiro a numeração e as taxas de câmbio explícitas desta empresa.',
                    'company_purchase_stale'=>'O registro mudou. Reabra a página antes de confirmar outra operação.',
                    'company_purchase_denied','company_purchase_reference_denied'=>'Registro ou permissão indisponível nesta empresa.',
                    'company_purchase_receipt_exceeds_order'=>'A quantidade ultrapassa o saldo ainda não recebido do pedido.',
                    'company_purchase_receipt_already_billed'=>'Um recebimento selecionado já pertence a um documento fechado.',
                    'company_purchase_settlement_exceeds_balance'=>'O valor ultrapassa o saldo a pagar ou a data antecede o documento.',
                    'company_purchase_evidence_immutable'=>'O arquivo comprova um documento ou uma declaração e deve permanecer preservado.',
                    'company_purchase_exchange_required'=>'Informe uma taxa de câmbio para a moeda da cotação antes de aprovar o pedido.',
                    'company_purchase_export_limit'=>'O período excede 5.000 títulos. Reduza o período para exportar um conjunto completo.',
                    'company_expense_denied'=>'Fechar um documento também exige consultar e criar despesas nesta empresa.',
                    default=>'Confira os dados, as quantidades e o estado atual. Nenhuma confirmação parcial é aceita.'
                };
            } catch (Throwable $e) {
                log_message('error','Company purchases operation unconfirmed; private data and secrets omitted.'); $this->output->set_status_header(503); $error='Não foi possível confirmar a operação. Reabra a página para verificar o estado antes de tentar novamente.';
            }
            $this->load->view('admin/expenses/native_purchases',['issuer'=>$issuer,'actor'=>$actor,'tab'=>$tab,'page'=>$page,'workspace'=>$workspace,'settings'=>$settings,'categories'=>$categories,'files'=>$files,'invites'=>$invites,'report'=>$report,'inviteSecret'=>$inviteSecret,'editSupplier'=>$editSupplier,'error'=>$error,'capabilities'=>$caps,'company_purchases_csrf'=>$csrf]);
        }
    protected function nativeTaskStandalone()
        {
            if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto indisponível.',503);return;}
            require_once APPPATH.'services/CompanyStandaloneTasks.php';
            $ctx=CompanyRequestScope::context();$issuer=$ctx->issuerId();$actor=$ctx->staffId();$acl=new IssuerResourceAccess($this->db,db_prefix());
            if(!$acl->can($issuer,$actor,'tasks','view')){show_error('Sem permissão para tarefas desta empresa.',403);return;}
            $this->output->set_header('Cache-Control: no-store');$csrf=$this->session->userdata('company_standalone_tasks_csrf');if(!is_string($csrf)||strlen($csrf)!==64){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_standalone_tasks_csrf',$csrf);}
            $creation_options=null;$creation_defaults=null;$s=new CompanyStandaloneTasks($this->db,db_prefix());$error=null;$task=null;$directory=['items'=>[],'next'=>null];$staff=[];
            try{
                if($this->input->method(true)==='POST'){
                    $token=$this->input->post('company_standalone_tasks_csrf',false);if(!is_string($token)||!hash_equals($csrf,$token)){show_error('Recarregue antes de salvar.',403);return;}
                    $action=$this->input->post('action',false);$key=$this->input->post('request_key',false);if(!is_string($key))throw new DomainException('company_project_invalid_input');
                    if(in_array($action,['create','update'],true)){
                        $input=$this->input->post('task',false);if(!is_array($input))throw new DomainException('company_project_invalid_input');$lines=$this->input->post('checklist_lines',false)??'';if(!is_string($lines)||strlen($lines)>6000)throw new DomainException('company_project_invalid_input');$input['checklist']=$input['checklist']??[];if(!is_array($input['checklist']))throw new DomainException('company_project_invalid_input');$input['checklist']=array_values($input['checklist']);foreach(preg_split('/\r\n|\r|\n/',$lines) as $line)if(trim($line)!=='')$input['checklist'][]=['text'=>$line,'done'=>'0'];
                        if($action==='create')$id=$s->create($issuer,$actor,$input,$key);else $id=$s->update($issuer,$actor,$this->input->post('task_id',false),$this->input->post('revision',false),$input,$key);
                    }elseif(in_array($action,['comment','start','stop'],true)){$body=$this->input->post('comment',false)??'';if(!is_string($body))throw new DomainException('company_project_invalid_input');$id=$s->activity($issuer,$actor,$this->input->post('task_id',false),$this->input->post('revision',false),$action,$key,$body);}
                    else throw new DomainException('company_project_invalid_input');
                    CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'tasks/standalone?task='.(int)$id),'location',303);return;
                }elseif(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
                $id=$this->input->get('task',false);if($id!==null)$task=$s->get($issuer,$actor,$id);$directory=$s->page($issuer,$actor,$this->input->get('after',false)??0);$staff=$s->staffOptions($issuer,$actor);if($acl->can($issuer,$actor,'tasks','create')){$creation_defaults=$s->creationDefaults($issuer,$actor);$creation_options=$s->priorityDefaults($issuer,$actor);}
            }catch(DomainException $e){$this->output->set_status_header(409);$error=match($e->getMessage()){'company_project_stale'=>'A tarefa mudou. Reabra antes de salvar.','company_project_closed'=>'A tarefa está concluída. Reabra-a para registrar nova atividade.','company_project_timer_running'=>'Já existe cronômetro em execução. Pare o cronômetro antes de iniciar outro ou concluir.','company_project_timer_missing'=>'Nenhum cronômetro seu está em execução nesta tarefa.','company_project_checklist_open'=>'Conclua todos os itens do checklist antes de finalizar.',default=>'Dados, referências ou permissões inválidos. Nenhuma alteração foi confirmada.'};}
            catch(Throwable $e){log_message('error','Company task operation unconfirmed; no contents logged.');$this->output->set_status_header(503);$error='Não foi possível confirmar a operação. Reabra antes de tentar novamente.';}
            $this->load->view('admin/tasks/native_standalone',['issuer'=>$issuer,'actor'=>$actor,'task'=>$task,'directory'=>$directory,'staff_options'=>$staff,'creation_defaults'=>$creation_defaults,'creation_options'=>$creation_options,'priority_available'=>CompanyTaskPrioritySchema::tables($this->db,db_prefix())!==[],'error'=>$error,'company_standalone_tasks_csrf'=>$csrf,'request_key'=>bin2hex(random_bytes(32)),'can_create'=>$acl->can($issuer,$actor,'tasks','create'),'can_edit'=>$acl->can($issuer,$actor,'tasks','edit')]);
        }
    protected function nativeTicketConnect()
        {
            if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto de empresa indisponível.',503);return;}require_once APPPATH.'services/CompanyTicketConnect.php';$context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();$service=new CompanyTicketConnect($this->db,db_prefix());$this->output->set_header('Cache-Control: no-store')->set_header('Pragma: no-cache')->set_header('Referrer-Policy: no-referrer');$url=CompanyRequestScope::adminUrl($this,'tickets/connect');$csrf=$this->session->userdata('company_ticket_connect_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_ticket_connect_csrf',$csrf);}$error=null;$ticket=null;$query=$this->input->get('ticket',false);if($query!==null){if(!is_string($query)||!preg_match('/^[1-9][0-9]{0,8}$/D',$query)){show_error('Chamado inválido.',422);return;}$ticket=(int)$query;}
            try{$method=$this->input->method(true);if($method==='POST'){$posted=$this->input->post('company_ticket_connect_csrf',false);if(!is_string($posted)||!hash_equals($csrf,$posted)){show_error('Reabra a página antes de continuar.',403);return;}$this->load->library('encryption');$action=$this->input->post('action',false);$revision=$this->input->post('revision',false);if(!is_string($revision)||!preg_match('/^(0|[1-9][0-9]{0,8})$/D',$revision))throw new DomainException('company_connect_api_invalid');$page=$service->page($issuer,$actor,$ticket);if($page['policy']['revision']!==(int)$revision)throw new DomainException('company_connect_api_stale');
                    if($action==='configure'){$enabled=$this->input->post('enabled',false);if(!in_array($enabled,['0','1'],true))throw new DomainException('company_connect_api_invalid');$service->configure($issuer,$actor,(int)$revision,$enabled==='1',$this->config->base_url());}
                    elseif($action==='queue'){$contact=$this->input->post('contact_id',false);$source=$this->input->post('source_revision',false);if(!$ticket||!is_string($contact)||!preg_match('/^[1-9][0-9]{0,8}$/D',$contact)||!is_string($source))throw new DomainException('company_connect_api_invalid');$service->queue($issuer,$actor,$ticket,(int)$contact,$source,fn($v)=>$this->encryption->encrypt($v));}
                    elseif($action==='dispatch'){$key=$this->input->post('message_key',false);if(!is_string($key))throw new DomainException('company_connect_api_invalid');$service->dispatch($issuer,$actor,$key,fn($v)=>$this->encryption->decrypt($v));}
                    elseif($action==='phone'){$contact=$this->input->post('contact_id',false);$phoneRevision=$this->input->post('phone_revision',false);$phone=$this->input->post('phone',false);if(!$ticket||!is_string($contact)||!preg_match('/^[1-9][0-9]{0,8}$/D',$contact)||!is_string($phoneRevision)||!is_string($phone))throw new DomainException('company_connect_api_invalid');$service->changePhone($issuer,$actor,$ticket,(int)$contact,$phoneRevision,$phone);}
                    else throw new DomainException('company_connect_api_invalid');CompanyRequestScope::invalidateCaches($this);redirect($url.($ticket?'?ticket='.$ticket:''),'location',303);return;
                }elseif(!in_array($method,['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
            }catch(DomainException $e){$status=match($e->getMessage()){'company_connect_api_denied'=>403,'company_connect_api_stale','company_connect_api_identity_changed'=>409,default=>422};$this->output->set_status_header($status);$error=$status===403?'Sua permissão mudou. Reabra a empresa.':($status===409?'O perfil, o chamado ou o contato mudou. Reabra a página.':'Confira a autorização e o perfil conectado. Nenhuma entrega foi confirmada.');}catch(Throwable $e){$this->output->set_status_header(503);$error='Operação não confirmada. Consulte a fila antes de continuar.';}
            try{$page=$service->page($issuer,$actor,$ticket);$acl=new IssuerResourceAccess($this->db,db_prefix());$this->load->view('admin/tickets/native_connect',['page'=>$page,'ticket'=>$ticket,'csrf'=>$csrf,'error'=>$error,'can_configure'=>$acl->can($issuer,$actor,'settings','edit')&&$acl->can($issuer,$actor,'connect-api-connector','configure'),'can_edit_phone'=>$acl->can($issuer,$actor,'customers','edit')]);}catch(DomainException $e){show_error('Sem acesso a este chamado ou configuração.',403);}catch(Throwable $e){show_error('Fila indisponível. Confira a atualização do núcleo.',503);}
        }
    protected function nativeProjectTimeBilling()
     {
      if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto indisponível.',503);return;}require_once APPPATH.'services/CompanyTaskTimeBilling.php';$ctx=CompanyRequestScope::context();$issuer=$ctx->issuerId();$actor=$ctx->staffId();$acl=new IssuerResourceAccess($this->db,db_prefix());$this->output->set_header('Cache-Control: no-store');foreach([['tasks','view'],['tasks','edit'],['projects','view'],['customers','view'],['invoices','view'],['invoices','create']] as [$f,$c])if(!$acl->can($issuer,$actor,$f,$c)){show_error('Sem permissão para faturar horas nesta empresa.',403);return;}
      $csrf=$this->session->userdata('company_time_billing_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_time_billing_csrf',$csrf);}$service=new CompanyTaskTimeBilling($this->db,db_prefix());$billing=null;$choices=null;$projects=[];$currencies=[];$rate=null;$error=null;
      try{if($this->input->method(true)==='POST'){$token=$this->input->post('company_time_billing_csrf',false);if(!is_string($token)||!hash_equals($csrf,$token)){show_error('Recarregue antes de salvar.',403);return;}$key=$this->input->post('request_key',false);if(!is_string($key))throw new DomainException('company_time_billing_invalid');CompanySalesBase::key($key);$action=$this->input->post('action',false);
        if($action==='prepare'){$input=$this->input->post('billing',false);if(!is_array($input)||$this->input->post('billable',false)!=='1')throw new DomainException('company_time_billing_invalid');$mode=$input['selection_mode']??null;if($mode==='selected_timers')unset($input['task_ids']);elseif($mode==='selected_tasks')unset($input['timer_ids']);elseif($mode==='project_remaining')unset($input['timer_ids'],$input['task_ids']);if(($input['rate_source']??'explicit')==='project')unset($input['rate'],$input['round_mode'],$input['round_minutes'],$input['prices']);elseif(($input['rate_source']??'explicit')==='per_task'){$input['rate_source']='explicit';unset($input['rate'],$input['round_mode'],$input['round_minutes'],$input['project_rate_id']);foreach($input['prices']??[] as $id=>$price)if(is_array($price)&&($price['rate']??'')==='')unset($input['prices'][$id]);}else unset($input['project_rate_id'],$input['prices']);if(isset($input['taxes'])&&is_array($input['taxes']))$input['taxes']=array_values(array_filter($input['taxes'],fn($t)=>!(is_array($t)&&($t['name']??null)===''&&($t['rate']??null)==='0.00')));$origin=$service->prepare($issuer,$actor,$this->input->post('project_id',false),$this->input->post('revision',false),$input,$key);}
        elseif(in_array($action,['convert','disable'],true)){$origin=$this->input->post('origin_id',false);if($action==='convert')$service->convert($issuer,$actor,$origin);else$service->disable($issuer,$actor,$origin);}else throw new DomainException('company_time_billing_invalid');CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'projects/time_billing?origin='.(int)$origin),'location',303);return;
       }elseif(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
       $origin=$this->input->get('origin',false);$project=$this->input->get('project',false);if($origin!==null){$billing=$service->status($issuer,$actor,$origin);$project=$billing['project_id'];}if($project!==null){$choices=$service->choices($issuer,$actor,$project,$this->input->get('timer_after',false)??0);$currencies=(new CompanyInvoices($this->db,db_prefix()))->currencies($issuer,$actor);$rate=(new CompanyProjectBillingRates($this->db,db_prefix()))->current($issuer,$actor,$project);}else{$after=$this->input->get('after',false)??0;$projects=(new CompanyProjects($this->db,db_prefix()))->page($issuer,$actor,$after==='0'?0:$after);}
      }catch(DomainException $e){$reason=$e->getMessage();if(in_array($reason,['company_time_billing_denied','company_task_billing_denied','company_project_access_denied','company_invoice_access_denied'],true)){show_error('Origem ou permissão indisponível nesta empresa.',403);return;}$this->output->set_status_header(in_array($reason,['company_time_batch_overlap','company_time_batch_immutable','company_time_billing_overlap','company_time_billing_conflict','company_time_billing_stale','company_time_billing_immutable','company_sales_origin_changed','company_project_rate_stale','company_project_fixed_billing_immutable'],true)?409:422);$error=match($reason){'company_time_billing_overlap'=>'Um timer já foi reservado ou utilizado em outra emissão. As horas anteriores não serão reutilizadas.','company_time_billing_immutable'=>'Esta reserva durável ou emissão não pode liberar horas para outra cobrança. Retome o mesmo lote.','company_time_billing_stale'=>'A tarefa, o timer ou o projeto mudou. Reabra e revise antes de confirmar.','company_time_billing_conflict'=>'Outro envio ou operador reservou esta origem. Reabra o lote.','company_sales_origin_changed'=>'A identidade fiscal, cliente ou configuração mudou. Revise a origem antes de emitir.','company_time_billing_not_ready'=>'Escolha timers encerrados de um projeto ativo ou concluído. Projeto inteiro exige conclusão prévia.',default=>'Confira seleção, taxa por hora, moeda, datas, arredondamento e valores decimais. O envio não foi confirmado.'};}
      catch(Throwable $e){log_message('error','Company time billing unconfirmed; contents omitted.');$this->output->set_status_header(503);$error='Não foi possível confirmar a operação. Reabra para retomar o mesmo lote.';}
      $this->load->view('admin/projects/native_time_billing',['issuer'=>$issuer,'billing'=>$billing,'choices'=>$choices,'projects'=>$projects,'currencies'=>$currencies,'rate'=>$rate,'error'=>$error,'company_time_billing_csrf'=>$csrf,'request_key'=>bin2hex(random_bytes(32))]);
     }
    protected function nativeProjectCopies()
        {
            if(func_num_args()!==0){show_404();return;}
            if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto indisponível.',503);return;}
            require_once APPPATH.'services/CompanyProjectCopies.php';$context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();$acl=new IssuerResourceAccess($this->db,db_prefix());foreach([['projects','view'],['projects','create'],['customers','view']] as [$feature,$cap])if(!$acl->can($issuer,$actor,$feature,$cap)){show_error('Sem permissão para copiar projetos nesta empresa.',403);return;}
            $this->output->set_header('Cache-Control: no-store');$s=new CompanyProjectCopies($this->db,db_prefix());$csrf=$this->session->userdata('company_project_copies_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_project_copies_csrf',$csrf);}$source=null;$preview=null;$receipt=null;$error=null;$attempt=null;
            try{
                $verb=$this->input->method(true);
                if($verb==='POST'){
                    $token=$this->input->post('company_project_copies_csrf',false);if(!is_string($token)||!hash_equals($csrf,$token)){show_error('Reabra a revisão antes de copiar.',403);return;}
                    if($this->input->post('action',false)!=='copy_reviewed_project'){show_404();return;}$source=IssuerPolicy::id($this->input->post('source_project_id',false));$review=$this->input->post('review',false);$key=$this->input->post('request_key',false);if(!is_array($review)||!is_string($key))throw new DomainException('company_project_copy_invalid');if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_project_copy_invalid');$attempt=$key;$result=$s->create($issuer,$actor,$source,$review,$attempt);CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'projects/copies?receipt='.$result['request_key']),'location',303);return;
                }
                if(!in_array($verb,['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
                $candidate=$this->input->get('source',false);$key=$this->input->get('receipt',false);if(($candidate!==null&&$key!==null)||($candidate===null&&$key===null)||($candidate!==null&&!is_scalar($candidate))||($key!==null&&!is_string($key))){show_404();return;}
                if($key!==null)$receipt=$s->receipt($issuer,$actor,$key);else{$source=IssuerPolicy::id($candidate);$preview=$s->preview($issuer,$actor,$source);}
            }catch(DomainException $e){
                if(in_array($e->getMessage(),['company_project_copy_denied','company_project_access_denied','company_project_reference_denied','issuer_access_denied'],true)){show_error('Cópia indisponível para esta empresa, origem ou responsável atual.',403);return;}
                $this->output->set_status_header(str_contains($e->getMessage(),'stale')||str_contains($e->getMessage(),'conflict')?409:422);$error=match($e->getMessage()){'company_project_copy_stale'=>'O projeto ou seus componentes mudaram. Reabra a revisão.','company_project_copy_conflict'=>'Esta chave já pertence a uma tentativa diferente. Confira o recibo original.','company_project_copy_limit'=>'A origem excede o limite de200tarefas ou100marcos por cópia.',default=>'Confira nome, datas, opções, responsáveis atuais e permissões. Nenhuma cópia foi confirmada.'};
            }catch(Throwable $e){log_message('error','Reviewed project copy unconfirmed; no source contents logged.');$this->output->set_status_header(503);$error='Não foi possível confirmar esta tentativa. Confira o recibo desta chave antes de iniciar outra cópia.';}
            $this->load->view('admin/projects/native_copies',['issuer'=>$issuer,'source'=>$source,'preview'=>$preview,'receipt'=>$receipt,'error'=>$error,'attempt'=>$attempt,'request_key'=>bin2hex(random_bytes(32)),'company_project_copies_csrf'=>$csrf]);
        }
    protected function nativeSettingsMicrosoftMailOauth()
        {
            if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto de empresa indisponível.',503);return;}
            require_once APPPATH.'services/CompanyMicrosoftMailOAuth.php';$context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();$service=new CompanyMicrosoftMailOAuth($this->db,db_prefix());
            $this->output->set_header('Cache-Control: no-store')->set_header('Pragma: no-cache')->set_header('Referrer-Policy: no-referrer');
            $url=CompanyRequestScope::adminUrl($this,'settings/microsoft_mail_oauth');$method=$this->input->method(true);$error=null;
            if(!(new IssuerResourceAccess($this->db,db_prefix()))->can($issuer,$actor,'settings','view')){show_error('Sem permissão nesta empresa.',403);return;}
            $this->load->library('encryption');$encrypt=fn($v)=>$this->encryption->encrypt($v);$decrypt=fn($v)=>$this->encryption->decrypt($v);
            // Authorization code is protocol query input only: never echo, persist, flash or log it.
            if(in_array($method,['GET','HEAD'],true)&&($this->input->get('state',false)!==null||$this->input->get('code',false)!==null||$this->input->get('error',false)!==null)){
                try{$state=$this->input->get('state',false);$code=$this->input->get('code',false);if($method!=='GET'||!is_string($state)||!is_string($code)||$this->input->get('error',false)!==null)throw new DomainException('company_microsoft_mail_state_invalid');$service->callback($issuer,$actor,$state,$code,$encrypt,$decrypt);$this->session->set_flashdata('company_microsoft_mail_result','connected');}
                catch(Throwable$error){$this->session->set_flashdata('company_microsoft_mail_result','unconfirmed');}
                redirect($url,'location',303);return;
            }
            $csrf=$this->session->userdata('company_microsoft_mail_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_microsoft_mail_csrf',$csrf);}
            $redirect=null;try{if(!defined('COMPANY_MICROSOFT_MAIL_REDIRECT_URI'))throw new DomainException('company_microsoft_mail_https_required');$redirect=CompanyMicrosoftMailOAuth::redirectUri(COMPANY_MICROSOFT_MAIL_REDIRECT_URI);}catch(Throwable$ignored){}
            try{
                if($method==='POST'){
                    $posted=$this->input->post('company_microsoft_mail_csrf',false);if(!is_string($posted)||!hash_equals($csrf,$posted)){show_error('Recarregue a página antes de salvar.',403);return;}
                    $revision=$this->input->post('revision',false);$action=$this->input->post('action',false);if(!is_string($revision)||!preg_match('/^(0|[1-9][0-9]{0,8})$/D',$revision))throw new DomainException('company_microsoft_mail_invalid');
                    if($action==='save'){$tenant=$this->input->post('tenant_id',false);$id=$this->input->post('client_id',false);$secret=$this->input->post('client_secret',false);if(!is_string($tenant)||!is_string($id)||!is_string($secret))throw new DomainException('company_microsoft_mail_invalid');$service->save($issuer,$actor,(int)$revision,$tenant,$id,$secret,$encrypt,$decrypt);}
                    elseif($action==='document_mode'){require_once APPPATH.'services/CompanyMailTransportPolicy.php';$policyRevision=$this->input->post('transport_revision',false);$mode=$this->input->post('document_mode',false);$providerRevision=$mode==='google'?$this->input->post('google_revision',false):$revision;if(!is_string($policyRevision)||!preg_match('/^(0|[1-9][0-9]{0,8})$/D',$policyRevision)||!is_string($providerRevision)||!preg_match('/^(0|[1-9][0-9]{0,8})$/D',$providerRevision)||!is_string($mode))throw new DomainException('company_mail_transport_invalid');(new CompanyMailTransportPolicy($this->db,db_prefix()))->save($issuer,$actor,(int)$policyRevision,$mode,(int)$providerRevision);}
                    elseif($action==='portal_mode'){require_once APPPATH.'services/CompanyPortalMailConfig.php';$portalRevision=$this->input->post('portal_revision',false);$mode=$this->input->post('portal_mode',false);if(!is_string($portalRevision)||!preg_match('/^(0|[1-9][0-9]{0,8})$/D',$portalRevision)||!is_string($mode))throw new DomainException('company_portal_mail_invalid');(new CompanyPortalMailConfig($this->db,db_prefix()))->save($issuer,$actor,(int)$portalRevision,$mode,(int)$revision);}
                    elseif($action==='revoke')$service->revoke($issuer,$actor,(int)$revision);
                    elseif($action==='begin'){if($redirect===null)throw new DomainException('company_microsoft_mail_https_required');$target=$service->begin($issuer,$actor,(int)$revision,$redirect,$encrypt);redirect($target,'location',303);return;}
                    else throw new DomainException('company_microsoft_mail_invalid');
                    CompanyRequestScope::invalidateCaches($this);redirect($url,'location',303);return;
                }elseif(!in_array($method,['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
            }catch(DomainException$failure){$status=match($failure->getMessage()){'company_microsoft_mail_access_denied','company_portal_mail_denied','company_mail_transport_denied'=>403,'company_microsoft_mail_stale','company_portal_mail_stale','company_mail_transport_stale'=>409,default=>422};$this->output->set_status_header($status);$error=match($failure->getMessage()){'company_microsoft_mail_access_denied','company_portal_mail_denied','company_mail_transport_denied'=>'Sua permissão mudou. Reabra a empresa antes de continuar.','company_microsoft_mail_stale','company_portal_mail_stale','company_mail_transport_stale'=>'A configuração mudou. Reabra a tela antes de continuar.','company_microsoft_mail_https_required'=>'Defina no servidor o endereço HTTPS confiável de retorno.','company_microsoft_mail_sender_required'=>'Configure primeiro o endereço de envio próprio desta empresa.',default=>'Confira os dados. Nenhuma nova conexão foi confirmada nesta tentativa.'};}
            catch(Throwable$failure){$this->output->set_status_header(503);$error='Não foi possível confirmar a operação. Reabra a tela; não repita um código de autorização.';}
            try{$profile=$service->view($issuer,$actor);require_once APPPATH.'services/CompanyMailTransportPolicy.php';$documentMail=(new CompanyMailTransportPolicy($this->db,db_prefix()))->page($issuer,$actor);$portalMail=null;require_once APPPATH.'services/CompanyPortalMailConfig.php';if(CompanyPortalMailSchema::present($this->db,db_prefix()))$portalMail=(new CompanyPortalMailConfig($this->db,db_prefix()))->page($issuer,$actor);}catch(Throwable$failure){show_error('Configuração indisponível nesta empresa. Configure o remetente próprio ou confira a atualização do núcleo.',503);return;}
            $this->load->view('admin/settings/native_microsoft_mail_oauth',['issuer'=>$issuer,'profile'=>$profile,'error'=>$error,'csrf'=>$csrf,'trusted_redirect'=>$redirect,'can_edit'=>(new IssuerResourceAccess($this->db,db_prefix()))->can($issuer,$actor,'settings','edit'),'document_mail'=>$documentMail,'portal_mail'=>$portalMail,'result'=>$this->session->flashdata('company_microsoft_mail_result')]);
        }
    protected function nativeHrContext():array
        {
            if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto indisponível.',503);exit;}
            require_once APPPATH.'services/CompanyHr.php';$c=CompanyRequestScope::context();$this->output->set_header('Cache-Control: no-store')->set_header('X-Content-Type-Options: nosniff')->set_header('Referrer-Policy: no-referrer');return [$c->issuerId(),$c->staffId(),new CompanyHr($this->db,db_prefix()),new IssuerResourceAccess($this->db,db_prefix())];
        }
    protected function nativeHrCsrf():string
        {
            $csrf=$this->session->userdata('company_hr_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_hr_csrf',$csrf);}return $csrf;
        }
    protected function nativeHrPostGuard(string $csrf):void
        {
            $submitted=$this->input->post('company_hr_csrf',false);if(!is_string($submitted)||!hash_equals($csrf,$submitted)){show_error('Recarregue o formulário antes de salvar.',403);exit;}
            if((int)($this->input->server('CONTENT_LENGTH')??0)>3000000)throw new DomainException('issuer_hr_invalid');
        }
    protected function nativeHrUpload(string $field,int $limit):array
        {
            $f=$_FILES[$field]??null;if(!is_array($f)||($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_string($f['tmp_name']??null)||!is_uploaded_file($f['tmp_name'])||!is_string($f['name']??null)||(int)($f['size']??0)>$limit)throw new DomainException('issuer_hr_invalid');$bytes=file_get_contents($f['tmp_name'],false,null,0,$limit+1);if(!is_string($bytes)||strlen($bytes)>$limit)throw new DomainException('issuer_hr_invalid');return [$f['name'],$bytes];
        }
    protected function nativeHrKey():string{$k=$this->input->post('request_key',false);if(!is_string($k)||!preg_match('/^[a-f0-9]{64}$/D',$k))throw new DomainException('issuer_hr_invalid');return $k;}
    protected function nativeHrFail(Throwable $e):string
        {
            if($e instanceof DomainException){$code=$e->getMessage();$this->output->set_status_header(str_ends_with($code,'denied')?403:(str_ends_with($code,'stale')||str_ends_with($code,'conflict')?409:422));return match($code){'issuer_hr_cycle'=>'A hierarquia não pode conter ciclos.','issuer_hr_reference_in_use'=>'Revise os vínculos ativos antes de encerrar esta referência.','issuer_hr_contract_overlap'=>'Há outro contrato ativo no mesmo intervalo.','issuer_hr_stale'=>'O registro mudou. Reabra a página e confira a revisão.','issuer_hr_limit'=>'O limite desta operação foi atingido.','issuer_hr_ambiguous_deadline'=>'A hora local é inexistente ou ambígua nesse fuso. Escolha outro horário.',default=>'Confira as permissões, o vínculo da empresa e os campos. Nenhuma alteração desta tentativa foi confirmada.'};}
            log_message('error','Company HR operation unconfirmed; sensitive fields, tokens and documents omitted.');$this->output->set_status_header(503);return 'Não foi possível confirmar a tentativa. Reabra o registro antes de repetir.';
        }
    protected function nativeHr()
        {
            [$i,$a,$s,$acl]=$this->nativeHrContext();if(!$acl->can($i,$a,'hr','view')){show_error('RH indisponível nesta empresa.',403);return;}$verb=$this->input->method(true);if(!in_array($verb,['GET','HEAD','POST'],true)){show_error('Método não permitido.',405);return;}
            $csrf=$this->nativeHrCsrf();$section=$this->input->get('section',false)??'people';if(!in_array($section,['people','structure','courses','articles','reports','import'],true)){show_error('Seção inválida.',422);return;}$error=null;$issued=null;$preview=null;$employeeId=$this->input->get('employee',false);
            try{
                $employeeId=$employeeId===null?null:IssuerPolicy::id($employeeId);
                if($verb==='POST'){
                    $this->nativeHrPostGuard($csrf);$key=$this->nativeHrKey();$action=$this->input->post('action',false);$id=$this->input->post('entity_id',false);$id=$id===null||$id===''?null:IssuerPolicy::id($id);$revision=$this->input->post('revision',false);$input=$this->input->post('input',false)??[];if(!is_array($input))throw new DomainException('issuer_hr_invalid');
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
                    elseif($action==='file_upload'){[$name,$bytes]=$this->nativeHrUpload('document',2097152);$s->addFile($i,$a,(string)($input['purpose']??''),IssuerPolicy::id($input['object_id']??null),$revision,$name,$bytes,$key);}
                    elseif($action==='file_hide'){$s->hideFile($i,$a,(string)($input['purpose']??''),IssuerPolicy::id($input['object_id']??null),IssuerPolicy::id($id),$revision,$key);}
                    elseif($action==='article_save'){$s->saveArticle($i,$a,$id,$revision,$input,$key);$section='articles';}
                    elseif($action==='import_review'){[$name,$csv]=$this->nativeHrUpload('csv',262144);$preview=$s->reviewImport($i,$a,$csv)+['content_base64'=>base64_encode($csv)];$section='import';$redirect=false;}
                    elseif($action==='import_commit'){$encoded=$input['content_base64']??null;$hash=$input['review_hash']??null;if(!is_string($encoded)||strlen($encoded)>350000||!is_string($hash)||($csv=base64_decode($encoded,true))===false||base64_encode($csv)!==$encoded)throw new DomainException('issuer_hr_invalid');$s->importEmployees($i,$a,$csv,$hash,$key);$section='people';}
                    else throw new DomainException('issuer_hr_invalid');
                    CompanyRequestScope::invalidateCaches($this);if($redirect){$query=['section'=>$section];if($employeeId!==null)$query['employee']=$employeeId;if(in_array($action,['file_upload','file_hide'],true)){$query['files_purpose']=$input['purpose'];$query['files_object']=$input['object_id'];}redirect(CompanyRequestScope::adminUrl($this,'staff/hr?'.http_build_query($query)),'location',303);return;}
                }
            }catch(Throwable $e){$error=$this->nativeHrFail($e);if(!is_int($employeeId))$employeeId=null;}
            $data=['issuer'=>$i,'actor'=>$a,'section'=>$section,'error'=>$error,'issued'=>$issued,'preview'=>$preview,'company_hr_csrf'=>$csrf,'request_key'=>bin2hex(random_bytes(32)),'employee'=>null,'profile'=>null,'records'=>[],'contracts'=>[],'assignments'=>[],'accesses'=>[],'courses'=>[],'articles'=>[],'report'=>null,'files'=>[],'file_context'=>null,'staff_choices'=>[]];
            $can=static fn($feature,$cap)=>$acl->can($i,$a,$feature,$cap);$data['can']=$can;
            try{
                $structure=$s->structureSnapshot($i,$a);$data['structure']=$structure['structure'];$data['chart']=$structure['chart'];$data['directory']=$s->employees($i,$a,$this->input->get('after',false)??0);
                if($can('hr','create'))$data['staff_choices']=$s->staffChoices($i,$a);
                if($employeeId!==null){$data['employee']=$s->getEmployee($i,$a,$employeeId);if($can('hr_sensitive','view')){$data['profile']=$s->getProfile($i,$a,$employeeId);$data['records']=$s->records($i,$a,$employeeId);}if($can('hr_contracts','view')&&$can('hr_sensitive','view'))$data['contracts']=$s->contracts($i,$a,$employeeId);if($can('hr_training','view'))$data['assignments']=$s->assignments($i,$a,$employeeId);if($can('hr_portal','manage'))$data['accesses']=$s->accesses($i,$a,$employeeId);}
                if($can('hr_training','view'))$data['courses']=$s->courses($i,$a);if($section==='articles')$data['articles']=$s->articles($i,$a);
                if($section==='reports'){$reportName=$this->input->get('report',false)??'roster';if(!is_string($reportName))throw new DomainException('issuer_hr_invalid');$data['report']=$s->report($i,$a,$reportName);}
                $purpose=$this->input->get('files_purpose',false);$object=$this->input->get('files_object',false);if($purpose===null&&$employeeId!==null&&$can('hr_sensitive','view')){$purpose='employee';$object=$employeeId;}
                if($purpose!==null&&$can('hr_files','view')){if(!is_string($purpose))throw new DomainException('issuer_hr_invalid');$object=IssuerPolicy::id($object);$data['files']=$s->files($i,$a,$purpose,$object);$parent=match($purpose){'employee'=>$s->getEmployee($i,$a,$object),'contract'=>$s->getContract($i,$a,$object),'course'=>$this->nativeHrFind($data['courses'],$object),'record'=>$this->nativeHrFind($data['records'],$object),'article'=>$this->nativeHrFind($data['articles'],$object),default=>throw new DomainException('issuer_hr_invalid')};$data['file_context']=['purpose'=>$purpose,'object_id'=>$object,'revision'=>$parent['revision']];}
            }catch(Throwable $e){if($data['error']===null)$data['error']=$this->nativeHrFail($e);$data+=['structure'=>['unit'=>[],'position'=>[],'workplace'=>[],'contract_type'=>[]],'directory'=>['items'=>[],'next'=>null],'chart'=>['employees'=>[],'count'=>0]];}
            $this->load->view('admin/staff/native_hr',$data);
        }
    protected function nativeHrFind(array $rows,int $id):array{foreach($rows as $r)if((int)$r['id']===$id)return $r;throw new DomainException('issuer_hr_access_denied');}
    protected function nativeHrOutputFile(array $blob):void
        {
            $this->output->set_header("Content-Security-Policy: sandbox; default-src 'none'")->set_header('Content-Disposition: attachment; filename="documento.'.($blob['mime']==='image/png'?'png':'pdf').'"; filename*=UTF-8\'\''.rawurlencode($blob['filename']))->set_header('Content-Length: '.strlen($blob['bytes']))->set_content_type($blob['mime'])->set_output($this->input->method(true)==='HEAD'?'':$blob['bytes']);
        }
    protected function nativeHrFile()
        {
            [$i,$a,$s]=$this->nativeHrContext();if(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}try{$purpose=$this->input->get('purpose',false);if(!is_string($purpose))throw new DomainException('issuer_hr_invalid');$this->nativeHrOutputFile($s->getFile($i,$a,$purpose,$this->input->get('object',false),$this->input->get('file',false)));}catch(Throwable $e){show_error($this->nativeHrFail($e),$e instanceof DomainException?403:503);}
        }
    protected function nativeHrPdf()
        {
            [$i,$a,$s]=$this->nativeHrContext();if(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}try{$id=$this->input->get('contract',false);$document=$s->contractDocument($i,$a,$id);require_once APPPATH.'services/CompanyHrPdf.php';$pdf=CompanyHrPdf::render($document);if($document!==$s->contractDocument($i,$a,$id))throw new DomainException('issuer_hr_stale');$this->nativeHrOutputFile(['mime'=>'application/pdf','filename'=>'contrato-rh-'.$document['number'].'.pdf','bytes'=>$pdf]);}catch(Throwable $e){show_error($this->nativeHrFail($e),$e instanceof DomainException?403:503);}
        }
    protected function nativeHrExport()
        {
            [$i,$a,$s]=$this->nativeHrContext();if(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}try{$name=$this->input->get('report',false);if(!is_string($name))throw new DomainException('issuer_hr_invalid');$csv=$s->reportCsv($i,$a,$name);$this->output->set_header('Content-Disposition: attachment; filename="rh.csv"')->set_content_type('text/csv','UTF-8')->set_output($this->input->method(true)==='HEAD'?'':$csv);}catch(Throwable $e){show_error($this->nativeHrFail($e),$e instanceof DomainException?403:503);}
        }
    protected function nativeHrMine()
        {
            [$i,$a,$s]=$this->nativeHrContext();$verb=$this->input->method(true);if(!in_array($verb,['GET','HEAD','POST'],true)){show_error('Método não permitido.',405);return;}$csrf=$this->nativeHrCsrf();$error=null;$result=null;
            try{if($verb==='POST'){$this->nativeHrPostGuard($csrf);$key=$this->nativeHrKey();$action=$this->input->post('action',false);if($action==='training_submit'){$answers=$this->input->post('answers',false);if(!is_array($answers))throw new DomainException('issuer_hr_invalid');$result=$s->submitTraining($i,$a,$this->input->post('assignment_id',false),$this->input->post('revision',false),$answers,$key);}elseif($action==='notice_read')$s->readNotice($i,$a,$this->input->post('notice_id',false),$key);else throw new DomainException('issuer_hr_invalid');}$assignments=$s->assignments($i,$a,null,true);$notices=$s->myNotices($i,$a);$articles=$s->articles($i,$a,true);}
            catch(Throwable $e){$error=$this->nativeHrFail($e);$assignments=[];$notices=[];$articles=[];}
            $this->load->view('admin/staff/native_hr_mine',['company_hr_csrf'=>$csrf,'request_key'=>bin2hex(random_bytes(32)),'error'=>$error,'result'=>$result,'assignments'=>$assignments,'notices'=>$notices,'articles'=>$articles]);
        }
    protected function nativeHrMine_file()
        {
            [$i,$a,$s]=$this->nativeHrContext();if(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}try{$this->nativeHrOutputFile($s->myTrainingFile($i,$a,$this->input->get('assignment',false),$this->input->get('file',false)));}catch(Throwable $e){show_error($this->nativeHrFail($e),$e instanceof DomainException?403:503);}
        }
    protected function nativeSettingsEfi()
        {
            if(!CompanyRequestScope::sealedFor($this)||!CompanySettingsRuntime::active()){show_error('Contexto de empresa indisponível.',403);return;}
            $issuer=CompanySettingsRuntime::issuerId();$actor=CompanySettingsRuntime::staffId();
            $this->output->set_header('Cache-Control: no-store, private')->set_header('Pragma: no-cache')->set_header('Referrer-Policy: no-referrer');
            $this->load->library('encryption');$accounts=new \Efi\CompanyAccounts($this->db,db_prefix());
            $checkout=new \Efi\CompanyCheckout($this->db,db_prefix(),$accounts,fn(array $row)=>new \Efi\CompanyTransport($row,$this->encryption));
            $csrf=$this->session->userdata('company_efi_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_efi_csrf',$csrf);}
            $error=null;$saved=null;$instrument=null;$method=$this->input->method(true);
            try{
                if(!in_array($method,['GET','HEAD','POST'],true)){show_error('Método não permitido.',405);return;}
                if($method==='POST'){
                    $post=$this->input->post(null,false);if(!is_array($post)||!is_string($post['company_efi_csrf']??null)||!hash_equals($csrf,$post['company_efi_csrf'])){show_error('Reabra o formulário desta empresa.',403);return;}
                    $text=static function(string $key)use($post):string{if(!is_string($post[$key]??null))throw new DomainException('efi_company_input_invalid');return $post[$key];};
                    $action=$text('action');
                    if(($post['confirm']??null)!=='1')throw new DomainException('efi_company_confirmation_required');
                    if($action==='save'){
                        $accounts->authorize($issuer,$actor,'configure');$certificate=null;
                        if(isset($_FILES['certificate'])&&($_FILES['certificate']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
                            $upload=$_FILES['certificate'];if(($upload['error']??null)!==UPLOAD_ERR_OK||!is_string($upload['tmp_name']??null)||!is_uploaded_file($upload['tmp_name'])||($upload['size']??0)>EfiCertificateStore::MAX_BYTES)throw new DomainException('efi_certificate_invalid');
                            $bytes=file_get_contents($upload['tmp_name']);if(!is_string($bytes))throw new DomainException('efi_certificate_invalid');$certificate=EfiCertificateStore::seal($bytes,$text('certificate_password'),$this->encryption);$bytes='';
                        }
                        $accounts->save($issuer,$actor,$text('environment'),$text('revision'),$text('client_id'),$text('client_secret'),$text('pix_key'),$certificate,$this->encryption);$saved='Conta própria protegida. A revisão mudou; esta ação não testa o provedor nem emite cobrança.';
                    }elseif($action==='revoke'){$accounts->revoke($issuer,$actor,IssuerPolicy::id($text('account_id')),$text('revision'));$saved='Conta revogada localmente. Histórico preservado; provas anteriores não autorizam novas baixas.';}
                    elseif($action==='issue'){
                        $kind=$text('kind');if(!in_array($kind,['pix','boleto'],true))throw new DomainException('efi_company_card_tokenization_unavailable');
                        $input=[];if($kind==='boleto')foreach(['email','phone'] as $field)$input[$field]=$text($field);
                        $id=$checkout->issue($issuer,$actor,IssuerPolicy::id($text('invoice_id')),IssuerPolicy::id($text('account_id')),$text('revision'),$kind,$input);$saved='Origem '.$id.' registrada. Emissão não comprova pagamento; consulte a origem antes de conciliar.';
                    }elseif($action==='instruments'){$instrument=$checkout->instruments($issuer,$actor,IssuerPolicy::id($text('origin_id')));$saved='Instrumento consultado na conta e ambiente desta origem.';}elseif($action==='query'){$id=$checkout->query($issuer,$actor,IssuerPolicy::id($text('origin_id')));$saved='Consulta registrada como prova '.$id.'. Nenhum pagamento foi lançado por esta ação.';}
                    elseif($action==='settle'){$id=$checkout->settle($issuer,$actor,IssuerPolicy::id($text('origin_id')),IssuerPolicy::id($text('proof_id')));$saved='Recebimento '.$id.' confirmado pelo escritor financeiro da empresa.';}
                    else throw new DomainException('efi_company_input_invalid');
                }
            }catch(DomainException $e){$this->output->set_status_header(422);$error='Ação não confirmada. Confira permissões, empresa, ambiente, revisão e estado da origem. Reabra a página; não repita emissão incerta.';}
            catch(Throwable $e){$this->output->set_status_header(503);$error='Operação indisponível ou resultado incerto. Não reenvie a cobrança; preserve a origem para revisão.';}
            try{
                $canConfigure=CompanySettingsRuntime::canResource('settings','view')&&CompanySettingsRuntime::canResource('settings','edit');
                $canViewConfig=CompanySettingsRuntime::canResource('settings','view');$canObserve=CompanySettingsRuntime::canResource('invoices','view');
                if(!$canViewConfig&&!$canObserve){show_error('Acesso negado nesta empresa.',403);return;}
                $profiles=$canViewConfig?$accounts->overview($issuer,$actor):[];$activity=$canObserve?$checkout->overview($issuer,$actor):[];
                $this->load->view('admin/settings/native_efi',['instrument'=>$instrument,'choices'=>$canObserve?$accounts->choices($issuer,$actor):[],'profiles'=>$profiles,'activity'=>$activity,'csrf'=>$csrf,'error'=>$error,'saved'=>$saved,'can_configure'=>$canConfigure,'can_issue'=>$canObserve&&CompanySettingsRuntime::canResource('invoices','edit')&&CompanySettingsRuntime::canResource('customers','view'),'can_settle'=>$canObserve&&CompanySettingsRuntime::canResource('payments','create')]);
            }catch(DomainException $e){show_error('Efí indisponível ou acesso revogado nesta empresa.',403);}catch(Throwable $e){show_error('Confira as migrations e a ativação local do módulo Efí.',503);}
        }
    protected function nativeTaskBilling()
        {
            if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto indisponível.',503);return;}
            require_once APPPATH.'services/CompanyTaskBilling.php';require_once APPPATH.'services/CompanyTasks.php';
            $ctx=CompanyRequestScope::context();$issuer=$ctx->issuerId();$actor=$ctx->staffId();$acl=new IssuerResourceAccess($this->db,db_prefix());$this->output->set_header('Cache-Control: no-store');
            foreach([['tasks','view'],['tasks','edit'],['projects','view'],['customers','view'],['invoices','view'],['invoices','create']] as [$feature,$cap])if(!$acl->can($issuer,$actor,$feature,$cap)){show_error('Sem permissão para faturar tarefas nesta empresa.',403);return;}
            $csrf=$this->session->userdata('company_task_billing_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_task_billing_csrf',$csrf);}
            $service=new CompanyTaskBilling($this->db,db_prefix());$task=null;$billing=null;$currencies=[];$projects=[];$error=null;$projectRate=null;
            try{
                if($this->input->method(true)==='POST'){
                    $posted=$this->input->post('company_task_billing_csrf',false);if(!is_string($posted)||!hash_equals($csrf,$posted)){show_error('Recarregue antes de salvar.',403);return;}
                    $action=$this->input->post('action',false);$id=$this->input->post('task_id',false);$key=$this->input->post('request_key',false);if(!is_string($key))throw new DomainException('company_task_billing_invalid');
                    if($action==='prepare'){$input=$this->input->post('billing',false);if(!is_array($input)||$this->input->post('billable',false)!=='1')throw new DomainException('company_task_billing_invalid');if(isset($input['taxes'])&&is_array($input['taxes']))$input['taxes']=array_values(array_filter($input['taxes'],fn($t)=>!(is_array($t)&&($t['name']??null)===''&&($t['rate']??null)==='0.00')));$service->prepare($issuer,$actor,$id,$this->input->post('revision',false),$input,$key);}
                    elseif($action==='convert')$service->convert($issuer,$actor,$id,$this->input->post('origin_id',false));elseif($action==='disable')$service->disable($issuer,$actor,$id,$this->input->post('origin_id',false));else throw new DomainException('company_task_billing_invalid');
                    CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'tasks/billing?task='.(int)$id),'location',303);return;
                }elseif(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
                $id=$this->input->get('task',false);if($id!==null){$task=(new CompanyTasks($this->db,db_prefix()))->get($issuer,$actor,$id);$billing=$service->status($issuer,$actor,$id);if($this->db->table_exists(db_prefix().'projects/billing_rates'))$projectRate=(new CompanyProjectBillingRates($this->db,db_prefix()))->current($issuer,$actor,$task['project_id']);$currencies=(new CompanyInvoices($this->db,db_prefix()))->currencies($issuer,$actor);}else $projects=(new CompanyProjects($this->db,db_prefix()))->page($issuer,$actor)['items'];
            }catch(DomainException $e){$reason=$e->getMessage();if(in_array($reason,['company_task_billing_denied','company_project_access_denied','company_invoice_access_denied'],true)){show_error('Origem ou permissão indisponível nesta empresa.',403);return;}$this->output->set_status_header(in_array($reason,['company_time_billing_overlap','company_time_billing_immutable','company_project_rate_stale','company_task_billing_stale','company_task_billing_conflict','company_task_billing_immutable','company_project_fixed_billing_immutable','company_sales_origin_changed'],true)?409:422);$error=match($reason){'company_time_billing_overlap','company_time_billing_immutable'=>'Há horas reservadas ou já alocadas em lotes. Use os lotes e apenas os timers restantes.','company_project_rate_stale'=>'A taxa do projeto mudou. Reabra a tarefa e revise a declaração de preço atual.','company_task_billing_stale'=>'A tarefa ou seus timers mudaram. Reabra e prepare outra declaração.','company_project_fixed_billing_immutable'=>'O projeto inteiro está reservado ou faturado; consulte o vínculo de preço fixo do projeto.','company_task_billing_immutable'=>'A conversão reservada ou concluída preserva a tarefa e seus timers.','company_task_billing_not_ready'=>'Conclua a tarefa e pare todos os cronômetros antes de faturar.','company_task_billing_reference_denied'=>'O projeto e o cliente próprio devem pertencer à empresa atual.','issuer_document_invalid_profile','issuer_document_profile_required'=>'Complete o endereço, cidade, estado, CEP e país nas configurações desta empresa antes de preparar a fatura.','company_sales_origin_changed'=>'A identidade, o cliente ou a configuração mudou. Revise a origem antes de emitir.',default=>'Confira valores com ponto decimal, moeda, datas, taxa horária e arredondamento. A tentativa não foi confirmada.'};}
            catch(Throwable $e){log_message('error','Company task billing unconfirmed; no contents logged.');$this->output->set_status_header(503);$error='Não foi possível confirmar a operação. Reabra para retomar o mesmo vínculo antes de tentar novamente.';}
            $this->load->view('admin/tasks/native_billing',['issuer'=>$issuer,'task'=>$task,'billing'=>$billing,'projects'=>$projects,'projectRate'=>$projectRate,'currencies'=>$currencies,'error'=>$error,'company_task_billing_csrf'=>$csrf,'request_key'=>bin2hex(random_bytes(32))]);
        }
    protected function nativeSettingsGoogleMailOauth()
        {
            if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto de empresa indisponível.',503);return;}
            require_once APPPATH.'services/CompanyGoogleMailOAuth.php';$context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();$service=new CompanyGoogleMailOAuth($this->db,db_prefix());
            $this->output->set_header('Cache-Control: no-store')->set_header('Pragma: no-cache')->set_header('Referrer-Policy: no-referrer');
            $url=CompanyRequestScope::adminUrl($this,'settings/google_mail_oauth');$method=$this->input->method(true);$error=null;
            if(!(new IssuerResourceAccess($this->db,db_prefix()))->can($issuer,$actor,'settings','view')){show_error('Sem permissão nesta empresa.',403);return;}
            $this->load->library('encryption');$encrypt=fn($v)=>$this->encryption->encrypt($v);$decrypt=fn($v)=>$this->encryption->decrypt($v);
            // Authorization code is protocol query input only: never echo, persist, flash or log it.
            if(in_array($method,['GET','HEAD'],true)&&($this->input->get('state',false)!==null||$this->input->get('code',false)!==null||$this->input->get('error',false)!==null)){
                try{$state=$this->input->get('state',false);$code=$this->input->get('code',false);if($method!=='GET'||!is_string($state)||!is_string($code)||$this->input->get('error',false)!==null)throw new DomainException('company_google_mail_state_invalid');$service->callback($issuer,$actor,$state,$code,$encrypt,$decrypt);$this->session->set_flashdata('company_google_mail_result','connected');}
                catch(Throwable$error){$this->session->set_flashdata('company_google_mail_result','unconfirmed');}
                redirect($url,'location',303);return;
            }
            $csrf=$this->session->userdata('company_google_mail_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_google_mail_csrf',$csrf);}
            $redirect=null;try{if(!defined('COMPANY_GOOGLE_MAIL_REDIRECT_URI'))throw new DomainException('company_google_mail_https_required');$redirect=CompanyGoogleMailOAuth::redirectUri(COMPANY_GOOGLE_MAIL_REDIRECT_URI);}catch(Throwable$ignored){}
            try{
                if($method==='POST'){
                    $posted=$this->input->post('company_google_mail_csrf',false);if(!is_string($posted)||!hash_equals($csrf,$posted)){show_error('Recarregue a página antes de salvar.',403);return;}
                    $revision=$this->input->post('revision',false);$action=$this->input->post('action',false);if(!is_string($revision)||!preg_match('/^(0|[1-9][0-9]{0,8})$/D',$revision))throw new DomainException('company_google_mail_invalid');
                    if($action==='save'){$id=$this->input->post('client_id',false);$secret=$this->input->post('client_secret',false);if(!is_string($id)||!is_string($secret))throw new DomainException('company_google_mail_invalid');$service->save($issuer,$actor,(int)$revision,$id,$secret,$encrypt,$decrypt);}
                    elseif($action==='portal_mode'){require_once APPPATH.'services/CompanyPortalMailConfig.php';$portalRevision=$this->input->post('portal_revision',false);$mode=$this->input->post('portal_mode',false);if(!is_string($portalRevision)||!preg_match('/^(0|[1-9][0-9]{0,8})$/D',$portalRevision)||!is_string($mode))throw new DomainException('company_portal_mail_invalid');(new CompanyPortalMailConfig($this->db,db_prefix()))->save($issuer,$actor,(int)$portalRevision,$mode,(int)$revision);}
                    elseif($action==='revoke')$service->revoke($issuer,$actor,(int)$revision);
                    elseif($action==='begin'){if($redirect===null)throw new DomainException('company_google_mail_https_required');$target=$service->begin($issuer,$actor,(int)$revision,$redirect,$encrypt);redirect($target,'location',303);return;}
                    else throw new DomainException('company_google_mail_invalid');
                    CompanyRequestScope::invalidateCaches($this);redirect($url,'location',303);return;
                }elseif(!in_array($method,['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
            }catch(DomainException$failure){$status=match($failure->getMessage()){'company_google_mail_access_denied','company_portal_mail_denied'=>403,'company_google_mail_stale','company_portal_mail_stale'=>409,default=>422};$this->output->set_status_header($status);$error=match($failure->getMessage()){'company_google_mail_access_denied','company_portal_mail_denied'=>'Sua permissão mudou. Reabra a empresa antes de continuar.','company_google_mail_stale','company_portal_mail_stale'=>'A configuração mudou. Reabra a tela antes de continuar.','company_google_mail_https_required'=>'Defina no servidor o endereço HTTPS confiável de retorno.','company_google_mail_sender_required'=>'Configure primeiro o endereço de envio próprio desta empresa.',default=>'Confira os dados. Nenhuma nova conexão foi confirmada nesta tentativa.'};}
            catch(Throwable$failure){$this->output->set_status_header(503);$error='Não foi possível confirmar a operação. Reabra a tela; não repita um código de autorização.';}
            try{$profile=$service->view($issuer,$actor);$portalMail=null;require_once APPPATH.'services/CompanyPortalMailConfig.php';if(CompanyPortalMailSchema::present($this->db,db_prefix()))$portalMail=(new CompanyPortalMailConfig($this->db,db_prefix()))->page($issuer,$actor);}catch(Throwable$failure){show_error('Configuração indisponível nesta empresa. Configure o remetente próprio ou confira a atualização do núcleo.',503);return;}
            $this->load->view('admin/settings/native_google_mail_oauth',['issuer'=>$issuer,'profile'=>$profile,'error'=>$error,'csrf'=>$csrf,'trusted_redirect'=>$redirect,'can_edit'=>(new IssuerResourceAccess($this->db,db_prefix()))->can($issuer,$actor,'settings','edit'),'portal_mail'=>$portalMail,'result'=>$this->session->flashdata('company_google_mail_result')]);
        }
    protected function nativeSettingsApiTokens()
        {
            if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto de empresa indisponível.',503);return;}
            require_once APPPATH.'services/CompanyApiTokens.php';$scope=CompanyRequestScope::context();$issuer=$scope->issuerId();$actor=$scope->staffId();$service=new CompanyApiTokens($this->db,db_prefix());$acl=new IssuerResourceAccess($this->db,db_prefix());$this->output->set_header('Cache-Control: no-store')->set_header('Pragma: no-cache');
            if(!$acl->can($issuer,$actor,'company_api','manage')){show_error('Sem permissão para administrar tokens API desta empresa.',403);return;}$csrf=$this->session->userdata('company_api_tokens_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_api_tokens_csrf',$csrf);}
            $error=null;$created=null;$tokens=[];$scopes=[];$message=null;
            try{if($this->input->method(true)==='POST'){$posted=$this->input->post('company_api_tokens_csrf',false);if(!is_string($posted)||!hash_equals($csrf,$posted)){show_error('Recarregue o formulário antes de salvar.',403);return;}$action=$this->input->post('action',false);$id=$this->input->post('token_id',false);if($action==='revoke'){if(!is_string($id))throw new DomainException('company_api_invalid_input');$service->revoke($issuer,$actor,$id);CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'settings/api_tokens'),'location',303);return;}
                    if(!in_array($action,['issue','rotate'],true))throw new DomainException('company_api_invalid_input');$label=$this->input->post('label',false);$selected=$this->input->post('scopes',false);$days=$this->input->post('days',false);$key=$this->input->post('request_key',false);if(!is_string($label)||!is_array($selected)||!is_string($days)||!preg_match('/^[1-9][0-9]?$/D',$days)||!is_string($key)||($action==='rotate'&&!is_string($id)))throw new DomainException('company_api_invalid_input');$created=$service->issue($issuer,$actor,$label,$selected,(int)$days,$key,$action==='rotate'?$id:null);if($created['secret']===null)$message='O envio já foi processado. O token anterior não será exibido novamente; faça uma rotação se precisar substituí-lo.';CompanyRequestScope::invalidateCaches($this);
                }elseif(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}$tokens=$service->page($issuer,$actor);$scopes=$service->availableScopes($issuer,$actor);
            }catch(DomainException $e){$reason=$e->getMessage();if($reason==='company_api_access_denied'){show_error('Token indisponível ou operação não autorizada nesta empresa.',403);return;}$this->output->set_status_header($reason==='company_api_request_conflict'?409:422);$error=match($reason){'company_api_scope_denied'=>'Uma permissão selecionada não está mais autorizada para você nesta empresa.','company_api_request_conflict'=>'Este envio já foi usado ou o token mudou. Reabra a tela.','company_api_token_limit'=>'Limite de 20 tokens ativos por funcionário e empresa. Revogue um token antes de criar outro.',default=>'Confira nome, permissões explícitas e prazo entre 1 e 90 dias. Nenhum token foi confirmado nesta tentativa.'};}
            catch(Throwable $e){log_message('error','Company API token management failed; no secret or input data logged.');$this->output->set_status_header(503);$error='Não foi possível confirmar a operação. Reabra a tela antes de tentar novamente.';}
            $this->load->view('admin/settings/native_api_tokens',['issuer'=>$issuer,'actor'=>$actor,'tokens'=>$tokens,'available_scopes'=>$scopes,'created'=>$created,'message'=>$message,'error'=>$error,'company_api_tokens_csrf'=>$csrf]);
        }
    protected function nativeTicketAiService():CompanyAi{require_once APPPATH.'services/CompanyAi.php';return new CompanyAi($this->db,db_prefix());}
    protected function nativeTicketAi()
        {
            if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto indisponível.',503);return;}
            $ctx=CompanyRequestScope::context();$issuer=$ctx->issuerId();$actor=$ctx->staffId();$service=$this->nativeTicketAiService();$url=CompanyRequestScope::adminUrl($this,'tickets/ai');$this->output->set_header('Cache-Control: no-store')->set_header('Pragma: no-cache')->set_header('Referrer-Policy: no-referrer');$error=null;$preview=null;$result=null;$history=[];
            $csrf=$this->session->userdata('company_ai_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_ai_csrf',$csrf);}
            $ticket=$this->input->get('ticket',false);$operation=$this->input->get('operation',false)??'summary';
            if(($ticket!==null&&(!is_string($ticket)||!preg_match('/^[1-9][0-9]{0,8}$/D',$ticket)))||!in_array($operation,['summary','reply'],true)){show_error('Seleção inválida.',422);return;}$ticket=$ticket===null?null:(int)$ticket;
            try{
                $method=$this->input->method(true);
                if($method==='POST'){
                    $token=$this->input->post('company_ai_csrf',false);if(!is_string($token)||!hash_equals($csrf,$token)){show_error('Reabra a página.',403);return;}$this->load->library('encryption');$action=$this->input->post('action',false);
                    $revision=$this->input->post('revision',false);if(!is_string($revision)||!preg_match('/^(0|[1-9][0-9]{0,8})$/D',$revision))throw new DomainException('company_ai_invalid');if($service->profile($issuer,$actor)['revision']!==(int)$revision)throw new DomainException('company_ai_stale');
                    if($action==='save'){$model=$this->input->post('model',false);$secret=$this->input->post('secret',false);$ops=$this->input->post('operations',false)??[];if(!is_string($model)||!is_string($secret)||!is_array($ops))throw new DomainException('company_ai_invalid');$service->save($issuer,$actor,(int)$revision,$model,$secret,$ops,fn($v)=>$this->encryption->encrypt($v));}
                    elseif($action==='revoke')$service->revoke($issuer,$actor,(int)$revision);
                    elseif($action==='queue'){$source=$this->input->post('source_hash',false);$key=$this->input->post('request_key',false);$consent=$this->input->post('consent',false);if(!$ticket||!is_string($source)||!is_string($key))throw new DomainException('company_ai_invalid');$service->queue($issuer,$actor,$ticket,$operation,$source,$key,$consent==='1');}
                    elseif(in_array($action,['dispatch','forget'],true)){$key=$this->input->post('request_key',false);if(!is_string($key))throw new DomainException('company_ai_invalid');if($action==='dispatch')$service->dispatch($issuer,$actor,$key,fn($v)=>$this->encryption->decrypt($v),fn($v)=>$this->encryption->encrypt($v));else$service->forget($issuer,$actor,$key);}
                    else throw new DomainException('company_ai_invalid');
                    CompanyRequestScope::invalidateCaches($this);redirect($url.($ticket?'?ticket='.$ticket.'&operation='.$operation:''),'location',303);return;
                }
                if(!in_array($method,['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
                $profile=$service->profile($issuer,$actor);if($ticket){$history=$service->history($issuer,$actor,$ticket);if($profile['status']==='configured'&&in_array($operation,$profile['operations'],true))$preview=$service->preview($issuer,$actor,$ticket,$operation);}
                $key=$this->input->get('result',false);if($key!==null){if(!is_string($key))throw new DomainException('company_ai_invalid');$this->load->library('encryption');$result=$service->result($issuer,$actor,$key,fn($v)=>$this->encryption->decrypt($v));}
            }catch(DomainException$e){$status=match($e->getMessage()){'company_ai_denied'=>403,'company_ai_stale','company_ai_conflict'=>409,default=>422};$this->output->set_status_header($status);$error=$status===403?'Sem acesso ao chamado ou à ação.':($status===409?'A origem ou configuração mudou. Reabra a prévia.':'Confira os limites, as permissões e o consentimento.');}catch(Throwable$e){$this->output->set_status_header(503);$error='Tentativa não confirmada. Consulte o histórico; não repita um resultado incerto.';}
            try{$profile=$service->profile($issuer,$actor);$acl=new IssuerResourceAccess($this->db,db_prefix());$canConfigure=$acl->can($issuer,$actor,'company-ai','configure')&&$acl->can($issuer,$actor,'settings','view')&&$acl->can($issuer,$actor,'settings','edit');$this->load->view('admin/tickets/native_ai',['profile'=>$profile,'ticket'=>$ticket,'operation'=>$operation,'preview'=>$preview,'history'=>$history,'result'=>$result,'error'=>$error,'csrf'=>$csrf,'can_configure'=>$canConfigure]);}catch(Throwable$e){show_error('Assistente privado indisponível.',403);}
        }
}
