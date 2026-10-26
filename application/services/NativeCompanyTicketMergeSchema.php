<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
/** Explicit immutable merge lineage; original records and attachment bytes are retained. */
final class NativeCompanyTicketMergeSchema
{
    public const LINKS='company_ticket_merges';
    public const REPLIES='company_ticket_merge_replies';
    public const FILES='company_ticket_merge_files';
    public static function definitions(string$p):array{
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        $v=static fn($n,$len)=>'`'.$n.'` varchar('.$len.') CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL';
        return[self::LINKS=>[
            '`child_id` int unsigned NOT NULL PRIMARY KEY','`issuer_id` int unsigned NOT NULL','`primary_id` int unsigned NOT NULL','`actor_id` int NOT NULL',$v('source_hash',64),$v('request_key',64),'`manifest_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL','`created_at` datetime NOT NULL',
            'KEY `merge_child` (`issuer_id`,`child_id`)','KEY `merge_primary` (`issuer_id`,`primary_id`)','KEY `merge_actor` (`actor_id`)',
            'FOREIGN KEY (`issuer_id`,`child_id`) REFERENCES `'.$p.'company_tickets` (`issuer_id`,`id`)','FOREIGN KEY (`issuer_id`,`primary_id`) REFERENCES `'.$p.'company_tickets` (`issuer_id`,`id`)','FOREIGN KEY (`actor_id`) REFERENCES `'.$p.'staff` (`staffid`)',
        ],self::REPLIES=>[
            '`reply_id` int unsigned NOT NULL PRIMARY KEY','`issuer_id` int unsigned NOT NULL','`primary_id` int unsigned NOT NULL','`child_id` int unsigned NOT NULL',$v('source_kind',16),'`source_id` int unsigned NOT NULL',
            '`author_staff_id` int NULL','`author_contact_id` int NULL','`client_id` int NULL',$v('author_name',191),$v('body_format',16),$v('content_hash',64),$v('source_revision',64),'`source_date` datetime NOT NULL',
            'KEY `merge_reply_primary` (`issuer_id`,`primary_id`)','KEY `merge_reply_child` (`child_id`)','KEY `merge_reply_staff` (`author_staff_id`)','KEY `merge_reply_contact` (`author_contact_id`)','KEY `merge_reply_client` (`client_id`)',
            'UNIQUE KEY `merge_reply_source` (`primary_id`,`child_id`,`source_kind`,`source_id`)',
            'FOREIGN KEY (`reply_id`) REFERENCES `'.$p.'company_ticket_replies` (`id`)','FOREIGN KEY (`issuer_id`,`primary_id`) REFERENCES `'.$p.'company_tickets` (`issuer_id`,`id`)','FOREIGN KEY (`child_id`) REFERENCES `'.$p.self::LINKS.'` (`child_id`)',
            'FOREIGN KEY (`author_staff_id`) REFERENCES `'.$p.'staff` (`staffid`)','FOREIGN KEY (`author_contact_id`) REFERENCES `'.$p.'contacts` (`id`)','FOREIGN KEY (`client_id`) REFERENCES `'.$p.'clients` (`userid`)',
        ],self::FILES=>[
            '`file_key` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY','`issuer_id` int unsigned NOT NULL','`primary_id` int unsigned NOT NULL','`reply_id` int unsigned NOT NULL','`child_id` int unsigned NOT NULL','`source_file_key` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL',$v('content_hash',64),
            'KEY `merge_file_primary` (`issuer_id`,`primary_id`)','KEY `merge_file_reply` (`reply_id`)','KEY `merge_file_child` (`child_id`)','KEY `merge_file_source` (`source_file_key`)',
            'UNIQUE KEY `merge_file_once` (`primary_id`,`child_id`,`source_file_key`)',
            'FOREIGN KEY (`file_key`) REFERENCES `'.$p.'company_ticket_files` (`file_key`)','FOREIGN KEY (`issuer_id`,`primary_id`) REFERENCES `'.$p.'company_tickets` (`issuer_id`,`id`)','FOREIGN KEY (`reply_id`) REFERENCES `'.$p.self::REPLIES.'` (`reply_id`)',
            'FOREIGN KEY (`child_id`) REFERENCES `'.$p.self::LINKS.'` (`child_id`)','FOREIGN KEY (`source_file_key`) REFERENCES `'.$p.'company_ticket_files` (`file_key`)',
        ]];
    }
    public static function active($db,string$p):bool{require_once __DIR__.'/NativeCompanyTicketVisibility.php';if(!NativeCompanyTicketVisibility::enabled($db,$p))return false;self::assertValid($db,$p);return true;}
    public static function tables($db,string$p):array{return self::active($db,$p)?array_keys(self::definitions($p)):[];}
    public static function ensure($db,string $p):void
    {
        // Validate existing tables BEFORE creating any missing table; never repair suspicious state.
        $missing=[];
        foreach(self::definitions($p) as $table=>$parts) {
            $found=IssuerStorage::query($db,'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.$table])->result_array();
            if($found)self::assertTable($db,$p,$table,$parts); else $missing[$table]=$parts;
        }
        foreach($missing as $table=>$parts){$ddl=self::boundedForeignKeyNames($table,$parts);IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',',$ddl).') ENGINE=InnoDB');}
        self::assertValid($db,$p);
    }
    /** Use bounded, deterministic symbols because installer prefixes count toward MySQL's 64-byte limit. */
    private sta