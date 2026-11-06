<?php
require __DIR__.'/invoice_repricing.php';
require_once APPPATH.'services/NativeCompanyInvoiceRepricingHttp.php';
class CompanyRequestScope
{
 public static int $issuer;public static int $actor=7;public static bool $sealed=true;
 static function sealedFor($ci){return self::$sealed;}
 static function context(){return new class{function issuerId(){return CompanyRequestScope::$issuer;}function staffId(){return CompanyRequestScope::$actor;}};}
 static function adminUrl($ci,$path){return 'https://synthetic.invalid/admin/'.$path;}
}
function show_error($message,$status=500){$GLOBALS['CI']->output->status=$status;}
$ci=$GLOBALS['CI'];$ci->input=new class{function method($upper=false){return 'GET';}function get($key,$filter=false){return $key==='catalog_search'?'X':null;}};
$ci->output=new class{public int$status=200;public string$body='';function set_header($v){return$this;}function set_status_header($v){$this->status=$v;return$this;}function set_content_type($v){return$this;}function set_output($v){$this->body=$v;return$this;}};
$ci->session=new class{public array$values=[];function userdata($key){return$this->values[$key]??null;}function set_userdata($key,$value){$this->values[$key]=$value;}};
$ci->load=new class{function view($name,$data){}};
$http=new NativeCompanyInvoiceRepricingHttp($ci);$httpStart=$GLOBALS['essentialChecks'];
set_error_handler(static function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
try{
 foreach([[$a,$x],[$b,$foreign]]as[$owner,$expected]){
  CompanyRequestScope::$issuer=$owner;$ci->output->status=200;$ci->output->body='';$http->index();
  essentialCheck($ci->output->status===200,'real catalogue search controller returns200 without undefined array keys');
  $actual=json_decode($ci->output->body,true,32,JSON_THROW_ON_ERROR);
  essentialCheck(count($actual)===1&&array_keys($actual[0])===['id','description','rate']&&$actual[0]['id']===$expected&&$actual[0]['description']==='X'&&is_string($actual[0]['rate'])&&(float)$actual[0]['rate']===10.1,'public item ID, real name and rate are scoped and minimized for issuer '.$owner);
 }
 CompanyRequestScope::$sealed=false;$ci->output->body='';$http->index();essentialCheck($ci->output->status===503&&$ci->output->body==='','unsealed request cannot disclose catalogue');
}finally{restore_error_handler();}
echo 'Repricing actual HTTP handler/catalogue: '.($GLOBALS['essentialChecks']-$httpStart).' additional checks; SQL real, request/session adapters synthetic.'.PHP_EOL;
