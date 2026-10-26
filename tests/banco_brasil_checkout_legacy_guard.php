<?php
/** Execute the actual legacy boundary without providing any DB/invoice/provider collaborator. */
declare(strict_types=1);
define('BASEPATH',dirname(__DIR__).'/system/');
class App_Model {}
require dirname(__DIR__).'/application/models/Payments_model.php';
$alerts=[];
function set_alert($kind,$message){$GLOBALS['alerts'][]=[$kind,$message];}
$model=(new ReflectionClass(Payments_model::class))->newInstanceWithoutConstructor();
foreach(['banco_brasil','banco_brasil_api','banco_brasil_cnab'] as $mode)foreach([[],['invoiceid'=>5],['invoiceid'=>99,'invoice'=>new class{function __get($name){throw new RuntimeException('invoice hydration forbidden');}}]] as $input){
    if($model->process_payment(['paymentmode'=>$mode]+$input,5)!==false)throw new RuntimeException('legacy checkout accepted');
}
if(count($alerts)!==9||array_unique(array_column($alerts,0))!==['warning'])throw new RuntimeException('safe native warning missing');
echo "BB legacy boundary rejects before DB, invoice hydration, payment attempt or provider.\n";
define('APPPATH',dirname(__DIR__).'/application/');
class AdminController {public $input;public $session;}
class CheckoutHttpStop extends RuntimeException {}
function show_error($message,$status){throw new CheckoutHttpStop('guard', $status);}
require dirname(__DIR__).'/modules/banco_brasil/controllers/Banco_brasil.php';
foreach(['preview_checkout','publish_checkout','save_channel_preferences'] as $action){
    foreach([['GET',str_repeat('a',64),405],['POST','invalid',403],['POST',null,403]] as [$method,$token,$expected]){
        $controller=(new ReflectionClass(Banco_brasil::class))->newInstanceWithoutConstructor();
        $controller->input=new class($method,$token){function __construct(private $verb,private $token){}function method(){return $this->verb;}function post($name){if($name!=='csrf_bb')throw new RuntimeException('unauthorized field read');return $this->token;}};
        $controller->session=new class{function userdata($key){return str_repeat('a',64);}};
        try{$controller->$action();throw new RuntimeException('controller accepted forbidden request');}
        catch(CheckoutHttpStop $error){if($error->getCode()!==$expected)throw $error;}
    }
}
echo "BB controller method and CSRF guards passed before issuer/DB/provider access.\n";
