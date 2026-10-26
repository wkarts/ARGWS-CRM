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
            $context=CompanyRequestScope::context()