<?php

declare(strict_types=1);

/** Plain-text PDF from a previously authorized immutable HR contract snapshot. */
final class CompanyHrPdf
{
    public static function render(array $document):string
    {
        $s=$document['snapshot']??null;if(!is_array($s)||!isset($s['company'],$s['employee'],$s['terms'],$s['number'])||(int)$s['number']!==(int)($document['number']??0))throw new DomainException('issuer_hr_document_denied');
        if(!class_exists('TCPDF'))require_once __DIR__.'/../vendor/tecnickcom/tcpdf/tcpdf.php';
        $pdf=new class extends TCPDF{public function __construct(){parent::__construct('P','mm','A4',true,'UTF-8',false,false);$this->tcpdflink=false;}};
        $pdf->setPrintHeader(false);$pdf->setPrintFooter(false);$pdf->setMargins(18,18,18);$pdf->setAutoPageBreak(true,18);$pdf->setFont('dejavusans','',10);$pdf->setCreator('CRM');$pdf->setTitle('Contrato de vínculo '.$s['number']);$pdf->AddPage();
        $terms=$s['terms'];$minor=(int)$terms['salary_minor'];$amount=intdiv($minor,100).','.str_pad((string)($minor%100),2,'0',STR_PAD_LEFT);
        $text='Contrato de vínculo '.$s['number']."\n\n".$s['company']['legal_name'].' · '.($s['company']['tax_id']??'')."\nPessoa: ".$s['employee']['name'].' · '.$s['employee']['employee_code']."\nTipo: ".$s['type']."\nVigência: ".$s['starts_on'].' até '.($s['ends_on']??'prazo aberto')."\nValor informado: ".$terms['currency'].' '.$amount."\nHoras por semana informadas: ".$terms['hours_per_week']."\n\n".$terms['job_description']."\n\n".$terms['text']."\n\nCiência registrada em: ".($document['acknowledged_at']??'ainda não registrada')."\nRegistro interno. A ciência por link não constitui assinatura digital, certificação jurídica, cálculo de folha ou apuração de encargos.";
        $pdf->MultiCell(0,6,$text,0,'L');return $pdf->Output('','S');
    }
}
