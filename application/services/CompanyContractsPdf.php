<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyInvoicePresentation.php';
require_once __DIR__.'/CompanyContractValue.php';
require_once __DIR__.'/NativeCompanyContractTypes.php';
require_once __DIR__.'/CompanyDocumentBranding.php';
/** Plain-text standalone TCPDF. No dynamic HTML, remote content, legacy hooks or settings. */
final class CompanyContractsPdf
{
    public static function render(array $contract):string
    {
        if($contract['number']===null||!in_array($contract['status'],['active','cancelled'],true))throw new DomainException('company_contract_pdf_denied');$snapshot=json_decode($contract['snapshot_json'],true,32,JSON_THROW_ON_ERROR);
        if(($snapshot['issuer_id']??null)!==(int)$contract['issuer_id']||($snapshot['number']??null)!==(int)$contract['number'])throw new DomainException('company_contract_pdf_denied');
        if(!class_exists('TCPDF'))require_once __DIR__.'/../vendor/tecnickcom/tcpdf/tcpdf.php';$format=CompanyInvoicePresentation::specification($snapshot,'contracts');$pdf=new class($format) extends TCPDF {public function __construct(array $format){parent::__construct($format['orientation'],'mm',$format['format'],true,'UTF-8',false,false);$this->tcpdflink=false;}};$pdf->setPrintHeader(false);$pdf->setPrintFooter(false);$pdf->setMargins(18,18,18);$pdf->setAutoPageBreak(true,18);$typography=CompanyInvoicePresentation::typography($snapshot,'contracts');$pdf->setFont($typography['font'],'',$typography['size']);$pdf->setCreator('CRM');$pdf->setTitle('Contrato '.$snapshot['number']);$pdf->AddPage();
        $type=NativeCompanyContractTypes::stored($snapshot);$layout=CompanyInvoicePresentation::layout($snapshot,'contracts');$value=CompanyContractValue::stored($snapshot);CompanyDocumentBranding::image($pdf,$snapshot['identity'],'logo_inline',$layout['logo_width']*25.4/72);$e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $pdf->writeHTML(CompanyPdfLayout::parties('<b>'.$e($snapshot['identity']['legal_name']).'</b><br>'.$e($snapshot['identity']['tax_id']).'<br>'.$e(CompanyDocumentBranding::address($snapshot['identity'])),'Cliente: '.$e($snapshot['client']['name']).'<br>'.$e($snapshot['client']['tax_id']??''),$layout));
        $text='Contrato '.$snapshot['number'].' — '.$snapshot['name']."\nEstado: ".($contract['status']==='active'?'Ativo':'Cancelado')."\n\nVigência: ".$snapshot['start_date'].' até '.$snapshot['due_date'].($type?"\nTipo: ".$type['contract_type']['name']:'').($value?"\nValor: ".$value['currency'].' '.$value['contract_value']:'')."\n\n".$snapshot['body'];
        $pdf->MultiCell(0,6,$text,0,'L');
        if($layout['show_signature']&&($snapshot['identity']['signature_inline']??'')!==''){$pdf->Ln(5);CompanyDocumentBranding::image($pdf,$snapshot['identity'],'signature_inline',45);$pdf->MultiCell(0,6,'Assinatura visual do emitente',0,'L');}
        $pdf->Ln(5);$pdf->MultiCell(0,6,'Documento de registro interno. Não contém assinatura eletrônica ou certificação de aceite.',0,'L');CompanyPdfLayout::pageNumbers($pdf,$layout,$typography['font']);return $pdf->Output('','S');
    }
}
