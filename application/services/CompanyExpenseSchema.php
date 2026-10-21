<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';

/** Additive company-owned ledger. Never adopts or rewrites legacy expenses. */
final class CompanyExpenseSchema
{
    public static function definitions():array
    {
        return [
            'company_expense_categories'=>['id'=>'int unsigned AUTO_INCREMENT','issuer_id'=>'int unsigned','name'=>'varchar(191)','active'=>'tinyint','revision'=>'int unsigned','created_at'=>'datetime'],
            'company_expenses'=>['id'=>'int unsigned AUTO_INCREMENT','issuer_id'=>'int unsigned','number'=>'int unsigned','category_id'=>'int unsigned','client_id'=>'int','client_name'=>'varchar(191)','project_id'=>'int unsigned','project_name'=>'varchar(191)','currency'=>'varchar(3)','expense_date'=>'date','description'=>'varchar(191)','note'=>'text','amount_cents'=>'bigint unsigned','tax_cents'=>'bigint unsigned','tax_rate'=>'int unsigned','total_cents'=>'bigint unsigned','state'=>'varchar(16)','revision'=>'int unsigned','created_by'=>'int','created_at'=>'datetime','updated_at'=>'datetime'],
            'company_expense_files'=>['id'=>'int unsigned AUTO_INCREMENT','file_key'=>'varchar(64)','issuer_id'=>'int unsigned','actor_id'=>'int','expense_id'=>'int unsigned','filename'=>'varchar(191)','mime'=>'varchar(40)','content_hash'=>'varchar(64)','byte_size'=>'int unsigned','content'=>'mediumblob','active'=>'tinyint','created_at'=>'datetime'],
            'company_expense_requests'=>['request_key'=>'varchar(64)','issuer_id'=>'int unsigned','actor_id'=>'int','operation'=>'varchar(16)','input_hash'=>'varchar(64)','result_id'=>'int unsigned','created_at'=>'datetime'],
        ];
    }
    public static function ensure($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');
        foreach(self::definitions() as $suffix=>$fields){
            $parts=[];foreach($fields as $name=>$type)$parts[]='`'.$name.'` '.$type.(str_starts_with($type,'varchar')||$type==='text'?' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin':'').' NOT NULL';
            $parts[]='PRIMARY KEY (`'.($suffix==='company_expense_requests'?'request_key':'id').'`)';
            if($suffix==='company_expense_files'){$parts[]='UNIQUE KEY `expense_file_key` (`file_key`)';$parts[]='KEY `expense_file_parent` (`issuer_id`,`expense_id`,`active`)';$parts[]='FOREIGN KEY (`expense_id`) REFERENCES `'.$p.'company_expenses` (`id`)';}
            $parts[]='FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)';
            if($suffix==='company_expense_categories')$parts[]='UNIQUE KEY `category_name` (`issuer_id`,`name`)';
            if($suffix==='company_expenses'){$parts[]='UNIQUE KEY `expense_number` (`issuer_id`,`number`)';$parts[]='KEY `expense_page` (`issuer_id`,`id`)';$parts[]='FOREIGN KEY (`category_id`) REFERENCES `'.$p.'company_expense_categories` (`id`)';}
            IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$suffix.'` ('.implode(',',$parts).') ENGINE=InnoDB');
        }
        self::assertValid($db,$p);
    }
    public static function assertValid($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');
        $conn=$db->pdo??$db->conn_id??null;if($conn instanceof PDO&&$conn->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')return;
        foreach(self::definitions() as $suffix=>$fields){
            $table=$p.$suffix;$columns=[];
            foreach(IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->result_array() as $row)$columns[$row['COLUMN_NAME']]=$row;
            foreach($fields as $name=>$type){$row=$columns[$name]??[];$auto=str_contains($type,' AUTO_INCREMENT');$type=str_replace(' AUTO_INCREMENT','',$type);$actual=preg_replace('/^(tinyint|int|bigint)\([0-9]+\)/','$1',strtolower($row['COLUMN_TYPE']??''));
                if($actual!==$type||($row['IS_NULLABLE']??null)!=='NO'||!IssuerStorage::sqlNullDefault($row)||($auto&&!str_contains(strtolower($row['EXTRA']??''),'auto_increment'))||((str_starts_with($type,'varchar')||$type==='text')&&($row['COLLATION_NAME']??null)!=='utf8mb4_bin'))throw new RuntimeException('issuer_expense_schema_invalid');}
            $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('issuer_expense_schema_invalid');
            $indexes=[];foreach(IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$table])->result_array() as $row){if($row['SUB_PART']!==null)throw new RuntimeException('issuer_expense_schema_invalid');$indexes[$row['INDEX_NAME']]['columns'][]=$row['COLUMN_NAME'];$indexes[$row['INDEX_NAME']]['non_unique']=(int)$row['NON_UNIQUE'];}
            $required=['PRIMARY'=>[[$suffix==='company_expense_requests'?'request_key':'id'],0]];
            if($suffix==='company_expenses')$required+=['expense_number'=>[['issuer_id','number'],0],'expense_page'=>[['issuer_id','id'],1]];
            if($suffix==='company_expense_files')$required+=['expense_file_key'=>[['file_key'],0],'expense_file_parent'=>[['issuer_id','expense_id','active'],1]];
            if($suffix==='company_expense_categories')$required+=['category_name'=>[['issuer_id','name'],0]];
            foreach($required as $name=>[$columns,$nonUnique])if(($indexes[$name]['columns']??[])!==$columns||($indexes[$name]['non_unique']??null)!==$nonUnique)throw new RuntimeException('issuer_expense_schema_invalid');
            $foreign=['issuer_id'=>['issuers_registry','id']];if($suffix==='company_expense_files')$foreign['expense_id']=['company_expenses','id'];if($suffix==='company_expenses')$foreign['category_id']=['company_expense_categories','id'];
            foreach($foreign as $column=>[$target,$key]){$rows=IssuerStorage::query($db,'SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$table,$column])->result_array();if(count($rows)!==1||$rows[0]['REFERENCED_TABLE_NAME']!==$p.$target||$rows[0]['REFERENCED_COLUMN_NAME']!==$key)throw new RuntimeException('issuer_expense_schema_invalid');}
        }
    }
}
