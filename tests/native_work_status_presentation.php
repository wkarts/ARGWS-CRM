<?php
require_once __DIR__.'/../application/services/WorkStatusPresentation.php';
function _l($key){return 'translated:'.$key;}
foreach(['projects','tasks']as$kind){$native=require __DIR__.'/fixtures/native-'.$kind.'-status-definitions.php';$actual=WorkStatusPresentation::$kind('_l');if($native!==$actual)throw new RuntimeException('Native '.$kind.' definitions changed');$translated=WorkStatusPresentation::$kind(static fn($key)=>'alternate:'.$key);foreach($actual as$i=>$row){if($translated[$i]['id']!==$row['id']||!str_starts_with($translated[$i]['name'],'alternate:'))throw new RuntimeException('Translation changed identity');}}
$source=file_get_contents(__DIR__.'/../application/services/WorkStatusPresentation.php');if(preg_match('/get_instance|load->model|get_option|hooks\(/',$source))throw new RuntimeException('Presentation unexpectedly bootstraps global state');
echo "Native work status presentation: exact project/task definitions, custom translator and no global bootstrap passed.\n";
define('BASEPATH',__DIR__.'/../system/');define('APPPATH',__DIR__.'/../application/');
class App_Model {}
function hooks(){return new class{public function apply_filters($name,$rows){$GLOBALS['nativeStatusHooks'][]=$name;return $rows;}};}
require APPPATH.'models/Projects_model.php';require APPPATH.'models/Tasks_model.php';
foreach(['projects'=>['Projects_model','get_project_statuses'],'tasks'=>['Tasks_model','get_statuses']]as$kind=>[$class,$method]){$instance=(new ReflectionClass($class))->newInstanceWithoutConstructor();$expected=WorkStatusPresentation::$kind('_l');usort($expected,static fn($a,$b)=>$a['order']-$b['order']);if($instance->$method()!==$expected)throw new RuntimeException('Legacy model status order changed');}
if(Tasks_model::STATUS_IN_PROGRESS!==4||Tasks_model::STATUS_NOT_STARTED!==1||Tasks_model::STATUS_AWAITING_FEEDBACK!==2||Tasks_model::STATUS_TESTING!==3||Tasks_model::STATUS_COMPLETE!==5)throw new RuntimeException('Native task constants drifted');
if($GLOBALS['nativeStatusHooks']!==['before_get_project_statuses','before_get_task_statuses'])throw new RuntimeException('Legacy status hooks were removed');
echo "Legacy real model methods preserve hooks/order and task constants.\n";
