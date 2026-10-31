<?php
require __DIR__.'/staff_form_read.php';require_once APPPATH.'services/NativeCompanyStaffPreferences.php';
$db->query('UPDATE '.$p.'issuers_registry SET active=1 WHERE id=82');
$prefs=new NativeCompanyStaffPreferences($db,$p);$page=$prefs->page(41,7);$roleWire=NativeCompanyResourceId::encode('company_staff_role',$role);
IssuerTransaction::run($db,$p,$prefs->tables(),fn()=>$prefs->saveInOwnedTransaction(41,7,$page['revision'],['default_staff_role'=>$roleWire]));
essentialCheck($reader->page(41,7)['native_staff_selected_role']===$roleWire,'same native creation form selects real scoped default');
essentialCheck($prefs->page(82,7)['values']['default_staff_role']==='','other company does not inherit the default');
settingThrows(fn()=>IssuerTransaction::run($db,$p,$prefs->tables(),fn()=>$prefs->saveInOwnedTransaction(41,7,$page['revision'],['default_staff_role'=>''])),'native_staff_preferences_stale','stale settings save denied');
settingThrows(fn()=>NativeCompanyStaffPreferences::prepare(['default_staff_role'=>'1']),'native_staff_preferences_invalid','global raw role is not a scoped default');
$second=$roles->save(41,7,null,$roles->page(41,7)['revision'],'Default only',[]);$next=$prefs->page(41,7);IssuerTransaction::run($db,$p,$prefs->tables(),fn()=>$prefs->saveInOwnedTransaction(41,7,$next['revision'],['default_staff_role'=>NativeCompanyResourceId::encode('company_staff_role',$second)]));
settingThrows(fn()=>$roles->delete(41,7,$second,$roles->page(41,7)['revision']),'native_staff_role_referenced','default role reference prevents dangling deletion without assignments');
echo "Staff default role: five actual SQLite native-wire/isolation/CAS/reference checks passed.\n";
