<?php
/** Schema contract tests: real SQLite DDL and explicit MySQL metadata doubles, not MySQL execution. */
require __DIR__.'/native_invoice_expense_billability.php';
$longPrefix=str_repeat('n',28);$defs=NativeCompanyExpenseBillabilitySchema::definitions($longPrefix);$ddl='CREATE TABLE `'.$longPrefix.NativeCompanyExpenseBillabilitySchema::TABLE.'` ('.implode(',',$defs[NativeCompanyExpenseBillabilitySchema::TABLE]).') ENGINE=InnoDB';
preg_match_all('/CONSTRAINT `([^`]+)`/',$ddl,$names);essentialCheck(count($names[1])===2&&count(array_unique($names[1]))===2&&max(array_map('strlen',$names[1]))<=64,'both foreign keys have distinct bounded deterministic names');essentialCheck($defs===NativeCompanyExpenseBillabilitySchema::definitions($longPrefix),'constraint names are stable for the same physical prefix');
if($db->driver()==='sqlite'){$db->query(preg_replace('/ CHARACTER SET [a-z0-9]+ COLLATE [a-z0-9_]+/','',$ddl));NativeCompanyExpenseBillabilitySchema::assertValid($db,$longPrefix);essentialCheck(true,'real SQLite DDL accepts prefix28 and physical table length62');$db->query('DROP TABLE `'.$longPrefix.NativeCompanyExpenseBillabilitySchema::TABLE.'`');}
$queries=new class{public int $calls=0;public function query($sql,$args=[]){$this->calls++;throw new RuntimeException('No query is permitted');}};
foreach([31,33]as$length){try{NativeCompanyExpenseBillabilitySchema::ensure($queries,str_repeat('n',$length));throw new RuntimeException('oversized table accepted');}catch(InvalidArgumentException$expected){essentialCheck($queries->calls===0,'oversized physical table prefix'.$length.' rejected before any query/DDL');}}
// This double tests validation of server metadata only. The disposable MySQL suite executes actual DDL separately.
$metadata=new class($p){public string $engine='InnoDB',$delete='RESTRICT',$update='RESTRICT';public function __construct(private string$p){}public function query($sql,$args=[]){
 if(str_contains($sql,'information_schema.COLUMNS')){$rows=[];foreach(NativeCompanyExpenseBillabilitySchema::FIELDS as$name=>$type)$rows[]=['COLUMN_NAME'=>$name,'COLUMN_TYPE'=>$type,'IS_NULLABLE'=>'NO','COLUMN_DEFAULT'=>null,'EXTRA'=>'','COLLATION_NAME'=>$name==='source_revision'?'ascii_bin':null];}
 elseif(str_contains($sql,'SELECT ENGINE'))$rows=[['ENGINE'=>$this->engine]];
 elseif(str_contains($sql,'information_schema.STATISTICS'))$rows=[['COLUMN_NAME'=>'expense_id']];
 elseif(str_contains($sql,'information_schema.KEY_COLUMN_USAGE')){$rows=[];foreach(['expense_id'=>'company_expenses','issuer_id'=>'issuers_registry']as$column=>$table)$rows[]=['COLUMN_NAME'=>$column,'REFERENCED_TABLE_NAME'=>$this->p.$table,'REFERENCED_COLUMN_NAME'=>'id','DELETE_RULE'=>$this->delete,'UPDATE_RULE'=>$this->update];}
 else throw new RuntimeException('Unexpected metadata query');return new class($rows){public function __construct(private array$rows){}public function result_array(){return$this->rows;}public function row_array(){return$this->rows[0]??[];}};
 }};
NativeCompanyExpenseBillabilitySchema::assertValid($metadata,$p);essentialCheck(true,'explicit valid MySQL metadata contract accepted');
foreach([['engine','MyISAM'],['delete','CASCADE'],['update','SET NULL']]as[$field,$bad]){$old=$metadata->$field;$metadata->$field=$bad;essentialThrows(fn()=>NativeCompanyExpenseBillabilitySchema::assertValid($metadata,$p),'issuer_native_expense_billability_schema_invalid','MySQL metadata rejects '.$field.' drift');$metadata->$field=$old;}
echo 'Expense metadata schema bounds/engine/referential rules: '.$GLOBALS['essentialChecks']." cumulative PASS; MySQL metadata doubles explicitly identified.\n";
