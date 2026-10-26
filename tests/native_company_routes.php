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
foreach (['GET','HEAD','POST'] as $verb) {
    $check(NativeCompanyRoutes::admits('admin/','','settings','index',$verb),'existing Settings route admitted only for its explicit native adapter');
}
foreach (['GET','HEAD','PUT','PATCH','DELETE','OPTIONS'] as $verb) {
    $check(!NativeCompanyRoutes::admits('admin/','','emails','sent_smtp_test_email',$verb),'native SMTP test never runs without explicit POST');
}
$check(NativeCompanyRoutes::admits('admin/','','emails','sent_smtp_test_email','POST'),'native SMTP test endpoint is connected to its scoped writer');
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
foreach (['', 'dashboard', 'dashboard/index','settings'] as $route) {
    $check(NativeCompanyRoutes::navigation($route), 'canonical native navigation accepted');
}
foreach (['/dashboard', '//evil.test', 'https://evil.test', '../dashboard', 'dashboard/../invoices',
    'dashboard?issuer_id=2', 'dashboard#fragment', 'dashboard%2findex', "dashboard\0", "dashboard\n", 'dashboard\\index',
    'company_context', 'company_invoices', 'dashboard/ticket_widget', 'invoices/15'] as $route) {
    $check(!NativeCompanyRoutes::navigation($route), 'return URL cannot expand native route authority');
}
echo "Native company route contracts: {$checks} checks passed.\n";

$mutations=['todo'=>['todo','change_todo_status','update_todo_items_order','delete_todo_item'],'announcements'=>['delete'],'taxes'=>['manage','delete'],'tickets'=>['bulk_action'],'invoice_items'=>['manage','add_group','update_group','delete_group','delete','bulk_action','copy']];
foreach($mutations as$controller=>$methods)foreach($methods as$method){$check(NativeCompanyRoutes::admits('admin','',$controller,$method,'POST'),'Explicit owned writer admitted');foreach(['GET','HEAD','PUT','DELETE']as$verb)$check(!NativeCompanyRoutes::admits('admin','',$controller,$method,$verb),'Mutation never admitted through read/foreign verb');}
foreach(['todo','announcements','taxes','tickets','invoice_items']as$controller){$check(NativeCompanyRoutes::admits('admin','',$controller,'index','GET'),'Original catalog page admitted');$check(NativeCompanyRoutes::navigation($controller),'Original navigation target admitted');$check(!NativeCompanyRoutes::admits('admin','module',$controller,'index','GET'),'Module cannot inherit native admission');$check(!NativeCompanyRoutes::admits('admin','',$controller,'unknown','POST'),'No wildcard actions');}
echo "Native finite auxiliary admission: {$checks} total route assertions PASS; authenticated HTTP remains required.\n";
