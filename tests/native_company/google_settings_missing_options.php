<?php
require __DIR__.'/google_calendar_preferences.php';
require_once APPPATH.'services/NativeCompanyGoogleSettings.php';
$forms=new NativeCompanyGoogleSettings($db,$p);
$secondary=$google->page($a,49);
foreach(NativeCompanyGoogleCalendarPreferences::KEYS as $key)$db->query('DELETE FROM '.$p.'options WHERE name=?',[$key]);
$page=$forms->page($calendarPrimary,7);
essentialCheck($page['values']['google_api_key']===''&&$page['values']['google_calendar_main_calendar']==='','missing optional primary Google keys render empty');
essentialCheck($db->query('SELECT value FROM '.$p.'options WHERE name=?',['google_api_key'])->result_array()===[],'GET does not create defaults');
$values=$page['values'];$values['google_api_key']='synthetic-primary-key';$values['google_calendar_main_calendar']='primary@example.invalid';
$saved=$forms->save($calendarPrimary,7,$page['revision'],$values,fn($v)=>'sealed:'.$v);
essentialCheck($saved['values']===$values,'explicit save creates missing primary options through guarded writer');
essentialThrows(fn()=>$forms->save($calendarPrimary,7,$page['revision'],$values,fn($v)=>'sealed:'.$v),'native_google_settings_stale','stale full form remains rejected');
essentialCheck($google->page($a,49)===$secondary,'primary save never modifies secondary values');
essentialThrows(fn()=>$forms->page($calendarPrimary,50),'native_google_settings_denied','unauthorized primary reader denied');
$values['google_api_key']='rollback-key';$db->failOn='INSERT INTO '.$p.'issuers_audit';
essentialThrows(fn()=>$forms->save($calendarPrimary,7,$saved['revision'],$values,fn($v)=>'sealed:'.$v),'issuer_storage_failed','audit failure is recoverable and atomic');$db->failOn=null;
essentialCheck($forms->page($calendarPrimary,7)===$saved,'failed save preserves complete form and revision');
echo "Primary Google missing optional options, guarded save, stale, ACL, isolation and rollback passed.\n";
