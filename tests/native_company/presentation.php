  $host=new class($this){private $owner;function __construct($owner){$this->owner=$owner;}function __get($key){return$this->owner->$key;}function __isset($key){return isset($this->owner->$key);}};
  check(!CompanyRequestScope::nativeFor($host),'a view loader cannot attest as the controller');
  (function($view,$extra,$variables){extract($variables);extract($extra);require APPPATH.'views/'.$view.'.php';})->call($host,$name,$extra,$this->vars);
 }
 function __get($name){throw new RuntimeException('Global dependency forbidden: '.$name);}
}
require APPPATH.'services/NativeCompanyAdminPresentation.php';
require_once APPPATH.'helpers/custom_fields_helper.php';
$ci=new Fixture;$GLOBALS['fixture']=$ci;CompanyRequestScope::$ci=$ci;
if(!$presentationDirect){return;}
NativeCompanyAdminPresentation::prepare($ci);
check(($ci->lang->language['dashboard_string']??null)==='Painel','shared CI language object receives the trusted native PT-BR catalog');
$data=NativeCompanyAdminPresentation::current($ci);
