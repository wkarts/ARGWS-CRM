<?php
declare(strict_types=1);
define('BASEPATH',__DIR__);define('APPPATH',dirname(__DIR__,2).'/application/');
$GLOBALS['nativeAuthCalls']=[];$GLOBALS['nativeAuthMethod']='get_qr';
class CompanyRequestScope{public static bool $native=true;public static function nativeFor($ci):bool{return self::$native;}}
class App_Controller{public $router;public $load;public $form_validation;public function __construct(){$this->router=new class{public function fetch_method(){return$GLOBALS['nativeAuthMethod'];}};$this->form_validation=new class{public function set_message(...$args){$GLOBALS['nativeAuthCalls'][]='validation';}};$this->load=new class{public function model($name){$GLOBALS['nativeAuthCalls'][]='model:'.$name;}public function library($name){$GLOBALS['nativeAuthCalls'][]='library:'.$name;}};}}
function load_admin_language(){$GLOBALS['nativeAuthCalls'][]='language';}function _l($key){return$key;}function hooks(){return new class{public function do_action($name){$GLOBALS['nativeAuthCalls'][]=$name;}};}
require APPPATH.'controllers/admin/Authentication.php';
new Authentication();if($GLOBALS['nativeAuthCalls']!==[])throw new RuntimeException('Native QR hydrated authentication model or hooks');
$GLOBALS['nativeAuthMethod']='admin';try{new Authentication();throw new RuntimeException('Unexpected native authentication action accepted');}catch(DomainException$error){if($error->getMessage()!=='native_staff_two_factor_route_denied')throw$error;}if($GLOBALS['nativeAuthCalls']!==[])throw new RuntimeException('Incompatible native action reached legacy bootstrap');
CompanyRequestScope::$native=false;new Authentication();if($GLOBALS['nativeAuthCalls']!==['language','model:Authentication_model','library:form_validation','validation','validation','validation','admin_auth_init'])throw new RuntimeException('Legacy authentication bootstrap changed');
echo "Actual Authentication constructor: three isolation/legacy checks; framework parent and scope are explicit doubles.\n";
