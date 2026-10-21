<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';

final class CompanyPortalSchema
{
    public static function ensure($db,string $prefix): void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('Invalid table prefix');
        $definitions=[
            'company_portal_tokens'=>[
                'token_hash'=>'varchar(64)','issuer_id'=>'int unsigned','contact_id'=>'int','client_id'=>'int',
                'identity_hash'=>'varchar(64)','expires_at'=>'datetime','created_at'=>'datetime',
            ],
            'company_portal_limits'=>['bucket_hash'=>'varchar(64)','window_start'=>'int','attempts'=>'int'],
        ];
        foreach($definitions as $suffix=>$fields){
            $table=$prefix.$suffix;$key=array_key_first($fields);$parts=[];
            foreach($fields as $name=>$type)$parts[]='`'.$name.'` '.$type.(str_ends_with($name,'_hash')?' CHARACTER SET ascii COLLATE ascii_bin':'').' NOT NULL'.($name===$key?' PRIMARY KEY':'');
            if($suffix==='company_portal_tokens')$parts[]='FOREIGN KEY (`issuer_id`) REFERENCES `'.$prefix.'issuers_registry` (`id`)';
            IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$table.'` ('.implode(',',$parts).') ENGINE=InnoDB');
            $actual=[];foreach(IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->result_array() as $column)$actual[$column['COLUMN_NAME']]=$column;
            foreach($fields as $name=>$type){$column=$actual[$name]??[];
                if(preg_replace('/^int\([0-9]+\)/','int',strtolower($column['COLUMN_TYPE']??''))!==$type||($column['IS_NULLABLE']??null)!=='NO'||(str_ends_with($name,'_hash')&&($column['COLLATION_NAME']??null)!=='ascii_bin'))throw new RuntimeException('company_portal_schema_invalid');
            }
            $pk=IssuerStorage::query($db,"SELECT COLUMN_NAME,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME='PRIMARY' ORDER BY SEQ_IN_INDEX",[$table])->result_array();
            $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->result_array();
            if(array_column($pk,'COLUMN_NAME')!==[$key]||($pk[0]['SUB_PART']??null)!==null||strtoupper($engine[0]['ENGINE']??'')!=='INNODB')throw new RuntimeException('company_portal_schema_invalid');
            if($suffix==='company_portal_tokens'){
                $fk=IssuerStorage::query($db,'SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$table,'issuer_id'])->result_array();
                if(count($fk)!==1||$fk[0]['REFERENCED_TABLE_NAME']!==$prefix.'issuers_registry'||$fk[0]['REFERENCED_COLUMN_NAME']!=='id')throw new RuntimeException('company_portal_schema_invalid');
            }
        }
    }
}
