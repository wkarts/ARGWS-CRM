<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyInvoices.php';

/** Native /admin/invoices/pdf transport; no legacy models, hooks or provider calls. */
final class NativeCompanyInvoicePdf
{
    public function __construct(private $db, private string $prefix) {}

    public function response(int $issuer, int $actor, $invoice, string $method, array $query): array
    {
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            throw new DomainException('native_company_invoice_method_denied');
        }
        // TCPDF output modes F/S/E are not HTTP delivery modes and must never be forwarded.
        if (array_diff(array_keys($query), ['output_type', 'print'])
            || (isset($query['output_type']) && !in_array($query['output_type'], ['I', 'D'], true))
            || (isset($query['print']) && !in_array($query['print'], ['1', 'true'], true))) {
            throw new DomainException('native_company_invoice_pdf_input');
        }
        $service = new CompanyInvoices($this->db, $this->prefix);
        $document = $service->document($issuer, $actor, $invoice);
        $id = $document->data()['invoice_id'];
        $inline = isset($query['print']) || ($query['output_type'] ?? 'D') === 'I';
        $headers = [
            'Cache-Control: no-store',
            'X-Content-Type-Options: nosniff',
            "Content-Security-Policy: sandbox; default-src 'none'",
            'Content-Disposition: '.($inline ? 'inline' : 'attachment').'; filename="fatura-'.$id.'.pdf"',
        ];
        if ($method === 'HEAD') {
            return ['headers'=>$headers, 'content_type'=>'application/pdf', 'body'=>''];
        }
        require_once APPPATH.'vendor/tecnickcom/tcpdf/tcpdf.php';
        require_once APPPATH.'libraries/pdf/Issuer_invoice_pdf.php';
        $bytes = (new Issuer_invoice_pdf($document))->prepare()->Output('', 'S');
        if (!is_string($bytes) || !str_starts_with($bytes, '%PDF-')) {
            throw new RuntimeException('native_company_invoice_pdf_unconfirmed');
        }
        // Recheck live permission and confirm the audit BEFORE returning any bytes.
        $service->confirmPdfDownload($issuer, $actor, $id);
        return ['headers'=>$headers, 'content_type'=>'application/pdf', 'body'=>$bytes];
    }
}
