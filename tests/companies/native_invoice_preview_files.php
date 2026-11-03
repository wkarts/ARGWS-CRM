<?php
require __DIR__.'/native_invoice_file_controller.php';
require_once APPPATH.'services/NativeCompanyInvoicePreviewData.php';
$fixture=$files->addExternal($a,7,$main,hash('sha256','preview-file'),'Synthetic preview attachment','dropbox','https://www.dropbox.com/s/synthetic/preview.pdf');
$preview=new NativeCompanyInvoicePreviewData($db,$p);$items=$preview->attachments($a,7,$main);
essentialCheck(count($items)===1&&(int)$items[0]['id']===(int)$fixture['id']&&$items[0]['native_file_source']==='invoice'&&(int)$items[0]['revision']===1,'preview exposes original scoped file ID, real revision and action metadata');
essentialThrows(fn()=>$preview->attachments($b,7,$main),'company_invoice_access_denied','foreign issuer cannot list invoice files by direct URL');
$files->remove($a,7,$fixture['id'],1);essentialCheck($preview->attachments($a,7,$main)===[],'existing authorized delete action removes file from preview');
echo 'Native invoice preview files: exact native ID, company scope and real deletion passed; route remains closed.'.PHP_EOL;
