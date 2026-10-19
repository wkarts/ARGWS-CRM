<?php

declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/Transaction.php';
require_once __DIR__.'/SchemaValidator.php';
final class ApiWebhookSchema
{
    private const TABLES=[
        'bb_api_webhook_receivers'=>[
            'columns'=>['id'=>'bigint unsigned','issuer_id'=>'bigint unsigned','profile_id'=>'bigint unsigned','fingerprint'=>'char(64)','reference'=>'varchar(191)','enabled'=>'tinyint unsigned','created_by'=>'bigint unsigned','created_at'=>'datetime'],
            'unique'=>[['profile_id','fingerprint']],
        ],
        'bb_api_notifications'=>[
            'columns'=>['id'=>'bigint unsigned','issuer_id'=>'bigint unsigned','origin_id'=>'bigint unsigned','receiver_id'=>'bigint unsigned','event_key'=>'char(64)','payload_hash'=>'char(64)','fingerprint'=>'char(64)','projection_json'=>'text','created_at'=>'datetime'],
            'unique'=>[['origin_id','event_key']],
        ],
    ];
    public static function ensure($db,string $p,bool $create=true):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('bb_prefix_invalid');
        Transaction::quiet($db,static function()use($db,$p,$create):void{
            if($create){
                foreach(self::TABLES as $suffix=>$table){$parts=[];foreach($table['columns'] as $column=>$type)$parts[]='`'.$column.'` '.$type.' NOT NULL'.($column==='id'?' AUTO_INCREMENT':'');$parts[]='PRIMARY KEY(id)';foreach($table['unique'] as $i=>$columns)$parts[]='UNIQUE KEY `unique_'.$i.'`(`'.implode('`,`',$columns).'`)';
                    $fks=$suffix==='bb_api_webhook_receivers'?['profile_id'=>['bb_api_profiles','id']]:['origin_id'=>['bb_origins','id'],'receiver_id'=>['bb_api_webhook_receivers','id']];
                    foreach($fks as $column=>[$target,$key])$parts[]='FOREIGN KEY(`'.$column.'`) REFERENCES `'.$p.$target.'`(`'.$key.'`)';
                    if(!$db->query('CREATE TABLE IF NOT EXISTS `'.$p.$suffix.'`('.implode(',',$parts).') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin'))throw new \RuntimeException('bb_api_webhook_schema_failed');
                }
            }
            SchemaValidator::assertCompatible($db,$p,self::TABLES);
            foreach(['bb_api_webhook_receivers'=>['profile_id'=>'bb_api_profiles'],'bb_api_notifications'=>['origin_id'=>'bb_origins','receiver_id'=>'bb_api_webhook_receivers']] as $suffix=>$fks)foreach($fks as $column=>$target){$fk=$db->query('SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$p.$suffix,$column])->result_array();if(count($fk)!==1||$fk[0]['REFERENCED_TABLE_NAME']!==$p.$target||$fk[0]['REFERENCED_COLUMN_NAME']!=='id')throw new \RuntimeException('bb_api_webhook_schema_invalid');}
        });
    }
}
