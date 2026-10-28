<?php
/** Native database/provider fixture; no real bank HTTP, activation or credentials. */
require __DIR__.'/banco_brasil_api16_components_portal_mysql.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/Checkout.php';
\BancoBrasil\CheckoutSchema::ensure($db,$p);\BancoBrasil\CheckoutSchema::ensure($db,$p);
$providerAccount=123873;$behavior='normal';$afterHttp=null;
$checkoutInvoice=$s->create($a,7,$apiInput,hash('sha256','checkout-native-invoice'));
$checkoutOrigin=$api->issue($a,7,$profile,$checkoutInvoice,$address,hash('sha256','checkout-native-origin'));
$checkout=new \BancoBrasil\Checkout($db,$p);$httpBefore=count($http);
$instrument=$checkout->staffInstrument($a,7,$checkoutOrigin);
essentialCheck($instrument['invoice_id']===$checkoutInvoice&&strlen($instrument['line'])===47,'native registered instrument is projected from its immutable origin');
essentialThrows(fn()=>$checkout->staffInstrument($b,7,$checkoutOrigin),'bb_api_origin_unavailable','foreign company cannot read the instrument');
essentialThrows(fn()=>$checkout->publish($a,7,$checkoutOrigin,str_repeat('0',64),true),'bb_checkout_stale','stale review cannot publish');
$nativeInvoice=$db->query("SELECT clientid,currency,total,duedate FROM {$p}invoices WHERE id=?",[$checkoutInvoice])->row_array();
essentialCheck((int)$nativeInvoice['clientid']===(int)$ca,'checkout fixture belongs to the authenticated native contact client');
foreach(['clientid'=>(int)$nativeInvoice['clientid']+100000,'currency'=>(int)$nativeInvoice['currency']+100000,'total'=>'106.53','duedate'=>'2031-01-01'] as $field=>$changed){
    $db->query("UPDATE {$p}invoices SET {$field}=? WHERE id=?",[$changed,$checkoutInvoice]);
    essentialThrows(fn()=>$checkout->staffInstrument($a,7,$checkoutOrigin),'bb_checkout_unavailable','changed invoice '.$field.' cannot reuse the old payer instrument');
    $db->query("UPDATE {$p}invoices SET {$field}=? WHERE id=?",[$nativeInvoice[$field],$checkoutInvoice]);
}
$issue=$db->query("SELECT id,result_json FROM {$p}bb_api_operations WHERE origin_id=? AND operation='issue' AND status='validated'",[$checkoutOrigin])->row_array();
$db->query("UPDATE {$p}bb_api_operations SET status='unknown' WHERE id=?",[$issue['id']]);
essentialThrows(fn()=>$checkout->staffInstrument($a,7,$checkoutOrigin),'bb_checkout_review_required','uncertain operation cannot disclose an instrument');
$db->query("UPDATE {$p}bb_api_operations SET status='validated' WHERE id=?",[$issue['id']]);
$db->query("UPDATE {$p}bb_api_operations SET operation='query',result_json=? WHERE id=?",[json_encode(['codigoEstadoTituloCobranca'=>'18'],JSON_THROW_ON_ERROR),$issue['id']]);
essentialThrows(fn()=>$checkout->staffInstrument($a,7,$checkoutOrigin),'bb_checkout_review_required','partial bank observation blocks a stale registered barcode');
$db->query("UPDATE {$p}bb_api_operations SET operation='issue',result_json=? WHERE id=?",[$issue['result_json'],$issue['id']]);
$altered=json_decode($issue['result_json'],true,16,JSON_THROW_ON_ERROR);$altered['numero']='00000000000000000';
$db->query("UPDATE {$p}bb_api_operations SET result_json=? WHERE id=?",[json_encode($altered,JSON_THROW_ON_ERROR),$issue['id']]);
essentialThrows(fn()=>$checkout->staffInstrument($a,7,$checkoutOrigin),'bb_checkout_unavailable','stored result must still match immutable origin');
$db->query("UPDATE {$p}bb_api_operations SET result_json=? WHERE id=?",[$issue['result_json'],$issue['id']]);
$checkout->publish($a,7,$checkoutOrigin,$instrument['revision'],true);
$checkout->publish($a,7,$checkoutOrigin,$instrument['revision'],true);
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}bb_checkout_publications WHERE origin_id=?",[$checkoutOrigin])->row()->n===1,'repeated explicit publication keeps one row');
$permission=$access->page($a,7,$ca)[$portalContact];$access->set($a,7,$ca,$portalContact,$permission['revision'],'1');
$foreignPortal=new CompanyPortal(clone $db,$p,str_repeat('synthetic-',4));
essentialThrows(fn()=>$checkout->portalInstrument($foreignPortal,$portalSession,$a,$checkoutInvoice),'company_portal_storage_mismatch','coincident IDs from a different connection never authorize checkout');
$visible=$checkout->portalInstrument($portal,$portalSession,$a,$checkoutInvoice);
essentialCheck($visible['line']===$instrument['line']&&!isset($visible['revision']),'authorized private contact receives only the reviewed instrument');
if(!defined('FCPATH'))define('FCPATH',dirname(__DIR__).'/');require __DIR__.'/companies/native_portal_http_fixture.php';require_once APPPATH.'services/NativePortalIdentity.php';require_once APPPATH.'controllers/Invoice.php';function show_404(){throw new DomainException('fixture_http_404');}
class NativeBbInvoiceFixture extends Invoice{public function layout($notInThemeViewFiles=false){if($this->view!=='invoicehtml')throw new LogicException('unexpected invoice view');extract($this->data);include APPPATH.'views/themes/perfex/views/invoicehtml.php';}}
$db->query('UPDATE '.$p.'contacts SET email_verified_at=? WHERE id=?',['2026-01-01 00:00:00',$portalContact]);$ci=(new ReflectionClass(NativeBbInvoiceFixture::class))->newInstanceWithoutConstructor();foreach(get_object_vars($auth)as$key=>$value)$ci->$key=$value;$ci->db=$db;$ci->router->class='invoice';$ci->router->method='index';$ci->input->verb='GET';$ci->input->get=['issuer_id'=>(string)$a];$ci->input->post=[];$ci->data=[];(new NativePortalIdentity($db,$p,str_repeat('synthetic-',4)))->establish($ci->session,$a,$portalSession);$hash=$db->query('SELECT hash FROM '.$p.'invoices WHERE id=?',[$checkoutInvoice])->row()->hash;
essentialCheck(PortalRequestScope::prepare($ci)&&PortalRequestScope::resolve($ci),'official invoice resolves authorized bank instrument contact');ob_start();$ci->index((string)$checkoutInvoice,$hash);$html=ob_get_clean();essentialCheck(str_contains($html,'value="bb"')&&!str_contains($html,$visible['line']),'official invoice offers only explicitly published instrument before contact action');
$ci->input->verb='POST';$ci->input->post=['native_payment_action'=>'bb'];essentialThrows(fn()=>$ci->index((string)$checkoutInvoice,$hash),'fixture_http_404','bank instrument request requires contact CSRF');$ci->input->post['_native_portal_csrf']=PortalRequestScope::csrfToken($ci);ob_start();$ci->index((string)$checkoutInvoice,$hash);$shown=ob_get_clean();essentialCheck(str_contains($shown,$visible['line'])&&str_contains($shown,'Homologação'),'official invoice renders the exact authorized sandbox instrument');
$ci->input->verb='GET';$ci->input->post=[];$ci->input->get=['issuer_id'=>(string)$b];essentialCheck(PortalRequestScope::prepare($ci)&&PortalRequestScope::resolve($ci),'foreign selected company resolves without adopting contact session');essentialThrows(fn()=>$ci->index((string)$checkoutInvoice,$hash),'fixture_http_404','foreign company cannot access the published official bank instrument');$ci->input->get=['issuer_id'=>(string)$a];essentialCheck(PortalRequestScope::prepare($ci)&&PortalRequestScope::resolve($ci),'owner context resumes');
$permission=$access->page($a,7,$ca)[$portalContact];$access->set($a,7,$ca,$portalContact,$permission['revision'],'0');
essentialThrows(fn()=>$checkout->portalInstrument($portal,$portalSession,$a,$checkoutInvoice),'bb_checkout_access_denied','native contact permission revocation closes checkout');
essentialThrows(fn()=>$ci->index((string)$checkoutInvoice,$hash),'fixture_http_404','revoked contact cannot render the official bank instrument');
$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");
essentialThrows(fn()=>$checkout->staffInstrument($a,7,$checkoutOrigin),'bb_api_access_denied','publisher revocation is not bypassed by persisted publication');
essentialThrows(fn()=>$checkout->publish($a,7,$checkoutOrigin,$instrument['revision'],true),'bb_checkout_access_denied','inactive staff cannot publish persisted instruments');
$db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");
$db->query("UPDATE {$p}bb_origins SET state='api_cancelled_confirmed' WHERE id=?",[$checkoutOrigin]);
$checkout->publish($a,7,$checkoutOrigin,$instrument['revision'],false);
essentialCheck((int)$db->query("SELECT enabled FROM {$p}bb_checkout_publications WHERE origin_id=?",[$checkoutOrigin])->row()->enabled===0,'revocation remains available after the financial instrument becomes unavailable');
essentialCheck(count($http)===$httpBefore,'preview publication portal and revocation perform no provider requests');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$checkoutInvoice])->row()->n===0,'checkout never fabricates payment');
$auditCount=(int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE event='bb_checkout_hidden' AND subject_id=?",[$checkoutOrigin])->row()->n;
$checkout->publish($a,7,$checkoutOrigin,str_repeat('0',64),false);
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE event='bb_checkout_hidden' AND subject_id=?",[$checkoutOrigin])->row()->n===$auditCount,'repeated hide is idempotent including its audit');
echo "BB native checkout publication and isolation passed; simulated provider only.\n";
