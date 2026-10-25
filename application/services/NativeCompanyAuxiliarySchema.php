<?php

declare(strict_types=1);
require_once __DIR__ . '/issuers/IssuerStorage.php';

/** Owned backend storage for the existing native utility screens. No legacy rows are adopted. */
final class NativeCompanyAuxiliarySchema
{
    public static function fields(): array
    {
        $owner = ['issuer_id' => ['int unsigned', false]];
        $revision = ['revision' => ['int unsigned', false]];
        return [
            'company_reminder_mail_deliveries' => ['queue_id'=>['int unsigned',false]] + $owner + [
                'reminder_id'=>['int unsigned',false], 'reminder_revision'=>['int unsigned',false],
                'creator_id'=>['int',false], 'recipient_id'=>['int',false],
                'recipient_hash'=>['char(64)',false], 'transport_hash'=>['char(64)',false],
                'payload_hash'=>['char(64)',false], 'created_at'=>['datetime',false],
            ],
            'company_item_groups' => ['id'=>['int unsigned AUTO_INCREMENT',false]] + $owner + ['name'=>['varchar(191)',false]] + $revision,
            'company_taxes' => ['id'=>['int unsigned AUTO_INCREMENT',false]] + $owner + ['name'=>['varchar(191)',false],'taxrate'=>['decimal(10,4)',false]] + $revision,
            'company_items' => ['id'=>['int unsigned AUTO_INCREMENT',false]] + $owner + [
                'description'=>['varchar(191)',false],'long_description'=>['mediumtext',false],'rate'=>['decimal(15,4)',false],
                'currency_rates_json'=>['text',false],'unit'=>['varchar(40)',false],
                'tax'=>['int unsigned',true],'tax2'=>['int unsigned',true],'group_id'=>['int unsigned',true],
            ] + $revision,
            'company_todos' => ['todoid' => ['int unsigned AUTO_INCREMENT', false]] + $owner + [
                'staffid' => ['int', false], 'description' => ['mediumtext', false],
                'finished' => ['tinyint unsigned', false], 'item_order' => ['int unsigned', false],
                'dateadded' => ['datetime', false], 'datefinished' => ['datetime', true],
            ] + $revision,
            'company_announcements' => ['announcementid' => ['int unsigned AUTO_INCREMENT', false]] + $owner + [
                'name' => ['varchar(191)', false], 'message' => ['mediumtext', false],
                'showtousers' => ['tinyint unsigned', false], 'showtostaff' => ['tinyint unsigned', false],
                'showname' => ['tinyint unsigned', false], 'userid' => ['varchar(100)', false], 'actor_id' => ['int', false], 'dateadded' => ['datetime', false],
            ] + $revision,
            'company_announcement_contact_dismissals' => $owner + ['announcementid'=>['int unsigned',false],'contact_id'=>['int',false],'created_at'=>['datetime',false]],
            'company_announcement_dismissals' => $owner + [
                'announcementid' => ['int unsigned', false], 'actor_id' => ['int', false], 'created_at' => ['datetime', false],
            ],
            'company_reminders' => ['id' => ['int unsigned AUTO_INCREMENT', false]] + $owner + [
                'rel_type' => ['varchar(40)', false], 'rel_id' => ['bigint unsigned', false],
                'source_kind' => ['varchar(32)', false], 'source_id' => ['int unsigned', false],
                'staff' => ['int', false], 'creator' => ['int', false], 'description' => ['mediumtext', false],
                'date' => ['datetime', false], 'isnotified' => ['tinyint unsigned', false],
                'notify_by_email' => ['tinyint unsigned', false],
            ] + $revision,
            'company_notes' => ['id' => ['int unsigned AUTO_INCREMENT', false]] + $owner + [
                'rel_type' => ['varchar(40)', false], 'rel_id' => ['bigint unsigned', false],
                'source_kind' => ['varchar(32)', false], 'source_id' => ['int unsigned', false],
                'description' => ['mediumtext', false], 'addedfrom' => ['int', false],
                'dateadded' => ['datetime', false], 'date_contacted' => ['datetime', true],
            ] + $revision,
        ];
    }

