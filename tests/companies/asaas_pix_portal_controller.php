<?php
/** Real native invoice controller + MySQL; no provider calls or generated QR. */
require __DIR__.'/asaas_pix_consent.php';
require __DIR__.'/native_portal_http_fixture.php';
require_once APPPATH.'services/NativePortalIdentity.php';
require_once APPPATH.'controllers/Invoice.php';
function show_404(){throw new DomainException('fixture_http_404');}
class PixPortalInvoiceFixture extends Invoice{public function layout($notInThemeViewFiles=false){if($this->view!=='invoicehtml')throw new LogicException('unexpected view');extract($this->data);include APPPATH.'views/themes/perfex/views/invoicehtml.php';}}
$start=$GLOBALS['essentialChecks'];
$invoice=$s->create($b,7,array_replace($input,['client_id'=>$customerB]),hash('sha256','pix-portal-http'));
$origin=$asaas->reservePixAuthorization($b,7,$invoice,$accounts[$b],'cus_b',$s->revision($b,7,$invoice),hash('sha256','pix-portal-http-key'),'MONTHLY',$input['date']);
$hash=$db->query('SELECT hash FROM '.$p.'invoices WHERE id=?',[$invoice])->row()->hash;
$detail=(new ReflectionClass(PixPortalInvoiceFixture::class))->newInstanceWithoutConstructor();foreach(get_object_vars($auth)as$key=>$value)$detail->$key=$value;
(new NativePortalIdentity($db,$p,str_repeat('synthetic-',4)))->establish($detail->session,$b,$sessions[$b]);
$detail->router->class='invoice';$detail->router->method='index';$detail->input->get=['issuer_id'=>(string)$b];$detail->input->verb='GET';$detail->input->post=[];$detail->data=[];
essentialCheck(PortalRequestScope::prepare($detail)&&PortalRequestScope::resolve($detail),'native Pix route resolves verified private contact');
$render=function()use($detail,$invoice,$hash){ob_start();try{$detail->index((string)$invoice,$hash);return ob_get_contents();}finally{ob_end_clean();}};
$html=$render();if(($snapshot=getenv('ASAAS_PIX_PORTAL_HTML'))!==false&&$snapshot!=='')file_put_contents($snapshot,$html);essentialCheck(str_contains($html,'native-pix-consent-title')&&str_contains($html,'pix_consent_revision')&&str_contains($html,'pix_consent_accept'),'original invoice contains scoped review and explicit checkbox');
essentialCheck(!str_contains($html,'checked')&&!str_contains($html,'encodedImage'),'no preselected consent or payable QR');
essentialCheck(!$db->query('SELECT id FROM '.$p.'asaas_company_proofs WHERE origin_id=? AND operation=?',[$origin,'pix_consent'])->row(),'GET has no consent side effect');
$review=$consent->review($portal,$sessions[$b],$b,$invoice);
$detail->input->verb='POST';$detail->input->post=['native_payment_action'=>'asaas_pix_consent','pix_consent_revision'=>$review['revision'],'pix_consent_accept'=>'1'];
essentialThrows($render,'fixture_http_404','HTTP acceptance requires native CSRF');
$detail->input->post['_native_portal_csrf']=PortalRequestScope::csrfToken($detail);
foreach([null,'0','false',['1']] as $invalid){$detail->input->post['pix_consent_accept']=$invalid;essentialThrows($render,'fixture_http_404','only exact checkbox value accepted');}
$detail->input->post['pix_consent_accept']='1';$detail->input->post['pix_consent_revision']=str_repeat('0',64);essentialThrows($render,'fixture_http_404','stale HTTP review denied');$detail->input->post['pix_consent_revision']=$review['revision'];
try{$render();throw new RuntimeException('missing redirect');}catch(NativePortalRedirect $redirect){essentialCheck($redirect->status===303&&str_contains($redirect->url,'invoice/'.$invoice.'/'.$hash)&&str_contains($redirect->url,'issuer_id='.$b),'accepted POST redirects to canonical same-company invoice');}
try{$render();throw new RuntimeException('missing retry redirect');}catch(NativePortalRedirect $redirect){essentialCheck($redirect->status===303,'POST retry remains idempotent');}
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'asaas_company_proofs WHERE origin_id=? AND operation=?',[$origin,'pix_consent'])->row()->n===1,'HTTP retries create one consent proof');
$detail->input->verb='GET';$detail->input->post=[];$html=$render();essentialCheck(str_contains($html,'Aceite registrado')&&!str_contains($html,'name="pix_consent_accept"'),'accepted review displayed without new implicit consent');
essentialCheck(in_array('Cache-Control: private, no-store',$detail->output->headers,true),'private terms are not cached');
essentialCheck(!$db->query('SELECT id FROM '.$p.'asaas_company_proofs WHERE origin_id=? AND operation=?',[$origin,'pix_send'])->row()&&!$db->query('SELECT id FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$invoice])->row(),'HTTP consent neither sends a mandate nor writes payment');
$detail->input->get=['issuer_id'=>(string)$a];essentialCheck(PortalRequestScope::prepare($detail)&&PortalRequestScope::resolve($detail),'foreign context is intercepted');essentialThrows($render,'fixture_http_404','known foreign invoice never exposes terms');
$qrCalls=0;$qrTransport=function(...$args)use($sender,&$qrCalls){$qrCalls++;return $sender(...$args);};
$qr=fn()=>$consent->instrument($portal,$sessions[$b],$b,$sendInvoice,$sendReview['revision'],$dec,$qrTransport);
$private=$qr();essentialCheck($private['payload']==='000201synthetic-never-a-real-code'&&!isset($private['encoded_image']),'private QR payload recovered without embedding arbitrary provider image');
$evidence=$db->query('SELECT result_json FROM '.$p.'asaas_company_proofs WHERE origin_id=?',[$sendOrigin])->result_array();essentialCheck(!str_contains(json_encode($evidence),'synthetic-never-a-real-code'),'QR is never written to durable proof');
$before=$qrCalls;$db->query('UPDATE '.$p.'contacts SET email_verified_at=NULL WHERE id=?',[$contactIds[$b]]);essentialThrows($qr,'asaas_pix_portal_denied','revoked private identity cannot recover QR');essentialCheck($qrCalls===$before,'revoked contact triggers no provider lookup');$db->query('UPDATE '.$p.'contacts SET email_verified_at=? WHERE id=?',['2026-01-01 00:00:00',$contactIds[$b]]);
$reply['immediateQrCode']['expirationDate']='2020-01-01T12:00:00-03:00';essentialThrows($qr,'asaas_pix_qr_unavailable','expired QR is not delivered');
echo 'Pix native portal controller: '.($GLOBALS['essentialChecks']-$start).' additional MySQL/controller checks; synthetic provider GET for private QR, no real financial operations.'.PHP_EOL;
