<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyTaskTimerStopPreferences.php';

/** Reads the public timer preference without hydrating global options for another company. */
final class NativeCompanyTimerPolicy
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    /** Same eligibility for the original header and Tasks endpoint; no hidden placeholders. */
    public static function canStartBlank(array$policy,array$timers):bool
    {
        foreach(['can_edit','auto_stop']as$key)if(!isset($policy[$key])||!is_bool($policy[$key]))throw new DomainException('native_timer_policy_invalid');$legacy=false;
        require_once __DIR__.'/NativeCompanyResourceId.php';foreach($timers as$timer){if(!array_key_exists('task_id',$timer))throw new DomainException('native_timer_policy_invalid');$source=NativeCompanyResourceId::decode('timer',$timer['id']??null);if((string)$timer['task_id']==='0')return false;if($source['source_kind']==='timer')$legacy=true;}
        return$policy['can_edit']&&(!$policy['auto_stop']||!$legacy);
    }
    public static function tables():array{return ['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','options'];}
    public function read($issuer,$actor):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);
        return IssuerTransaction::run($this->db,$this->p,self::tables(),fn()=>$this->readInOwned($issuer,$actor));
    }
    public function readInOwned(int $issuer,int $actor):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,self::tables());$acl=new IssuerResourceAccess($this->db,$this->p);$acl->lockFinancialIdentity($issuer,$actor);
        if(!$acl->can($issuer,$actor,'tasks','view'))throw new DomainException('native_company_task_denied');
        $primary=$acl->primaryId();$policy=CompanyTaskTimerStopPreferences::readInOwnedTransaction($this->db,$this->p,$issuer);
        $value=$policy[CompanyTaskTimerStopPreferences::AUTO_STOP];$source=$policy['revision']===0?'canonical_default':'owned_override';$legacy=null;
        if($primary===$issuer && $policy['revision']===0){
            $legacy=IssuerStorage::query($this->db,'SELECT name,value FROM '.$this->p.'options WHERE name=? FOR UPDATE',[CompanyTaskTimerStopPreferences::AUTO_STOP])->result_array();
            if(count($legacy)!==1||!in_array($legacy[0]['value'],['0','1'],true))throw new DomainException('native_timer_policy_invalid');
            $value=$legacy[0]['value'];$source='principal_legacy';
        }
        $out=['auto_stop'=>$value==='1','revision'=>$policy['revision'],'source'=>$source,'can_edit'=>$acl->can($issuer,$actor,'tasks','edit'),'can_view_all'=>$acl->can($issuer,$actor,'reports','view')];
        CompanyTaskTimerStopPreferences::confirmInOwnedTransaction($this->db,$this->p,$issuer,$policy);
        if($legacy!==null&&IssuerStorage::query($this->db,'SELECT name,value FROM '.$this->p.'options WHERE name=? FOR UPDATE',[CompanyTaskTimerStopPreferences::AUTO_STOP])->result_array()!==$legacy)throw new DomainException('native_timer_policy_changed');
        if($acl->primaryId()!==$primary||!$acl->can($issuer,$actor,'tasks','view')||$acl->can($issuer,$actor,'tasks','edit')!==$out['can_edit']||$acl->can($issuer,$actor,'reports','view')!==$out['can_view_all'])throw new DomainException('native_timer_policy_changed');return $out;
    }
}
