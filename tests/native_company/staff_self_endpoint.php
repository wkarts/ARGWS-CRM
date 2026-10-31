<?php
require __DIR__.'/staff_self.php';require_once APPPATH.'services/NativeCompanyStaffEndpoints.php';
if(!class_exists('CompanyRequestScope',false)){class CompanyRequestScope{public static bool $csrf=true;public static int $actor=12;public static function nativeFor($ci){return true;}public static function assertNativeMutation($ci){if(!$ci->input->isPost||!self::$csrf)throw new DomainException('native_staff_csrf_denied');}public static function context(){return new class{public function issuerId(){return 41;}public function staffId(){return CompanyRequestScope::$actor;}};}}}
if(!function_exists('get_staff_user_id')){function get_staff_user_id(){return $GLOBALS['selfSessionActor'];}}
$GLOBALS['selfSessionActor']=12;$db->query('UPDATE '.$p.'issuers_staff SET can_view=1 WHERE issuer_id=41 AND staff_id=12');
$ci=(object)['db'=>$db,'input'=>new class{public bool $isPost=true;public array $data=[];public function post($key=null,$clean=false){return $key===null?$this->data:($this->data[$key]??null);}}];
$ci->input->data=['oldpassword'=>'synthetic-new-password','newpassword'=>'synthetic-third-password','newpasswordr'=>'synthetic-third-password','native_staff_self_revision'=>$self->page(41,12)['revision']];
$GLOBALS['selfSessionActor']=7;settingThrows(fn()=>NativeCompanyStaffEndpoints::ownPassword($ci),'issuer_staff_denied','context actor cannot replace actual authenticated session identity');$GLOBALS['selfSessionActor']=12;
CompanyRequestScope::$csrf=false;settingThrows(fn()=>NativeCompanyStaffEndpoints::ownPassword($ci),'native_staff_csrf_denied','personal password endpoint rejects CSRF before service');CompanyRequestScope::$csrf=true;$ci->input->isPost=false;settingThrows(fn()=>NativeCompanyStaffEndpoints::ownPassword($ci),'native_staff_csrf_denied','personal password endpoint is not a GET mutator');$ci->input->isPost=true;
$revision=NativeCompanyStaffEndpoints::ownPassword($ci);essentialCheck($revision===$self->page(41,12)['revision'],'original personal password fields execute real service with session actor');
echo "Personal password endpoint: four real-service checks, framework boundary doubles.\n";
if(!$db->table_exists($p.'company_departments'))$db->query('CREATE TABLE '.$p.'company_departments(id INT,issuer_id INT,name TEXT)');
if(!$db->table_exists($p.'company_department_members'))$db->query('CREATE TABLE '.$p.'company_department_members(id INT,issuer_id INT,staff_id INT,department_source_kind TEXT,department_source_id INT)');
if(!$db->table_exists($p.'emailtemplates'))$db->query('CREATE TABLE '.$p.'emailtemplates(emailtemplateid INT,issuer_id INT NULL,slug TEXT,active INT)');
$db->query('INSERT INTO '.$p.'company_departments VALUES(100,41,?)',['Own department']);$db->query('INSERT INTO '.$p.'company_department_members VALUES(100,41,12,?,100)',['company_ticket_department']);
$page=NativeCompanyStaffEndpoints::ownPage($ci);essentialCheck($page['member']->staffid===12&&$page['staff_departments'][0]['name']==='Own department'&&$page['native_staff_languages']===['portuguese_br'],'personal page reads only identity and own department names');
$ci->input->data=['native_staff_self_revision'=>$page['native_staff_self_revision'],'firstname'=>'Personal native form'];$next=NativeCompanyStaffEndpoints::ownSave($ci);essentialCheck($next===$self->page(41,12)['revision'],'original own profile parser commits safe native fields');
$db->query('UPDATE '.$p.'company_departments SET issuer_id=82 WHERE id=100');settingThrows(fn()=>NativeCompanyStaffEndpoints::ownPage($ci),'issuer_staff_denied','corrupt cross-company department reference is not silently hidden');
echo "Personal profile page/save: three actual service/SQLite checks.\n";
