<?php
require __DIR__.'/calendar_feed.php';require_once APPPATH.'services/NativeCompanyCalendarEndpoints.php';
if(!function_exists('db_prefix')){function db_prefix(){return $GLOBALS['p'];}}
if(!function_exists('_l')){function _l($k){return $k;}}
if(!function_exists('to_sql_date')){function to_sql_date($v,$time=false){return $v;}}
final class CompanyRequestScope{
    public static $issuer;public static $actor=49;public static $valid=true;public static $calls=0;
    public static function nativeFor($ci){return true;}
    public static function context(){return new class {function issuerId(){return CompanyRequestScope::$issuer;}function staffId(){return CompanyRequestScope::$actor;}};}
    public static function nativeCsrfToken($ci){return 'synthetic-token';}
    public static function assertNativeMutation($ci){self::$calls++;if(!$ci->input->postAllowed||!self::$valid)throw new DomainException('native_mutation_denied');}
}
$input=new class{public array $data=[];public bool $postAllowed=true;function post($k=null){return $k===null?$this->data:($this->data[$k]??null);}function get(){return $GLOBALS['query'];}};$ci=(object)['db'=>$db,'input'=>$input];CompanyRequestScope::$issuer=$a;
$input->data=['title'=>'Native form event','description'=>'Synthetic','start'=>'2026-10-24 10:00:00','end'=>'','color'=>'#123456','reminder_before'=>'10','reminder_before_type'=>'minutes'];
$result=NativeCompanyCalendarEndpoints::save($ci);essentialCheck($result['success']===true&&$result['revision']==='1','original form fields save real owned event');
$input->data['eventid']=$result['eventid'];$input->data['revision']='1';$input->data['title']='Edited native form';$edited=NativeCompanyCalendarEndpoints::save($ci);essentialCheck($edited['revision']==='2','original event form edits through revision guard');
essentialThrows(fn()=>NativeCompanyCalendarEndpoints::save($ci),'native_event_stale','stale original form cannot overwrite');
CompanyRequestScope::$issuer=$b;$input->data=['revision'=>'2'];essentialThrows(fn()=>NativeCompanyCalendarEndpoints::delete($ci,$result['eventid']),'native_event_denied','foreign issuer delete denied');CompanyRequestScope::$issuer=$a;
CompanyRequestScope::$valid=false;essentialThrows(fn()=>NativeCompanyCalendarEndpoints::delete($ci,$result['eventid']),'native_mutation_denied','CSRF fails before delete service');CompanyRequestScope::$valid=true;
$input->postAllowed=false;essentialThrows(fn()=>NativeCompanyCalendarEndpoints::delete($ci,$result['eventid']),'native_mutation_denied','GET delete forbidden');$input->postAllowed=true;
essentialCheck(NativeCompanyCalendarEndpoints::delete($ci,$result['eventid'])['success']===true,'POST deletes original modal event after matching revision');
echo "Calendar original endpoint adapters: 7 real SQLite checks; boundary double explicit, no HTTP claim.\n";
if(!$db->table_exists($p.'company_departments'))$db->query('CREATE TABLE '.$p.'company_departments(id INT PRIMARY KEY,issuer_id INT,calendar_id TEXT)');
if(!$db->table_exists($p.'company_department_members'))$db->query('CREATE TABLE '.$p.'company_department_members(id INT PRIMARY KEY,issuer_id INT,staff_id INT,department_source_kind TEXT,department_source_id INT)');
$db->query('INSERT INTO '.$p.'company_departments VALUES(1,?,?)',[$a,'a-calendar@example.test']);$db->query('INSERT INTO '.$p.'company_departments VALUES(2,?,?)',[$b,'b-calendar@example.test']);
$db->query('INSERT INTO '.$p.'company_department_members VALUES(1,?,49,?,1)',[$a,'company_ticket_department']);
$db->query('INSERT INTO '.$p.'company_department_members VALUES(2,?,49,?,2)',[$a,'company_ticket_department']);
$store=new CompanySettingsStorage($db,$p);$store->updateBatch($a,49,['dateformat'=>'Y-m-d|%Y-%m-%d','time_format'=>'24','default_timezone'=>'America/Sao_Paulo']);
if(!$db->table_exists($p.'emailtemplates'))$db->query('CREATE TABLE '.$p.'emailtemplates(emailtemplateid INT PRIMARY KEY,issuer_id INT,slug TEXT,language TEXT,active INT,message TEXT)');elseif(!IssuerStorage::fieldExists($db,'issuer_id',$p.'emailtemplates'))$db->query('ALTER TABLE '.$p.'emailtemplates ADD issuer_id INT');
essentialThrows(fn()=>$store->mutate($a,49,'delete','default_timezone'),'company_settings_access_denied','calendar editor cannot delete company settings without that capability');
$store->mutate($a,7,'delete','default_timezone');
$unconfigured=NativeCompanyCalendarEndpoints::page($ci);
essentialCheck($unconfigured['native_calendar_config']['options']['timezone']==='UTC','new company renders calendar with public UTC default without global inheritance');
$store->mutate($a,49,'update','default_timezone','Brazil/East');
essentialCheck(NativeCompanyCalendarEndpoints::page($ci)['native_calendar_config']['options']['timezone']==='Brazil/East','calendar accepts timezone aliases accepted by the native Settings validator');
$store->mutate($a,49,'update','default_timezone','not/a/timezone');essentialThrows(fn()=>NativeCompanyCalendarEndpoints::page($ci),'native_calendar_configuration_invalid','explicit invalid timezone remains an error');
$store->mutate($a,49,'update','default_timezone','America/Sao_Paulo');
$page=NativeCompanyCalendarEndpoints::page($ci);
essentialCheck($page['native_calendar_config']['calendar_ids']===['a-calendar@example.test'],'calendar-only member sees own department source, corrupted cross-issuer membership is ignored');
essentialCheck($page['native_calendar_config']['options']['timezone']==='America/Sao_Paulo'&&$page['native_calendar_config']['options']['date_format']==='Y-m-d','native FullCalendar gets actual scoped timezone and format');
echo "Calendar page adapter: 2 additional actual storage checks passed.\n";
$db->query('INSERT INTO '.$p.'company_departments VALUES(3,?,?)',[$a,'other-department@example.test']);$departments=new NativeCompanyDepartments($db,$p);
essentialCheck($departments->calendarSources($a,49)===['a-calendar@example.test'],'calendar grant alone does not broaden department assignment');
essentialCheck(count($departments->calendarSources($a,7))===2,'installation administrator sees all calendars of selected issuer only');
$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=? AND staff_id=49 AND feature='calendar'",[$a]);essentialThrows(fn()=>$departments->calendarSources($a,49),'native_department_denied','revoked calendar grant denies department source lookup');
echo "Calendar department sources: 3 additional actual authority checks passed.\n";
