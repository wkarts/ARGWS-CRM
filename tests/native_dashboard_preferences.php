<?php
require_once __DIR__.'/../application/services/NativeCompanyDashboardPreferences.php';
function nativePreferenceCheck($ok,string $name):void{if(!$ok)throw new RuntimeException($name);echo "PASS $name\n";}
$order=['left-8'=>['widget-calendar','widget-payments_chart'],'right-4'=>'empty'];
nativePreferenceCheck(NativeCompanyDashboardPreferences::order($order)===['left-8'=>['widget-calendar','widget-payments_chart'],'right-4'=>[]],'native sortable wire format preserved');
nativePreferenceCheck(NativeCompanyDashboardPreferences::visibility([['id'=>'calendar','visible'=>'0'],['id'=>'todos','visible'=>1]])===[['id'=>'calendar','visible'=>0],['id'=>'todos','visible'=>1]],'native checkbox values canonicalized');
foreach([
 ['order',['unknown'=>[]]],['order',['left-8'=>['../../secret']]],['order',['left-8'=>['widget-calendar','widget-calendar']]],
 ['order',['left-8'=>['widget-calendar'],'right-4'=>['widget-calendar']]],['order',['left-8'=>[1=>'widget-calendar']]],
 ['visibility',[['id'=>'calendar','visible'=>2]]],['visibility',[['id'=>'calendar','visible'=>0,'issuer_id'=>2]]],
 ['visibility',[['id'=>'calendar','visible'=>0],['id'=>'calendar','visible'=>1]]],['visibility',[['id'=>'arbitrary','visible'=>1]]],
] as $i=>[$method,$input]){try{NativeCompanyDashboardPreferences::$method($input);throw new RuntimeException('invalid preferences accepted '.$i);}catch(DomainException $e){echo "PASS invalid preference $i\n";}}
echo "Native dashboard preferences pure contracts passed.\n";
