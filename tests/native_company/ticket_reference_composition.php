<?php
declare(strict_types=1);
require __DIR__.'/ticket_read.php';
$resolve=static fn($issuer,$ticket)=>NativeCompanyResourceReferences::resolve($db,'tbl',$issuer,17,'ticket',$ticket,'view');
kc($resolve(2,$id)['canonical_id']===$id&&$resolve(1,'1')['name']==='Historical primary','actual generic resolver delegates named and proven historical lineage');
$deny(fn()=>$resolve(2,'1'));$deny(fn()=>$resolve(3,$id));$deny(fn()=>$resolve(1,'2'));
$db->query('UPDATE tblissuers_installation_identity SET master_staff_id=NULL');$db->query('UPDATE tblstaff SET admin=0 WHERE staffid=17');$db->queries=[];
$deny(fn()=>$resolve(2,$id));kc(!preg_grep('/SELECT (?:subject|\*) FROM tblcompany_tickets/',$db->queries),'resolver denies department before subject hydration');
$db->query("INSERT INTO tblcompany_department_members(issuer_id,department_source_kind,department_source_id,staff_id,revision,actor_id,created_at,updated_at) VALUES(2,'company_ticket_department',1,17,1,17,'2026-01-01','2026-01-01')");
$db->trans_begin();try{$resolve(2,$id);throw new LogicException('raw transaction accepted');}catch(RuntimeException$e){kc($e->getMessage()==='issuer_outer_transaction_not_allowed','raw PDO transaction is not owned authority');}finally{$db->trans_rollback();}
try{IssuerTransaction::run($db,'tbl',['issuers_lock','issuers_registry'],fn()=>$resolve(2,$id));throw new LogicException('incomplete owned transaction accepted');}catch(RuntimeException$e){kc($e->getMessage()==='issuer_outer_transaction_not_allowed','incomplete owned boundary cannot turn into nested reference read');}
$ok=IssuerTransaction::run($db,'tbl',$reader->tables(),fn()=>$resolve(2,$id));kc($ok['canonical_id']===$id,'complete declared owned boundary joins without nested transaction');
$db->query('DELETE FROM tblcompany_department_members WHERE department_source_id=1');$deny(fn()=>$resolve(2,$id));
echo "Ticket generic resolver composition: actual PDO reader/delegate, named/principal, foreign/raw IDs, department-before-label, raw/incomplete/complete transaction and revocation passed.\n";
