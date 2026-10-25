<?php
declare(strict_types=1);
require __DIR__.'/ticket_catalog_schema.php';require_once __DIR__.'/../../application/services/NativeCompanyTicketLifecycleSchema.php';
final class TicketLifecycleSchemaDb{
 public bool$db_debug=true,$save_queries=true;public array$tables=[],$writes=[];
 function __construct(){foreach(NativeCompanyTicketLifecycleSchema::definitions('tbl')as$t=>$parts)$this->tables['tbl'.$t]=$this->metadata($parts);}
 private function metadata(array$parts):array{return(new ReflectionMethod(TicketCatalogSchemaDb::class,'metadata'))->invoke(null,$parts);}
 function query($sql,$p=[]){if(str_starts_with($sql,'CREATE TABLE')){$this->writes[]=$sql;foreach(NativeCompanyTicketLifecycleSchema::definitions('tbl')as$t=>$parts)$this->tables['tbl'.$t]=$this->metadata($parts);return new TicketCatalogSchemaRows([]);}$table=$this->tables[$p[0]]??null;if(str_contains($sql,'SELECT TABLE_NAME FROM'))return new TicketCatalogSchemaRows($table?[['TABLE_NAME'=>$p[0]]]:[]);foreach(['COLUMNS'=>'columns','STATISTICS'=>'indexes','KEY_COLUMN_USAGE'=>'foreign','REFERENTIAL_CONSTRAINTS'=>'rules','TABLES'=>'engine']as$n=>$k)if(str_contains($sql,'information_schema.'.$n))return new TicketCatalogSchemaRows($table[$k]??[]);throw new LogicException('Unexpected schema query');}
}
$replyDb=new TicketLifecycleSchemaDb();NativeCompanyTicketLifecycleSchema::ensure($replyDb,'tbl');ticketCatalogSchemaCheck($replyDb->writes===[],'lifecycle schema replay no changes');$replyDb->tables=[];NativeCompanyTicketLifecycleSchema::ensure($replyDb,'tbl');ticketCatalogSchemaCheck(count($replyDb->writes)===2,'lifecycle missing table explicit create');
$mutations[6]=static function(&$t){$t['indexes'][0]['NON_UNIQUE']=1;};
foreach($mutations as$i=>$mutate){$replyDb=new TicketLifecycleSchemaDb();$mutate($replyDb->tables['tbl'.NativeCompanyTicketLifecycleSchema::TICKETS]);try{NativeCompanyTicketLifecycleSchema::ensure($replyDb,'tbl');throw new LogicException('mutation accepted '.$i);}catch(RuntimeException$e){ticketCatalogSchemaCheck($e->getMessage()==='issuer_native_ticket_lifecycle_schema_invalid'&&$replyDb->writes===[],'strict metadata fails before mutation '.$i);}}
echo "Lifecycle metadata contract doubles passed: ten corruptions, exact replay/missing-only DDL. Real MySQL remains required.\n";
