<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';

final class CompanyOnboardingSchema
{
    public static function ensure($db,string $prefix): void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)){throw new InvalidArgumentException('Invalid table prefix');}
        $table=$prefix.'company_wizard_drafts';
        IssuerStorage::query($db,"CREATE TABLE IF NOT EXISTS `{$table}` (
            `draft_key` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
            `actor_id` int NOT NULL, `revision` int NOT NULL, `step` int NOT NULL,
            `payload_json` longtext NOT NULL, `issuer_id` int unsigned DEFAULT NULL,
            `created_at` datetime NOT NULL, `updated_at` datetime NOT NULL,
            KEY `actor_pending` (`actor_id`,`issuer_id`),
            FOREIGN KEY (`issuer_id`) REFERENCES `{$prefix}issuers_registry` (`id`)
        ) ENGINE=InnoDB");
        $columns=IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->result_array();
        $actual=[];foreach($columns as $column){$actual[$column['COLUMN_NAME']]=$column;}
        foreach(['draft_key'=>['varchar(64)','NO'],'actor_id'=>['int','NO'],'revision'=>['int','NO'],'step'=>['int','NO'],'payload_json'=>['longtext','NO'],'issuer_id'=>['int unsigned','YES'],'created_at'=>['datetime','NO'],'updated_at'=>['datetime','NO']] as $name=>[$type,$nullable]){
            $column=$actual[$name]??[];
            if(preg_replace('/^int\([0-9]+\)/','int',strtolower($column['COLUMN_TYPE']??''))!==$type||($column['IS_NULLABLE']??null)!==$nullable){throw new RuntimeException('issuer_wizard_schema_invalid');}
        }
        $pk=IssuerStorage::query($db,"SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME='PRIMARY' ORDER BY SEQ_IN_INDEX",[$table])->result_array();
        $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->result_array();
        if(array_column($pk,'COLUMN_NAME')!==['draft_key']||($actual['draft_key']['COLLATION_NAME']??null)!=='ascii_bin'||strtoupper($engine[0]['ENGINE']??'')!=='INNODB'){throw new RuntimeException('issuer_wizard_schema_invalid');}
        $foreign=IssuerStorage::query($db,'SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$table,'issuer_id'])->result_array();
        if(count($foreign)!==1||$foreign[0]['REFERENCED_TABLE_NAME']!==$prefix.'issuers_registry'||$foreign[0]['REFERENCED_COLUMN_NAME']!=='id'){throw new RuntimeException('issuer_wizard_schema_invalid');}
    }
}
