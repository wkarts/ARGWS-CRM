<?php
require dirname(__DIR__).'/issuers_core_support.php';
$versions=['custom_links'=>['1.1.9','1.2.0'],'connect_api_chat'=>['1.1.2','1.1.3'],'accounting'=>['1.3.4','1.3.5'],'goals'=>['2.3.0','2.3.1'],'ma'=>['1.0.9','1.1.0'],'multiple_companies'=>['1.0.5','1.0.6']];
foreach($versions as $name=>[$old,$target]){
    $f=essentialFixture();$db=$f->db;$p=$f->prefix;
    $module=['system_name'=>$name,'headers'=>['version'=>$target],'init_file'=>APP_MODULES_PATH.$name.'/'.$name.'.php','path'=>APP_MODULES_PATH.$name.'/','installed_version'=>$old,'activated'=>1];
    $ref=new ReflectionClass(App_modules::class);$ref->getProperty('modules')->setValue($f->app_modules,[$name=>$module]);
    $db->insert($p.'modules',['module_name'=>$name,'installed_version'=>$old,'active'=>1]);
    $before=essentialLegacy($f);$runner=new App_module_migration($name);
    essentialCheck($runner->to_latest()!==false,$name.' native upgrade succeeds');
    essentialCheck($db->where('module_name',$name)->get($p.'modules')->row()->installed_version===$target,$name.' marker confirmed');
    essentialCheck($runner->to_latest()===true,$name.' replay is native no-op');
    essentialCheck(essentialLegacy($f)===$before,$name.' NOOP preserves business data');
    $db->where('module_name',$name)->update($p.'modules',['installed_version'=>$old]);$db->failVersion=$target;
    essentialCheck((new App_module_migration($name))->to_latest()===false,$name.' failed marker never reports success');
    $db->failVersion=null;
    $trigger=$p.'ignore_marker';
    if($db->driver()==='sqlite')$db->query("CREATE TRIGGER $trigger BEFORE UPDATE ON {$p}modules BEGIN SELECT RAISE(IGNORE); END");
    else $db->query("CREATE TRIGGER $trigger BEFORE UPDATE ON {$p}modules FOR EACH ROW SET NEW.installed_version=OLD.installed_version");
    essentialCheck((new App_module_migration($name))->to_latest()===false,$name.' ignored successful write is detected by readback');
    essentialCheck($f->app_modules->upgrade_database($name)!==true,$name.' module UI cannot report success after ignored marker');
    $db->query("DROP TRIGGER $trigger");
}
echo 'Company consumer native migrations: '.$GLOBALS['essentialChecks'].' checks passed.'.PHP_EOL;
