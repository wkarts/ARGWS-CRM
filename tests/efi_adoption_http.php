<?php
/** Administrative dispatch negatives use the real controller, without app bootstrap/providers. */
declare(strict_types=1);
define('BASEPATH', dirname(__DIR__) . '/system/');
define('APPPATH', dirname(__DIR__) . '/application/');
require APPPATH . 'services/EfiVersionAdoption.php';
class AdminController { public $input; public $session; public $app_modules; public $db; }
require APPPATH . 'controllers/admin/Mods.php';
require APPPATH . 'libraries/App_modules.php';
class EfiHttpStop extends RuntimeException {}
function is_admin(){return $GLOBALS['admin'];}
function access_denied($resource){throw new EfiHttpStop('denied',403);}
function show_404(){throw new EfiHttpStop('not_found',404);}
function show_error($message,$status){throw new EfiHttpStop('csrf',$status);}
function db_prefix(){return 'synthetic_';}
function admin_url($path){return '/admin/'.$path;}
function redirect($path){}
function set_alert($level,$message){$GLOBALS['alerts'][]=$level;}
function log_message($level,$message){}
function get_staff_user_id(){return 17;}
$checks=0;
function efiHttpCheck($ok,$message){++$GLOBALS['checks'];if(!$ok)throw new RuntimeException($message);}
function efiRequest(bool $admin,string $method,array $post,string $source='5.1.4 sdk f'): array {
    $GLOBALS['admin']=$admin;$GLOBALS['alerts']=[];
    $controller=(new ReflectionClass(Mods::class))->newInstanceWithoutConstructor();
    $controller->input=new class($method,$post){function __construct(private $method,private $post){}function method($upper){return $this->method;}function post($key){return $this->post[$key]??null;}};
    $controller->session=new class{function userdata($key){return str_repeat('a',64);}};
    $controller->app_modules=new class{function get($name){return ['installed_version'=>'5.1.4 sdk f','headers'=>['version'=>'5.1.5']];}};
    $controller->db=new class($source){
        function __construct(private $source){}
        public string $database='synthetic';public array $queries=[];
        function query($sql,$args){$this->queries[]=$sql;if(str_contains($sql,'GET_LOCK'))return new class{function row(){return (object)['acquired'=>1];}};if(str_contains($sql,'RELEASE_LOCK'))return true;throw new RuntimeException('unexpected storage access');}
        function where($key,$value){return $this;}
        function get($table){return new class($this->source){function __construct(private $source){}function row(){return (object)['installed_version'=>$this->source,'active'=>0];}};}
    };
    try{$controller->upgrade_database('gerencianet_gateway');$status=200;}catch(EfiHttpStop $error){$status=$error->getCode();}
    return [$status,$controller->db->queries,$GLOBALS['alerts']];
}
foreach([[false,'POST',[],403],[true,'GET',[],404],[true,'POST',[],403],[true,'POST',['module_migration_csrf'=>'bad'],403]] as [$admin,$method,$post,$expected]) {
    [$status,$queries]=$result=efiRequest($admin,$method,$post);
    efiHttpCheck($status===$expected&&$queries===[], 'admin/method/CSRF reject before any DB lock/mutation');
}
foreach([[],['adoption_plan'=>'wrong'],['adoption_plan'=>'efi-native-515-v1','adoption_source'=>[]]] as $post){
    [$status,$queries,$alerts]=efiRequest(true,'POST',$post+['module_migration_csrf'=>str_repeat('a',64)]);
    efiHttpCheck($alerts===['danger'],'explicit plan/source confirmation required');
    efiHttpCheck(count($queries)===2&&str_contains($queries[0],'GET_LOCK')&&str_contains($queries[1],'RELEASE_LOCK'),'confirmation failure releases lock without SQL mutation');
}
foreach(['5.1.4','5.1.3','5.1.4 sdk','5.1.6'] as $source) {
    [$status,$queries,$alerts]=efiRequest(true,'POST',['module_migration_csrf'=>str_repeat('a',64)],$source);
    efiHttpCheck($alerts===['danger']&&count($queries)===2,'unproven canonical/text origins denied by actual controller');
}
foreach(['5.1.4 sdk f'=>'explicit_adoption','5.1.4 sdk final'=>'explicit_adoption','5.1.4'=>'invalid_version','5.1.3'=>'invalid_version','5.1.4 sdk'=>'invalid_version'] as $source=>$status) {
    $modules=(new ReflectionClass(App_modules::class))->newInstanceWithoutConstructor();
    foreach(['modules'=>['gerencianet_gateway'=>['headers'=>['version'=>'5.1.5']]],'db_modules'=>['gerencianet_gateway'=>(object)['installed_version'=>$source,'active'=>0]]] as $property=>$value){
        $prop=new ReflectionProperty(App_modules::class,$property);$prop->setValue($modules,$value);
    }
    $inventory=$modules->database_maintenance_inventory();
    efiHttpCheck($inventory['gerencianet_gateway']['status']===$status,'actual inventory preserves explicit origin policy');
}
echo "Efí adoption actual controller negatives: {$checks} checks PASS\n";
