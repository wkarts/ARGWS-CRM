<?php
declare(strict_types=1);
require_once __DIR__.'/NativePortalIdentity.php';
require_once __DIR__.'/NativePortalRemember.php';
require_once __DIR__.'/NativePortalPresentation.php';
require_once __DIR__.'/CompanyPortalRecoveryMail.php';
require_once __DIR__.'/NativePortalVerificationMail.php';
/** Native Authentication controller adapter; no staff identity, legacy models or plugin hooks. */
final class NativePortalAuthentication
{
 private int$issuer;private string$secret;private NativePortalIdentity$identity;private CompanyPortal$portal;private NativePortalRemember$remember;
 public function __construct(private$ci){if(!PortalRequestScope::sealedFor($ci)||!in_array($ci->input->method(true),['GET','HEAD','POST'],true))throw new DomainException('native_portal_context_required');$this->issuer=PortalRequestScope::issuerId();$this->secret=$ci->config->item('encryption_key');$this->identity=new NativePortalIdentity($ci->db,db_prefix(),$this->secret);$this->portal=new CompanyPortal($ci->db,db_prefix(),$this->secret);$this->remember=new NativePortalRemember($ci->db,db_prefix(),$this->secret);}
 private function destination(array$binding):string{$rows=IssuerStorage::query($this->ci->db,'SELECT email_verified_at FROM '.db_prefix().'contacts WHERE id=? AND userid=?',[$binding['contact_id'],$binding['client_id']])->result_array();if(count($rows)!==1)throw new DomainException('native_portal_identity_invalid');return$rows[0]['email_verified_at']===null?'verification':'clients';}
 private function post():bool{return$this->ci->input->method(true)==='POST';}
 private function value(string$key):string{$v=$this->ci->input->post($key,false);if($v===null)return'';if(!is_string($v))throw new DomainException('native_portal_input_invalid');return$v;}
 private function render(string$view,string$title,?string$message=null):void{$this->ci->data(['title'=>_l($title),'bodyclass'=>$view==='login'?'customers_login':$view,'native_portal_message'=>$message]);$this->ci->view($view);$this->ci->layout();}
 private function redirect(string$path):void{redirect(NativePortalPresentation::url($path),'location',303);}
 private function cookie(?array$receipt):void{
  $path=NativePortalPresentation::cookiePath($this->ci);$options=NativePortalRemember::cookieOptions($receipt['cookie']??'',$path,$receipt['expires_at']??date('Y-m-d H:i:s',time()-3600));
  // CI 3.1.11's cookie helper ignores SameSite and may apply global domain/prefix.
  // Emit the explicit host-only native cookie instead of inheriting those options.
  $expires=$receipt===null?time()-3600:strtotime($receipt['expires_at']);$age=$receipt===null?0:$options['expire'];
  $this->ci->output->set_header('Set-Cookie: '.NativePortalRemember::COOKIE.'='.rawurlencode($options['value']).'; Path='.$path.'; Max-Age='.$age.'; Expires='.gmdate('D, d M Y H:i:s',$expires).' GMT; Secure; HttpOnly; SameSite=Lax',false);
 }
 private function cookieValue():string{$v=$this->ci->input->cookie(NativePortalRemember::COOKIE,false);return is_string($v)&&strlen($v)===113?$v:'';}
 private function hasher(){require_once APPPATH.'third_party/phpass.php';if(!defined('PHPASS_HASH_STRENGTH')||!defined('PHPASS_HASH_PORTABLE'))throw new RuntimeException('issuer_portal_hasher_unconfigured');return new PasswordHash(PHPASS_HASH_STRENGTH,PHPASS_HASH_PORTABLE);}
 private function decrypt(string$cipher):string{$this->ci->load->library('encryption');$plain=$this->ci->encryption->decrypt($cipher);if(!is_string($plain))throw new RuntimeException('issuer_portal_decryption_failed');return$plain;}
 public function login():void{
  if($current=$this->identity->current($this->ci->session,$this->issuer)){$this->redirect($this->destination($current['binding']));return;}
  if(!$this->post()){$cookie=$this->cookieValue();if($cookie!==''){$receipt=$this->remember->consume($this->issuer,$cookie);if($receipt!==null){$this->identity->establish($this->ci->session,$this->issuer,$receipt['binding']);$this->cookie($receipt);$this->redirect($this->destination($receipt['binding']));return;}$this->cookie(null);}$this->render('login','clients_login_heading_no_register');return;}
  PortalRequestScope::assertMutation($this->ci);$hasher=$this->hasher();$email=$this->value('email');$password=$this->value('password');
  $allowed=$this->portal->allowAttempt($this->issuer,(string)$this->ci->input->ip_address(),'login');$captcha=$allowed&&$this->captcha();$binding=$captcha?$this->portal->login($this->issuer,$email,$password,[$hasher,'CheckPassword']):null;
  if(!$binding){$this->render('login','clients_login_heading_no_register',_l('client_invalid_username_or_password'));return;}
  $remember=$this->value('remember');if(!in_array($remember,['','on','1'],true))throw new DomainException('native_portal_input_invalid');
  $receipt=$remember!==''?$this->remember->issue($this->issuer,$binding):null;$this->identity->establish($this->ci->session,$this->issuer,$binding);$this->cookie($receipt);$this->redirect($this->destination($binding));
 }
 public function forgot():void{
  if($this->post()){PortalRequestScope::assertMutation($this->ci);$email=$this->value('email');
   if($this->portal->allowAttempt($this->issuer,(string)$this->ci->input->ip_address(),'recovery'))(new CompanyPortalRecoveryMail($this->ci->db,db_prefix(),$this->secret))->requestRecovery($this->issuer,$email,$this->ci->config->item('base_url'),fn($v)=>$this->decrypt($v),'native');
   $this->render('forgot_password','customer_forgot_password',_l('check_email_for_resetting_password'));return;
  }$this->render('forgot_password','customer_forgot_password');
 }
 public function reset($staff,$contact,$key):void{
  if((string)$staff!=='0'||($key!=='native'&&(!is_string($key)||!preg_match('/^[a-f0-9]{32}$/D',$key))))throw new DomainException('native_portal_input_invalid');$contact=IssuerPolicy::id($contact);
  if($this->post()){PortalRequestScope::assertMutation($this->ci);$password=$this->value('password');$repeat=$this->value('passwordr');$token=$this->value('native_reset_token');$hasher=$this->hasher();
   $ok=$password===$repeat&&$this->portal->allowAttempt($this->issuer,(string)$this->ci->input->ip_address(),'reset')&&($key==='native'?$this->portal->reset($this->issuer,$token,$password,[$hasher,'HashPassword'],$contact):$this->portal->resetHistoricalPrimary($this->issuer,$contact,$key,$password,[$hasher,'HashPassword']));
   if($ok){NativePortalIdentity::clear($this->ci->session);$this->cookie(null);$this->redirect('authentication/login');return;}$this->ci->data(['native_reset_token'=>preg_match('/^[a-f0-9]{64}$/D',$token)?$token:'']);$this->render('reset_password','customer_reset_password_heading',_l('password_reset_key_expired'));return;
  }$this->render('reset_password','customer_reset_password_heading');
 }
 public function logout():void{PortalRequestScope::assertMutation($this->ci);$this->remember->revoke($this->issuer,$this->cookieValue());NativePortalIdentity::clear($this->ci->session);$this->cookie(null);$this->redirect('authentication/login');}
 public function register():void{
  require_once __DIR__.'/NativePortalRegistration.php';require_once __DIR__.'/NativeCompanyNotifications.php';
  if($this->identity->current($this->ci->session,$this->issuer)){$this->redirect('clients');return;}
  $preferences=new NativePortalPreferences($this->ci->db,db_prefix());$policy=$preferences->publicValues($this->issuer);if($policy['allow_registration']!=='1')throw new DomainException('native_portal_registration_denied');
  $honeypot=$policy['enable_honeypot_spam_validation']==='1';$fields=[];foreach(['firstname','lastname','email','company']as$key)$fields[$key]=$honeypot?$key.'mjxw':$key;
  $key=$this->post()?$this->value('native_registration_key'):bin2hex(random_bytes(32));if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('native_portal_input_invalid');$error=null;
  if($this->post()){
   PortalRequestScope::assertMutation($this->ci);$posted=$this->ci->input->post(null,false);if(!is_array($posted))throw new DomainException('native_portal_input_invalid');
   if($honeypot)foreach(array_keys($fields)as$original)if($this->value($original)!=='')throw new DomainException('native_portal_registration_invalid');
   $allowed=$this->portal->allowAttempt($this->issuer,(string)$this->ci->input->ip_address(),'registration');if(!$allowed||!$this->captcha())$error=_l('client_invalid_username_or_password');
   else{
    foreach($fields as$name=>$postedName){$posted[$name]=$this->value($postedName);if($postedName!==$name)unset($posted[$postedName]);}
    foreach(['issuer_id','_native_portal_csrf','native_registration_key','g-recaptcha-response']as$meta)unset($posted[$meta]);
    // A language selector is presentation-only and must name the actual rendered product locale.
    if(isset($posted['language'])){if(!is_string($posted['language'])||!in_array($posted['language'],NativePortalPresentation::languages(),true))throw new DomainException('native_portal_input_invalid');unset($posted['language']);}
    $verification=new NativePortalVerificationMail($this->ci->db,db_prefix(),$this->secret);$notifications=new NativeCompanyNotifications($this->ci->db,db_prefix());$writer=new NativePortalRegistration($this->ci->db,db_prefix(),$this->secret);$hasher=$this->hasher();
    try{$receipt=$writer->register($this->issuer,$key,$posted,[$hasher,'HashPassword'],fn($issuer,$client,$contact,$request)=>$verification->queueRegistrationInOwned($issuer,$client,$contact,$request,$this->ci->config->item('base_url'),fn($v)=>$this->decrypt($v)),array_merge($verification->tables(),NativeCompanyNotifications::tables('registration')),fn($issuer,$request)=>$notifications->recordRegistrationInOwnedTransaction($issuer,$request),is_string($this->ci->data['language']??null)?$this->ci->data['language']:null);
     // Only a newly committed registration may establish its exact active contact. Replays never log in.
     if(!$receipt['replayed']){$binding=$this->portal->login($this->issuer,$posted['email'],$posted['password'],[$hasher,'CheckPassword']);if($binding&&(int)$binding['contact_id']===$receipt['contact_id']&&(int)$binding['client_id']===$receipt['client_id']){$this->identity->establish($this->ci->session,$this->issuer,$binding);$this->redirect($this->destination($binding));return;}}
     $this->redirect('authentication/login');return;
    }catch(DomainException$failure){$error='Não foi possível concluir o cadastro. Confira os campos e tente novamente.';}
   }
  }
  $required=json_decode($policy['required_register_fields'],true,16,JSON_THROW_ON_ERROR);$requiredFields=['contact'=>[],'company'=>[]];foreach(['contact_firstname','contact_lastname','contact_email',...NativePortalPreferences::REQUIRED]as$name){$group=str_starts_with($name,'contact_')?'contact':'company';$requiredFields[$group][$name]=['is_required'=>in_array($name,['contact_firstname','contact_lastname','contact_email'],true)||in_array($name,$required,true)];}
  $requiredFields['company']['company_company']=['is_required'=>$policy['company_is_required']==='1'];$countries=IssuerStorage::query($this->ci->db,'SELECT country_id,short_name FROM '.db_prefix().'countries ORDER BY short_name,country_id')->result_array();
  $this->ci->data(['honeypot'=>$honeypot,'fields'=>$fields,'requiredFields'=>$requiredFields,'native_registration_key'=>$key,'native_portal_countries'=>$countries]);$this->render('register','clients_register_heading',$error);
 }
 public function verification():void{
  $identity=$this->identity->current($this->ci->session,$this->issuer);if(!$identity){$this->redirect('authentication/login');return;}if($this->destination($identity['binding'])==='clients'){$this->redirect('clients');return;}
  $this->ci->data(['native_verification_action'=>'resend']);$this->render('verification_required','email_verification_required');
 }
 public function verify($contact,$key):void{
  if($key!=='native'&&(!is_string($key)||!preg_match('/^[a-f0-9]{32}$/D',$key)))throw new DomainException('native_portal_input_invalid');$contact=IssuerPolicy::id($contact);$this->ci->data(['native_verification_action'=>'verify','native_verification_contact'=>$contact,'native_verification_route_key'=>$key,'native_verification_legacy_token'=>$key==='native'?'':$key]);
  if($this->post()){PortalRequestScope::assertMutation($this->ci);$token=$this->value('native_verification_token');$allowed=$this->portal->allowAttempt($this->issuer,(string)$this->ci->input->ip_address(),'verification');
   $service=new NativePortalVerificationMail($this->ci->db,db_prefix(),$this->secret);$ok=$allowed&&($key==='native'?$service->verify($this->issuer,$contact,$token):(hash_equals($key,$token)&&$service->verifyLegacyPrimary($this->issuer,$contact,$token)));
   if($ok){$this->redirect('authentication/login');return;}$this->render('verification_required','email_verification_required',_l('invalid_verification_key'));return;
  }$this->render('verification_required','email_verification_required');
 }
 public function resendVerification():void{
  PortalRequestScope::assertMutation($this->ci);$identity=$this->identity->current($this->ci->session,$this->issuer);if(!$identity)throw new DomainException('native_portal_identity_invalid');
  $queued=null;if($this->portal->allowAttempt($this->issuer,(string)$this->ci->input->ip_address(),'verification'))$queued=(new NativePortalVerificationMail($this->ci->db,db_prefix(),$this->secret))->resend($this->issuer,$identity['binding'],$this->ci->config->item('base_url'),fn($v)=>$this->decrypt($v));
  $this->ci->data(['native_verification_action'=>'resend']);$this->render('verification_required','email_verification_required',$queued!==null?'Solicitação de verificação registrada. O envio será processado pela fila.':'Solicitação processada conforme a configuração e o estado atual da verificação.');
 }
 public function validateCaptcha():bool{PortalRequestScope::assertMutation($this->ci);return$this->portal->allowAttempt($this->issuer,(string)$this->ci->input->ip_address(),'captcha')&&$this->captcha();}
 private function captcha():bool{
  $prefs=new NativePortalPreferences($this->ci->db,db_prefix());$options=$prefs->publicValues($this->issuer);if($options['use_recaptcha_customers_area']!=='1'||$prefs->captchaIgnored($this->issuer,(string)$this->ci->input->ip_address()))return true;
  $token=$this->value('g-recaptcha-response');if($token===''||strlen($token)>8192)return false;$revision=$prefs->revision($this->issuer);$secret=$prefs->captchaSecret($this->issuer,fn($v)=>$this->decrypt($v));
  if(!function_exists('curl_init'))throw new RuntimeException('issuer_portal_captcha_transport_required');$curl=curl_init('https://www.google.com/recaptcha/api/siteverify');$response='';
  curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['secret'=>$secret,'response'=>$token]),CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>5,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_WRITEFUNCTION=>static function($handle,$chunk)use(&$response){if(strlen($response)+strlen($chunk)>65536)return 0;$response.=$chunk;return strlen($chunk);}]);
  if(defined('CURLOPT_PROTOCOLS_STR'))curl_setopt($curl,CURLOPT_PROTOCOLS_STR,'HTTPS');else curl_setopt($curl,CURLOPT_PROTOCOLS,CURLPROTO_HTTPS);
  try{$ok=curl_exec($curl)!==false&&curl_getinfo($curl,CURLINFO_RESPONSE_CODE)===200;}finally{unset($curl);}
  if(!$ok||!hash_equals($revision,$prefs->revision($this->issuer)))return false;$body=json_decode($response,true);$host=parse_url($this->ci->config->item('base_url'),PHP_URL_HOST);return is_array($body)&&($body['success']??null)===true&&is_string($body['hostname']??null)&&strtolower($body['hostname'])===strtolower((string)$host);
 }
}
