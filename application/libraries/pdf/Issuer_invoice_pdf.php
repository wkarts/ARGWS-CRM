<?php

// This file intentionally does not bootstrap App_pdf or its legacy hooks.
require_once __DIR__ . '/../../services/issuers/InvoiceIssuerDocumentData.php';

/** Standalone renderer: only an authorized immutable DTO, no CI container, options, theme or hooks. */
final class Issuer_invoice_pdf extends TCPDF
{
    private InvoiceIssuerDocumentData $document;
    private bool $prepared = false;

    public function __construct(InvoiceIssuerDocumentData $document)
    {
        $this->document = $document;
        $format=CompanyInvoicePresentation::specification($document->data()['identity'],'invoices');
        parent::__construct($format['orientation'],'mm',$format['format'],true,'UTF-8',false,false);
        $this->tcpdflink = false;
        $this->setPrintHeader(false);
        $this->setPrintFooter(true);
        $this->setMargins(15,15,15);
        $this->setAutoPageBreak(true,15);
        $this->setFont('dejavusans','',9);
        $this->setCreator('CRM');
        $data = $document->data();
        $this->setAuthor($data['identity']['legal_name']);
        $this->setTitle($data['number']);
        $this->setSubject('Fatura');
    }

    public function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('dejavusans','',8);
        $this->SetTextColor(110,110,110);
        $this->Cell(0,5,'Página ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(),0,0,'C');
    }

    public function html(): string
    {
        $document = $this->document;
        ob_start();
        try {
            require __DIR__ . '/../../views/pdf/issuer_invoicepdf.php';
            return (string) ob_get_clean();
        } catch (Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }

    public function prepare(): self
    {
        if (!$this->prepared) {
            $this->AddPage();
            $this->writeHTML($this->html(),true,false,true,false,'');
            $this->prepared = true;
        }
        return $this;
    }
}
