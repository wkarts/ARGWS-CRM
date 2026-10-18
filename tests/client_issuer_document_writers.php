<?php
/** Real model entrypoints and SQLite storage; transport/framework collaborators are explicit doubles. */
require __DIR__.'/support/client_issuer_test_support.php';
require __DIR__.'/../application/services/InvoiceIssuerScope.php';
require __DIR__.'/../application/services/RecurringInvoiceDates.php';
foreach(['Invoices','Credit_notes','Estimates','Proposals','Contracts'] as $name) require __DIR__.'/../application/models/'.$name.'_model.php';
function app_generate_hash(){return 'synthetic-only';}
function to_sql_date($value){return $value;}
function format_invoice_number($id){return 'LEGACY-'.$id;}
function format_estimate_number($id){return 'EST-'.$id;}
function apply_filters_deprecated($name,$args,...$rest){return $args[0];}
class ClientWriterDb extends ClientModelDb {
 public function get($table = '') { return parent::get($table && !str_starts_with($table, db_prefix()) ? db_prefix().$table : $table); }
 public function field_exists($field,$table){return in_array($field,array_column($this->query('PRAGMA table_info('.$table.')')->result_array(),'name'),true);}
}
$db=new ClientWriterDb($pdo);
foreach(['invoices'=>'clientid INT,issuer_id INT NULL','creditnotes'=>'clientid INT','estimates'=>'clientid INT','contracts'=>'client INT','proposals'=>'rel_id INT,rel_type TEXT'] as $table=>$columns){
 $pdo->exec('CREATE TABLE q_'.$table.'(id INT PRIMARY KEY,'.$columns.')');
 foreach([1,2,3] as $id){$values=$table==='proposals'?"$id,$id,'customer'":($table==='invoices'?"$id,$id,NULL":"$id,$id");$pdo->exec('INSERT INTO q_'.$table.' VALUES('.$values.')');}
}
$models=[];
foreach(['Invoices'=>'clientid','Credit_notes'=>'clientid','Estimates'=>'clientid','Contracts'=>'client','Proposals'=>'rel_id'] as $name=>$field){
 $model=(new ReflectionClass($name.'_model'))->newInstanceWithoutConstructor();$model->db=$db;$models[$name]=$model;
 foreach([2,3,999,0,false,[]] as $client){
  $data=[$field=>$client];if($name==='Proposals')$data['rel_type']='customer';
  $GLOBALS['effects']=[];$before=$db->writes;
  check($model->add($data)===false,$name.' rejects private/invalid new customer');
  check($db->writes===$before && $GLOBALS['effects']===[],$name.' denied before writes/hooks');
  check($model->update($data,1)===false,$name.' cannot reassign legacy document to hidden customer');
 }
 foreach([2,3] as $id){
  $GLOBALS['effects']=[];$before=$db->writes;
  check($model->update([], $id)===false,$name.' existing private relationship denied before hydration');
  if(method_exists($model,'copy'))check($model->copy($id)===false,$name.' copy cannot create a new legacy-private relationship');
  if(method_exists($model,'convert_to_invoice'))check($model->convert_to_invoice($id)===false,$name.' conversion rejects private source');
  check($db->writes===$before && $GLOBALS['effects']===[],$name.' source denial has no effects');
 }
}
check($models['Contracts']->renew(['contractid'=>2])===false,'renewal cannot bypass private contract guard');
foreach($models as $name=>$model){
 $field=$name==='Contracts'?'client':($name==='Proposals'?'rel_id':'clientid');
 $GLOBALS['hooks']=new class($field,$name) {
  public int $calls=0;
  public function __construct(private $field,private $name){}
  public function apply_filters($event,$value,...$args){
   $this->calls++;
   if(isset($value['data'])){$value['data'][$this->field]=2;if($this->name==='Proposals')$value['data']['rel_type']='customer';}
   else{$value[$this->field]=2;}
   return $value;
  }
  public function do_action(...$args){throw new RuntimeException('No after-write effects expected');}
 };
 $data=[$field=>1,'date'=>'2026-10-18','duedate'=>'','number'=>'1','billing_street'=>'','shipping_street'=>'','currency'=>1,'total'=>1,'newitems'=>[],
 'address'=>'','datestart'=>'2026-10-18','dateend'=>'','attachment'=>'','recurring'=>0,'custom_recurring'=>0];
 if($name==='Proposals')$data['rel_type']='customer';
 $before=$db->writes;
 check($model->add($data)===false,$name.' rejects private customer injected by pre-insert hook');
 check($GLOBALS['hooks']->calls===1 && $db->writes===$before,$name.' hook injection never reaches insert');
}

