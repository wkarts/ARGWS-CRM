<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanySettingsForms.php';
require_once __DIR__.'/NativeSettingsSections.php';
/** Adapter for Settings::index and the existing admin/settings/all template only. */
final class NativeCompanySettingsController
{
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
            if(!(new IssuerResourceAccess($ci->db,db_prefix()))->can($issuer,$actor,'settings','delete'))throw new DomainException('native_settings_denied');
            $post=$ci->input->post(null,false);$csrf=$ci->security->get_csrf_token_name();
            if(!is_array($post)||array_diff(array_keys($post),['revision','_native_company_csrf','_company_context',$csrf])||!is_string($post['revision']??null))throw new DomainException('native_settings_invalid');
            (new CompanyAssets($ci->db,db_prefix()))->save($issuer,$actor,$kind,null,$post['revision']);CompanyRequestScope::invalidateCaches($ci);
            $ci->output->set_header('Cache-Control: no-store');$ci->output->set_content_type('application/json');$ci->output->set_output(json_encode(['success'=>true],JSON_THROW_ON_ERROR));
        }catch(DomainException $e){show_error('Não foi possível remover esta imagem. Recarregue e confira suas permissões.',$e->getMessage()==='company_asset_stale'?409:403);}
    }
    public static function index($ci):void
    {
        try{
            if(!CompanyRequestScope::nativeFor($ci)||!CompanySettingsRuntime::active())throw new DomainException('native_settings_denied');
            $context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();
            if($issuer!==CompanySettingsRuntime::issuerId()||$actor!==CompanySettingsRuntime::staffId())throw new DomainException('native_settings_denied');
            $forms=new NativeCompanySettingsForms($ci->db,db_prefix());$forms->authorize($issuer,$actor,'view');
            $group=$ci->input->get('group')?:'general';if(!is_string($group))throw new DomainException('native_settings_invalid');
            $supported=array_fill_keys(NativeCompanySettingsForms::availableGroups(),true);
            $sections=NativeSettingsSections::collect();
            foreach($sections as$key=>$section){$section['children']=array_values(array_filter($section['children'],static fn($child)=>isset($supported[$child['id']])));if($section['children']===[])unset($sections[$key]);else$sections[$key]=$section;}
            $selected=null;
            foreach($sections as$section)foreach($section['children']as$child)if($child['id']===$group)$selected=$child;
            if($selected===null)throw new DomainException('native_settings_group_unavailable');
            NativeCompanySettingsForms::fields($group);
            $method=strtoupper($ci->input->method(true));
            if($method==='POST'){
                CompanyRequestScope::assertNativeMutation($ci);$forms->authorize($issuer,$actor,'edit');
                $post=$ci->input->post(null,false);$csrfName=$ci->security->get_csrf_token_name();
                if(!is_array($post)||array_diff(array_keys($post),['settings','custom_fields','fakeusernameremembered','fakepasswordremembered','test_email','native_settings_revision','_native_company_csrf','_company_context',$csrfName])||!is_array($post['settings']??null)||!is_string($post['native_settings_revision']??null))throw new DomainException('native_settings_invalid');
                foreach(['fakeusernameremembered','fakepasswordremembered','test_email']as$ignored)if(isset($post[$ignored])&&($group!=='email'||!is_string($post[$ignored])||strlen($post[$ignored])>4096))throw new DomainException('native_settings_invalid');
                $custom=$post['custom_fields']??[];if(!is_array($custom)||array_diff(array_keys($custom),['company'])||isset($custom['company'])&&!is_array($custom['company']))throw new DomainException('native_settings_invalid');
                $uploads=[];$fileNames=['company_logo'=>'logo','company_logo_dark'=>'logo_dark','favicon'=>'favicon'];
                foreach($_FILES as$name=>$file){
                    if(!is_array($file))throw new DomainException('native_settings_invalid');
                    if(($file['error']??null)===UPLOAD_ERR_NO_FILE)continue;
                    if($group!=='general'||!isset($fileNames[$name])||($file['error']??null)!==UPLOAD_ERR_OK||!is_int($file['size']??null)||$file['size']<1||$file['size']>524288||!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name']))throw new DomainException('native_settings_invalid');
                    $bytes=file_get_contents($file['tmp_name'],false,null,0,524289);
                    if(!is_string($bytes)||strlen($bytes)!==$file['size'])throw new DomainException('native_settings_invalid');
                    $uploads[$fileNames[$name]]=$bytes;
                }
                $encrypt=null;$decrypt=null;if(in_array($group,['email','google'],true)){$ci->load->library('encryption');$encrypt=fn($v)=>$ci->encryption->encrypt($v);$decrypt=fn($v)=>$ci->encryption->decrypt($v);}
                $forms->save($issuer,$actor,$group,$post['settings'],$custom['company']??[],$post['native_settings_revision'],$uploads,$encrypt,$decrypt);
                CompanyRequestScope::invalidateCaches($ci);
                redirect(admin_url('settings?group='.rawurlencode($group)),'location',303);return;
            }
            if(!in_array($method,['GET','HEAD'],true))throw new DomainException('native_settings_method');
            $page=$forms->page($issuer,$actor,$group);
            if($group==='email'){require_once __DIR__.'/NativeCompanyMailQueue.php';$page['queue']=(new NativeCompanyMailQueue($ci->db,db_prefix()))->page($issuer,$actor);$cron=IssuerStorage::query($ci->db,'SELECT value FROM '.db_prefix().'options WHERE name=?',['cron_has_run_from_cli'])->row_array();$page['cron_ready']=($cron['value']??'')==='1'?'1':'0';$page['command_fields']=['_native_company_csrf'=>CompanyRequestScope::nativeCsrfToken($ci),'_company_context'=>CompanyRequestScope::token(),$ci->security->get_csrf_token_name()=>$ci->security->get_csrf_hash()];}
            $ci->load->helper(['fields','form']);
            $nativeTaxDropdown=null;$ticketStatuses=null;$ticketPriorities=null;$taskStatuses=null;
            if($group==='tasks'){require_once __DIR__.'/WorkStatusPresentation.php';$taskStatuses=WorkStatusPresentation::tasks('_l');}
            if($group==='tickets'){$catalog=new NativeCompanyTicketCatalog($ci->db,db_prefix());$ticketStatuses=$catalog->list($issuer,$actor,'status');$ticketPriorities=$catalog->list($issuer,$actor,'priority');}
            if($group==='sales_general'){
                require_once __DIR__.'/NativeCompanyCatalogUi.php';
                $raw=$page['values']['default_tax'];$selectedTaxes=[];
                if($raw!==''){set_error_handler(static function(){throw new DomainException('native_settings_invalid');});try{$selectedTaxes=unserialize($raw,['allowed_classes'=>false]);}finally{restore_error_handler();}if(!is_array($selectedTaxes)||!array_is_list($selectedTaxes))throw new DomainException('native_settings_invalid');}
                $catalog=new NativeCompanyCatalogUi($ci->db,db_prefix(),$issuer,$actor);
                $nativeTaxDropdown=$catalog->taxDropdown('settings[default_tax][]',$selectedTaxes);
            }
            $ci->load->view('admin/settings/all',[
                'title'=>_l('options'),'sections'=>$sections,'group'=>$selected,'admin_tabs'=>['update','info','argws_platform'],
                'native_settings'=>true,'native_settings_clients'=>$group==='clients'?$page:null,'native_settings_misc'=>$group==='misc'?$page:null,'roles'=>$group==='misc'?$page['roles']:[],'native_settings_task_values'=>$group==='tasks'?$page['values']:null,'task_statuses'=>$taskStatuses,'native_settings_google'=>$group==='google'?$page:null,'native_settings_calendar_values'=>$group==='calendar'?$page['values']:null,'native_settings_email'=>$group==='email'?$page:null,'native_settings_ticket_values'=>$group==='tickets'?$page['values']:null,'native_settings_ticket_statuses'=>$ticketStatuses,'ticket_priorities'=>$ticketPriorities,'native_settings_issuer'=>$issuer,'native_settings_invoice_values'=>$group==='invoices'?$page['values']:null,'native_settings_company_values'=>$group==='company'?$page['values']:null,'native_settings_company_custom_fields'=>$group==='company'?$page['custom_fields']:null,'native_settings_company_field_definitions'=>$group==='company'?$page['custom_field_definitions']:null,'native_settings_tax_dropdown'=>$nativeTaxDropdown,'native_settings_revision'=>$page['revision'],'native_settings_assets'=>$page['assets'],
                'native_settings_can_edit'=>CompanySettingsRuntime::can('edit'),
            ]);
        }catch(DomainException $e){
            $status=$e->getMessage()==='native_settings_stale'?409:(in_array($e->getMessage(),['native_settings_invalid','company_settings_invalid_input','issuer_invalid_name','issuer_invalid_tax_id','native_custom_field_required'],true)?422:403);
            show_error('Não foi possível concluir esta operação nas definições da empresa selecionada. Recarregue o formulário e confira suas permissões.',$status);
        }
    }
}
