<?php
declare(strict_types=1);
define("BASEPATH",__DIR__."/../../system/");
define("APPPATH",__DIR__."/../../application/");
require_once __DIR__.'/../../application/services/NativePortalAuthentication.php';
final class CookieOriginFixture { public function __construct(public string $url){} public function item($key){return $key==='base_url'?$this->url:null;} }
final class CookieOutputFixture { public array $headers=[];public function set_header($value,$replace=true){$this->headers[]=$value;return$this;} }
function checkCookie(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
$ci=(object)['config'=>new CookieOriginFixture('http://127.0.0.1:45001/crm/'),'output'=>new CookieOutputFixture()];
checkCookie(NativePortalPresentation::cookiePath($ci,false)==='/crm/','expiration preserves the configured subdirectory');
try{NativePortalPresentation::cookiePath($ci);throw new RuntimeException('HTTP credential issuance accepted');}catch(DomainException $e){checkCookie($e->getMessage()==='native_portal_secure_origin_required','HTTPS guard preserved');}
$auth=(new ReflectionClass(NativePortalAuthentication::class))->newInstanceWithoutConstructor();$property=new ReflectionProperty($auth,'ci');$property->setAccessible(true);$property->setValue($auth,$ci);$cookie=new ReflectionMethod($auth,'cookie');$cookie->setAccessible(true);$cookie->invoke($auth,null);
checkCookie(count($ci->output->headers)===1,'ordinary session can expire remember-cookie without HTTP 500');$header=$ci->output->headers[0];foreach(['Set-Cookie: '.NativePortalRemember::COOKIE.'=;','Path=/crm/','Max-Age=0','Secure; HttpOnly; SameSite=Lax']as$part)checkCookie(str_contains($header,$part),'expiration retains cookie security: '.$part);
try{$cookie->invoke($auth,['cookie'=>'synthetic','expires_at'=>'2030-01-01 00:00:00']);throw new RuntimeException('HTTP remember-cookie issuance accepted');}catch(DomainException $e){checkCookie($e->getMessage()==='native_portal_secure_origin_required','persistent auth still requires HTTPS');}
$ci->config->url='https://example.invalid/crm/';checkCookie(NativePortalPresentation::cookiePath($ci)==='/crm/','HTTPS issuance path preserved');
foreach(['ftp://example.invalid/','https://user:password@example.invalid/','https://example.invalid/?token=x','https://example.invalid/#fragment','/relative']as$bad){$ci->config->url=$bad;try{NativePortalPresentation::cookiePath($ci,false);throw new RuntimeException('Unsafe origin accepted');}catch(DomainException $expected){}}
echo "Native remember-cookie expiration: HTTP session safe, HTTPS issuance mandatory, Secure/HttpOnly/SameSite retained; invalid origins rejected.\n";
