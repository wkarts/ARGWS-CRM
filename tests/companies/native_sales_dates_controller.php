<?php
// Exercise the original controller methods with synthetic CI/session/output seams.
require __DIR__.'/native_sales_dates.php';
#[AllowDynamicProperties] class AdminController {}
final class SalesDateFixtureContext { public function __construct(public int $issuer,public int $actor){} public function issuerId(){return $this->issuer;}public function staffId(){return $this->actor;} }
final class CompanyRequestScope {
    public static bool $native=true;public static $ctx;
    public static function nativeFor($ci){return self::$native;}
    public static function context(){return self::$ctx;}
    public static function assertNativeMutation($ci){if($ci->input->verb!=='POST'||($ci->input->values['_native_company_csrf']??'')!=='valid'||($ci->input->values['_company_context']??'')!=='current')throw new DomainException('native_company_csrf_invalid');}
}
function show_error($message,$status=500){throw new LogicException('HTTP '.$status);}
function get_option($key){return $key==='proposal_due_after'?'3':'2';}
function to_sql_date($value){return $value;}
function _d($value){return $value;}
require APPPATH.'controllers/admin/Estimates.php';require APPPATH.'controllers/admin/Proposals.php';
function dateFixture($class,$db,$values,$issuer,$actor){
    $controller=(new ReflectionClass($class))->newInstanceWithoutConstructor();$controller->db=$db;
    $controller->security=new class{function get_csrf_token_name(){return 'csrf_token';}};
    $controller->input=new class($values){public string $verb='POST';public function __construct(public array $values){}function method($upper=false){return $upper?$this->verb:strtolower($this->verb);}function post($key=null,$raw=true){return $key===null?$this->values:($this->values[$key]??null);}};
    $controller->output=new class{public array $headers=[];public string $body='';public string $type='';function set_header($s){$this->headers[]=$s;return $this;}function set_content_type($s){$this->type=$s;return $this;}function set_output($s){$this->body=$s;return $this;}};
    CompanyRequestScope::$ctx=new SalesDateFixtureContext($issuer,$actor);return $controller;
}
$start=$GLOBALS['essentialChecks'];$payload=['date'=>'28/02/2024','_native_company_csrf'=>'valid','_company_context'=>'current','csrf_token'=>'synthetic'];
$estimate=dateFixture('Estimates',$db,$payload,$a,7);$estimate->get_due_date();
essentialCheck($estimate->output->body==='01/03/2024'&&$estimate->output->type==='text/plain'&&in_array('Cache-Control: no-store',$estimate->output->headers,true),'original estimate action emits selected company value without caching');
$proposal=dateFixture('Proposals',$db,$payload,$a,7);$proposal->get_due_date();
essentialCheck($proposal->output->body==='29/02/2024','original proposal action uses separate proposal setting');
$foreign=dateFixture('Estimates',$db,array_replace($payload,['date'=>'02-28-2024']),$b,7);$foreign->get_due_date();essentialCheck($foreign->output->body==='','company B receives own explicit zero');
$invalid=dateFixture('Estimates',$db,array_replace($payload,['date'=>'30/02/2024']),$a,7);try{$invalid->get_due_date();throw new RuntimeException('invalid date accepted');}catch(LogicException $e){essentialCheck($e->getMessage()==='HTTP 422','invalid date rejected');}
$denied=dateFixture('Estimates',$db,$payload,$a,12);try{$denied->get_due_date();throw new RuntimeException('unauthorized accepted');}catch(LogicException $e){essentialCheck($e->getMessage()==='HTTP 403','resource ACL enforced');}
$csrf=dateFixture('Proposals',$db,array_replace($payload,['_native_company_csrf'=>'stale']),$a,7);try{$csrf->get_due_date();throw new RuntimeException('stale CSRF accepted');}catch(LogicException $e){essentialCheck($e->getMessage()==='HTTP 403','stale context CSRF rejected');}
$extra=dateFixture('Proposals',$db,$payload+['unknown'=>'no'],$a,7);try{$extra->get_due_date();throw new RuntimeException('extra field accepted');}catch(LogicException $e){essentialCheck($e->getMessage()==='HTTP 422','extra data rejected');}
$get=dateFixture('Estimates',$db,$payload,$a,7);$get->input->verb='GET';try{$get->get_due_date();throw new RuntimeException('GET accepted');}catch(LogicException $e){essentialCheck($e->getMessage()==='HTTP 403','GET rejected by native mutation guard');}
CompanyRequestScope::$native=false;$legacy=dateFixture('Proposals',$db,['date'=>'2024-02-28'],$principal,7);ob_start();try{$legacy->get_due_date();$legacyOutput=ob_get_clean();}catch(Throwable $e){ob_end_clean();throw$e;}essentialCheck($legacyOutput==='2024-03-02','principal keeps original due-date path');
echo 'Native sales due-date controller: '.($GLOBALS['essentialChecks']-$start)." added checks; middleware and browser remain separate.\n";
