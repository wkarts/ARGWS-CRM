<?php
require __DIR__.'/invoice_native_files.php';
if(!class_exists('App_Controller',false)){class App_Controller{public $db;public $output;public $input;}}
final class CompanyRequestScope{public static int $issuer;public static function nativeFor($ci):bool{return true;}public static function context(){return new class{public function issuerId():int{return CompanyRequestScope::$issuer;}public function staffId():int{return 7;}};}}
if(!function_exists('show_404')){function show_404(){throw new DomainException('http_not_found');}}
require APPPATH.'controllers/Download.php';
final class NativeFileTestOutput{public array $headers=[];public string $body='';public string $mime='';public function set_header($v){$this->headers[]=$v;return$this;}public function set_content_type($v){$this->mime=$v;return$this;}public function set_output($v){$this->body=$v;return$this;}}
$controller=(new ReflectionClass(Download::class))->newInstanceWithoutConstructor();$controller->db=$db;$controller->output=new NativeFileTestOutput();$controller->input=new class{public string $verb='GET';public function method($upper=false){return$this->verb;}public function get($key,$filter=false){return null;}};
CompanyRequestScope::$issuer=$a;$controller->file('sales_attachment',$n['attachment_key']);essentialCheck($controller->output->body===$pdf&&in_array('Cache-Control: private, no-store',$controller->output->headers,true)&&in_array('X-Content-Type-Options: nosniff',$controller->output->headers,true),'actual download controller returns verified bytes with private headers');
$controller->input->verb='HEAD';$controller->output=new NativeFileTestOutput();$controller->file('sales_attachment',$n['attachment_key']);essentialCheck($controller->output->body==='','HEAD never emits blob');
CompanyRequestScope::$issuer=$b;essentialThrows(fn()=>$controller->file('sales_attachment',$n['attachment_key']),'http_not_found','actual native download refuses foreign issuer');
CompanyRequestScope::$issuer=$a;$controller->input->verb='POST';essentialThrows(fn()=>$controller->file('sales_attachment',$n['attachment_key']),'http_not_found','download denies mutation verb');
echo "Actual native invoice download controller: 4 checks PASS\n";
