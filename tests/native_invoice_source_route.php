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
