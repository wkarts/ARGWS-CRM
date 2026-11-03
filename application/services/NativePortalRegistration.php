<?php
declare(strict_types=1);
require_once __DIR__.'/NativePortalRegistrationSchema.php';
require_once __DIR__.'/NativePortalPreferences.php';
require_once __DIR__.'/NativeCompanyCustomFields.php';
require_once __DIR__.'/NativeCompanyContactPreferences.php';
require_once __DIR__.'/CompanyEmailTemplates.php';
/** Public writer: no staff impersonation, global model, hooks, or cross-company email lookup. */
final class NativePortalRegistration
{
 private ?array$creating=null;
 public function __construct(private$db,private string$p,private string$secret){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p)||strlen($secret)<32)throw new InvalidArgumentException('Invalid registration context');}
 private function __clone(){}
 public function __serialize():array{throw new LogicException('native_creation_writer_runtime_only');}
 public function tables():array{return['issuers_lock','issuers_registry','issuers_installation_identity','issuers_clients','issuers_audit','clients','contacts','contact_permissions','countries','emailtemplates','customfields','company_custom_fields','company_custom_field_values','company_native_portal_registrations'];}
 private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
 private function policy(int$issuer):array{
  if(!$this->rows('SELECT id FROM '.$this->p.'issuers_registry WHERE id=? AND active=1',[$issuer]))throw new DomainException('native_portal_registration_denied');
  $v=(new NativePortalPreferences($this->db,$this->p))->publicValues($issuer);if($v['allow_registration']!=='1')throw new DomainException('native_portal_registration_denied');return$v;
 }
 public function assertRegisteredInOwned($db,string$p,int$issuer,string$kind,int$id):void{
  if($db!==$this->db||$p!==$this->p||!$this->creating||$this->creating['issuer']!==$issuer||!in_array($kind,['client','contact'],true)||$this->creating[$kind]!==$id)throw new DomainException('native_custom_field_creation_denied');
  IssuerTransaction::assertOwned($db,$p,$this->tables());$this->policy($issuer);
  if(!$this->rows('SELECT c.id FROM '.$p.'contacts c JOIN '.$p.'issuers_clients o ON o.client_id=c.userid WHERE c.id=? AND c.userid=? AND o.owner_issuer_id=?',[$this->creating['contact'],$this->creating['client'],$issuer]))throw new DomainException('native_custom_field_creation_denied');
 }
 /** Input is normalized by the service, never by global registration callbacks. */
 private function input(array$input,array$policy):array{
  $limits=['firstname'=>191,'lastname'=>191,'email'=>100,'company'=>191,'vat'=>50,'phonenumber'=>30,'contact_phonenumber'=>100,'website'=>150,'title'=>100,'address'=>191,'city'=>100,'state'=>50,'zip'=>15,'country'=>9,'password'=>4096,'passwordr'=>4096];$out=[];
  if(array_diff(array_keys($input),array_merge(array_keys($limits),['custom_fields','accept_terms_and_conditions'])))throw new DomainException('native_portal_registration_invalid');
  foreach($limits as$k=>$limit){$v=$input[$k]??'';if(!is_string($v)||strlen($v)>$limit||preg_match('//u',$v)!==1||preg_match('/[\x00-\x1f\x7f]/',$v))throw new DomainException('native_portal_registration_invalid');$out[$k]=in_array($k,['password','passwordr'],true)?$v:trim($v);}
  if($out['firstname']===''||$out['lastname']===''||!filter_var($out['email'],FILTER_VALIDATE_EMAIL)||strlen($out['password'])<8||$out['password']!==$out['passwordr']||($policy['company_is_required']==='1'&&$out['company']===''))throw new DomainException('native_portal_registration_invalid');
  $out['email']=strtolower($out['email']);$out['country']=$out['country']===''?'0':$out['country'];if(!preg_match('/^(0|[1-9][0-9]{0,8})$/D',$out['country']))throw new DomainException('native_portal_registration_invalid');
  if($out['website']!==''&&!filter_var(preg_match('~^https?://~i',$out['website'])?$out['website']:'https://'.$out['website'],FILTER_VALIDATE_URL))throw new DomainException('native_portal_registration_invalid');
  foreach(json_decode($policy['required_register_fields'],true,16,JSON_THROW_ON_ERROR)as$field){$key=preg_replace('/^(contact|company)_/','',$field);if(($out[$key]??'')===''||($key==='country'&&$out[$key]==='0'))throw new DomainException('native_portal_registration_invalid');}
  $custom=$input['custom_fields']??[];if(!is_array($custom)||array_diff(array_keys($custom),['customers','contacts']))throw new DomainException('native_portal_registration_invalid');foreach(['customers','contacts']as$to)if(isset($custom[$to])&&!is_array($custom[$to]))throw new DomainException('native_portal_registration_invalid');
  $out['custom_fields']=$custom;$out['accept_terms_and_conditions']=$input['accept_terms_and_conditions']??'';if(!in_array($out['accept_terms_and_conditions'],['','on','1'],true))throw new DomainException('native_portal_registration_invalid');return$out;
 }
 private function insert(string$table,string$id,array$fields):int{
  $before=(int)($this->rows('SELECT MAX('.$id.') max_id FROM '.$this->p.$table)[0]['max_id']??0);
  IssuerStorage::query($this->db,'INSERT INTO '.$this->p.$table.' (`'.implode('`,`',array_keys($fields)).'`) VALUES('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));$new=(int)$this->db->insert_id();
  if($new<1||$new<=$before)throw new RuntimeException('native_portal_registration_unconfirmed');$row=$this->rows('SELECT * FROM '.$this->p.$table.' WHERE '.$id.'=?',[$new])[0]??[];self::confirmed($row,$fields);return$new;
 }
 private static function confirmed(array$row,array$fields):void{foreach($fields as$k=>$v)if(!array_key_exists($k,$row)||($v===null?$row[$k]!==null:(string)$row[$k] !== (string)$v))throw new RuntimeException('native_portal_registration_unconfirmed');}
 private function registrationLanguage(int$issuer,?string$selected,array$policy):string{
  $store=new CompanySettingsStorage($this->db,$this->p);$configured=$store->get($issuer,'active_language');$language=$policy['disable_language']==='0'&&$selected!==null?$selected:($configured!==''?$configured:'portuguese_br');
  if(!preg_match('/^[a-z][a-z0-9_]{0,39}$/D',$language)||!is_file(APPPATH.'language/'.$language.'/'.$language.'_lang.php'))throw new DomainException('native_portal_registration_invalid');return$language;
 }
 private function registrationPhone(int$issuer,array$input):string{
  $number=$input['contact_phonenumber'];if($number===''||$input['country']==='0')return$number;$store=new CompanySettingsStorage($this->db,$this->p);$calling=$store->exists($issuer,NativeCompanyContactPreferences::CALLING)?$store->get($issuer,NativeCompanyContactPreferences::CALLING):NativeCompanyContactPreferences::defaultValue(NativeCompanyContactPreferences::CALLING);NativeCompanyContactPreferences::prepare([NativeCompanyContactPreferences::CALLING=>$calling]);if($calling!=='1')return$number;
  $country=$this->rows('SELECT calling_code FROM '.$this->p.'countries WHERE country_id=?',[(int)$input['country']])[0]??null;if(!$country)return$number;$code=ltrim((string)$country['calling_code'],'+');if(!preg_match('/^[0-9]{1,10}$/D',$code))throw new DomainException('native_portal_registration_invalid');
  if(str_starts_with($number,$code))$number='+'.$number;elseif(!str_starts_with($number,'+'.$code))$number='+'.$code.$number;if(strlen($number)>100)throw new DomainException('native_portal_registration_invalid');return$number;
 }
 /** Participants are internal services, with their tables enlisted before taking the issuer lock. */
 public function register(int$issuer,string$key,array$input,callable$hashPassword,callable$verification,array$participantTables=[],?callable$afterInsert=null,?string$selectedLanguage=null):array{
  if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('native_portal_registration_invalid');$policy=$this->policy($issuer);$input=$this->input($input,$policy);$language=$this->registrationLanguage($issuer,$selectedLanguage,$policy);$digest=hash_hmac('sha256',json_encode([$issuer,$input,$language],JSON_THROW_ON_ERROR),$this->secret);$hash=$hashPassword($input['password']);if(!is_string($hash)||strlen($hash)<20||strlen($hash)>255||$hash===$input['password'])throw new DomainException('native_portal_registration_invalid');
  return IssuerTransaction::run($this->db,$this->p,array_values(array_unique(array_merge($this->tables(),$participantTables))),function()use($issuer,$key,$input,$digest,$hash,$verification,$afterInsert,$language,$selectedLanguage){
   NativePortalRegistrationSchema::assertValid($this->db,$this->p);$policy=$this->policy($issuer);$this->input($input,$policy);if($language!==$this->registrationLanguage($issuer,$selectedLanguage,$policy))throw new DomainException('native_portal_registration_invalid');
   $old=$this->rows('SELECT * FROM '.$this->p.'company_native_portal_registrations WHERE issuer_id=? AND request_key=? FOR UPDATE',[$issuer,$key])[0]??null;
   if($old){if(!hash_equals($old['input_hash'],$digest)||!$this->rows('SELECT c.id FROM '.$this->p.'contacts c JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid WHERE c.id=? AND c.userid=? AND o.owner_issuer_id=?',[$old['contact_id'],$old['client_id'],$issuer]))throw new DomainException('native_portal_registration_conflict');return['client_id'=>(int)$old['client_id'],'contact_id'=>(int)$old['contact_id'],'replayed'=>true];}
   $store=new CompanySettingsStorage($this->db,$this->p);if($store->get($issuer,'enable_gdpr')==='1'&&$store->get($issuer,'gdpr_enable_terms_and_conditions')==='1'&&!in_array($input['accept_terms_and_conditions'],['on','1'],true))throw new DomainException('native_portal_registration_invalid');
   $origin='(o.owner_issuer_id=? OR (o.client_id IS NULL AND i.primary_issuer_id=?))';
   if($this->rows('SELECT c.id FROM '.$this->p.'contacts c LEFT JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid LEFT JOIN '.$this->p.'issuers_installation_identity i ON i.id=1 WHERE '.$origin.' AND LOWER(c.email)=?',[$issuer,$issuer,$input['email']]))throw new DomainException('native_portal_registration_conflict');
   if($input['country']!=='0'&&!$this->rows('SELECT country_id FROM '.$this->p.'countries WHERE country_id=?',[$input['country']]))throw new DomainException('native_portal_registration_invalid');
   $custom=new NativeCompanyCustomFields($this->db,$this->p);foreach(['customers','contacts']as$to)$custom->validateRegistration($issuer,$to,$input['custom_fields'][$to]??[]);
   $now=gmdate('Y-m-d H:i:s');$pending=$policy['customers_register_require_confirmation']==='1';
   $clientFields=array_intersect_key($input,array_flip(['company','vat','phonenumber','country','city','address','zip','state','website']));$clientFields+=['datecreated'=>$now,'addedfrom'=>0,'active'=>$pending?0:1,'registration_confirmed'=>$pending?0:1,'billing_street'=>$input['address'],'billing_city'=>$input['city'],'billing_state'=>$input['state'],'billing_zip'=>$input['zip'],'billing_country'=>$input['country'],'default_language'=>$language];
   $client=$this->insert('clients','userid',$clientFields);
   $tax=IssuerPolicy::taxId($input['vat'],false);$owner=['client_id'=>$client,'owner_issuer_id'=>$issuer,'tax_id'=>$tax,'created_by'=>0,'created_at'=>$now];IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_clients(client_id,owner_issuer_id,tax_id,created_by,created_at) VALUES(?,?,?,?,?)',array_values($owner));self::confirmed($this->rows('SELECT * FROM '.$this->p.'issuers_clients WHERE client_id=?',[$client])[0]??[],$owner);
   $contactFields=['userid'=>$client,'firstname'=>$input['firstname'],'lastname'=>$input['lastname'],'email'=>$input['email'],'phonenumber'=>$this->registrationPhone($issuer,$input),'title'=>$input['title'],'password'=>$hash,'datecreated'=>$now,'active'=>$pending?0:1,'is_primary'=>1,'email_verified_at'=>null,'email_verification_key'=>null,'email_verification_sent_at'=>null];
   $permissions=NativeCompanyContactPreferences::permissions($store->exists($issuer,'default_contact_permissions')?$store->get($issuer,'default_contact_permissions'):NativeCompanyContactPreferences::DEFAULT_PERMISSIONS);sort($permissions,SORT_NUMERIC);foreach(['invoice_emails'=>1,'credit_note_emails'=>1,'estimate_emails'=>2,'contract_emails'=>3,'ticket_emails'=>5,'project_emails'=>6,'task_emails'=>6]as$flag=>$permission)$contactFields[$flag]=in_array($permission,$permissions,true)?1:0;
   $contact=$this->insert('contacts','id',$contactFields);
   foreach($permissions as$permission)IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'contact_permissions(userid,permission_id) VALUES(?,?)',[$contact,$permission]);if(array_map('intval',array_column($this->rows('SELECT permission_id FROM '.$this->p.'contact_permissions WHERE userid=? ORDER BY permission_id',[$contact]),'permission_id'))!==$permissions)throw new RuntimeException('native_portal_registration_unconfirmed');
   $receipt=['issuer_id'=>$issuer,'request_key'=>$key,'input_hash'=>$digest,'client_id'=>$client,'contact_id'=>$contact,'created_at'=>$now,'nonce'=>bin2hex(random_bytes(32))];IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_native_portal_registrations('.implode(',',array_keys($receipt)).') VALUES(?,?,?,?,?,?,?)',array_values($receipt));self::confirmed($this->rows('SELECT * FROM '.$this->p.'company_native_portal_registrations WHERE issuer_id=? AND request_key=?',[$issuer,$key])[0]??[],$receipt);
   $this->creating=['issuer'=>$issuer,'client'=>$client,'contact'=>$contact];try{foreach(['customers'=>$client,'contacts'=>$contact]as$to=>$id)$custom->saveRegisteredInOwnedTransaction($issuer,$to,$id,$input['custom_fields'][$to]??[],$this);$verification($issuer,$client,$contact,$key);if($afterInsert)$afterInsert($issuer,$key);}finally{$this->creating=null;}
   $metadata=json_encode(['actor_kind'=>'contact','contact_id'=>$contact,'client_id'=>$client,'request_key'=>$key,'pending_confirmation'=>$pending,'nonce'=>bin2hex(random_bytes(32))],JSON_THROW_ON_ERROR);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,issuer_id,subject_id,event,metadata_json,created_at) VALUES(0,?,?,?,?,?)',[$issuer,$contact,'native_portal_registered',$metadata,$now]);$audit=$this->rows('SELECT metadata_json FROM '.$this->p.'issuers_audit WHERE id=?',[(int)$this->db->insert_id()])[0]??[];if(($audit['metadata_json']??null)!==$metadata)throw new RuntimeException('issuer_portal_audit_unconfirmed');$this->policy($issuer);
   return['client_id'=>$client,'contact_id'=>$contact,'replayed'=>false];
  });
 }
}
