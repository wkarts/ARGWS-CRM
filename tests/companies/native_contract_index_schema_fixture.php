<?php
/** Missing index prerequisites in the existing disposable contract fixture. No production schema fallback. */
require_once APPPATH.'services/NativeCompanyFilterSchema.php';
require_once APPPATH.'services/NativeCompanyCustomFieldSchema.php';
require_once dirname(__DIR__).'/support/NativeInstalledSchemaFixture.php';
NativeCompanyFilterSchema::ensure($db,$p);
NativeCompanyCustomFieldSchema::ensure($db,$p);
$contractIndexPlan=NativeInstalledSchemaFixture::plan(file_get_contents(dirname(__DIR__,2).'/install/database.sql'),$p);
$contractIndexCreated=[];
foreach(['currencies','contracts_types','filters','filter_defaults','customfields','customfieldsvalues','projects'] as $name){
    if($db->table_exists($p.$name))continue;
    $ddl=$contractIndexPlan[$name]['create'];
    if($db->driver()==='sqlite'){
        $ddl=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$ddl);
        $ddl=preg_replace('/enum\([^)]*\)/','varchar(50)',$ddl);
    }
    $db->query($ddl);
    if($db->driver()==='mysql')foreach($contractIndexPlan[$name]['alters'] as $ddl)$db->query($ddl);
    $contractIndexCreated[]=$name;
    unset($db->data_cache['table_names']);
}
if(!$db->query('SELECT id FROM '.$p.'currencies WHERE isdefault=1')->row_array()){
    $db->query('INSERT INTO '.$p.'currencies(id,name,symbol,decimal_separator,thousand_separator,placement,isdefault) VALUES(?,?,?,?,?,?,?)',[1,'BRL','R$',',','.','before',1]);
}
// contracts_types is absent from the older shared cleanup list.
register_shutdown_function(static function()use($db,$p,$contractIndexCreated){if(in_array('contracts_types',$contractIndexCreated,true))$db->query('DROP TABLE IF EXISTS '.$p.'contracts_types');});
