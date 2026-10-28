<?php
/** Actual controller negative boundaries: no session hydration, SQL, credentials or provider requests. */
declare(strict_types=1);
if(!isset($argv[1])){
    foreach(['scope'=>403,'method'=>405,'csrf'=>403] as $case=>$status){$lines=[];$exit=0;exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($case),$lines,$exit);if($exit!==0||implode("\n",$lines)!=='REJECT '.$status)throw new RuntimeException('Boundary child failed: '.$case);}
    echo "Efí company actual controller negative boundaries PASS; no remote or database operations.\n";exit(0);
}
define('BASEPATH',__DIR__.'/');define('APPPATH',dirname(__DIR__,2).'/application/');define('FCPATH',dirname(__DIR__,2).'/');
function show_error($message,$status=500){if(($GLOBALS['queries']??0)!==0||($GLOBALS['decrypts']??0)!==0)throw new RuntimeException('Boundary touched storage or secrets');echo 'REJECT '.(int)$status;exit(0);}
function db_prefix(){return 'tbl';}
class CompanyRequestScope {public static bool $sealed=true;static function sealedFor($ci){return self::$sealed;}static function nativeFor($ci){return true;}}
class CompanySettingsRuntime {static function active(){return true;}static function issuerId(){return 2;}static function staffId(){return 99;}}
class AdminController {public $db,$input,$session,$output,$load,$encryption;}
require FCPATH.'application/controllers/admin/Settings.php';
$queries=0;$decrypts=0;
$controller=new Settings();
$controller->db=new class($queries){private $queries;function __construct(&$queries){$this->queries=&$queries;}function query($sql,$args=[]){++$this->queries;throw new RuntimeException('SQL not allowed before boundary');}};
$controller->encryption=new class($decrypts){private $decrypts;function __construct(&$value){$this->decrypts=&$value;}function decrypt($value){++$this->decrypts;throw new RuntimeException('Secrets not allowed');}};
$controller->load=new class {function library($name){if($name!=='encryption')throw new RuntimeException('unexpected loader');}};
$controller->output=new class {function set_header($value){return $this;}};
$controller->session=new class {function userdata($key){return str_repeat('a',64);}};
$controller->input=new class {public string $method='POST';public array $values=['company_efi_csrf'=>'bad','action'=>'save'];function method($upper){return $this->method;}function post($key=null,$xss=false){return $key===null?$this->values:($this->values[$key]??null);}};
if($argv[1]==='scope')CompanyRequestScope::$sealed=false;
elseif($argv[1]==='method')$controller->input->method='DELETE';
elseif($argv[1]!=='csrf')throw new RuntimeException('Unknown synthetic case');
$controller->efi();throw new RuntimeException('Expected controller rejection');
