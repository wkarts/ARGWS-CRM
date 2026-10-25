<?php
require __DIR__.'/native_dashboard_preferences.php';
require_once APPPATH.'services/NativeCompanyTableOrder.php';
$start=$GLOBALS['essentialChecks'];$primary=(new IssuerResourceAccess($db,$p))->primaryId();
$ta=new NativeCompanyTableOrder($db,$p,$a,7);$tb=new NativeCompanyTableOrder($db,$p,$b,7);$tp=new NativeCompanyTableOrder($db,$p,$primary,7);
$db->query('INSERT INTO '.$p.'user_meta(staff_id,meta_key,meta_value) VALUES(?,?,?)',[7,'projects-table-last-order','[[2,"desc"]]']);
essentialCheck($tp->read('projects')==='[[2,"desc"]]'&&$ta->read('projects')===''&&$tb->read('projects')==='','native principal preference preserved; named companies do not inherit it');
$ta->save('projects',[['column'=>'1','dir'=>'asc']],4);
essentialCheck($ta->read('projects')==='[[1,"asc"]]'&&$tb->read('projects')===''&&$tp->read('projects')==='[[2,"desc"]]','table order isolated from B and principal');
essentialThrows(fn()=>$ta->save('projects',[['column'=>'4','dir'=>'asc']],4),'native_table_order_invalid','column bounds validated before persistence');
essentialThrows(fn()=>$ta->save('projects',[[1,'asc'],[1,'desc']],4),'native_table_order_invalid','duplicate column cannot enter stored order');
essentialThrows(fn()=>$ta->read('unknown'),'native_table_order_invalid','caller cannot select arbitrary feature or metadata key');
essentialThrows(fn()=>(new NativeCompanyTableOrder($db,$p,$a,51))->read('projects'),'native_table_order_denied','revoked actor cannot read own preference');
$db->failOn='UPDATE '.$p.'user_meta';try{$ta->save('projects',[[2,'desc']],4);throw new RuntimeException('failure injection ignored');}catch(RuntimeException $e){essentialCheck($e->getMessage()==='issuer_storage_failed','failed preference update is explicit');}finally{$db->failOn=null;}
essentialCheck($ta->read('projects')==='[[1,"asc"]]','failed write keeps former value');
$db->query('INSERT INTO '.$p.'user_meta(staff_id,meta_key,meta_value) VALUES(?,?,?)',[7,'issuer:'.$a.':projects-table-last-order','[[2,"desc"]]']);
essentialThrows(fn()=>$ta->read('projects'),'native_table_order_ambiguous','duplicate metadata never chooses arbitrary value');
echo 'Native table order: '.($GLOBALS['essentialChecks']-$start).' checks passed.'.PHP_EOL;
