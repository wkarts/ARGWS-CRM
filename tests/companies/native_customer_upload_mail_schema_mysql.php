<?php
/** Disposable MySQL/MariaDB metadata proof, not production schema. */
declare(strict_types=1);
require __DIR__.'/legacy_financial_module_fixture.php';require_once APPPATH.'services/NativeCompanyCustomerUploadMailSchema.php';
$p='upload'.bin2hex(random_bytes(16));$created=[];
try{
 foreach(['issuers_registry'=>'id INT UNSIGNED NOT NULL PRIMARY KEY','files'=>'id INT NOT NULL PRIMARY KEY','mail_queue'=>'id INT NOT NULL PRIMARY KEY']as$t=>$ddl){$db->query('CREATE TABLE '.$p.$t.'('.$ddl.') ENGINE=InnoDB');$created[]=$p.$t;}
 NativeCompanyCustomerUploadMailSchema::ensure($db,$p);foreach(NativeCompanyCustomerUploadMailSchema::tables()as$t)$created[]=$p.$t;NativeCompanyCustomerUploadMailSchema::ensure($db,$p);
 foreach(NativeCompanyCustomerUploadMailSchema::tables()as$table){if(strlen($p.$table)>64)throw new LogicException('fixture table identifier too long');$foreign=$db->query('SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.$table])->result_array();foreach($foreign as$row)if(!preg_match('/^ncfum_[a-f0-9]{40}$/D',$row['CONSTRAINT_NAME']))throw new LogicException('foreign key name is not bounded');}
 try{NativeCompanyCustomerUploadMailSchema::ensure($db,str_repeat('x',39));throw new LogicException('oversized table prefix accepted');}catch(InvalidArgumentException $e){if($e->getMessage()!=='Invalid prefix')throw$e;}
 $t=$p.NativeCompanyCustomerUploadMailSchema::RECEIPTS;
 foreach([["ALTER TABLE $t ALTER recipient_id SET DEFAULT 1","ALTER TABLE $t ALTER recipient_id DROP DEFAULT"],["ALTER TABLE $t MODIFY payload_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL","ALTER TABLE $t MODIFY payload_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL"],["ALTER TABLE $t ADD INDEX unexpected_source(source_hash(16))","ALTER TABLE $t DROP INDEX unexpected_source"]]as[$mutate,$restore]){$db->query($mutate);try{NativeCompanyCustomerUploadMailSchema::assertValid($db,$p);throw new LogicException('unsafe customer upload mail metadata accepted');}catch(RuntimeException$e){if($e->getMessage()!=='native_customer_upload_mail_schema_invalid')throw$e;}finally{$db->query($restore);}NativeCompanyCustomerUploadMailSchema::assertValid($db,$p);}
 echo "Native customer upload outbox/receipt MySQL schema: create/replay, precise parent FKs, defaults/collation/prefix-index negatives passed.\n";
}finally{foreach(array_reverse($created)as$t)$db->query('DROP TABLE IF EXISTS '.$t);}
