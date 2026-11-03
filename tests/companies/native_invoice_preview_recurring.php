<?php
require __DIR__.'/native_invoice_rows.php';
require_once APPPATH.'services/NativeCompanyInvoicePreviewData.php';
$preview=new NativeCompanyInvoicePreviewData($db,$p);$parent=$preview->recurring($a,7,$main);
essentialCheck(count(array_filter($parent,fn($row)=>$row['schedule_id']===(int)$sid&&$row['principal']===true))===1,'principal invoice projects its own durable recurring schedule');
$child=$preview->recurring($a,7,$sendInvoice);
essentialCheck(count(array_filter($child,fn($row)=>$row['schedule_id']===(int)$sendMode&&$row['principal']===false))===1,'issued occurrence resolves its own schedule');
essentialThrows(fn()=>$preview->recurring($b,7,$main),'company_invoice_access_denied','another company cannot read recurring invoice sources');
echo 'Native invoice recurring preview: principal, issued occurrence and company scope verified.'.PHP_EOL;
