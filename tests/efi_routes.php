<?php
/** Actual finite route expressions + MX locator, without app config, controller or SDK execution. */
declare(strict_types=1);
define('BASEPATH',dirname(__DIR__).'/system/');define('APPPATH',dirname(__DIR__).'/application/');define('APP_MODULES_PATH',dirname(__DIR__).'/modules/');define('ADMIN_URI','admin');define('ENVIRONMENT','testing');
class CI_Router { public $config;public string $directory='',$class='',$method='';function fetch_directory(){return $this->directory;}function fetch_class(){return $this->class;}function fetch_method(){return $this->method;} }
$CFG=new class{function item($name){return $name==='modules_locations'?[APP_MODULES_PATH=>'../../modules/']:null;}};
function log_message(...$args){}
require APPPATH.'third_party/MX/Router.php';
$source=file_get_contents(APPPATH.'config/routes.php');
preg_match_all("~^\\\$route\\['(gateways/gerencia_net/[^']+)'\\] = '([^']+)';$~m",$source,$matches,PREG_SET_ORDER);
if(count($matches)!==9)throw new RuntimeException('Unexpected Efí route inventory');
$checks=0;
foreach(['invoice/17/synthetic_hash','gerar_cartao_credito/17/synthetic_hash','gerar_pix_por_javascript/17/synthetic_hash','callback_check_pix_pago/ABC123/17/synthetic_hash','callback_boleto','callback_cartao','view_converter_certificado_p12_para_pem','converterCertificado','fetch_pixes'] as $suffix){
    $uri='gateways/gerencia_net/'.$suffix;$destinations=[];
    foreach($matches as $route)if(preg_match('~^'.$route[1].'$~D',$uri))$destinations[]=preg_replace('~^'.$route[1].'$~D',$route[2],$uri);
    if(count($destinations)!==1)throw new RuntimeException('Route ambiguity');
    $router=new MX_Router();$router->config=$CFG;$located=$router->locate(explode('/',$destinations[0]));
    if($router->fetch_module()!=='gerencianet_gateway'||$located[0]!=='gerencia_net'||trim($router->fetch_directory(),'/')!=='../../modules/gerencianet_gateway/controllers')throw new RuntimeException('Route did not resolve to modular controller');
    ++$checks;
}
foreach(['fetch_payment','unknown','callback_check_pix_pago/ABC123/17'] as $suffix){
    foreach($matches as $route)if(preg_match('~^'.$route[1].'$~D','gateways/gerencia_net/'.$suffix))throw new RuntimeException('Unreviewed legacy URL aliased');
    ++$checks;
}
echo "Efí native MX route resolution: {$checks} checks PASS; no controller/SDK execution\n";
