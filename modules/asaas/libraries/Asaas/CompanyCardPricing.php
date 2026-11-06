<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyPreferences.php';
require_once __DIR__.'/CompanyCardPricingSchema.php';
require_once __DIR__.'/../../helpers/asaas_card_pricing_helper.php';

/** Confirmed1.5.6 calculator, with configuration owned by one issuer/account/environment. */
final class CompanyCardPricing
{
    public function __construct(private $db,private string $prefix){if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new \InvalidArgumentException('asaas_company_prefix_invalid');}
    private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public static function tables():array{return array_values(array_unique(array_merge(CompanyPreferences::tables(),CompanyCardPricingSchema::tables())));}
    private function authorize(int $issuer,int $actor,bool $write):void
    {
        if(class_exists('CompanySettingsRuntime',false)&&\CompanySettingsRuntime::active()&&(\CompanySettingsRuntime::issuerId()!==$issuer||\CompanySettingsRuntime::staffId()!==$actor))throw new \DomainException('asaas_company_access_denied');
        if(!(new \IssuerResourceAccess($this->db,$this->prefix))->can($issuer,$actor,'settings',$write?'edit':'view'))throw new \DomainException('asaas_company_access_denied');
        $module=$this->rows('SELECT active,installed_version FROM '.$this->prefix.'modules WHERE module_name=?',['asaas'])[0]??[];
        if((int)($module['active']??0)!==1||!is_string($module['installed_version']??null)||!preg_match('/^[0-9]\.[0-9]\.[0-9]$/D',$module['installed_version'])||version_compare($module['installed_version'],'1.7.3','<'))throw new \DomainException('asaas_module_173_required');
        CompanyCardPricingSchema::ensure($this->db,$this->prefix,false);
    }
    private function account(int $issuer,int $account,bool $lock=false):array
    {
        $row=$this->rows('SELECT id,issuer_id,environment,revision FROM '.$this->prefix.'asaas_company_accounts WHERE id=? AND issuer_id=?'.($lock?' FOR UPDATE':''),[$account,$issuer])[0]??null;
        if(!$row||!in_array($row['environment'],['sandbox','production'],true))throw new \DomainException('asaas_company_access_denied');return$row;
    }
    private static function revision(string $value):void{if(!preg_match('/^[a-f0-9]{64}$/D',$value))throw new \DomainException('asaas_company_preferences_stale');}
    private function read(int $issuer,array $account):array
    {
        $row=$this->rows('SELECT environment,settings_json,settings_hash,revision FROM '.$this->prefix.'asaas_company_card_pricing WHERE issuer_id=? AND account_row=?',[$issuer,$account['id']])[0]??null;
        if(!$row)return ['settings'=>\asaas_card_pricing_defaults(),'revision'=>CompanyPreferences::ZERO,'account_revision'=>$account['revision'],'environment'=>$account['environment']];
        if($row['environment']!==$account['environment']||!hash_equals($row['settings_hash'],hash('sha256',$row['settings_json'])))throw new \DomainException('asaas_card_pricing_corrupt');
        $values=json_decode($row['settings_json'],true,16,JSON_THROW_ON_ERROR);
        if(!is_array($values)||array_keys($values)!==array_keys(\asaas_card_pricing_defaults())||\asaas_card_pricing_normalize_settings($values)!==$values)throw new \DomainException('asaas_card_pricing_corrupt');
        self::revision($row['revision']);return ['settings'=>$values,'revision'=>$row['revision'],'account_revision'=>$account['revision'],'environment'=>$account['environment']];
    }
    public function page($issuer,$actor,$account):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$account=\IssuerPolicy::id($account);$this->authorize($issuer,$actor,false);return$this->read($issuer,$this->account($issuer,$account));
    }
    public function save($issuer,$actor,$account,array $values,string $revision,string $accountRevision):void
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$account=\IssuerPolicy::id($account);self::revision($revision);self::revision($accountRevision);
        $keys=array_keys(\asaas_card_pricing_defaults());if(array_diff(array_keys($values),$keys)||array_diff($keys,array_keys($values)))throw new \DomainException('asaas_card_pricing_invalid');
        try{$values=\asaas_card_pricing_normalize_settings($values);}catch(\InvalidArgumentException $error){throw new \DomainException('asaas_card_pricing_invalid');}
        $this->authorize($issuer,$actor,true);
        \IssuerTransaction::run($this->db,$this->prefix,self::tables(),function()use($issuer,$actor,$account,$values,$revision,$accountRevision){
            $this->authorize($issuer,$actor,true);$owned=$this->account($issuer,$account,true);$old=$this->read($issuer,$owned);
            if(!hash_equals($owned['revision'],$accountRevision)||!hash_equals($old['revision'],$revision))throw new \DomainException('asaas_company_preferences_stale');
            $json=json_encode($values,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$hash=hash('sha256',$json);$next=bin2hex(random_bytes(32));$now=gmdate('Y-m-d H:i:s');
            if($revision===CompanyPreferences::ZERO)\IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'asaas_company_card_pricing(issuer_id,account_row,environment,settings_json,settings_hash,revision,updated_at) VALUES(?,?,?,?,?,?,?)',[$issuer,$account,$owned['environment'],$json,$hash,$next,$now]);
            else \IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'asaas_company_card_pricing SET settings_json=?,settings_hash=?,revision=?,updated_at=? WHERE issuer_id=? AND account_row=? AND revision=?',[$json,$hash,$next,$now,$issuer,$account,$revision]);
            $after=$this->read($issuer,$owned);if($after['revision']!==$next||$after['settings']!==$values)throw new \RuntimeException('issuer_asaas_preferences_unconfirmed');
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,?,?)',[$actor,'asaas_card_pricing_saved',$issuer,$account,$now]);$id=(int)$this->db->insert_id();
            if($id<1||!$this->rows('SELECT id FROM '.$this->prefix.'issuers_audit WHERE id=? AND issuer_id=? AND subject_id=? AND event=?',[$id,$issuer,$account,'asaas_card_pricing_saved']))throw new \RuntimeException('issuer_asaas_preferences_unconfirmed');
            $this->authorize($issuer,$actor,true);
        });
    }
    /** Administrative simulation only; no invoice lookup, mutation, credential or provider access. */
    public function preview($issuer,$actor,$account,string $balance,int $count):array
    {
        $page=$this->page($issuer,$actor,$account);
        try{return \asaas_card_price($balance,$count,$page['settings']);}catch(\InvalidArgumentException $error){throw new \DomainException('asaas_card_pricing_invalid');}
    }
}
