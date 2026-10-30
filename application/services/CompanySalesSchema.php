<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';

/** Private sales documents and repeatable invoice intentions; no legacy ownership adoption. */
final class CompanySalesSchema
{
    public static function definitions():array
    {
        return [
            'company_sales_documents'=>['id'=>'int unsigned AUTO_INCREMENT','issuer_id'=>'int unsigned','actor_id'=>'int','conversion_actor_id'=>'int','client_id'=>'int','currency'=>'int','kind'=>'varchar(16)','number'=>'int unsigned','state'=>'varchar(20)','payload_json'=>'longtext','context_json'=>'longtext','origin_hash'=>'varchar(64)','total'=>'decimal(15,2)','request_key'=>'varchar(64)','input_hash'=>'varchar(64)','invoice_id'=>'int','created_at'=>'datetime'],
            'company_recurring'=>['id'=>'int unsigned AUTO_INCREMENT','issuer_id'=>'int unsigned','actor_id'=>'int','payload_json'=>'longtext','context_json'=>'longtext','origin_hash'=>'varchar(64)','next_date'=>'date','anchor_day'=>'int unsigned','interval_months'=>'int unsigned','max_runs'=>'int unsigned','completed_runs'=>'int unsigned','state'=>'varchar(20)','last_error'=>'varchar(64)','request_key'=>'varchar(64)','input_hash'=>'varchar(64)','created_at'=>'datetime'],
            'company_recurring_runs'=>['request_key'=>'varchar(64)','issuer_id'=>'int unsigned','schedule_id'=>'int unsigned','run_date'=>'date','invoice_id'=>'int','state'=>'varchar(20)','created_at'=>'datetime'],
        ];
    }
    public static function indexes(string $table):array
    {
        $r=['PRIMARY'=>[[$table==='company_recurring_runs'?'request_key':'id'],0]];
        if($table==='company_sales_documents')$r+=['sales_request'=>[['request_key'],0],'sales_number'=>[['issuer_id','kind','number'],0],'sales_page'=>[['issuer_id','id'],1],'sales_portal'=>[['issuer_id','client_id','id'],1]];
        if($table==='company_recurring')$r+=['recurring_request'=>[['request_key'],0],'recurring_parent'=>[['issuer_id','id'],0],'recurring_due'=>[['state','next_date'],1]];
        if($table==='company_recurring_runs')$r+=['recurring_period'=>[['issuer_id','schedule_id','run_date'],0]];
        return $r;
    }
    public static function ensure($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        foreach(self::definitions() as $table=>$fields){
            $parts=[];foreach($fields as $name=>$type)$parts[]='`'.$name.'` '.$type.(str_starts_with($type,'varchar')||$type==='longtext'?' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin':'').' NOT NULL';
            foreach(self::indexes($table) as $key=>[$columns,$nonUnique])$parts[]=($key==='PRIMARY'?'PRIMARY KEY':($nonUnique?'KEY':'UNIQUE KEY').' `'.$key.'`').' (`'.implode('`,`',$columns).'`)';
            $parts[]='FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)';
            if($table==='company_recurring_runs')$parts[]='FOREIGN KEY (`issuer_id`,`schedule_id`) REFERENCES `'.$p.'company_recurring` (`issuer_id`,`id`)';
            IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',',$parts).') ENGINE=InnoDB');
        }
        self::assertValid($db,$p);
    }
    public static function assertValid($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        $conn=$db->pdo??$db->conn_id??null;if($conn instanceof PDO&&$conn->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')return;
        foreach(self::definitions() as $table=>$fields){
            $name=$p.$table;$columns=[];
            foreach(IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->result_array() as $r)$columns[$r['COLUMN_NAME']]=$r;
            foreach($fields as $key=>$type){$r=$columns[$key]??[];$auto=str_contains($type,' AUTO_INCREMENT');$type=str_replace(' AUTO_INCREMENT','',$type);$actual=preg_replace('/^(int|bigint)\([0-9]+\)/','$1',strtolower($r['COLUMN_TYPE']??''));if($actual!==$type||($r['IS_NULLABLE']??null)!=='NO'||!IssuerStorage::sqlNullDefault($r)||($auto&&!str_contains(strtolower($r['EXTRA']??''),'auto_increment'))||((str_starts_with($type,'varchar')||$type==='longtext')&&($r['COLLATION_NAME']??null)!=='utf8mb4_bin'))throw new RuntimeException('issuer_sales_schema_invalid');}
            $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('issuer_sales_schema_invalid');
            $keys=[];foreach(IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$name])->result_array() as $r){if($r['SUB_PART']!==null)throw new RuntimeException('issuer_sales_schema_invalid');$keys[$r['INDEX_NAME']]['columns'][]=$r['COLUMN_NAME'];$keys[$r['INDEX_NAME']]['non_unique']=(int)$r['NON_UNIQUE'];}
            foreach(self::indexes($table) as $key=>[$cols,$nonUnique])if(($keys[$key]['columns']??[])!==$cols||($keys[$key]['non_unique']??null)!==$nonUnique)throw new RuntimeException('issuer_sales_schema_invalid');
            $fks=IssuerStorage::query($db,'SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',[$name])->result_array();$expected=[['issuer_id',$p.'issuers_registry','id']];if($table==='company_recurring_runs')$expected=array_merge($expected,[['issuer_id',$p.'company_recurring','issuer_id'],['schedule_id',$p.'company_recurring','id']]);foreach($expected as [$column,$target,$targetCol])if(!array_filter($fks,fn($r)=>$r['COLUMN_NAME']===$column&&$r['REFERENCED_TABLE_NAME']===$target&&$r['REFERENCED_COLUMN_NAME']===$targetCol))throw new RuntimeException('issuer_sales_schema_invalid');if($table==='company_recurring_runs'){$groups=[];foreach($fks as $fk)$groups[$fk['CONSTRAINT_NAME']][]=[$fk['COLUMN_NAME'],$fk['REFERENCED_TABLE_NAME'],$fk['REFERENCED_COLUMN_NAME']];$composite=[['issuer_id',$p.'company_recurring','issuer_id'],['schedule_id',$p.'company_recurring','id']];if(!array_filter($groups,fn($g)=>$g===$composite))throw new RuntimeException('issuer_sales_schema_invalid');}
        }
    }
}
