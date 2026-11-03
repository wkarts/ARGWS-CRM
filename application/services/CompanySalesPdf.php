<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyInvoiceLines.php';
require_once __DIR__.'/CompanySalesPreferences.php';
require_once __DIR__.'/CompanyDocumentBranding.php';
/** Renders only an authorized immutable DTO. Caller reauthorizes and audits before bytes. */
final class CompanySalesPdf
{
    public static function prepare(array $document):TCPDF
    {
        $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$payload=$document['payload'];$context=$document['context'];$amounts=CompanyInvoiceLines::calculate($payload['items'],array_intersect_key($payload,array_flip(['discount_mode','discount_value','discount_type','adjustment','calculation_contract'])));if(CompanyInvoiceAmounts::minor($amounts['total'])!==CompanyInvoiceAmounts::minor((string)$document['total']))throw new DomainException('company_sales_invalid');$kind=$document['kind']==='estimates'?'Orçamento':'Proposta';
        $display=CompanySalesPreferences::presentation($document['kind'],$document['number'],$context,'',$document['payload']['date']??null);$native=isset($payload['native_fields'])?CompanyInvoiceNativeFields::input($payload['native_fields']):[];if(array_key_exists('terms',$native))$display['terms']=$native['terms'];$format=CompanyDocumentPdf::specification($context);$pdf=new TCPDF($format['orientation'],'mm',$format['format'],true,'UTF-8',false);$pdf->setPrintHeader(false);$pdf->setPrintFooter(false);$pdf->SetCreator('ARGWS CRM');$pdf->SetTitle($kind.' '.(isset($context['sales_preferences'])?$display['formatted_number']:(int)$document['number']));$typography=CompanyDocumentPdf::typography($context);$pdf->SetFont($typography['font'],'',$typography['size']);$pdf->AddPage();
        $layout=CompanyDocumentPdf::layout($context,$document['kind']);CompanyDocumentBranding::image($pdf,$context['identity'],'logo_inline',$layout['logo_width']*25.4/72);
        $stateLabel=match($document['state']){'open'=>'Em aberto','accepted'=>'Aceito','declined'=>'Recusado','cancelled'=>'Cancelado','converting'=>'Em conversão','converted'=>'Convertido',default=>throw new DomainException('company_sales_invalid')};
        $html='<h1>'.$kind.' '.$e($display['formatted_number']).'</h1>'.CompanyPdfLayout::parties($e($context['identity']['legal_name']).' · '.$e($context['identity']['tax_id']).'<br>'.$e(CompanyDocumentBranding::address($context['identity'])),'Cliente: '.$e($context['recipient']['name']).'<br>'.$e($context['recipient']['tax_id']),$layout).'<p>Emissão: '.$e($payload['date']).' · Validade: '.$e($payload['due_date']).'</p><table border="1" cellpadding="4"><thead><tr style="background-color:'.$e($layout['heading_color']).';color:'.$e($layout['heading_text_color']).';"><th>Descrição</th><th>Quantidade</th><th>Preço unitário</th></tr></thead><tbody>';
        if($document['kind']==='estimates'&&$layout['show_status'])$html='<p>Estado: '.$e($stateLabel).'</p>'.$html;
        foreach($amounts['items'] as $item){
            if(($item['is_optional']??0)===1&&($item['is_selected']??1)===0)continue;
            $taxes=[];foreach($item['taxes'] as $tax)$taxes[]=$e($tax['name'].' '.$tax['rate'].'%');
            $details=$item['long_description']??'';$unit=$item['unit']??'';
            $html.='<tr><td>'.$e($item['description']).($details!==''?'<br>'.nl2br($e($details)):'').($taxes?'<br>'.implode(' · ',$taxes):'').'</td><td>'.$e($item['qty']).($unit!==''?' '.$e($unit):'').'</td><td>'.$e($item['rate']).'</td></tr>';
        }
        $html.='</tbody></table>';
        if(($payload['native_reference']??'')!=='')$html.='<p>Referência: '.$e($payload['native_reference']).'</p>';
        if($native!==[]){
            $billing=array_values(array_filter([$native['billing_street']??'', $native['billing_city']??'', $native['billing_state']??'', $native['billing_zip']??''],static fn($v)=>$v!==''));
            if($billing!==[])$html.='<p>Faturamento: '.implode(' · ',array_map($e,$billing)).'</p>';
            if(($native['include_shipping']??0)===1&&($native['show_shipping_on_invoice']??0)===1){
                $shipping=array_values(array_filter([$native['shipping_street']??'', $native['shipping_city']??'', $native['shipping_state']??'', $native['shipping_zip']??''],static fn($v)=>$v!==''));
                if($shipping!==[])$html.='<p>Entrega: '.implode(' · ',array_map($e,$shipping)).'</p>';
            }
        }
        if(isset($document['custom_fields'])){
            if(!is_array($document['custom_fields'])||!array_is_list($document['custom_fields']))throw new DomainException('company_sales_invalid');
            foreach($document['custom_fields'] as$field){if(!is_array($field)||!is_string($field['name']??null)||!is_string($field['value']??null)||!in_array($field['show_on_pdf']??null,[0,1],true))throw new DomainException('company_sales_invalid');if($field['show_on_pdf']===1)$html.='<p>'.$e($field['name']).': '.nl2br($e($field['value'])).'</p>';}
        }
        $html.='<p>Subtotal: '.$e($amounts['subtotal']).'<br>Tributos: '.$e($amounts['tax']).'<br>Desconto: '.$e($amounts['discount_total']).' ('.($amounts['discount_type']==='before_tax'?'antes dos tributos':'depois dos tributos').')<br>Ajuste: '.$e($amounts['adjustment']).'</p><p>Total: '.$e($context['currency']['name']).' '.$e($amounts['total']).'</p><p>'.nl2br($e($payload['note'])).'</p><p>'.nl2br($e($display['terms'])).'</p>';$pdf->writeHTML($html);
        if($layout['show_signature']&&($context['identity']['signature_inline']??'')!==''){$pdf->Ln(5);CompanyDocumentBranding::image($pdf,$context['identity'],'signature_inline',45);$pdf->writeHTML('<p>Assinatura visual do emitente</p>');}
        if(isset($document['signature'])){
            $signature=$document['signature'];if(!is_array($signature))throw new DomainException('company_sales_invalid');foreach(['firstname','lastname','email','title','ip','date','image']as$key)if(!is_string($signature[$key]??null))throw new DomainException('company_sales_invalid');
            if(!str_starts_with($signature['image'],'data:image/png;base64,')||strlen($signature['image'])>700000||($image=base64_decode(substr($signature['image'],22),true))===false||strlen($image)>524288||(@getimagesizefromstring($image)['mime']??null)!=='image/png')throw new DomainException('company_sales_invalid');
            $pdf->AddPage();$signatureHtml='<h2>Assinatura</h2><p>'.$e($signature['firstname'].' '.$signature['lastname']).'<br>'.$e($signature['email']).'<br>'.$e($signature['title']).'<br>'.$e($signature['date']).'<br>'.$e($signature['ip']).'</p>';$pdf->writeHTML($signatureHtml);$pdf->Image('@'.$image,15,$pdf->GetY()+5,50,20,'PNG');
        }
        CompanyPdfLayout::pageNumbers($pdf,$layout,$typography['font']);return $pdf;
    }
}
