<?php
require __DIR__.'/native_invoice_draft_creation_tags.php';
require_once APPPATH.'services/NativeCompanyInvoiceEditorItems.php';
$field=NativeCompanyResourceId::encode('company_custom_field',$db->query("SELECT id FROM {$p}company_custom_fields WHERE issuer_id=? AND slug='draft_line_metadata'",[$a])->row_array()['id']);
$editor=NativeCompanyInvoiceEditorItems::load($db,$p,$a,7,$canonical);$rows=$editor->rows();
essentialCheck(count($rows)===count($draftInput['items'])&&NativeCompanyResourceId::decode('invoice_item',$rows[0]['id'])['source_kind']==='company_invoice_draft_line','editor uses persisted draft line identities for the original item wire');
IssuerTransaction::run($db,$p,array_merge($fields->tableNames(),(new CompanyInvoiceDrafts($db,$p))->tables()),fn()=>$fields->saveInOwnedTransaction($a,7,'items',$rows[0]['id'],[$field=>'Synthetic </textarea><script>secret</script>']));
$new=NativeCompanyInvoiceEditorItems::load($db,$p,$a,12);
essentialCheck(count($new->definitions())>0&&$new->rows()===[],'create-only invoice actor gets legitimate new line definitions without catalogue permission');
essentialThrows(fn()=>NativeCompanyInvoiceEditorItems::load($db,$p,$b,7,$canonical),'company_invoice_draft_unavailable','foreign parent cannot grant editor definitions');
essentialThrows(fn()=>NativeCompanyInvoiceEditorItems::load($db,$p,$a,12,$canonical),'native_custom_field_denied','create-only actor cannot edit an existing draft');
$fake=$rows[0];$fake['id']=$rows[1]['id'];essentialThrows(fn()=>$editor->assertItem($fake),'native_invoice_editor_mismatch','mutated line DTO cannot borrow another persisted ID');
if(!class_exists('CompanyRequestScope',false)){class CompanyRequestScope{public static int $issuer;public static int $actor=7;public static bool $native=true;public static function nativeFor($ci){return self::$native;}public static function context(){return new class{public function issuerId(){return CompanyRequestScope::$issuer;}public function staffId(){return CompanyRequestScope::$actor;}};}}}
CompanyRequestScope::$issuer=$a;$GLOBALS['CI']=(object)['db'=>$db];
if(!function_exists('is_client_logged_in')){function is_client_logged_in(){return false;}}
if(!class_exists('NativeCompanyAdminPresentation',false)){class NativeCompanyAdminPresentation{public static function label($key){return$key;}}}
if(!function_exists('render_input')){function render_input($name,$label,$value,$type,$attrs){return '<input name="'.htmlspecialchars($name,ENT_QUOTES).'" value="'.htmlspecialchars($value,ENT_QUOTES).'">';}}
if(!function_exists('form_error')){function form_error($name){return '';}}
require APPPATH.'helpers/custom_fields_helper.php';
$html=render_custom_fields_items_table_in($rows[0],'items[1]',$editor);
essentialCheck(str_contains($html,'items[1][custom_fields][items]['.$field.']')&&str_contains($html,'&lt;script&gt;')&&!str_contains($html,'<script>'),'original applied-field renderer keeps native wire and escapes persisted value');
CompanyRequestScope::$actor=12;$html=render_custom_fields_items_table_add_edit_preview($new);
essentialCheck(str_contains($html,'data-id="'.$field.'"'),'original new-line renderer keeps native custom field cell');
CompanyRequestScope::$issuer=$b;essentialThrows(fn()=>render_custom_fields_items_table_add_edit_preview($new),'native_invoice_editor_mismatch','typed editor cannot cross current request issuer');
CompanyRequestScope::$issuer=$a;CompanyRequestScope::$native=false;essentialThrows(fn()=>render_custom_fields_items_table_add_edit_preview($new),'native_custom_field_denied','typed editor cannot fall through to legacy global query');CompanyRequestScope::$native=true;CompanyRequestScope::$actor=7;
$issued=NativeCompanyInvoiceEditorItems::load($db,$p,$a,7,$branded);
$stored=$db->query("SELECT id FROM {$p}itemable WHERE rel_type='invoice' AND rel_id=? ORDER BY item_order,id",[$branded])->result_array();
essentialCheck(array_column($issued->rows(),'id')===array_map('intval',array_column($stored,'id')),'issued editor retains actual itemable IDs rather than draft or catalogue identifiers');
essentialThrows(fn()=>$issued->assertContext($db,$p,$b,7),'native_invoice_editor_mismatch','issued editor also rejects context substitution');
$template=file_get_contents(APPPATH.'views/admin/invoices/invoice_template.php');
essentialCheck(str_contains($template,'render_custom_fields_items_table_add_edit_preview($native_invoice_editor_items ?? null)')&&str_contains($template,"render_custom_fields_items_table_in(\$item, \$items_indicator . '[' . \$i . ']', \$native_invoice_editor_items ?? null)"),'same original invoice template supplies typed DTO to both real field renderers');
$drafts=new CompanyInvoiceDrafts($db,$p);$drafts->change($a,7,$id,1,$draftInput,hash('sha256','editor-stale-after-change'));
essentialThrows(fn()=>$editor->rows(),'company_invoice_draft_stale','stale editor cannot hydrate previous revision after edit');
echo 'Original native invoice editor item cells: '.$GLOBALS['essentialChecks']." cumulative PASS; rendering primitive is a deterministic seam, not browser evidence.\n";
