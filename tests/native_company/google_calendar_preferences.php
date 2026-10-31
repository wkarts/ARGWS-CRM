<?php
require __DIR__.'/calendar_preferences.php';require_once APPPATH.'services/NativeCompanyGoogleCalendarPreferences.php';
$google=new NativeCompanyGoogleCalendarPreferences($db,$p);$g=$google->page($a,49);$gb=$google->page($b,49);
essentialCheck($g['values']===array_fill_keys(NativeCompanyGoogleCalendarPreferences::KEYS,''),'named Google starts empty and does not inherit global browser key');
$values=['google_api_key'=>'synthetic-browser-key','google_calendar_main_calendar'=>'calendar@example.test'];
IssuerTransaction::run($db,$p,$google->tables(),fn()=>$google->saveInOwnedTransaction($a,49,$g['revision'],$values));
essentialCheck($google->page($a,49)['values']===$values&&$google->page($b,49)===$gb,'Google key and main calendar remain issuer isolated');
essentialThrows(fn()=>IssuerTransaction::run($db,$p,$google->tables(),fn()=>$google->saveInOwnedTransaction($a,49,$g['revision'],$values)),'native_calendar_google_stale','stale Google form denied');
essentialThrows(fn()=>NativeCompanyGoogleCalendarPreferences::prepare(['google_api_key'=>"bad\nkey",'google_calendar_main_calendar'=>'']),'native_calendar_google_invalid','control bytes never emitted to browser transport');
echo "Native Google calendar preferences: 4 SQLite checks passed, no external Google call.\n";
