<?php
/** Real MySQL/MariaDB metadata, dedicated synthetic tables; no production schema/data. */
declare(strict_types=1);
require __DIR__.'/legacy_financial_module_fixture.php';
require_once APPPATH.'services/NativeCompanyPrivacySchema.php';
$p='privacy474_'.bin2hex(random_bytes(8)).'_';$created=[];
try{
    foreach(['issuers_registry'=>'id INT UNSIGNED NOT NULL PRIMARY KEY','gdpr_requests'=>'id INT NOT NULL PRIMARY KEY']as$t=>$ddl){$db->query('CREATE TABLE '.$p.$t.' ('.$ddl.') ENGINE=InnoDB');$created[]=$p.$t;}
    NativeCompanyPrivacySchema::ensure($db,$p);$created[]=$p.'company_native_gdpr_request_owners';NativeCompanyPrivacySchema::ensure($db,$p);NativeCompanyPrivacySchema::assertValid($db,$p);
    $indexes=$db->query('SHOW INDEX FROM '.$p.'company_native_gdpr_request_owners')->result_array();$issuer=array_values(array_filter($indexes,fn($r)=>$r['Key_name']==='issuer_id'));
    if(count($issuer)!==1||(int)$issuer[0]['Non_unique']!==1||$issuer[0]['Column_name']!=='issuer_id')throw new RuntimeException('explicit issuer index missing');
    $db->query('ALTER TABLE '.$p.'company_native_gdpr_request_owners ADD INDEX unexpected_source(source_id)');
    try{NativeCompanyPrivacySchema::assertValid($db,$p);throw new LogicException('unexpected index accepted');}catch(RuntimeException$e){if($e->getMessage()!=='issuer_privacy_schema_invalid')throw$e;}
    $db->query('ALTER TABLE '.$p.'company_native_gdpr_request_owners DROP INDEX unexpected_source');NativeCompanyPrivacySchema::assertValid($db,$p);
    echo "Native privacy MySQL schema: create, replay, issuer FK supporting nonunique index, extra-index denial and restored metadata passed.\n";
}finally{foreach(array_reverse($created)as$t)$db->query('DROP TABLE '.$t);}
