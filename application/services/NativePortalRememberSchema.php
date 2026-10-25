<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';
/** Core-owned additive storage. No adoption of native global autologin cookies. */
final class NativePortalRememberSchema
{
 public const FIELDS=['selector'=>'varchar(48)','validator_hash'=>'varchar(64)','identity_stamp'=>'varchar(64)','issuer_id'=>'int unsigned','contact_id'=>'int','client_id'=>'int','expires_at'=>'datetime','revision'=>'int unsigned','revoked'=>'tinyint unsigned','nonce'=>'varchar(64)'];
 private static function sqlite($db):bool{$c=$db->pdo??$db->conn_id??null;return$c instanceof PDO&&$c->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';}
 public static function ensure($db,string$p):void{
  if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');IssuerTransaction::assertIndependent($db);$sqlite=self::sqlite($db);$parts=[];
  foreach(self::FIELDS as$n=>$t)$parts[]="$n ".($sqlite?(str_contains($t,'int')?'INTEGER':'TEXT'):$t.(str_starts_with($t,'varchar')?' CHARACTER SET ascii COLLATE ascii_bin':'')).' NOT NULL';
  $parts[]='PRIMARY KEY(selector)';foreach(['issuer_id'=>['issuers_registry','id'],'contact_id'=>['contacts','id'],'client_id'=>['clients','userid']]as$n=>[$table,$id]){$parts[]="FOREIGN KEY($n) REFERENCES {$p}{$table}($id)";if(!$sqlite)$parts[]="KEY $n($n)";}
  IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS '.$p.'company_native_portal_remember('.implode(',',$parts).')'.($sqlite?'':' ENGINE=InnoDB'));
  if($sqlite)foreach(['issuer_id','contact_id','client_id']as$n)IssuerStorage::query($db,"CREATE INDEX IF NOT EXISTS {$p}portal_remember_$n ON {$p}company_native_portal_remember($n)");
  if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);self::assertValid($db,$p);
 }
 public static function assertValid($db,string$p):void{
  if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');$t=$p.'company_native_portal_remember';$fail=static function(){throw new RuntimeException('issuer_portal_remember_schema_invalid');};$indices=[];$fks=[];
  if(self::sqlite($db)){
   $cols=IssuerStorage::query($db,"PRAGMA table_info($t)")->result_array();if(array_column($cols,'name')!==array_keys(self::FIELDS))$fail();foreach($cols as$r)if((int)$r['notnull']!==1||$r['dflt_value']!==null||strtoupper($r['type'])!==(str_contains(self::FIELDS[$r['name']],'int')?'INTEGER':'TEXT')||(int)$r['pk']!==($r['name']==='selector'?1:0))$fail();
   foreach(IssuerStorage::query($db,"PRAGMA index_list($t)")->result_array()as$r){if((int)$r['partial']!==0)$fail();$names=array_column(IssuerStorage::query($db,'PRAGMA index_info('.$r['name'].')')->result_array(),'name');$indices[implode(',',$names)]=(int)$r['unique'];}
   foreach(IssuerStorage::query($db,"PRAGMA foreign_key_list($t)")->result_array()as$r){if(!in_array($r['on_delete'],['RESTRICT','NO ACTION'],true)||!in_array($r['on_update'],['RESTRICT','NO ACTION'],true))$fail();$fks[$r['from']]=[$r['table'],$r['to']];}
  }else{
   $cols=IssuerStorage::query($db,"SHOW FULL COLUMNS FROM $t")->result_array();if(array_column($cols,'Field')!==array_keys(self::FIELDS))$fail();foreach($cols as$r){$type=preg_replace('/\b(tinyint|int)\(\d+\)/','$1',strtolower($r['Type']));if($type!==self::FIELDS[$r['Field']]||$r['Null']!=='NO'||$r['Default']!==null||$r['Extra']!==''||$r['Collation']!==(str_starts_with($type,'varchar')?'ascii_bin':null))$fail();}
   $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')$fail();$groups=[];foreach(IssuerStorage::query($db,"SHOW INDEX FROM $t")->result_array()as$r){if($r['Sub_part']!==null||strtoupper($r['Index_type'])!=='BTREE'||($r['Visible']??'YES')!=='YES'||($r['Expression']??null)!==null)$fail();$groups[$r['Key_name']][(int)$r['Seq_in_index']]=[$r['Column_name'],1-(int)$r['Non_unique']];}foreach($groups as$group){ksort($group);$indices[implode(',',array_column($group,0))]=$group[1][1];}
   foreach(IssuerStorage::query($db,'SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[$t])->result_array()as$r){if(!in_array($r['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($r['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))$fail();$fks[$r['COLUMN_NAME']]=[$r['REFERENCED_TABLE_NAME'],$r['REFERENCED_COLUMN_NAME']];}
  }
  ksort($indices);ksort($fks);if($indices!==['client_id'=>0,'contact_id'=>0,'issuer_id'=>0,'selector'=>1]||$fks!==['client_id'=>[$p.'clients','userid'],'contact_id'=>[$p.'contacts','id'],'issuer_id'=>[$p.'issuers_registry','id']])$fail();
 }
}
