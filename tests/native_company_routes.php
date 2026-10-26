<?php
/** Pure routing contract; no database, session, transport or application bootstrap. */
require dirname(__DIR__) . '/application/services/NativeCompanyRoutes.php';
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) { throw new RuntimeException($message); }
    ++$checks;
};
foreach (['GET', 'HEAD'] as $verb) {
    $check(NativeCompanyRoutes::admits('admin/', '', 'Dashboard', 'index', $verb), 'native dashboard read admitted');
}
$check(NativeCompanyRoutes::admits('admin/','','issuers','index','GET'),'original company registry page admitted through its issuer-scoped reader');
$check(NativeCompanyRoutes::admits('admin/','','issuers','index','HEAD'),'original company registry supports HEAD');
$check(NativeCompanyRoutes::admits('admin/','','issuers','save','POST'),'original company registry writer admitted through its membership and CSRF checks');
foreach(['POST','PUT','PATCH','DELETE'] as $verb)$check(!NativeCompanyRoutes::admits('admin/','','issuers','index',$verb),'company registry reads never mutate');
foreach(['GET','HEAD','PUT','PATCH','DELETE'] as $verb)$check(!NativeCompanyRoutes::admits('admin/','','issuers','save',$verb),'company registry mutations require POST');
foreach(['GET','HEAD','POST'] as $verb)$check(NativeCompanyRoutes::admits('admin/','','utilities','calendar',$verb),'original Utilities calendar is connected to its scoped page or writer');
foreach(['GET','HEAD','POST','PUT','PATCH','DELETE','OPTIONS'] as $verb)$check(NativeCompanyRoutes::admits('admin/','','utilities','get_calendar_data',$verb)===($verb==='GET'),'native calendar feed is read-only GET');
foreach(['GET','HEAD','POST','PUT','PATCH','DELETE','OPTIONS'] as $verb)$check(NativeCompanyRoutes::admits('admin/','','utilities','view_event',$verb)===in_array($verb,['GET','HEAD'],true),'native calendar event detail is read-only');
foreach(['GET','HEAD','POST','PUT','PATCH','DELETE','OPTIONS'] as $verb)$check(NativeCompanyRoutes::admits('admin/','','utilities','delete_event',$verb)===($verb==='POST'),'native calendar event deletion requires POST and native CSRF');
foreach (['GET','HEAD','POST'] as $verb) {
    $check(NativeCompanyRoutes::admits('admin/','','settings','index',$verb),'existing Settings route admitted only for its explicit native adapter');
}
foreach (['GET','HEAD','POST'] as $verb) {
    $check(NativeCompanyRoutes::admits('admin/','','departments','index',$verb),'original native Departments list and DataTables methods admitted');
}
foreach (['department','delete','email_exists','folders','test_imap_connection'] as $method) {
    $check(NativeCompanyRoutes::admits('admin/','','departments',$method,'POST'),'native Departments endpoint uses its original POST route: '.$method);
    foreach (['GET','HEAD','PUT','PATCH','DELETE','OPTIONS'] as $verb) {
        $check(!NativeCompanyRoutes::admits('admin/','','departments',$method,$verb),'native Departments endpoint denies alternate verb: '.$method.'/'.$verb);
    }
}
foreach (['GET','HEAD','PUT','PATCH','DELETE','OPTIONS'] as $verb) {
    $check(!NativeCompanyRoutes::admits('admin/','','emails','sent_smtp_test_email',$verb),'native SMTP test never runs without explicit POST');
}
$check(NativeCompanyRoutes::admits('admin/','','emails','sent_smtp_test_email','POST'),'native SMTP test endpoint is connected to its scoped writer');
$check(NativeCompanyRoutes::admits('admin/','','misc','dismiss_announcement','POST'),'native announcement dismissal uses the original scoped POST action');
$check(!NativeCompanyRoutes::admits('admin/','','misc','dismiss_announcement','GET'),'native announcement dismissal never mutates through GET');
foreach (['delete_queued_email','clear_queued_emails'] as $method) {
    $check(NativeCompanyRoutes::admits('admin/','','emails',$method,'POST'),'native queue deletion uses original POST route');
    $check(!NativeCompanyRoutes::admits('admin/','','emails',$method,'GET'),'native queue deletion never uses GET');
}
foreach (['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $verb) {
    $check(!NativeCompanyRoutes::admits('admin/', '', 'dashboard', 'index', $verb), 'unimplemented mutation denied');
}
foreach ([['admin', 'third_party', 'dashboard', 'index'], ['public', '', 'dashboard', 'index'],
    ['admin', '', 'dashboard', 'unknown'], ['admin', '', 'invoices', 'index'],
    ['admin', '', 'company_context', 'index'], ['admin', '', 'authentication', 'index']] as $route) {
    $check(!NativeCompanyRoutes::admits(...array_merge($route, ['GET'])), 'unadapted or non-native route denied');
}
foreach (['', 'dashboard', 'dashboard/index','issuers','settings','departments','utilities/calendar'] as $route) {
    $check(NativeCompanyRoutes::navigation($route), 'canonical native navigation accepted');
}
foreach (['/dashboard', '//evil.test', 'https://evil.test', '../dashboard', 'dashboard/../invoices',
    'dashboard?issuer_id=2', 'dashboard#fragment', 'dashboard%2findex', "dashboard\0", "dashboard\n", 'dashboard\\index',
    'company_context', 'company_issuers', 'company_invoices', 'dashboard/ticket_widget', 'invoices/15'] as $route) {
    $check(!NativeCompanyRoutes::navigation($route), 'return URL cannot expand native route authority');
}
echo "Native company route contracts: {$checks} checks passed.\n";

$mutations=['todo'=>['todo','change_todo_status','update_todo_items_order','delete_todo_item'],'announcements'=>['delete'],'taxes'=>['manage','delete'],'tickets'=>['bulk_action'],'invoice_items'=>['manage','add_group','update_group','delete_group','delete','bulk_action','copy']];
foreach($mutations as$controller=>$methods)foreach($methods as$method){$check(NativeCompanyRoutes::admits('admin','',$controller,$method,'POST'),'Explicit owned writer admitted');foreach(['GET','HEAD','PUT','DELETE']as$verb)$check(!NativeCompanyRoutes::admits('admin','',$controller,$method,$verb),'Mutation never admitted through read/foreign verb');}
foreach(['todo','announcements','taxes','tickets','invoice_items']as$controller){$check(NativeCompanyRoutes::admits('admin','',$controller,'index','GET'),'Original catalog page admitted');$check(NativeCompanyRoutes::navigation($controller),'Original navigation target admitted');$check(!NativeCompanyRoutes::admits('admin','module',$controller,'index','GET'),'Module cannot inherit native admission');$check(!NativeCompanyRoutes::admits('admin','',$controller,'unknown','POST'),'No wildcard actions');}
echo "Native finite auxiliary admission: {$checks} total route assertions PASS; authenticated HTTP remains required.\n";

foreach(['admin/','../../modules/banco_brasil/controllers/']as$directory){
 $check(NativeCompanyRoutes::admits($directory,'banco_brasil','banco_brasil','index','GET'),'BB shares native presentation in both MX directory forms');
 foreach(['register_connection','generate_remittance','save_channel_preferences','issue_api','preview_checkout','publish_checkout']as$method){
  $check(NativeCompanyRoutes::admits($directory,'banco_brasil','banco_brasil',$method,'POST'),'explicit BB writer uses native company CSRF');
  $check(!NativeCompanyRoutes::admits($directory,'banco_brasil','banco_brasil',$method,'GET'),'BB writer cannot run through GET');
 }
 $check(!NativeCompanyRoutes::admits($directory,'banco_brasil','banco_brasil','unknown','POST'),'BB has no wildcard module action');
}
$check(NativeCompanyRoutes::navigation('banco_brasil'),'single native module navigation');
echo "BB native finite admission: {$checks} total assertions passed.\n";

$check(NativeCompanyRoutes::navigation('staff'),'native original Staff navigation is available');
foreach(['index','member']as$method)foreach(['GET','HEAD','POST']as$verb)$check(NativeCompanyRoutes::admits('admin','','staff',$method,$verb),'native staff table/form finite admission');
foreach(['change_staff_status','save_hidden_table_columns','remove_staff_profile_image']as$method){$check(NativeCompanyRoutes::admits('admin','','staff',$method,'POST'),'scoped staff action uses POST');$check(!NativeCompanyRoutes::admits('admin','','staff',$method,'GET'),'staff mutation refuses GET');}
$check(!NativeCompanyRoutes::admits('admin','','staff','delete','POST'),'global staff deletion is not admitted to a named company');
$check(NativeCompanyRoutes::admits('admin','','misc','staff_email_exists','POST'),'native member form can validate email under its current context');
echo "Native Staff admission: {$checks} total assertions passed.\n";

foreach(['add_note','edit_note','delete_note']as$method){$check(NativeCompanyRoutes::admits('admin','','misc',$method,'POST'),'scoped Staff notes have native mutation admission');$check(!NativeCompanyRoutes::admits('admin','','misc',$method,'GET'),'notes never mutate by GET');}
$check(NativeCompanyRoutes::admits('admin','','tasks','timer_tracking','POST'),'own scoped timer action is available to Staff report');
$check(!NativeCompanyRoutes::admits('admin','','tasks','delete_timesheet','GET'),'legacy timesheet deletion remains denied');
