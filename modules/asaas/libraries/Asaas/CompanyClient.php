<?php

declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/ExactJson.php';
require_once __DIR__.'/PaymentListQuery.php';
require_once __DIR__.'/AutomaticPixQuery.php';
require_once __DIR__.'/CompanyCardPaymentRequest.php';

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
        if($method==='POST'&&preg_match('~^payments/[A-Za-z0-9_-]{1,64}/(payWithCreditCard|payWithCard)$~D',$path,$cardMatch)){
            if($payload===null)throw new \DomainException('asaas_card_request_invalid');
            $payload=CompanyCardPaymentRequest::payload($cardMatch[1],$payload);
        }
        if($method==='POST'&&str_ends_with($path,'/receiveInCash')){
            if(!is_array($payload)||array_keys($payload)!==['paymentDate','value','notifyCustomer']||!is_string($payload['paymentDate'])||!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$payload['paymentDate'])||!is_string($payload['value'])||!preg_match('/^[0-9]+\.[0-9]{2}$/D',$payload['value'])||!is_bool($payload['notifyCustomer']))throw new \DomainException('asaas_manual_request_invalid');
        }
        $invoiceEdit=$method==='PUT'&&preg_match('~^payments/[A-Za-z0-9_-]{1,64}$~D',$path);
        if($invoiceEdit){
            if($payload===null||array_keys($payload)!==['billingType','value','dueDate']||!in_array($payload['billingType'],['PIX','BOLETO'],true)||!is_string($payload['value'])||!preg_match('/^[0-9]+\\.[0-9]{2}$/D',$payload['value'])||!is_string($payload['dueDate']))throw new \DomainException('asaas_invoice_edit_request_invalid');
            $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$payload['dueDate']);if(!$date||$date->format('Y-m-d')!==$payload['dueDate'])throw new \DomainException('asaas_invoice_edit_request_invalid');
        }
        $webhookRead=$method==='GET'&&$payload===null&&preg_match('~^webhooks\?limit=100&offset=(?:0|[1-9][0-9]{0,2})$~D',$path);
        $webhookWrite=($method==='POST'&&$path==='webhooks'&&$payload!==null)
            ||($method==='PUT'&&preg_match('~^webhooks/[A-Za-z0-9_-]{1,128}$~D',$path)&&$payload!==null)
            ||($method==='DELETE'&&preg_match('~^webhooks/[A-Za-z0-9_-]{1,128}$~D',$path)&&$payload===null);
        $readPath=AutomaticPixQuery::allowed($path)||preg_match('~^installments/[A-Za-z0-9_-]{1,120}(?:/payments)?$~D',$path)||PaymentListQuery::allowed($path)||preg_match('~^(?:wallets/|payments\?externalReference=[A-Za-z0-9_-]{1,200}&limit=100&offset=(?:0|[1-9][0-9]{0,3})|customers/[A-Za-z0-9_-]{1,64}|customers\?externalReference=[a-f0-9]{64}&limit=100&offset=(?:0|[1-9][0-9]{0,3})|payments/[A-Za-z0-9_-]{1,64}(?:/(?:pixQrCode|identificationField))?)$~D',$path);
        if(!$invoiceEdit&&!$webhookRead&&!$webhookWrite&&(!in_array($method,['GET','POST','DELETE'],true)||($method==='GET'&&(!$readPath||$payload!==null))||($method==='POST'&&!in_array($path,['payments','customers'],true)&&!preg_match('~^payments/[A-Za-z0-9_-]{1,64}/(?:refund|payWithCreditCard|payWithCard|receiveInCash)$~D',$path))||($method==='DELETE'&&($payload!==null||!preg_match('~^payments/[A-Za-z0-9_-]{1,64}$~D',$path)))))throw new \DomainException('asaas_company_request_invalid');
        return $this->perform($method,$path,$payload);
    }
    /** Only approved report resources, never a URL/HTTP method selected by the caller. */
    public function report(string $report,array $path,array $query):array
    {
        require_once __DIR__.'/AsaasReportCatalog.php';
        $definition=AsaasReportCatalog::definitions()[$report]??null;
        if($definition===null)throw new \DomainException('asaas_report_invalid');
        $endpoint=$definition['endpoint'];
        if(str_contains($endpoint,'{id}')){
            if(array_keys($path)!==['id']||!is_string($path['id'])||!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,119}$/D',$path['id']))throw new \DomainException('asaas_report_invalid');
            $endpoint=str_replace('{id}',$path['id'],$endpoint);
        }elseif($path!==[])throw new \DomainException('asaas_report_invalid');
        $allowed=array_column($definition['filters'],'key');$allowed=array_diff($allowed,[$definition['path_filter']??'']);
        if($definition['kind']==='list')array_push($allowed,'limit','offset');
        foreach($definition['dates'] as $field=>$label)foreach(($definition['range']??[$field.'[ge]',$field.'[le]']) as $key)$allowed[]=$key;
        foreach($query as $key=>$value)if(!in_array($key,$allowed,true)||!is_scalar($value)||strlen((string)$value)>200||preg_match('/[\x00-\x1f\x7f]/',(string)$value))throw new \DomainException('asaas_report_invalid');
        if(isset($query['limit'])&&(!is_int($query['limit'])||$query['limit']<1||$query['limit']>100))throw new \DomainException('asaas_report_invalid');
        if(!str_starts_with($endpoint,'/v3/')||str_contains($endpoint,'..'))throw new \DomainException('asaas_report_invalid');
        $query=array_map(static fn($value)=>is_bool($value)?($value?'true':'false'):$value,$query);
        return $this->perform('GET',substr($endpoint,4).($query?'?'.http_build_query($query,'','&',PHP_QUERY_RFC3986):''),null);
    }
    private function perform(string $method,string $path,?array $payload):array
    {
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
