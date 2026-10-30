<?php

declare(strict_types=1);

/** Fixed Google HTTPS endpoints. The injected transport is an internal test dependency, never request data. */
final class CompanyGoogleMailOAuthClient
{
    public const AUTH='https://accounts.google.com/o/oauth2/v2/auth';
    public const TOKEN='https://oauth2.googleapis.com/token';
    public const USERINFO='https://openidconnect.googleapis.com/v1/userinfo';
    public const MAIL_SCOPE='https://mail.google.com/';
    public function __construct(private $transport=null){}
    public function token(array $form):array{return $this->request(self::TOKEN,'POST',$form,'');}
    public function identity(string $access):array{return $this->request(self::USERINFO,'GET',[],$access);}
    private function request(string $url,string $method,array $form,string $bearer):array
    {
        if($this->transport!==null){$response=($this->transport)($url,$method,$form,$bearer);if(!is_array($response))throw new RuntimeException('company_google_mail_remote_unavailable');return$response;}
        $curl=curl_init($url);$headers=['Accept: application/json'];if($bearer!=='')$headers[]='Authorization: Bearer '.$bearer;
        $options=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>25,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_HTTPHEADER=>$headers];
        if($method==='POST'){$options[CURLOPT_POST]=true;$options[CURLOPT_POSTFIELDS]=http_build_query($form,'','&',PHP_QUERY_RFC3986);$options[CURLOPT_HTTPHEADER][]='Content-Type: application/x-www-form-urlencoded';}
        curl_setopt_array($curl,$options);$body=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
        if(!is_string($body)||$status!==200||strlen($body)>65536)throw new RuntimeException('company_google_mail_remote_unavailable');
        try{$data=json_decode($body,true,16,JSON_THROW_ON_ERROR);}catch(Throwable$error){throw new RuntimeException('company_google_mail_remote_unavailable');}
        if(!is_array($data))throw new RuntimeException('company_google_mail_remote_unavailable');return$data;
    }
}
