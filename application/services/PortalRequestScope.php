<?php
declare(strict_types=1);
/** Public route admission precedes autoload; identity resolution follows minimal DB/session loading. */
final class PortalRequestScope
{
 private const AUTH=['index','login','register','forgot_password','reset_password','logout','contact_email_exists','change_language','recaptcha'];
 private static bool $active=false;private static bool $candidate=false;private static bool $resolved=false;private static bool $native=false;
 private static ?object $owner=null;private static ?int $issuer=null;private static ?array $identity=null;private static string$class='';private static string$method='';
 public static function prepare($ci):bool{
  self::$active=self::$candidate=self::$resolved=self::$native=false;self::$issuer=null;self::$identity=null;self::$owner=$ci;
  if($ci->input->is_cli_request()||defined('API')||trim((string)$ci->router->fetch_directory(),'/')!==''||$ci->router->fetch_module())return false;
  self::$class=strtolower((string)$ci->router->fetch_class());self::$method=strtolower((string)$ci->router->fetch_method());
  if(self::$class==='company_portal'&&self::$method==='index'){self::$active=true;return true;}
  self::$candidate=(self::$class==='authentication'&&in_array(self::$method,self::AUTH,true))||(self::$class==='verification'&&in_array(self::$method,['index','verify','resend'],true))||(self::$class==='consent'&&self::$method==='contact')||(in_array(self::$class,['terms_and_conditions','privacy_policy'],true)&&self::$method==='index');self::$active=self::$candidate;return self::$active;
 }
 public static function active():bool{return self::$active;}
 public static function candidate():bool{return self::$candidate;}
 /** Candidates always resolve an explicit persisted issuer, including the proven installation principal. */
 public static function resolve($ci):bool{
  if(!self::$candidate)return false;if(self::$owner!==$ci)throw new DomainException('native_portal_context_invalid');if(self::$resolved)return self::$native;
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
  self::$identity=(new NativePortalIdentity($ci->db,$p,$secret))->current($ci->session,$issuer);self::$issuer=$issuer;self::$native=true;self::$resolved=true;return true;
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
