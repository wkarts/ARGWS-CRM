<?php
declare(strict_types=1);
require_once __DIR__.'/NativePortalPreferences.php';
require_once __DIR__.'/NativeCompanyContactPreferences.php';
require_once __DIR__.'/NativeCompanyCustomFields.php';
require_once __DIR__.'/NativeCompanyContentHtml.php';
/** The existing Clients settings controls and their native wire formats. */
final class NativeCompanyClientsSettings
{
 public const DEFAULTS=[
  'clients_default_theme'=>'perfex','customer_default_country'=>'','visible_customer_profile_tabs'=>'all','required_register_fields'=>'[]',
  'company_is_required'=>'1','company_requires_vat_number_field'=>'1','allow_registration'=>'0','customers_register_require_confirmation'=>'0',
  'allow_primary_contact_to_manage_other_contacts'=>'0','enable_honeypot_spam_validation'=>'0','allow_primary_contact_to_view_edit_billing_and_shipping'=>'0',
  'only_own_files_contacts'=>'0','allow_contact_to_delete_files'=>'0','use_knowledge_base'=>'0','knowledge_base_without_registration'=>'0',
  'show_estimate_request_in_customers_area'=>'0','default_contact_permissions'=>NativeCompanyContactPreferences::DEFAULT_PERMISSIONS,
  'customer_info_format'=>"{company_name}<br />\r\n      {street}<br />\r\n      {city} {state}<br />\r\n      {country_code} {zip_code}<br />\r\n      {vat_number_with_label}",
 ];
 public const TABS=['profile'=>'client_add_edit_profile','contacts'=>'customer_contacts','notes'=>'contracts_notes_tab','statement'=>'customer_statement','invoices'=>'client_invoices_tab','payments'=>'client_payments_tab','proposals'=>'proposals','credit_notes'=>'credit_notes','estimates'=>'estimates','subscriptions'=>'subscriptions','expenses'=>'expenses','contracts'=>'contracts','projects'=>'projects','tasks'=>'tasks','tickets'=>'tickets','attachments'=>'customer_attachments','vault'=>'vault','reminders'=>'client_reminders_tab','map'=>'customer_map'];
 public const MERGES=['company_name','customer_id','street','city','state','zip_code','country_code','country_name','phone','vat_number','vat_number_with_label'];
 public function __construct(private$db,private string$p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
 private function forms():NativeCompanyEstimateRequestForms{require_once __DIR__.'/NativeCompanyEstimateRequestForms.php';return new NativeCompanyEstimateRequestForms($this->db,$this->p);}
 public function tables():array{return array_values(array_unique(array_merge(NativePortalPreferences::tables(),(new NativeCompanyCustomFields($this->db,$this->p))->tableNames(),$this->forms()->tables(),['customfields','countries'])));}
 private function authorize(int$i,int$a,bool$write=false):void{$acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($i,$a,'settings','view')||($write&&(!$acl->administrative($i,$a)||!$acl->can($i,$a,'settings','edit'))))throw new DomainException('native_clients_settings_denied');}
 private function capture(int$i,int$a):array{
  IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$this->authorize($i,$a);$store=new CompanySettingsStorage($this->db,$this->p);$values=[];
  foreach(self::DEFAULTS as$key=>$default)$values[$key]=$store->exists($i,$key)?$store->get($i,$key):$default;
  if($values['required_register_fields']==='')$values['required_register_fields']='[]';self::validate($values);
  $countries=IssuerStorage::query($this->db,'SELECT country_id,short_name FROM '.$this->p.'countries ORDER BY short_name,country_id')->result_array();
  $definitions=(new NativeCompanyCustomFields($this->db,$this->p))->definitionsForClientSettings($i,$a);$forms=$this->forms()->forSettings($i,$a);
  $this->authorize($i,$a);return['values'=>$values,'countries'=>$countries,'customer_field_definitions'=>$definitions,'estimate_request_forms'=>$forms,'themes'=>self::themes(),'tabs'=>self::TABS,'revision'=>hash('sha256',json_encode([$i,$values,$countries,$definitions,$forms],JSON_THROW_ON_ERROR)),'assets'=>null,'custom_fields'=>[]];
 }
 public function page(int$i,int$a):array{return IssuerTransaction::run($this->db,$this->p,$this->tables(),fn()=>$this->capture($i,$a));}
 public function save(int$i,int$a,string$revision,array$input):array{
  $this->authorize($i,$a,true);$values=self::input($input);if(!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('native_clients_settings_stale');
  return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($i,$a,$revision,$values){
   $this->authorize($i,$a,true);(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($i,$a);$before=$this->capture($i,$a);if(!hash_equals($before['revision'],$revision))throw new DomainException('native_clients_settings_stale');
   if(!in_array($values['customer_default_country'],['','0'],true)&&!in_array($values['customer_default_country'],array_map('strval',array_column($before['countries'],'country_id')),true))throw new DomainException('native_clients_settings_invalid');
   if($values['show_estimate_request_in_customers_area']!=='0'&&!in_array($values['show_estimate_request_in_customers_area'],array_map('strval',array_column($before['estimate_request_forms'],'id')),true))throw new DomainException('native_clients_settings_invalid');
   preg_match_all('/\{cf_([1-9][0-9]*)\}/',$values['customer_info_format'],$tokens);$allowed=array_map('strval',array_column($before['customer_field_definitions'],'id'));foreach($tokens[1]as$id)if(!in_array($id,$allowed,true))throw new DomainException('native_clients_settings_invalid');
   $store=new CompanySettingsStorage($this->db,$this->p);if($store->isPrimary($i)){$guard=new CompanySettingsCommitGuard($this->db,$this->p,$i,$a,'edit');foreach($values as$key=>$value)$guard->writeOption('update',$key,$value);$guard->audit('company_settings_batch_updated');$guard->confirm();}else$store->updateBatchInOwnedTransaction($i,$a,$values);
   $this->authorize($i,$a,true);$after=$this->capture($i,$a);if($after['values']!==$values||$after['countries']!==$before['countries']||$after['customer_field_definitions']!==$before['customer_field_definitions']||$after['estimate_request_forms']!==$before['estimate_request_forms'])throw new RuntimeException('native_clients_settings_unconfirmed');return$after;
  });
 }
 public static function fields():array{return array_keys(self::DEFAULTS);}
 public static function themes():array{return ['perfex'];} // The only bundled, adapted core theme. No arbitrary PHP theme loading.
 public static function contactPermissions(callable$translate):array{
  $labels=[1=>'customer_permission_invoice',2=>'customer_permission_estimate',3=>'customer_permission_contract',4=>'customer_permission_proposal',5=>'customer_permission_support',6=>'customer_permission_projects'];$out=[];foreach($labels as$id=>$label)$out[]=['id'=>$id,'name'=>$translate($label)];return$out;
 }
 public static function registrationFields(array$values,callable$translate):array{
  $required=json_decode($values['required_register_fields'],true,8,JSON_THROW_ON_ERROR);$labels=['contact_firstname'=>'clients_firstname','contact_lastname'=>'clients_lastname','contact_email'=>'clients_email','contact_contact_phonenumber'=>'clients_phone','contact_website'=>'client_website','contact_title'=>'contact_position','company_company'=>'clients_company','company_vat'=>'clients_vat','company_phonenumber'=>'clients_phone','company_country'=>'clients_country','company_city'=>'clients_city','company_address'=>'clients_address','company_zip'=>'clients_zip','company_state'=>'clients_state'];$out=['contact'=>[],'company'=>[]];
  foreach($labels as$key=>$label){$disabled=in_array($key,['contact_firstname','contact_lastname','contact_email','company_company'],true);$must=$key==='company_company'?$values['company_is_required']==='1':($disabled||in_array($key,$required,true));$out[str_starts_with($key,'contact_')?'contact':'company'][$key]=['label'=>$translate($label),'disabled'=>$disabled,'is_required'=>$must];}return$out;
 }
 /** Historical native map is parsed as a finite scalar grammar; PHP object deserialization is never invoked. */
 public static function visibleTabs(string$value):array{
  if($value==='all')return array_fill_keys(array_keys(self::TABS),true);
  if(strlen($value)>3000||!preg_match('/^a:[0-9]{1,2}:\{(?:s:[0-9]{1,2}:"[a-z_]+";b:[01];)*\}$/D',$value))throw new DomainException('native_clients_settings_invalid');
  preg_match('/^a:([0-9]{1,2}):/',$value,$head);preg_match_all('/s:([0-9]{1,2}):"([a-z_]+)";b:([01]);/',$value,$entries,PREG_SET_ORDER);if((int)$head[1]!==count($entries))throw new DomainException('native_clients_settings_invalid');$map=[];
  foreach($entries as$entry){if((int)$entry[1]!==strlen($entry[2])||!array_key_exists($entry[2],self::TABS)||array_key_exists($entry[2],$map))throw new DomainException('native_clients_settings_invalid');$map[$entry[2]]=$entry[3]==='1';}
  return array_replace(array_fill_keys(array_keys(self::TABS),true),$map,['profile'=>true,'contacts'=>true]);
 }
 /** Browser form arrays are not accepted as persisted values; conversion is explicit and finite. */
 public static function input(array$input):array{
  if(isset($input['customer_settings'])){if($input['customer_settings']!=='true')throw new DomainException('native_clients_settings_invalid');unset($input['customer_settings']);}
  if(array_diff(array_keys($input),self::fields()))throw new DomainException('native_clients_settings_invalid');
  foreach(['visible_customer_profile_tabs','required_register_fields','default_contact_permissions']as$key){$list=$input[$key]??[];if(!is_array($list)||!array_is_list($list)||count($list)>30||count(array_filter($list,'is_string'))!==count($list)||count(array_unique($list))!==count($list))throw new DomainException('native_clients_settings_invalid');
   if($key==='visible_customer_profile_tabs'){
    if(array_diff($list,array_diff(array_keys(self::TABS),['profile','contacts'])))throw new DomainException('native_clients_settings_invalid');
    $input[$key]=$list===[]?'all':serialize(array_combine(array_keys(self::TABS),array_map(fn($tab)=>in_array($tab,$list,true),array_keys(self::TABS))));
   }elseif($key==='required_register_fields'){
    if(array_diff($list,NativePortalPreferences::REQUIRED))throw new DomainException('native_clients_settings_invalid');$input[$key]=json_encode($list,JSON_THROW_ON_ERROR);
   }else{$input[$key]=serialize($list);NativeCompanyContactPreferences::permissions($input[$key]);}
  }
  if(array_diff(self::fields(),array_keys($input)))throw new DomainException('native_clients_settings_invalid');
  $input['customer_info_format']=self::format($input['customer_info_format']);self::validate($input);return array_replace(self::DEFAULTS,$input);
 }
 public static function validate(array$values):void{
  if(array_diff(array_keys($values),self::fields())||array_diff(self::fields(),array_keys($values)))throw new DomainException('native_clients_settings_invalid');
  foreach($values as$k=>$v){if(!is_string($v)||strlen($v)>20000||preg_match('//u',$v)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$v))throw new DomainException('native_clients_settings_invalid');
   if(in_array($k,['clients_default_theme','customer_default_country','visible_customer_profile_tabs','required_register_fields','show_estimate_request_in_customers_area','default_contact_permissions','customer_info_format'],true))continue;
   if(!in_array($v,['0','1'],true))throw new DomainException('native_clients_settings_invalid');
  }
  if(!in_array($values['clients_default_theme'],self::themes(),true)||!preg_match('/^(?:|0|[1-9][0-9]{0,8})$/D',$values['customer_default_country'])||!preg_match('/^(?:0|[1-9][0-9]{0,15})$/D',$values['show_estimate_request_in_customers_area']))throw new DomainException('native_clients_settings_invalid');
  self::visibleTabs($values['visible_customer_profile_tabs']);NativePortalPreferences::prepare(['required_register_fields'=>$values['required_register_fields']]);NativeCompanyContactPreferences::permissions($values['default_contact_permissions']);
  $format=$values['customer_info_format'];if(str_contains($format,'<?'))throw new DomainException('native_clients_settings_invalid');
  preg_match_all('/\{([^{}]*)\}/',$format,$matches);foreach($matches[1]as$token)if(!in_array($token,self::MERGES,true)&&!preg_match('/^cf_[1-9][0-9]{0,15}$/D',$token))throw new DomainException('native_clients_settings_invalid');
 }
 /** Native rich text, with no remote resources, code or arbitrary merge instructions. */
 public static function format(string$value):string{
  if(strlen($value)>20000||str_contains($value,'<?')||preg_match('//u',$value)!==1)throw new DomainException('native_clients_settings_invalid');
  if(!class_exists('HTMLPurifier'))require_once __DIR__.'/../vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php';
  $c=HTMLPurifier_Config::createDefault();$c->set('Core.Encoding','UTF-8');$c->set('Cache.DefinitionImpl',null);
  $c->set('HTML.Allowed','p[style],div[style],span[style],strong,b,em,i,u,s,sub,sup,br,hr,ul,ol,li');
  $c->set('CSS.AllowedProperties',['color','font-family','font-size','font-weight','font-style','text-align','text-decoration','padding','margin']);
  return(new HTMLPurifier($c))->purify($value);
 }

}
