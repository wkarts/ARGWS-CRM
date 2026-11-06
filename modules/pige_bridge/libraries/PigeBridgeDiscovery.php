<?php
declare(strict_types=1);

/** Verified PIGE360 administrative contracts. JWT authorization remains distinct from bridge HMAC. */
final class PigeBridgeDiscovery
{
    public function __construct(private $authorize, private $transport=null, private $resolver=null) {}
    public static function origin(string $url):string
    {
        $parts=parse_url($url);
        if(!is_array($parts)||($parts['scheme']??'')!=='https'||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])||isset($parts['port'])&&$parts['port']!==443||!in_array($parts['path']??'',['','/'],true))throw new DomainException('pige_discovery_destination');
        $host=strtolower($parts['host']??'');
        if(strlen($host)>253||!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D',$host))throw new DomainException('pige_discovery_destination');
        return 'https://'.$host;
    }
    public static function publicAddress(string $address):bool
    {
        // Only public IPv4 is supported by this transport; no IPv6, mapped IPs or local aliases.
        if(filter_var($address,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4|FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)===false)return false;
        $n=unpack('N',inet_pton($address))[1];
        foreach([['0.0.0.0',8],['100.64.0.0',10],['192.0.0.0',24],['192.0.2.0',24],['198.18.0.0',15],['198.51.100.0',24],['203.0.113.0',24],['224.0.0.0',4],['240.0.0.0',4]] as [$network,$bits]){
            $mask=(0xffffffff << (32-$bits)) & 0xffffffff;
            if(($n & $mask)===(unpack('N',inet_pton($network))[1] & $mask))return false;
        }
        return true;
    }
    private function address(string $host):string
    {
        $addresses=$this->resolver!==null?($this->resolver)($host):gethostbynamel($host);
        if(!is_array($addresses)||!$addresses||count($addresses)>32)throw new DomainException('pige_discovery_destination');
        foreach($addresses as $address)if(!is_string($address)||!self::publicAddress($address))throw new DomainException('pige_discovery_destination');
        return $addresses[0];
    }
    private function request(string $origin,string $path,string $credential,bool $list=false,string $method='GET',array $payload=[]):array
    {
        ($this->authorize)();
        if(!preg_match('/^[A-Za-z0-9_-]{1,4096}\.[A-Za-z0-9_-]{1,8192}\.[A-Za-z0-9_-]{1,4096}$/D',$credential))throw new DomainException('pige_discovery_admin_credential');
        $host=(string)parse_url($origin,PHP_URL_HOST);$address=$this->address($host);
        try{$response=$this->transport!==null?($this->transport)($origin.$path,$credential,$host,$address,$method,$payload):self::https($origin.$path,$credential,$host,$address,$method,$payload);}
        catch(Throwable $error){throw new DomainException('pige_discovery_transport');}
        ($this->authorize)();
        if(!is_array($response)||!isset($response['status'],$response['body'])||!is_int($response['status'])||!is_string($response['body'])||strlen($response['body'])>262144)throw new DomainException('pige_discovery_response');
        if(in_array($response['status'],[401,403],true))throw new DomainException('pige_discovery_access');
        if($response['status']!==200)throw new DomainException('pige_discovery_response');
        try{$parsed=json_decode($response['body'],false,32,JSON_THROW_ON_ERROR);$data=json_decode($response['body'],true,32,JSON_THROW_ON_ERROR);}catch(Throwable $error){throw new DomainException('pige_discovery_response');}
        if(($list?!is_array($parsed):!$parsed instanceof stdClass)||!is_array($data))throw new DomainException('pige_discovery_response');return $data;
    }
    private static function https(string $url,string $credential,string $host,string $address,string $method='GET',array $payload=[]):array
    {
        if(!function_exists('curl_init'))throw new RuntimeException('pige_discovery_transport');
        $ch=curl_init($url);$body='';
        try{
            curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
                CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
                // Pin the validated address and keep TLS hostname verification. A proxy would bypass this DNS boundary.
                CURLOPT_PROXY=>'',CURLOPT_RESOLVE=>[$host.':443:'.$address],CURLOPT_HTTPGET=>true,
                CURLOPT_HTTPHEADER=>['Accept: application/json','Content-Type: application/json','Authorization: Bearer '.$credential],
                CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>262144)return 0;$body.=$chunk;return strlen($chunk);}]);
            if($method==='PUT'){curl_setopt($ch,CURLOPT_CUSTOMREQUEST,'PUT');curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload,JSON_THROW_ON_ERROR));}
            elseif($method!=='GET'||$payload!==[])throw new RuntimeException('pige_discovery_transport');
            if(curl_exec($ch)!==true)throw new RuntimeException('pige_discovery_transport');
            return ['status'=>(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE),'body'=>$body];
        }finally{curl_close($ch);}
    }
    private static function school(string $id):string
    {if(!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D',$id))throw new DomainException('pige_discovery_school');return $id;}
    /** Explicit new remote preparation only; existing configurations are never overwritten. */
    public function prepareRemote(string $url,string $credential,array $scope,string $secret,string $crmUrl):array
    {
        $origin=self::origin($url);$crm=self::origin($crmUrl);$school=self::school($scope['school_id']??'');
        if(array_keys($scope)!==['school_id','issuer_id','account_id','environment']||!is_string($scope['issuer_id'])||!preg_match('/^[1-9][0-9]{0,18}$/D',$scope['issuer_id'])||!is_string($scope['account_id'])||!preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D',$scope['account_id'])||!in_array($scope['environment'],['sandbox','production'],true)||!preg_match('/^[!-~]{32,512}$/D',$secret))throw new DomainException('pige_preparation_scope');
        $path='/api/v1/schools/'.$school.'/crm-bridge/configuration';
        $before=$this->request($origin,$path,$credential);
        if(!array_key_exists('configuration',$before))throw new DomainException('pige_discovery_response');
        if($before['configuration']!==null)throw new DomainException('pige_preparation_already_configured');
        $payload=['version'=>null,'enabled'=>false,'base_url'=>$crm,'issuer_id'=>$scope['issuer_id'],'account_id'=>$scope['account_id'],'environment'=>$scope['environment'],'secret'=>$secret];
        // PUT carries the expected absent revision. A concurrent creator is rejected by the PIGE CAS.
        try{$row=$this->request($origin,$path,$credential,false,'PUT',$payload);}finally{unset($payload,$secret);}
        if(($row['base_url']??null)!==$crm||($row['issuer_id']??null)!==$scope['issuer_id']||($row['account_id']??null)!==$scope['account_id']||($row['environment']??null)!==$scope['environment']||($row['enabled']??null)!==false||($row['secret_configured']??null)!==true||!is_int($row['version']??null)||$row['version']<1)throw new DomainException('pige_preparation_unconfirmed');
        return ['state'=>'prepared_disabled','remote_revision'=>$row['version'],'authenticated'=>false];
    }
    public function schools(string $url,string $credential):array
    {
        $rows=$this->request(self::origin($url),'/api/v1/schools',$credential,true);
        if(!array_is_list($rows)||count($rows)>100)throw new DomainException('pige_discovery_response');
        $out=[];$seen=[];foreach($rows as $row){
            if(!is_array($row)||!is_string($row['id']??null)||!is_string($row['name']??null)||($row['active']??null)!==true||strlen($row['name'])>500||trim($row['name'])===''||preg_match('/[\x00-\x1f\x7f]/',$row['name'])||preg_match('//u',$row['name'])!==1)throw new DomainException('pige_discovery_response');
            $id=self::school($row['id']);if(isset($seen[$id]))throw new DomainException('pige_discovery_response');$seen[$id]=true;$out[]=['id'=>$id,'name'=>$row['name']];
        }
        return $out;
    }
    public function configuration(string $url,string $credential,string $school,int $issuer,string $crmUrl):array
    {
        $school=self::school($school);$origin=self::origin($url);$crm=self::origin($crmUrl);
        if($issuer<1)throw new DomainException('pige_discovery_scope');
        $data=$this->request($origin,'/api/v1/schools/'.$school.'/crm-bridge/configuration',$credential);$row=$data['configuration']??null;
        if($row===null)throw new DomainException('pige_discovery_not_configured');
        if(!is_array($row)||!is_string($row['base_url']??null)||self::origin($row['base_url'])!==$crm||($row['issuer_id']??null)!==(string)$issuer)throw new DomainException('pige_discovery_scope');
        if(!is_string($row['account_id']??null)||!preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D',$row['account_id'])||!in_array($row['environment']??null,['sandbox','production'],true)||!is_int($row['version']??null)||$row['version']<1||!is_bool($row['secret_configured']??null))throw new DomainException('pige_discovery_response');
        // No remote token, enabled flag, financial permission or connection ID is imported.
        return ['school_id'=>$school,'issuer_id'=>(string)$issuer,'account_id'=>$row['account_id'],'environment'=>$row['environment'],'remote_revision'=>$row['version'],'secret_configured'=>$row['secret_configured']];
    }
}
