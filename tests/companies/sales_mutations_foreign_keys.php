<?php
/** New V4 metadata proof. Real engine FKs, no altered historical assertions or fabricated metadata. */
register_shutdown_function(static function():void{
 if(!isset($GLOBALS['db'],$GLOBALS['salesFkProbe']))return;$db=$GLOBALS['db'];$probe=$GLOBALS['salesFkProbe'];
 foreach(['company_sales_copy_requests','company_sales_revisions','company_sales_documents','staff','issuers_registry','foreign_registry']as$table)try{$db->query('DROP TABLE IF EXISTS `'.$probe.$table.'`');}catch(Throwable$ignored){}
 if($db->driver()==='mysql')try{$db->query('DROP TABLE IF EXISTS `asaas_test`.`'.$probe.'issuers_registry`');}catch(Throwable$ignored){}
});
require (getenv('SALES_FK_TEST_ROOT')?:__DIR__).'/sales_mutations_setup.php';
$probe=$p.'fk4_';$GLOBALS['salesFkProbe']=$probe;
foreach(['issuers_registry','staff','company_sales_documents']as$table){
 if($db->driver()==='mysql')$db->query('CREATE TABLE `'.$probe.$table.'` LIKE `'.$p.$table.'`');
 else{$sql=$db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name=?",[$p.$table])->row()->sql;$db->query(str_replace($p,$probe,$sql));}
}
if($db->driver()==='mysql')$db->query('CREATE TABLE `'.$probe.'foreign_registry` LIKE `'.$probe.'issuers_registry`');else{$sql=$db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name=?",[$probe.'issuers_registry'])->row()->sql;$db->query(str_replace($probe.'issuers_registry',$probe.'foreign_registry',$sql));}
$db->query('CREATE UNIQUE INDEX `'.$probe.'parent_pair` ON `'.$probe.'company_sales_documents`(issuer_id,id)');
CompanySalesMutationsSchema::ensure($db,$probe);CompanySalesMutationsSchema::ensure($db,$probe,false);essentialCheck(CompanySalesMutationsSchema::present($db,$probe),'V4 accepts both native canonical FK sets');
$expected=['company_sales_revisions'=>['issuer_id'=>['issuers_registry','id'],'document_id'=>['company_sales_documents','id']],'company_sales_copy_requests'=>['issuer_id'=>['issuers_registry','id'],'source_document_id'=>['company_sales_documents','id'],'result_document_id'=>['company_sales_documents','id']]];
$checks=static function(string$label)use($db,$probe):void{essentialThrows(fn()=>CompanySalesMutationsSchema::ensure($db,$probe,false),'issuer_sales_mutation_schema_invalid',$label.' validation-only');essentialThrows(fn()=>CompanySalesMutationsSchema::present($db,$probe),'issuer_sales_mutation_schema_invalid',$label.' presence rejects corrupt partial capability');essentialThrows(fn()=>CompanySalesMutationsSchema::ensure($db,$probe),'issuer_sales_mutation_schema_invalid',$label.' idempotent ensure cannot bless existing drift');};
$cases=0;
if($db->driver()==='sqlite'){
 foreach($expected as$table=>$parents){$name=$probe.$table;$ddl=$db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name=?",[$name])->row()->sql;$indexes=array_column($db->query("SELECT sql FROM sqlite_master WHERE type='index' AND tbl_name=? AND sql IS NOT NULL ORDER BY name",[$name])->result_array(),'sql');
  $replace=static function(string$sql)use($db,$name,$indexes):void{$db->query('DROP TABLE `'.$name.'`');$db->query($sql);foreach($indexes as$index)$db->query($index);};
  $fault=static function(string$label,callable$alter)use($replace,$ddl,$checks,$table,&$cases):void{$changed=$alter($ddl);essentialCheck($changed!==$ddl,'real SQLite DDL differs '.$label);$replace($changed);try{$checks('SQLite '.$table.' '.$label);}finally{$replace($ddl);}++$cases;};
  $add=static fn(string$sql,string$clause):string=>substr($sql,0,-1).','.$clause.')';$issuer='FOREIGN KEY (`issuer_id`) REFERENCES `'.$probe.'issuers_registry` (`id`)';
  $fault('missing FK',fn($sql)=>str_replace(','.$issuer,'',$sql));
  $fault('extra actor CASCADE',fn($sql)=>$add($sql,'FOREIGN KEY(actor_id) REFERENCES `'.$probe.'staff`(staffid) ON DELETE CASCADE'));
  $fault('duplicate expected FK',fn($sql)=>$add($sql,$issuer));
  $fault('foreign contract parent',fn($sql)=>str_replace($issuer,'FOREIGN KEY (`issuer_id`) REFERENCES `'.$probe.'foreign_registry` (`id`)',$sql));
  $pair=$table==='company_sales_revisions'?'document_id':'source_document_id';$fault('composite rule',fn($sql)=>str_replace($issuer,'FOREIGN KEY(issuer_id,'.$pair.') REFERENCES `'.$probe.'company_sales_documents`(issuer_id,id)',$sql));
  foreach($parents as$column=>[$parent,$key]){$clause='FOREIGN KEY (`'.$column.'`) REFERENCES `'.$probe.$parent.'` (`'.$key.'`)';foreach(['DELETE','UPDATE']as$action)$fault($column.' '.$action.' CASCADE',fn($sql)=>str_replace($clause,$clause.' ON '.$action.' CASCADE',$sql));}
  foreach(['RESTRICT','NO ACTION']as$action){$valid=$ddl;foreach($parents as$column=>[$parent,$key]){$clause='FOREIGN KEY (`'.$column.'`) REFERENCES `'.$probe.$parent.'` (`'.$key.'`)';$valid=str_replace($clause,$clause.' ON DELETE '.$action.' ON UPDATE '.$action,$valid);}$replace($valid);CompanySalesMutationsSchema::ensure($db,$probe,false);essentialCheck(CompanySalesMutationsSchema::present($db,$probe),'SQLite permits explicit '.$action.' on every expected '.$table.' FK');$replace($ddl);}
 }
}else{
 $foreign=static function(string$table,string$column)use($db,$probe):string{$rows=$db->query('SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',[$probe.$table,$column])->result_array();if(count($rows)!==1||!preg_match('/^[A-Za-z0-9_]+$/D',$rows[0]['CONSTRAINT_NAME']))throw new RuntimeException('unsafe_v4_fixture_fk');return$rows[0]['CONSTRAINT_NAME'];};
 $drop=static fn(string$table,string$name)=>$db->query('ALTER TABLE `'.$probe.$table.'` DROP FOREIGN KEY `'.$name.'`');
 $add=static function(string$table,string$name,string$columns,string$target,string$keys,string$actions='')use($db,$probe):void{$db->query('ALTER TABLE `'.$probe.$table.'` ADD CONSTRAINT `'.$name.'` FOREIGN KEY('.$columns.') REFERENCES '.$target.'('.$keys.') '.$actions);};
 foreach($expected as$table=>$parents){$extra=$probe.$table.'_extra';$original=$foreign($table,'issuer_id');
  $drop($table,$original);try{$checks('Maria missing issuer FK '.$table);}finally{$add($table,$original,'issuer_id','`'.$probe.'issuers_registry`','id');}++$cases;
  foreach([['extra actor CASCADE','actor_id','`'.$probe.'staff`','staffid','ON DELETE CASCADE'],['duplicate expected','issuer_id','`'.$probe.'issuers_registry`','id','']]as[$label,$column,$target,$key,$actions]){$add($table,$extra,$column,$target,$key,$actions);try{$checks('Maria '.$label.' '.$table);}finally{$drop($table,$extra);}++$cases;}
  $drop($table,$original);$add($table,$original,'issuer_id','`'.$probe.'foreign_registry`','id');try{$checks('Maria foreign contract parent '.$table);}finally{$drop($table,$original);$add($table,$original,'issuer_id','`'.$probe.'issuers_registry`','id');}++$cases;
  $pair=$table==='company_sales_revisions'?'document_id':'source_document_id';$drop($table,$original);$add($table,$original,'issuer_id,'.$pair,'`'.$probe.'company_sales_documents`','issuer_id,id');try{$checks('Maria composite FK '.$table);}finally{$drop($table,$original);$add($table,$original,'issuer_id','`'.$probe.'issuers_registry`','id');}++$cases;
  foreach($parents as$column=>[$parent,$key])foreach(['DELETE','UPDATE']as$action){$name=$foreign($table,$column);$drop($table,$name);$add($table,$name,$column,'`'.$probe.$parent.'`',$key,'ON '.$action.' CASCADE');try{$checks('Maria '.$column.' '.$action.' CASCADE '.$table);}finally{$drop($table,$name);$add($table,$name,$column,'`'.$probe.$parent.'`',$key);}++$cases;}
  foreach(['RESTRICT','NO ACTION']as$action){foreach($parents as$column=>[$parent,$key]){$name=$foreign($table,$column);$drop($table,$name);$add($table,$name,$column,'`'.$probe.$parent.'`',$key,'ON DELETE '.$action.' ON UPDATE '.$action);}CompanySalesMutationsSchema::ensure($db,$probe,false);essentialCheck(CompanySalesMutationsSchema::present($db,$probe),'Maria permits explicit '.$action.' on every expected '.$table.' FK');}
 }
 $db->query('CREATE TABLE `asaas_test`.`'.$probe.'issuers_registry` LIKE `'.$probe.'issuers_registry`');
 foreach(array_keys($expected)as$table){$original=$foreign($table,'issuer_id');$drop($table,$original);$add($table,$original,'issuer_id','`asaas_test`.`'.$probe.'issuers_registry`','id');try{$checks('Maria cross-schema FK with otherwise exact parent name '.$table);}finally{$drop($table,$original);$add($table,$original,'issuer_id','`'.$probe.'issuers_registry`','id');}++$cases;}
}
CompanySalesMutationsSchema::ensure($db,$probe);CompanySalesMutationsSchema::ensure($db,$probe,false);
$actual=[];foreach(array_keys($expected)as$table){$rows=$db->driver()==='sqlite'?$db->query('PRAGMA foreign_key_list(`'.$probe.$table.'`)')->result_array():$db->query('SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',[$probe.$table])->result_array();$actual[$table]=count($rows);essentialCheck((int)$db->query('SELECT COUNT(*) n FROM `'.$probe.$table.'`')->row()->n===0,'metadata failures cannot backfill evidence '.$table);}
essentialCheck($actual===['company_sales_revisions'=>2,'company_sales_copy_requests'=>3],'V4 retains exact native two/three FK topology and only two ledgers');
echo 'Sales V4 exact foreign keys: '.$cases.' real invalid-engine variants; '.$GLOBALS['essentialChecks'].' cumulative ('.$db->driver().'); both DELETE/UPDATE and NO ACTION/RESTRICT, duplicates/composites/extra/foreign parents.'.PHP_EOL;
