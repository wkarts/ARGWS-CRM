<?php
require __DIR__.'/native_invoice_item_custom_fields.php';
require_once APPPATH.'services/NativeCompanyInvoiceItemsPresentation.php';
require_once APPPATH.'vendor/autoload.php';
require_once APPPATH.'libraries/App_items_table.php';
if(!function_exists('e')){function e($v){return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}}
if(!function_exists('_l')){function _l($v,...$unused){return$v;}}
if(!function_exists('get_option')){function get_option($key){return ['show_tax_per_item'=>'1','remove_tax_name_from_item_table'=>'0','items_table_amounts_exclude_currency_symbol'=>'0'][$key]??'';}}
if(!function_exists('app_format_money')){function app_format_money($v,$c,...$unused){return$c.' '.$v;}}
if(!function_exists('app_format_number')){function app_format_number($v){return(string)$v;}}
if(!function_exists('multiple_taxes_found_for_item')){function multiple_taxes_found_for_item($v){return count($v)>1;}}
$GLOBALS['essentialHooks']=new class {public function apply_filters(...$args){throw new RuntimeException('Global hooks forbidden in scoped item renderer');}};
$presentation=NativeCompanyInvoiceItemsPresentation::load($db,$p,$a,7,$branded);
$transaction=$presentation->transaction();
$table=new App_items_table($transaction,'invoice','html',true);$html=$table->table();
essentialCheck(str_contains($html,'data-item-id="'.$line.'"')&&str_contains($html,'data-native-invoice-id="'.$branded.'"'),'same native App_items_table renders actual storage IDs and parent identity');
essentialCheck(str_contains($html,'Real line, not catalog item')&&str_contains($html,'Synthetic invoice line field'),'same native table hydrates scoped line custom fields');
essentialCheck(str_contains($html,'data-native-invoice-revision="'.$s->revision($a,7,$branded).'"'),'same native preview carries persisted reorder CAS');
$transaction->items[0]['rate']='0.00';essentialThrows(fn()=>new App_items_table($transaction,'invoice','html',true),'native_invoice_items_projection_mismatch','mutated DTO cannot render forged money');
essentialThrows(fn()=>NativeCompanyInvoiceItemsPresentation::load($db,$p,$b,7,$branded),'company_invoice_access_denied','foreign issuer cannot assemble native item renderer');
echo 'Actual native App_items_table scoped projection: '.$GLOBALS['essentialChecks']." cumulative PASS; formatting helpers are test doubles, not browser proof.\n";
$legacyCalls=[];
$GLOBALS['essentialHooks']=new class {public array $names=[];public function apply_filters($name,$value,...$args){$this->names[]=$name;return$value;}};
if(!function_exists('get_items_custom_fields_for_table_html')){function get_items_custom_fields_for_table_html($id,$type){return[];}}
if(!function_exists('get_invoice_item_taxes')){function get_invoice_item_taxes($id){return[];}}
if(!function_exists('process_text_content_for_display')){function process_text_content_for_display($v){return e($v);}}
$legacy=$presentation->transaction();unset($legacy->native_items_presentation);
$legacyHtml=(new App_items_table($legacy,'invoice','html',true))->table();
essentialCheck(in_array('item_preview_rate',$GLOBALS['essentialHooks']->names,true)&&!str_contains($legacyHtml,'data-native-invoice-revision'),'original legacy renderer keeps hooks and no invented scoped CAS');
$transaction=$presentation->transaction();$transaction->native_items_presentation=new stdClass();
essentialThrows(fn()=>new App_items_table($transaction,'invoice','html',true),'native_invoice_items_projection_mismatch','untyped injected marker cannot suppress native hooks');
echo 'Native/legacy shared renderer differential: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
$transaction=$presentation->transaction();$transaction->recurring_type='month';$transaction->is_recurring_from=999999;
$GLOBALS['CI']->invoices_model=new class {public function get($id){throw new RuntimeException('Global recurrence hydration forbidden');}};
(new App_items_table($transaction,'invoice','html',true))->table();
essentialCheck(true,'extra recurring metadata cannot trigger legacy model from canonical named content');
$optionalInput=$input;$optionalInput['client_id']=$ca;$optionalInput['items'][]=['description'=>'Visible optional unselected line','qty'=>'1.00','rate'=>'9.99','is_optional'=>1,'is_selected'=>0];$optionalId=$s->create($a,7,$optionalInput,hash('sha256','renderer-all-optional'));
$allPresentation=NativeCompanyInvoiceItemsPresentation::load($db,$p,$a,7,$optionalId);$allHtml=(new App_items_table($allPresentation->transaction(),'invoice','html',true))->table();
essentialCheck(str_contains($allHtml,'Visible optional unselected line')&&str_contains($allHtml,'opacity:0.5'),'same original renderer displays optional unselected line and native dimming');
$readOnly=$s->previewItems($a,7,$optionalId);essentialCheck(count($readOnly)===2&&count($s->document($a,7,$optionalId)->data()['items'])===1,'HTML preview full rows remain separate from charged PDF totals');
$db->query("UPDATE {$p}issuers_staff SET can_view=1 WHERE issuer_id=? AND staff_id=12",[$a]);essentialCheck(count($s->previewItems($a,12,$optionalId))===2,'view-only actor can read real preview lines without edit permission');
echo 'Optional-line native preview fidelity: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
