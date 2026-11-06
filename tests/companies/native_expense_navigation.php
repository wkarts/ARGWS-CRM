<?php
require __DIR__.'/expense_billing_controller.php';
$start=$GLOBALS['essentialChecks'];CompanyRequestScope::$sealed=true;CompanyRequestScope::$actor=7;CompanyRequestScope::$issuer=$a;
$canonical=NativeCompanyResourceId::encode('company_expense',$guiExpense);
$resetBilling();$controllerBilling->index($canonical);$view=end($GLOBALS['CI']->load->views);
essentialCheck($outBilling->status===200&&(int)$view[1]['detail']['id']===$guiExpense,'Original expenses/index/{id} resolves its actual canonical detail');
$resetBilling();$controllerBilling->list_expenses($canonical);$view=end($GLOBALS['CI']->load->views);
essentialCheck($outBilling->status===200&&(int)$view[1]['detail']['id']===$guiExpense,'Original expenses/list_expenses/{id} preserves detail navigation');
$resetBilling(['expense'=>NativeCompanyResourceId::encode('company_expense',$eb)]);$controllerBilling->index($canonical);
essentialCheck($outBilling->status===403&&$GLOBALS['CI']->load->views===[],'Conflicting route and query identities are rejected before rendering');
CompanyRequestScope::$issuer=$b;$resetBilling();$controllerBilling->list_expenses($canonical);
essentialCheck($outBilling->status===404&&$GLOBALS['CI']->load->views===[],'Known expense detail path does not cross the selected company');
CompanyRequestScope::$issuer=$a;$resetBilling();$controllerBilling->index((string)$guiExpense);
essentialCheck($outBilling->status===403,'Raw historical identity is never treated as owned by a numeric collision');
echo 'Expense original detail navigation: '.($GLOBALS['essentialChecks']-$start).' added controller/database checks passed ('.$db->driver().').'.PHP_EOL;
