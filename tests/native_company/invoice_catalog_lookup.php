<?php
require __DIR__.'/catalog_invoice_editor.php';require_once APPPATH.'services/NativeCompanyInvoiceCatalogLookup.php';
$fields=new NativeCompanyCustomFields($db,$p);$field=$fields->saveDefinition(41,7,['fieldto'=>'items','name'=>'Invoice catalogue field','type'=>'input'],null,null,str_repeat('c',32))['id'];
IssuerTransaction::run($db,$p,$fields->tableNames(),fn()=>$fields->saveInOwnedTransaction(41,7,'items_pr',$id,[$field=>'Owned selection value']));
$lookup=new NativeCompanyInvoiceCatalogLookup($db,$p,41,12,'','');$projection=$lookup->fields($id);
essentialCheck($projection['values'][$field]==='Owned selection value'&&$projection['definitions'][0]['id']===$field,'invoice create authority reads owned catalogue customfield values without items view');
settingThrows(fn()=>$fields->values(41,12,'items_pr',$id),'native_custom_field_denied','concrete editor does not promote ordinary customfield catalogue lookup');
settingThrows(fn()=>$lookup->fields('1'),'company_catalog_denied','raw principal item is not selected from a named invoice editor');
settingThrows(fn()=>new NativeCompanyInvoiceCatalogLookup($db,$p,82,12,'',''),'company_invoice_access_denied','invoice editor context has no cross-company grant');
settingThrows(fn()=>new NativeCompanyInvoiceCatalogLookup($db,$p,41,12,'',str_repeat('a',64)),'company_invoice_stale','new editor cannot carry invented existing revision');
$db->query('DELETE FROM '.$p.'issuers_resource_permissions WHERE issuer_id=41 AND staff_id=12 AND feature=? AND capability=?',['invoices','create']);
settingThrows(fn()=>$lookup->fields($id),'company_invoice_access_denied','revocation denies already constructed lookup before values');
echo "Invoice catalogue lookup: six real SQLite parent/field/lineage/ACL checks passed.\n";
$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(41,12,?,?)',['invoices','create']);
if(!class_exists('CompanyRequestScope',false)){class CompanyRequestScope{public static int $issuer=41;public static int $actor=12;public static bool $native=true;public static bool $csrf=true;public static function assertNativeMutation($ci){if(!$GLOBALS['lookupPost']||!self::$csrf)throw new DomainException('lookup_boundary_denied');}public static function nativeFor($ci){return self::$native;}public static function context(){return new class{public function issuerId(){return CompanyRequestScope::$issuer;}public function staffId(){return CompanyRequestScope::$actor;}};}}}
$GLOBALS['CI']=(object)['db'=>$db];
if(!function_exists('get_instance')){function &get_instance(){return $GLOBALS['CI'];}}
if(!function_exists('is_client_logged_in')){function is_client_logged_in(){return false;}}
if(!class_exists('NativeCompanyAdminPresentation',false)){class NativeCompanyAdminPresentation{public static function label($key){return$key;}}}
if(!function_exists('render_input')){function render_input($name,$label,$value,$type,$attrs){return '<input name="'.htmlspecialchars($name,ENT_QUOTES).'" value="'.htmlspecialchars($value,ENT_QUOTES).'">';}}
if(!function_exists('form_error')){function form_error($name){return '';}}
require APPPATH.'helpers/custom_fields_helper.php';
$html=render_custom_fields('items',$id,[],['items_pr'=>true,'native_invoice_catalog'=>$lookup]);
essentialCheck(str_contains($html,'Owned selection value')&&str_contains($html,(string)$field),'original renderer uses typed catalogue values without items permission');
CompanyRequestScope::$issuer=82;settingThrows(fn()=>render_custom_fields('items',$id,[],['items_pr'=>true,'native_invoice_catalog'=>$lookup]),'native_invoice_editor_mismatch','renderer rejects substituted current company');
CompanyRequestScope::$issuer=41;CompanyRequestScope::$native=false;settingThrows(fn()=>render_custom_fields('items',$id,[],['items_pr'=>true,'native_invoice_catalog'=>$lookup]),'native_custom_field_denied','typed lookup never falls through to legacy renderer');
echo "Invoice catalogue original helper: three real-renderer checks, primitive input seam, no browser.\n";

CompanyRequestScope::$native=true;
if(!class_exists('AdminController',false)){class AdminController{public $db;public $input;}}
require APPPATH.'controllers/admin/Invoice_items.php';
$controller=(new ReflectionClass(Invoice_items::class))->newInstanceWithoutConstructor();$controller->db=$db;
$controller->input=new class{public array $data=[];public function get($key){return$this->data[$key]??null;}public function post($key){return$this->data[$key]??null;}};
$property=new ReflectionProperty(Invoice_items::class,'companyCatalog');$property->setAccessible(true);$property->setValue($controller,new NativeCompanyCatalogUi($db,$p,41,12,$controller));
$controller->input->data=['native_editor'=>'invoice','native_invoice_id'=>'','native_invoice_revision'=>'','q'=>'Secondary'];$GLOBALS['lookupPost']=true;
ob_start();$controller->search();$json=ob_get_clean();essentialCheck(in_array($id,array_column(json_decode($json,true,32,JSON_THROW_ON_ERROR),'id'),true),'actual search action keeps same URL and invoice-only catalogue authority');
ob_start();$controller->get_item_by_id($id);$json=ob_get_clean();$decoded=json_decode($json,true,32,JSON_THROW_ON_ERROR);essentialCheck($decoded['custom_fields'][0]['value']==='Owned selection value'&&str_contains($decoded['custom_fields_html'],'Owned selection value'),'actual item action supplies original customfields JSON and HTML');
CompanyRequestScope::$csrf=false;settingThrows(fn()=>$controller->search(),'lookup_boundary_denied','actual search rejects CSRF before lookup');CompanyRequestScope::$csrf=true;$GLOBALS['lookupPost']=false;settingThrows(fn()=>$controller->search(),'lookup_boundary_denied','actual search rejects GET transport before lookup');
$controller->input->data['native_editor']='arbitrary';settingThrows(fn()=>$controller->get_item_by_id($id),'company_catalog_invalid','unknown authority discriminator cannot elevate catalogue');
echo "Original Invoice_items controller: five real-service/SQLite checks, framework transport doubles.\n";
