<?php
require __DIR__.'/ticket_files.php';
if(!defined('BASEPATH'))define('BASEPATH',__DIR__.'/../../system/');
if(!defined('APPPATH'))define('APPPATH',__DIR__.'/../../application/');
if(!function_exists('db_prefix')){function db_prefix(){return'tbl';}}
if(!class_exists('App_Controller',false)){class App_Controller{public $db;public $output;public $input;}}
final class CompanyRequestScope{public static int $issuer=2;public static function nativeFor($ci):bool{return true;}public static function context(){return new class{public function issuerId():int{return CompanyRequestScope::$issuer;}public function staffId():int{return 17;}};}}
if(!function_exists('show_404')){function show_404(){throw new DomainException('http_not_found');}}
require __DIR__.'/../../application/controllers/Download.php';
final class NativeTicketFileOutput{public array$headers=[];public string$body='';public string$mime='';public function set_header($v){$this->headers[]=$v;return$this;}public function set_content_type($v){$this->mime=$v;return$this;}public function set_output($v){$this->body=$v;return$this;}}
$db->query('UPDATE tblissuers_installation_identity SET master_staff_id=17');$db->query('UPDATE tblissuers_staff SET can_view=1 WHERE issuer_id=2 AND staff_id=17');
$controller=(new ReflectionClass(Download::class))->newInstanceWithoutConstructor();$controller->db=$wrapped;$controller->output=new NativeTicketFileOutput();$controller->input=new class{public string$verb='GET';public $ticket;public function method($upper=false){return$this->verb;}public function get($name,$xss=false){return$name==='ticket_id'?$this->ticket:null;}};$controller->input->ticket=(string)$id;
$controller->file('ticket',$key);kc($controller->output->body===$bytes&&in_array('Cache-Control: private, no-store',$controller->output->headers,true)&&in_array('X-Content-Type-Options: nosniff',$controller->output->headers,true),'actual native controller returns authorized ticket BLOB privately');
$controller->input->verb='HEAD';$controller->output=new NativeTicketFileOutput();$controller->file('ticket',$key);kc($controller->output->body==='','HEAD has no ticket bytes');
CompanyRequestScope::$issuer=3;$deny(fn()=>$controller->file('ticket',$key));CompanyRequestScope::$issuer=2;$controller->input->verb='POST';$deny(fn()=>$controller->file('ticket',$key));$controller->input->verb='GET';$controller->input->ticket=(string)$contactId;$deny(fn()=>$controller->file('ticket',$key));$controller->input->ticket=[$id];$deny(fn()=>$controller->file('ticket',$key));$controller->input->ticket=(string)$id;$deny(fn()=>$controller->file('ticket','../'.$key));
echo "Native ticket actual Download: 7 HTTP-contract checks passed; context seam explicit, no browser claim.\n";
