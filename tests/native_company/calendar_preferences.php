<?php
require __DIR__.'/../settings/calendar_setup.php';require_once APPPATH.'services/NativeCompanyCalendarPreferences.php';
$nativePrefs=new NativeCompanyCalendarPreferences($db,$p);
$first=$nativePrefs->page($a,49);$other=$nativePrefs->page($b,49);
essentialCheck(count($first['values'])===26&&isset($first['values']['show_ticket_reminders_on_calendar']),'same native calendar form has all 26 actual fields');
$save=fn($i,$actor,$rev,$v)=>IssuerTransaction::run($db,$p,$nativePrefs->tables(),fn()=>$nativePrefs->saveInOwnedTransaction($i,$actor,$rev,$v));
$v=$first['values'];$v['show_ticket_reminders_on_calendar']='0';$v['calendar_reminder_color']='#112233';$v['calendar_first_day']='2';$save($a,49,$first['revision'],$v);$next=$nativePrefs->page($a,49);
essentialCheck($next['values']===$v&&$next['revision']!==$first['revision'],'same atomic save persists base node and real reminder preference');
essentialCheck($nativePrefs->page($b,49)===$other,'calendar settings do not change company B');
essentialThrows(fn()=>$save($a,49,$first['revision'],$v),'native_calendar_preferences_stale','stale full native form denied');
$before=$db->query('SELECT settings_json FROM '.$p.'issuers_registry WHERE id=?',[$a])->row()->settings_json;
$db->failOn='INSERT INTO '.$p.'issuers_audit';$v2=array_replace($v,['show_ticket_reminders_on_calendar'=>'1','calendar_first_day'=>'3']);essentialThrows(fn()=>$save($a,49,$next['revision'],$v2),'issuer_storage_failed','calendar aggregate audit failure prevents partial base and extras');$db->failOn=null;
essentialCheck($db->query('SELECT settings_json FROM '.$p.'issuers_registry WHERE id=?',[$a])->row()->settings_json===$before,'failed aggregate leaves full JSON unchanged');
essentialThrows(fn()=>$save($a,50,$next['revision'],$v),'company_calendar_denied','calendar viewer cannot configure');
// Populate only this disposable fixture from the public baseline, never runtime fallback.
foreach(NativeCompanyCalendarPreferences::defaults()as$key=>$default){preg_match('/\(\d+, '.preg_quote("'".$key."'",'/').", '([^']*)', [01]\\)/",$native,$match);if(!$match)throw new RuntimeException('public calendar default missing '.$key);$db->query('DELETE FROM '.$p.'options WHERE name=?',[$key]);$db->query('INSERT INTO '.$p.'options(name,value) VALUES(?,?)',[$key,$match[1]]);}
$principal=$nativePrefs->page($calendarPrimary,49);essentialCheck($principal['values']['show_invoices_on_calendar']==='1'&&$principal['values']['calendar_first_day']==='0','principal uses native persisted historical options, not opt-out shadow defaults');
$pv=array_replace($principal['values'],['calendar_first_day'=>'4']);$save($calendarPrimary,49,$principal['revision'],$pv);essentialCheck($db->query('SELECT value FROM '.$p.'options WHERE name=?',['calendar_first_day'])->row()->value==='4','principal original setting updated through guarded native option');
essentialCheck($nativePrefs->page($a,49)===$next,'principal option change cannot alter named calendar values');
echo "Native calendar settings adapter: 10 actual storage checks PASS; original fields and principal/native lineage.\n";
