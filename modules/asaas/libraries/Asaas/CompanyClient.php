<?php

declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/ExactJson.php';

/** Bounded Asaas API transport. No POST retry, redirects, caller-selected host or exception payloads. */
final class CompanyClient
{
    public function __construct(private string $environment,private string $secret,private $transport=null)
    {
        if(!in_array($environment,['sandbox','production'],true)||$secret===''||strlen($secret)>4096||preg_match('/[\x00-\x20\x7f]/',$secret))throw new \DomainException('asaas_company_credentials_required');
    }
    public static function decodeExact(string $json):array{return ExactJson::decode($json);}
    public function request(string $method,string $path,?array $payload=null):array
    {
        if(!in_array($method,['GET','POST'],true)||!preg_match('~^(?:wallets/|customers/[A-Za-z0-9_-]{1,64}|payments(?:/[A-Za-z0-9_-]{1,64})?)$~D',$path)||($method==='POST'&&$path!=='payments')||($method==='GET'&&$payload!==null))throw new \DomainException('asaas_company_request_invalid');
        try {
            if($this->transport!==null){$response=($this->transport)($this->environment,$method,$path,$payload,$this->secret);if(is_string($response))$response=self::decodeExact($response);if(!is_array($response)||array_is_list($response))throw new \RuntimeException();return $response;}
            $base=$this->environment==='sandbox'?'https://api-sandbox.asaas.com/v3/':'https://api.asaas.com/v3/';
            $handle=curl_init($base.$path);if($handle===false)throw new \RuntimeException();$body='';
            try{
                curl_setopt_array($handle,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>['Accept: application/json','Content-Type: application/json','User-Agent: CRM Asaas integration','access_token: '.$this->secret],CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_WRITEFUNCTION=>static function($handle,$chunk)use(&$body){if(strlen($body)+strlen($chunk)>1048576)return 0;$body.=$chunk;return strlen($chunk);}]);
                // Decimal strings are rendered as JSON number literals without float arithmetic.
                if($payload!==null){$json=json_encode($payload,JSON_THROW_ON_ERROR);$json=preg_replace('/"value":"([0-9]+\.[0-9]{2})"/','"value":$1',$json);curl_setopt($handle,CURLOPT_POSTFIELDS,$json);}
                if(curl_exec($handle)===false||(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE)<200||(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE)>=300)throw new \RuntimeException();
                $response=self::decodeExact($body);if(!is_array($response)||array_is_list($response))throw new \RuntimeException();return $response;
            }finally{unset($handle);}
        }catch(\Throwable $e){throw new \RuntimeException('asaas_company_transport_unconfirmed');}
    }
}
