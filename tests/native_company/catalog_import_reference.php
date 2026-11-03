<?php
require __DIR__.'/auxiliary_contract.php';
if(!defined('TEMP_FOLDER'))define('TEMP_FOLDER',sys_get_temp_dir().'/');
require APPPATH.'libraries/import/Import_items.php';
$reflection=new ReflectionClass(Import_items::class);$import=$reflection->newInstanceWithoutConstructor();$method=$reflection->getMethod('nativeReference');
$owned=NativeCompanyResourceId::encode('company_tax',1);
$rows=[['id'=>'1','name'=>'Duplicated','taxrate'=>'5'],['id'=>$owned,'name'=>'Duplicated','taxrate'=>'7']];
settingThrows(fn()=>$method->invoke($import,$rows,'name','Duplicated'),'company_catalog_import_ambiguous_reference','duplicate label across lineages is not selected by first match');
essentialCheck($method->invoke($import,$rows,'id',$owned)->taxrate==='7'&&$method->invoke($import,$rows,'id','1')->taxrate==='5','explicit native ID resolves exact lineage');
essentialCheck($method->invoke($import,$rows,'name','Missing')===null,'unknown reference remains absent for caller validation');
settingThrows(fn()=>$method->invoke($import,$rows,'taxrate','5'),'company_catalog_invalid','only native documented ID/name selectors accepted');
echo "Native import exact identity and ambiguous reference rejection: passed.\n";