    public static function indexes(string $table): array
    {
        return match ($table) {
            'company_reminder_mail_deliveries' => ['PRIMARY'=>[['queue_id'],0], 'reminder_mail_owner'=>[['issuer_id','queue_id'],0], 'reminder_mail_once'=>[['issuer_id','reminder_id','reminder_revision'],0]],
            'company_item_groups' => ['PRIMARY'=>[['id'],0],'item_group_owner'=>[['issuer_id','id'],0],'item_group_name'=>[['issuer_id','name'],0]],
            'company_taxes' => ['PRIMARY'=>[['id'],0],'tax_owner'=>[['issuer_id','id'],0],'tax_name'=>[['issuer_id','name'],0]],
            'company_items' => ['PRIMARY'=>[['id'],0],'item_owner'=>[['issuer_id','id'],0],'item_description'=>[['issuer_id','description'],1]],
            'company_todos' => ['PRIMARY' => [['todoid'], 0], 'todo_owner' => [['issuer_id', 'todoid'], 0], 'todo_staff' => [['issuer_id', 'staffid', 'finished', 'item_order'], 1]],
            'company_announcements' => ['PRIMARY' => [['announcementid'], 0], 'announcement_owner' => [['issuer_id', 'announcementid'], 0], 'announcement_staff' => [['issuer_id', 'showtostaff', 'announcementid'], 1]],
            'company_announcement_contact_dismissals' => ['PRIMARY'=>[['issuer_id','announcementid','contact_id'],0]],
            'company_announcement_dismissals' => ['PRIMARY' => [['issuer_id', 'announcementid', 'actor_id'], 0]],
            'company_reminders' => ['PRIMARY' => [['id'], 0], 'reminder_owner' => [['issuer_id', 'id'], 0], 'reminder_staff' => [['issuer_id', 'staff', 'isnotified', 'date'], 1], 'reminder_relation' => [['issuer_id', 'rel_type', 'rel_id'], 1]],
            'company_notes' => ['PRIMARY' => [['id'], 0], 'note_owner' => [['issuer_id', 'id'], 0], 'note_relation' => [['issuer_id', 'rel_type', 'rel_id', 'id'], 1]],
            default => throw new InvalidArgumentException('Unknown auxiliary table'),
        };
    }

    public static function definitions(string $prefix): array
    {
        self::prefix($prefix); $result = [];
        foreach (self::fields() as $table => $fields) {
            $parts = [];
            foreach ($fields as $name => [$type, $nullable]) {
                $parts[] = '`' . $name . '` ' . $type . (self::textType($type) ? ' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin' : '') . ($nullable ? ' NULL' : ' NOT NULL');
            }
            foreach (self::indexes($table) as $key => [$columns, $nonUnique]) {
                $parts[] = ($key === 'PRIMARY' ? 'PRIMARY KEY' : ($nonUnique ? 'KEY' : 'UNIQUE KEY') . ' `' . $key . '`') . ' (`' . implode('`,`', $columns) . '`)';
            }
            $parts[] = 'FOREIGN KEY (`issuer_id`) REFERENCES `' . $prefix . 'issuers_registry` (`id`)';
            if ($table === 'company_items') {
                $parts[] = 'FOREIGN KEY (`issuer_id`,`tax`) REFERENCES `' . $prefix . 'company_taxes` (`issuer_id`,`id`)';
                $parts[] = 'FOREIGN KEY (`issuer_id`,`tax2`) REFERENCES `' . $prefix . 'company_taxes` (`issuer_id`,`id`)';
                $parts[] = 'FOREIGN KEY (`issuer_id`,`group_id`) REFERENCES `' . $prefix . 'company_item_groups` (`issuer_id`,`id`)';
            }
            if (in_array($table,['company_announcement_dismissals','company_announcement_contact_dismissals'],true)) {
                $parts[] = 'FOREIGN KEY (`issuer_id`,`announcementid`) REFERENCES `' . $prefix . 'company_announcements` (`issuer_id`,`announcementid`)';
            }
            $result[$table] = $parts;
        }
        return $result;
    }

    public static function ensure($db, string $prefix): void
    {
        foreach (self::definitions($prefix) as $table => $parts) {
            IssuerStorage::query($db, 'CREATE TABLE IF NOT EXISTS `' . $prefix . $table . '` (' . implode(',', $parts) . ') ENGINE=InnoDB');
        }
        self::assertValid($db, $prefix);
    }

