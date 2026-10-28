<?php
require __DIR__.'/../native_company/primary_notes_reminders.php';
require_once APPPATH.'services/NativeCompanyInvoicePreviewData.php';
$preview=new NativeCompanyInvoicePreviewData($db,$p);
essentialCheck($preview->reminderBadge($principal,7,42)===2,'original badge counts own pending legacy and company reminders for the authorized invoice');
essentialCheck(count($preview->notes($principal,7,42))===2,'original notes projection preserves owned and historical lineage');
settingThrows(fn()=>$preview->reminderBadge(41,12,42),'company_invoice_access_denied','named company cannot read principal reminder badge by direct ID');
settingThrows(fn()=>$preview->notes(41,12,42),'company_invoice_access_denied','named company cannot read principal invoice notes');
echo 'Native invoice reminder badge: actor and invoice ownership verified; existing revision-bound reminder actions retained.'.PHP_EOL;
