<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanySettingsForms.php';
require_once __DIR__.'/NativeSettingsSections.php';
/** Adapter for Settings::index and the existing admin/settings/all template only. */
final class NativeCompanySettingsController
{
    /** Known form failures have stable HTTP meanings; unknown errors remain denied. */
    private static function domainStatus(string $reason):int
    {
        return match($reason){
            'native_settings_stale','native_clients_settings_stale','native_google_settings_stale',
            'native_calendar_preferences_stale','native_mail_settings_stale','native_ticket_preferences_stale',
            'company_calendar_preferences_stale',
            'native_invoice_sequence_stale','native_sales_sequence_stale','native_sales_settings_stale','company_task_preferences_stale','company_task_operation_preferences_stale',
            'company_task_timer_preferences_stale','company_task_timer_stop_preferences_stale','company_ticket_preferences_stale',
            'company_pdf_typography_stale','company_project_file_settings_stale'=>409,
            'native_settings_invalid','company_settings_invalid_input','issuer_invalid_name','issuer_invalid_tax_id',
            'native_custom_field_required','native_clients_settings_invalid','native_google_settings_invalid',
            'native_calendar_preferences_invalid','native_mail_settings_invalid','native_misc_settings_invalid',
            'company_calendar_preferences_invalid',
            'native_task_settings_invalid','native_ticket_preferences_invalid','native_invoice_sequence_invalid',
            'native_sales_settings_invalid','native_sales_sequence_invalid','native_sales_sequence_regression','native_sales_sequence_unemittable',
            'company_task_preferences_invalid','company_task_operation_preferences_invalid',
            'company_task_timer_preferences_invalid','company_task_timer_stop_preferences_invalid',
            'company_ticket_preferences_invalid','company_pdf_typography_invalid','company_project_file_settings_invalid'=>422,
            'company_calendar_source_unavailable'=>503,
            'company_settings_write_unconfirmed'=>503,
            default=>403,
        };
    }
    public static function testMail($ci):void
    {
        try{
            if(!CompanyRequestScope::nativeFor($ci))throw new DomainException('native_mail_test_denied');CompanyRequestScope::assertNativeMutation($ci);
            $post=$ci->input->post(null,false);$csrf=$ci->security->get_csrf_token_name();
            if(!is_array($post)||array_diff(array_keys($post),['test_email','_native_company_csrf','_company_context',$csrf])||!is_string($post['test_email']??null))throw new DomainException('native_mail_test_invalid');
            $scope=CompanyRequestScope::context();$ci->load->library('encryption');require_once __DIR__.'/NativeCompanyMailTest.php';
            $sent=(new NativeCompanyMailTest($ci->db,db_prefix()))->send($scope->issuerId(),$scope->staffId(),$post['test_email'],fn($v)=>$ci->encryption->decrypt($v),fn($v)=>$ci->encryption->encrypt($v),static function(array $config,object $email,array $headers):bool{
                require_once APPPATH.'libraries/App_mailer.php';$mailer=new App_mailer($config);
                $mailer->from($headers['from'],$headers['fromName']);$mailer->to($email->email);$mailer->subject($headers['subject']);$mailer->message($email->message);
                // No global hooks, BCC, attachments or debug output on the test path.
                return $mailer->send()===true;
            });
            $ci->output->set_header('Cache-Control: no-store');$ci->output->set_content_type('application/json');$ci->output->set_output(json_encode(['confirmed'=>$sent,'message'=>$sent?'O transporte confirmou o envio de teste. Confira a caixa do destinatário.':'O envio não foi confirmado. A tentativa não será repetida automaticamente.'],JSON_THROW_ON_ERROR));
        }catch(Throwable $error){$ci->output->set_status_header($error instanceof DomainException?422:503);$ci->output->set_header('Cache-Control: no-store');$ci->output->set_content_type('application/json');$ci->output->set_output(json_encode(['confirmed'=>false,'message'=>'Não foi possível confirmar o teste desta empresa. Confira suas permissões e a configuração salva.'],JSON_THROW_ON_ERROR));}
    }
    public static function removeQueue($ci,$id=null):void
    {
        try{
            if(!CompanyRequestScope::nativeFor($ci))throw new DomainException('native_mail_queue_denied');
            CompanyRequestScope::assertNativeMutation($ci);$scope=CompanyRequestScope::context();$post=$ci->input->post(null,false);$csrf=$ci->security->get_csrf_token_name();
            if(!is_array($post)||array_diff(array_keys($post),['_native_company_csrf','_company_context',$csrf]))throw new DomainException('native_mail_queue_denied');
            require_once __DIR__.'/NativeCompanyMailQueue.php';
            (new NativeCompanyMailQueue($ci->db,db_prefix()))->remove($scope->issuerId(),$scope->staffId(),$id===null?null:IssuerPolicy::id($id));
            redirect(admin_url('settings?group=email&tab=email_queue'),'location',303);
        }catch(Throwable $error){show_error('Não foi possível confirmar a remoção da fila desta empresa. Recarregue antes de continuar.',$error instanceof DomainException?403:503);}
    }
    public static function removeAsset($ci,string $kind):void
    {
        try{
            if(!CompanyRequestScope::nativeFor($ci))throw new DomainException('native_settings_denied');
            CompanyRequestScope::assertNativeMutation($ci);$context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();
            if(!(new IssuerResourceAccess($ci->db,db_prefix()))->can($issuer,$actor,'settings','edit'))throw new DomainException('native_settings_denied');
            $post=$ci->input->post(null,false);$csrf=$ci->security->get_csrf_token_name();
            if(!is_array($post)||array_diff(array_keys($post),['revision','_native_company_csrf','_company_context',$csrf])||!is_string($post['revision']??null))throw new DomainException('native_settings_invalid');
            (new CompanyAssets($ci->db,db_prefix()))->save($issuer,$actor,$kind,null,$post['revision']);CompanyRequestScope::invalidateCaches($ci);
            $ci->output->set_header('Cache-Control: no-store');$ci->output->set_content_type('application/json');$ci->output->set_output(json_encode(['success'=>true],JSON_THROW_ON_ERROR));
        }catch(DomainException $e){show_error('Não foi possível remover esta imagem. Recarregue e confira suas permissões.',$e->getMessage()==='company_asset_stale'?409:403);}
    }
    public static function downloadAsset($ci,string $kind):void
    {
        try{
            if(!CompanyRequestScope::nativeFor($ci)||!in_array($kind,['logo','logo_dark','favicon','signature'],true))throw new DomainException('native_settings_denied');
            $method=strtoupper($ci->input->method(true));if(!in_array($method,['GET','HEAD'],true))throw new DomainException('native_settings_method');
            $context=CompanyRequestScope::context();$revision=$ci->input->get('revision',false);
            if(!is_string($revision)||!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('native_settings_invalid');
            $bytes=(new CompanyAssets($ci->db,db_prefix()))->download($context->issuerId(),$context->staffId(),$kind,$revision);
            $ci->output->set_header('Cache-Control: no-store, private, max-age=0')->set_header('Pragma: no-cache')->set_header('X-Content-Type-Options: nosniff');
            $ci->output->set_content_type('image/png')->set_header('Content-Disposition: attachment; filename="company-'.$kind.'.png"');
            $ci->output->set_output($method==='HEAD'?'':$bytes);
        }catch(DomainException $e){$status=$e->getMessage()==='company_asset_stale'?409:($e->getMessage()==='company_asset_missing'?404:403);show_error('Imagem indisponível para esta empresa. Reabra as definições.',$status);}
        catch(Throwable $e){show_error('Não foi possível confirmar a imagem desta empresa.',503);}
    }
    private static function certificateUpload($upload):string
    {
        if(!is_array($upload)||!is_int($upload['error']??null)||$upload['error']!==UPLOAD_ERR_OK
            ||!is_string($upload['name']??null)||!preg_match('/\.(?:pfx|p12)$/iD',$upload['name'])
            ||!is_int($upload['size']??null)||$upload['size']<1||$upload['size']>IssuerCertificateParser::MAX_BYTES
            ||!is_string($upload['tmp_name']??null)||!is_uploaded_file($upload['tmp_name']))throw new DomainException('issuer_certificate_invalid_upload');
        $bytes=@file_get_contents($upload['tmp_name'],false,null,0,IssuerCertificateParser::MAX_BYTES+1);
        if(!is_string($bytes)||$bytes===''||strlen($bytes)>IssuerCertificateParser::MAX_BYTES)throw new DomainException('issuer_certificate_invalid_upload');
        return$bytes;
    }
    private static function certificateStatus(string $reason):int
    {
        return match($reason){
            'issuer_certificate_denied','native_company_csrf_invalid','native_settings_denied'=>403,
            'company_context_mismatch','issuer_certificate_stale'=>409,
            'issuer_certificate_crypto_unavailable'=>503,
            default=>422,
        };
    }
    private static function certificateMessage(string $reason):string
    {
        return match($reason){
            'issuer_certificate_denied'=>'Seu acesso mudou. Reabra a empresa.',
            'issuer_certificate_stale'=>'Este cadastro mudou em outra aba. Recarregue antes de enviar novamente.',
            'issuer_certificate_invalid_upload'=>'Envie um arquivo .pfx ou .p12 de até 2 MiB e confira a senha.',
            'issuer_certificate_parse_failed'=>'Não foi possível interpretar o PFX/P12. Confira se o arquivo está completo e foi exportado com o certificado e sua chave privada. O cadastro anterior foi preservado.',
            'issuer_certificate_algorithm_unsupported'=>'O PFX/P12 usa um algoritmo indisponível neste servidor. Solicite uma nova exportação com AES-256 e PBKDF2, incluindo a chave privada. O cadastro anterior foi preservado.',
            'issuer_certificate_authentication_failed'=>'Não foi possível verificar a senha e a integridade do PFX/P12. Confira a senha localmente e, se persistir, solicite uma nova exportação. O cadastro anterior foi preservado.',
            'issuer_certificate_legacy_unavailable'=>'A importação compatível não pôde ser concluída com os limites de segurança. Solicite uma nova exportação com AES-256 e PBKDF2. O cadastro anterior foi preservado.',
            'issuer_certificate_key_required'=>'O arquivo precisa conter o certificado e sua chave privada correspondente.',
            'issuer_certificate_expired'=>'O certificado enviado está expirado. O cadastro anterior foi preservado.',
            'issuer_certificate_not_yet_valid'=>'O certificado enviado ainda não está válido. O cadastro anterior foi preservado.',
            'issuer_certificate_document_mismatch'=>'O CNPJ do certificado não corresponde ao cadastro desta empresa. A substituição foi bloqueada.',
            'issuer_certificate_document_invalid'=>'O CNPJ do certificado é inválido ou ambíguo. A substituição foi bloqueada.',
            'issuer_certificate_crypto_unavailable'=>'Cifra indisponível. O administrador deve conferir a chave já existente do CRM e a extensão OpenSSL.',
            'issuer_certificate_envelope_invalid'=>'Não foi possível verificar a integridade do certificado armazenado. Substitua ou remova o cadastro após revisão administrativa.',
            default=>'Operação não confirmada. Confira o cadastro e suas permissões.',
        };
    }
    /** Existing Settings page; no certificate-specific controller, route or shell. */
    private static function certificate($ci,int $issuer,int $actor,array $sections,array $selected,NativeCompanySettingsForms $forms):void
    {
        $ci->output->set_header('Cache-Control: no-store')->set_header('Pragma: no-cache')->set_header('Referrer-Policy: no-referrer');$error=null;
        try{
            $forms->authorize($issuer,$actor,'edit');$key=$ci->config->item('encryption_key');if(!is_string($key)||strlen($key)!==32)throw new DomainException('issuer_certificate_crypto_unavailable');
            $ci->load->library('encryption');require_once APPPATH.'services/issuers/IssuerCertificateManager.php';$manager=new IssuerCertificateManager($ci->db,db_prefix(),$ci->encryption);$method=strtoupper($ci->input->method(true));
            if($method==='POST'){
                CompanyRequestScope::assertNativeMutation($ci);$post=$ci->input->post(null,false);$csrf=$ci->security->get_csrf_token_name();
                if(!is_array($post)||array_diff(array_keys($post),['action','certificate_password','native_settings_revision','_native_company_csrf','_company_context',$csrf])||array_diff(array_keys($_FILES),['certificate_file'])
                    ||!is_string($post['action']??null)||!is_string($post['native_settings_revision']??null)||!preg_match('/^(?:0|[1-9][0-9]{0,9})$/D',$post['native_settings_revision']))throw new DomainException('issuer_certificate_invalid_upload');
                $revision=(int)$post['native_settings_revision'];
                if($post['action']==='replace'){
                    if(!is_string($post['certificate_password']??null))throw new DomainException('issuer_certificate_invalid_upload');$pfx=self::certificateUpload($_FILES['certificate_file']??null);$manager->replace($issuer,$actor,$revision,$pfx,$post['certificate_password']);unset($pfx,$post['certificate_password']);$notice='Certificado armazenado de forma cifrada para esta empresa.';
                }elseif($post['action']==='remove'){
                    if(($post['certificate_password']??'')!=='')throw new DomainException('issuer_certificate_invalid_upload');$upload=$_FILES['certificate_file']??null;if($upload!==null&&(!is_array($upload)||($upload['error']??null)!==UPLOAD_ERR_NO_FILE))throw new DomainException('issuer_certificate_invalid_upload');$manager->remove($issuer,$actor,$revision);$notice='Material do certificado removido do cadastro ativo. Backups seguem a retenção operacional.';
                }else throw new DomainException('issuer_certificate_invalid_upload');
                $ci->session->set_flashdata('issuer_certificate_notice',$notice);CompanyRequestScope::invalidateCaches($ci);redirect(admin_url('settings?group=certificates'),'location',303);return;
            }
            if(!in_array($method,['GET','HEAD'],true))throw new DomainException('issuer_certificate_denied');
        }catch(DomainException $exception){$ci->output->set_status_header(self::certificateStatus($exception->getMessage()));$error=self::certificateMessage($exception->getMessage());}
        catch(Throwable $exception){$ci->output->set_status_header(503);$error='Operação não confirmada. Confira a atualização do núcleo antes de repetir.';log_message('error','Company certificate Settings operation unavailable; submitted values and crypto errors omitted.');}
        if(!isset($manager)){show_error($error??'Certificado indisponível.',503);return;}
        try{$page=$manager->page($issuer,$actor);}
        catch(DomainException $exception){show_error('Sem acesso ao certificado ou integridade não confirmada. Consulte o administrador.',403);return;}
        catch(Throwable $exception){show_error('Cadastro de certificados indisponível. Confira a atualização do núcleo.',503);return;}
        $ci->load->helper(['fields','form']);$ci->load->view('admin/settings/all',[
            'title'=>_l('options'),'sections'=>$sections,'group'=>$selected,'admin_tabs'=>['update','info','argws_platform'],'native_settings'=>true,
            'native_settings_revision'=>(string)$page['revision'],'native_settings_assets'=>null,'native_settings_can_edit'=>true,
            'native_settings_certificate'=>$page,'native_settings_certificate_error'=>$error,'native_settings_certificate_notice'=>$ci->session->flashdata('issuer_certificate_notice'),
        ]);
    }
    public static function index($ci):void
    {
        try{
            if(!CompanyRequestScope::nativeFor($ci)||!CompanySettingsRuntime::active())throw new DomainException('native_settings_denied');
            $context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();
            if($issuer!==CompanySettingsRuntime::issuerId()||$actor!==CompanySettingsRuntime::staffId())throw new DomainException('native_settings_denied');
            $forms=new NativeCompanySettingsForms($ci->db,db_prefix());$forms->authorize($issuer,$actor,'view');
            $group=$ci->input->get('group')?:'general';if(!is_string($group))throw new DomainException('native_settings_invalid');
            $supported=array_fill_keys(NativeCompanySettingsForms::availableGroups(),true);if(!CompanySettingsRuntime::can('edit'))unset($supported['certificates']);
            $sections=NativeSettingsSections::collect();
            foreach($sections as$key=>$section){$section['children']=array_values(array_filter($section['children'],static fn($child)=>isset($supported[$child['id']])));if($section['children']===[])unset($sections[$key]);else$sections[$key]=$section;}
            $selected=null;
            foreach($sections as$section)foreach($section['children']as$child)if($child['id']===$group)$selected=$child;
            if($selected===null)throw new DomainException('native_settings_group_unavailable');
            NativeCompanySettingsForms::fields($group);
            $method=strtoupper($ci->input->method(true));
            if($group==='certificates'){self::certificate($ci,$issuer,$actor,$sections,$selected,$forms);return;}
            if($method==='POST'){
                CompanyRequestScope::assertNativeMutation($ci);$forms->authorize($issuer,$actor,'edit');
                $post=$ci->input->post(null,false);$csrfName=$ci->security->get_csrf_token_name();
                if(!is_array($post)||array_diff(array_keys($post),['settings','custom_fields','fakeusernameremembered','fakepasswordremembered','test_email','native_settings_revision','_native_company_csrf','_company_context',$csrfName])||!is_array($post['settings']??null)||!is_string($post['native_settings_revision']??null))throw new DomainException('native_settings_invalid');
                foreach(['fakeusernameremembered','fakepasswordremembered','test_email']as$ignored)if(isset($post[$ignored])&&($group!=='email'||!is_string($post[$ignored])||strlen($post[$ignored])>4096))throw new DomainException('native_settings_invalid');
                $custom=$post['custom_fields']??[];if(!is_array($custom)||array_diff(array_keys($custom),['company'])||isset($custom['company'])&&!is_array($custom['company']))throw new DomainException('native_settings_invalid');
                $uploads=[];$fileNames=['company_logo'=>'logo','company_logo_dark'=>'logo_dark','favicon'=>'favicon','signature_image'=>'signature'];
                foreach($_FILES as$name=>$file){
                    if(!is_array($file))throw new DomainException('native_settings_invalid');
                    if(($file['error']??null)===UPLOAD_ERR_NO_FILE)continue;
                    if(($group!=='general'&&($group!=='pdf'||$name!=='signature_image'))||!isset($fileNames[$name])||($file['error']??null)!==UPLOAD_ERR_OK||!is_int($file['size']??null)||$file['size']<1||$file['size']>524288||!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name']))throw new DomainException('native_settings_invalid');
                    $bytes=file_get_contents($file['tmp_name'],false,null,0,524289);
                    if(!is_string($bytes)||strlen($bytes)!==$file['size'])throw new DomainException('native_settings_invalid');
                    $uploads[$fileNames[$name]]=$bytes;
                }
                $encrypt=null;$decrypt=null;if(in_array($group,['email','google'],true)){$ci->load->library('encryption');$encrypt=fn($v)=>$ci->encryption->encrypt($v);$decrypt=fn($v)=>$ci->encryption->decrypt($v);}
                $forms->save($issuer,$actor,$group,$post['settings'],$custom['company']??[],$post['native_settings_revision'],$uploads,$encrypt,$decrypt);
                CompanyRequestScope::invalidateCaches($ci);
                set_alert('success','Definições salvas para esta empresa.');
                redirect(admin_url('settings?group='.rawurlencode($group)),'location',303);return;
            }
            if(!in_array($method,['GET','HEAD'],true))throw new DomainException('native_settings_method');
            $page=$forms->page($issuer,$actor,$group);
            if($group==='email'){require_once __DIR__.'/NativeCompanyMailQueue.php';$page['queue']=(new NativeCompanyMailQueue($ci->db,db_prefix()))->page($issuer,$actor);$cron=IssuerStorage::query($ci->db,'SELECT value FROM '.db_prefix().'options WHERE name=?',['cron_has_run_from_cli'])->row_array();$page['cron_ready']=($cron['value']??'')==='1'?'1':'0';$page['command_fields']=['_native_company_csrf'=>CompanyRequestScope::nativeCsrfToken($ci),'_company_context'=>CompanyRequestScope::token(),$ci->security->get_csrf_token_name()=>$ci->security->get_csrf_hash()];}
            $ci->load->helper(['fields','form']);
            $nativeTaxDropdown=null;$ticketStatuses=null;$ticketPriorities=null;$taskStatuses=null;
            $proposalFields=$group==='proposals'?(new NativeCompanyCustomFields($ci->db,db_prefix()))->definitionsForSalesSettings($issuer,$actor,'proposal'):null;
            if($group==='tasks'){require_once __DIR__.'/WorkStatusPresentation.php';$taskStatuses=WorkStatusPresentation::tasks('_l');}
            if($group==='tickets'){$catalog=new NativeCompanyTicketCatalog($ci->db,db_prefix());$ticketStatuses=$catalog->list($issuer,$actor,'status');$ticketPriorities=$catalog->list($issuer,$actor,'priority');}
            if($group==='sales_general'){
                require_once __DIR__.'/NativeCompanyCatalogUi.php';
                $raw=$page['values']['default_tax'];$selectedTaxes=[];
                if($raw!==''){set_error_handler(static function(){throw new DomainException('native_settings_invalid');});try{$selectedTaxes=unserialize($raw,['allowed_classes'=>false]);}finally{restore_error_handler();}if(!is_array($selectedTaxes)||!array_is_list($selectedTaxes))throw new DomainException('native_settings_invalid');}
                $catalog=new NativeCompanyCatalogUi($ci->db,db_prefix(),$issuer,$actor);
                $nativeTaxDropdown=$catalog->taxDropdown('settings[default_tax][]',$selectedTaxes);
            }
            $canEdit=CompanySettingsRuntime::can('edit');
            if($group==='calendar')$canEdit=$canEdit&&(new IssuerResourceAccess($ci->db,db_prefix()))->can($issuer,$actor,'calendar','configure');
            $ci->load->view('admin/settings/all',[
                'title'=>_l('options'),'sections'=>$sections,'group'=>$selected,'admin_tabs'=>['update','info','argws_platform'],
                'native_settings'=>true,'native_settings_clients'=>$group==='clients'?$page:null,'native_settings_misc'=>$group==='misc'?$page:null,'roles'=>$group==='misc'?$page['roles']:[],'native_settings_task_values'=>$group==='tasks'?$page['values']:null,'task_statuses'=>$taskStatuses,'native_settings_google'=>$group==='google'?$page:null,'native_settings_calendar_values'=>$group==='calendar'?$page['values']:null,'native_settings_email'=>$group==='email'?$page:null,'native_settings_ticket_values'=>$group==='tickets'?$page['values']:null,'native_settings_ticket_statuses'=>$ticketStatuses,'ticket_priorities'=>$ticketPriorities,'native_settings_issuer'=>$issuer,'native_settings_invoice_values'=>$group==='invoices'?$page['values']:null,'native_settings_company_values'=>$group==='company'?$page['values']:null,'native_settings_company_custom_fields'=>$group==='company'?$page['custom_fields']:null,'native_settings_company_field_definitions'=>$group==='company'?$page['custom_field_definitions']:null,'native_settings_tax_dropdown'=>$nativeTaxDropdown,'native_settings_revision'=>$page['revision'],'native_settings_assets'=>$page['assets'],
                'native_settings_pdf_values'=>$group==='pdf'?$page['values']:null,
                'native_settings_estimate_values'=>$group==='estimates'?$page['values']:null,
                'native_settings_proposal_values'=>$group==='proposals'?$page['values']:null,
                'native_settings_proposal_field_definitions'=>$proposalFields,
                'native_settings_policy_values'=>in_array($group,['task_timer_stop','task_timers','task_operations','task_defaults','ticket_preferences','pdf_typography','project_files'],true)?$page['values']:null,
                'native_settings_can_edit'=>$canEdit,
            ]);
        }catch(DomainException $e){
            show_error('Não foi possível concluir esta operação nas definições da empresa selecionada. Recarregue o formulário e confira suas permissões.',self::domainStatus($e->getMessage()));
        }catch(RuntimeException $e){
            // Only recognized persistence/transaction failures are mapped. An
            // unexpected programming error must not be silently reclassified.
            $policyFailure=isset($group)&&in_array($group,['task_timer_stop','task_timers','task_operations','task_defaults','ticket_preferences','pdf_typography','project_files'],true);
            if(!$policyFailure&&!in_array($e->getMessage(),['issuer_storage_failed','issuer_commit_unconfirmed','issuer_transactional_storage_required','issuer_calendar_preferences_unconfirmed','native_calendar_preferences_unconfirmed',
                'issuer_project_file_settings_write_unconfirmed','issuer_pdf_typography_write_unconfirmed','issuer_ticket_preferences_unconfirmed','issuer_task_preferences_unconfirmed',
                'issuer_task_operation_preferences_unconfirmed','issuer_task_timer_preferences_unconfirmed','issuer_task_timer_stop_preferences_unconfirmed'],true))throw $e;
            show_error('Não foi possível confirmar a gravação das definições. Recarregue a página antes de tentar novamente.',503);
        }
    }
}
