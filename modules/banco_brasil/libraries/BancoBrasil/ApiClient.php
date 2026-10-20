<?php

declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/Fields.php';
require_once __DIR__.'/ExactJson.php';

/** Cobranças v2 / OpenAPI3.2.2, retrieved2026-10-19 from the official public catalogue. */
final class ApiClient
{
    public const SPEC_SHA256='6767b098b13bc00e42700d80435a73e1f6ff4d31d7a28647926209a58d5f00d3';
    public const SERVERS=[
        'sandbox'=>['https://api.sandbox.bb.com.br/cobrancas/v2','https://oauth.sandbox.bb.com.br/oauth/token'],
        'homologation'=>['https://api.hm.bb.com.br/cobrancas/v2','https://oauth.hm.bb.com.br/oauth/token'],
        'production'=>['https://api.bb.com.br/cobrancas/v2','https://oauth.bb.com.br/oauth/token'],
    ];
    private $transport;
    public function __construct(?callable $transport=null){$this->transport=$transport??[self::class,'curl'];}
    public static function stage(string $stage):array
    {
        if(!isset(self::SERVERS[$stage]))throw new \DomainException('bb_api_environment_invalid');return self::SERVERS[$stage];
    }
    /** Real transport: fixed HTTPS origins, no redirects/proxy credentials in diagnostics, bounded bytes/time. */
    public static function curl(string $method,string $url,array $headers,string $body):array
    {
        $allowed=false;foreach(self::SERVERS as [$api,$oauth])if($url===$oauth||str_starts_with($url,$api.'/'))$allowed=true;
        if(!$allowed||!in_array($method,['GET','POST','PATCH'],true))throw new \DomainException('bb_api_transport_target_denied');
        if(!function_exists('curl_init'))throw new ApiFailure('transport_unavailable',0,false);
        $handle=curl_init($url);if($handle===false)throw new ApiFailure('transport_unavailable',0,true);
        $bytes='';$overflow=false;
        try{
            $options=[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_RETURNTRANSFER=>false,CURLOPT_WRITEFUNCTION=>static function($curl,$chunk)use(&$bytes,&$overflow):int{if(strlen($bytes)+strlen($chunk)>1024*1024){$overflow=true;return 0;}$bytes.=$chunk;return strlen($chunk);}];
            if(in_array($method,['POST','PATCH'],true))$options[CURLOPT_POSTFIELDS]=$body;
            curl_setopt_array($handle,$options);
            $ok=curl_exec($handle);$status=(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE);
            if($ok===false||$overflow)throw new ApiFailure($overflow?'response_too_large':'transport_unconfirmed',$status,true);
            return ['status'=>$status,'body'=>$bytes];
        }finally{curl_close($handle);}
    }
    private function request(string $method,string $url,array $headers,string $body):array
    {
        try{$r=($this->transport)($method,$url,$headers,$body);}catch(ApiFailure $e){throw $e;}catch(\Throwable $e){throw new ApiFailure('transport_unconfirmed',0,true);}
        if(!is_array($r)||!is_int($r['status']??null)||!is_string($r['body']??null)||strlen($r['body'])>1024*1024)throw new ApiFailure('response_invalid',0,true);
        return $r;
    }
    public static function credentials(array $input):array
    {
        if(array_diff(array_keys($input),['client_id','client_secret','application_key'])||array_diff(['client_id','client_secret','application_key'],array_keys($input)))throw new \DomainException('bb_api_credentials_invalid');
        foreach($input as $value)if(!is_string($value)||strlen($value)<1||strlen($value)>2048||!preg_match('/^[\x21-\x7e]+$/D',$value))throw new \DomainException('bb_api_credentials_invalid');
        return $input;
    }
    /** No token is stored, logged or returned to the browser. A token failure never submits a charge. */
    public function token(string $stage,array $credentials,string $operation):string
    {
        [, $oauth]=self::stage($stage);$credentials=self::credentials($credentials);
        if(!in_array($operation,['issue','query','cancel','amend_due','amend_abat','amend_addr','amend_disc','movements'],true))throw new \DomainException('bb_api_operation_invalid');
        $scope=in_array($operation,['query','movements'],true)?'cobrancas.boletos-info':'cobrancas.boletos-requisicao';
        $basic=base64_encode(rawurlencode($credentials['client_id']).':'.rawurlencode($credentials['client_secret']));
        $response=$this->request('POST',$oauth,['Authorization: Basic '.$basic,'Content-Type: application/x-www-form-urlencoded','Accept: application/json'],http_build_query(['grant_type'=>'client_credentials','scope'=>$scope],'','&',PHP_QUERY_RFC3986));
        if($response['status']!==200)throw new ApiFailure('authentication_failed',$response['status'],false);
        try{$data=json_decode($response['body'],true,16,JSON_THROW_ON_ERROR);}catch(\Throwable $error){throw new ApiFailure('authentication_response_invalid',200,false);}
        if(!is_array($data)||!is_string($data['access_token']??null)||strlen($data['access_token'])>8192||!preg_match('/^[A-Za-z0-9._~+\/-]+=*$/D',$data['access_token'])||(!is_string($data['token_type']??null)||strtolower($data['token_type'])!=='bearer')||!is_int($data['expires_in']??null)||$data['expires_in']<1||$data['expires_in']>86400)throw new ApiFailure('authentication_response_invalid',200,false);
        return $data['access_token'];
    }
    /** Only the persisted origin determines id, agreement, environment and credentials. */
    public function execute(string $stage,array $credentials,string $operation,string $number,string $agreement,string $token,string $payload=''):array
    {
        [$base]=self::stage($stage);$credentials=self::credentials($credentials);
        if(!preg_match('/^[1-9][0-9]{6}$/D',$agreement)||!preg_match('/^000'.preg_quote($agreement,'/').'[0-9]{10}$/D',$number)||!preg_match('/^[A-Za-z0-9._~+\/-]+=*$/D',$token))throw new \DomainException('bb_api_origin_invalid');
        if(!in_array($operation,['issue','query','cancel','amend_due','amend_abat','amend_addr','amend_disc','movements'],true))throw new \DomainException('bb_api_operation_invalid');
        $query=['gw-dev-app-key'=>$credentials['application_key']];$path='/boletos';$method='POST';
        if($operation==='query'){$path.='/'.$number;$method='GET';$query['numeroConvenio']=$agreement;$payload='';}
        if($operation==='movements'){$path='/convenios/'.$agreement.'/listar-retorno-movimento';if($payload===''||strlen($payload)>65536)throw new \DomainException('bb_api_payload_invalid');}
        if(in_array($operation,['amend_due','amend_abat','amend_addr','amend_disc'],true)){$path.='/'.$number;$method='PATCH';if($payload===''||strlen($payload)>65536)throw new \DomainException('bb_api_payload_invalid');$wire=self::decodeExact($payload);if(($wire['numeroConvenio']??null)!==$agreement)throw new \DomainException('bb_api_payload_invalid');}
        if($operation==='cancel'){$path.='/'.$number.'/baixar';$payload=json_encode(['numeroConvenio'=>(int)$agreement],JSON_THROW_ON_ERROR);}
        if($operation==='issue'){if($payload===''||strlen($payload)>65536)throw new \DomainException('bb_api_payload_invalid');$wire=self::decodeExact($payload);if(($wire['numeroTituloCliente']??null)!==$number||($wire['numeroConvenio']??null)!==$agreement)throw new \DomainException('bb_api_payload_invalid');}
        $r=$this->request($method,$base.$path.'?'.http_build_query($query,'','&',PHP_QUERY_RFC3986),['Authorization: Bearer '.$token,'Content-Type: application/json','Accept: application/json'],$payload);
        $expected=$operation==='issue'?201:200;
        if($r['status']!==$expected)throw new ApiFailure($r['status']>=500?'provider_unconfirmed':'provider_rejected',$r['status'],$r['status']>=500||$r['status']<400||$r['status']>=600||in_array($r['status'],[408,409,425,429],true));
        try{$data=self::decodeExact($r['body']);}catch(\Throwable $error){throw new ApiFailure('response_invalid',$r['status'],true);}
        if(!is_array($data)||array_is_list($data))throw new ApiFailure('response_invalid',$r['status'],true);
        return ['status'=>$r['status'],'data'=>$data,'response_hash'=>hash('sha256',$r['body'])];
    }
    /** Account-scoped list confirms that the detailed Nosso Número still belongs to the origin account. */
    public function confirmAccount(string $stage,array $credentials,Agreement $agreement,string $number,string $token,array $snapshot,array $detail):array
    {
        [$base]=self::stage($stage);$credentials=self::credentials($credentials);$tax=Fields::taxId($snapshot['document']['recipient']['tax_id']);$state=$detail['codigoEstadoTituloCobranca'];
        $date=(new \DateTimeImmutable($snapshot['invoice']['duedate']))->format('d.m.Y');
        $query=['gw-dev-app-key'=>$credentials['application_key'],'indicadorSituacao'=>in_array((int)$state,[5,6,7,9,10,11,12,16],true)?'B':'A','agenciaBeneficiario'=>(int)$agreement->branch,'contaBeneficiario'=>(int)$agreement->account,'carteiraConvenio'=>17,'variacaoCarteiraConvenio'=>(int)$agreement->variation,'modalidadeCobranca'=>1,'codigoEstadoTituloCobranca'=>(int)$state,'dataInicioVencimento'=>$date,'dataFimVencimento'=>$date];
        if(strlen($tax)===11){$query['cpfPagador']=(int)substr($tax,0,-2);$query['digitoCPFPagador']=(int)substr($tax,-2);}else{$query['cnpjPagador']=substr($tax,0,-2);$query['digitoCNPJPagador']=substr($tax,-2);}
        $cursor=0;$seen=[];
        for($page=0;$page<10;$page++){
            if($cursor)$query['indice']=$cursor;
            $response=$this->request('GET',$base.'/boletos?'.http_build_query($query,'','&',PHP_QUERY_RFC3986),['Authorization: Bearer '.$token,'Accept: application/json'],'');
            if($response['status']!==200)throw new ApiFailure('account_scope_unconfirmed',$response['status'],true);
            try{$data=self::decodeExact($response['body']);}catch(\Throwable $error){throw new ApiFailure('account_scope_unconfirmed',200,true);}
            // The official response describes an array of RespostaListaBoletos; its component is one envelope object.
            if(array_is_list($data)&&count($data)===1&&is_array($data[0]))$data=$data[0];
            if(!is_array($data['boletos']??null)||!array_is_list($data['boletos'])||count($data['boletos'])>300)throw new ApiFailure('account_scope_unconfirmed',200,true);
            foreach($data['boletos'] as $title){
                if(!is_array($title)||($title['numeroBoletoBB']??null)!==$number)continue;
                $due=$title['dataVencimento']??null;
                if(!is_string($due)||!preg_match('/^([0-9]{2})[.\/]([0-9]{2})[.\/]([0-9]{4})$/D',$due,$m)||$m[3].'-'.$m[2].'-'.$m[1]!==$snapshot['invoice']['duedate']||!self::integerEquals($title['carteiraConvenio']??null,'17')||!self::integerEquals($title['variacaoCarteiraConvenio']??null,(string)(int)$agreement->variation)||!self::integerEquals($title['codigoEstadoTituloCobranca']??null,$state)||!self::integerEquals($title['contrato']??null,$detail['numeroContratoCobranca'])||\app\services\InvoicePaymentWriter::minorUnits($title['valorOriginal']??null)!==\app\services\InvoicePaymentWriter::minorUnits($snapshot['invoice']['total']))throw new ApiFailure('account_scope_unconfirmed',200,true);
                return ['account_scope_confirmed'=>'1','account_evidence_hash'=>hash('sha256',$response['body'])];
            }
            $more=$data['indicadorContinuidade']??null;if(!is_string($more)||!in_array(strtoupper($more),['S','N'],true))throw new ApiFailure('account_scope_unconfirmed',200,true);if(strtoupper($more)==='N')break;
            $next=$data['proximoIndice']??null;if(!is_string($next)||!preg_match('/^[1-9][0-9]{0,9}$/D',$next)||(int)$next<=$cursor||(int)$next>2147483647||isset($seen[$next]))throw new ApiFailure('account_scope_unconfirmed',200,true);$seen[$next]=true;$cursor=(int)$next;
        }
        // Bounded pagination is a review condition, never proof that a bank title does not exist.
        throw new ApiFailure('account_scope_unconfirmed',200,true);
    }
    private static function integerEquals($value,string $expected):bool
    {
        return is_string($value)&&preg_match('/^[0-9]+(?:\.0+)?$/D',$value)&&ltrim(explode('.',$value)[0],'0')===ltrim(explode('.',$expected)[0],'0');
    }

    /** Preserve every JSON numeric lexeme without converting money or identifiers to floating point. */
    public static function decodeExact(string $json):array
    {
        return ExactJson::decode($json);
    }
}
final class ApiFailure extends \RuntimeException
{
    public function __construct(public readonly string $category,public readonly int $httpStatus,public readonly bool $uncertain){parent::__construct('bb_api_'.$category);}
}
