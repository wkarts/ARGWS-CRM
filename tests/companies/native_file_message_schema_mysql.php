<?php
/** Explicit disposable MySQL schema metadata proof; never production. */
declare(strict_types=1);
require __DIR__.'/legacy_financial_module_fixture.php';
require_once APPPATH.'services/NativeCompanyFileMessageSchema.php';
$p='filemail474_'.bin2hex(random_bytes(8)).'_';$created=[];
try{
 foreach(['issuers_registry'=>'id INT UNSIGNED NOT NULL PRIMARY KEY','mail_queue'=>'id INT NOT NULL PRIMARY KEY']as$t=>$ddl){$db->query('CREATE TABLE '.$p.$t.' ('.$ddl.') ENGINE=InnoDB');$created[]=$p.$t;}
 NativeCompanyFileMessageSchema::ensure($db,$p);$created[]=$p.'company_native_file_mail_requests';NativeCompanyFileMessageSchema::ensure($db,$p);
 $db->query('ALTER TABLE '.$p.'company_native_file_mail_requests ADD INDEX unexpected_binding(binding_hash)');try{NativeCompanyFileMessageSchema::assertValid($db,$p);throw new LogicException('unexpected index accepted');}catch(RuntimeException$e){if($e->getMessage()!=='native_file_mail_schema_invalid')throw$e;}
 $db->query('ALTER TABLE '.$p.'company_native_file_mail_requests DROP INDEX unexpected_binding');NativeCompanyFileMessageSchema::assertValid($db,$p);
 $table=$p.'company_native_file_mail_requests';
 foreach([
  ["ALTER TABLE $table ALTER COLUMN issuer_id SET DEFAULT 1","ALTER TABLE $table ALTER COLUMN issuer_id DROP DEFAULT"],
  ["ALTER TABLE $table MODIFY binding_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL","ALTER TABLE $table MODIFY binding_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL"],
  ["ALTER TABLE $table DROP INDEX issuer_request, ADD UNIQUE KEY issuer_request(issuer_id,request_key(32))","ALTER TABLE $table DROP INDEX issuer_request, ADD UNIQUE KEY issuer_request(issuer_id,request_key)"],
 ]as[$mutate,$restore]){
  $db->query($mutate);try{NativeCompanyFileMessageSchema::assertValid($db,$p);throw new LogicException('mutated metadata accepted');}catch(RuntimeException$e){if($e->getMessage()!=='native_file_mail_schema_invalid')throw$e;}finally{$db->query($restore);}NativeCompanyFileMessageSchema::assertValid($db,$p);
 }
 echo "Native file mail MySQL schema: create/replay, default/collation/prefix-key negatives and owner/queue foreign keys passed.\n";
}finally{foreach(array_reverse($created)as$t)$db->query('DROP TABLE '.$t);}
