<?php
/** Original controller, actual company settings and PDO; framework request is a double. */
require __DIR__.'/native_invoice_totals_controller.php';
$start=$GLOBALS['essentialChecks'];
$settings=new CompanySettingsStorage($db,$p);
$settings->mutate($a,7,'update','dateformat','Y-m-d|%Y-%m-%d');
$settings->mutate($b,7,'update','dateformat','Y-m-d|%Y-%m-%d');
$settings->mutate($a,7,'update','invoice_due_after','5');
$settings->mutate($b,7,'update','invoice_due_after','10');
$ci->input->verb='POST';$ci->input->posts=['date'=>'2026-01-15'];
CompanyRequestScope::$issuer=$a;$ci->output->status=200;$controller->get_due_date();
essentialCheck($ci->output->status===200&&$ci->output->body==='2026-01-20','original invoice date action uses A settings');
CompanyRequestScope::$issuer=$b;$ci->output->status=200;$controller->get_due_date();
essentialCheck($ci->output->status===200&&$ci->output->body==='2026-01-25','same original action uses B settings after switch');
$ci->input->posts=['date'=>'2026-02-30'];$ci->output->status=200;$controller->get_due_date();
essentialCheck($ci->output->status===422,'invalid date is rejected by the original action');
$ci->input->posts=['date'=>'2026-01-15'];
foreach(['csrf','contextValid'] as $flag){CompanyRequestScope::${$flag}=false;$ci->output->status=200;$controller->get_due_date();essentialCheck($ci->output->status===403,'invoice date action requires '.$flag);CompanyRequestScope::${$flag}=true;}
$ci->input->verb='GET';$ci->output->status=200;$controller->get_due_date();
essentialCheck($ci->output->status===403,'read verb cannot access the mutation-guarded action');
echo 'Original invoice due-date action: '.($GLOBALS['essentialChecks']-$start).' checks; middleware/browser remain separate.'.PHP_EOL;
