<?php
require dirname(__DIR__).'/companies/invoice_attachments.php';
$key=hash('sha256','native-files-owned');
essentialThrows(fn()=>$files->uploadInOwned($a,7,$ia,$key,'owned.pdf',$pdf),'issuer_owned_transaction_required','owned upload cannot borrow arbitrary caller transaction');
essentialThrows(fn()=>$files->downloadInOwned($a,7,$ia,$fa),'issuer_owned_transaction_required','owned download requires explicit boundary');
essentialThrows(fn()=>$files->hideInOwned($a,7,$ia,$fa,hash('sha256',$pdf)),'issuer_owned_transaction_required','owned hide requires explicit boundary');
essentialThrows(fn()=>IssuerTransaction::run($db,$p,CompanyInvoiceAttachments::tableNames(),function()use($files,$a,$ia,$key,$pdf){$files->uploadInOwned($a,7,$ia,$key,'owned.pdf',$pdf);throw new DomainException('projection_failed');}),'projection_failed','projection failure rolls back content in same transaction');
essentialCheck(!$db->query("SELECT file_key FROM {$p}company_invoice_attachments WHERE file_key=?",[$key])->row_array(),'no orphan blob after projection rollback');
IssuerTransaction::run($db,$p,CompanyInvoiceAttachments::tableNames(),fn()=>$files->uploadInOwned($a,7,$ia,$key,'owned.pdf',$pdf));
$row=IssuerTransaction::run($db,$p,CompanyInvoiceAttachments::tableNames(),fn()=>$files->downloadInOwned($a,7,$ia,$key));
essentialCheck($row['content']===$pdf,'owned read returns verified original bytes');
if($db->driver()==='sqlite'){
    $files->download($a,7,$ia,$key); // prior audit exists, potentially same second
    $db->query("CREATE TRIGGER native_attachment_ignore_audit BEFORE INSERT ON {$p}issuers_audit BEGIN SELECT RAISE(IGNORE); END");
    essentialThrows(fn()=>$files->download($a,7,$ia,$key),'issuer_invoice_attachment_unconfirmed','ignored identical audit cannot reuse previous receipt');
    $db->query('DROP TRIGGER native_attachment_ignore_audit');
}
echo "Native attachment owned transaction and audit proofs PASS\n";
