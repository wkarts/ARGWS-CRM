<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyGoogleCalendarPreferences.php';
require_once __DIR__.'/NativePortalPreferences.php';
/** The existing Google settings form: one issuer, one revision and one atomic save. */
final class NativeCompanyGoogleSettings
{
 public const FIELDS=['google_api_key','google_client_id','recaptcha_site_key','recaptcha_secret_key','use_recaptcha_customers_area','recaptcha_ignore_ips','google_calendar_main_calendar','enable_google_picker'];
 public function __construct(private$db,private string$p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
 public function tables():array{return array_values(array_unique(array_merge((new NativeCompanyGoogleCalendarPreferences($this->db,$this->p))->tables(),NativePortalPreferences::tables())));}
 private function authorize(int$i,int$a,bool$edit=false):void{$acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($i,$a,'settings','view')||($edit&&(!$acl->administrative($i,$a)||!$acl->can($i,$a,'settings','edit'))))throw new DomainException('native_google_settings_denied');}
 private function capture(int$i,int$a):array{
  IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$this->authorize($i,$a);$store=new CompanySettingsStorage($this->db,$this->p);$calendar=(new NativeCompanyGoogleCalendarPreferences($this->db,$this->p))->valuesInOwnedTransaction($i,$a);$portal=new NativePortalPreferences($this->db,$this->p);$portalRevision=$portal->revision($i);$values=[];
  foreach(self::FIELDS as$key){if($key==='recaptcha_secret_key')$values[$key]='';elseif(array_key_exists($key,$calendar['values']))$values[$key]=$calendar['values'][$key];else$values[$key]=$store->exists($i,$key)?$store->get($i,$key):(in_array($key,['enable_google_picker','use_recaptcha_customers_area'],true)?'0':'');}
  self::validate($values);$configured=$store->get($i,'recaptcha_secret_key')!=='';
  return['values'=>$values,'secret_configured'=>$configured,'calendar_revision'=>$calendar['revision'],'portal_revision'=>$portalRevision,'revision'=>hash('sha256',json_encode([$i,$values,$calendar['revision'],$portalRevision],JSON_THROW_ON_ERROR))];
 }
 private static function validate(array$values):void{
  $keys=array_keys($values);sort($keys);$expected=self::FIELDS;sort($expected);if($keys!==$expected)throw new DomainException('native_google_settings_invalid');
  foreach($values as$key=>$value)if(!is_string($value)||strlen($value)>16384||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value))throw new DomainException('native_google_settings_invalid');
  NativeCompanyGoogleCalendarPreferences::prepare(array_intersect_key($values,array_flip(NativeCompanyGoogleCalendarPreferences::KEYS)));
  NativePortalPreferences::prepare(array_intersect_key($values,array_flip(['recaptcha_site_key','recaptcha_secret_key','use_recaptcha_customers_area','recaptcha_ignore_ips'])));
  if(!in_array($values['enable_google_picker'],['0','1'],true)||!preg_match('/^[A-Za-z0-9_.-]{0,255}$/D',$values['google_client_id']))throw new DomainException('native_google_settings_invalid');
  if($values['enable_google_picker']==='1'&&($values['google_client_id']===''||$values['google_api_key']===''))throw new DomainException('native_google_settings_invalid');
 }
 private static function publicPage(array$v):array{unset($v['calendar_revision'],$v['portal_revision']);return$v+['assets'=>null,'custom_fields'=>[]];}
 public function page(int$i,int$a):array{return IssuerTransaction::run($this->db,$this->p,$this->tables(),fn()=>self::publicPage($this->capture($i,$a)));}
 public function save(int$i,int$a,string$revision,array$values,callable$encrypt):array{
  $this->authorize($i,$a,true);self::validate($values);if(!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('native_google_settings_stale');
  return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($i,$a,$revision,$values,$encrypt){
   $this->authorize($i,$a,true);(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($i,$a);$before=$this->capture($i,$a);if(!hash_equals($before['revision'],$revision))throw new DomainException('native_google_settings_stale');
   (new NativeCompanyGoogleCalendarPreferences($this->db,$this->p))->saveInOwnedTransaction($i,$a,$before['calendar_revision'],array_intersect_key($values,array_flip(NativeCompanyGoogleCalendarPreferences::KEYS)));
   (new NativePortalPreferences($this->db,$this->p))->saveInOwnedTransaction($i,$a,$before['portal_revision'],array_intersect_key($values,array_flip(['recaptcha_site_key','recaptcha_secret_key','use_recaptcha_customers_area','recaptcha_ignore_ips'])),$encrypt);
   $store=new CompanySettingsStorage($this->db,$this->p);$picker=array_intersect_key($values,array_flip(['google_client_id','enable_google_picker']));
   if($store->isPrimary($i)){$guard=new CompanySettingsCommitGuard($this->db,$this->p,$i,$a,'edit');foreach($picker as$key=>$value)$guard->writeOption('update',$key,$value);$guard->audit('company_settings_batch_updated');$guard->confirm();}else$store->updateBatchInOwnedTransaction($i,$a,$picker);
   $this->authorize($i,$a,true);$after=$this->capture($i,$a);$expected=$values;$expected['recaptcha_secret_key']='';if($after['values']!==$expected)throw new RuntimeException('native_google_settings_unconfirmed');return self::publicPage($after);
  });
 }
}
