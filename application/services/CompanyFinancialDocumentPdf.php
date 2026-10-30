<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyDocumentBranding.php';
require_once __DIR__.'/CompanyInvoicePresentation.php';
/** Bounded financial report renderer. Inputs are authorized stored facts, never remote images or executable HTML. */
final class CompanyFinancialDocumentPdf
{
    public static function render(string $title,array $identity,array $recipient,array $lines,string $format='A4-PORTRAIT'):string{
        if(strlen($title)>300||count($lines)>10000)throw new DomainException('company_document_invalid');
        $spec=CompanyDocumentPdf::choiceSpecification($format);if(!class_exists('TCPDF'))require_once __DIR__.'/../vendor/tecnickcom/tcpdf/tcpdf.php';
        $pdf=new class($spec)extends TCPDF{public function __construct(array $s){parent::__construct($s['orientation'],'mm',$s['format'],true,'UTF-8',false,false);$this->tcpdflink=false;}};
        $layout=CompanyInvoicePresentation::layout($identity,'invoices');$type=CompanyInvoicePresentation::typography($identity,'invoices');
        $pdf->setPrintHeader(false);$pdf->setPrintFooter(false);$pdf->setMargins(18,18,18);$pdf->setAutoPageBreak(true,18);$pdf->setFont($type['font'],'',$type['size']);$pdf->setCreator('CRM');$pdf->setTitle($title);$pdf->AddPage();
        CompanyDocumentBranding::image($pdf,$identity,'logo_inline',$layout['logo_width']*25.4/72);$e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $pdf->writeHTML(CompanyPdfLayout::parties('<b>'.$e($identity['legal_name']).'</b><br>'.$e($identity['tax_id']).'<br>'.$e(CompanyDocumentBranding::address($identity)),$e($recipient['name']??'').'<br>'.$e($recipient['tax_id']??''),$layout));
        $pdf->MultiCell(0,7,$title,0,'L');$pdf->Ln(3);
        foreach($lines as$line){if(!is_string($line)||strlen($line)>20000||preg_match('//u',$line)!==1)throw new DomainException('company_document_invalid');$pdf->MultiCell(0,6,$line,0,'L');}
        if($layout['show_signature']&&($identity['signature_inline']??'')!==''){$pdf->Ln(4);CompanyDocumentBranding::image($pdf,$identity,'signature_inline',45);$pdf->MultiCell(0,5,'Assinatura visual do emitente',0,'L');}
        CompanyPdfLayout::pageNumbers($pdf,$layout,$type['font']);return$pdf->Output('','S');
    }
    public static function payment(array $data,string $format='A4-PORTRAIT'):string{
        $p=$data['payment'];$i=$data['invoice'];
        $lines=['Fatura: '.$i['number'].' — '.$i['date'],'Pagamento: '.$p['date'],'Modo: '.($p['paymentmethod']?:$p['paymentmode']),'Referência: '.$p['transactionid'],'Valor original: '.$i['currency'].' '.$p['original_amount'],'Valor estornado: '.$i['currency'].' '.$p['reversed_amount'],'Valor efetivo: '.$i['currency'].' '.$p['effective_amount'],'Saldo da fatura: '.$i['currency'].' '.$data['balance']['receivable'],'Nota: '.$data['note']];
        foreach($p['reversals']as$r)$lines[]='Estorno em '.$r['book_date'].': '.$i['currency'].' '.$r['amount'].' — '.$r['reference'].' — '.$r['reason'];
        return self::render('Comprovante de pagamento #'.$p['id'],$i['identity'],$i['recipient'],$lines,$format);
    }
}
