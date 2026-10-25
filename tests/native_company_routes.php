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
foreach (['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $verb) {
    $check(!NativeCompanyRoutes::admits('admin/', '', 'dashboard', 'index', $verb), 'unimplemented mutation denied');
}
foreach ([['admin', 'third_party', 'dashboard', 'index'], ['public', '', 'dashboard', 'index'],
    ['admin', '', 'dashboard', 'unknown'], ['admin', '', 'invoices', 'index'],
    ['admin', '', 'company_context', 'index'], ['admin', '', 'authentication', 'index']] as $route) {
    $check(!NativeCompanyRoutes::admits(...array_merge($route, ['GET'])), 'unadapted or non-native route denied');
}
foreach (['', 'dashboard', 'dashboard/index'] as $route) {
    $check(NativeCompanyRoutes::navigation($route), 'canonical native navigation accepted');
}
foreach (['/dashboard', '//evil.test', 'https://evil.test', '../dashboard', 'dashboard/../invoices',
    'dashboard?issuer_id=2', 'dashboard#fragment', 'dashboard%2findex', "dashboard\0", "dashboard\n", 'dashboard\\index',
    'company_context', 'company_invoices', 'dashboard/ticket_widget', 'invoices/15'] as $route) {
    $check(!NativeCompanyRoutes::navigation($route), 'return URL cannot expand native route authority');
}
echo "Native company route contracts: {$checks} checks passed.\n";
