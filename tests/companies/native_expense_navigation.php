<?php
require __DIR__.'/native_expense_index_http_fixture.php';
$start=$GLOBALS['essentialChecks'];CompanyRequestScope::$sealed=true;CompanyRequestScope::$actor=7;CompanyRequestScope::$issuer=$a;
$canonical=$saved['expenseid'];
foreach(['index','list_expenses']as$route){
    $reset();$controller->$route($canonical);
    essentialCheck($out->status===200&&$dom($out->body)->query('//*[@name="expenseid" and @value="'.$canonical.'"]')->length===1,'Original expenses/'.$route.'/{id} renders its validated canonical preview identity in the actual native DOM');
}
$reset('GET',[],['expense'=>NativeCompanyResourceId::encode('company_expense',$bExpense)]);$controller->index($canonical);
essentialCheck($out->status===403&&$loader->views===[],'Conflicting route and query identities are rejected before rendering');
CompanyRequestScope::$issuer=$b;$reset();$controller->list_expenses($canonical);
essentialCheck($out->status===403&&$loader->views===[],'Known expense detail path does not cross the selected company');
CompanyRequestScope::$issuer=$a;$reset();$controller->index((string)$saved['source_id']);
essentialCheck($out->status===403&&$loader->views===[],'Raw historical identity is never treated as owned by a numeric collision');
echo 'Expense original detail navigation: '.($GLOBALS['essentialChecks']-$start).' added controller/database checks passed ('.$db->driver().').'.PHP_EOL;
