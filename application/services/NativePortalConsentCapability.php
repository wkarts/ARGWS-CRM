<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyPortal.php';
require_once __DIR__.'/CompanyCustomerAccess.php';
require_once __DIR__.'/CompanySettingsStorage.php';
/** A purpose-specific, expiring bearer link; never establishes a portal or staff session. */
final class NativePortalConsentCapability
{
 public const TTL=86400;
 public function __construct(private$db,private string$p,private string$secret){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p)||strlen($secret)<32)throw new DomainException('native_consent_capability_denied');}
 private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
 private function policy(int$issuer):void{$store=new CompanySettingsStorage($this->db,$this->p);if($store->get($issuer,'enable_gdpr')!=='1'||$store->get($issuer,'gdpr_enable_consent_for_contacts')!=='1')throw new DomainException('native_consent_capability_denied');}
 private function source(int$issuer,int$contact):array{
  $this->policy($issuer);$row=$this->rows('SELECT id,userid,email,password FROM '.$this->p.'contacts WHERE id=?',[$contact])[0]??null;if(!$row)throw new DomainException('native_consent_capability_denied');$stamp=hash_hmac('sha256',json_encode([(int)$row['id'],(int)$row['userid'],$issuer,$row['email'],$row['password']],JSON_THROW_ON_ERROR),$this->secret);$binding=['issuer_id'=>$issuer,'contact_id'=>$contact,'client_id'=>(int)$row['userid'],'stamp'=>$stamp];$who=(new CompanyPortal($this->db,$this->p,$this->secret))->invoiceIdentity($binding,$issuer);if(!$who)throw new DomainException('native_consent_capability_denied');return['binding'=>$binding,'identity'=>$who];
 }
 private function signature(int$issuer,int$contact,int$expires,array$source):string{return hash_hmac('sha256',json_encode(['native-contact-consent-v1',$issuer,$contact,$source['binding']['client_id'],$source['binding']['stamp'],$expires],JSON_THROW_ON_ERROR),$this->secret);}
 private function issue(int$issuer,int$contact):string{$source=$this->source($issuer,$contact);$expires=time()+self::TTL;$token='v1.'.$contact.'.'.$expires.'.'.$this->signature($issuer,$contact,$expires,$source);$this->resolve($issuer,$token);return$token;}
 public function issueForBinding(CompanyPortal$portal,array$binding,int$issuer):string{$portal->assertStorageBinding($this->db,$this->p);$who=$portal->invoiceIdentity($binding,$issuer);if(!$who)throw new DomainException('native_consent_capability_denied');$token=$this->issue($issuer,$who['contact_id']);if(!$portal->invoiceIdentity($binding,$issuer))throw new DomainException('native_consent_capability_denied');return$token;}
 public function issueForStaff(int$issuer,int$actor,int$contact):string{$source=$this->source($issuer,$contact);$access=new CompanyCustomerAccess($this->db,$this->p);$client=$source['identity']['client_id'];if(!$access->can($issuer,$actor,$client,'view')&&!$access->canReadHistorical($issuer,$actor,$client))throw new DomainException('native_consent_capability_denied');$token=$this->issue($issuer,$contact);if(!$access->can($issuer,$actor,$client,'view')&&!$access->canReadHistorical($issuer,$actor,$client))throw new DomainException('native_consent_capability_denied');return$token;}
 /** Returns only safe contact identity. Its private binding cannot be promoted into another public API. */
 public function resolve(int$issuer,string$token):array{
  if(!preg_match('/^v1\.([1-9][0-9]{0,9})\.([1-9][0-9]{9})\.([a-f0-9]{64})$/D',$token,$m))throw new DomainException('native_consent_capability_denied');$contact=(int)$m[1];$expires=(int)$m[2];if($expires<=time()||$expires>time()+self::TTL)throw new DomainException('native_consent_capability_denied');$source=$this->source($issuer,$contact);if(!hash_equals($this->signature($issuer,$contact,$expires,$source),$m[3]))throw new DomainException('native_consent_capability_denied');$this->policy($issuer);return$source['identity']+['issuer_id'=>$issuer,'expires_at'=>$expires];
 }
 public function assertStorageBinding($db,string$p):void{if($db!==$this->db||$p!==$this->p)throw new DomainException('native_consent_capability_denied');}
 public function __debugInfo():array{return[];}
 public function __serialize():array{throw new LogicException('native_consent_runtime_only');}
}
