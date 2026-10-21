<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyClientProfileSchema.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
/** Private, issuer-keyed supplementary identity; never part of the shared client projection. */
final class CompanyClientProfile
{
    public const TABLES=['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','issuers_clients','issuers_client_links','clients','company_client_profiles'];
    public const EXTRA=['person_type'=>2,'trade_name'=>191,'rg'=>40,'state_registration'=>40,'address_number'=>30,'address_complement'=>100,'address_district'=>100,'country_code'=>2];
    public const NATIVE=['address'=>191,'city'=>100,'state'=>50,'zip'=>15,'phonenumber'=>30];
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public static function normalize(array $input,?string $tax):array
    {
        if(array_diff(array_keys($input),array_keys(self::EXTRA+self::NATIVE)))throw new DomainException('company_client_invalid_input');
        foreach($input as $key=>$value){if(!is_string($value)||strlen($value)>(self::EXTRA+self::NATIVE)[$key]||preg_match('/[\x00-\x1f\x7f]/',$value)||preg_match('//u',$value)!==1)throw new DomainException('company_client_invalid_input');$input[$key]=trim($value);}
        $type=$input['person_type']??'';
        if(!in_array($type,['','pf','pj'],true))throw new DomainException('company_client_invalid_input');
        if($type!==''&&$tax!==null&&$tax!==''&&(($type==='pf'&&strlen($tax)!==11)||($type==='pj'&&strlen($tax)!==14)))throw new DomainException('company_client_invalid_input');
        $effective=$type!==''?$type:($tax?(strlen($tax)===11?'pf':'pj'):'');
        if(($effective==='pf'&&(!empty($input['state_registration'])||!empty($input['trade_name'])))||($effective==='pj'&&!empty($input['rg'])))throw new DomainException('company_client_invalid_input');
        if(isset($input['country_code'])){$input['country_code']=strtoupper($input['country_code']);if($input['country_code']!==''&&!preg_match('/^[A-Z]{2}$/D',$input['country_code']))throw new DomainException('company_client_invalid_input');}
        ksort($input);return $input;
    }
    public function allowed(int $issuer,int $actor,int $client,string $cap='view'):bool
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($issuer,$actor,'customers',$cap))return false;
        $owner=$this->rows('SELECT owner_issuer_id FROM '.$this->p.'issuers_clients WHERE client_id=?',[$client])[0]??null;
        if($owner)return (int)$owner['owner_issuer_id']===$issuer;
        return $acl->primaryId()===$issuer && (bool)$this->rows('SELECT userid FROM '.$this->p.'clients WHERE userid=?',[$client]);
    }
    private static function validateStored($extra):void
    {
        if(!is_array($extra)||array_diff(array_keys($extra),array_keys(self::EXTRA)))throw new RuntimeException('company_client_profile_corrupt');
        foreach($extra as $key=>$value)if(!is_string($value)||strlen($value)>self::EXTRA[$key]||preg_match('/[\x00-\x1f\x7f]/',$value)||preg_match('//u',$value)!==1)throw new RuntimeException('company_client_profile_corrupt');
    }
    public function read(int $issuer,int $actor,int $client):array
    {
        if(!$this->allowed($issuer,$actor,$client))throw new DomainException('company_client_access_denied');
        if(!CompanyClientProfileSchema::ready($this->db,$this->p))return [];
        $row=$this->rows('SELECT '.implode(',',array_keys(self::NATIVE)).' FROM '.$this->p.'clients WHERE userid=?',[$client])[0]??[];
        foreach($row as $key=>$value)$row[$key]=(string)($value??'');
        $stored=$this->rows('SELECT profile_json FROM '.$this->p.'company_client_profiles WHERE issuer_id=? AND client_id=?',[$issuer,$client])[0]??null;
        $extra=$stored?json_decode($stored['profile_json'],true,16,JSON_THROW_ON_ERROR):[];
        self::validateStored($extra);
        return $row+$extra;
    }
    /** Caller owns the client-row transaction; failures must roll back that transaction. */
    public function write(int $issuer,int $actor,int $client,array $input,string $cap='edit'):void
    {
        if(!$this->allowed($issuer,$actor,$client,$cap))throw new DomainException('company_client_access_denied');
        if(!CompanyClientProfileSchema::ready($this->db,$this->p))throw new DomainException('company_client_profile_upgrade_required');
        IssuerTransaction::assertOwned($this->db,$this->p,self::TABLES);
        CompanyClientProfileSchema::assertValid($this->db,$this->p);
        $tax=$this->rows('SELECT vat FROM '.$this->p.'clients WHERE userid=?',[$client])[0]['vat']??null;
        $normalizedTax=null;try{$normalizedTax=$tax?IssuerPolicy::taxId($tax,false):null;}catch(DomainException $error){if(!empty($input['person_type']))throw $error;}$input=self::normalize($input,$normalizedTax);
        $stored=$this->rows('SELECT profile_json FROM '.$this->p.'company_client_profiles WHERE issuer_id=? AND client_id=?',[$issuer,$client])[0]??null;$extra=$stored?json_decode($stored['profile_json'],true,16,JSON_THROW_ON_ERROR):[];self::validateStored($extra);$extra=array_replace($extra,array_intersect_key($input,self::EXTRA));$extra=self::normalize($extra,$normalizedTax);ksort($extra);
        $native=array_intersect_key($input,self::NATIVE);
        if(!empty($input['country_code'])){$country=$this->rows('SELECT country_id FROM '.$this->p.'countries WHERE iso2=?',[$input['country_code']])[0]??null;if(!$country)throw new DomainException('company_client_invalid_input');$native['country']=(int)$country['country_id'];}
        if($native){$set=[];$args=[];foreach($native as $key=>$value){$set[]=$key.'=?';$args[]=$value;}$args[]=$client;IssuerStorage::query($this->db,'UPDATE '.$this->p.'clients SET '.implode(',',$set).' WHERE userid=?',$args);$row=$this->rows('SELECT '.implode(',',array_keys($native)).' FROM '.$this->p.'clients WHERE userid=?',[$client])[0]??[];foreach($native as $key=>$value)if((string)($row[$key]??'')!==(string)$value)throw new RuntimeException('issuer_client_write_unconfirmed');}
        $json=json_encode($extra,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        if($this->rows('SELECT client_id FROM '.$this->p.'company_client_profiles WHERE issuer_id=? AND client_id=?',[$issuer,$client]))IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_client_profiles SET profile_json=? WHERE issuer_id=? AND client_id=?',[$json,$issuer,$client]);
        else IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_client_profiles(issuer_id,client_id,profile_json) VALUES(?,?,?)',[$issuer,$client,$json]);
        if(($this->rows('SELECT profile_json FROM '.$this->p.'company_client_profiles WHERE issuer_id=? AND client_id=?',[$issuer,$client])[0]['profile_json']??null)!==$json)throw new RuntimeException('issuer_client_write_unconfirmed');
        if(!$this->allowed($issuer,$actor,$client,$cap))throw new DomainException('company_client_access_denied');
    }
}
