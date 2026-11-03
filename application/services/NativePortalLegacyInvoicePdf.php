<?php
declare(strict_types=1);

/** PDF of the existing historical record; no synthesized issuer snapshot. */
final class NativePortalLegacyInvoicePdf
{
 public static function render(array $data):string{
  if(!class_exists('TCPDF'))require_once APPPATH.'vendor/tecnickcom/tcpdf/tcpdf.php';$pdf=new TCPDF();$pdf->setPrintHeader(false);$pdf->setPrintFooter(false);$pdf->SetFont('dejavusans','',10);$pdf->AddPage();$e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
  $html='<h2>'.$e($data['number']).'</h2><p>'.$e($data['identity']['legal_name']).'<br>'.$e($data['identity']['tax_id']??'').'<br>'.$e($data['recipient']['name']).'<br>'.$e($data['recipient']['tax_id']??'').'</p>';foreach(['billing'=>'invoice_bill_to','shipping'=>'ship_to']as$kind=>$label){$address=$data['recipient'][$kind]??null;if(!is_array($address))continue;$html.='<p><strong>'.$e(_l($label)).'</strong><br>';foreach(['street','city','state','zip','country']as$part)if(($address[$part]??'')!=='')$html.=$e($address[$part]).'<br>';$html.='</p>';}$html.='<p>'.$e(_l('invoice_data_date')).' '.$e($data['date']).'<br>'.$e(_l('invoice_data_duedate')).' '.$e($data['due_date']??'').'</p><table border="1" cellpadding="3"><tr><th>'.$e(_l('invoice_table_item_heading')).'</th><th>'.$e(_l('invoice_table_quantity_heading')).'</th><th>'.$e(_l('invoice_table_rate_heading')).'</th><th>'.$e(_l('invoice_table_amount_heading')).'</th></tr>';
  foreach($data['items']as$item){$description=$e($item['description']).'<br>'.$e($item['details']);foreach($item['taxes']as$tax)$description.='<br>'.$e($tax['name'].' '.$tax['rate'].'%');$html.='<tr><td>'.$description.'</td><td>'.$e($item['quantity'].' '.$item['unit']).'</td><td>'.$e($item['rate']).'</td><td>'.$e($item['amount']).'</td></tr>';}
  $html.='</table><p>';foreach(['subtotal'=>'invoice_subtotal','total_tax'=>'invoice_total_tax','discount_total'=>'invoice_discount','adjustment'=>'invoice_adjustment','total'=>'invoice_total']as$key=>$label)$html.=$e(_l($label)).': '.$e($data['currency'].' '.$data['totals'][$key]).'<br>';$html.=$e(_l('invoice_total_paid')).': '.$e($data['currency'].' '.$data['position']['received']).'<br>'.$e(_l('invoice_amount_due')).': '.$e($data['currency'].' '.$data['position']['receivable']).'</p><p>'.$e($data['note']).'<br>'.$e($data['terms']).'</p>';$pdf->writeHTML($html);return$pdf->Output('','S');
 }
}
