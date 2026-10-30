<?php

declare(strict_types=1);
require_once __DIR__.'/IssuerStorage.php';

/** Additive schema only. Called by the next native core migration, never by a request. */
final class IssuerCertificateSchema
{
    public static function definitions(string $prefix): array
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)) { throw new InvalidArgumentException('Invalid table prefix'); }
        return [
            'issuer_certificate_credentials'=>[
                '`issuer_id` int unsigned NOT NULL PRIMARY KEY','`revision` int unsigned NOT NULL',
                '`status` varchar(16) NOT NULL','`pfx_cipher` longtext NOT NULL','`password_cipher` text NOT NULL',
                '`metadata_json` text NOT NULL','`fingerprint_sha256` varchar(64) NOT NULL',
                '`configured_by` int NOT NULL','`created_at` datetime NOT NULL','`updated_at` datetime NOT NULL',
                'FOREIGN KEY (`issuer_id`) REFERENCES `'.$prefix.'issuers_registry` (`id`)',
            ],
            'issuer_certificate_audit'=>[
                '`id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY','`issuer_id` int unsigned NOT NULL',
                '`actor_id` int NOT NULL','`event` varchar(32) NOT NULL','`revision` int unsigned NOT NULL',
                '`fingerprint_sha256` varchar(64) NOT NULL','`result` varchar(16) NOT NULL','`created_at` datetime NOT NULL',
                'KEY `certificate_audit_owner` (`issuer_id`,`id`)',
                'FOREIGN KEY (`issuer_id`) REFERENCES `'.$prefix.'issuers_registry` (`id`)',
            ],
        ];
    }
    public static function ensure($db,string $prefix): void
    {
        foreach (self::definitions($prefix) as $table=>$parts) {
            IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$prefix.$table.'` ('.implode(',',$parts).') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
        // Raw CREATE queries do not invalidate CI's request-local table list.
        if (property_exists($db,'data_cache')) { unset($db->data_cache['table_names']); }
        self::assertValid($db,$prefix);
    }
    public static function assertValid($db,string $prefix): void
    {
        foreach (self::definitions($prefix) as $table=>$parts) {
            $name=$prefix.$table; $columns=[];
            foreach (IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->result_array() as $row) { $columns[$row['COLUMN_NAME']]=$row; }
            $expected=[];
            foreach ($parts as $part) {
                if (!preg_match('/^`([^`]+)` (int unsigned|bigint unsigned|int|varchar\([0-9]+\)|longtext|text|datetime) NOT NULL/', $part, $match)) { continue; }
                $expected[]=$match[1]; $actual=$columns[$match[1]]??[];
                $type=preg_replace('/^(int|bigint)\([0-9]+\)/','$1',strtolower($actual['COLUMN_TYPE']??''));
                // SQLite integration adapter reports INTEGER for the emulated auto-increment key.
                $connection=$db->pdo??$db->conn_id??null;
                $sqlite=$connection instanceof PDO && $connection->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
                if ($sqlite && $match[1]==='id' && $type==='integer') { $type='bigint unsigned'; }
                if ($type!==$match[2] || ($actual['IS_NULLABLE']??null)!=='NO') { throw new RuntimeException('issuer_certificate_schema_invalid'); }
            }
            if (array_diff(array_keys($columns),$expected)) { throw new RuntimeException('issuer_certificate_schema_invalid'); }
            $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->result_array()[0]??[];
            if (strtoupper($engine['ENGINE']??'')!=='INNODB') { throw new RuntimeException('issuer_certificate_schema_invalid'); }
            $indexes=[];
            foreach (IssuerStorage::query($db,'SELECT INDEX_NAME,NON_UNIQUE,COLUMN_NAME,SEQ_IN_INDEX,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$name])->result_array() as $row) {
                if ($row['SUB_PART']!==null) { throw new RuntimeException('issuer_certificate_schema_invalid'); }
                $indexes[$row['INDEX_NAME']]['columns'][]=$row['COLUMN_NAME']; $indexes[$row['INDEX_NAME']]['unique']=(int)$row['NON_UNIQUE']===0;
            }
            $pk=$table==='issuer_certificate_credentials'?'issuer_id':'id';
            if (($indexes['PRIMARY']??null)!==['columns'=>[$pk],'unique'=>true]) { throw new RuntimeException('issuer_certificate_schema_invalid'); }
            $foreign=IssuerStorage::query($db,'SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME,ORDINAL_POSITION FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',[$name])->result_array();
            if (count($foreign)!==1 || $foreign[0]['COLUMN_NAME']!=='issuer_id' || $foreign[0]['REFERENCED_TABLE_NAME']!==$prefix.'issuers_registry' || $foreign[0]['REFERENCED_COLUMN_NAME']!=='id') { throw new RuntimeException('issuer_certificate_schema_invalid'); }
        }
    }
}
