<?php
require __DIR__.'/native_invoice_editor_items.php';
require_once APPPATH.'services/NativeCompanyCatalogUi.php';
$parts=NativeCompanyAuxiliarySchema::definitions($p)['company_taxes'];$ddl='CREATE TABLE '.$p.'company_taxes('.implode(',',$parts).') ENGINE=InnoDB';if($db->driver()==='sqlite')$ddl=preg_replace('/ CHARACTER SET utf8mb4 COLLATE utf8mb4_bin/','',$ddl);$db->query($ddl);
try {
$db->query('INSERT INTO '.$p.'company_taxes(id,issuer_id,name,taxrate,revision) VALUES(1,?,?,?,1), (2,?,?,?,1)',[$a,'Current A tax','3.0000',$b,'Private B tax','7.0000']);
$payload=$draftInput;$payload['items'][0]['description']='Synthetic </textarea><script>bad</script>';$payload['items'][0]['long_description']='Synthetic </textarea><img src=x>';$payload['items'][0]['unit']='" onfocus="bad';
$draft=(new CompanyInvoiceDrafts($db,$p))->create($a,7,$payload,hash('sha256','editor-real-tax-source'));
$native_invoice_editor_items=NativeCompanyInvoiceEditorItems::load($db,$p,$a,7,NativeCompanyResourceId::encode('company_invoice_draft',$draft));$invoice=(object)['items'=>$native_invoice_editor_items->rows()];
if(!function_exists('_l')){function _l($key){return$key;}}
if(!function_exists('e')){function e($value){return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}}
if(!function_exists('clear_textarea_breaks')){function clear_textarea_breaks($value){return trim(str_ireplace(['<br />','<br>','<br/>'],'',$value));}}
if(!function_exists('form_hidden')){function form_hidden($name,$value){return '<input type="hidden" name="'.e($name).'" value="'.e($value).'">';}}
if(!function_exists('app_format_number')){function app_format_number($value){return number_format($value,2,'.','');}}
if(!function_exists('get_invoice_item_taxes')){function get_invoice_item_taxes($id){throw new RuntimeException('Legacy tax lookup forbidden');}}
$template=file_get_contents(APPPATH.'views/admin/invoices/invoice_template.php');$start=strpos($template,'<?php if (isset($invoice) || isset($add_items)) {');$end=strpos($template,'?>',$start);if($start===false||$end===false)throw new RuntimeException('Original editor row block missing');
// Evaluate the exact original production row block; formatting inputs are explicit seams.
$code=substr($template,$start+5,$end-$start-5);ob_start();eval($code);$html=ob_get_clean();
essentialCheck(str_contains($html,'items[1][itemid]')&&str_contains($html,(string)$invoice->items[0]['id']),'original editor row preserves real draft source ID and native field names');
essentialCheck(str_contains($html,'&lt;/textarea&gt;&lt;script&gt;bad&lt;/script&gt;')&&!str_contains($html,'<script>bad'),'original editor description remains escaped through actual row renderer');
essentialCheck(str_contains($html,'&quot; onfocus=&quot;bad')&&!str_contains($html,'value="" onfocus='),'original unit value cannot escape its input attribute');
essentialCheck(str_contains($html,'Current A tax')&&!str_contains($html,'Private B tax'),'real catalog dropdown selects current issuer only');
$historical=$invoice->items[0]['taxname'][0]['taxname'];$parts=explode('|',$historical);$expected=$parts[0].'|'.number_format((float)$parts[1],4,'.','');
essentialCheck(str_contains($html,'value="'.e($expected).'" selected'),'historical owned line tax survives catalog absence and remains selected');
$forged=$invoice->items[0];$forged['taxname'][]=['taxname'=>'Private fake|99.0000','taxrate'=>'99.0000'];essentialThrows(fn()=>$native_invoice_editor_items->taxDropdown('items[1][taxname][]',$forged),'native_invoice_editor_mismatch','arbitrary historical tax cannot be injected through forged DTO');
echo 'Original native editor rows with real issuer tax catalog: '.$GLOBALS['essentialChecks']." cumulative PASS; not complete page/browser proof.\n";

} finally { $db->query('DROP TABLE IF EXISTS '.$p.'company_taxes'); }
