<?php
/** Real Settings writer and canonical invoice/credit PDF snapshots, no provider. */
require __DIR__.'/credits_reader.php';
require_once APPPATH.'services/NativeCompanyPdfSettings.php';
if(!defined('APP_BASE_URL'))define('APP_BASE_URL','https://stored-app.example.invalid/crm/');
$start=$GLOBALS['essentialChecks'];$pdfSettings=new NativeCompanyPdfSettings($db,$p);
$primary=(new IssuerResourceAccess($db,$p))->primaryId();
$settings->saveIdentity($primary,7,['legal_name'=>'Layout principal','tax_id'=>'12345678909','profile'=>['invoice_company_address'=>'Stored principal address','invoice_company_city'=>'Salvador','company_state'=>'BA','invoice_company_country_code'=>'BR','invoice_company_postal_code'=>'40000-000']]);
$cp=$customers->create($primary,7,['company'=>'Layout principal recipient']);
$image=imagecreatetruecolor(4,4);imagefill($image,0,0,imagecolorallocate($image,12,34,56));ob_start();imagepng($image);$png=ob_get_clean();unset($image);
$changed=array_replace(CompanyPdfLayout::DEFAULTS,['swap_pdf_info'=>'1','pdf_table_heading_color'=>'#123456','pdf_table_heading_text_color'=>'#fedcba','pdf_logo_width'=>'175','show_page_number_on_pdf'=>'1','show_status_on_pdf_ei'=>'0']);
$folder=getenv('CRM_DOCUMENT_PDF_EVIDENCE');if($folder&&(!preg_match('~^/tmp/crm-document-pdf-[A-Za-z0-9_-]+$~D',$folder)||!is_dir($folder)))throw new RuntimeException('invalid evidence folder');
foreach([[$a,$ca],[$b,$cb],[$primary,$cp]]as[$owner,$client]){
 $other=$owner===$a?$b:$a;$otherBefore=$pdfSettings->page($other,7);
 $before=$pdfSettings->page($owner,7);$saved=$pdfSettings->save($owner,7,$before['revision'],$changed,['signature'=>$png]);
 essentialCheck(array_intersect_key($saved['values'],$changed)===$changed&&$pdfSettings->page($other,7)===$otherBefore,'principal/A/B Settings saves only its own real visual options and image');
 essentialThrows(fn()=>$pdfSettings->save($owner,7,$before['revision'],['pdf_logo_width'=>'120']),'native_settings_stale','stale PDF form cannot overwrite stored options');
 $invoice=$s->create($owner,7,array_replace($input,['client_id'=>$client]),hash('sha256','layout-invoice-'.$owner));
 $dto=$s->document($owner,7,$invoice);$frozen=$dto->data()['identity'];$layout=$frozen['pdf_preferences']['layout'];
 essentialCheck($layout['swap_info']&&$layout['logo_width']===175&&!$layout['show_status']&&$layout['heading_color']==='#123456'&&$layout['show_page_number'],'canonical invoice freezes the actual scoped visual options');
 $pdf=new Issuer_invoice_pdf($dto);$html=$pdf->html();
 essentialCheck(strpos($html,'Destinatário')<strpos($html,htmlspecialchars($frozen['legal_name'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'))&&str_contains($html,'#123456')&&str_contains($html,'#fedcba'),'actual canonical invoice HTML swaps parties and uses captured table colours');
 essentialCheck(!str_contains($html,'Em aberto')&&str_contains($html,'Assinatura do emitente')&&str_contains($html,'105,53'),'status/signature and exact stored monetary total are rendered, not placeholder values');
 $pdf->prepare();essentialCheck($pdf->getNumPages()===1,'page number option does not create a blank extra page');$bytes=$pdf->Output('','S');essentialCheck(str_starts_with($bytes,'%PDF-')&&str_contains($bytes,'/Subtype /Image'),'real invoice PDF embeds the stored signature');
 $credit=$writer->issueCompanyCredit($owner,7,array_replace($ci,['client_id'=>$client]),hash('sha256','layout-credit-'.$owner));$creditBefore=$reader->get($owner,7,$credit);
 essentialCheck($creditBefore['identity']['pdf_preferences']['layout']===array_replace($layout,['show_status'=>true]),'credit captures own layout; the invoice/estimate status option does not suppress credit status');$creditBytes=$reader->pdf($owner,7,$credit);essentialCheck(str_starts_with($creditBytes,'%PDF-')&&str_contains($creditBytes,'/Subtype /Image'),'real credit PDF embeds own stored image');
 if($folder){file_put_contents($folder.'/layout-invoice-'.$owner.'.pdf',$bytes);file_put_contents($folder.'/layout-credit-'.$owner.'.pdf',$creditBytes);}
 $current=$pdfSettings->page($owner,7);$pdfSettings->save($owner,7,$current['revision'],['swap_pdf_info'=>'0','show_pdf_signature_invoice'=>'0','show_pdf_signature_credit_note'=>'0','show_status_on_pdf_ei'=>'1','show_page_number_on_pdf'=>'0']);
 essentialCheck($s->document($owner,7,$invoice)->data()['identity']===$frozen&&$reader->get($owner,7,$credit)===$creditBefore,'later Settings changes preserve all existing document snapshots');
 $new=$s->create($owner,7,array_replace($input,['client_id'=>$client]),hash('sha256','layout-next-invoice-'.$owner));$newPdf=new Issuer_invoice_pdf($s->document($owner,7,$new));$newHtml=$newPdf->html();
 essentialCheck(str_contains($newHtml,'Em aberto')&&!str_contains($newHtml,'Assinatura do emitente')&&strpos($newHtml,htmlspecialchars($frozen['legal_name'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'))<strpos($newHtml,'Destinatário'),'new invoice alone uses the newly stored signature/status/order options');
 essentialThrows(fn()=>$s->document($other,7,$invoice),'issuer_document_scope_mismatch','known foreign PDF remains inaccessible');
 $current=$pdfSettings->page($owner,7);$pdfSettings->save($owner,7,$current['revision'],['show_transactions_on_invoice_pdf'=>'1','show_pay_link_to_invoice_pdf'=>'1']);
 $paidInvoice=$s->create($owner,7,array_replace($input,['client_id'=>$client]),hash('sha256','layout-payment-invoice-'.$owner));
 $paymentId=$writer->recordCompanyManual($owner,7,$paidInvoice,['amount'=>'20.00','date'=>'2026-10-19','reference'=>'layout-receipt-'.$owner,'note'=>'Private internal evidence'],hash('sha256','layout-receipt-'.$owner));
 $writer->moveCompanyCredit($owner,7,'manual_reverse',['invoice_id'=>$paidInvoice,'payment_id'=>$paymentId,'amount'=>'7.00','date'=>'2026-10-19','reference'=>'layout-reversal-'.$owner,'reason'=>'Private correction'],hash('sha256','layout-reversal-'.$owner));
 $paymentDto=$s->document($owner,7,$paidInvoice);$paymentData=$paymentDto->data();$transactions=$paymentData['transactions'];
 essentialCheck(count($transactions)===1&&$transactions[0]['id']===$paymentId&&$transactions[0]['original']==='20.00'&&$transactions[0]['reversed']==='7.00'&&$transactions[0]['effective']==='13.00','PDF projects the actual payment and preserved manual reversal, never a fabricated amount');
 $paymentHtml=(new Issuer_invoice_pdf($paymentDto))->html();$storedHash=$db->query('SELECT hash FROM '.$p.'invoices WHERE id=?',[$paidInvoice])->row()->hash;
 essentialCheck(str_contains($paymentHtml,'20,00')&&str_contains($paymentHtml,'7,00')&&str_contains($paymentHtml,'13,00')&&!str_contains($paymentHtml,'Private internal evidence'),'transaction table renders original/reversed/effective amounts without leaking the internal note');
 essentialCheck(str_contains($paymentHtml,'https://stored-app.example.invalid/crm/invoice/'.$paidInvoice.'/'.$storedHash.'?issuer_id='.$owner)&&str_contains($paymentHtml,'autenticação'),'optional link uses the configured application and real invoice identity; portal still requires contact authorization');
 $writer->recordCompanyManual($owner,7,$paidInvoice,['amount'=>'92.53','date'=>'2026-10-19','reference'=>'layout-final-'.$owner,'note'=>''],hash('sha256','layout-final-'.$owner));
 $settled=$s->document($owner,7,$paidInvoice);essentialCheck($settled->data()['status']===2&&!isset($settled->data()['portal_path'])&&!str_contains((new Issuer_invoice_pdf($settled))->html(),'Consultar fatura e opções'),'settled invoice keeps payment history and suppresses the payment link');
 essentialCheck(!isset($s->document($owner,7,$invoice)->data()['transactions']),'new PDF options do not rewrite older issued snapshots');
 essentialThrows(fn()=>$s->document($other,7,$paidInvoice),'issuer_document_scope_mismatch','foreign payment history and portal URL cannot be read via the PDF');
}
$before=$pdfSettings->page($b,7);
foreach([['pdf_logo_width'=>'0'],['pdf_logo_width'=>'301'],['pdf_logo_width'=>'2.5'],['pdf_table_heading_color'=>'red;background:url(https://external.invalid)'],['show_page_number_on_pdf'=>'true'],['unknown'=>'1']]as$bad){
 essentialThrows(fn()=>$pdfSettings->save($b,7,$before['revision'],$bad),isset($bad['unknown'])?'native_settings_invalid':'company_document_layout_invalid','invalid style/option rejected before any write');
 essentialCheck($pdfSettings->page($b,7)===$before,'invalid PDF style leaves all settings/assets unchanged');
}
essentialThrows(fn()=>$pdfSettings->save($b,9999,$before['revision'],['swap_pdf_info'=>'1']),'native_settings_denied','unknown/restricted staff cannot alter company PDF settings');
$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows(fn()=>$pdfSettings->save($b,7,$before['revision'],['pdf_logo_width'=>'176']),'issuer_storage_failed','late audit failure rolls back visual option update');$db->failOn=null;
essentialCheck($pdfSettings->page($b,7)===$before,'audit rollback retains prior visual settings and revision');
$bad=$layout;$bad['show_signature']='1';essentialThrows(fn()=>CompanyPdfLayout::validate($bad),'company_document_layout_invalid','snapshot bool cannot be coerced');
$bad=$layout;$bad['external_url']='https://external.invalid/';essentialThrows(fn()=>CompanyPdfLayout::validate($bad),'company_document_layout_invalid','unknown snapshot URL is rejected');
echo 'Native PDF layout, stored images/values, immutable snapshots, principal/A/B, stale/ACL/rollback: '.($GLOBALS['essentialChecks']-$start).' added checks passed ('.$db->driver().').'.PHP_EOL;
