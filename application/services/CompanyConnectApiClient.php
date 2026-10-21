<?php

declare(strict_types=1);

/** The existing Connect|API connector 1.1.2 state/identity/sendText/restart HTTP contract, isolated from its global hooks. */
final class CompanyConnectApiClient
{
    public function __construct(private$transport=null){}
    public function request(array$endpoint,string$instance,string$token,string$operation,array$payload=[]):array
    {
        if(!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$instance)||$token===''||strlen($token)>4096||preg_match('/[\r\n\x00]/',$token))throw new DomainException('company_connect_api_invalid');
        [$method,$path]=match($operation){'state'=>['GET','/instance/connectionState/'],'identity'=>['GET','/instance/fetchInstances?instanceName='],'qr'=>['GET','/instance/connect/'],'restart'=>['POST','/instance/restart/'],'send'=>['POST','/message/sendText/'],default=>throw new DomainException('company_connect_api_invalid')};$url=$endpoint['url'].$path.rawurlencode($instance);
        if($this->transport!==null){try{$r=($this->transport)($url,$method,$payload,$token);if(!is_array($r)||($operation!=='identity'&&array_is_list($r))||isset($r['error']))throw new RuntimeException();return$r;}catch(Throwable$e){throw new RuntimeException('company_connect_api_unavailable');}}
        if(!function_exists('curl_init'))throw new RuntimeException('company_connect_api_unavailable');$ch=curl_init($url);$body='';$options=[CURLOPT_RETURNTRANSFER=>false,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_HTTPHEADER=>['Accept: application/json','Content-Type: application/json','apikey: '.$token],CURLOPT_WRITEFUNCTION=>static function($ch,$chunk)use(&$body){if(strlen($body)+strlen($chunk)>131072)return 0;$body.=$chunk;return strlen($chunk);}];if($method==='POST'){$options[CURLOPT_POST]=true;$options[CURLOPT_POSTFIELDS]=json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}curl_setopt_array($ch,$options);
        try{$ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);if($ok===false||!in_array($status,$method==='GET'?[200]:[200,201],true))throw new RuntimeException('company_connect_api_unavailable');try{$r=json_decode($body,true,32,JSON_THROW_ON_ERROR);}catch(Throwable$e){throw new RuntimeException('company_connect_api_unavailable');}if(!is_array($r)||($operation!=='identity'&&array_is_list($r))||isset($r['error']))throw new RuntimeException('company_connect_api_unavailable');return$r;}finally{curl_close($ch);}
    }
    /** Exact deployment allowlist is server infrastructure, never a Host/header or per-company arbitrary URL. */
    public static function endpoints(?array$configured=null):array
    {
        $configured??=defined('COMPANY_CONNECT_API_ENDPOINTS')?COMPANY_CONNECT_API_ENDPOINTS:[];$out=[];$urls=[];if(count($configured)>32)throw new DomainException('company_connect_api_endpoint_invalid');foreach($configured as$id=>$config){if(!is_string($id)||!preg_match('/^[a-z][a-z0-9_-]{0,39}$/D',$id)||!is_array($config)||array_keys($config)!==['label','url']||!is_string($config['label'])||trim($config['label'])===''||strlen($config['label'])>120||!is_string($config['url']))throw new DomainException('company_connect_api_endpoint_invalid');$url=rtrim($config['url'],'/');$u=parse_url($url);if(!$u||($u['scheme']??'')!=='https'||empty($u['host'])||preg_match('/[^a-zA-Z0-9.-]/',$u['host'])||isset($u['user'])||isset($u['pass'])||isset($u['query'])||isset($u['fragment'])||str_contains($u['path']??'','..')||strlen($url)>1000||isset($urls[strtolower($url)]))throw new DomainException('company_connect_api_endpoint_invalid');$urls[strtolower($url)]=true;$out[$id]=['label'=>$config['label'],'url'=>$url];}return$out;
    }
}
