<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';
final class NativePortalRegistrationSchema
{
 public const FIELDS=['issuer_id'=>'int unsigned','request_key'=>'char(64)','input_hash'=>'char(64)','client_id'=>'int','contact_id'=>'int','created_at'=>'datetime','nonce'=>'char(64)'];
 private static function sqlite($db):bool{$c=$db->pdo??$db->conn_id??null;return$c instanceof PDO&&$c->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';}
 public static function ensure($db,string$p):void{
  if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');IssuerTransaction::assertIndependent($db);$sqlite=self::sqlite($db);$parts=[];
  foreach(self::FIELDS as$n=>$type)$parts[]="$n ".($sqlite?(str_contains($type,'int')?'INTEGER':'TEXT'):$type.(str_starts_with($type,'char')?' CHARACTER SET ascii COLLATE ascii_bin':'')).' NOT NULL';
  $parts[]='PRIMARY KEY(issuer_id,request_key)';$parts[]='UNIQUE(issuer_id,contact_id)';foreach(['issuer_id'=>['issuers_registry','id'],'client_id'=>['clients','userid'],'contact_id'=>['contacts','id']]as$n=>[$t,$id])$parts[]="FOREIGN KEY($n) REFERENCES {$p}{$t}($id)";
  if(!$sqlite){$parts[]='KEY client_id(client_id)';$parts[]='KEY contact_id(contact_id)';}
  IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS '.$p.'company_native_portal_registrations('.implode(',',$parts).')'.($sqlite?'':' ENGINE=InnoDB'));
  if($sqlite)foreach(['client_id','contact_id']as$n)IssuerStorage::query($db,"CREATE INDEX IF NOT EXISTS {$p}portal_registration_$n ON {$p}company_native_portal_registrations($n)");if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);self::assertValid($db,$p);
 }
 public static function assertValid($db,string$p):void{
  if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');$t=$p.'company_native_portal_registrations';$fail=static function(){throw new RuntimeException('issuer_portal_registration_schema_invalid');};$indexes=[];$refs=[];
  if(self::sqlite($db)){
   $columns=IssuerStorage::query($db,"PRAGMA table_info($t)")->result_array();if(array_column($columns,'name')!==array_keys(self::FIELDS))$fail();foreach($columns as$r)if((int)$r['notnull']!==1||$r['dflt_value']!==null||(int)$r['pk']!==match($r['name']){'issuer_id'=>1,'request_key'=>2,default=>0}||strtoupper($r['type'])!==(str_contains(self::FIELDS[$r['name']],'int')?'INTEGER':'TEXT'))$fail();
   foreach(IssuerStorage::query($db,"PRAGMA index_list($t)")->result_array()as$r){if((int)$r['partial']!==0)$fail();$cols=array_column(IssuerStorage::query($db,'PRAGMA index_info('.$r['name'].')')->result_array(),'name');$indexes[implode(',',$cols)]=(int)$r['unique'];}
   foreach(IssuerStorage::query($db,"PRAGMA foreign_key_list($t)")->result_array()as$r){if((int)$r['seq']!==0||!in_array($r['on_delete'],['RESTRICT','NO ACTION'],true)||!in_array($r['on_update'],['RESTRICT','NO ACTION'],true))$fail();$refs[$r['from']]=[$r['table'],$r['to']];}
  }else{
   $columns=IssuerStorage::query($db,"SHOW FULL COLUMNS FROM $t")->result_array();if(array_column($columns,'Field')!==array_keys(self::FIELDS))$fail();foreach($columns as$r){$type=preg_replace('/\bint\(\d+\)/','int',strtolower($r['Type']));if($type!==self::FIELDS[$r['Field']]||$r['Null']!=='NO'||$r['Default']!==null||$r['Extra']!==''||$r['Collation']!==(str_starts_with($type,'char')?'ascii_bin':null))$fail();}
   $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')$fail();$groups=[];foreach(IssuerStorage::query($db,"SHOW INDEX FROM $t")->result_array()as$r){if($r['Sub_part']!==null||strtoupper($r['Index_type'])!=='BTREE')$fail();$groups[$r['Key_name']][(int)$r['Seq_in_index']]=[$r['Column_name'],1-(int)$r['Non_unique']];}foreach($groups as$g){ksort($g);$indexes[implode(',',array_column($g,0))]=$g[1][1];}
   foreach(IssuerStorage::query($db,'SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[$t])->result_array()as$r){if(!in_array($r['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($r['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))$fail();$refs[$r['COLUMN_NAME']]=[$r['REFERENCED_TABLE_NAME'],$r['REFERENCED_COLUMN_NAME']];}
  }
  ksort($indexes);ksort($refs);if($indexes!==['client_id'=>0,'contact_id'=>0,'issuer_id,contact_id'=>1,'issuer_id,request_key'=>1]||$refs!==['client_id'=>[$p.'clients','userid'],'contact_id'=>[$p.'contacts','id'],'issuer_id'=>[$p.'issuers_registry','id']])$fail();
 }
}
