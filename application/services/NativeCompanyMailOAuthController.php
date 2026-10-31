<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyGoogleMailOAuth.php';
require_once __DIR__.'/CompanyMicrosoftMailOAuth.php';
/** Existing smtp_oauth_*/
final class NativeCompanyMailOAuthController
{
    public static function token($ci,string $provider):void
    {
        try{
            if(!in_array($provider,['google','microsoft'],true)||!CompanyRequestScope::nativeFor($ci))throw new DomainException('native_mail_oauth_denied');
            $scope=CompanyRequestScope::context();$i=$scope->issuerId();$a=$scope->staffId();
            $acl=new IssuerResourceAccess($ci->db,db_prefix());if($acl->primaryId()===$i||!$acl->can($i,$a,'settings','edit'))throw new DomainException('native_mail_oauth_denied');
            $class=$provider==='google'?CompanyGoogleMailOAuth::class:CompanyMicrosoftMailOAuth::class;$service=new $class($ci->db,db_prefix());
            $ci->output->set_header('Cache-Control: no-store')->set_header('Pragma: no-cache')->set_header('Referrer-Policy: no-referrer');
            $method=strtoupper($ci->input->method(true));
            $ci->load->library('encryption');$encrypt=fn($v)=>$ci->encryption->encrypt($v);$decrypt=fn($v)=>$ci->encryption->decrypt($v);
            if($method==='POST'){
                CompanyRequestScope::assertNativeMutation($ci);$post=$ci->input->post(null,false);$csrf=$ci->security->get_csrf_token_name();
                if(!is_array($post)||array_diff(array_keys($post),['action','revision','_native_company_csrf','_company_context',$csrf])||!is_string($post['revision']??null)||!preg_match('/^(0|[1-9][0-9]{0,8})$/D',$post['revision']))throw new DomainException('native_mail_oauth_invalid');
                if(($post['action']??null)==='begin'){
                    // admin_url is the installation's configured base, not a callback URL supplied by the request.
                    $return=$class::redirectUri(admin_url('smtp_oauth_'.$provider.'/token'));
                    $target=$service->begin($i,$a,(int)$post['revision'],$return,$encrypt);redirect($target,'location',303);return;
                }
                if(($post['action']??null)!=='revoke')throw new DomainException('native_mail_oauth_invalid');
                $service->revoke($i,$a,(int)$post['revision']);
            }elseif($method==='GET'){
                $state=$ci->input->get('state',false);$code=$ci->input->get('code',false);
                if(!is_string($state)||!is_string($code)||$ci->input->get('error',false)!==null)throw new DomainException('native_mail_oauth_invalid');
                // The existing service binds a one-use state to issuer, actor, sender and account revision.
                $service->callback($i,$a,$state,$code,$encrypt,$decrypt);
            }else throw new DomainException('native_mail_oauth_invalid');
            CompanyRequestScope::invalidateCaches($ci);redirect(admin_url('settings?group=email'),'location',303);
        }catch(Throwable $error){show_error('Não foi possível confirmar a autorização de e-mail. Reabra as definições da empresa; não repita o código de retorno.', $error instanceof DomainException?403:503);}
    }
}
