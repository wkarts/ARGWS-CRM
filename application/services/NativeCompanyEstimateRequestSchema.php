<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
/** Native form IDs and rows remain in place; NULL ownership is explicit historical primary lineage. */
final class NativeCompanyEstimateRequestSchema
{
 public const TABLE='estimate_request_forms';
 private static function sqlite($db):bool{$c=$db->conn_id??$db->pdo??null;return$c instanceof PDO&&$c->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';}
 private static function table(string$p):string{if(!preg_match('/^[A-Za-z0-9_]+$/D',$p)||strlen($p.self::TABLE)>64)throw new InvalidArgumentException('Invalid prefix');return$p.self::TABLE;}
 private static function columns($db,string$t):array{return self::sqlite($db)?IssuerStorage::query($db,'PRAGMA table_info('.$t.')')->result_array():IssuerStorage::query($db,'SHOW FULL COLUMNS FROM '.$t)->result_array();}
 public static function ensure($db,string$p):void
 {
  IssuerTransaction::assertIndependent($db);$t=self::table($p);if(!$db->table_exists($t))throw new RuntimeException('native_estimate_form_table_missing');
  $sqlite=self::sqlite($db);$columns=self::columns($db,$t);$names=array_column($columns,$sqlite?'name':'Field');
  if(!in_array('id',$names,true)||!in_array('name',$names,true))throw new RuntimeException('native_estimate_form_schema_invalid');
  if(!$sqlite){$engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t])->row_array();if(strtolower((string)($engine['ENGINE']??''))!=='innodb')throw new RuntimeException('native_estimate_form_schema_invalid');}
  if(!in_array('issuer_id',$names,true))IssuerStorage::query($db,'ALTER TABLE '.$t.' ADD COLUMN issuer_id '.($sqlite?'INTEGER NULL REFERENCES '.$p.'issuers_registry(id)':'INT UNSIGNED NULL'));
  self::assertColumn($db,$p);
  if($sqlite){$indexes=IssuerStorage::query($db,'PRAGMA index_list('.$t.')')->result_array();$name='nefri_'.substr(hash('sha256',$t),0,40);$found=false;foreach($indexes as$index)if($index['name']===$name)$found=true;if(!$found)IssuerStorage::query($db,'CREATE INDEX '.$name.' ON '.$t.'(issuer_id,id)');}
  else{
   $indexes=IssuerStorage::query($db,'SHOW INDEX FROM '.$t)->result_array();if(!array_filter($indexes,fn($r)=>$r['Key_name']==='native_issuer'))IssuerStorage::query($db,'ALTER TABLE '.$t.' ADD KEY native_issuer(issuer_id,id)');
   $fks=self::foreignKeys($db,$p);if(!$fks){$name='nefrfk_'.substr(hash('sha256',$t),0,40);IssuerStorage::query($db,'ALTER TABLE '.$t.' ADD CONSTRAINT '.$name.' FOREIGN KEY(issuer_id) REFERENCES '.$p.'issuers_registry(id)');}
  }
  self::assertValid($db,$p);
 }
 private static function assertColumn($db,string$p):void
 {
  $t=self::table($p);$sqlite=self::sqlite($db);$matches=array_values(array_filter(self::columns($db,$t),fn($r)=>$r[$sqlite?'name':'Field']==='issuer_id'));if(count($matches)!==1)throw new RuntimeException('native_estimate_form_schema_invalid');$c=$matches[0];
  if($sqlite){if(strtoupper($c['type'])!=='INTEGER'||(int)$c['notnull']!==0||$c['dflt_value']!==null||(int)$c['pk']!==0)throw new RuntimeException('native_estimate_form_schema_invalid');}
  elseif(!preg_match('/^int(?:\(\d+\))? unsigned$/D',strtolower($c['Type']))||$c['Null']!=='YES'||$c['Default']!==null||$c['Extra']!==''||$c['Collation']!==null)throw new RuntimeException('native_estimate_form_schema_invalid');
 }
 private static function foreignKeys($db,string$p):array
 {
  $t=self::table($p);if(self::sqlite($db))return array_values(array_filter(IssuerStorage::query($db,'PRAGMA foreign_key_list('.$t.')')->result_array(),fn($r)=>$r['from']==='issuer_id'));
  return IssuerStorage::query($db,'SELECT k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.UPDATE_RULE,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.COLUMN_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[$t,'issuer_id'])->result_array();
 }
 public static function assertValid($db,string$p):void
 {
  $t=self::table($p);self::assertColumn($db,$p);$sqlite=self::sqlite($db);if(!$sqlite){$engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t])->row_array();if(strtolower((string)($engine['ENGINE']??''))!=='innodb')throw new RuntimeException('native_estimate_form_schema_invalid');}$fks=self::foreignKeys($db,$p);if(count($fks)!==1)throw new RuntimeException('native_estimate_form_schema_invalid');$f=$fks[0];if(($f[$sqlite?'table':'REFERENCED_TABLE_NAME']??null)!==$p.'issuers_registry'||($f[$sqlite?'to':'REFERENCED_COLUMN_NAME']??null)!=='id'||!in_array($f[$sqlite?'on_update':'UPDATE_RULE'],['NO ACTION','RESTRICT'],true)||!in_array($f[$sqlite?'on_delete':'DELETE_RULE'],['NO ACTION','RESTRICT'],true))throw new RuntimeException('native_estimate_form_schema_invalid');
  if($sqlite){$name='nefri_'.substr(hash('sha256',$t),0,40);$indexes=IssuerStorage::query($db,'PRAGMA index_list('.$t.')')->result_array();$matches=array_values(array_filter($indexes,fn($r)=>$r['name']===$name));if(count($matches)!==1||(int)$matches[0]['unique']!==0||(int)$matches[0]['partial']!==0||array_column(IssuerStorage::query($db,'PRAGMA index_info('.$name.')')->result_array(),'name')!==['issuer_id','id'])throw new RuntimeException('native_estimate_form_schema_invalid');}
  else{$rows=array_values(array_filter(IssuerStorage::query($db,'SHOW INDEX FROM '.$t)->result_array(),fn($r)=>$r['Key_name']==='native_issuer'));usort($rows,fn($a,$b)=>(int)$a['Seq_in_index']<=>(int)$b['Seq_in_index']);if(array_column($rows,'Column_name')!==['issuer_id','id'])throw new RuntimeException('native_estimate_form_schema_invalid');foreach($rows as$r)if((int)$r['Non_unique']!==1||$r['Sub_part']!==null||strtoupper($r['Index_type'])!=='BTREE')throw new RuntimeException('native_estimate_form_schema_invalid');}
 }
}
