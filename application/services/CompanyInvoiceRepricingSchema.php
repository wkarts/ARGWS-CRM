<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Additive proposal, reviewed identity and future-payload journal. No existing row is backfilled. */
final class CompanyInvoiceRepricingSchema
{
    public static function definitions():array
    {
        return [
            'company_invoice_reprice_proposals'=>['request_key'=>'varchar(64)','issuer_id'=>'int unsigned','actor_id'=>'int','state'=>'varchar(16)','revision'=>'varchar(64)','input_json'=>'longtext','input_hash'=>'varchar(64)','preview_json'=>'longtext','preview_hash'=>'varchar(64)','timezone'=>'varchar(64)','execute_at'=>'datetime NULL','created_at'=>'datetime','confirmed_at'=>'datetime NULL','applied_at'=>'datetime NULL'],
            'company_invoice_schedule_prices'=>['id'=>'int unsigned AUTO_INCREMENT','issuer_id'=>'int unsigned','schedule_id'=>'int unsigned','actor_id'=>'int','proposal_key'=>'varchar(64)','effective_date'=>'date','before_hash'=>'varchar(64)','payload_json'=>'longtext','payload_hash'=>'varchar(64)','created_at'=>'datetime'],
            'company_invoice_schedule_item_links'=>['issuer_id'=>'int unsigned','schedule_id'=>'int unsigned','line_index'=>'int unsigned','item_id'=>'varchar(32)','line_hash'=>'varchar(64)','revision'=>'varchar(64)','actor_id'=>'int','reviewed_at'=>'datetime'],
        ];
    }
    public static function indexes(string $table):array
    {
        return match($table){
            'company_invoice_reprice_proposals'=>['PRIMARY'=>[['request_key'],0],'reprice_owner'=>[['issuer_id','request_key'],0],'reprice_due'=>[['state','execute_at'],1]],
            'company_invoice_schedule_prices'=>['PRIMARY'=>[['id'],0],'reprice_once'=>[['issuer_id','schedule_id','proposal_key'],0],'reprice_history'=>[['issuer_id','schedule_id','id'],1]],
            'company_invoice_schedule_item_links'=>['PRIMARY'=>[['issuer_id','schedule_id','line_index'],0]],
        };
    }
    private static function foreign(string $table,string $p):array
    {
        $f=[[['issuer_id',$p.'issuers_registry','id']]];
        if($table!=='company_invoice_reprice_proposals')$f[]=[['issuer_id',$p.'company_invoice_schedules','issuer_id'],['schedule_id',$p.'company_invoice_schedules','id']];
        if($table==='company_invoice_schedule_prices')$f[]=[['issuer_id',$p.'company_invoice_reprice_proposals','issuer_id'],['proposal_key',$p.'company_invoice_reprice_proposals','request_key']];
        return$f;
    }
    private static function sqlite($db):bool{$c=$db->pdo??$db->conn_id??null;return$c instanceof PDO&&$c->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';}
    public static function tables():array{return array_keys(self::definitions());}
    public static function availableTables($db,string $p):array
    {
        $found=array_filter(self::tables(),fn($t)=>$db->table_exists($p.$t));
        if($found&&count($found)!==count(self::tables()))throw new RuntimeException('invoice_reprice_schema_incomplete');
        return$found?self::tables():[];
    }
    public static function ensure($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        IssuerTransaction::assertIndependent($db);$sqlite=self::sqlite($db);
        foreach(self::definitions()as$table=>$fields){$parts=[];$inline=null;
            foreach($fields as$name=>$type){$auto=str_contains($type,' AUTO_INCREMENT');$null=str_ends_with($type,' NULL');$type=str_replace(' NULL','',$type);
                if($sqlite&&$auto){$parts[]='`'.$name.'` INTEGER PRIMARY KEY AUTOINCREMENT';$inline=$name;continue;}
                $parts[]='`'.$name.'` '.$type.(!$sqlite&&(str_starts_with($type,'varchar')||str_contains($type,'text'))?' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin':'').($null?' NULL':' NOT NULL');
            }
            foreach(self::indexes($table)as$key=>[$cols,$non]){
                if($sqlite&&($non||$key==='PRIMARY'&&$inline!==null))continue;
                $kind=$key==='PRIMARY'?'PRIMARY KEY':($sqlite?'UNIQUE':($non?'KEY':'UNIQUE KEY').' `'.$key.'`');
                $parts[]=$kind.' (`'.implode('`,`',$cols).'`)';
            }
            foreach(self::foreign($table,$p)as$fk)$parts[]='FOREIGN KEY (`'.implode('`,`',array_column($fk,0)).'`) REFERENCES `'.$fk[0][1].'` (`'.implode('`,`',array_column($fk,2)).'`)';
            IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',',$parts).')'.($sqlite?'':' ENGINE=InnoDB'));
        }
        if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);self::assertValid($db,$p);
    }
    public static function assertValid($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        if(count(self::availableTables($db,$p))!==3)throw new RuntimeException('invoice_reprice_schema_required');
        foreach(self::definitions()as$table=>$fields){
            if(self::sqlite($db)){$rows=IssuerStorage::query($db,'PRAGMA table_info(`'.$p.$table.'`)')->result_array();if(array_column($rows,'name')!==array_keys($fields))throw new RuntimeException('invoice_reprice_schema_invalid');continue;}
            $cols=[];foreach(IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.$table])->result_array()as$r)$cols[$r['COLUMN_NAME']]=$r;
            if(count($cols)!==count($fields))throw new RuntimeException('invoice_reprice_schema_invalid');
            foreach($fields as$name=>$type){$r=$cols[$name]??[];$auto=str_contains($type,' AUTO_INCREMENT');$null=str_ends_with($type,' NULL');$expected=str_replace([' AUTO_INCREMENT',' NULL'],'',$type);$actual=preg_replace('/^int\([0-9]+\)/','int',strtolower($r['COLUMN_TYPE']??''));
                if($actual!==$expected||($r['IS_NULLABLE']??'')!==($null?'YES':'NO')||!IssuerStorage::sqlNullDefault($r)||$auto&&!str_contains(strtolower($r['EXTRA']??''),'auto_increment')||(str_starts_with($type,'varchar')||str_contains($type,'text'))&&($r['COLLATION_NAME']??'')!=='utf8mb4_bin')throw new RuntimeException('invoice_reprice_schema_invalid');
            }
            $meta=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.$table])->row_array();if(strtoupper($meta['ENGINE']??'')!=='INNODB')throw new RuntimeException('invoice_reprice_schema_invalid');
            $idx=[];foreach(IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$p.$table])->result_array()as$r){if($r['SUB_PART']!==null)throw new RuntimeException('invoice_reprice_schema_invalid');$idx[$r['INDEX_NAME']]['columns'][]=$r['COLUMN_NAME'];$idx[$r['INDEX_NAME']]['non_unique']=(int)$r['NON_UNIQUE'];}
            foreach(self::indexes($table)as$key=>[$cols,$non])if(($idx[$key]['columns']??[])!==$cols||($idx[$key]['non_unique']??null)!==$non)throw new RuntimeException('invoice_reprice_schema_invalid');
            $fk=[];foreach(IssuerStorage::query($db,'SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',[$p.$table])->result_array()as$r)$fk[$r['CONSTRAINT_NAME']][]=[$r['COLUMN_NAME'],$r['REFERENCED_TABLE_NAME'],$r['REFERENCED_COLUMN_NAME']];
            foreach(self::foreign($table,$p)as$need)if(!in_array($need,array_values($fk),true))throw new RuntimeException('invoice_reprice_schema_invalid');
        }
    }
}
