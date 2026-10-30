<?php
/** Run against disposable MySQL/MariaDB fixture database only. */
declare(strict_types=1);
require __DIR__.'/legacy_financial_module_fixture.php';require_once APPPATH.'services/NativeCompanyContactImageSchema.php';
$p='avatar'.bin2hex(random_bytes(14));$tables=[];
try{
 foreach(['issuers_registry'=>'id INT UNSIGNED PRIMARY KEY','contacts'=>'id INT PRIMARY KEY,userid INT NOT NULL','staff'=>'staffid INT PRIMARY KEY']as$t=>$fields){$db->query('CREATE TABLE '.$p.$t.'('.$fields.') ENGINE=InnoDB');$tables[]=$p.$t;}
 $db->query('INSERT INTO '.$p.'issuers_registry VALUES(41)');$db->query('INSERT INTO '.$p.'contacts VALUES(1,10),(2,10)');$db->query('INSERT INTO '.$p.'staff VALUES(7)');
 // Exact preceding 474 self-contact contract. Upgrade must preserve a proven historical author.
 $fields=[];foreach(NativeCompanyContactImageSchema::FIELDS as$key=>$type)$fields[]=$key.' '.$type.(in_array($key,['mime','sha256','actor_kind'],true)?' CHARACTER SET ascii COLLATE ascii_bin':'').(in_array($key,['updated_by','actor_contact_id'],true)?' NULL':' NOT NULL');
 $fields[]='PRIMARY KEY(contact_id)';$fields[]='KEY owner_client(issuer_id,client_id)';$fields[]='CONSTRAINT old_issuer_'.substr(sha1($p),0,20).' FOREIGN KEY(issuer_id) REFERENCES '.$p.'issuers_registry(id)';$fields[]='CONSTRAINT old_target_'.substr(sha1($p),0,20).' FOREIGN KEY(contact_id) REFERENCES '.$p.'contacts(id)';$fields[]="CONSTRAINT old_self_".substr(sha1($p),0,20)." CHECK ((actor_kind='staff' AND updated_by IS NOT NULL AND updated_by>0 AND actor_contact_id IS NULL) OR (actor_kind='contact' AND updated_by IS NULL AND actor_contact_id IS NOT NULL AND actor_contact_id=contact_id))";
 $table=$p.'company_native_contact_images';$db->query('CREATE TABLE '.$table.'('.implode(',',$fields).') ENGINE=InnoDB');$tables[]=$table;$bytes='synthetic-preserved-schema-bytes';$db->query("INSERT INTO $table VALUES(1,41,10,2,'image/png',?,?,?,NULL,?,'contact',1)",[strlen($bytes),hash('sha256',$bytes),$bytes,'2026-10-25 00:00:00']);$before=$db->query('SELECT * FROM '.$table)->row_array();
 NativeCompanyContactImageSchema::ensure($db,$p);NativeCompanyContactImageSchema::ensure($db,$p);if($before!==$db->query('SELECT * FROM '.$table)->row_array())throw new LogicException('historical avatar changed');
 $db->query('UPDATE '.$table.' SET actor_contact_id=2 WHERE contact_id=1');NativeCompanyContactImageSchema::assertValid($db,$p);
 $bad=false;try{$db->query('UPDATE '.$table.' SET actor_contact_id=99999 WHERE contact_id=1');}catch(Throwable$e){$bad=true;}if(!$bad)throw new LogicException('actor FK missing');
 $fks=$db->query('SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME=?',[$table,$p.'contacts'])->result_array();$actor='nci_'.sha1($table.'|actor');if(!in_array($actor,array_column($fks,'CONSTRAINT_NAME'),true))throw new LogicException('bounded explicit actor FK missing');
 $db->query('ALTER TABLE '.$table.' DROP FOREIGN KEY '.$actor);try{NativeCompanyContactImageSchema::assertValid($db,$p);throw new LogicException('missing FK accepted');}catch(RuntimeException$e){if($e->getMessage()!=='issuer_contact_image_schema_invalid')throw$e;}
 echo "Native contact image MySQL/MariaDB: exact intermediate 474 upgrade/replay, byte preservation, distinct real contact actor and missing FK negative passed.\n";
}finally{foreach(array_reverse($tables)as$table)$db->query('DROP TABLE IF EXISTS '.$table);}
