<?php
/** Real installer/515; native runner boundary is a double, MySQL adoption has its own real test. */
declare(strict_types=1);
define('BASEPATH', dirname(__DIR__).'/system/');
function db_prefix(){return 'synthetic_';}
function &get_instance(){return $GLOBALS['ci'];}
class App_module_migration {
    function __construct($module=null){}
    function to_latest(){++$GLOBALS['runner'];if($GLOBALS['case']==='runner')return false;if($GLOBALS['case']==='tracking')$GLOBALS['ci']->db->version='5.1.4 sdk f';return true;}
}
$checks=0;
foreach(['core','engine','field','legacy','runner','tracking','success','success'] as $case) {
    $GLOBALS['case']=$case;$GLOBALS['runner']=0;
    $GLOBALS['ci']=(object)[
        'app'=>new class{function get_current_db_version(){return $GLOBALS['case']==='core'?472:473;}},
        'app_modules'=>new class{function get($name){return ['headers'=>['version'=>'5.1.5']];}},
        'db'=>new class{
            public string $version='5.1.5';
            function query($sql,$binds){return new class{function row(){return (object)['ENGINE'=>$GLOBALS['case']==='engine'?'MyISAM':'InnoDB'];}};}
            function field_exists($column,$table){return $GLOBALS['case']!=='field';}
            function where($column,$value){return $this;}
            function get($table){$version=$GLOBALS['case']==='legacy'?'5.1.4 sdk f':$this->version;return new class($version){function __construct(private $version){}function row(){return (object)['installed_version'=>$this->version];}};}
        }
    ];
    $error=null;try{include dirname(__DIR__).'/modules/gerencianet_gateway/install.php';}catch(Throwable $caught){$error=$caught;}
    ++$checks;
    if(($case==='success')!==($error===null))throw new RuntimeException('Unexpected installer outcome: '.$case);
    if(in_array($case,['core','engine','field','legacy'],true)&&$GLOBALS['runner']!==0)throw new RuntimeException('Runner reached before prerequisites');
    if($case==='success'&&($GLOBALS['runner']!==1||$GLOBALS['ci']->db->version!=='5.1.5'))throw new RuntimeException('Successful marker unconfirmed');
}
echo "Efí actual installer/515 contract: {$checks} cases PASS\n";
