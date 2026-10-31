<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Public portal identity/recovery; deliberately avoids the legacy theme, models and module hydration. */
class Portal extends CI_Controller
{
    public function index($issuer=null)
    {
        if(!PortalRequestScope::active()){show_404();return;}
        require_once APPPATH.'services/CompanyPortal.php';
        $this->output->set_header('Cache-Control: no-store');
        $this->output->set_header('Referrer-Policy: no-referrer');
        $this->output->set_header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'nonce-".($nonce=base64_encode(random_bytes(18)))."'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
        $this->output->set_header('X-Content-Type-Options: nosniff');
        try{$issuer=IssuerPolicy::id($issuer);$portal=new CompanyPortal($this->db,db_prefix(),(string)$this->config->item('encryption_key'));}
        catch(Throwable $error){show_404();return;}
        $invoices=null;$sales=[];$hostedCheckout=[];$bbCheckout=[];$bbInstrument=null;$operations=[];$operation_detail=null;$operation_kind=null;$project_discussions=null;$project_discussion=null;$discussion_receipt=null;$discussion_extras=null;$project_file_controls=null;$project_file_receipt=null;$project_gantt=null;$project_timesheets=null;$project_milestones=null;$project_tasks=null;$project_task=null;$task_receipt=null;$author_tasks=null;$task_write_controls=null;$project_task_create_controls=null;
        $sessionKey='company_portal_identity';$identity=$this->session->userdata($sessionKey);$message=null;$error=null;
        $csrf=$this->session->userdata('company_portal_csrf');
        if(!is_string($csrf)||strlen($csrf)!==64){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_portal_csrf',$csrf);}
        try{
            $current=is_array($identity)?$portal->sessionIdentity($identity,$issuer):null;
            if(!$current&&is_array($identity)&&($identity['issuer_id']??null)===$issuer)$this->session->unset_userdata($sessionKey);
            if($this->input->method(true)==='POST'){
                $posted=$this->input->post('portal_csrf',false);
                if(!is_string($posted)||!hash_equals($csrf,$posted)){show_error('Recarregue o formulário antes de continuar.',403);return;}
                $action=$this->input->post('action',false);
                if($action==='logout'){
                    $this->session->unset_userdata($sessionKey);$this->session->sess_regenerate(true);
                    redirect($this->config->site_url('company_portal/index/'.$issuer),'location',303);return;
                }
                if(is_string($action)&&in_array($action,['project_discussion_edit','project_discussion_delete','project_discussion_file_upload','project_discussion_file_hide','project_discussion_file_download'],true)){if(!$current||!is_array($identity)){show_404();return;}require_once APPPATH.'services/CompanyProjectDiscussionExtrasHttp.php';CompanyProjectDiscussionExtrasHttp::portal($this,$identity,$issuer,$action);return;}
                if(is_string($action)&&in_array($action,['project_discussion_open','project_discussion_comment'],true)){if(!$current||!is_array($identity)){show_404();return;}require_once APPPATH.'services/CompanyProjectDiscussionsHttp.php';CompanyProjectDiscussionsHttp::portal($this,$identity,$issuer,$action);return;}
                if(is_string($action)&&in_array($action,['project_contact_file_upload','project_contact_file_hide','project_contact_file_download','project_contact_files_bundle'],true)){if(!$current||!is_array($identity)){show_404();return;}require_once APPPATH.'services/CompanyProjectContactFilesHttp.php';CompanyProjectContactFilesHttp::handle($this,$identity,$issuer,$action);return;}
                if(is_string($action)&&in_array($action,['project_task_create','project_task_edit','project_task_comment','project_task_upload','project_task_hide','project_task_receipt_file'],true)){
                    if(!$current||!is_array($identity)){show_404();return;}require_once APPPATH.'services/CompanyPortalTaskHttp.php';CompanyPortalTaskHttp::handle($this,$identity,$issuer,$action);return;
                }
                if($action==='project_task_file'){
                    if(!$current||!is_array($identity)){show_404();return;}require_once APPPATH.'services/CompanyPortalProjectTasks.php';$fileKey=$this->input->post('file_key',false);if(!is_string($fileKey)){show_404();return;}
                    try{$tasks=new CompanyPortalProjectTasks($this->db,db_prefix(),(string)$this->config->item('encryption_key'));$projectId=$this->input->post('project_id',false);$taskId=$this->input->post('task_id',false);$file=$tasks->file($identity,$issuer,$projectId,$taskId,$fileKey);$tasks->confirmFile($identity,$issuer,$projectId,$taskId,$fileKey,$file['content_hash']);}catch(DomainException $denied){show_404();return;}
                    $this->output->set_header("Content-Security-Policy: sandbox; default-src 'none'");$this->output->set_header('Content-Disposition: attachment; filename="anexo.'.($file['mime']==='image/png'?'png':'pdf').'"; filename*=UTF-8\'\''.rawurlencode($file['filename']));$this->output->set_content_type($file['mime'])->set_output($file['content']);return;
                }
                if($action==='contract_discussion'){
                    if(!$current||!is_array($identity)){show_404();return;}require_once APPPATH.'services/CompanyContractDiscussions.php';$key=$this->input->post('request_key',false);$body=$this->input->post('discussion_body',false);$id=$this->input->post('resource_id',false);
                    try{if(!is_string($key)||!is_string($body))throw new DomainException('company_contract_discussion_invalid');(new CompanyContractDiscussions($this->db,db_prefix(),$portal))->contactAdd($identity,$issuer,$id,$this->input->post('revision',false),$body,$key);}
                    catch(DomainException $denied){$reason=$denied->getMessage();if($reason==='company_contract_discussion_unavailable'){show_404();return;}$status=match($reason){'company_contract_discussion_stale','company_contract_discussion_request_conflict'=>409,'company_contract_discussion_limit'=>429,default=>422};show_error($status===409?'O contrato ou a conversa mudou. Reabra antes de comentar.':($status===429?'O contrato atingiu o limite de500 comentários.':'Escreva um comentário de até8000 bytes, em texto.'),$status);return;}
                    redirect($this->config->site_url('company_portal/index/'.$issuer).'?operation_kind=contracts&resource_id='.(int)$id,'location',303);return;
                }
                if($action==='support_file_upload'||$action==='support_file_hide'){
                    if(!$current||!is_array($identity)){show_404();return;}require_once APPPATH.'services/CompanyPortalSupportFiles.php';require_once APPPATH.'services/CompanyOperationFilesHttp.php';$files=new CompanyPortalSupportFiles($this->db,db_prefix(),$portal);$key=$this->input->post('request_key',false);$id=$this->input->post('resource_id',false);$revision=$this->input->post('revision',false);
                    try{if(!is_string($key))throw new DomainException('company_portal_support_invalid');if($action==='support_file_upload'){[$name,$bytes]=CompanyOperationFilesHttp::upload();$id=$files->upload($identity,$issuer,$id,$revision,$key,$name,$bytes);}else{$file=$this->input->post('file_key',false);if(!is_string($file))throw new DomainException('company_portal_support_invalid');$id=$files->hide($identity,$issuer,$id,$revision,$file,$key);}}
                    catch(DomainException $denied){$reason=$denied->getMessage();if($reason==='company_portal_operation_unavailable'){show_404();return;}$status=match($reason){'company_portal_support_stale','company_portal_support_request_conflict','company_portal_support_closed'=>409,'company_operation_file_limit'=>429,default=>422};show_error($status===409?'O chamado mudou ou foi encerrado. Reabra antes de alterar anexos.':($status===429?'O chamado atingiu o limite de20 arquivos ou20 MiB, incluindo ocultos.':'Envie um PDF até2 MiB ou PNG/JPEG até512 KiB pelo formulário de anexo.'),$status);return;}
                    redirect($this->config->site_url('company_portal/index/'.$issuer).'?operation_kind=tickets&resource_id='.(int)$id,'location',303);return;
                }
                if($action==='support_open'||$action==='support_reply'){
                    if(!$current||!is_array($identity)){show_404();return;}require_once APPPATH.'services/CompanyPortalSupportWrite.php';$support=new CompanyPortalSupportWrite($this->db,db_prefix(),$portal);$key=$this->input->post('request_key',false);
                    try{if(!is_string($key))throw new DomainException('company_portal_support_invalid');if($action==='support_open'){$fields=$this->input->post('support',false);if(!is_array($fields))throw new DomainException('company_portal_support_invalid');$id=$support->open($identity,$issuer,$fields,$key);}else{$body=$this->input->post('reply',false);if(!is_string($body))throw new DomainException('company_portal_support_invalid');$id=$support->reply($identity,$issuer,$this->input->post('resource_id',false),$this->input->post('revision',false),$body,$key);}}
                    catch(DomainException $denied){$reason=$denied->getMessage();if($reason==='company_portal_operation_unavailable'){show_404();return;}$status=match($reason){'company_portal_support_stale','company_portal_support_request_conflict','company_portal_support_closed'=>409,'company_portal_support_limit'=>429,default=>422};show_error($status===409?'O chamado mudou ou foi encerrado. Reabra antes de enviar.':($status===429?'Limite de mensagens ou chamados atingido. Tente mais tarde.':'Confira o assunto, a mensagem e a prioridade antes de enviar.'),$status);return;}
                    redirect($this->config->site_url('company_portal/index/'.$issuer).'?operation_kind=tickets&resource_id='.(int)$id,'location',303);return;
                }
                if($action==='operation_file'||$action==='operation_contract_pdf'){
                    if(!$current||!is_array($identity)){show_404();return;}require_once APPPATH.'services/CompanyPortalOperations.php';$reader=new CompanyPortalOperations($this->db,db_prefix(),$portal);$id=$this->input->post('resource_id',false);
                    if($action==='operation_file'){$kind=$this->input->post('operation_kind',false);$fileKey=$this->input->post('file_key',false);if(!is_string($kind)||!is_string($fileKey)){show_404();return;}try{$file=$reader->file($identity,$issuer,$kind,$id,$fileKey);}catch(DomainException $denied){show_404();return;}$this->output->set_header("Content-Security-Policy: sandbox; default-src 'none'");$this->output->set_header('Content-Disposition: attachment; filename="anexo.'.($file['mime']==='image/png'?'png':'pdf').'"; filename*=UTF-8\'\''.rawurlencode($file['filename']));$this->output->set_content_type($file['mime'])->set_output($file['content']);return;}
                    try{$record=$reader->detail($identity,$issuer,'contracts',$id);require_once APPPATH.'services/CompanyContractsPdf.php';$pdf=CompanyContractsPdf::render($record);$reader->confirmPdf($identity,$issuer,$id,$record['revision'],hash('sha256',$record['snapshot_json']),hash('sha256',$pdf));}catch(DomainException $denied){show_404();return;}$this->output->set_header('Content-Disposition: attachment; filename="contrato-'.(int)$record['number'].'.pdf"');$this->output->set_content_type('application/pdf')->set_output($pdf);return;
                }
                if($action==='invoice_bb_checkout'){
                    if(!$current||!is_array($identity)){show_404();return;}
                    require_once FCPATH.'modules/banco_brasil/libraries/BancoBrasil/Checkout.php';
                    try{$bbInstrument=(new \BancoBrasil\Checkout($this->db,db_prefix()))->portalInstrument($portal,$identity,$issuer,$this->input->post('invoice_id',false));}
                    catch(DomainException $denied){show_404();return;}
                }
                if($action==='invoice_checkout'){
                    if(!$current||!is_array($identity)){show_404();return;}
                    require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyHostedLinks.php';
                    try{$link=(new \Asaas\CompanyHostedLinks($this->db,db_prefix()))->portalLink($portal,$identity,$issuer,$this->input->post('invoice_id',false));}catch(DomainException $denied){show_404();return;}
                    $this->output->set_header('Referrer-Policy: no-referrer');redirect($link['url'],'location',303);return;
                }
                if($action==='invoice_attachment'){
                    if(!$current||!is_array($identity)){show_404();return;}
                    require_once APPPATH.'services/CompanyPortalInvoices.php';require_once APPPATH.'services/CompanyInvoiceAttachments.php';
                    $invoice=$this->input->post('invoice_id',false);$key=$this->input->post('file_key',false);if(!is_string($key)){show_404();return;}
                    try{$file=(new CompanyInvoiceAttachments($this->db,db_prefix()))->portalDownload(new CompanyPortalInvoices($this->db,db_prefix(),$portal),$identity,$issuer,$invoice,$key);}catch(DomainException $denied){show_404();return;}
                    $this->output->set_header("Content-Security-Policy: sandbox; default-src 'none'");
                    $this->output->set_header('Content-Disposition: attachment; filename="anexo.'.($file['mime']==='image/png'?'png':'pdf').'"; filename*=UTF-8\'\''.rawurlencode($file['filename']));$this->output->set_content_type($file['mime'])->set_output($file['content']);return;
                }
                if($action==='sales_file'){
                    if(!$current||!is_array($identity)){show_404();return;}require_once APPPATH.'services/CompanySalesExtrasHttp.php';
                    $id=$this->input->post('document_id',false);$key=$this->input->post('file_key',false);if(!is_string($key)){show_404();return;}
                    try{$file=(new CompanySalesFiles($this->db,db_prefix()))->portalDownload($portal,$identity,$issuer,$id,$key);}catch(DomainException $denied){show_404();return;}CompanySalesExtrasHttp::output($this,$file);return;
                }
                if($action==='sales_pdf'){
                    if(!$current||!is_array($identity)){show_404();return;}
                    require_once APPPATH.'services/CompanyPortalSales.php';require_once APPPATH.'services/CompanySalesPdf.php';
                    $documents=new CompanyPortalSales($this->db,db_prefix(),$portal);$id=$this->input->post('document_id',false);
                    try{$document=$documents->document($identity,$issuer,$id);if(!class_exists('TCPDF'))require_once APPPATH.'vendor/tecnickcom/tcpdf/tcpdf.php';$pdf=CompanySalesPdf::prepare($document)->Output('','S');$documents->confirmDownload($identity,$issuer,$id,hash('sha256',$pdf));}catch(DomainException $denied){show_404();return;}
                    $this->output->set_header('Content-Disposition: attachment; filename="documento-'.(int)$id.'.pdf"');$this->output->set_content_type('application/pdf')->set_output($pdf);return;
                }
                if($action==='invoice_pdf'){
                    if(!$current||!is_array($identity)){show_404();return;}
                    require_once APPPATH.'services/CompanyPortalInvoices.php';
                    $documents=new CompanyPortalInvoices($this->db,db_prefix(),$portal);$invoice=$this->input->post('invoice_id',false);
                    try{
                        $dto=$documents->document($identity,$issuer,$invoice);
                        if(!class_exists('TCPDF'))require_once APPPATH.'vendor/tecnickcom/tcpdf/tcpdf.php';
                        require_once APPPATH.'libraries/pdf/Issuer_invoice_pdf.php';
                        $pdf=(new Issuer_invoice_pdf($dto))->prepare()->Output('','S');
                        $documents->confirmDownload($identity,$issuer,$invoice,hash('sha256',$pdf));
                    }catch(DomainException $denied){show_404();return;}
                    $this->output->set_header('Content-Disposition: attachment; filename="fatura-'.(int)$invoice.'.pdf"');
                    $this->output->set_content_type('application/pdf')->set_output($pdf);return;
                }
                if(!in_array($action,['login','recovery','reset'],true))throw new DomainException('company_portal_invalid_input');
                $allowed=$portal->allowAttempt($issuer,$this->input->ip_address(),$action);
                $email=$this->input->post('email',false);$password=$this->input->post('password',false);
                if($action==='recovery'){
                    if($allowed&&is_string($email)){
                        $this->load->library('encryption');
                        require_once APPPATH.'services/CompanyPortalRecoveryMail.php';
                        (new CompanyPortalRecoveryMail($this->db,db_prefix(),(string)$this->config->item('encryption_key')))->requestRecovery($issuer,$email,$this->config->base_url(),fn($value)=>$this->encryption->decrypt($value));
                    }
                    $message='Se houver uma conta ativa nesta empresa e o envio estiver disponível, você receberá um link. Aguarde antes de solicitar novamente.';
                }elseif($action==='login'){
                    require_once APPPATH.'third_party/phpass.php';
                    $hasher=new PasswordHash(PHPASS_HASH_STRENGTH,PHPASS_HASH_PORTABLE);
                    $authenticated=$allowed&&is_string($email)&&is_string($password)?$portal->login($issuer,$email,$password,static fn($value,$hash)=>$hasher->CheckPassword($value,$hash)):null;
                    if($authenticated){
                        $this->session->sess_regenerate(true);$this->session->set_userdata($sessionKey,$authenticated);
                        $this->session->set_userdata('company_portal_csrf',bin2hex(random_bytes(32)));
                        redirect($this->config->site_url('company_portal/index/'.$issuer),'location',303);return;
                    }
                    $this->output->set_status_header(422);$error='Não foi possível entrar. Confira a empresa, o e-mail e a senha, ou aguarde antes de tentar novamente.';
                }else{
                    $token=$this->input->post('token',false);$repeat=$this->input->post('password_repeat',false);
                    require_once APPPATH.'third_party/phpass.php';
                    $hasher=new PasswordHash(PHPASS_HASH_STRENGTH,PHPASS_HASH_PORTABLE);
                    if($allowed&&is_string($password)&&$password===$repeat&&is_string($token)&&$portal->reset($issuer,$token,$password,static fn($value)=>$hasher->HashPassword($value))){
                        $this->session->unset_userdata($sessionKey);$this->session->sess_regenerate(true);$current=null;
                        $message='Senha alterada. Entre novamente nesta empresa.';
                    }else{$this->output->set_status_header(422);$error='Não foi possível alterar a senha. Use um link válido e uma senha de pelo menos 12 caracteres. Se necessário, solicite outro link.';}
                }
                // POST/refresh never echoes a bearer or password into HTML.
            }elseif(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
        }catch(Throwable $exception){
            log_message('error','Company portal operation unavailable; no identity, password or token logged.');
            // Public recovery has the same response for an unknown account and unavailable mail config.
            if($this->input->post('action',false)==='recovery')$message='Se houver uma conta ativa nesta empresa e o envio estiver disponível, você receberá um link. Aguarde antes de solicitar novamente.';
            else{$this->output->set_status_header(503);$error='Serviço indisponível. Tente novamente mais tarde.';}
        }
        if(($current??null)&&is_array($identity)){
            try{require_once APPPATH.'services/CompanyPortalInvoices.php';$invoices=(new CompanyPortalInvoices($this->db,db_prefix(),$portal))->page($identity,$issuer,$this->input->get('before',false)??0);}
            catch(Throwable $unavailable){$error='Faturas indisponíveis no momento. Tente novamente mais tarde.';}
            if(is_array($invoices)&&$this->db->table_exists(db_prefix().'bb_checkout_publications')){try{require_once FCPATH.'modules/banco_brasil/libraries/BancoBrasil/Checkout.php';$bbCheckout=(new \BancoBrasil\Checkout($this->db,db_prefix()))->portalPage($portal,$identity,$issuer,array_column($invoices['items'],'id'));}catch(Throwable $unavailable){$bbCheckout=[];}}
            if(is_array($invoices)&&$this->db->table_exists(db_prefix().'asaas_company_hosted_links')){try{require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyHostedLinks.php';$hostedCheckout=(new \Asaas\CompanyHostedLinks($this->db,db_prefix()))->portalPage($portal,$identity,$issuer,array_column($invoices['items'],'id'));}catch(Throwable $unavailable){$hostedCheckout=[];}}
            try{require_once APPPATH.'services/CompanyPortalSales.php';$salesReader=new CompanyPortalSales($this->db,db_prefix(),$portal);foreach(['estimates','proposals'] as $kind)$sales[$kind]=$salesReader->page($identity,$issuer,$kind);$ids=[];foreach($sales as $rows)foreach($rows??[] as $row)$ids[]=$row['id'];if($ids){require_once APPPATH.'services/CompanySalesFiles.php';$published=(new CompanySalesFiles($this->db,db_prefix()))->portalPages($portal,$identity,$issuer,$ids);foreach($sales as &$rows)if(is_array($rows))foreach($rows as &$row)$row['files']=$published[(int)$row['id']]??[];unset($rows,$row);}}
            catch(Throwable $unavailable){$error='Documentos indisponíveis no momento. Tente novamente mais tarde.';}
            try{require_once APPPATH.'services/CompanyPortalOperations.php';$reader=new CompanyPortalOperations($this->db,db_prefix(),$portal);foreach(['contracts','tickets','projects'] as $kind)$operations[$kind]=$reader->page($identity,$issuer,$kind,$this->input->get('op_after_'.$kind,false)??0);$requestedKind=$this->input->get('operation_kind',false);$requestedId=$this->input->get('resource_id',false);if($requestedKind!==null||$requestedId!==null){if(!is_string($requestedKind)||!in_array($requestedKind,['contracts','tickets','projects'],true)){show_404();return;}try{$operation_detail=$reader->detail($identity,$issuer,$requestedKind,$requestedId);$operation_kind=$requestedKind;}catch(DomainException $denied){show_404();return;}}}
            catch(Throwable $unavailable){$operations=[];$operation_detail=null;$operation_kind=null;$error='Operações indisponíveis no momento. Tente novamente mais tarde.';}

            foreach(['discussion_post','discussion_parent','discussion_change_receipt','discussion_file_receipt'] as $extraQuery){$candidate=$this->input->get($extraQuery,false);if($candidate!==null&&($operation_kind!=='projects'||!is_array($operation_detail)||(!is_string($candidate)&&!is_int($candidate)))){show_404();return;}}
            $requestedDiscussion=$this->input->get('project_discussion',false);$requestedDiscussionReceipt=$this->input->get('discussion_receipt',false);if(($requestedDiscussion!==null||$requestedDiscussionReceipt!==null)&&($operation_kind!=='projects'||!is_array($operation_detail)||($requestedDiscussionReceipt!==null&&!is_string($requestedDiscussionReceipt)))){show_404();return;}
            $requestedProjectFile=$this->input->get('project_file_receipt',false);if($requestedProjectFile!==null&&(!is_string($requestedProjectFile)||$operation_kind!=='projects'||!is_array($operation_detail))){show_404();return;}$timesheetsCursor=$this->input->get('timesheets_before',false);if($timesheetsCursor!==null&&(!is_string($timesheetsCursor)||$operation_kind!=='projects'||!is_array($operation_detail))){show_404();return;}$requestedTask=$this->input->get('project_task',false);$tasksAfter=$this->input->get('tasks_after',false);$requestedReceipt=$this->input->get('task_receipt',false);$authorAfter=$this->input->get('author_tasks_after',false);
            if(($requestedTask!==null||$tasksAfter!==null||$requestedReceipt!==null||$authorAfter!==null)&&($operation_kind!=='projects'||!is_array($operation_detail))){show_404();return;}
            if($operation_kind==='projects'&&is_array($operation_detail)){
                try{require_once APPPATH.'services/CompanyPortalProjectDiscussions.php';$discussionReader=new CompanyPortalProjectDiscussions($this->db,db_prefix(),(string)$this->config->item('encryption_key'));$project_discussions=$discussionReader->page($identity,$issuer,$operation_detail['id']);if($requestedDiscussion!==null)$project_discussion=$discussionReader->detail($identity,$issuer,$operation_detail['id'],$requestedDiscussion);if($requestedDiscussionReceipt!==null)$discussion_receipt=$discussionReader->receipt($identity,$issuer,$operation_detail['id'],$requestedDiscussionReceipt);require_once APPPATH.'services/CompanyProjectContactFileReceipts.php';$projectFileReader=new CompanyProjectContactFileReceipts($this->db,db_prefix(),(string)$this->config->item('encryption_key'));$project_file_controls=$projectFileReader->controls($identity,$issuer,$operation_detail['id']);if($requestedProjectFile!==null)$project_file_receipt=$projectFileReader->get($identity,$issuer,$operation_detail['id'],$requestedProjectFile);require_once APPPATH.'services/CompanyPortalProjectGantt.php';$project_gantt=(new CompanyPortalProjectGantt($this->db,db_prefix(),(string)$this->config->item('encryption_key')))->page($identity,$issuer,$operation_detail['id']);require_once APPPATH.'services/CompanyPortalProjectTimesheets.php';$project_timesheets=(new CompanyPortalProjectTimesheets($this->db,db_prefix(),(string)$this->config->item('encryption_key')))->page($identity,$issuer,$operation_detail['id'],$timesheetsCursor);if($timesheetsCursor!==null&&$project_timesheets===null){show_404();return;}require_once APPPATH.'services/CompanyPortalProjectMilestones.php';$project_milestones=(new CompanyPortalProjectMilestones($this->db,db_prefix(),(string)$this->config->item('encryption_key')))->page($identity,$issuer,$operation_detail['id']);require_once APPPATH.'services/CompanyPortalProjectTasks.php';$tasks=new CompanyPortalProjectTasks($this->db,db_prefix(),(string)$this->config->item('encryption_key'));$project_tasks=$tasks->page($identity,$issuer,$operation_detail['id'],$tasksAfter??0);if(($requestedTask!==null||$tasksAfter!==null)&&$project_tasks===null){show_404();return;}if($requestedTask!==null)$project_task=$tasks->detail($identity,$issuer,$operation_detail['id'],$requestedTask);require_once APPPATH.'services/CompanyPortalTaskWriteControls.php';require_once APPPATH.'services/CompanyPortalTaskReceipts.php';$controls=new CompanyPortalTaskWriteControls($this->db,db_prefix(),(string)$this->config->item('encryption_key'));$project_task_create_controls=$controls->get($identity,$issuer,$operation_detail['id']);$author_tasks=(new CompanyPortalTaskReceipts($this->db,db_prefix(),(string)$this->config->item('encryption_key')))->page($identity,$issuer,$operation_detail['id'],$authorAfter??0);if($requestedReceipt!==null){if(!is_string($requestedReceipt)){show_404();return;}$task_receipt=(new CompanyPortalTaskReceipts($this->db,db_prefix(),(string)$this->config->item('encryption_key')))->get($identity,$issuer,$operation_detail['id'],$requestedReceipt);$task_write_controls=$controls->get($identity,$issuer,$operation_detail['id'],$task_receipt['task_id']);}elseif($project_task!==null)$task_write_controls=$controls->get($identity,$issuer,$operation_detail['id'],$project_task['id']);}
                catch(DomainException $denied){show_404();return;}
                catch(Throwable $unavailable){$project_tasks=null;$project_task=null;$task_receipt=null;$author_tasks=null;$task_write_controls=null;$project_task_create_controls=null;show_error('Operações do projeto indisponíveis no momento. Tente novamente mais tarde.',503);return;}
            }

        }
        if(is_array($project_discussions)){try{require_once APPPATH.'services/CompanyProjectDiscussionExtrasPage.php';$discussion_extras=CompanyProjectDiscussionExtrasPage::get($this,$identity,$issuer,$operation_detail['id'],$project_discussion);}catch(DomainException $denied){show_404();return;}catch(Throwable $unavailable){log_message('error','Project discussion subresources unavailable; contents omitted.');show_error('Não foi possível abrir os comentários e anexos.',503);return;}}elseif($this->input->get('discussion_post',false)!==null||$this->input->get('discussion_parent',false)!==null||$this->input->get('discussion_change_receipt',false)!==null||$this->input->get('discussion_file_receipt',false)!==null){show_404();return;}
        if(is_array($project_discussions)){try{(new CompanyPortalProjectDiscussions($this->db,db_prefix(),(string)$this->config->item('encryption_key')))->confirm($identity,$issuer,$operation_detail['id'],$project_discussions,$project_discussion,$discussion_receipt);}catch(DomainException $denied){show_404();return;}catch(Throwable $unavailable){log_message('error','Project discussion response not confirmed; contents omitted.');show_error('Não foi possível confirmar as conversas. Reabra o projeto.',503);return;}}
        if(is_array($discussion_extras)){try{CompanyProjectDiscussionExtrasPage::confirm($this,$identity,$issuer,$operation_detail['id'],$discussion_extras);}catch(DomainException $denied){show_404();return;}catch(Throwable $unavailable){log_message('error','Project discussion subresource response not confirmed; contents omitted.');show_error('Não foi possível confirmar os comentários e anexos.',503);return;}}
        // Recheck after other page projections, immediately before disclosing the instrument.
        if($bbInstrument!==null){
            try{$bbInstrument=(new \BancoBrasil\Checkout($this->db,db_prefix()))->portalInstrument($portal,$identity,$issuer,$bbInstrument['invoice_id']);}
            catch(Throwable $denied){$bbInstrument=null;}
        }
        $this->load->view('company_portal/index',['discussion_extras'=>$discussion_extras,'project_discussions'=>$project_discussions,'project_discussion'=>$project_discussion,'discussion_receipt'=>$discussion_receipt,'project_file_controls'=>$project_file_controls,'project_file_receipt'=>$project_file_receipt,'project_gantt'=>$project_gantt,'project_timesheets'=>$project_timesheets,'project_milestones'=>$project_milestones,'author_tasks'=>$author_tasks,'task_receipt'=>$task_receipt,'task_write_controls'=>$task_write_controls,'project_task_create_controls'=>$project_task_create_controls,'project_tasks'=>$project_tasks,'project_task'=>$project_task,'bb_checkout'=>$bbCheckout,'bb_instrument'=>$bbInstrument,'hosted_checkout'=>$hostedCheckout,'operations'=>$operations,'operation_detail'=>$operation_detail,'operation_kind'=>$operation_kind,'sales'=>$sales,'invoices'=>$invoices,'issuer'=>$issuer,'identity'=>$current??null,'message'=>$message,'error'=>$error,'portal_csrf'=>$csrf,'script_nonce'=>$nonce]);
    }
}
