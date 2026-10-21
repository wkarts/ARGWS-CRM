<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyInvoicePresentation.php';
/** Plain-text standalone TCPDF. No dynamic HTML, remote content, legacy hooks or settings. */
final class CompanyContractsPdf
{
    public static function render(array $contract):string
    {
        if($contract['number']===null||!in_array($contract['status'],['active','cancelled'],true))throw new DomainException('company_contract_pdf_denied');$snapshot=json_decode($contract['snapshot_json'],true,32,JSON_THROW_ON_ERROR);
        if(($snapshot['issuer_id']??null)!==(int)$contract['issuer_id']||($snapshot['number']??null)!==(int)$contract['number'])throw new DomainException('company_contract_pdf_denied');
        if(!class_exists('TCPDF'))require_once __DIR__.'/../vendor/tecnickcom/tcpdf/tcpdf.php';$format=CompanyInvoicePresentation::specification($snapshot,'contracts');$pdf=new class($format) extends TCPDF {public function __construct(array $format){parent::__construct($format['orientation'],'mm',$format['format'],true,'UTF-8',false,false);$this->tcpdflink=false;}};$pdf->setPrintHeader(false);$pdf->setPrintFooter(false);$pdf->setMargins(18,18,18);$pdf->setAutoPageBreak(true,18);$typography=CompanyInvoicePresentation::typography($snapshot,'contracts');$pdf->setFont($typography['font'],'',$typography['size']);$pdf->setCreator('CRM');$pdf->setTitle('Contrato '.$snapshot['number']);$pdf->AddPage();
        $text='Contrato '.$snapshot['number'].' — '.$snapshot['name']."\nEstado: ".($contract['status']==='active'?'Ativo':'Cancelado')."\n\n".$snapshot['identity']['legal_name'].' · '.$snapshot['identity']['tax_id']."\nCliente: ".$snapshot['client']['name'].' · '.($snapshot['client']['tax_id']??'')."\nVigência: ".$snapshot['start_date'].' até '.$snapshot['due_date']."\n\n".$snapshot['body']."\n\nDocumento de registro interno. Não contém assinatura eletrônica ou certificação de aceite.";
        $pdf->MultiCell(0,6,$text,0,'L');return $pdf->Output('','S');
    }
}
