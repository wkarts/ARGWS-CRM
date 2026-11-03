<?php
/** Dedicated synthetic persistent MySQL tables; schema metadata and replay, never production. */
declare(strict_types=1);
require __DIR__.'/legacy_financial_module_fixture.php';
require_once APPPATH.'services/NativeCompanyCustomerFileSchema.php';
$p='customerfiles474_'.bin2hex(random_bytes(8)).'_';$created=[];
try{
 foreach(['issuers_registry'=>'id INT UNSIGNED NOT NULL PRIMARY KEY','files'=>'id INT NOT NULL PRIMARY KEY','clients'=>'userid INT NOT NULL PRIMARY KEY']as$t=>$ddl){$db->query('CREATE TABLE '.$p.$t.' ('.$ddl.') ENGINE=InnoDB');$created[]=$p.$t;}
 NativeCompanyCustomerFileSchema::ensure($db,$p);$created[]=$p.'company_customer_file_contents';NativeCompanyCustomerFileSchema::ensure($db,$p);
 $db->query('ALTER TABLE '.$p.'company_customer_file_contents ADD INDEX unexpected_content(content_hash)');try{NativeCompanyCustomerFileSchema::assertValid($db,$p);throw new LogicException('unexpected index accepted');}catch(RuntimeException$e){if($e->getMessage()!=='native_customer_file_schema_invalid')throw$e;}
 $db->query('ALTER TABLE '.$p.'company_customer_file_contents DROP INDEX unexpected_content');NativeCompanyCustomerFileSchema::assertValid($db,$p);
 echo "Native customer file MySQL schema: create, replay, exact indexes, FK ownership and extra-index denial passed.\n";
}finally{foreach(array_reverse($created)as$t)$db->query('DROP TABLE '.$t);}
