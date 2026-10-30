<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
/** Durable request intent and separate frozen delivery receipts. No credentials or mail body. */
final class NativeCompanyCustomerUploadMailSchema
{
 public const OUTBOX='company_file_upload_outbox';
 public const RECEIPTS='company_file_upload_mail';
 private static function fields():array{return[
  self::OUTBOX=>['file_id'=>'int','issuer_id'=>'int unsigned','source_hash'=>'char(64)','state'=>'char(16)','write_nonce'=>'char(64)','created_at'=>'char(19)'],
  self::RECEIPTS=>['queue_id'=>'int','issuer_id'=>'int unsigned','file_id'=>'int','recipient_id'=>'int','source_hash'=>'char(64)','recipient_hash'=>'char(64)','template_hash'=>'char(64)','configuration_hash'=>'char(64)','payload_hash'=>'char(64)','binding_hash'=>'char(64)','write_nonce'=>'char(64)']
 ];}
 private static function keys():array{return[self::OUTBOX=>['PRIMARY'=>[0,['file_id']],'issuer_file'=>[0,['issuer_id','file_id']]],self::RECEIPTS=>['PRIMARY'=>[0,['queue_id']],'file_recipient'=>[0,['issuer_id','file_id','recipient_id']],'file_id'=>[1,['file_id']]]];}
 private static function fks(string$p):array{return[self::OUTBOX=>['issuer_id'=>[$p.'issuers_registry','id'],'file_id'=>[$p.'files','id']],self::RECEIPTS=>['issuer_id'=>[$p.'issuers_registry','id'],'file_id'=>[$p.'files','id'],'queue_id'=>[$p.'mail_queue','id']]];}
 private static function sqlite($db):bool{$c=$db->pdo??$db->conn_id??null;return$c instanceof PDO&&$c->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';}
 public static function tables():array{return[self::OUTBOX,self::RECEIPTS];}
 public static function ensure($db,string$p):void{
  self::prefix($p);IssuerTransaction::assertIndependent($db);$sqlite=self::sqlite($db);
  foreach(self::fields()as$name=>$fields){$parts=[];foreach($fields as$key=>$type)$parts[]=$key.' '.($sqlite?(str_starts_with($type,'int')?'INTEGER':'TEXT'):$type.(str_starts_with($type,'char')?' CHARACTER SET ascii COLLATE ascii_bin':'')).' NOT NULL';
   foreach(self::keys()[$name]as$key=>[$nonunique,$columns]){if($key==='PRIMARY')$parts[]='PRIMARY KEY('.implode(',',$columns).')';elseif(!$sqlite)$parts[]=($nonunique?'KEY ':'UNIQUE KEY ').$key.'('.implode(',',$columns).')';elseif(!$nonunique)$parts[]='UNIQUE('.implode(',',$columns).')';}
   foreach(self::fks($p)[$name]as$column=>[$table,$target])$parts[]=($sqlite?'':'CONSTRAINT ncfum_'.sha1($p.$name.$column).' ')."FOREIGN KEY($column) REFERENCES $table($target)";
   IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS '.$p.$name.'('.implode(',',$parts).')'.($sqlite?'':' ENGINE=InnoDB'));
   if($sqlite)foreach(self::keys()[$name]as$key=>[$nonunique,$columns])if($nonunique)IssuerStorage::query($db,'CREATE INDEX IF NOT EXISTS '.$p.$name.'_'.$key.' ON '.$p.$name.'('.implode(',',$columns).')');
  }
  if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);self::assertValid($db,$p);
 }
 private static function prefix(string$p):void{if(!preg_match('/^[A-Za-z0-9_]+$/D',$p)||strlen($p)>38)throw new InvalidArgumentException('Invalid prefix');}
 public static function assertValid($db,string$p):void{
  self::prefix($p);$sqlite=self::sqlite($db);
  foreach(self::fields()as$name=>$fields){$t=$p.$name;$rows=IssuerStorage::query($db,$sqlite?'PRAGMA table_info('.$t.')':'SHOW FULL COLUMNS FROM '.$t)->result_array();if(array_column($rows,$sqlite?'name':'Field')!==array_keys($fields))throw new RuntimeException('native_customer_upload_mail_schema_invalid');
   foreach($rows as$r){$key=$r[$sqlite?'name':'Field'];$type=$sqlite?strtoupper($r['type']):preg_replace('/\bint\([0-9]+\)/','int',strtolower($r['Type']));$expected=$sqlite?(str_starts_with($fields[$key],'int')?'INTEGER':'TEXT'):$fields[$key];if($type!==$expected||($sqlite?(int)$r['notnull']!==1:$r['Null']!=='NO')||($sqlite?$r['dflt_value']!==null:!IssuerStorage::sqlNullDefault(['COLUMN_DEFAULT'=>$r['Default']])))throw new RuntimeException('native_customer_upload_mail_schema_invalid');if($sqlite){if((int)$r['pk']!==($key===self::keys()[$name]['PRIMARY'][1][0]?1:0))throw new RuntimeException('native_customer_upload_mail_schema_invalid');}elseif($r['Extra']!==''||$r['Collation']!==(str_starts_with($type,'char')?'ascii_bin':null))throw new RuntimeException('native_customer_upload_mail_schema_invalid');}
   $actual=[];$expected=[];
   if($sqlite){foreach(IssuerStorage::query($db,'PRAGMA index_list('.$t.')')->result_array()as$index){if((int)$index['partial']!==0||!preg_match('/^[A-Za-z0-9_]+$/D',$index['name']))throw new RuntimeException('native_customer_upload_mail_schema_invalid');$cols=IssuerStorage::query($db,'PRAGMA index_xinfo('.$index['name'].')')->result_array();$names=[];foreach($cols as$c)if((int)$c['key']===1){if((int)$c['cid']<0||(int)$c['desc']!==0||strtoupper($c['coll'])!=='BINARY')throw new RuntimeException('native_customer_upload_mail_schema_invalid');$names[]=$c['name'];}$actual[]=json_encode([1-(int)$index['unique'],$names]);}foreach(self::keys()[$name]as$key=>$value)if($key!=='PRIMARY')$expected[]=json_encode($value);
   }else{$engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('native_customer_upload_mail_schema_invalid');$indexes=[];foreach(IssuerStorage::query($db,'SHOW INDEX FROM '.$t)->result_array()as$r){if($r['Sub_part']!==null||strtoupper($r['Index_type'])!=='BTREE'||($r['Collation']??'A')!=='A')throw new RuntimeException('native_customer_upload_mail_schema_invalid');$indexes[$r['Key_name']][0]=(int)$r['Non_unique'];$indexes[$r['Key_name']][1][(int)$r['Seq_in_index']-1]=$r['Column_name'];}foreach($indexes as$key=>$value){ksort($value[1]);$actual[]=json_encode([$key,$value]);}foreach(self::keys()[$name]as$key=>$value)$expected[]=json_encode([$key,$value]);}
   sort($actual);sort($expected);if($actual!==$expected)throw new RuntimeException('native_customer_upload_mail_schema_invalid');
   $actual=[];$expected=[];foreach(self::fks($p)[$name]as$key=>[$table,$target])$expected[]=json_encode([$key,$table,$target]);
   if($sqlite){foreach(IssuerStorage::query($db,'PRAGMA foreign_key_list('.$t.')')->result_array()as$r){if((int)$r['seq']!==0||!in_array($r['on_delete'],['NO ACTION','RESTRICT'],true)||!in_array($r['on_update'],['NO ACTION','RESTRICT'],true))throw new RuntimeException('native_customer_upload_mail_schema_invalid');$actual[]=json_encode([$r['from'],$r['table'],$r['to']]);}}
   else{foreach(IssuerStorage::query($db,'SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[$t])->result_array()as$r){if(!in_array($r['DELETE_RULE'],['NO ACTION','RESTRICT'],true)||!in_array($r['UPDATE_RULE'],['NO ACTION','RESTRICT'],true))throw new RuntimeException('native_customer_upload_mail_schema_invalid');$actual[]=json_encode([$r['COLUMN_NAME'],$r['REFERENCED_TABLE_NAME'],$r['REFERENCED_COLUMN_NAME']]);}}
   sort($actual);sort($expected);if($actual!==$expected)throw new RuntimeException('native_customer_upload_mail_schema_invalid');
  }
 }
}
