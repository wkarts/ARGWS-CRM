<?php
require_once __DIR__.'/issuers_core_support.php';
$source=file_get_contents(__DIR__.'/issuers_core_support.php');$start=strpos($source,'function essentialCleanup(): void');$body=substr($source,$start,strpos($source,'/** Real waiter counts',$start)-$start);
if(!preg_match('/foreach \(\[(.*?)\] as \$table\)/s',$body,$match))throw new RuntimeException('cleanup list absent');
preg_match_all("/'([A-Za-z0-9_]+)'/",$match[1],$names);$tables=$names[1];$checks=0;
if(count($tables)!==count(array_unique($tables)))throw new RuntimeException('duplicate cleanup table');++$checks;
foreach(['company_invoice_native_commands'=>['issuers_registry','staff','issuers_audit'],'company_invoice_draft_line_transfers'=>['company_invoice_draft_lines','invoices','issuers_audit','issuers_registry'],'company_invoice_draft_lines'=>['company_invoice_drafts','issuers_registry'],'company_invoice_draft_projection'=>['company_invoice_drafts','issuers_registry'],'company_invoice_native_relations'=>['invoices','clients','issuers_registry'],'company_custom_field_values'=>['company_custom_fields','issuers_registry'],'company_work_tag_links'=>['company_work_tags','issuers_registry'],'taggables'=>['tags'],'customfieldsvalues'=>['customfields']]as$child=>$parents){
 foreach($parents as$parent){$c=array_search($child,$tables,true);$p=array_search($parent,$tables,true);if($c===false||$p===false||$c>=$p)throw new RuntimeException('invalid child-before-parent cleanup');++$checks;}
}
$pdo=new class {
 public array $executed=[];public string $schema='issuer_local_module_audit';public int $foreignKeys=1;public bool $failDrop=false;
 public function query($sql){$value=match($sql){'SELECT DATABASE()'=>$this->schema,'SELECT @@SESSION.FOREIGN_KEY_CHECKS'=>$this->foreignKeys,default=>throw new RuntimeException('unexpected query')};return new class($value){public function __construct(private $value){}public function fetchColumn(){return $this->value;}};}
 public function exec($sql){$this->executed[]=$sql;if($this->failDrop&&str_starts_with($sql,'DROP TABLE'))throw new RuntimeException('synthetic_drop_failure');return 0;}
};
$db=new class($pdo){public function __construct(public $pdo){}public function driver(){return 'mysql';}};
$previous=$GLOBALS['essentialFixtures']??[];
try{
 $GLOBALS['essentialFixtures']=[(object)['db'=>$db,'prefix'=>'production_']];
 try{essentialCleanup();throw new RuntimeException('unsafe prefix accepted');}catch(RuntimeException$e){if($e->getMessage()!=='essential_cleanup_prefix_invalid')throw$e;++$checks;}
 if($pdo->executed!==[])throw new RuntimeException('SQL executed before prefix rejection');++$checks;
 $GLOBALS['essentialFixtures']=[(object)['db'=>$db,'prefix'=>'essential_0123456789_']];$pdo->schema='production';
 try{essentialCleanup();throw new RuntimeException('unsafe schema accepted');}catch(RuntimeException$e){if($e->getMessage()!=='essential_cleanup_schema_invalid')throw$e;++$checks;}
 if($pdo->executed!==[])throw new RuntimeException('SQL executed for unsafe schema');++$checks;
 $pdo->schema='issuer_local_module_audit';essentialCleanup();
 if(count($pdo->executed)!==count($tables)+2||$pdo->executed[0]!=='SET SESSION FOREIGN_KEY_CHECKS=0'||end($pdo->executed)!=='SET SESSION FOREIGN_KEY_CHECKS=1')throw new RuntimeException('cleanup omitted tables or failed to restore FK state');++$checks;
 foreach(array_slice($pdo->executed,1,-1) as$sql)if(!preg_match('/^DROP TABLE IF EXISTS essential_0123456789_[A-Za-z0-9_]+$/D',$sql))throw new RuntimeException('cleanup escaped synthetic prefix');++$checks;
 $pdo->executed=[];$pdo->failDrop=true;try{essentialCleanup();throw new RuntimeException('failed DROP accepted');}catch(RuntimeException$e){if($e->getMessage()!=='synthetic_drop_failure')throw$e;++$checks;}
 if(end($pdo->executed)!=='SET SESSION FOREIGN_KEY_CHECKS=1')throw new RuntimeException('FK state not restored on failure');++$checks;
}finally{$GLOBALS['essentialFixtures']=$previous;}
echo "Invoice fixture cleanup inventory/order/prefix: {$checks} checks PASS (fake SQL executor, not MySQL).\n";
