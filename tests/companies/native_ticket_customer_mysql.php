<?php
/** Dedicated synthetic schema; no existing ticket data, no production DSN. */
declare(strict_types=1);
require __DIR__.'/legacy_financial_module_fixture.php';require_once APPPATH.'services/NativeCompanyTicketCustomerSchema.php';
// Keep MySQL's generated FK names below its 64-character identifier limit.
$p='t474_'.bin2hex(random_bytes(6)).'_';$owned=[];
$create=static function(string$name,string$ddl)use($db,&$owned):void{if($db->query('CREATE TABLE `'.$name.'` ('.$ddl.') ENGINE=InnoDB')===false)throw new RuntimeException('synthetic_create_failed');$owned[]=$name;};
$check=static function(bool$ok,string$name):void{if(!$ok)throw new RuntimeException($name);};
try{
    $create($p.'issuers_registry','id int unsigned NOT NULL PRIMARY KEY');$create($p.'clients','userid int NOT NULL PRIMARY KEY');$create($p.'contacts','userid int NOT NULL,id int NOT NULL');
    foreach(CompanyContractsSchema::definitions($p)as$name=>$parts)$create($p.$name,implode(',',$parts));
    $db->query('INSERT INTO `'.$p.'issuers_registry` VALUES(2),(3)');$db->query('INSERT INTO `'.$p.'clients` VALUES(8),(9)');
    $db->query('INSERT INTO `'.$p.'company_tickets` (issuer_id,client_id,assignee_id,subject,body,status,priority,revision,created_at,updated_at) VALUES(2,8,17,?,?,?,?,1,?,?),(3,9,18,?,?,?,?,1,?,?)',['Synthetic A','body A','open','normal','2026-01-01 00:00:00','2026-01-01 00:00:00','Synthetic B','body B','resolved','high','2026-01-02 00:00:00','2026-01-02 00:00:00']);
    CompanyContractsSchema::assertValid($db,$p);$before=$db->query('SELECT * FROM `'.$p.'company_tickets` ORDER BY id')->result_array();
    require_once APPPATH.'services/CompanyPortalSupportWriteSchema.php';foreach(array_keys(CompanyPortalSupportWriteSchema::definitions($p))as$table)$owned[]=$p.$table;CompanyPortalSupportWriteSchema::ensure($db,$p);
    $first=NativeCompanyTicketCustomerSchema::ensure($db,$p);$check($first['changed']&&$first['rows']===2,'migration_receipt');CompanyContractsSchema::assertValid($db,$p);CompanyPortalSupportWriteSchema::assertValid($db,$p);
    $check($before===$db->query('SELECT * FROM `'.$p.'company_tickets` ORDER BY id')->result_array(),'original_rows_preserved');
    $replay=NativeCompanyTicketCustomerSchema::ensure($db,$p);$check(!$replay['changed']&&$replay['sha256']===$first['sha256'],'migration_replay');
    $db->query('INSERT INTO `'.$p.'company_tickets` (issuer_id,client_id,assignee_id,subject,body,status,priority,revision,created_at,updated_at) VALUES(2,NULL,17,?,?,?,?,1,?,?)',['Email native','body','open','normal','2026-01-03 00:00:00','2026-01-03 00:00:00']);
    $check($db->query('SELECT client_id FROM `'.$p.'company_tickets` WHERE id=3')->row_array()['client_id']===null,'email_ticket_without_fake_customer');
    $failed=false;try{$db->query('UPDATE `'.$p.'company_tickets` SET client_id=999 WHERE id=3');}catch(Throwable$e){$failed=true;}$check($failed,'customer_fk_preserved');
    $db->query('ALTER TABLE `'.$p.'company_tickets` ADD unexpected int NULL');$failed=false;try{NativeCompanyTicketCustomerSchema::ensure($db,$p);}catch(RuntimeException$e){$failed=$e->getMessage()==='issuer_native_ticket_customer_schema_invalid';}$check($failed,'unexpected_column_rejected');
    echo "Native ticket customer MySQL: exact nullable evolution, original row hashes/replay, real NULL insert, foreign-key enforcement and unexpected-column rejection passed.\n";
}finally{foreach(array_reverse($owned)as$name)if($db->table_exists($name))$db->query('DROP TABLE `'.$name.'`');}
