<?php
/** Actual CI/MX/App router against the shipped tree; no bootstrap stubs for routing or module lookup. */
declare(strict_types=1);
define('BASEPATH',dirname(__DIR__).'/system/');define('APPPATH',dirname(__DIR__).'/application/');define('APP_MODULES_PATH',dirname(__DIR__).'/modules/');define('ADMIN_URI','admin');
$CFG=new class{public function item($key){return match($key){'modules_locations'=>[APP_MODULES_PATH=>'../../modules/'],'controller_suffix'=>'',default=>null};}};$GLOBALS['CFG']=$CFG;
require BASEPATH.'core/Router.php';require APPPATH.'core/App_Router.php';
$checks=0;function routingCheck(bool$ok,string$label):void{++$GLOBALS['checks'];if(!$ok)throw new RuntimeException($label);}
function routeFixture():App_Router{$router=(new ReflectionClass(App_Router::class))->newInstanceWithoutConstructor();$router->config=$GLOBALS['CFG'];$router->uri=(object)['rsegments'=>[]];$router->directory='';return$router;}
foreach([['admin','issuers'],['admin','issuers','index'],['admin','issuers','index','41'],['admin','issuers','save'],['admin','issuers','unexpected_method']]as$segments){
 $router=routeFixture();$resolved=$router->locate($segments);
 routingCheck($resolved===array_slice($segments,1),'native company route keeps controller/method/ID segments '.implode('/',$segments));
 routingCheck($router->directory==='admin/'&&$router->fetch_module()===null,'archived module cannot shadow native company controller '.implode('/',$segments));
 routingCheck(realpath(APPPATH.'controllers/'.$router->directory.ucfirst($resolved[0]).'.php')===realpath(APPPATH.'controllers/admin/Issuers.php'),'actual native controller path selected');
 [$path,$file]=Modules::find('issuers_helper',$router->fetch_module(),'helpers/');routingCheck($path===false&&$file==='issuers_helper','native helper resolution falls through to application, never retired module');
}
foreach([['issuers'],['issuers','index','41'],['issuers','save']]as$segments){$router=routeFixture();$result=$router->locate($segments);routingCheck($result===null&&$router->fetch_module()!=='issuers','unprefixed retired module cannot become a public route');}
foreach([['banco_brasil','banco_brasil'],['asaas','asaas']]as[$module,$controller]){$router=routeFixture();$result=$router->locate(['admin',$module,$controller,'index']);routingCheck($result===[$controller,'index']&&$router->fetch_module()===$module,'unrelated module controller remains routable '.$module);routingCheck($router->directory==='../../modules/'.$module.'/controllers/','unrelated module location remains canonical '.$module);}
$router=routeFixture();$result=$router->locate(['admin','staff','member','17']);routingCheck($result===['staff','member','17']&&$router->directory==='admin/'&&$router->fetch_module()===null,'ordinary native staff route remains native');
routingCheck(!function_exists('issuer_core_ready')&&!defined('ISSUERS_MODULE_NAME'),'router proof never hydrates native or retired helpers/modules');
echo 'Native company routing: '.$checks.' checks; actual CI/MX lookup, no retired module dispatch or helper shadow; unrelated module/native routes preserved.'.PHP_EOL;
