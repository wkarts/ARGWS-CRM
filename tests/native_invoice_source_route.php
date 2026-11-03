<?php
/** Adapter selection by persisted invoice ownership; authorization remains in the real owned handler. */
define('BASEPATH',dirname(__DIR__).'/system/');define('APPPATH',dirname(__DIR__).'/application/');define('ADMIN_URI','admin');
function db_prefix(){return'tbl';}
require APPPATH.'services/CompanyRequestScope.php';
final class InvoiceRouteFixtureDb{
 public $owner=null;public bool$exists=true;public bool$field=true;public int$calls=0;
 public function field_exists($field,$table){if($field!=='issuer_id'||$table!=='tblinvoices')throw new RuntimeException('unexpected metadata lookup');return$this->field;}
 public function query($sql,$args){++$this->calls;if($sql!=='SELECT issuer_id FROM tblinvoices WHERE id=?'||$args!==['42'])throw new RuntimeException('invoice source lookup must remain exact and bound');return new class($this->exists,$this->owner){public function __construct(private bool$exists,private$owner){}public function row_array(){return$this->exists?['issuer_id'=>$this->owner]:null;}};}
}
$db=new InvoiceRouteFixtureDb();$uri=new class{public array$segments=[3=>'42',4=>'42'];public function rsegment($n){return$this->segments[$n]??false;}};
$input=new class{public string$verb='GET';public string$invoice='42';public function method($upper){return$this->verb;}public function post($key,$filter){return$key==='invoiceid'?$this->invoice:null;}};
$ci=(object)['db'=>$db,'uri'=>$uri,'input'=>$input];$method=new ReflectionMethod(CompanyRequestScope::class,'ownedInvoiceRoute');$checks=0;
$check=function($expected,$class,$action)use($method,$ci,&$checks){if($method->invoke(null,$ci,$class,$action)!==$expected)throw new RuntimeException('Unexpected adapter for '.$class.'/'.$action);++$checks;};
foreach(['list_invoices','get_invoice_data_ajax','invoice','pdf','record_invoice_payment_ajax']as$action){foreach([null,3,77]as$owner){$db->owner=$owner;$check($owner!==null,'invoices',$action);}}
$input->verb='POST';foreach(['invoice','mark_as_cancelled','unmark_as_cancelled','record_payment','delete_payment','send_to_email']as$action){foreach([null,3,77]as$owner){$db->owner=$owner;$check($owner!==null,'invoices',$action);}}
$input->verb='GET';$db->owner=3;$db->exists=false;$check(false,'invoices','pdf');$db->exists=true;$db->field=false;$before=$db->calls;$check(false,'invoices','pdf');if($db->calls!==$before)throw new RuntimeException('pre-migration source queried ownership');$db->field=true;
foreach(['','0','-1','42/extra','https://example.invalid']as$id){$uri->segments[3]=$id;$before=$db->calls;$check(false,'invoices','pdf');if($db->calls!==$before)throw new RuntimeException('malformed identity queried the database');}
$uri->segments[3]='42';$check(false,'settings','pdf');$check(false,'invoices','unknown');$input->verb='POST';$check(false,'invoices','pdf');$check(false,'invoices','table');
unset($ci->uri);$input->verb='GET';$check(false,'invoices','pdf');
echo "Invoice source route: {$checks} bounded native/foreign/NULL/pre-migration/method scenarios passed.\n";
// Expense records use the published namespace; explicit own collections still require native verb/ACL/CSRF checks.
$expenseUri=new class{public array $segments=[];public function rsegment($n){return $this->segments[$n]??false;}};
$expenseInput=new class{public string $verb='GET';public array $gets=[],$posts=[];public function method($upper){return $this->verb;}public function get($key,$filter){return $this->gets[$key]??null;}public function post($key,$filter){return $this->posts[$key]??null;}};
$expenseCi=(object)['uri'=>$expenseUri,'input'=>$expenseInput];$expenseMethod=new ReflectionMethod(CompanyRequestScope::class,'ownedExpenseRoute');$expenseChecks=0;
$expenseCheck=function(bool $expected,string $action,string $class='expenses')use($expenseMethod,$expenseCi,&$expenseChecks){if($expenseMethod->invoke(null,$expenseCi,$class,$action)!==$expected)throw new RuntimeException('Unexpected expense adapter '.$class.'/'.$action);++$expenseChecks;};
$ownedExpense=NativeCompanyResourceId::encode('company_expense',42);
foreach(['index','list_expenses','expense','pdf'] as $action){$expenseInput->gets=['expense'=>$ownedExpense];$expenseCheck(true,$action);foreach(['42','0','',[],NativeCompanyResourceId::encode('company_ticket',42)] as $bad){$expenseInput->gets=['expense'=>$bad];$expenseCheck(false,$action);}}
$expenseInput->gets=['source'=>'company'];$expenseCheck(true,'index');$expenseInput->gets=['source'=>'company','expense'=>'42'];$expenseCheck(false,'index');
$expenseInput->verb='POST';$expenseInput->posts=['expense_id'=>$ownedExpense];foreach(['expense','delete','convert_to_invoice','add_expense_attachment'] as $action)$expenseCheck(true,$action);
$expenseCheck(false,'unknown');$expenseCheck(false,'expense','settings');$expenseInput->verb='DELETE';$expenseCheck(false,'expense');
$expenseInput->posts=[];
foreach(['GET','POST'] as $verb){$expenseInput->verb=$verb;$expenseUri->segments=[];$expenseInput->gets=['source'=>'company'];$expenseCheck(true,'copy');$expenseInput->gets=['expense'=>$ownedExpense];$expenseCheck(true,'copy');$expenseUri->segments=[3=>'42'];$expenseInput->gets=[];$expenseCheck(false,'copy');$expenseInput->gets=['source'=>'company'];$expenseCheck(false,'copy');}
$expenseUri->segments=[];$expenseInput->gets=[];$expenseInput->verb='POST';$expenseInput->posts=['source_expense_id'=>$ownedExpense];$expenseCheck(true,'copy');$expenseCheck(false,'index');
foreach(['',[],['42'],'42',NativeCompanyResourceId::encode('company_ticket',42)] as $malformed){$expenseInput->posts=['source_expense_id'=>$malformed];$expenseCheck(true,'copy');}
echo "Expense source route: {$expenseChecks} typed/legacy/foreign-namespace/method scenarios passed.\n";
