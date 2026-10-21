<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';require_once __DIR__.'/CompanyPortalSupportWriteSchema.php';require_once __DIR__.'/CompanyPortalSupportFilesSchema.php';require_once __DIR__.'/CompanyContractDiscussionsSchema.php';
/** Additive migration434 compatibility for legacy database defaults; never called by a domain writer. */
final class CompanyOperationalAuthorCharset
{
 public const TABLES=['company_portal_support_origins','company_portal_support_replies','company_portal_support_files','company_contract_discussions'];
 private static function column($db,string $p,string $table):array
 {
  if(!preg_match('/^[A-Za-z0-9_]+$/D',$p)||!in_array($table,self::TABLES,true))throw new InvalidArgumentException('Invalid author charset target');$r=IssuerStorage::query($db,'SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,CHARACTER_SET_NAME,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',[$p.$table,'author_name'])->row_array();
  if(($r['COLUMN_TYPE']??null)!=='varchar(383)'||($r['IS_NULLABLE']??null)!=='NO'||!IssuerStorage::sqlNullDefault($r)||($r['EXTRA']??'')!==''||!in_array($r['CHARACTER_SET_NAME']??null,['latin1','utf8','utf8mb3','utf8mb4'],true))throw new RuntimeException('issuer_operational_author_charset_invalid');return $r;
 }
 public static function ensure($db,string $p):void
 {
  IssuerTransaction::assertIndependent($db);CompanyPortalSupportWriteSchema::assertValid($db,$p);CompanyPortalSupportFilesSchema::assertValid($db,$p);CompanyContractDiscussionsSchema::assertValid($db,$p);
  // Preflight every supported historical column before any DDL. Partial DDL failures propagate; replay resumes safely.
  $columns=[];foreach(self::TABLES as $table)$columns[$table]=self::column($db,$p,$table);
  foreach($columns as $table=>$column)if($column['CHARACTER_SET_NAME']!=='utf8mb4'||$column['COLLATION_NAME']!=='utf8mb4_bin')IssuerStorage::query($db,'ALTER TABLE `'.$p.$table.'` MODIFY `author_name` varchar(383) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL');self::assertValid($db,$p);IssuerTransaction::assertIndependent($db);
 }
 public static function assertValid($db,string $p):void{foreach(self::TABLES as $table){$r=self::column($db,$p,$table);if($r['CHARACTER_SET_NAME']!=='utf8mb4'||$r['COLLATION_NAME']!=='utf8mb4_bin')throw new RuntimeException('issuer_operational_author_charset_invalid');}}
}
