<?php
/** Render the supported accounting editor and inspect its actual controls, without a browser. */
require __DIR__.'/native_credit_controller.php';
if(!function_exists('init_head')){function init_head(){}}
if(!function_exists('init_tail')){function init_tail(){}}
CompanyRequestScope::$issuer=$a;$ci->input->verb='GET';$ci->input->gets=[];$ci->output->status=200;
$controller->credit_note();$formData=end($ci->load->views)[1];
$render=function()use($formData){extract($formData,EXTR_SKIP);require APPPATH.'views/admin/credit_notes/native_form.php';};
ob_start();$render->bindTo($ci,null)();$html=ob_get_clean();
$previous=libxml_use_internal_errors(true);$dom=new DOMDocument();$dom->loadHTML($html);libxml_clear_errors();libxml_use_internal_errors($previous);$xpath=new DOMXPath($dom);
essentialCheck($xpath->query('//table[contains(@class,"invoice-items-table")]/thead/tr/th')->length===6&&$dom->getElementById('credit-items') instanceof DOMElement,'actual credit editor renders the accounting item grid');
essentialCheck($dom->getElementById('credit-item-description')->tagName==='textarea'&&$dom->getElementById('credit-item-rate')->getAttribute('name')==='credit[items][0][rate]','grid retains canonical writer item names and a multiline description');
foreach(['credit-client'=>'credit[client_id]','credit-currency'=>'credit[currency]','credit-date'=>'credit[date]','credit-discount'=>'credit[discount_value]','credit-discount-mode'=>'credit[discount_mode]','credit-discount-type'=>'credit[discount_type]','credit-adjustment'=>'credit[adjustment]','credit-note'=>'credit[note]']as$id=>$name)essentialCheck($dom->getElementById($id)->getAttribute('name')===$name,'actual editor roundtrip control '.$id);
essentialCheck($dom->getElementById('credit-number')->hasAttribute('readonly')&&!$dom->getElementById('credit-number')->hasAttribute('name'),'automatic canonical numbering is visible without submitting an unsupported mutable number');
essentialCheck($xpath->query('//*[@data-credit-total]')->length===5&&str_contains($html,'NativeCreditEditor.mount'),'exact client total preview is mounted on the actual form');
essentialCheck($xpath->query('//*[@name="credit[reference_no]" or @name="credit[project_id]" or @name="credit[terms]"]')->length===0,'unsupported metadata is not presented as a working roundtrip control');
$ci->input->verb='HEAD';$beforeViews=count($ci->load->views);$controller->credit_note();essentialCheck($ci->output->body===''&&count($ci->load->views)===$beforeViews,'credit creation HEAD does not emit a form body');
echo 'Native credit supported editor actual DOM: '.$GLOBALS['essentialChecks']." cumulative PASS. No screenshot equivalence claimed.\n";
