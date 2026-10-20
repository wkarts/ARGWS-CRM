<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
/** Dedicated mutation receipts; token issuance receipts retain their original meaning. */
final class CompanyFinancialApiSchema
{
    public static function definitions():array{return ['company_financial_api_requests'=>['request_key'=>'varchar(64)','issuer_id'=>'int unsigned','actor_id'=>'int','operation'=>'varchar(32)','input_hash'=>'varchar(64)','target_json'=>'text','token_id'=>'varchar(32)','created_at'=>'datetime']];}
    public static function ensure($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');
        $fields=[];foreach(self::definitions()['company_financial_api_requests'] as $name=>$type)$fields[]='`'.$name.'` '.$type.(in_array($name,['request_key','operation','input_hash','token_id'],true)?' CHARACTER SET ascii COLLATE ascii_bin':($type==='text'?' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin':'')).' NOT NULL';
        $fields[]='PRIMARY KEY(request_key)';$fields[]='KEY financial_api_owner(issuer_id,actor_id)';$fields[]='FOREIGN KEY(issuer_id) REFERENCES '.$p.'issuers_registry(id)';$fields[]='FOREIGN KEY(token_id) REFERENCES '.$p.'company_api_tokens(token_id)';
        IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS '.$p.'company_financial_api_requests('.implode(',',$fields).') ENGINE=InnoDB');self::assertValid($db,$p);
    }
    public static function assertValid($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');$conn=$db->pdo??$db->conn_id??null;if($conn instanceof PDO&&$conn->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')return;
        $t=$p.'company_financial_api_requests';$columns=[];foreach(IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t])->result_array() as $row)$columns[$row['COLUMN_NAME']]=$row;
        foreach(self::definitions()['company_financial_api_requests'] as $name=>$type){$row=$columns[$name]??[];$collation=in_array($name,['request_key','operation','input_hash','token_id'],true)?'ascii_bin':($type==='text'?'utf8mb4_bin':null);if(preg_replace('/^int\([0-9]+\)/','int',strtolower($row['COLUMN_TYPE']??''))!==$type||($row['IS_NULLABLE']??null)!=='NO'||!IssuerStorage::sqlNullDefault($row)||($collation!==null&&($row['COLLATION_NAME']??null)!==$collation))throw new RuntimeException('issuer_financial_api_schema_invalid');}
        if(strtoupper(IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t])->row_array()['ENGINE']??'')!=='INNODB')throw new RuntimeException('issuer_financial_api_schema_invalid');
        $indexes=[];foreach(IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$t])->result_array() as $row){if($row['SUB_PART']!==null)throw new RuntimeException('issuer_financial_api_schema_invalid');$indexes[$row['INDEX_NAME']]['columns'][]=$row['COLUMN_NAME'];$indexes[$row['INDEX_NAME']]['non_unique']=(int)$row['NON_UNIQUE'];}
        foreach(['PRIMARY'=>[['request_key'],0],'financial_api_owner'=>[['issuer_id','actor_id'],1]] as $name=>[$cols,$nonunique])if(($indexes[$name]['columns']??[])!==$cols||($indexes[$name]['non_unique']??null)!==$nonunique)throw new RuntimeException('issuer_financial_api_schema_invalid');
        foreach(['issuer_id'=>['issuers_registry','id'],'token_id'=>['company_api_tokens','token_id']] as $col=>[$table,$key]){$fk=IssuerStorage::query($db,'SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$t,$col])->result_array();if(count($fk)!==1||$fk[0]['REFERENCED_TABLE_NAME']!==$p.$table||$fk[0]['REFERENCED_COLUMN_NAME']!==$key)throw new RuntimeException('issuer_financial_api_schema_invalid');}
    }
}