// Four update paths also revalidate a customer changed by an extension hook.
class InvoiceWriterFixture extends Invoices_model {
 public function get($id = '', $where = []) { return (object) ['number'=>1,'recurring'=>0,'recurring_due_mode'=>'days']; }
}
class EstimateWriterFixture extends Estimates_model {
 public function get($id = '', $where = []) { return (object) ['number'=>1,'status'=>1]; }
}
$pdo->exec('ALTER TABLE q_contracts ADD COLUMN dateend TEXT');
foreach(['Invoices'=>InvoiceWriterFixture::class,'Estimates'=>EstimateWriterFixture::class,'Credit_notes'=>Credit_notes_model::class,'Contracts'=>Contracts_model::class] as $name=>$class){
 $fixture=(new ReflectionClass($class))->newInstanceWithoutConstructor();$fixture->db=$db;
 $field=$name==='Contracts'?'client':'clientid';
 $GLOBALS['hooks']=new class($field) {
  public int $calls=0;
  public function __construct(private $field){}
  public function apply_filters($event,$value,...$args){$this->calls++;if(isset($value['data'])){$value['data'][$this->field]=2;}else{$value[$this->field]=2;}return $value;}
  public function do_action(...$args){throw new RuntimeException('No after-write effects expected');}
 };
 $before=$db->writes;
 check($fixture->update([$field=>1,'number'=>'1','billing_street'=>'','shipping_street'=>'','duedate'=>'','dateend'=>''],1)===false,$name.' update rejects private customer introduced by hook');
 check($GLOBALS['hooks']->calls===1 && $db->writes===$before,$name.' hook-retargeted update does not persist document/items');
}

// Real update entrypoint: a hook can omit rel_type, so validation must combine stored state.
class ProposalWriterFixture extends Proposals_model {
 public function get($id = '', $where = [], $for_editor = false) { return (object) ['rel_id'=>1,'rel_type'=>'customer']; }
}
$proposal=(new ReflectionClass(ProposalWriterFixture::class))->newInstanceWithoutConstructor();$proposal->db=$db;
$GLOBALS['hooks']=new class {
 public int $calls=0;
 public function apply_filters($event,$value,...$args){ $this->calls++; $value['data']['rel_id']=2; unset($value['data']['rel_type']); return $value; }
 public function do_action(...$args){throw new RuntimeException('No after-write effects expected');}
};
$before=$db->writes;
check($proposal->update(['rel_id'=>1,'rel_type'=>'customer','address'=>''],1)===false,'proposal hook cannot retain stored customer type while replacing only ID');
check($GLOBALS['hooks']->calls===1 && $db->writes===$before,'effective proposal relation denied before insert/update');
use app\services\ClientIssuerScope;
check(ClientIssuerScope::allowsLegacyWrite($db,'q_',['clientid'=>1], 'clientid',true),'unowned legacy customer remains writable');
check(ClientIssuerScope::allowsLegacyProposalWrite($db,'q_',['rel_type'=>'lead','rel_id'=>2]),'lead relation is not mistaken for customer');
check(ClientIssuerScope::allowsLegacyProposalWrite($db,'q_',[]),'unassigned proposal behavior preserved');
foreach(['CUSTOMER','customer ',' customer','Customer',true,2,[],'project'] as $type){
 $before=$db->writes;$GLOBALS['effects']=[];
 check($models['Proposals']->add(['rel_type'=>$type,'rel_id'=>2])===false,'noncanonical proposal relation cannot bypass customer validation');
 check($db->writes===$before && $GLOBALS['effects']===[],'unknown relation rejected before writer effects');
}
$pdo->exec('DROP TABLE q_issuers_clients');
check(!ClientIssuerScope::allowsLegacyWrite($db,'q_',['clientid'=>1], 'clientid',true),'partial issuer schema fails closed');
$pdo->exec('DROP TABLE q_issuers_client_links');
check(ClientIssuerScope::allowsLegacyWrite($db,'q_',['clientid'=>1], 'clientid',true),'pre-module legacy reference behavior preserved');
echo "Private customer document writers: $n checks passed (real model methods + SQLite; no transport).\n";
