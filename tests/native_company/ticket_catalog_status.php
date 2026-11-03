<?php
declare(strict_types=1);
require __DIR__.'/ticket_catalog.php';$db->query('UPDATE tblissuers_installation_identity SET master_staff_id=17');$db->query('UPDATE tblissuers_staff SET can_view=1 WHERE issuer_id=2 AND staff_id=17');
$db->query('CREATE TABLE tblcompany_tickets(id INTEGER PRIMARY KEY,issuer_id INTEGER,status TEXT)');$db->query("INSERT INTO tblcompany_tickets VALUES(1,2,'open'),(2,2,'resolved'),(3,3,'closed')");$status=$catalog->list(2,17,'status')[0];
$db->query("INSERT INTO tblcompany_ticket_catalog_references(id,issuer_id,ticket_id,kind,source_kind,source_id) VALUES(1,2,1,'status',?,?)",[$status['source_kind'],$status['source_id']]);$db->queries=[];
$counts=$catalog->statusCounts(2,17);kc($counts['counts'][$status['native_id']]===1&&$counts['historical_owned']===['resolved'=>1],'configured status counts and original textual status remain distinct');kc(!preg_grep('/FROM tbltickets /',$db->queries),'named counts never read historical global tickets');
$db->query("INSERT INTO tblcompany_ticket_catalog_references(id,issuer_id,ticket_id,kind,source_kind,source_id) VALUES(2,2,3,'status',?,?)",[$status['source_kind'],$status['source_id']]);$deny(fn()=>$catalog->statusCounts(2,17));$db->query('DELETE FROM tblcompany_ticket_catalog_references WHERE id=2');
$foreign=$catalog->list(3,18,'status')[0];$db->query('UPDATE tblcompany_ticket_catalog_references SET source_id=? WHERE id=1',[$foreign['source_id']]);$deny(fn()=>$catalog->statusCounts(2,17));
echo "Native status counts actual SQLite: exact configured references, preserved historical resolved state, foreign ticket/status references rejected and no global read passed.\n";
