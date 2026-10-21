<?php
/** Synthetic unit/native prepare checks. No database credentials, transport, or email is used. */
declare(strict_types=1);
define('BASEPATH',__DIR__);
define('APPPATH',dirname(__DIR__).'/application/');
require APPPATH.'libraries/mails/App_mail_template.php';
require APPPATH.'libraries/merge_fields/App_merge_fields.php';
require dirname(__DIR__).'/modules/purchase/services/PurchaseMailLanguage.php';
require dirname(__DIR__).'/modules/purchase/libraries/merge_fields/Purchase_approve_merge_fields.php';
foreach(glob(dirname(__DIR__).'/modules/purchase/libraries/mails/*.php') as $file)require $file;
$checks=0;
function checkPurchase($ok,$message){global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
$GLOBALS['active_language']='portuguese_br';$GLOBALS['staff_language']='english';$GLOBALS['vendor_rows']=[['id'=>91,'default_language'=>'french']];$GLOBALS['lookups']=0;
function get_option($name){return $name==='active_language'?$GLOBALS['active_language']:'';}
function db_prefix(){return 'synthetic_';}
function get_mail_template_path($class,$params=[]){return dirname(__DIR__).'/modules/purchase/libraries/mails/'.$class.'.php';}
function total_rows($table,$where){return in_array($table,['synthetic_staff','synthetic_contacts'],true)?1:0;}
function get_staff_default_language($id){return $GLOBALS['staff_language'];}
function get_client_default_language($id){return 'german';}
class Emails_model{public function get_for_sending($slug,$language){return (object)['slug'=>$slug,'language'=>$language];}}
$db=new class {public function select(...$args){return $this;}public function where(...$args){return $this;}public function get($table){return new class {function row(){return(object)['staffid'=>12,'userid'=>91,'default_language'=>'french'];}};}};
$GLOBALS['CI']=(object)['db'=>$db,'emails_model'=>new Emails_model];
function &get_instance(){return $GLOBALS['CI'];}
$lookup=static function($email){$GLOBALS['lookups']++;return $GLOBALS['vendor_rows'];};
$GLOBALS['lookup']=$lookup;
$GLOBALS['hooks']=new class {public function apply_filters($name,$value,...$args){return $name==='email_template_language'?PurchaseMailLanguage::resolve((string)$value,$args[0],(string)get_option('active_language'),$GLOBALS['lookup']):$value;}};
function hooks(){return $GLOBALS['hooks'];}
function mailWithoutConstructor($class){$mail=(new ReflectionClass($class))->newInstanceWithoutConstructor();$property=new ReflectionProperty(App_mail_template::class,'ci');$property->setValue($mail,$GLOBALS['CI']);return $mail;}
$vendorClasses=['Purchase_order_to_contact','Purchase_request_to_contact','Purchase_quotation_to_contact','Purchase_contract_to_contact','Purchase_statement_to_contact','Debit_note_to_contact','Vendor_registration_confirmed','Vendor_welcome_new_contact'];
foreach($vendorClasses as $class){
 $mail=mailWithoutConstructor($class);$context=['template'=>$mail,'email'=>'vendor@example.invalid'];
 foreach(['english','french','portuguese_br'] as $language){$GLOBALS['vendor_rows']=[['id'=>91,'default_language'=>$language]];checkPurchase(PurchaseMailLanguage::resolve('portuguese_br',$context,'portuguese_br',$lookup)===$language,'explicit vendor preference '.$class.' '.$language);}
 $GLOBALS['vendor_rows']=[['id'=>91,'default_language'=>'french']];
 checkPurchase(PurchaseMailLanguage::resolve('english',$context,'portuguese_br',$lookup)==='english','prior explicit recipient language retained '.$class);
 checkPurchase($mail->prepare('vendor@example.invalid')->language==='french','actual native preparation receives vendor language '.$class);
 checkPurchase(mailWithoutConstructor('App_mail_template')->prepare('vendor@example.invalid',$class)->language==='french','generic native preview matches actual vendor preparation '.$class);
}
$mail=mailWithoutConstructor('Purchase_order_to_contact');$context=['template'=>$mail,'email'=>'vendor@example.invalid'];
foreach([[],[['id'=>1,'default_language'=>'english'],['id'=>2,'default_language'=>'french']],[['id'=>1,'default_language'=>'']],[['id'=>1,'default_language'=>'../../elsewhere']],[['id'=>1,'default_language'=>'nonexistent']]] as $rows){$GLOBALS['vendor_rows']=$rows;checkPurchase(PurchaseMailLanguage::resolve('portuguese_br',$context,'portuguese_br',$lookup)==='portuguese_br','missing/ambiguous/invalid preference retains configured language');}
$before=$GLOBALS['lookups'];
foreach([['template'=>(object)['slug'=>'purchase-order-to-contact'],'email'=>'vendor@example.invalid'],['template_slug'=>'purchase-order-to-contact','email'=>'vendor@example.invalid'],['template'=>$mail,'email'=>'invalid-email']] as $invalid)checkPurchase(PurchaseMailLanguage::resolve('english',$invalid,'english',$lookup)==='english','invalid or foreign context unchanged');
checkPurchase($GLOBALS['lookups']===$before,'foreign contexts do not query vendor identities');
$originalSlug=$mail->slug;$mail->slug='contact-forgot-password';checkPurchase(PurchaseMailLanguage::resolve('english',['template'=>$mail,'email'=>'vendor@example.invalid'],'english',$lookup)==='english','mutated slug cannot apply vendor preference');$mail->slug=$originalSlug;
$GLOBALS['vendor_rows']=[['id'=>91,'default_language'=>'french']];
foreach(['Purchase_send_approved','Purchase_send_rejected','Request_approval'] as $class){
 $staffMail=mailWithoutConstructor($class);$before=$GLOBALS['lookups'];
 checkPurchase($staffMail->prepare('staff@example.invalid')->language==='english','staff explicit preference is native '.$class);
 checkPurchase($GLOBALS['lookups']===$before,'staff template never queries vendor with same email '.$class);
}
class CoreCustomerLanguageFixture extends App_mail_template {protected $for='customer';public $slug='invoice-send-to-client';}
class CoreStaffLanguageFixture extends App_mail_template {protected $for='staff';public $slug='staff-forgot-password';}
class CoreLeadLanguageFixture extends App_mail_template {public $rel_type='lead';public $rel_id=91;public $slug='new-lead-assigned';}
foreach(['CoreCustomerLanguageFixture'=>'german','CoreStaffLanguageFixture'=>'english','CoreLeadLanguageFixture'=>'french'] as $class=>$expected){$before=$GLOBALS['lookups'];checkPurchase(mailWithoutConstructor($class)->prepare('shared@example.invalid')->language===$expected,'native non-Purchase recipient retains explicit language '.$class);checkPurchase($GLOBALS['lookups']===$before,'native non-Purchase recipient does not query vendor '.$class);}
$formatter=(new ReflectionClass(Purchase_approve_merge_fields::class))->newInstanceWithoutConstructor();
$fields=$formatter->format((object)['staff_name'=>'Destinatário correto','by_staff_name'=>'Aprovador diferente','type'=>'Pedido','link'=>'https://example.invalid/order/1']);
checkPurchase($fields['{staff_name}']==='Destinatário correto' && $fields['{by_staff_name}']==='Aprovador diferente','recipient is not confused with approving actor');
$fields=$formatter->format((object)['by_staff_name'=>'Actor','type'=>'Pedido','link'=>'https://example.invalid']);
checkPurchase($fields['{staff_name}']==='','missing recipient name does not guess actor or logged-in user');
$main=file_get_contents(dirname(__DIR__).'/modules/purchase/purchase.php');
checkPurchase(str_contains($main,'Version: 1.5.1') && str_contains($main,'PurchaseMailLanguage::resolve'),'module wires tested resolver with new version');
checkPurchase(str_contains($main,"->where('c.active', 1)->where('v.active', 1)") && str_contains($main,'->limit(2)'),'live query excludes inactive contacts and rejects ambiguous rows');
$before=$GLOBALS['lookups'];
class App_module_migration{}
require dirname(__DIR__).'/modules/purchase/migrations/151_version_151.php';
(new Migration_Version_151)->up();(new Migration_Version_151)->up();
checkPurchase($GLOBALS['lookups']===$before,'new module migration is replayable NOOP');
echo 'Purchase mail language/recipient: '.$checks.' checks passed; no mail sent.'.PHP_EOL;
