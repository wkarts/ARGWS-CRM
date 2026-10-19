<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyInvoiceAmounts.php';
require_once __DIR__.'/CompanySalesPreferences.php';
/** Renders only an authorized immutable DTO. Caller reauthorizes and audits before bytes. */
final class CompanySalesPdf
{
    public static function prepare(array $document):TCPDF
    {
        $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$payload=$document['payload'];$context=$document['context'];$amounts=CompanyInvoiceAmounts::calculate($payload['items'],array_intersect_key($payload,array_flip(['discount_mode','discount_value','discount_type','adjustment'])));if(CompanyInvoiceAmounts::minor($amounts['total'])!==CompanyInvoiceAmounts::minor((string)$document['total']))throw new DomainException('company_sales_invalid');$kind=$document['kind']==='estimates'?'Orçamento':'Proposta';
        $display=CompanySalesPreferences::presentation($document['kind'],$document['number'],$context);$format=CompanyDocumentPdf::specification($context);$pdf=new TCPDF($format['orientation'],'mm',$format['format'],true,'UTF-8',false);$pdf->setPrintHeader(false);$pdf->setPrintFooter(false);$pdf->SetCreator('ARGWS CRM');$pdf->SetTitle($kind.' '.(isset($context['sales_preferences'])?$display['formatted_number']:(int)$document['number']));$pdf->SetFont('dejavusans','',10);$pdf->AddPage();
        $html='<h1>'.$kind.' '.$e($display['formatted_number']).'</h1><p>'.$e($context['identity']['legal_name']).' · '.$e($context['identity']['tax_id']).'<br>'.$e($context['identity']['address']).'</p><p>Cliente: '.$e($context['recipient']['name']).'<br>'.$e($context['recipient']['tax_id']).'</p><p>Emissão: '.$e($payload['date']).' · Validade: '.$e($payload['due_date']).'</p><table border="1" cellpadding="4"><thead><tr><th>Descrição</th><th>Quantidade</th><th>Preço unitário</th></tr></thead><tbody>';
        foreach($payload['items'] as $item){$taxes=[];foreach($item['taxes'] as $tax)$taxes[]=$e($tax['name'].' '.$tax['rate'].'%');$html.='<tr><td>'.$e($item['description']).($taxes?'<br>'.implode(' · ',$taxes):'').'</td><td>'.$e($item['qty']).'</td><td>'.$e($item['rate']).'</td></tr>';}$html.='</tbody></table><p>Subtotal: '.$e($amounts['subtotal']).'<br>Tributos: '.$e($amounts['tax']).'<br>Desconto: '.$e($amounts['discount_total']).' ('.($amounts['discount_type']==='before_tax'?'antes dos tributos':'depois dos tributos').')<br>Ajuste: '.$e($amounts['adjustment']).'</p><p>Total: '.$e($context['currency']['name']).' '.$e($document['total']).'</p><p>'.nl2br($e($payload['note'])).'</p><p>'.nl2br($e($display['terms'])).'</p>';$pdf->writeHTML($html);return $pdf;
    }
}
