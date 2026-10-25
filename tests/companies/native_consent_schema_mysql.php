<?php
/** Real MySQL/MariaDB disposable metadata and interrupted-DDL proof. */
declare(strict_types=1);
require __DIR__.'/legacy_financial_module_fixture.php';
require_once APPPATH.'services/NativeCompanyConsentSchema.php';
$p='consent474_'.bin2hex(random_bytes(6)).'_';$created=[];
try{
 $db->query('CREATE TABLE '.$p.'issuers_registry(id INT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=InnoDB');$created[]=$p.'issuers_registry';$db->query('INSERT INTO '.$p.'issuers_registry VALUES(1)');
 NativeCompanyConsentSchema::ensure($db,$p);$created[]=$p.'company_native_consent_purposes';$created[]=$p.'company_native_consents';NativeCompanyConsentSchema::ensure($db,$p);
 $t=$p.'company_native_consents';$nonce=str_repeat('a',64);$at='2026-10-25 00:00:00';$record=json_encode(['purpose_name'=>'Proven source','purpose_revision'=>1],JSON_THROW_ON_ERROR);
 $db->query('INSERT INTO '.$p.'company_native_consent_purposes(issuer_id,name,description,revision,write_nonce,date_created,last_updated) VALUES(1,?,?,1,?,?,?)',['Proven source','Meaning',$nonce,$at,$at]);$purpose=(int)$db->insert_id();
 $db->query("INSERT INTO $t(issuer_id,contact_id,purpose_id,record_json,nonce,created_at,purpose_source) VALUES(1,1,?,?,?,?,'company_consent_purpose')",[$purpose,$record,$nonce,$at]);
 // Recreate the exact earlier unpublished shape without interpreting a numeric ID.
 $db->query('DROP TABLE '.$t);$ddl=NativeCompanyConsentSchema::definitions($p)['company_native_consents'];$ddl=str_replace(",purpose_source VARCHAR(32) NOT NULL,CONSTRAINT {$p}consent_source CHECK (purpose_source IN ('company_consent_purpose','consent_purpose'))",'',$ddl);$db->query($ddl);
 $db->query("INSERT INTO $t(issuer_id,contact_id,purpose_id,record_json,nonce,created_at) VALUES(1,1,?,?,?,?)",[$purpose,$record,$nonce,$at]);
 NativeCompanyConsentSchema::ensure($db,$p);NativeCompanyConsentSchema::ensure($db,$p);
 $row=$db->query('SELECT * FROM '.$t)->row_array();if($row['record_json']!==$record||$row['purpose_source']!=='company_consent_purpose')throw new RuntimeException('consent receipt changed');
 // Simulate successful ADD followed by interruption before DROP DEFAULT.
 $db->query("ALTER TABLE $t ALTER purpose_source SET DEFAULT 'company_consent_purpose'");NativeCompanyConsentSchema::ensure($db,$p);NativeCompanyConsentSchema::assertValid($db,$p);
 foreach([["ALTER TABLE $t ALTER purpose_source SET DEFAULT 'consent_purpose'","ALTER TABLE $t ALTER purpose_source DROP DEFAULT"],["ALTER TABLE $t ADD INDEX unexpected_contact(contact_id)","ALTER TABLE $t DROP INDEX unexpected_contact"]]as[$mutate,$restore]){$db->query($mutate);try{NativeCompanyConsentSchema::assertValid($db,$p);throw new LogicException('unsafe consent metadata accepted');}catch(RuntimeException$e){if($e->getMessage()!=='native_consent_schema_invalid')throw$e;}finally{$db->query($restore);}}
 NativeCompanyConsentSchema::assertValid($db,$p);echo "Native consent MySQL schema: fresh/replay, proven predecessor, interrupted default-stage and metadata negatives passed.\n";
}finally{foreach(array_reverse($created)as$t)$db->query('DROP TABLE IF EXISTS '.$t);}
