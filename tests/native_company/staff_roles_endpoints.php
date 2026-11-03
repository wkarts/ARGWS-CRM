<?php
require __DIR__.'/staff_roles.php';require_once APPPATH.'services/NativeCompanyRolesEndpoints.php';
function _l($key,...$args){return$key;}function admin_url($path=''){return'https://example.test/crm/admin/'.$path;}function e($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function get_available_staff_permissions(){throw new RuntimeException('global permissions must not hydrate');}function staff_can(){throw new RuntimeException('global authority must not run');}function has_role_permission(){throw new RuntimeException('global roles must not hydrate');}function is_admin(){throw new RuntimeException('global admin must not run');}
final class CompanyRequestScope{public static int $issuer=41;public static bool $valid=true;public static function nativeFor($ci){return true;}public static function context(){return new class{function issuerId(){return CompanyRequestScope::$issuer;}function staffId(){return 7;}};}public static function nativeCsrfToken($ci){return'synthetic';}public static function assertNativeMutation($ci){if(!$ci->input->postAllowed||!self::$valid)throw new DomainException('native_mutation_denied');}}
$input=new class{public array$data=[];public bool$postAllowed=true;function post($key=null){return$key===null?$this->data:($this->data[$key]??null);}function get(){return[];}};$ci=(object)['db'=>$db,'input'=>$input];
$page=NativeCompanyRolesEndpoints::page($ci);$input->data=['name'=>'Native original role','permissions'=>['tasks'=>['view']],'revision'=>$page['native_staff_roles_revision']];$wire=NativeCompanyRolesEndpoints::save($ci);$page=NativeCompanyRolesEndpoints::page($ci,$wire);
essentialCheck(NativeCompanyResourceId::decode('staff_role',$wire)['source_kind']==='company_staff_role','native endpoint returns lossless role identity');
$render=function(array$data){extract($data);ob_start();require APPPATH.'views/admin/staff/permissions.php';return ob_get_clean();};$html=$render($page);
essentialCheck(str_contains($html,'name="permissions[tasks][]"')&&str_contains($html,'checked'),'same original permission partial renders scoped grants without global lookup');
CompanyRequestScope::$issuer=82;settingThrows(fn()=>NativeCompanyRolesEndpoints::page($ci,$wire),'native_staff_role_denied','matching local role ID cannot cross issuer');CompanyRequestScope::$issuer=41;
$input->data=['revision'=>$page['native_staff_roles_revision']];CompanyRequestScope::$valid=false;settingThrows(fn()=>NativeCompanyRolesEndpoints::delete($ci,$wire),'native_mutation_denied','CSRF denied before storage');CompanyRequestScope::$valid=true;$input->postAllowed=false;settingThrows(fn()=>NativeCompanyRolesEndpoints::delete($ci,$wire),'native_mutation_denied','GET delete denied');$input->postAllowed=true;
NativeCompanyRolesEndpoints::delete($ci,$wire);essentialCheck($s->page(41,7)['roles']===[],'native deletion succeeds with revisioned POST');
echo "Native original roles endpoint/permission partial: six real SQLite checks, request-boundary double.\n";

foreach(['Zulu','Alpha']as$name){$page=NativeCompanyRolesEndpoints::page($ci);$input->data=['name'=>$name,'permissions'=>[],'revision'=>$page['native_staff_roles_revision']];NativeCompanyRolesEndpoints::save($ci);}
$input->data=['draw'=>'2','length'=>'1','order'=>[['column'=>'0','dir'=>'desc']]];$table=NativeCompanyRolesEndpoints::table($ci);
essentialCheck($table['aaData'][0][0]==='Zulu'&&$table['iTotalRecords']===2,'original role table honors sorting and bounded pagination');
essentialCheck(str_contains($table['aaData'][0][1],'onsubmit="return confirm_delete();"')&&str_contains($table['aaData'][0][1],'method="post"'),'role deletion retains native confirmation with POST');
$input->data=['order'=>[['column'=>'1','dir'=>'desc']]];settingThrows(fn()=>NativeCompanyRolesEndpoints::table($ci),'native_staff_role_invalid','non-sortable options column rejected');
