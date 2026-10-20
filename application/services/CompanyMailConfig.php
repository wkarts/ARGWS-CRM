<?php

declare(strict_types=1);
require_once __DIR__ . '/CompanySettingsStorage.php';

/** Explicit origin-bound configuration for request and background mail transports.
 * The caller must authorize the request or validate the persisted job origin first.
 * Never changes the interactive company context and never caches decrypted secrets.
 */
final class CompanyMailConfig
{
    public static function build($db, string $prefix, $issuerId, callable $decrypt, ?callable $encrypt=null, string $purpose='native', ?CompanyGoogleMailOAuthDispatchSource $source=null): array
    {
        $storage = new CompanySettingsStorage($db, $prefix);
        $primary = $storage->isPrimary($issuerId);
        if($purpose==='document_notifications'){
            require_once __DIR__.'/CompanyGoogleMailOAuth.php';
            $oauth=new CompanyGoogleMailOAuth($db,$prefix);$profile=$oauth->transportProfile($issuerId);
            if($profile!==null){
                require_once __DIR__.'/CompanyGoogleMailOAuthTokenProvider.php';
                return ['useragent'=>'phpmailer','protocol'=>'google','smtp_host'=>'smtp.gmail.com','smtp_port'=>'587','smtp_crypto'=>'tls','smtp_auto_tls'=>false,'smtp_timeout'=>30,'smtp_user'=>$profile['sender_email'],'smtp_pass'=>'','smtp_debug'=>0,'debug_output'=>'error_log','smtp_conn_options'=>[],'wordwrap'=>true,'mailtype'=>'html','charset'=>trim($storage->get($issuerId,'smtp_email_charset'))?:'utf-8','validate'=>false,'priority'=>3,'newline'=>"\r\n",'crlf'=>"\r\n",'bcc_batch_mode'=>false,'bcc_batch_size'=>200,'encoding'=>'8bit','client_id'=>'','client_secret'=>'','tenant_id'=>'','refresh_token'=>'','xoauth2'=>'','dkim_domain'=>'','dkim_private'=>'','dkim_private_string'=>'','dkim_selector'=>'','dkim_passphrase'=>'','dkim_identity'=>'','company_google_mail_provider'=>new CompanyGoogleMailOAuthTokenProvider($oauth,(int)$issuerId,$profile['epoch'],$profile['sender_email'],$encrypt,$decrypt,$source)];
            }
        }elseif($purpose!=='native'){throw new DomainException('company_mail_purpose_invalid');}
        $get = static fn(string $name): string => $storage->get($issuerId, $name);
        $protocol = $get('email_protocol');
        $engine = $get('mail_engine');
        if (!in_array($engine, ['phpmailer', 'codeigniter'], true)
            || !in_array($protocol, $primary ? ['mail', 'sendmail', 'smtp', 'microsoft', 'google'] : ['smtp', 'microsoft', 'google'], true)) {
            throw new DomainException('company_mail_configuration_required');
        }
        $secret = static function (string $name) use ($get, $decrypt): string {
            $encrypted = $get($name);
            if ($encrypted === '') { return ''; }
            try { $value = $decrypt($encrypted); } catch (Throwable $error) {
                throw new DomainException('company_mail_secret_unavailable');
            }
            if (!is_string($value)) { throw new DomainException('company_mail_secret_unavailable'); }
            return $value;
        };
        $config = [
            'useragent' => $engine, 'protocol' => $protocol, 'mailpath' => '/usr/bin/sendmail',
            'smtp_host' => trim($get('smtp_host')),
            'smtp_user' => trim($get('smtp_username') ?: $get('smtp_email')),
            'smtp_pass' => $secret('smtp_password'), 'smtp_port' => trim($get('smtp_port')),
            'smtp_timeout' => 30, 'smtp_crypto' => $get('smtp_encryption'),
            'smtp_debug' => 0, 'debug_output' => 'error_log', 'smtp_auto_tls' => false,
            'smtp_conn_options' => [], 'wordwrap' => true, 'mailtype' => 'html',
            'charset' => trim($get('smtp_email_charset')) ?: 'utf-8',
            'validate' => false, 'priority' => 3, 'newline' => "\r\n", 'crlf' => "\r\n",
            'bcc_batch_mode' => false, 'bcc_batch_size' => 200, 'encoding' => '8bit',
            // Explicit resets prevent a reused transport retaining another account's OAuth/DKIM.
            'client_id' => '', 'client_secret' => '', 'tenant_id' => '', 'refresh_token' => '',
            'company_google_mail_provider'=>null,
            'xoauth2' => '', 'dkim_domain' => '', 'dkim_private' => '', 'dkim_private_string' => '',
            'dkim_selector' => '', 'dkim_passphrase' => '', 'dkim_identity' => '',
        ];
        if ($protocol === 'smtp' && ($config['smtp_host'] === '' || !ctype_digit($config['smtp_port'])
            || (int) $config['smtp_port'] < 1 || (int) $config['smtp_port'] > 65535)) {
            throw new DomainException('company_mail_configuration_required');
        }
        if (in_array($protocol, ['microsoft', 'google'], true)) {
            $provider = $protocol === 'microsoft' ? 'microsoft_mail_' : 'google_mail_';
            $config['client_id'] = $get($provider . 'client_id');
            $config['client_secret'] = $secret($provider . 'client_secret');
            $config['refresh_token'] = $get($provider . 'refresh_token');
            $config['tenant_id'] = $protocol === 'microsoft' ? $get('microsoft_mail_azure_tenant_id') : '';
            if ($engine !== 'phpmailer' || $config['client_id'] === '' || $config['client_secret'] === '' || $config['refresh_token'] === '') {
                throw new DomainException('company_mail_configuration_required');
            }
        }
        return $config;
    }
}
