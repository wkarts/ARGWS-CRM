<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyPdfTypography.php';

/** Historical totals are printed as stored, never recomputed or migrated by a download. */
final class NativeCompanyLegacySalesPdf
{
    public static function prepare(array $document):TCPDF
    {
        if(($document['origin']??null)!=='legacy')throw new DomainException('company_sales_invalid');
        $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $kind=$document['kind'];$format=$document['pdf_options']['pdf_format_'.($kind==='estimates'?'estimate':'proposal')]??'A4-PORTRAIT';
        if(!in_array($format,['A4-PORTRAIT','A4-LANDSCAPE','LETTER-PORTRAIT','LETTER-LANDSCAPE'],true))$format='A4-PORTRAIT';
        [$paper,$orientation]=explode('-',$format);$pdf=new TCPDF($orientation==='LANDSCAPE'?'L':'P','mm',$paper,true,'UTF-8',false);
        $font=$document['pdf_options']['pdf_font']??'dejavusans';if(!isset(CompanyPdfTypography::fonts()[$font]))$font='dejavusans';
        $size=(int)($document['pdf_options']['pdf_font_size']??10);if($size<6||$size>24)$size=10;
        $pdf->setPrintHeader(false);$pdf->setPrintFooter(false);$pdf->SetFont($font,'',$size);$pdf->SetTitle($document['formatted_number']);$pdf->AddPage();
        $ctx=$document['context'];$data=$document['payload'];
        $html='<h1>'.($kind==='estimates'?'Orçamento':'Proposta').' '.$e($document['formatted_number']).'</h1><p>'.$e($ctx['identity']['legal_name']).' · '.$e($ctx['identity']['tax_id']).'<br>'.$e($ctx['identity']['address']).'</p><p>Cliente: '.$e($ctx['recipient']['name']).'</p><p>Emissão: '.$e($data['date']).' · Validade: '.$e($data['due_date']).'</p>';
        if($document['subject']!=='')$html.='<h2>'.$e($document['subject']).'</h2>';
        if($document['legacy_content']!==''){require_once __DIR__.'/CompanySettingsCatalog.php';$html.=CompanySettingsCatalog::htmlFragment($document['legacy_content']);}
        $html.='<table border="1" cellpadding="4"><tr><th>Descrição</th><th>Quantidade</th><th>Preço unitário</th></tr>';
        foreach($data['items']as$item){if($item['is_optional']===1&&$item['is_selected']===0)continue;$html.='<tr><td>'.$e($item['description']).'<br>'.nl2br($e($item['long_description'])).'</td><td>'.$e($item['qty'].' '.$item['unit']).'</td><td>'.$e($item['rate']).'</td></tr>';}
        $html.='</table>';foreach(['subtotal'=>'Subtotal','total_tax'=>'Tributos','discount_total'=>'Desconto','adjustment'=>'Ajuste','total'=>'Total']as$key=>$label)if(isset($document['stored_amounts'][$key]))$html.='<p>'.$label.': '.$e($ctx['currency']['name'].' '.$document['stored_amounts'][$key]).'</p>';
        $html.='<p>'.nl2br($e($data['note'])).'</p><p>'.nl2br($e($document['terms'])).'</p>';
        $pdf->writeHTML($html,true,false,true,false,'');return $pdf;
    }
}
