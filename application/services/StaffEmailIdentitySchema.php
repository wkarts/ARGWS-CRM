<?php

declare(strict_types=1);
require_once __DIR__.'/StaffEmailIdentity.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** No legacy identity is rewritten, merged, removed, activated or reassigned. */
final class StaffEmailIdentitySchema
{
    private static function rows($db, string $sql, array $params = []): array
    {
        return IssuerStorage::query($db, $sql, $params)->result_array();
    }

    /** Aggregate-only diagnostics: safe for deployment logs; never return identity data. */
    public static function preflight($db, string $prefix): array
    {
        $table = StaffEmailIdentity::table($prefix);
        $result = ['rows'=>0, 'invalid_rows'=>0, 'duplicate_groups'=>0];
        $after = null;
        do {
            $rows = self::rows($db, 'SELECT staffid,email FROM `'.$table.'`'.($after === null ? '' : ' WHERE staffid>?').' ORDER BY staffid LIMIT 500', $after === null ? [] : [$after]);
            foreach ($rows as $row) {
                ++$result['rows'];
                try { StaffEmailIdentity::normalize($row['email']); }
                catch (DomainException $error) { ++$result['invalid_rows']; }
                $after = (int) $row['staffid'];
            }
        } while (count($rows) === 500);
        $groups = self::rows($db, 'SELECT COUNT(*) AS n FROM (SELECT '.StaffEmailIdentity::expression().' AS identity_key FROM `'.$table.'` GROUP BY identity_key HAVING COUNT(*)>1) AS duplicate_identities');
        $result['duplicate_groups'] = (int) $groups[0]['n'];
        return $result;
    }

    private static function columns($db, string $table): array
    {
        return self::rows($db, 'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLLATION_NAME,EXTRA,GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME IN (?,?)', [$table, 'email', StaffEmailIdentity::COLUMN]);
    }

    private static function indexes($db, string $table): array
    {
        return self::rows($db, 'SELECT COLUMN_NAME,NON_UNIQUE,SUB_PART,SEQ_IN_INDEX,INDEX_TYPE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? ORDER BY SEQ_IN_INDEX', [$table, StaffEmailIdentity::INDEX]);
    }

    private static function validColumn(array $column): bool
    {
        // MariaDB prints LCASE where MySQL prints LOWER. Both describe the same expression.
        $expression = strtolower(preg_replace('/[\s`]+/', '', (string) $column['GENERATION_EXPRESSION']));
        $expression = str_replace('lcase(', 'lower(', $expression);
        return strtolower($column['COLUMN_TYPE']) === 'varchar(100)'
            && $column['COLLATION_NAME'] === StaffEmailIdentity::COLLATION
            && strtoupper($column['EXTRA']) === 'STORED GENERATED'
            && $expression === 'lower(trim(email))';
    }

    private static function validIndex(array $index): bool
    {
        return count($index) === 1 && $index[0]['COLUMN_NAME'] === StaffEmailIdentity::COLUMN
            && (int) $index[0]['NON_UNIQUE'] === 0 && $index[0]['SUB_PART'] === null
            && (int) $index[0]['SEQ_IN_INDEX'] === 1 && strtoupper($index[0]['INDEX_TYPE']) === 'BTREE';
    }

    public static function ensure($db, string $prefix): void
    {
        // Refuse implicit commit of a caller-owned transaction during DDL.
        IssuerTransaction::assertIndependent($db);
        $table = StaffEmailIdentity::table($prefix);
        $engine = self::rows($db, 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table]);
        $columns = array_column(self::columns($db, $table), null, 'COLUMN_NAME');
        $email = $columns['email'] ?? [];
        if (strtoupper($engine[0]['ENGINE'] ?? '') !== 'INNODB' || ($email['COLUMN_TYPE'] ?? '') !== 'varchar(100)'
            || ($email['COLLATION_NAME'] ?? '') !== StaffEmailIdentity::COLLATION || ($email['IS_NULLABLE'] ?? '') !== 'NO') {
            throw new RuntimeException('issuer_staff_email_schema_invalid');
        }
        $column = $columns[StaffEmailIdentity::COLUMN] ?? null;
        $index = self::indexes($db, $table);
        if (($column !== null && !self::validColumn($column)) || ($index && !self::validIndex($index))) {
            throw new RuntimeException('issuer_staff_email_schema_invalid');
        }
        $report = self::preflight($db, $prefix);
        if ($report['invalid_rows'] || $report['duplicate_groups']) {
            throw new RuntimeException('issuer_staff_email_preflight_failed');
        }
        // MySQL DDL commits implicitly. Preflight finishes before any DDL; a failed or
        // interrupted index build leaves data untouched and is safely resumed here.
        if ($column === null) {
            IssuerStorage::query($db, 'ALTER TABLE `'.$table.'` ADD COLUMN `'.StaffEmailIdentity::COLUMN.'` varchar(100) CHARACTER SET utf8mb4 COLLATE '.StaffEmailIdentity::COLLATION.' GENERATED ALWAYS AS (LOWER(TRIM(`email`))) STORED');
        }
        if (!$index) {
            IssuerStorage::query($db, 'ALTER TABLE `'.$table.'` ADD UNIQUE INDEX `'.StaffEmailIdentity::INDEX.'` (`'.StaffEmailIdentity::COLUMN.'`)');
        }
        $columns = array_column(self::columns($db, $table), null, 'COLUMN_NAME');
        if (!isset($columns[StaffEmailIdentity::COLUMN]) || !self::validColumn($columns[StaffEmailIdentity::COLUMN]) || !self::validIndex(self::indexes($db, $table))) {
            throw new RuntimeException('issuer_staff_email_schema_invalid');
        }
    }
}
