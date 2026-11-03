<?php
declare(strict_types=1);
require __DIR__.'/ticket_catalog_schema.php';require_once __DIR__.'/../../application/services/NativeCompanyTicketReplySchema.php';
final class TicketReplySchemaDb{
 public bool$db_debug=true,$save_queries=true;public array$tables=[],$writes=[];
 function __construct(){foreach(NativeCompanyTicketReplySchema::definitions('tbl')as$t=>$parts)$this->tables['tbl'.$t]=$this->metadata($parts);}
 private function metadata(array$parts):array{return(new ReflectionMethod(TicketCatalogSchemaDb::class,'metadata'))->invoke(null,$parts);}
 function query($sql,$p=[]){if(str_starts_with($sql,'CREATE TABLE')){$this->writes[]=$sql;foreach(NativeCompanyTicketReplySchema::definitions('tbl')as$t=>$parts)$this->tables['tbl'.$t]=$this->metadata($parts);return new TicketCatalogSchemaRows([]);}$table=$this->tables[$p[0]]??null;if(str_contains($sql,'SELECT TABLE_NAME FROM'))return new TicketCatalogSchemaRows($table?[['TABLE_NAME'=>$p[0]]]:[]);foreach(['COLUMNS'=>'columns','STATISTICS'=>'indexes','KEY_COLUMN_USAGE'=>'foreign','REFERENTIAL_CONSTRAINTS'=>'rules','TABLES'=>'engine']as$n=>$k)if(str_contains($sql,'information_schema.'.$n))return new TicketCatalogSchemaRows($table[$k]??[]);throw new LogicException('Unexpected schema query');}
}
$replyDb=new TicketReplySchemaDb();NativeCompanyTicketReplySchema::ensure($replyDb,'tbl');ticketCatalogSchemaCheck($replyDb->writes===[],'reply schema replay no changes');$replyDb->tables=[];NativeCompanyTicketReplySchema::ensure($replyDb,'tbl');ticketCatalogSchemaCheck(count($replyDb->writes)===3,'reply missing table explicit create');
foreach($mutations as$i=>$mutate){$replyDb=new TicketReplySchemaDb();$mutate($replyDb->tables['tbl'.NativeCompanyTicketReplySchema::TABLE]);try{NativeCompanyTicketReplySchema::ensure($replyDb,'tbl');throw new LogicException('mutation accepted '.$i);}catch(RuntimeException$e){ticketCatalogSchemaCheck($e->getMessage()==='issuer_native_ticket_reply_schema_invalid'&&$replyDb->writes===[],'strict metadata fails before mutation '.$i);}}
echo "Reply metadata contract doubles passed: ten corruptions, exact replay/missing-only DDL. Real MySQL remains required.\n";
