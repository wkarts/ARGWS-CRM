<?php
declare(strict_types=1);
require_once __DIR__.'/NativePortalIdentity.php';
require_once __DIR__.'/NativePortalPresentation.php';
require_once __DIR__.'/CompanyPortalInvoices.php';

/** Original invoice route, with contact and invoice ownership revalidated before delivery. */
final class NativePortalInvoiceDetailHttp
{
 public function __construct(private $ci){}
 private function verified(array $identity,NativePortalIdentity $identities,int $issuer):void{
  if($identities->current($this->ci->session,$issuer)!==$identity)throw new DomainException('company_portal_invoice_unavailable');$binding=$identity['binding'];$rows=IssuerStorage::query($this->ci->db,'SELECT email_verified_at FROM '.db_prefix().'contacts WHERE id=? AND userid=?',[$binding['contact_id'],$binding['client_id']])->result_array();if(count($rows)!==1||$rows[0]['email_verified_at']===null)throw new DomainException('company_portal_invoice_unavailable');
 }
 public function index($id,$hash):void{
  if(!PortalRequestScope::sealedFor($this->ci)||!in_array($this->ci->input->method(true),['GET','HEAD','POST'],true)||(!is_string($id)&&!is_int($id))||!preg_match('/^[1-9][0-9]*$/D',(string)$id)||!is_string($hash)||!preg_match('/^[A-Za-z0-9]{32}$/D',$hash))throw new DomainException('company_portal_invoice_unavailable');
  $issuer=PortalRequestScope::issuerId();$secret=$this->ci->config->item('encryption_key');$identities=new NativePortalIdentity($this->ci->db,db_prefix(),$secret);$identity=$identities->current($this->ci->session,$issuer);if(!$identity)throw new DomainException('company_portal_invoice_unavailable');
  $portal=new CompanyPortal($this->ci->db,db_prefix(),$secret);$documents=new CompanyPortalInvoices($this->ci->db,db_prefix(),$portal);
  $row=IssuerStorage::query($this->ci->db,'SELECT hash,issuer_id FROM '.db_prefix().'invoices WHERE id=? AND clientid=?',[(int)$id,$identity['binding']['client_id']])->row_array();
  if(!$row||!is_string($row['hash'])||!hash_equals($row['hash'],$hash))throw new DomainException('company_portal_invoice_unavailable');
  $legacy=null;$legacyState=null;if($row['issuer_id']===null){require_once __DIR__.'/NativePortalLegacyInvoice.php';$legacy=new NativePortalLegacyInvoice($this->ci->db,db_prefix(),$portal);$legacyState=$legacy->read($identity['binding'],$issuer,(int)$id,$hash);$data=$legacyState['document'];}
  else{if((int)$row['issuer_id']!==$issuer)throw new DomainException('company_portal_invoice_unavailable');$document=$documents->document($identity['binding'],$issuer,$id);$data=$document->data();}
  if($identities->current($this->ci->session,$issuer)!==$identity)throw new DomainException('company_portal_invoice_unavailable');
  $bbInstrument=null;$paymentAction=$this->ci->input->method(true)==='POST'?$this->ci->input->post('native_payment_action'):null;
  if($paymentAction!==null){
   PortalRequestScope::assertMutation($this->ci);if($legacy)throw new DomainException('company_portal_invoice_unavailable');
   if($paymentAction==='bb'&&$this->ci->db->table_exists(db_prefix().'bb_checkout_publications')){require_once FCPATH.'modules/banco_brasil/libraries/BancoBrasil/Checkout.php';$bbInstrument=(new \BancoBrasil\Checkout($this->ci->db,db_prefix()))->portalInstrument($portal,$identity['binding'],$issuer,$id);}
   elseif($paymentAction==='asaas'&&$this->ci->db->table_exists(db_prefix().'asaas_company_hosted_links')){require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyHostedLinks.php';$link=(new \Asaas\CompanyHostedLinks($this->ci->db,db_prefix()))->portalLink($portal,$identity['binding'],$issuer,$id);$this->verified($identity,$identities,$issuer);$this->ci->output->set_header('Referrer-Policy: no-referrer');redirect($link['url'],'location',303);return;}
   else throw new DomainException('company_portal_invoice_unavailable');
  }
  elseif($this->ci->input->method(true)==='POST'){
   PortalRequestScope::assertMutation($this->ci);$receiptId=$this->ci->input->post('paymentpdf');$invoicePdf=$this->ci->input->post('invoicepdf');if(!class_exists('TCPDF'))require_once APPPATH.'vendor/tecnickcom/tcpdf/tcpdf.php';
   if($invoicePdf==='invoicepdf'&&$receiptId===null){if($legacy){require_once __DIR__.'/NativePortalLegacyInvoicePdf.php';$bytes=NativePortalLegacyInvoicePdf::render($data);}else{require_once APPPATH.'libraries/pdf/Issuer_invoice_pdf.php';$bytes=(new Issuer_invoice_pdf($document))->prepare()->Output('','S');}$filename='invoice.pdf';}
   elseif($invoicePdf===null&&is_string($receiptId)&&preg_match('/^[1-9][0-9]*$/D',$receiptId)){
    $matches=array_values(array_filter($legacy?$legacyState['payments']:$documents->payments($identity['binding'],$issuer,$id),static fn($row)=>(string)$row['id']===$receiptId));if(count($matches)!==1)throw new DomainException('company_portal_invoice_unavailable');$payment=$matches[0];
    $pdf=new TCPDF();$pdf->setPrintHeader(false);$pdf->setPrintFooter(false);$pdf->SetFont('dejavusans','',10);$pdf->AddPage();$escape=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $html='<h2>'.$escape(_l('payment')).' #'.$payment['id'].'</h2><p>'.$escape($data['identity']['legal_name']).'<br>'.$escape($data['recipient']['name']).'</p><p>'.$escape($data['number']).'<br>'.$escape(_l('payment_date')).' '.$escape($payment['date']).'<br>'.$escape(_l('payment_mode')).': '.$escape($payment['paymentmode']).'</p><p>'.$escape(_l('payment_total_amount')).': '.$escape($data['currency'].' '.$payment['original']).'<br>'.$escape(_l('invoice_adjustment')).': '.$escape($data['currency'].' '.$payment['reversed']).'<br>'.$escape(_l('invoice_total_paid')).': '.$escape($data['currency'].' '.$payment['effective']).'</p>';
    $pdf->writeHTML($html);$bytes=$pdf->Output('','S');$filename='payment-'.$payment['id'].'.pdf';
   }else throw new DomainException('company_portal_invoice_unavailable');
   if(!is_string($bytes)||!str_starts_with($bytes,'%PDF-'))throw new RuntimeException('company_portal_invoice_pdf_invalid');$this->verified($identity,$identities,$issuer);
   if($legacy)$legacy->confirm($identity['binding'],$issuer,(int)$id,$hash,$legacyState,hash('sha256',$bytes),isset($payment)?$payment['id']:null);elseif(isset($payment))$documents->confirmPaymentReceipt($identity['binding'],$issuer,$id,$payment,hash('sha256',$bytes));else $documents->confirmDownload($identity['binding'],$issuer,$id,hash('sha256',$bytes));
   $this->verified($identity,$identities,$issuer);
   $this->ci->output->set_header('Cache-Control: private, no-store')->set_header('X-Content-Type-Options: nosniff')->set_header('Content-Disposition: attachment; filename="'.$filename.'"')->set_content_type('application/pdf')->set_output($bytes);return;
  }
  $payments=$legacy?$legacyState['payments']:$documents->payments($identity['binding'],$issuer,$id);if($identities->current($this->ci->session,$issuer)!==$identity)throw new DomainException('company_portal_invoice_unavailable');
  $attachments=$legacy?$legacy->attachments($identity['binding'],$issuer,(int)$id,$hash):$documents->attachments($identity['binding'],$issuer,$id);$available=['bb'=>false,'asaas'=>false];if(!$legacy&&$this->ci->db->table_exists(db_prefix().'bb_checkout_publications')){require_once FCPATH.'modules/banco_brasil/libraries/BancoBrasil/Checkout.php';$available['bb']=!empty((new \BancoBrasil\Checkout($this->ci->db,db_prefix()))->portalPage($portal,$identity['binding'],$issuer,[(int)$id])[(int)$id]);}if(!$legacy&&$this->ci->db->table_exists(db_prefix().'asaas_company_hosted_links')){require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyHostedLinks.php';$available['asaas']=!empty((new \Asaas\CompanyHostedLinks($this->ci->db,db_prefix()))->portalPage($portal,$identity['binding'],$issuer,[(int)$id])[(int)$id]);}if($identities->current($this->ci->session,$issuer)!==$identity)throw new DomainException('company_portal_invoice_unavailable');
  if($bbInstrument!==null){$bbInstrument=(new \BancoBrasil\Checkout($this->ci->db,db_prefix()))->portalInstrument($portal,$identity['binding'],$issuer,$id);}$this->verified($identity,$identities,$issuer);
  $this->ci->data(['title'=>$data['number'],'native_portal_invoice_document'=>$data,'native_portal_invoice_payments'=>$payments,'native_portal_invoice_attachments'=>$attachments,'native_portal_payment_available'=>$available,'native_portal_bb_instrument'=>$bbInstrument,'native_portal_invoice_hash'=>$hash,'native_portal_csrf'=>PortalRequestScope::csrfToken($this->ci)]);$this->ci->view('invoicehtml');$this->ci->layout();
 }
}
