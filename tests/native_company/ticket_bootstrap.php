<?php
declare(strict_types=1);
require __DIR__.'/ticket_catalog_delete.php';require_once __DIR__.'/../../application/services/NativeCompanyTicketBootstrap.php';
$db->query('DROP TABLE tblmigrations');try{NativeCompanyTicketBootstrap::plan($wrapped,'tbl');throw new LogicException('missing version treated as legacy');}catch(RuntimeException $e){kc($e->getMessage()==='issuer_storage_failed','missing version fails closed');}
$db->query('CREATE TABLE tblmigrations(version BIGINT NOT NULL)');$db->query('INSERT INTO tblmigrations VALUES(473)');$plan=NativeCompanyTicketBootstrap::plan($wrapped,'tbl');kc($plan===['version'=>473,'tables'=>['migrations']],'explicit pre474 boundary');
$db->query('UPDATE tblmigrations SET version=474');$plan=NativeCompanyTicketBootstrap::plan($wrapped,'tbl');kc(count($plan['tables'])===4,'474 declares catalog and both references');
$tables=array_merge(['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff'],$plan['tables']);$run=fn($p)=>IssuerTransaction::run($wrapped,'tbl',$tables,fn()=>NativeCompanyTicketBootstrap::initializeInOwnedTransaction($wrapped,'tbl',2,17,$p));$before=$db->query('SELECT * FROM tblcompany_ticket_catalog ORDER BY id')->result_array();$run($plan);kc($before===$db->query('SELECT * FROM tblcompany_ticket_catalog ORDER BY id')->result_array(),'474 confirmed replay does not rewrite configured defaults');
$db->query('UPDATE tblmigrations SET version=473');$deny(fn()=>$run($plan));kc($before===$db->query('SELECT * FROM tblcompany_ticket_catalog ORDER BY id')->result_array(),'version change cannot silently skip bootstrap');
foreach(['0474','474x','0','-1']as$bad){$db->query('UPDATE tblmigrations SET version=?',[$bad]);if($bad==='0474')continue; // SQLite integer affinity canonicalizes the stored value to 474.
$deny(fn()=>NativeCompanyTicketBootstrap::plan($wrapped,'tbl'));}
$db->query('UPDATE tblmigrations SET version=474');$db->query('INSERT INTO tblmigrations VALUES(474)');$deny(fn()=>NativeCompanyTicketBootstrap::plan($wrapped,'tbl'));
echo "Ticket bootstrap actual SQLite plus metadata double: exact persisted version, no absent fallback, 473/474 boundary, version race, confirmed replay and duplicate version rejection passed.\n";
