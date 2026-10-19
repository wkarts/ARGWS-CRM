<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
/** Existing customized templates remain NULL-owned, interpreted only as installation-primary. */
final class CompanyEmailTemplateSchema
{
    public static function ensure($db,string $prefix): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)) throw new InvalidArgumentException('Invalid table prefix');
        $table=$prefix.'emailtemplates';
        if (!$db->table_exists($table)) return;
        if (!IssuerStorage::fieldExists($db,'issuer_id',$table)) IssuerStorage::query($db,"ALTER TABLE `{$table}` ADD `issuer_id` INT UNSIGNED NULL DEFAULT NULL");
        $rows=IssuerStorage::query($db,'SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',[$table,'issuer_id'])->result_array();
        $column=$rows[0]??[];
        if (count($rows)!==1 || preg_replace('/^int\([0-9]+\)/','int',strtolower((string)($column['COLUMN_TYPE']??'')))!=='int unsigned' || ($column['IS_NULLABLE']??null)!=='YES' || !IssuerStorage::sqlNullDefault($column)) throw new RuntimeException('company_email_template_schema_invalid');
        $index=self::index($db,$table);
        if (!$index) { IssuerStorage::query($db,"ALTER TABLE `{$table}` ADD INDEX `issuer_template_lookup` (`issuer_id`,`slug`,`language`)");$index=self::index($db,$table); }
        if ($index!==['issuer_id','slug','language']) throw new RuntimeException('company_email_template_index_invalid');
    }
    private static function index($db,string $table): array
    {
        $columns=[];
        foreach(IssuerStorage::query($db,'SELECT INDEX_NAME,NON_UNIQUE,COLUMN_NAME,SEQ_IN_INDEX,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$table])->result_array() as $row) {
            if($row['INDEX_NAME']!=='issuer_template_lookup')continue;
            if((int)$row['NON_UNIQUE']!==1 || $row['SUB_PART']!==null)throw new RuntimeException('company_email_template_index_invalid');
            $columns[(int)$row['SEQ_IN_INDEX']]=$row['COLUMN_NAME'];
        }
        ksort($columns);return array_values($columns);
    }

}
