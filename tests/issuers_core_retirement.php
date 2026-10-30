<?php
/** Real native loader, synthetic module-state I/O; archived files must never activate. */
define('BASEPATH',__DIR__);define('APPPATH',dirname(__DIR__).'/application/');
define('APP_MODULES_PATH',dirname(__DIR__).'/modules/');
function db_prefix(){return 'synthetic_';}
function &get_instance(){return $GLOBALS['retiredCI'];}
function directory_map($path,$depth){return ['issuers/','api/'];}
function read_file($path){return file_get_contents($path);}
$db=new class{public array $queries=[];function get($table){$this->queries[]=$table;return new class{function result(){return [(object)['module_name'=>'issuers','installed_version'=>'1.0.2','active'=>1],(object)['module_name'=>'api','installed_version'=>'2.0.1','active'=>1]];}};}};
$retiredCI=(object)['db'=>$db,'load'=>new class{function helper($name){}},'app'=>new class{function get_current_db_version(){return 393;}}];
require APPPATH.'libraries/App_modules.php';
$loader=new App_modules();$checks=0;
function retiredCheck($condition,$label){global$checks;$checks++;if(!$condition)throw new RuntimeException($label);}
retiredCheck(array_column(App_modules::get_valid_modules(),'name')===['api'],'disk history not discoverable as a module');
retiredCheck($loader->get('issuers')===null,'archived module absent from catalog');
retiredCheck($loader->get('api')!==null,'unrelated module remains discoverable');
retiredCheck(!array_key_exists('issuers',$loader->get_activated()),'historical active marker never loads module');
retiredCheck(array_key_exists('api',$loader->get_activated()),'unrelated active module preserved');
foreach(['activate','deactivate','uninstall','upgrade_database']as$operation){retiredCheck($loader->$operation('issuers')===false,'retired operation denied: '.$operation);}
retiredCheck($db->queries===['synthetic_modules'],'loader does not migrate/write retired state');
$before=$db->queries;require APPPATH.'helpers/issuers_helper.php';
retiredCheck($db->queries===$before,'helper inclusion before schema gate performs no queries');
retiredCheck(!defined('ISSUERS_MODULE_NAME'),'native helper never executes module init');
echo "Core company retirement: $checks checks passed.\n";
