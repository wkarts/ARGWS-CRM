<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';

/** Issuer-owned, idempotent client statement mail receipts; queue cleanup never changes the receipt. */
final class CompanyClientStatementDeliverySchema
{
    private const FIELDS=[
        'request_key'=>'varchar(64)',
        'issuer_id'=>'int unsigned',
        'actor_id'=>'int',
        'client_id'=>'int unsigned',
        'contact_id'=>'int',
        'revision'=>'varchar(64)',
        'recipient_hash'=>'varchar(64)',
        'payload_hash'=>'varchar(64)',
        'queue_id'=>'int',
        'created_at'=>'datetime',
    ];
    public static function definitions(string $p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        $parts=[];
        foreach(self::FIELDS as $field=>$type){
            $collation=in_array($field,['request_key','revision','recipient_hash','payload_hash'],true)?' CHARACTER SET ascii COLLATE ascii_bin':'';
            $parts[]=$field.' '.$type.$collation.' NOT NULL'.($field==='request_key'?' PRIMARY KEY':'');
        }
        $parts[]='UNIQUE KEY client_statement_queue (queue_id)';
        $parts[]='KEY client_statement_parent (issuer_id,client_id)';
        $parts[]='FOREIGN KEY (issuer_id) REFERENCES '.$p.'issuers_registry (id)';
        return ['company_client_statement_deliveries'=>$parts];
    }
    public static function ensure($db,string $p):void
    {
        foreach(self::definitions($p) as $table=>$parts)IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS '.$p.$table.' ('.implode(',',$parts).') ENGINE=InnoDB');
        self::assertValid($db,$p);
    }
    public static function assertValid($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        $connection=$db->pdo??$db->conn_id??null;
        if($connection instanceof PDO&&$connection->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')return;
        $table=$p.'company_client_statement_deliveries';
        $columns=[];
        foreach(IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->result_array() as $row)$columns[$row['COLUMN_NAME']]=$row;
        foreach(self::FIELDS as $field=>$type){
            $row=$columns[$field]??[];$actual=preg_replace('/^int\\([0-9]+\\)/','int',strtolower($row['COLUMN_TYPE']??''));
            $needsAscii=in_array($field,['request_key','revision','recipient_hash','payload_hash'],true);
            if($actual!==$type||($row['IS_NULLABLE']??null)!=='NO'||!IssuerStorage::sqlNullDefault($row)||($needsAscii&&($row['COLLATION_NAME']??null)!=='ascii_bin'))throw new RuntimeException('issuer_client_statement_schema_invalid');
        }
        $indexes=[];
        foreach(IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$table])->result_array() as $row){
            if($row['SUB_PART']!==null)throw new RuntimeException('issuer_client_statement_schema_invalid');
            $indexes[$row['INDEX_NAME']]['columns'][]=$row['COLUMN_NAME'];$indexes[$row['INDEX_NAME']]['non_unique']=(int)$row['NON_UNIQUE'];
        }
        foreach(['PRIMARY'=>[['request_key'],0],'client_statement_queue'=>[['queue_id'],0],'client_statement_parent'=>[['issuer_id','client_id'],1]] as $name=>[$fields,$nonUnique])if(($indexes[$name]['columns']??[])!==$fields||($indexes[$name]['non_unique']??null)!==$nonUnique)throw new RuntimeException('issuer_client_statement_schema_invalid');
        $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->row_array();
        if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('issuer_client_statement_schema_invalid');
        $foreign=IssuerStorage::query($db,'SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$table,'issuer_id'])->result_array();
        if(count($foreign)!==1||$foreign[0]['REFERENCED_TABLE_NAME']!==$p.'issuers_registry'||$foreign[0]['REFERENCED_COLUMN_NAME']!=='id')throw new RuntimeException('issuer_client_statement_schema_invalid');
    }
}
