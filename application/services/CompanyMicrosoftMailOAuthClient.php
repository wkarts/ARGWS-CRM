<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyMicrosoftMailIdentity.php';

/** Fixed commercial Entra tenant endpoints only; credential-bearing network calls never hold a SQL transaction. */
final class CompanyMicrosoftMailOAuthClient
{
    public const MAIL_SCOPE='https://outlook.office.com/SMTP.Send';
    public const SCOPE='openid profile offline_access https://outlook.office.com/SMTP.Send';
    public function __construct(private$transport=null){}
    public static function authorize(string$tenant):string{return 'https://login.microsoftonline.com/'.CompanyMicrosoftMailIdentity::tenant($tenant).'/oauth2/v2.0/authorize';}
    public static function tokenEndpoint(string$tenant):string{return 'https://login.microsoftonline.com/'.CompanyMicrosoftMailIdentity::tenant($tenant).'/oauth2/v2.0/token';}
    public static function keysEndpoint(string$tenant):string{return 'https://login.microsoftonline.com/'.CompanyMicrosoftMailIdentity::tenant($tenant).'/discovery/v2.0/keys';}
    public function token(string$tenant,array$form):array{return$this->request(self::tokenEndpoint($tenant),'POST',$form);}
    public function keys(string$tenant):array{return$this->request(self::keysEndpoint($tenant),'GET',[]);}
    private function request(string$url,string$method,array$form):array
    {
        if($this->transport!==null){try{$result=($this->transport)($url,$method,$form);if(!is_array($result))throw new RuntimeException('invalid synthetic response');return$result;}catch(Throwable$error){throw new RuntimeException('company_microsoft_mail_remote_unavailable');}}
        if(!function_exists('curl_init'))throw new RuntimeException('company_microsoft_mail_remote_unavailable');$ch=curl_init($url);$body='';$limit=$method==='POST'?65536:131072;$options=[CURLOPT_RETURNTRANSFER=>false,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>25,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_WRITEFUNCTION=>static function($ch,string$chunk)use(&$body,$limit){if(strlen($body)+strlen($chunk)>$limit)return 0;$body.=$chunk;return strlen($chunk);}];if($method==='POST'){$options[CURLOPT_POST]=true;$options[CURLOPT_POSTFIELDS]=http_build_query($form,'','&',PHP_QUERY_RFC3986);$options[CURLOPT_HTTPHEADER][]='Content-Type: application/x-www-form-urlencoded';}curl_setopt_array($ch,$options);
        try{$ok=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);if($ok===false||$code!==200)throw new RuntimeException('company_microsoft_mail_remote_unavailable');try{$data=json_decode($body,true,32,JSON_THROW_ON_ERROR);}catch(Throwable$error){throw new RuntimeException('company_microsoft_mail_remote_unavailable');}if(!is_array($data)||array_is_list($data)||isset($data['error']))throw new RuntimeException('company_microsoft_mail_remote_unavailable');return$data;}finally{curl_close($ch);}
    }
}
