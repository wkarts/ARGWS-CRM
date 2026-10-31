<?php
declare(strict_types=1);
require_once __DIR__.'/CompanySettingsStorage.php';
/** Literal public factory defaults from versioned seed; never inherit another issuer's options. */
final class NativePortalPreferences
{
 public const DEFAULTS=['company_requires_vat_number_field'=>'1','allow_primary_contact_to_view_edit_billing_and_shipping'=>'0','allow_registration'=>'0','company_is_required'=>'1','customers_register_require_confirmation'=>'0','enable_honeypot_spam_validation'=>'0','disable_language'=>'1','required_register_fields'=>'[]','use_recaptcha_customers_area'=>'0','recaptcha_site_key'=>'','recaptcha_secret_key'=>'','recaptcha_ignore_ips'=>''];
 public const REQUIRED=['contact_contact_phonenumber','contact_website','contact_title','company_vat','company_phonenumber','company_country','company_city','company_address','company_zip','company_state'];
 public static function fields():array{return array_keys(self::DEFAULTS);}
 public static function prepare(array$values):array{
  if(array_diff(array_keys($values),self::fields()))throw new DomainException('native_portal_preferences_invalid');
  foreach($values as$k=>$v){if(!is_string($v))throw new DomainException('native_portal_preferences_invalid');
   if($k==='required_register_fields'){$data=json_decode($v,true);if(strlen($v)>1000||!is_array($data)||!array_is_list($data)||count(array_filter($data,'is_string'))!==count($data)||array_diff($data,self::REQUIRED)||count(array_unique($data))!==count($data))throw new DomainException('native_portal_preferences_invalid');}
   elseif($k==='recaptcha_ignore_ips'){self::ips($v);}
   elseif($k==='recaptcha_site_key'){if(!preg_match('/^[A-Za-z0-9_-]{0,256}$/D',$v))throw new DomainException('native_portal_preferences_invalid');}
   elseif($k==='recaptcha_secret_key'){if(strlen($v)>16384||str_contains($v,"\0"))throw new DomainException('native_portal_preferences_invalid');}
   elseif(!in_array($v,['0','1'],true))throw new DomainException('native_portal_preferences_invalid');
  }return$values;
 }
 public function __construct(private$db,private string$p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
 /** Public DTO never contains ciphertext, SMTP configuration, admin flags or customer data. */
 public function publicValues(int$issuer):array{
  $store=new CompanySettingsStorage($this->db,$this->p);$values=[];foreach(self::DEFAULTS as$k=>$default)if(!in_array($k,['recaptcha_secret_key','recaptcha_ignore_ips'],true))$values[$k]=$store->exists($issuer,$k)?$store->get($issuer,$k):$default;
  self::prepare($values);return$values;
 }
 public function captchaSecret(int$issuer,callable$decrypt):string{
  $store=new CompanySettingsStorage($this->db,$this->p);$cipher=$store->get($issuer,'recaptcha_secret_key');if($cipher==='')throw new DomainException('native_portal_captcha_unconfigured');
  $plain=$store->isPrimary($issuer)?$cipher:$decrypt($cipher);if(!is_string($plain)||!preg_match('/^[A-Za-z0-9_-]{16,256}$/D',$plain))throw new DomainException('native_portal_captcha_unconfigured');return$plain;
 }
 public function revision(int$issuer):string{$store=new CompanySettingsStorage($this->db,$this->p);$values=[];foreach(self::DEFAULTS as$k=>$default)$values[$k]=$store->exists($issuer,$k)?$store->get($issuer,$k):$default;return hash('sha256',json_encode([$issuer,$values],JSON_THROW_ON_ERROR));}
 public static function tables():array{return ['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_audit','options'];}
 private static function ips(string$value):array{
  if(strlen($value)>4096)throw new DomainException('native_portal_preferences_invalid');if($value==='')return[];$ips=array_map('trim',explode(',',$value));
  if(count($ips)>100||count(array_unique($ips))!==count($ips))throw new DomainException('native_portal_preferences_invalid');foreach($ips as$ip)if(!filter_var($ip,FILTER_VALIDATE_IP))throw new DomainException('native_portal_preferences_invalid');return$ips;
 }
 public function captchaIgnored(int$issuer,string$ip):bool{
  if(!filter_var($ip,FILTER_VALIDATE_IP))return false;$store=new CompanySettingsStorage($this->db,$this->p);$value=$store->exists($issuer,'recaptcha_ignore_ips')?$store->get($issuer,'recaptcha_ignore_ips'):'';return in_array($ip,self::ips($value),true);
 }
 /** Empty unhydrated secret means preserve; named secrets are encrypted, proven principal retains core representation. */
 public function save(int$issuer,int$actor,string$revision,array$values,callable$encrypt):void{
  IssuerTransaction::run($this->db,$this->p,self::tables(),fn()=>$this->saveInOwnedTransaction($issuer,$actor,$revision,$values,$encrypt));
 }
 public function saveInOwnedTransaction(int$issuer,int$actor,string$revision,array$values,callable$encrypt):void{
  IssuerTransaction::assertOwned($this->db,$this->p,self::tables());self::prepare($values);$acl=new IssuerResourceAccess($this->db,$this->p);$store=new CompanySettingsStorage($this->db,$this->p);
  $check=function()use($acl,$issuer,$actor){if(!$acl->administrative($issuer,$actor)||!$acl->can($issuer,$actor,'settings','edit'))throw new DomainException('native_portal_preferences_denied');};$check();
  if(!hash_equals($this->revision($issuer),$revision))throw new DomainException('native_portal_preferences_stale');$primary=$store->isPrimary($issuer);
  if(array_key_exists('recaptcha_secret_key',$values)){
   $plain=$values['recaptcha_secret_key'];if($plain==='')unset($values['recaptcha_secret_key']);else{
    if(!preg_match('/^[A-Za-z0-9_-]{16,256}$/D',$plain))throw new DomainException('native_portal_preferences_invalid');
    if(!$primary){$cipher=$encrypt($plain);if(!is_string($cipher)||$cipher===''||$cipher===$plain||strlen($cipher)>16384)throw new DomainException('native_portal_preferences_invalid');$values['recaptcha_secret_key']=$cipher;}
   }
  }
  $check();if(!hash_equals($this->revision($issuer),$revision))throw new DomainException('native_portal_preferences_stale');
  if($values){if($primary){$guard=new CompanySettingsCommitGuard($this->db,$this->p,$issuer,$actor,'edit');foreach($values as$name=>$value)$guard->writeOption('update',$name,$value);$guard->audit('company_settings_batch_updated');$guard->confirm();}else$store->updateBatchInOwnedTransaction($issuer,$actor,$values);}
  $check();foreach($values as$name=>$value)if($store->get($issuer,$name)!==$value)throw new RuntimeException('native_portal_preferences_unconfirmed');
 }
}