    public static function assertValid($db, string $prefix): void
    {
        self::prefix($prefix);
        $connection = $db->pdo ?? $db->conn_id ?? null;
        if ($connection instanceof PDO && $connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') { return; }
        foreach (self::fields() as $table => $fields) {
            $name = $prefix . $table; $columns = [];
            foreach (IssuerStorage::query($db, 'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$name])->result_array() as $row) { $columns[$row['COLUMN_NAME']] = $row; }
            foreach ($fields as $field => [$type, $nullable]) {
                $row = $columns[$field] ?? []; $auto = str_contains($type, ' AUTO_INCREMENT');
                $type = str_replace(' AUTO_INCREMENT', '', $type);
                $actual = preg_replace('/^(tinyint|int|bigint)\([0-9]+\)/', '$1', strtolower($row['COLUMN_TYPE'] ?? ''));
                if ($actual !== $type || ($row['IS_NULLABLE'] ?? '') !== ($nullable ? 'YES' : 'NO')
                    || !IssuerStorage::sqlNullDefault($row)
                    || strtolower((string)($row['EXTRA'] ?? '')) !== ($auto ? 'auto_increment' : '')
                    || (self::textType($type) && ($row['COLLATION_NAME'] ?? '') !== 'utf8mb4_bin')) { throw new RuntimeException('issuer_native_auxiliary_schema_invalid'); }
            }
            $engine = IssuerStorage::query($db, 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$name])->row_array();
            if (strtoupper($engine['ENGINE'] ?? '') !== 'INNODB') { throw new RuntimeException('issuer_native_auxiliary_schema_invalid'); }
            $indexes = [];
            foreach (IssuerStorage::query($db, 'SHOW INDEX FROM `' . $name . '`')->result_array() as $row) {
                if($row['Sub_part']!==null)throw new RuntimeException('issuer_native_auxiliary_schema_invalid');
                $indexes[$row['Key_name']]['columns'][(int)$row['Seq_in_index']] = $row['Column_name'];
                $indexes[$row['Key_name']]['non_unique'] = (int)$row['Non_unique'];
            }
            foreach (self::indexes($table) as $key => [$expected, $nonUnique]) {
                $actual = $indexes[$key]['columns'] ?? []; ksort($actual);
                if (array_values($actual) !== $expected || ($indexes[$key]['non_unique'] ?? null) !== $nonUnique) { throw new RuntimeException('issuer_native_auxiliary_schema_invalid'); }
            }
            $foreign=[];
            foreach (IssuerStorage::query($db,'SELECT k.CONSTRAINT_NAME,k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,k.ORDINAL_POSITION,r.UPDATE_RULE,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[$name])->result_array() as $row) {
                if(!in_array($row['UPDATE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($row['DELETE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('issuer_native_auxiliary_schema_invalid');
                $foreign[$row['CONSTRAINT_NAME']][(int)$row['ORDINAL_POSITION']]=[$row['COLUMN_NAME'],$row['REFERENCED_TABLE_NAME'],$row['REFERENCED_COLUMN_NAME']];
            }
            $expectedForeign=[[['issuer_id',$prefix.'issuers_registry','id']]];
            if(in_array($table,['company_announcement_dismissals','company_announcement_contact_dismissals'],true))$expectedForeign[]=[['issuer_id',$prefix.'company_announcements','issuer_id'],['announcementid',$prefix.'company_announcements','announcementid']];
            if($table==='company_items')foreach(['tax'=>'company_taxes','tax2'=>'company_taxes','group_id'=>'company_item_groups']as$field=>$target)$expectedForeign[]=[['issuer_id',$prefix.$target,'issuer_id'],[$field,$prefix.$target,'id']];
            $actualForeign=[];foreach($foreign as$parts){ksort($parts);$actualForeign[]=array_values($parts);}
            if(count($actualForeign)!==count($expectedForeign))throw new RuntimeException('issuer_native_auxiliary_schema_invalid');
            foreach($expectedForeign as$expected)if(!in_array($expected,$actualForeign,true))throw new RuntimeException('issuer_native_auxiliary_schema_invalid');
        }
    }

    private static function prefix(string $prefix): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) { throw new InvalidArgumentException('Invalid table prefix'); }
    }
    private static function textType(string $type): bool { return str_starts_with($type, 'varchar') || str_starts_with($type, 'char(') || str_contains($type, 'text'); }
}
