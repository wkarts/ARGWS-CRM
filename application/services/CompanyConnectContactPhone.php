<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';require_once __DIR__.'/issuers/IssuerResourceAccess.php';

/** Narrow own-contact phone editor; no permission, owner, recipient receipt or contact identity is reassigned. */
final class CompanyConnectContactPhone
{
    public function __construct(private$db,private string$p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public function tables():array{return['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_clients','clients','contacts','issuers_audit'];}
    private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public static function number(string$value):string{$value=trim($value);if(!str_starts_with($value,'+')||substr_count($value,'+')!==1||strlen($value)>60||preg_match('/[^0-9 +().-]/',$value))throw new DomainException('company_connect_api_phone_required');$digits=preg_replace('/[ +().-]/','',$value);if(!preg_match('/^[1-9][0-9]{1,14}$/D',$digits))throw new DomainException('company_connect_api_phone_required');return$digits;}
    private function auth(int$issuer,int$actor,int$client,string$cap):void{$acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($issuer,$actor,'customers',$cap)||!$this->rows('SELECT c.userid FROM '.$this->p.'clients c JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid AND o.owner_issuer_id=? WHERE c.userid=? AND c.active=1 FOR UPDATE',[$issuer,$client]))throw new DomainException('company_connect_api_denied');}
    private static function revision(array$r,int$epoch):string{$r['id']=(int)$r['id'];$r['userid']=(int)$r['userid'];$r['active']=(int)$r['active'];return hash('sha256',json_encode(['company-connect-contact-phone-v1',$r,$epoch],JSON_THROW_ON_ERROR));}
    public function epochInOwned(int$issuer,int$contact):int{IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());return(int)($this->rows("SELECT MAX(id) epoch FROM ".$this->p."issuers_audit WHERE issuer_id=? AND subject_id=? AND event='company_connect_contact_phone_changed'",[$issuer,$contact])[0]['epoch']??0);}
    private function row(int$client,int$contact):array{$r=$this->rows('SELECT id,userid,firstname,lastname,email,phonenumber,active FROM '.$this->p.'contacts WHERE id=? AND userid=? AND active=1 FOR UPDATE',[$contact,$client])[0]??null;if(!$r)throw new DomainException('company_connect_api_denied');return$r;}
    public function pageInOwned(int$issuer,int$actor,int$client):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$this->auth($issuer,$actor,$client,'view');$rows=$this->rows('SELECT id,userid,firstname,lastname,email,phonenumber,active FROM '.$this->p.'contacts WHERE userid=? AND active=1 ORDER BY id LIMIT 101',[$client]);if(count($rows)>100)throw new DomainException('company_connect_api_contact_limit');return array_map(function($r)use($issuer){$digits=preg_replace('/[^0-9]/','',$r['phonenumber']);return['id'=>(int)$r['id'],'name'=>trim($r['firstname'].' '.$r['lastname']),'phone'=>$digits===''?'Não informado':'***'.substr($digits,-4),'revision'=>self::revision($r,$this->epochInOwned($issuer,(int)$r['id']))];},$rows);
    }
    public function changeInOwned(int$issuer,int$actor,int$client,int$contact,string$revision,string$phone):void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$this->auth($issuer,$actor,$client,'edit');if(!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('company_connect_api_invalid');$number='+'.self::number($phone);$r=$this->row($client,$contact);if(!hash_equals(self::revision($r,$this->epochInOwned($issuer,$contact)),$revision))throw new DomainException('company_connect_api_stale');if($r['phonenumber']===$number)return;IssuerStorage::query($this->db,'UPDATE '.$this->p.'contacts SET phonenumber=? WHERE id=? AND userid=?',[$number,$contact,$client]);$new=$this->row($client,$contact);if($new['phonenumber']!==$number||array_diff_assoc(array_diff_key($new,['phonenumber'=>1]),array_diff_key($r,['phonenumber'=>1])))throw new RuntimeException('issuer_connect_api_write_unconfirmed');$stamp=gmdate('Y-m-d H:i:s');IssuerStorage::query($this->db,"INSERT INTO ".$this->p."issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,'company_connect_contact_phone_changed',?,?,?)",[$actor,$issuer,$contact,$stamp]);$id=(int)$this->db->insert_id();if(!$this->rows("SELECT id FROM ".$this->p."issuers_audit WHERE id=? AND actor_id=? AND issuer_id=? AND subject_id=? AND event='company_connect_contact_phone_changed' AND created_at=?",[$id,$actor,$issuer,$contact,$stamp]))throw new RuntimeException('issuer_connect_api_write_unconfirmed');$this->auth($issuer,$actor,$client,'edit');
    }
}
