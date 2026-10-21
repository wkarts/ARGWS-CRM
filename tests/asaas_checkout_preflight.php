<?php
/** Actual CI_Controller -> App/Clients/Checkout constructors, MX router and autoload; providers are probes. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
define('FCPATH',dirname(__DIR__).'/');define('BASEPATH',FCPATH.'system/');define('APPPATH',FCPATH.'application/');define('APP_MODULES_PATH',FCPATH.'modules/');define('ADMIN_URI','admin');define('ENVIRONMENT','testing');
class CI_Router{public $config;public string $directory='',$class='',$method='';function fetch_directory(){return $this->directory;}function fetch_class(){return $this->class;}function fetch_method(){return $this->method;}}
$CFG=new class{function item($name){return $name==='modules_locations'?[APP_MODULES_PATH=>'../../modules/']:null;}};
function log_message(...$args){}function db_prefix(){return 'tbl';}function list_files($path){$GLOBALS['bootTrace'][]='gateway_catalog';return ['Synthetic_gateway.php'];}
class CheckoutPreflight404 extends RuntimeException{}class CheckoutProviderReached extends RuntimeException{}
function show_404(){throw new CheckoutPreflight404('404');}
function &get_instance(){return CI_Controller::get_instance();}
function is_loaded(){return ['router'=>'Router','uri'=>'Uri','input'=>'Input','config'=>'Config','db'=>'Db'];}
function &load_class($class,$directory=null){$value=&$GLOBALS['kernelObjects'][$class];return $value;}
require APPPATH.'third_party/MX/Router.php';require BASEPATH.'core/Controller.php';require APPPATH.'core/App_Controller.php';require APPPATH.'core/ClientsController.php';require APP_MODULES_PATH.'asaas/controllers/Checkout.php';
class PreflightDb{
 public bool $db_debug=true;public array $queries=[];public bool $fail=false;
 function __construct(public PDO $pdo){}
 function query($sql,$args=[]){$this->queries[]=[$sql,$args];if($this->fail)throw new RuntimeException('synthetic SQL detail must not escape');$s=$this->pdo->prepare($sql);$s->execute($args);$rows=$s->fetchAll(PDO::FETCH_ASSOC);return new class($rows){function __construct(private array $rows){}function result_array(){return $this->rows;}};}
}
class PreflightLoader{
 public array $autoload=[];
 function database(){$GLOBALS['bootTrace'][]='database';}
 function helper($name){$GLOBALS['bootTrace'][]='helper:'.$name;}
 function initialize(){include APPPATH.'config/autoload.php';$this->autoload=$autoload;$GLOBALS['bootTrace'][]='autoload_complete';foreach($autoload['libraries']??[] as $library)if($library==='app'||str_starts_with($library,'gateways/')){$GLOBALS['bootTrace'][]='provider_probe:'.$library;throw new CheckoutProviderReached($library);}}
}
$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->exec('CREATE TABLE tblinvoices(id INTEGER PRIMARY KEY,issuer_id INTEGER NULL,hash TEXT,private_document TEXT)');$insert=$pdo->prepare('INSERT INTO tblinvoices VALUES(?,?,?,?)');foreach([[1,null,'legacy_hash'],[2,81,'company_a_hash'],[3,82,'company_b_hash'],[4,0,'invalid_zero_owner'],[5,null,'ambiguous_hash'],[6,81,'ambiguous_hash']] as [$id,$owner,$hash])$insert->execute([$id,$owner,$hash,'private data never selected']);
$checks=0;function preflightCheck(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException('FAIL: '.$message);$checks++;}
function kernel(string $method,$hash,bool $fail=false):array{
 global $pdo,$CFG;$GLOBALS['bootTrace']=[];$router=new MX_Router();$router->config=$CFG;$located=$router->locate(['asaas','checkout',$method,is_string($hash)?$hash:'invalid_fixture_type']);$router->class=$located[0];$router->method=$located[1];$db=new PreflightDb($pdo);$db->fail=$fail;$loader=new PreflightLoader();
 $GLOBALS['kernelObjects']=['Router'=>$router,'Uri'=>new class($hash,$method){function __construct(private $hash,private $method){}function rsegment($i){return [1=>'checkout',2=>$this->method,3=>$this->hash][$i]??null;}function segment($i){return [1=>'asaas',2=>'checkout',3=>$this->method,4=>$this->hash][$i]??null;}},'Input'=>new class{function is_cli_request(){return false;}},'Config'=>$CFG,'Db'=>$db,'Loader'=>$loader];$status='unexpected';
 try{new Checkout();}catch(CheckoutPreflight404 $error){$status='404';}catch(CheckoutProviderReached $error){$status='legacy_provider_boot';}
 return [$status,$GLOBALS['bootTrace'],$db,$loader,$router];
}
if(($argv[1]??'')==='observe'){$result=kernel('index','company_a_hash');echo json_encode(['status'=>$result[0],'trace'=>$result[1]],JSON_THROW_ON_ERROR).PHP_EOL;exit;}
foreach(['index','boleto','cartao','qrcode'] as $method){
 foreach(['company_a_hash','company_b_hash','invalid_zero_owner','unknown_hash','ambiguous_hash'] as $hash){[$status,$trace,$db,$loader,$router]=kernel($method,$hash);preflightCheck($status==='404','actual constructor kernel rejects '.$method.'/'.$hash.' before provider boot');preflightCheck(!array_filter($trace,fn($v)=>str_starts_with($v,'provider_probe:')||$v==='gateway_catalog'||str_starts_with($v,'helper:')),'denied route never reaches global app/gateway/helper hydration');preflightCheck($loader->autoload===[]&&count($db->queries)===1&&str_starts_with($db->queries[0][0],'SELECT id,issuer_id FROM '),'only minimal ownership query runs before denial');preflightCheck($db->db_debug===true,'preflight restores DB debug policy after bounded read');}
 [$status,$trace,$db,$loader]=kernel($method,'legacy_hash');preflightCheck($status==='legacy_provider_boot'&&in_array('provider_probe:app',$trace,true),'explicit NULL retains native global bootstrap '.$method);preflightCheck(in_array('gateways/synthetic_gateway',$loader->autoload['libraries'],true)&&in_array('email',$loader->autoload['libraries'],true),'NULL autoload list preserves legacy provider/email choices '.$method);
 foreach(['../malformed',str_repeat('a',129),null,[],17] as $hash){[$status,$trace,$db]=kernel($method,$hash);preflightCheck($status==='404'&&$db->queries===[]&&$trace===[],'malformed resolved hash rejected before database or provider '.$method);}
}
[$status,$trace,$db]=kernel('index','legacy_hash',true);preflightCheck($status==='404'&&$db->db_debug===true&&!in_array('gateway_catalog',$trace,true),'database read failure denies before hydration and restores debug without SQL leakage');
// Route selection is based only on server-owned HMVC metadata; unrelated controllers are unaffected.
foreach([['other','checkout','index','../../modules/other/controllers'],['asaas','health','index','../../modules/asaas/controllers'],['asaas','checkout','error','../../modules/asaas/controllers'],[null,'checkout','index','']] as [$module,$class,$method,$directory]){
 $ci=(object)['router'=>new class($module,$class,$method,$directory){function __construct(private $module,private $class,private $method,private $directory){}function fetch_module(){return $this->module;}function fetch_class(){return $this->class;}function fetch_method(){return $this->method;}function fetch_directory(){return $this->directory;}}];preflightCheck(AsaasLegacyCheckoutPreflight::allowsBootstrap($ci),'unrelated route is not an ownership target');
}
echo 'Asaas legacy actual constructor/autoload preflight: '.$checks.' checks passed. Provider/library loading is probed, no provider network.'.PHP_EOL;
