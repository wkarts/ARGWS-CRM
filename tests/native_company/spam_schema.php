<?php
declare(strict_types=1);
require __DIR__.'/ticket_catalog_schema.php';require_once __DIR__.'/../../application/services/NativeCompanySpamSchema.php';
final class SpamSchemaDb{
 public bool$db_debug=true,$save_queries=true;public array$tables=[],$writes=[];
 function __construct(){foreach(NativeCompanySpamSchema::definitions('tbl')as$t=>$parts)$this->tables['tbl'.$t]=$this->metadata($parts);}
 private function metadata(array$parts):array{return(new ReflectionMethod(TicketCatalogSchemaDb::class,'metadata'))->invoke(null,$parts);}
 function query($sql,$p=[]){if(str_starts_with($sql,'CREATE TABLE')){$this->writes[]=$sql;foreach(NativeCompanySpamSchema::definitions('tbl')as$t=>$parts)$this->tables['tbl'.$t]=$this->metadata($parts);return new TicketCatalogSchemaRows([]);}$table=$this->tables[$p[0]]??null;if(str_contains($sql,'SELECT TABLE_NAME FROM'))return new TicketCatalogSchemaRows($table?[['TABLE_NAME'=>$p[0]]]:[]);foreach(['COLUMNS'=>'columns','STATISTICS'=>'indexes','KEY_COLUMN_USAGE'=>'foreign','REFERENTIAL_CONSTRAINTS'=>'rules','TABLES'=>'engine']as$n=>$k)if(str_contains($sql,'information_schema.'.$n))return new TicketCatalogSchemaRows($table[$k]??[]);throw new LogicException('Unexpected schema query');}
}
$replyDb=new SpamSchemaDb();NativeCompanySpamSchema::ensure($replyDb,'tbl');ticketCatalogSchemaCheck($replyDb->writes===[],'spam schema replay no changes');$replyDb->tables=[];NativeCompanySpamSchema::ensure($replyDb,'tbl');ticketCatalogSchemaCheck(count($replyDb->writes)===2,'spam missing table explicit create');
$mutations[6]=static function(&$t){$t['indexes'][1]['NON_UNIQUE']=0;};
foreach($mutations as$i=>$mutate){$replyDb=new SpamSchemaDb();$mutate($replyDb->tables['tbl'.NativeCompanySpamSchema::TABLE]);try{NativeCompanySpamSchema::ensure($replyDb,'tbl');throw new LogicException('mutation accepted '.$i);}catch(RuntimeException$e){ticketCatalogSchemaCheck($e->getMessage()==='issuer_native_spam_schema_invalid'&&$replyDb->writes===[],'strict metadata fails before mutation '.$i);}}
echo "Spam metadata contract doubles passed: ten corruptions, exact replay/missing-only DDL. Real MySQL remains required.\n";

$explicit=new SpamSchemaDb();
ticketCatalogSchemaCheck(array_column($explicit->tables['tbl'.NativeCompanySpamSchema::TABLE]['columns'],'COLUMN_NAME')===['id','issuer_id','type','rel_type','value','revision','actor_id','created_at','updated_at'],'metadata fixture includes actual mediumtext value, never silently omits declared fields');
$commandKeys=array_values(array_filter($explicit->tables['tbl'.NativeCompanySpamSchema::COMMANDS]['indexes'],fn($r)=>$r['INDEX_NAME']==='PRIMARY'));ticketCatalogSchemaCheck(array_column($commandKeys,'COLUMN_NAME')===['issuer_id','request_key'],'metadata fixture includes standalone composite primary key');
foreach(['type','primary']as$case){$bad=new SpamSchemaDb();if($case==='type')$bad->tables['tbl'.NativeCompanySpamSchema::TABLE]['columns'][4]['COLUMN_TYPE']='text';else$bad->tables['tbl'.NativeCompanySpamSchema::COMMANDS]['indexes']=array_values(array_filter($bad->tables['tbl'.NativeCompanySpamSchema::COMMANDS]['indexes'],fn($r)=>$r['INDEX_NAME']!=='PRIMARY'));$failed=false;try{NativeCompanySpamSchema::ensure($bad,'tbl');}catch(RuntimeException$e){$failed=$e->getMessage()==='issuer_native_spam_schema_invalid';}ticketCatalogSchemaCheck($failed,'mediumtext narrowing or missing primary rejected '.$case);}
echo "Spam declaration completeness and mediumtext/composite-primary negatives passed.\n";
