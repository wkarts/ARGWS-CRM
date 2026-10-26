<?php

declare(strict_types=1);

/** Additive native core storage. Historical grants/global credentials are never copied. */
final class IssuerAccessSchema
{
    public static function statements(string $prefix): array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)) { throw new InvalidArgumentException('Invalid table prefix'); }
        $p=$prefix.'issuers_';
        return [
            "CREATE TABLE IF NOT EXISTS `{$p}resource_permissions` (
                `issuer_id` int unsigned NOT NULL, `staff_id` int NOT NULL,
                `feature` varchar(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                `capability` varchar(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                PRIMARY KEY (`issuer_id`,`staff_id`,`feature`,`capability`),
                FOREIGN KEY (`issuer_id`,`staff_id`) REFERENCES `{$p}staff` (`issuer_id`,`staff_id`)
            ) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS `{$p}installation_identity` (
                `id` int NOT NULL PRIMARY KEY, `master_staff_id` int DEFAULT NULL,
                `primary_issuer_id` int unsigned DEFAULT NULL,
                `source` varchar(32) NOT NULL, `resolved_at` datetime DEFAULT NULL,
                FOREIGN KEY (`primary_issuer_id`) REFERENCES `{$p}registry` (`id`)
            ) ENGINE=InnoDB",
        ];
    }
    private static function rows($db,string $sql,array $params=[]): array
    {
        $result=$db->query($sql,$params);
        if($result===false) { throw new RuntimeException('issuer_access_schema_failed'); }
        return $result->result_array();
    }
    public static function ensure($db,string $prefix): void
    {
        foreach(self::statements($prefix) as $sql) {
            if($db->query($sql)===false) { throw new RuntimeException('issuer_access_schema_failed'); }
        }
        $audit=$prefix.'issuers_audit';
        if(property_exists($db,'data_cache')) { unset($db->data_cache['field_names'][$audit]); }
        if(!$db->field_exists('metadata_json',$audit)&&$db->query("ALTER TABLE `{$audit}` ADD `metadata_json` longtext DEFAULT NULL")===false) { throw new RuntimeException('issuer_access_schema_failed'); }
        if(property_exists($db,'data_cache')) { unset($db->data_cache['field_names'][$audit]); }
        $metadata=self::rows($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$audit]);
        $metadata=array_values(array_filter($metadata,static fn($column)=>$column['COLUMN_NAME']==='metadata_json'));
        if(count($metadata)!==1||strtolower($metadata[0]['COLUMN_TYPE'])!=='longtext'||$metadata[0]['IS_NULLABLE']!=='YES') { throw new RuntimeException('issuer_access_audit_schema_invalid'); }
        self::assertValid($db,$prefix);
    }
    public static function assertValid($db,string $prefix): void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)) { throw new InvalidArgumentException('Invalid table prefix'); }
        $definitions=[
            'resource_permissions'=>['issuer_id'=>['int unsigned','NO',null],'staff_id'=>['int','NO',null],'feature'=>['varchar(100)','NO','ascii_bin'],'capability'=>['varchar(100)','NO','ascii_bin']],
            'installation_identity'=>['id'=>['int','NO',null],'master_staff_id'=>['int','YES',null],'primary_issuer_id'=>['int unsigned','YES',null],'source'=>['varchar(32)','NO',null],'resolved_at'=>['datetime','YES',null]],
        ];
        $primary=['resource_permissions'=>['issuer_id','staff_id','feature','capability'],'installation_identity'=>['id']];
        foreach($definitions as $suffix=>$expected) {
            $table=$prefix.'issuers_'.$suffix;
            $columns=self::rows($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);
            $found=[];
            foreach($columns as $column) { $found[$column['COLUMN_NAME']]=$column; }
            foreach($expected as $name=>[$type,$nullable,$collation]) {
                $column=$found[$name]??[];
                $actual=preg_replace('/^int\([0-9]+\)/','int',strtolower((string)($column['COLUMN_TYPE']??'')));
                if(!$column) { throw new RuntimeException('issuer_access_column_'.$suffix.'_'.$name.'_missing'); }
                if($actual!==$type) { throw new RuntimeException('issuer_access_column_'.$suffix.'_'.$name.'_type_invalid'); }
                if(($column['IS_NULLABLE']??null)!==$nullable) { throw new RuntimeException('issuer_access_column_'.$suffix.'_'.$name.'_nullable_invalid'); }
                if($collation!==null&&($column['COLLATION_NAME']??null)!==$collation) { throw new RuntimeException('issuer_access_column_'.$suffix.'_'.$name.'_collation_invalid'); }
            }
            $indexes=self::rows($db,'SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=\'PRIMARY\' ORDER BY SEQ_IN_INDEX',[$table]);
            if(array_column($indexes,'COLUMN_NAME')!==$primary[$suffix]) { throw new RuntimeException('issuer_access_primary_key_invalid'); }
            $engines=self::rows($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);
            if(count($engines)!==1||strtoupper((string)$engines[0]['ENGINE'])!=='INNODB') { throw new RuntimeException('issuer_access_engine_invalid'); }
        }
        foreach(['resource_permissions'=>['issuer_id'=>'issuer_id','staff_id'=>'staff_id'],'installation_identity'=>['primary_issuer_id'=>'id']] as $suffix=>$keys) {
            $table=$prefix.'issuers_'.$suffix;
            $target=$prefix.'issuers_'.($suffix==='resource_permissions'?'staff':'registry');
            $rows=self::rows($db,'SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME,ORDINAL_POSITION FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',[$table]);
            $groups=[];
            foreach($rows as $row) { if($row['REFERENCED_TABLE_NAME']===$target) { $groups[$row['CONSTRAINT_NAME']][$row['COLUMN_NAME']]=$row['REFERENCED_COLUMN_NAME']; } }
            if(!in_array($keys,$groups,true)) { throw new RuntimeException('issuer_access_foreign_key_invalid'); }
        }
    }
}
