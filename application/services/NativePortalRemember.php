<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyPortal.php';
require_once __DIR__.'/NativePortalRememberSchema.php';
/** Opaque issuer-bound cookie. Only hashes persist; each successful use rotates its validator. */
final class NativePortalRemember
{
 public const COOKIE='argws_native_contact_remember';
 public const LIFETIME=5356800; // Native product duration: 62 days.
 private CompanyPortal $portal;
 public function __construct(private $db,private string$p,string$secret){$this->portal=new CompanyPortal($db,$p,$secret);}
 public static function tables():array{return['issuers_lock','issuers_registry','issuers_installation_identity','issuers_clients','clients','contacts','issuers_audit','company_native_portal_remember'];}
 private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
 private function tx(callable$fn){return IssuerTransaction::run($this->db,$this->p,self::tables(),$fn);}
 private static function parse(string$cookie):?array{return preg_match('/^([a-f0-9]{48})\.([a-f0-9]{64})$/D',$cookie,$m)?[$m[1],$m[2]]:null;}
 private function binding(array$row):array{return['issuer_id'=>(int)$row['issuer_id'],'contact_id'=>(int)$row['contact_id'],'client_id'=>(int)$row['client_id'],'stamp'=>$row['identity_stamp']];}
 private function audit(int$issuer,int$contact,string$event):void{
  $metadata=json_encode(['actor_kind'=>'contact','contact_id'=>$contact,'receipt_nonce'=>bin2hex(random_bytes(24))],JSON_THROW_ON_ERROR);$now=date('Y-m-d H:i:s');
  IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,metadata_json,created_at) VALUES(0,?,?,?,?,?)',[$event,$issuer,$contact,$metadata,$now]);
  $r=$this->rows('SELECT actor_id,event,issuer_id,subject_id,created_at FROM '.$this->p.'issuers_audit WHERE metadata_json=?',[$metadata]);if(count($r)!==1||(int)$r[0]['actor_id']!==0||$r[0]['event']!==$event||(int)$r[0]['issuer_id']!==$issuer||(int)$r[0]['subject_id']!==$contact||$r[0]['created_at']!==$now)throw new RuntimeException('issuer_portal_audit_unconfirmed');
 }
 public function issue(int$issuer,array$binding):array{
  return$this->tx(function()use($issuer,$binding){
   $identity=$this->portal->invoiceIdentity($binding,$issuer,true);if(!$identity)throw new DomainException('native_portal_identity_invalid');
   $selector=bin2hex(random_bytes(24));$validator=bin2hex(random_bytes(32));$nonce=bin2hex(random_bytes(32));$expires=date('Y-m-d H:i:s',time()+self::LIFETIME);
   $row=['selector'=>$selector,'validator_hash'=>hash('sha256',$validator),'identity_stamp'=>$binding['stamp'],'issuer_id'=>$issuer,'contact_id'=>$identity['contact_id'],'client_id'=>$identity['client_id'],'expires_at'=>$expires,'revision'=>1,'revoked'=>0,'nonce'=>$nonce];
   IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_native_portal_remember('.implode(',',array_keys($row)).') VALUES('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));
   $written=$this->rows('SELECT * FROM '.$this->p.'company_native_portal_remember WHERE selector=?',[$selector])[0]??[];foreach($row as$k=>$v)if(!array_key_exists($k,$written)||(string)$written[$k]!==(string)$v)throw new RuntimeException('issuer_portal_remember_unconfirmed');
   $this->audit($issuer,$identity['contact_id'],'native_portal_remember_created');return['cookie'=>$selector.'.'.$validator,'expires_at'=>$expires];
  });
 }
 public function consume(int$issuer,string$cookie):?array{
  $parts=self::parse($cookie);if(!$parts)return null;[$selector,$validator]=$parts;
  return$this->tx(function()use($issuer,$selector,$validator){
   $row=$this->rows('SELECT * FROM '.$this->p.'company_native_portal_remember WHERE selector=? AND issuer_id=? FOR UPDATE',[$selector,$issuer])[0]??null;
   if(!$row||(int)$row['revoked']!==0||$row['expires_at']<=date('Y-m-d H:i:s')||!hash_equals($row['validator_hash'],hash('sha256',$validator)))return null;
   $binding=$this->binding($row);if(!$this->portal->invoiceIdentity($binding,$issuer,true))return null;
   $next=bin2hex(random_bytes(32));$nonce=bin2hex(random_bytes(32));$revision=(int)$row['revision']+1;
   if($revision>2147483647)throw new DomainException('native_portal_identity_invalid');
   IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_native_portal_remember SET validator_hash=?,revision=?,nonce=? WHERE selector=? AND issuer_id=? AND validator_hash=? AND revision=? AND revoked=0',[hash('sha256',$next),$revision,$nonce,$selector,$issuer,$row['validator_hash'],$row['revision']]);
   $written=$this->rows('SELECT validator_hash,revision,nonce FROM '.$this->p.'company_native_portal_remember WHERE selector=?',[$selector])[0]??[];
   if(($written['nonce']??null)!==$nonce||($written['validator_hash']??null)!==hash('sha256',$next)||(int)($written['revision']??0)!==$revision)throw new RuntimeException('issuer_portal_remember_unconfirmed');
   $this->audit($issuer,(int)$row['contact_id'],'native_portal_remember_rotated');return['binding'=>$binding,'cookie'=>$selector.'.'.$next,'expires_at'=>$row['expires_at']];
  });
 }
 public function revoke(int$issuer,string$cookie):void{
  $parts=self::parse($cookie);if(!$parts)return;[$selector,$validator]=$parts;
  $this->tx(function()use($issuer,$selector,$validator){
   $row=$this->rows('SELECT * FROM '.$this->p.'company_native_portal_remember WHERE selector=? AND issuer_id=? FOR UPDATE',[$selector,$issuer])[0]??null;
   if(!$row||(int)$row['revoked']!==0||!hash_equals($row['validator_hash'],hash('sha256',$validator)))return;
   $nonce=bin2hex(random_bytes(32));IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_native_portal_remember SET revoked=1,nonce=? WHERE selector=? AND issuer_id=? AND validator_hash=?',[$nonce,$selector,$issuer,$row['validator_hash']]);
   $written=$this->rows('SELECT revoked,nonce FROM '.$this->p.'company_native_portal_remember WHERE selector=?',[$selector])[0]??[];if((int)($written['revoked']??0)!==1||($written['nonce']??null)!==$nonce)throw new RuntimeException('issuer_portal_remember_unconfirmed');
   $this->audit($issuer,(int)$row['contact_id'],'native_portal_remember_revoked');
  });
 }
 /** CI cookie path must come from the configured local base URL, never request input. */
 public static function cookieOptions(string$value,string$path,string$expiresAt):array{
  if(!preg_match('#^/(?:[A-Za-z0-9._~-]+/)*$#D',$path))throw new DomainException('native_portal_cookie_path_invalid');
  $expires=strtotime($expiresAt);if($expires===false)throw new DomainException('native_portal_cookie_expiry_invalid');
  return['name'=>self::COOKIE,'value'=>$value,'expire'=>max(0,$expires-time()),'path'=>$path,'secure'=>true,'httponly'=>true,'samesite'=>'Lax','prefix'=>'','domain'=>''];
 }
 public function __debugInfo():array{return[];}
 public function __serialize():array{throw new LogicException('native_portal_runtime_only');}
}
