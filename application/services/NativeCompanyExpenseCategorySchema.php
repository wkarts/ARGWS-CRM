<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Optional original category description. No backfill, adoption or change to historical receipts. */
final class NativeCompanyExpenseCategorySchema
{
    public const TABLE='company_expense_categories';
    private static function table(string$p):string{if(!preg_match('/^[A-Za-z0-9_]+$/D',$p)||strlen($p.self::TABLE)>64)throw new InvalidArgumentException('Invalid physical prefix');return$p.self::TABLE;}
    private static function sqlite($db):bool{$pdo=$db->pdo??$db->conn_id??null;return$pdo instanceof PDO&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';}
    public static function available($db,string$p):bool{return IssuerStorage::fieldExists($db,'description',self::table($p));}
    public static function ensure($db,string$p):void
    {
        $table=self::table($p);IssuerTransaction::assertIndependent($db);
        if(!self::available($db,$p)){
            IssuerStorage::query($db,'ALTER TABLE `'.$table.'` ADD COLUMN `description` TEXT'.(self::sqlite($db)?'':' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin').' NULL DEFAULT NULL');
            // CI caches list_fields/field_exists on the connection used by the migration runner.
            // Only this table changed; a second ensure must observe the new column on that same connection.
            unset($db->data_cache['field_names'][$table]);
        }
        self::assertValid($db,$p);
    }
    public static function assertValid($db,string$p):void
    {
        $table=self::table($p);
        if(self::sqlite($db)){
            $rows=array_values(array_filter(IssuerStorage::query($db,'PRAGMA table_info(`'.$table.'`)')->result_array(),static fn($r)=>$r['name']==='description'));
            if(count($rows)!==1||strtoupper($rows[0]['type'])!=='TEXT'||(int)$rows[0]['notnull']!==0||(int)$rows[0]['pk']!==0||!in_array($rows[0]['dflt_value'],[null,'NULL'],true))throw new RuntimeException('native_expense_category_schema_invalid');return;
        }
        $rows=IssuerStorage::query($db,'SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,COLLATION_NAME,COLUMN_COMMENT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',[$table,'description'])->result_array();
        if(count($rows)!==1||strtolower($rows[0]['COLUMN_TYPE'])!=='text'||$rows[0]['IS_NULLABLE']!=='YES'||!IssuerStorage::sqlNullDefault($rows[0])||$rows[0]['EXTRA']!==''||$rows[0]['COLLATION_NAME']!=='utf8mb4_bin'||$rows[0]['COLUMN_COMMENT']!=='')throw new RuntimeException('native_expense_category_schema_invalid');
        $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('native_expense_category_schema_invalid');
    }
}
