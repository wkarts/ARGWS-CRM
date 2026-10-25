<?php
declare(strict_types=1);
require __DIR__.'/ticket_catalog_delete.php';require_once __DIR__.'/../../application/services/NativeCompanyTicketCatalogBackfill.php';
$db->query("INSERT INTO tblissuers_registry(id,active,settings_json) VALUES(4,0,'{}'),(5,1,'{}')");
$before=$db->query('SELECT * FROM tblcompany_ticket_catalog ORDER BY id')->result_array();
$first=NativeCompanyTicketCatalogBackfill::run($wrapped,'tbl');kc($first['issuers']===5&&$first['principal_skipped']&&$first['rows_added']===16,'two existing nonprincipal issuers including inactive receive exact public defaults');
$after=$db->query('SELECT * FROM tblcompany_ticket_catalog ORDER BY id')->result_array();kc(array_slice($after,0,count($before))===$before,'all original configured rows preserved byte for byte');
kc((int)$db->query('SELECT COUNT(*) n FROM tblcompany_ticket_catalog WHERE issuer_id=1')->row_array()['n']===0,'proven principal uses original catalogs');
$second=NativeCompanyTicketCatalogBackfill::run($wrapped,'tbl');kc($second['rows_added']===0&&$second['catalog_sha256']===$first['catalog_sha256'],'replay stable without timestamps rewriting');
$db->query("INSERT INTO tblissuers_registry(id,active,settings_json) VALUES(6,1,'{}'),(7,1,'{}')");
$db->query("CREATE TRIGGER ticket_backfill_fail BEFORE INSERT ON tblcompany_ticket_catalog WHEN NEW.issuer_id=7 BEGIN SELECT RAISE(ABORT,'synthetic final issuer failure'); END");
try{NativeCompanyTicketCatalogBackfill::run($wrapped,'tbl');throw new LogicException('partial backfill committed');}catch(RuntimeException$e){kc($e->getMessage()==='issuer_storage_failed','SQL error surfaced safely');}
kc($after===$db->query('SELECT * FROM tblcompany_ticket_catalog ORDER BY id')->result_array(),'later issuer failure rolls back earlier issuer defaults');$db->query('DROP TRIGGER ticket_backfill_fail');
$db->query("CREATE TRIGGER ticket_backfill_identity AFTER INSERT ON tblcompany_ticket_catalog WHEN NEW.issuer_id=6 BEGIN UPDATE tblissuers_installation_identity SET primary_issuer_id=6 WHERE id=1; END");
$deny(fn()=>NativeCompanyTicketCatalogBackfill::run($wrapped,'tbl'));kc($after===$db->query('SELECT * FROM tblcompany_ticket_catalog ORDER BY id')->result_array(),'identity drift rolls back entire backfill');$db->query('DROP TRIGGER ticket_backfill_identity');
$db->query('UPDATE tblissuers_installation_identity SET primary_issuer_id=999');$deny(fn()=>NativeCompanyTicketCatalogBackfill::run($wrapped,'tbl'));$db->query('UPDATE tblissuers_installation_identity SET primary_issuer_id=1');
$db->trans_begin();try{NativeCompanyTicketCatalogBackfill::run($wrapped,'tbl');throw new LogicException('outer transaction accepted');}catch(RuntimeException$e){kc($e->getMessage()==='issuer_outer_transaction_not_allowed','independent migration transaction required');}finally{$db->trans_rollback();}
$final=NativeCompanyTicketCatalogBackfill::run($wrapped,'tbl');kc($final['rows_added']===16,'repair prerequisite then successful replay without duplicate previous issuers');
echo "Native ticket backfill real SQLite plus explicit metadata double: complete issuer census, inactive issuer, principal skip, original rows, replay, late-write rollback, identity drift and outer-transaction negatives passed. MySQL remains required.\n";
