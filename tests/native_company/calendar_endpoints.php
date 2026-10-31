<?php
require __DIR__.'/calendar_feed.php';require_once APPPATH.'services/NativeCompanyCalendarEndpoints.php';$nativeEndpointStart=$GLOBALS['essentialChecks'];
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
$input=new class{public array $data=[];public bool $postAllowed=true;public ?string $target=null;function post($k=null){return $k===null?$this->data:($this->data[$k]??null);}function get(){return $GLOBALS['query']+['_framework_route'=>'synthetic ambient metadata'];}function server($key,$clean=false){return $key==='REQUEST_URI'?$this->target:null;}};$ci=(object)['db'=>$db,'input'=>$input];CompanyRequestScope::$issuer=$a;
$input->target='/admin/utilities/get_calendar_data?'.http_build_query(['start'=>'2026-09-27','end'=>'2026-11-08']);
essentialCheck(count(NativeCompanyCalendarEndpoints::feed($ci))===9,'exact browser target reads all owned sources despite unrelated ambient framework input');
$queryDiagnostic=NativeCompanyCalendarEndpoints::queryDiagnostics($ci);$queryDiagnosticJson=json_encode($queryDiagnostic,JSON_THROW_ON_ERROR);essentialCheck(($queryDiagnostic['key_count']??0)===2&&count($queryDiagnostic['key_fingerprints']??[])===2&&!str_contains($queryDiagnosticJson,'2026-09-27')&&!str_contains($queryDiagnosticJson,'_framework_route')&&!str_contains($queryDiagnosticJson,'synthetic ambient metadata'),'controller diagnostics contain only bounded key fingerprints and no request or ambient values');
$ci->security=new class{public string $name='csrf_token_name';function get_csrf_token_name(){return $this->name;}function get_csrf_hash(){return 'synthetic-framework-hash';}};
$civilTarget=$input->target;$input->target='/admin/utilities/get_calendar_data?csrf_token_name=synthetic-framework-hash&start=2026-09-27&end=2026-11-08';
essentialCheck(count(NativeCompanyCalendarEndpoints::feed($ci))===9,'current framework metadata is verified and consumed before the unchanged source whitelist');
$input->target=str_replace('synthetic-framework-hash','wrong-framework-hash',$input->target);essentialThrows(fn()=>NativeCompanyCalendarEndpoints::feed($ci),'native_calendar_framework_csrf_invalid','unknown or stale framework metadata is never silently stripped');
foreach(['&start=2026-10-01','&csrf_token_name=wrong&csrf_token_name=synthetic-framework-hash','&events%5B%5D=true','&calendar.filters=true','&%zz=true','&events=true;invoices=true','&_company_context=synthetic','&_native_company_csrf=synthetic']as$suffix){$input->target=$civilTarget.$suffix;$code=preg_match('/start=|csrf_token_name=|%zz/',$suffix)?'native_calendar_request_target_invalid':'native_calendar_range_invalid';essentialThrows(fn()=>NativeCompanyCalendarEndpoints::feed($ci),$code,'flat HTTP contract rejects duplicates malformed escapes bracket/dot aliases and URL-native tokens');}
$ci->security->name='start';$input->target=$civilTarget;essentialThrows(fn()=>NativeCompanyCalendarEndpoints::feed($ci),'native_calendar_configuration_invalid','framework configuration cannot erase a calendar data field');$ci->security->name='csrf_token_name';$input->target=$civilTarget;
$input->target.='&issuer_id='.$b;$beforeQueryReject=count($db->queries);essentialThrows(fn()=>NativeCompanyCalendarEndpoints::feed($ci),'native_calendar_range_invalid','unknown real HTTP company parameter is rejected even if framework input omits it');
$rejectedQueries=array_slice($db->queries,$beforeQueryReject);essentialCheck(!array_filter($rejectedQueries,fn($q)=>preg_match('/FROM '.preg_quote($p,'/').'(?:invoices|company_events|company_reminders|company_sales_documents)\\b/i',$q[0])),'unknown HTTP query is rejected before reading any source content');
foreach([null,'https://other.example/admin/utilities/get_calendar_data?start=2026-09-27&end=2026-11-08','/admin/utilities/get_calendar_data?'.str_repeat('end=2026-11-08&',65)]as$badTarget){$input->target=$badTarget;essentialThrows(fn()=>NativeCompanyCalendarEndpoints::feed($ci),'native_calendar_request_target_invalid','missing absolute or excessive target has no ambient-input fallback');}
$input->target='/admin/utilities/get_calendar_data?'.http_build_query($query);
$input->data=['calendar_filters'=>'1','events'=>'1'];$beforeFilterSettings=$db->query('SELECT settings_json FROM '.$p.'issuers_registry WHERE id=?',[$a])->row()->settings_json;$beforeFilterEvents=$db->query('SELECT COUNT(*) n FROM '.$p.'company_events')->row()->n;NativeCompanyCalendarEndpoints::filter($ci);
essentialCheck($db->query('SELECT settings_json FROM '.$p.'issuers_registry WHERE id=?',[$a])->row()->settings_json===$beforeFilterSettings&&$db->query('SELECT COUNT(*) n FROM '.$p.'company_events')->row()->n===$beforeFilterEvents,'original filter POST changes neither settings nor events');
$input->data['title']='Not an event';essentialThrows(fn()=>NativeCompanyCalendarEndpoints::filter($ci),'native_calendar_range_invalid','filter form cannot contain event mutations');$input->data=['events'=>'1'];essentialThrows(fn()=>NativeCompanyCalendarEndpoints::filter($ci),'native_calendar_filter_invalid','explicit filter marker is required');
$input->data=['calendar_filters'=>'1','events'=>'1'];CompanyRequestScope::$valid=false;essentialThrows(fn()=>NativeCompanyCalendarEndpoints::filter($ci),'native_mutation_denied','filter POST keeps native CSRF/context enforcement');CompanyRequestScope::$valid=true;$input->postAllowed=false;essentialThrows(fn()=>NativeCompanyCalendarEndpoints::filter($ci),'native_mutation_denied','filter adapter never uses GET as mutation admission');$input->postAllowed=true;
$input->data=['title'=>'Native form event','description'=>'Synthetic','start'=>'2026-10-24 10:00:00','end'=>'','color'=>'#123456','reminder_before'=>'10','reminder_before_type'=>'minutes'];
$result=NativeCompanyCalendarEndpoints::save($ci);essentialCheck($result['success']===true&&$result['revision']==='1','original form fields save real owned event');
$input->data['eventid']=$result['eventid'];$input->data['revision']='1';$input->data['title']='Edited native form';$edited=NativeCompanyCalendarEndpoints::save($ci);essentialCheck($edited['revision']==='2','original event form edits through revision guard');
essentialThrows(fn()=>NativeCompanyCalendarEndpoints::save($ci),'native_event_stale','stale original form cannot overwrite');
CompanyRequestScope::$issuer=$b;$input->data=['revision'=>'2'];essentialThrows(fn()=>NativeCompanyCalendarEndpoints::delete($ci,$result['eventid']),'native_event_denied','foreign issuer delete denied');CompanyRequestScope::$issuer=$a;
CompanyRequestScope::$valid=false;essentialThrows(fn()=>NativeCompanyCalendarEndpoints::delete($ci,$result['eventid']),'native_mutation_denied','CSRF fails before delete service');CompanyRequestScope::$valid=true;
$input->postAllowed=false;essentialThrows(fn()=>NativeCompanyCalendarEndpoints::delete($ci,$result['eventid']),'native_mutation_denied','GET delete forbidden');$input->postAllowed=true;
essentialCheck(NativeCompanyCalendarEndpoints::delete($ci,$result['eventid'])['success']===true,'POST deletes original modal event after matching revision');
echo 'Calendar original endpoint adapters: '.($GLOBALS['essentialChecks']-$nativeEndpointStart).' added checks ('.$db->driver().'); boundary double explicit, no HTTP claim.'.PHP_EOL;
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
$preparedEvent=NativeCompanyCalendarEndpoints::event($ci,$event['eventid']);NativeCompanyCalendarEndpoints::confirm($ci,$preparedEvent,$event['eventid']);
$eventSource=NativeCompanyResourceId::decode('event',$event['eventid']);$db->query('UPDATE '.$p.'company_events SET title=? WHERE issuer_id=? AND eventid=?',['Changed after render',$a,$eventSource['source_id']]);
essentialThrows(fn()=>NativeCompanyCalendarEndpoints::confirm($ci,$preparedEvent,$event['eventid']),'native_calendar_stale','buffered event content is revalidated before HTML delivery');
$db->query('UPDATE '.$p.'company_events SET title=? WHERE issuer_id=? AND eventid=?',[$preparedEvent['event']->title,$a,$eventSource['source_id']]);
$page=NativeCompanyCalendarEndpoints::page($ci);
NativeCompanyCalendarEndpoints::confirm($ci,$page);
$store->mutate($a,49,'update','default_timezone','UTC');essentialThrows(fn()=>NativeCompanyCalendarEndpoints::confirm($ci,$page),'native_calendar_stale','buffered original calendar cannot emit a superseded configuration');
$store->mutate($a,49,'update','default_timezone','America/Sao_Paulo');
essentialCheck($page['native_calendar_config']['calendar_ids']===['a-calendar@example.test'],'calendar-only member sees own department source, corrupted cross-issuer membership is ignored');
essentialCheck($page['native_calendar_config']['options']['timezone']==='America/Sao_Paulo'&&$page['native_calendar_config']['options']['date_format']==='Y-m-d','native FullCalendar gets actual scoped timezone and format');
echo "Calendar page adapter: 2 additional actual storage checks passed.\n";
$db->query('INSERT INTO '.$p.'company_departments VALUES(3,?,?)',[$a,'other-department@example.test']);$departments=new NativeCompanyDepartments($db,$p);
essentialCheck($departments->calendarSources($a,49)===['a-calendar@example.test'],'calendar grant alone does not broaden department assignment');
essentialCheck(count($departments->calendarSources($a,7))===2,'installation administrator sees all calendars of selected issuer only');
$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=? AND staff_id=49 AND feature='calendar'",[$a]);essentialThrows(fn()=>$departments->calendarSources($a,49),'native_department_denied','revoked calendar grant denies department source lookup');
essentialThrows(fn()=>NativeCompanyCalendarEndpoints::confirm($ci,$page),'company_calendar_denied','permission revoked after rendering prevents buffered calendar/modal delivery');
essentialThrows(fn()=>NativeCompanyCalendarEndpoints::confirm($ci,$preparedEvent,$event['eventid']),'company_calendar_denied','permission revoked after event rendering prevents private modal delivery');
echo "Calendar department sources: 3 additional actual authority checks passed.\n";
// Principal retains historical departments under the same page adapter. Calendar
// permission alone never grants another department or a secondary company's key.
$db->query('CREATE TABLE '.$p.'departments(departmentid INT PRIMARY KEY,calendar_id TEXT)');
$db->query('CREATE TABLE '.$p.'staff_departments(departmentid INT,staffid INT)');
$db->query("INSERT INTO {$p}departments VALUES(11,'principal-own@example.test'),(12,'principal-other@example.test')");
$db->query('INSERT INTO '.$p.'staff_departments VALUES(11,49)');
$db->query('INSERT INTO '.$p.'company_department_members VALUES(4,?,49,?,12)',[$b,'ticket_department']);
CompanyRequestScope::$issuer=$calendarPrimary;
foreach(['google_api_key'=>'principal-browser-key','google_calendar_main_calendar'=>'principal-main@example.test','dateformat'=>'Y-m-d|%Y-%m-%d','time_format'=>'24','default_timezone'=>'America/Bahia','active_language'=>'portuguese_br']as$key=>$value){
 if($db->query('SELECT name FROM '.$p.'options WHERE name=?',[$key])->row_array())$db->query('UPDATE '.$p.'options SET value=? WHERE name=?',[$value,$key]);else$db->query('INSERT INTO '.$p.'options(name,value) VALUES(?,?)',[$key,$value]);
}
$principalPage=NativeCompanyCalendarEndpoints::page($ci);NativeCompanyCalendarEndpoints::confirm($ci,$principalPage);
essentialCheck($principalPage['native_calendar_config']['calendar_ids']===['principal-own@example.test','principal-main@example.test'],'principal shared page retains assigned historical department and own main calendar only');
essentialCheck($principalPage['native_calendar_config']['options']['google_api']==='principal-browser-key','principal browser key is explicit and never the secondary key');
essentialCheck($departments->calendarSources($calendarPrimary,7)===['principal-own@example.test','principal-other@example.test'],'proven installation administrator sees principal historical department calendars');
CompanyRequestScope::$issuer=$b;essentialCheck(!str_contains(json_encode(NativeCompanyCalendarEndpoints::page($ci)),'principal-browser-key'),'secondary page never inherits principal key or historical departments');
echo "Calendar principal page: shared adapter, historical assignment, administrator, A/B configuration and final reauthorization passed.\n";
