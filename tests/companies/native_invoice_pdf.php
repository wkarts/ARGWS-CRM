<?php
/** Real scoped services + TCPDF on the existing disposable A/B/native-NULL fixture. */
require __DIR__.'/invoices.php';
require_once APPPATH.'services/NativeCompanyInvoicePdf.php';
$pdfTransport = new NativeCompanyInvoicePdf($db, $p);
$cashBefore = $db->query('SELECT * FROM '.$p.'invoicepaymentrecords ORDER BY id')->result_array();
$invoicesBefore = $db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array();
$auditCount = (int)$db->query('SELECT COUNT(*) n FROM '.$p.'issuers_audit')->row()->n;
$head = $pdfTransport->response($a, 7, $branded, 'HEAD', []);
essentialCheck($head['body'] === '' && $head['content_type'] === 'application/pdf', 'HEAD checks ownership without rendering or returning document bytes');
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'issuers_audit')->row()->n === $auditCount, 'HEAD never records a completed download');
foreach ([[$a, $branded], [$b, $ib]] as [$owner, $invoice]) {
    foreach ([[], ['output_type'=>'I'], ['print'=>'true']] as $query) {
        $response = $pdfTransport->response($owner, 7, $invoice, 'GET', $query);
        essentialCheck(str_starts_with($response['body'], '%PDF-'), 'actual A/B native-route transport renders the existing scoped PDF');
        essentialCheck(in_array('Cache-Control: no-store', $response['headers'], true), 'PDF is not publicly cacheable');
        $disposition = 'Content-Disposition: '.($query ? 'inline' : 'attachment').'; filename="fatura-'.$invoice.'.pdf"';
        essentialCheck(in_array($disposition, $response['headers'], true), 'only native inline/download semantics are exposed');
    }
}
essentialThrows(fn()=>$pdfTransport->response($b, 7, $branded, 'GET', []), 'issuer_document_scope_mismatch', 'B cannot export A by direct native URL');
essentialThrows(fn()=>$pdfTransport->response($a, 7, $ib, 'HEAD', []), 'issuer_document_scope_mismatch', 'A cannot probe B via HEAD');
essentialThrows(fn()=>$pdfTransport->response($a, 7, 42, 'GET', []), 'issuer_document_scope_mismatch', 'named route never adopts native NULL invoice');
essentialThrows(fn()=>$pdfTransport->response($a, 999999, $branded, 'GET', []), 'company_invoice_access_denied', 'unknown actor cannot render a document');
foreach (['POST', 'PUT', 'DELETE'] as $method) {
    essentialThrows(fn()=>$pdfTransport->response($a, 7, $branded, $method, []), 'native_company_invoice_method_denied', 'PDF is never a mutation transport');
}
foreach ([['output_type'=>'F'], ['output_type'=>'S'], ['output_type'=>['I']], ['print'=>['1']], ['issuer_id'=>$b], ['file'=>'/tmp/forbidden.pdf']] as $query) {
    essentialThrows(fn()=>$pdfTransport->response($a, 7, $branded, 'GET', $query), 'native_company_invoice_pdf_input', 'filesystem modes and forged context are rejected');
}
$db->failOn = 'INSERT INTO '.$p.'issuers_audit';
essentialThrows(fn()=>$pdfTransport->response($a, 7, $branded, 'GET', []), 'issuer_storage_failed', 'audit failure cannot return already-rendered bytes');
$db->failOn = null;
essentialCheck($db->query('SELECT * FROM '.$p.'invoicepaymentrecords ORDER BY id')->result_array() === $cashBefore, 'PDF transport never mutates cash');
essentialCheck($db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array() === $invoicesBefore, 'PDF transport preserves all principal/A/B/NULL invoice bytes');
echo 'Native invoice PDF transport A/B/NULL/ACL/HEAD/audit/HTTP modes: '.$GLOBALS['essentialChecks']." cumulative checks.\n";
