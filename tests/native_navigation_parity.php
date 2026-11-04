<?php
declare(strict_types=1);
require __DIR__.'/../application/services/NativeCompanyRoutes.php';
require __DIR__.'/../application/services/NativeSettingsCompatibility.php';
require __DIR__.'/../application/services/NativeInstallationModuleMenu.php';
function parity(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
foreach(['paymentmodes','roles','knowledge_base','spam_filters/view/tickets','gdpr','emails','misc/reminders','expenses/categories','contracts/types']as$route)parity(NativeCompanyRoutes::navigation($route),'missing navigation '.$route);
foreach(['paymentmodes'=>'manage','roles'=>'delete','knowledge_base'=>'delete_article','gdpr'=>'save','misc'=>'edit_reminder']as$controller=>$action){
 parity(NativeCompanyRoutes::admits('admin','',$controller,$action,'POST'),'scoped action '.$controller.'/'.$action);
 parity(!NativeCompanyRoutes::admits('admin','',$controller,$action,'GET'),'GET must not mutate '.$controller.'/'.$action);
}
foreach(NativeSettingsCompatibility::PRIMARY_GROUPS as $group){parity(NativeSettingsCompatibility::usesInstallationForm(true,$group),'primary setting '.$group);parity(!NativeSettingsCompatibility::usesInstallationForm(false,$group),'secondary global settings leak '.$group);}
foreach(['settings?group=cronjob','../settings','paymentmodes#x','%67dpr','https://example.test']as$route)parity(!NativeCompanyRoutes::navigation($route),'untrusted destination '.$route);
$sections=['other'=>['children'=>[]],'ai'=>['children'=>[]],'finance'=>['children'=>[]]];
$sections=NativeSettingsCompatibility::appendInstallationSections($sections,['openai','products']);
parity(array_column($sections['other']['children'],'id')===['sms','products'],'primary SMS and active product settings retained');
parity(array_column($sections['ai']['children'],'id')===['openai']&&$sections['finance']['children']===[],'inactive module settings stay absent');
parity(NativeSettingsCompatibility::appendInstallationSections($sections,['openai','products'])===$sections,'settings directory is not duplicated');
class CompanyRequestScope{static bool $primary=false;static function context(){return new class{function isPrimary(){return CompanyRequestScope::$primary;}function issuerId(){return 7;}function staffId(){return 10;}};}static function adminUrl($ci,$route){return '/admin/'.$route;}}
class IssuerResourceAccess{static bool $admin=true;function __construct(...$args){}function administrative(...$args){return self::$admin;}}
class IssuerStorage{static int $queries=0;static function query($db,$sql){self::$queries++;parity($sql==='SELECT module_name FROM tblmodules WHERE active=1','must not hydrate global options/hooks');return new class{function result_array(){return array_map(fn($m)=>['module_name'=>$m],['purchase','hr_payroll','menu_setup','connect_api_connector','connect_api_chat']);}};}}
function db_prefix(){return 'tbl';}function base_url($route){return '/'.$route;}
$ci=(object)['db'=>(object)[]];$menus=['sidebar'=>[],'setup'=>[]];$children=[];
NativeInstallationModuleMenu::append($ci,$menus,$children);parity(IssuerStorage::$queries===0&&$menus['sidebar']===[],'secondary cannot inherit installation modules');
CompanyRequestScope::$primary=true;IssuerResourceAccess::$admin=false;
NativeInstallationModuleMenu::append($ci,$menus,$children);parity(IssuerStorage::$queries===0,'nonadmin cannot hydrate installation menus');
IssuerResourceAccess::$admin=true;NativeInstallationModuleMenu::append($ci,$menus,$children);
parity(isset($menus['sidebar']['purchase'],$menus['sidebar']['hr_payroll'],$menus['setup']['menu-options']),'active modules restored');
parity(!isset($menus['sidebar']['fleet']),'inactive module stays absent');
parity(count($children['sidebar']['connect-api'])>=2,'shared module parent retains sibling integrations');
$purchase=array_column($children['sidebar']['purchase'],'href');parity(in_array('/admin/purchase/vendors',$purchase,true),'original purchase vendor destination retained');
echo "Navigation parity: restored routes, POST guards, primary/secondary settings, active modules and installation ACL passed.\n";
