<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
/** Public route admission precedes autoload; identity resolution follows minimal DB/session loading. */
final class PortalRequestScope
{
 private const AUTH=['index','login','register','forgot_password','reset_password','logout','contact_email_exists','change_language','recaptcha'];
 private static bool $active=false;private static bool $candidate=false;private static bool $resolved=false;private static bool $native=false;private static bool $unsupported=false;private static bool $invalidSession=false;
 private static ?object $owner=null;private static ?int $issuer=null;private static ?array $identity=null;private static string$class='';private static string$method='';
 public static function prepare($ci):bool{
  self::$active=self::$candidate=self::$resolved=self::$native=self::$unsupported=self::$invalidSession=false;self::$issuer=null;self::$identity=null;self::$owner=$ci;
  if($ci->input->is_cli_request()||defined('API')||trim((string)$ci->router->fetch_directory(),'/')!==''||$ci->router->fetch_module())return false;
  self::$class=strtolower((string)$ci->router->fetch_class());self::$method=strtolower((string)$ci->router->fetch_method());
  if(in_array(self::$class,['portal','company_portal'],true)&&self::$method==='index'){self::$active=true;return true;}
  // CI loads autoload.php before its configured session driver. Start only the
  // normal session driver for public customer/download controllers so an
  // existing native binding cannot fall through to global hydration merely
  // because a user removed the issuer selector from an old or direct URL.
  if(in_array(self::$class,['clients','contacts','download','invoice','estimate','proposal'],true)&&!isset($ci->session)&&isset($ci->load)&&method_exists($ci->load,'driver'))$ci->load->driver('session');
  $binding=isset($ci->session)&&method_exists($ci->session,'userdata')?$ci->session->userdata('native_company_portal_identity'):null;
  $bound=$binding!==null;
  // A preexisting legacy session may use legacy hydration only after its
  // active contact/customer and ownerless principal lineage are proven.
  // A named-company or malformed session without a native binding fails closed.
  $clientSession=isset($ci->session)&&method_exists($ci->session,'userdata')&&$ci->session->userdata('client_logged_in')!==null;
  $staffDownload=self::$class==='download'&&self::$method==='file'&&isset($ci->uri)&&method_exists($ci->uri,'segment')&&in_array(strtolower((string)$ci->uri->segment(3)),['client','project','ticket','sales_attachment'],true)&&isset($ci->session)&&(bool)$ci->session->userdata('staff_logged_in');
  if(in_array(self::$class,['clients','contacts','download','invoice','estimate','proposal'],true)&&$clientSession&&!$bound&&!$staffDownload)self::$invalidSession=!self::provenLegacyContact($ci);
  // Public customer files use the original Clients endpoints. Only requests
  // explicitly carrying portal company context enter this minimal bootstrap;
  // the legacy principal URLs without a selector retain their existing path.
  // Resolve still authenticates the session binding against that issuer.
  $customerSelected=self::$class==='clients'&&($bound||self::$invalidSession||$ci->input->get('issuer_id',false)!==null||$ci->input->post('issuer_id',false)!==null);
  $customerFiles=$customerSelected&&in_array(self::$method,['index','invoices','estimates','proposals','contracts','projects','project','tickets','open_ticket','ticket','files','upload_files','delete_file','profile','remove_profile_image','company','announcements','announcement','dismiss_announcement','gdpr'],true);
  $contactSelected=self::$class==='contacts'&&($bound||self::$invalidSession||$ci->input->get('issuer_id',false)!==null||$ci->input->post('issuer_id',false)!==null);
  $customerContacts=$contactSelected&&in_array(self::$method,['index','contact','delete','delete_profile_image','contact_email_profile_unique'],true);
  // Dashboard links retain canonical URLs. Until their own issuer adapters are
  // complete they must fail before the legacy global models are hydrated.
  $invoiceSelected=self::$class==='invoice'&&($bound||self::$invalidSession||$ci->input->get('issuer_id',false)!==null||$ci->input->post('issuer_id',false)!==null);
  $invoiceView=$invoiceSelected&&self::$method==='index';
  $estimateSelected=self::$class==='estimate'&&($bound||self::$invalidSession||$ci->input->get('issuer_id',false)!==null||$ci->input->post('issuer_id',false)!==null);
  $estimateView=$estimateSelected&&self::$method==='index';
  $proposalSelected=self::$class==='proposal'&&($bound||self::$invalidSession||$ci->input->get('issuer_id',false)!==null||$ci->input->post('issuer_id',false)!==null);
  $proposalView=$proposalSelected&&self::$method==='index';
  self::$unsupported=($customerSelected&&!$customerFiles)||($contactSelected&&!$customerContacts)||($invoiceSelected&&!$invoiceView)||($estimateSelected&&!$estimateView)||($proposalSelected&&!$proposalView);
  // The same Download controller is also used by staff, tickets and invoices.
  // An explicit portal marker separates its contact-bound download from those
  // existing entry points; the handler itself accepts only client files.
  $customerDownload=self::$class==='download'&&self::$method==='file'&&($ci->input->get('_native_portal',false)==='1'||(($bound||self::$invalidSession)&&!$staffDownload));
  self::$candidate=(self::$class==='authentication'&&in_array(self::$method,self::AUTH,true))||(self::$class==='verification'&&in_array(self::$method,['index','verify','resend'],true))||(self::$class==='consent'&&self::$method==='contact')||(in_array(self::$class,['terms_and_conditions','privacy_policy'],true)&&self::$method==='index')||$customerFiles||$customerContacts||$customerDownload||$invoiceView||$estimateView||$proposalView||self::$unsupported;self::$active=self::$candidate;return self::$active;
 }
 private static function provenLegacyContact($ci):bool{
  if(!isset($ci->db)&&isset($ci->load)&&method_exists($ci->load,'database'))$ci->load->database();if(!isset($ci->db))return false;
  $contact=$ci->session->userdata('contact_user_id');$client=$ci->session->userdata('client_user_id');
  foreach([$contact,$client]as$id)if((!is_int($id)&&!is_string($id))||!preg_match('/^[1-9][0-9]*$/D',(string)$id))return false;
  $p=db_prefix();$rows=IssuerStorage::query($ci->db,'SELECT c.id FROM '.$p.'contacts c JOIN '.$p.'clients p ON p.userid=c.userid WHERE c.id=? AND c.userid=? AND c.active=1 AND p.active=1',[$contact,$client])->result_array();if(count($rows)!==1)return false;
  $owners=$ci->db->table_exists($p.'issuers_clients');$identity=$ci->db->table_exists($p.'issuers_installation_identity');$registry=$ci->db->table_exists($p.'issuers_registry');
  if(!$owners&&!$identity&&!$registry)return true;if(!$owners||!$identity||!$registry)return false;
  $primary=IssuerStorage::query($ci->db,'SELECT r.id FROM '.$p.'issuers_installation_identity i JOIN '.$p.'issuers_registry r ON r.id=i.primary_issuer_id WHERE i.id=1 AND r.active=1')->result_array();
  $assigned=IssuerStorage::query($ci->db,'SELECT client_id FROM '.$p.'issuers_clients WHERE client_id=?',[$client])->result_array();return count($primary)===1&&$assigned===[];
 }
 public static function active():bool{return self::$active;}
 public static function candidate():bool{return self::$candidate;}
 /** Candidates always resolve an explicit persisted issuer, including the proven installation principal. */
 public static function resolve($ci):bool{
  if(!self::$candidate)return false;if(self::$owner!==$ci||self::$invalidSession)throw new DomainException('native_portal_context_invalid');if(self::$unsupported)throw new DomainException('native_portal_route_unavailable');if(self::$resolved)return self::$native;
  require_once __DIR__.'/NativePortalIdentity.php';
  $get=$ci->input->get('issuer_id',false);$post=$ci->input->post('issuer_id',false);
  foreach([$get,$post]as$v)if($v!==null&&(!is_string($v)&&!is_int($v)||!preg_match('/^[1-9][0-9]{0,9}$/D',(string)$v)))throw new DomainException('native_portal_context_invalid');
  if($get!==null&&$post!==null&&(string)$get!==(string)$post)throw new DomainException('native_portal_context_invalid');
  $binding=$ci->session->userdata(NativePortalIdentity::SESSION_KEY);$explicit=$post??$get;
  if($explicit===null&&$binding!==null&&(!is_array($binding)||!is_int($binding['issuer_id']??null)||$binding['issuer_id']<1))throw new DomainException('native_portal_context_invalid');
  $issuer=$explicit!==null?IssuerPolicy::id($explicit):($binding['issuer_id']??null);$p=db_prefix();
  if($issuer===null){
   $primary=IssuerStorage::query($ci->db,'SELECT r.id FROM '.$p.'issuers_installation_identity i JOIN '.$p.'issuers_registry r ON r.id=i.primary_issuer_id WHERE i.id=1 AND r.active=1')->result_array();
   if(count($primary)!==1)throw new DomainException('native_portal_context_required');$issuer=IssuerPolicy::id($primary[0]['id']);
  }
  $rows=IssuerStorage::query($ci->db,'SELECT id FROM '.$p.'issuers_registry WHERE id=? AND active=1',[$issuer])->result_array();if(count($rows)!==1)throw new DomainException('native_portal_context_invalid');
  $secret=$ci->config->item('encryption_key');if(!is_string($secret)||strlen($secret)<32)throw new RuntimeException('issuer_portal_secret_required');
  $identity=(new NativePortalIdentity($ci->db,$p,$secret))->current($ci->session,$issuer);
  // The login controller directs unverified contacts to Verification. Apply
  // the same persisted policy on every protected route as well, so a direct
  // URL cannot bypass that redirect or reuse an expired verification token.
  if($identity!==null&&in_array(self::$class,['clients','contacts','download','invoice','estimate','proposal'],true)){
   if(!$ci->db->field_exists('email_verified_at',$p.'contacts'))throw new RuntimeException('issuer_portal_verification_schema_required');
   $binding=$identity['binding'];$verified=IssuerStorage::query($ci->db,'SELECT email_verified_at FROM '.$p.'contacts WHERE id=? AND userid=?',[$binding['contact_id'],$binding['client_id']])->result_array();
   if(count($verified)!==1||$verified[0]['email_verified_at']===null)throw new DomainException('native_portal_verification_required');
   if((new NativePortalIdentity($ci->db,$p,$secret))->current($ci->session,$issuer)!==$identity)throw new DomainException('native_portal_context_invalid');
  }
  self::$identity=$identity;self::$issuer=$issuer;self::$native=true;self::$resolved=true;return true;
 }
 public static function nativeFor($ci):bool{return self::$owner===$ci&&self::$candidate&&self::$resolved&&self::$native&&strtolower((string)$ci->router->fetch_class())===self::$class&&strtolower((string)$ci->router->fetch_method())===self::$method;}
 public static function sealedFor($ci):bool{return self::nativeFor($ci);}
 public static function issuerId():int{if(!self::$resolved||!self::$native||self::$issuer===null)throw new DomainException('native_portal_context_required');return self::$issuer;}
 public static function identity():?array{self::issuerId();return self::$identity;}
 public static function csrfToken($ci):string{
  if(!self::sealedFor($ci))throw new DomainException('native_portal_context_invalid');
  $issuer=self::issuerId();$tokens=$ci->session->userdata('native_portal_csrf');if(!is_array($tokens))$tokens=[];
  if(!is_string($tokens[$issuer]??null)||!preg_match('/^[a-f0-9]{64}$/D',$tokens[$issuer])){$tokens=[$issuer=>bin2hex(random_bytes(32))];$ci->session->set_userdata(['native_portal_csrf'=>$tokens]);}return$tokens[$issuer];
 }
 public static function assertMutation($ci):void{
  if(!self::nativeFor($ci)||$ci->input->method(true)!=='POST')throw new DomainException('native_portal_csrf_denied');
  $posted=$ci->input->post('_native_portal_csrf',false);if(!is_string($posted)||!hash_equals(self::csrfToken($ci),$posted))throw new DomainException('native_portal_csrf_denied');
 }
}
