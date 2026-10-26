<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyContractsSchema.php';
/** Core474-only evolution: native email tickets need no fabricated customer. Historical DDL stays unchanged. */
final class NativeCompanyTicketCustomerSchema
{
    private static function nullable($db,string $p):bool
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        $rows=IssuerStorage::query($db,'SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',[$p.'company_tickets','client_id'])->result_array();
        if(count($rows)!==1||!in_array($rows[0]['IS_NULLABLE']??null,['NO','YES'],true))throw new RuntimeException('issuer_native_ticket_customer_schema_invalid');
        return$rows[0]['IS_NULLABLE']==='YES';
    }
    public static function assertValid($db,string $p):void{self::assertTable($db,$p,true);}
    /** Called explicitly by core474, never by a GET, ticket writer or historical migration. */
    public static function ensure($db,string $p):array
    {
        $nullable=self::nullable($db,$p);self::assertTable($db,$p,$nullable);$before=self::fingerprint($db,$p);
        if(!$nullable)IssuerStorage::query($db,'ALTER TABLE `'.$p.'company_tickets` MODIFY `client_id` int NULL');
        self::assertValid($db,$p);$after=self::fingerprint($db,$p);
        if($before!==$after)throw new RuntimeException('issuer_native_ticket_customer_rows_changed');
        return ['changed'=>!$nullable,'rows'=>$after['rows'],'sha256'=>$after['sha256']];
    }
    private static function fingerprint($db,string $p):array
    {
        $hash=hash_init('sha256');$count=0;$last=0;
        do{
            $rows=IssuerStorage::query($db,'SELECT * FROM `'.$p.'company_tickets` WHERE id>? ORDER BY id LIMIT 500',[$last])->result_array();
            foreach($rows as$row){$id=filter_var($row['id']??null,FILTER_VALIDATE_INT);if($id===false||$id<=$last)throw new RuntimeException('issuer_native_ticket_customer_rows_invalid');$last=$id;ksort($row);$row=array_map(static fn($value)=>$value===null?null:(string)$value,$row);hash_update($hash,json_encode($row,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");$count++;}
        }while(count($rows)===500);
        return ['rows'=>$count,'sha256'=>hash_final($hash)];
    }
    private static function assertTable($db,string $p,bool $nullable):void
    {
        $table='company_tickets';$parts=CompanyContractsSchema::definitions($p)[$table];
        if($nullable)$parts=array_map(static fn($part)=>$part==='`client_id` int NOT NULL'?'`client_id` int NULL':$part,$parts);
            $name=$p.$table;$columns=[];
            foreach(IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->result_array() as $r)$columns[$r['COLUMN_NAME']]=$r;
            $expected=[];
            foreach($parts as $definition){
                if(!preg_match('/^`([^`]+)` (varchar\([0-9]+\)|bigint unsigned|int unsigned|int|text|datetime|date)(.*)$/D',$definition,$m))continue;
                [$all,$column,$type,$tail]=$m;$expected[]=$column;$r=$columns[$column]??[];
                $actual=preg_replace('/^(bigint|int)\([0-9]+\)/','$1',strtolower($r['COLUMN_TYPE']??''));$nullable=str_contains($tail,'NOT NULL')?'NO':'YES';
                if($actual!==$type||($r['IS_NULLABLE']??null)!==$nullable||!IssuerStorage::sqlNullDefault($r)||strtolower($r['EXTRA']??'')!==(str_contains($tail,'AUTO_INCREMENT')?'auto_increment':''))throw new RuntimeException('issuer_native_ticket_customer_schema_invalid');
                if(preg_match('/COLLATE ([a-z0-9_]+)/',$tail,$c)&&($r['COLLATION_NAME']??null)!==$c[1])throw new RuntimeException('issuer_native_ticket_customer_schema_invalid');
            }
            if(array_diff(array_keys($columns),$expected))throw new RuntimeException('issuer_native_ticket_customer_schema_invalid');
            $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('issuer_native_ticket_customer_schema_invalid');
            $indexes=[];foreach(IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$name])->result_array() as $r){if($r['SUB_PART']!==null)throw new RuntimeException('issuer_native_ticket_customer_schema_invalid');if(isset($indexes[$r['INDEX_NAME']]['non_unique'])&&$indexes[$r['INDEX_NAME']]['non_unique']!==(int)$r['NON_UNIQUE'])throw new RuntimeException('issuer_native_ticket_customer_schema_invalid');$indexes[$r['INDEX_NAME']]['columns'][]=$r['COLUMN_NAME'];$indexes[$r['INDEX_NAME']]['non_unique']=(int)$r['NON_UNIQUE'];}
            // MySQL may materialize a separate single-column issuer_id index for
            // the FK even though ticket_owner already starts with issuer_id.
            // Accept only that exact non-unique support index; client_id's FK
            // support index remains mandatory after making it nullable.
            $supportIndexes=['client_id'=>true,'issuer_id'=>false];
            $expectedIndexes=array_keys($supportIndexes);
            foreach($supportIndexes as $key=>$required){
                if(!isset($indexes[$key])){if($required)throw new RuntimeException('issuer_native_ticket_customer_schema_invalid');continue;}
                if(($indexes[$key]['columns']??[])!==[$key]||($indexes[$key]['non_unique']??null)!==1)throw new RuntimeException('issuer_native_ticket_customer_schema_invalid');
            }
            foreach($parts as $definition){
                if(preg_match('/^(UNIQUE )?KEY `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){$key=$m[2];$cols=explode(',',str_replace('`','',$m[3]));$unique=$m[1]!==''?0:1;}
                elseif(preg_match('/^`([^`]+)` .*PRIMARY KEY/',$definition,$m)){$key='PRIMARY';$cols=[$m[1]];$unique=0;}else continue;
                $expectedIndexes[]=$key;
                if(($indexes[$key]['columns']??[])!==$cols||($indexes[$key]['non_unique']??null)!==$unique)throw new RuntimeException('issuer_native_ticket_customer_schema_invalid');
            }
            $unexpectedIndexes=array_values(array_diff(array_keys($indexes),$expectedIndexes));
            if($unexpectedIndexes){sort($unexpectedIndexes,SORT_STRING);throw new RuntimeException('issuer_native_ticket_customer_schema_invalid:unexpected_indexes='.implode(',',$unexpectedIndexes));}
            $foreign=[];foreach(IssuerStorage::query($db,'SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',[$name])->result_array() as $r)$foreign[$r['CONSTRAINT_NAME']][]=$r['COLUMN_NAME'].':'.$r['REFERENCED_TABLE_NAME'].':'.$r['REFERENCED_COLUMN_NAME'];
            $expectedForeign=0;
            foreach($parts as $definition)if(preg_match('/^FOREIGN KEY \(([^)]+)\) REFERENCES `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){$expectedForeign++;$cols=explode(',',str_replace('`','',$m[1]));$refs=explode(',',str_replace('`','',$m[3]));$want=[];foreach($cols as $i=>$col)$want[]=$col.':'.$m[2].':'.$refs[$i];if(!in_array($want,array_values($foreign),true))throw new RuntimeException('issuer_native_ticket_customer_schema_invalid');}
        if(count($foreign)!==$expectedForeign)throw new RuntimeException('issuer_native_ticket_customer_schema_invalid');
        $rules=IssuerStorage::query($db,'SELECT UPDATE_RULE,DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->result_array();
        if(count($rules)!==$expectedForeign)throw new RuntimeException('issuer_native_ticket_customer_schema_invalid');
        foreach($rules as $rule)foreach(['UPDATE_RULE','DELETE_RULE'] as $key)if(!in_array(strtoupper($rule[$key]??''),['RESTRICT','NO ACTION'],true))throw new RuntimeException('issuer_native_ticket_customer_schema_invalid');
    }
}
