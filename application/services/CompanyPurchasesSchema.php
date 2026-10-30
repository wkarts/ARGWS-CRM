<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Additive purchasing/AP storage. Legacy purchase/vendor rows are never adopted. */
final class CompanyPurchasesSchema
{
    public static function definitions(string $p): array
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $p)) { throw new InvalidArgumentException('Invalid table prefix'); }
        $id = '`id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $owner = '`issuer_id` int unsigned NOT NULL';
        $text = static fn(string $n, int $l): string => '`'.$n.'` varchar('.$l.') CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL';
        $ascii = static fn(string $n, int $l, bool $null = false): string => '`'.$n.'` varchar('.$l.') CHARACTER SET ascii COLLATE ascii_bin '.($null ? 'NULL' : 'NOT NULL');
        $json = static fn(string $n): string => '`'.$n.'` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL';
        $fk = static fn(string $column, string $table): string => 'FOREIGN KEY (`issuer_id`,`'.$column.'`) REFERENCES `'.$p.$table.'` (`issuer_id`,`id`)';
        $issuerFk = 'FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)';
        $ownIndex = 'UNIQUE KEY `purchase_owner` (`issuer_id`,`id`)';
        $time = ['`created_by` int NOT NULL', '`created_at` datetime NOT NULL'];
        return [
            'company_purchase_settings' => [$owner.' PRIMARY KEY', $ascii('prefix',12), $ascii('base_currency',3), $json('rates_json'), '`revision` int unsigned NOT NULL', '`updated_by` int NOT NULL', '`updated_at` datetime NOT NULL', $issuerFk],
            'company_purchase_suppliers' => [$id,$owner,$text('name',191),$ascii('tax_id',32),$text('email',100),$text('phone',64),$text('address',1000),'`active` tinyint NOT NULL','`revision` int unsigned NOT NULL','`updated_at` datetime NOT NULL',...$time,$ownIndex,'KEY `purchase_supplier_search` (`issuer_id`,`active`,`id`)',$issuerFk],
            'company_purchase_requests' => [$id,$owner,'`number` int unsigned NOT NULL',$ascii('reference',48),$text('title',191),'`needed_date` date NOT NULL',$ascii('currency',3),$json('lines_json'),$ascii('state',16),'`revision` int unsigned NOT NULL','`updated_at` datetime NOT NULL',...$time,$ownIndex,'UNIQUE KEY `purchase_request_number` (`issuer_id`,`number`)',$issuerFk],
            'company_purchase_quotes' => [$id,$owner,'`request_id` int unsigned NOT NULL','`supplier_id` int unsigned NOT NULL','`number` int unsigned NOT NULL',$ascii('reference',48),'`valid_until` date NOT NULL',$ascii('currency',3),'`tax_rate` int unsigned NOT NULL','`base_cents` bigint unsigned NOT NULL','`tax_cents` bigint unsigned NOT NULL','`total_cents` bigint unsigned NOT NULL',$json('lines_json'),$text('note',2000),$ascii('origin',16),'`invite_id` int unsigned NULL',$ascii('record_hash',64),...$time,$ownIndex,'UNIQUE KEY `purchase_quote_number` (`issuer_id`,`number`)','KEY `purchase_quote_request` (`issuer_id`,`request_id`)','KEY `purchase_quote_supplier` (`issuer_id`,`supplier_id`)',$fk('request_id','company_purchase_requests'),$fk('supplier_id','company_purchase_suppliers')],
            'company_purchase_orders' => [$id,$owner,'`request_id` int unsigned NOT NULL','`quote_id` int unsigned NOT NULL','`supplier_id` int unsigned NOT NULL','`number` int unsigned NOT NULL',$ascii('reference',48),'`order_date` date NOT NULL','`expected_date` date NOT NULL',$ascii('currency',3),$ascii('base_currency',3),'`fx_millionths` bigint unsigned NOT NULL','`tax_rate` int unsigned NOT NULL','`base_cents` bigint unsigned NOT NULL','`tax_cents` bigint unsigned NOT NULL','`total_cents` bigint unsigned NOT NULL',$json('snapshot_json'),$ascii('snapshot_hash',64),$ascii('state',16),'`revision` int unsigned NOT NULL','`updated_at` datetime NOT NULL',...$time,$ownIndex,'UNIQUE KEY `purchase_order_number` (`issuer_id`,`number`)','UNIQUE KEY `purchase_order_request` (`issuer_id`,`request_id`)','UNIQUE KEY `purchase_order_quote` (`issuer_id`,`quote_id`)','KEY `purchase_order_supplier` (`issuer_id`,`supplier_id`)',$fk('request_id','company_purchase_requests'),$fk('quote_id','company_purchase_quotes'),$fk('supplier_id','company_purchase_suppliers')],
            'company_purchase_receipts' => [$id,$owner,'`order_id` int unsigned NOT NULL','`number` int unsigned NOT NULL',$ascii('reference',48),'`received_date` date NOT NULL',$json('lines_json'),'`base_cents` bigint unsigned NOT NULL',$text('note',2000),$ascii('record_hash',64),...$time,$ownIndex,'UNIQUE KEY `purchase_receipt_number` (`issuer_id`,`number`)','KEY `purchase_receipt_order` (`issuer_id`,`order_id`)',$fk('order_id','company_purchase_orders')],
            'company_purchase_documents' => [$id,$owner,'`order_id` int unsigned NOT NULL','`supplier_id` int unsigned NOT NULL','`number` int unsigned NOT NULL',$ascii('reference',48),$text('vendor_number',100),'`document_date` date NOT NULL','`due_date` date NOT NULL',$ascii('currency',3),'`base_cents` bigint unsigned NOT NULL','`tax_rate` int unsigned NOT NULL','`tax_cents` bigint unsigned NOT NULL','`total_cents` bigint unsigned NOT NULL','`category_id` int unsigned NOT NULL','`file_id` int unsigned NULL',$json('snapshot_json'),$ascii('record_hash',64),...$time,$ownIndex,'UNIQUE KEY `purchase_document_number` (`issuer_id`,`number`)','UNIQUE KEY `purchase_document_vendor` (`issuer_id`,`supplier_id`,`vendor_number`)','KEY `purchase_document_order` (`issuer_id`,`order_id`)',$fk('order_id','company_purchase_orders'),$fk('supplier_id','company_purchase_suppliers')],
            'company_purchase_allocations' => [$id,$owner,'`document_id` int unsigned NOT NULL','`receipt_id` int unsigned NOT NULL','`base_cents` bigint unsigned NOT NULL',$ownIndex,'UNIQUE KEY `purchase_receipt_billed` (`issuer_id`,`receipt_id`)','KEY `purchase_allocation_document` (`issuer_id`,`document_id`)',$fk('document_id','company_purchase_documents'),$fk('receipt_id','company_purchase_receipts')],
            'company_purchase_payables' => [$id,$owner,'`supplier_id` int unsigned NOT NULL','`order_id` int unsigned NOT NULL','`document_id` int unsigned NOT NULL','`expense_id` int unsigned NOT NULL',$ascii('currency',3),'`amount_cents` bigint unsigned NOT NULL','`due_date` date NOT NULL',$ascii('expense_hash',64),$ascii('record_hash',64),...$time,$ownIndex,'UNIQUE KEY `purchase_payable_document` (`issuer_id`,`document_id`)','UNIQUE KEY `purchase_payable_expense` (`expense_id`)','KEY `purchase_payable_due` (`issuer_id`,`due_date`,`id`)','KEY `purchase_payable_order` (`issuer_id`,`order_id`)','KEY `purchase_payable_supplier` (`issuer_id`,`supplier_id`)',$fk('document_id','company_purchase_documents'),$fk('order_id','company_purchase_orders'),$fk('supplier_id','company_purchase_suppliers'),'FOREIGN KEY (`expense_id`) REFERENCES `'.$p.'company_expenses` (`id`)'],
            'company_purchase_events' => [$id,$owner,'`payable_id` int unsigned NOT NULL',$ascii('kind',32),'`amount_cents` bigint unsigned NOT NULL',$ascii('currency',3),'`event_date` date NOT NULL',$text('external_reference',191),$text('note',2000),'`reverses_id` int unsigned NULL','`file_id` int unsigned NULL',$ascii('record_hash',64),...$time,$ownIndex,'UNIQUE KEY `purchase_event_reversal` (`issuer_id`,`reverses_id`)','KEY `purchase_event_payable` (`issuer_id`,`payable_id`,`id`)',$fk('payable_id','company_purchase_payables'),$fk('reverses_id','company_purchase_events')],
            'company_purchase_files' => [$id,$owner,'`request_id` int unsigned NOT NULL','`supplier_id` int unsigned NULL',$ascii('file_key',64),$text('filename',191),$ascii('mime',40),$ascii('content_hash',64),'`byte_size` int unsigned NOT NULL','`content` mediumblob NOT NULL','`portal_visible` tinyint NOT NULL','`active` tinyint NOT NULL','`revision` int unsigned NOT NULL',...$time,$ownIndex,'UNIQUE KEY `purchase_file_key` (`file_key`)','KEY `purchase_file_request` (`issuer_id`,`request_id`,`active`)','KEY `purchase_file_supplier` (`issuer_id`,`supplier_id`)',$fk('request_id','company_purchase_requests'),$fk('supplier_id','company_purchase_suppliers')],
            'company_purchase_invites' => [$id,$owner,'`request_id` int unsigned NOT NULL','`supplier_id` int unsigned NOT NULL',$ascii('token_id',32),$ascii('secret_hash',64),'`expires_at` datetime NOT NULL','`revoked` tinyint NOT NULL','`revision` int unsigned NOT NULL',...$time,$ownIndex,'UNIQUE KEY `purchase_invite_token` (`token_id`)','KEY `purchase_invite_request` (`issuer_id`,`request_id`)','KEY `purchase_invite_supplier` (`issuer_id`,`supplier_id`)',$fk('request_id','company_purchase_requests'),$fk('supplier_id','company_purchase_suppliers')],
            'company_purchase_commands' => [$id,$owner,'`actor_id` int NOT NULL',$ascii('request_key',64),$ascii('operation',32),$ascii('input_hash',64),$ascii('object_kind',16),'`object_id` int unsigned NOT NULL',$json('result_json'),$ascii('result_hash',64),'`created_at` datetime NOT NULL','UNIQUE KEY `purchase_command_key` (`request_key`)','KEY `purchase_command_owner` (`issuer_id`,`actor_id`,`id`)',$issuerFk],
        ];
    }

    public static function ensure($db, string $p): void
    {
        IssuerTransaction::assertIndependent($db);
        foreach (self::definitions($p) as $table => $parts) {
            IssuerStorage::query($db, 'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',', $parts).') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
        }
        if (property_exists($db, 'data_cache')) { unset($db->data_cache['table_names']); }
        self::assertValid($db, $p);
    }

    public static function assertValid($db, string $p): void
    {
        $schema = IssuerStorage::query($db, 'SELECT DATABASE() current_schema')->row_array()['current_schema'];
        foreach (self::definitions($p) as $table => $parts) {
            $name = $p.$table;
            $columns = IssuerStorage::query($db, 'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION', [$name])->result_array();
            $expected = [];
            foreach ($parts as $definition) {
                if (!preg_match('/^`([^`]+)` (varchar\([0-9]+\)|bigint unsigned|int unsigned|int|tinyint|mediumblob|text|datetime|date)(.*)$/D', $definition, $m)) { continue; }
                [, $column, $type, $tail] = $m;
                $r = $columns[count($expected)] ?? [];
                $expected[] = $column;
                $actual = preg_replace('/^(tinyint|int|bigint)\([0-9]+\)/', '$1', strtolower($r['COLUMN_TYPE'] ?? ''));
                $collation = preg_match('/COLLATE ([a-z0-9_]+)/', $tail, $c) ? $c[1] : null;
                if (($r['COLUMN_NAME'] ?? '') !== $column || $actual !== $type || ($r['IS_NULLABLE'] ?? '') !== (str_contains($tail, 'NOT NULL') ? 'NO' : 'YES') || !IssuerStorage::sqlNullDefault($r) || ($r['EXTRA'] ?? '') !== (str_contains($tail, 'AUTO_INCREMENT') ? 'auto_increment' : '') || ($r['COLLATION_NAME'] ?? null) !== $collation) {
                    throw new RuntimeException('issuer_purchase_schema_invalid');
                }
            }
            if (count($columns) !== count($expected)) { throw new RuntimeException('issuer_purchase_schema_invalid'); }
            $engine = IssuerStorage::query($db, 'SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$name])->row_array();
            if (strtoupper($engine['ENGINE'] ?? '') !== 'INNODB' || ($engine['TABLE_COLLATION'] ?? '') !== 'utf8mb4_bin') { throw new RuntimeException('issuer_purchase_schema_invalid'); }
            $indexes = [];
            foreach (IssuerStorage::query($db, 'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX', [$name])->result_array() as $r) {
                if ($r['SUB_PART'] !== null) { throw new RuntimeException('issuer_purchase_schema_invalid'); }
                $indexes[$r['INDEX_NAME']]['columns'][] = $r['COLUMN_NAME'];
                $indexes[$r['INDEX_NAME']]['non_unique'] = (int)$r['NON_UNIQUE'];
            }
            $required = [];
            foreach ($parts as $definition) {
                if (preg_match('/^(UNIQUE )?KEY `([^`]+)` \(([^)]+)\)$/D', $definition, $m)) { $key = $m[2]; $cols = explode(',', str_replace('`', '', $m[3])); $unique = $m[1] !== '' ? 0 : 1; }
                elseif (preg_match('/^`([^`]+)` .*PRIMARY KEY/', $definition, $m)) { $key = 'PRIMARY'; $cols = [$m[1]]; $unique = 0; }
                else { continue; }
                $required[$key] = ['columns'=>$cols,'non_unique'=>$unique];
            }
            ksort($required); ksort($indexes);
            if ($required !== $indexes) { throw new RuntimeException('issuer_purchase_schema_invalid'); }
            $foreign = [];
            foreach (IssuerStorage::query($db, 'SELECT k.CONSTRAINT_NAME,k.COLUMN_NAME,k.REFERENCED_TABLE_SCHEMA,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.UPDATE_RULE,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? ORDER BY k.CONSTRAINT_NAME,k.ORDINAL_POSITION', [$name])->result_array() as $r) {
                if ($r['REFERENCED_TABLE_SCHEMA'] !== $schema || !in_array($r['UPDATE_RULE'], ['RESTRICT','NO ACTION'], true) || !in_array($r['DELETE_RULE'], ['RESTRICT','NO ACTION'], true)) { throw new RuntimeException('issuer_purchase_schema_invalid'); }
                $foreign[$r['CONSTRAINT_NAME']][] = $r['COLUMN_NAME'].':'.$r['REFERENCED_TABLE_NAME'].':'.$r['REFERENCED_COLUMN_NAME'];
            }
            $wanted = [];
            foreach ($parts as $definition) {
                if (!preg_match('/^FOREIGN KEY \(([^)]+)\) REFERENCES `([^`]+)` \(([^)]+)\)$/D', $definition, $m)) { continue; }
                $cols = explode(',', str_replace('`', '', $m[1])); $refs = explode(',', str_replace('`', '', $m[3])); $want = [];
                foreach ($cols as $i => $col) { $want[] = $col.':'.$m[2].':'.$refs[$i]; }
                $wanted[] = $want;
            }
            $foreign = array_values($foreign); sort($foreign); sort($wanted);
            if ($foreign !== $wanted) { throw new RuntimeException('issuer_purchase_schema_invalid'); }
        }
    }
}
