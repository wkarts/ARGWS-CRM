<?php
/** Actual SQLite, same ACL/authority/storage; no browser or MySQL claim. */
declare(strict_types=1);
require __DIR__.'/ticket_read.php';
if(!defined('BASEPATH'))define('BASEPATH',true);
require_once __DIR__.'/../../application/services/ClientIssuerScope.php';
// Count a complete 50-result page before exercising the >1000 dataset.
$statement=$db->pdo->prepare("INSERT INTO tblcompany_tickets(issuer_id,client_id,subject,body,status,priority,assignee_id,revision,created_at,updated_at) VALUES(2,NULL,?,'body','open','normal',17,1,'2026-01-01','2026-01-01')");
for($n=0;$n<1100;$n++)$statement->execute(['Paging match '.$n]);
$db->queries=[];$page=$reader->relationOptions(2,17,'Paging match');$queryCount=count($db->queries);
echo 'Optimized 50-result page SQL count: '.$queryCount."\n";
kc(count($page)===50,'over 1000 rows returns first 50, no artificial catalog limit');
kc($queryCount<4000,'narrow authority does not read all 27 preferences per reference');
kc(!preg_grep('/SELECT t\.(?:id|ticketid) AS candidate_id.*SELECT.*subject|SELECT t\.subject/',$db->queries),'candidate queries return ID only');
$candidates=array_values(preg_grep('/AS candidate_id/',$db->queries));kc(count($candidates)===1&&str_contains($candidates[0],"t.subject LIKE ? ESCAPE '!'")&&str_contains($candidates[0],'t.id>?'),'search uses SQL bound predicate and keyset');
// Literal wildcard characters must not broaden the candidate population.
$statement->execute(['Literal %_!']);$literalId=(int)$db->insert_id();$literal=$reader->relationOptions(2,17,'%_!');kc(count($literal)===1&&$literal[0]['id']===NativeCompanyResourceId::encode('company_ticket',$literalId),'LIKE wildcard escaping literal');
$statement->execute(['Beyond first thousand']);$lastId=(int)$db->insert_id();kc($reader->relationOptions(2,17,'Beyond first thousand')[0]['id']===NativeCompanyResourceId::encode('company_ticket',$lastId),'SQL predicate finds matches after 1000 rows');
// Every preceding record is inaccessible to non-admin staff; paginator must scan onward.
$db->query('UPDATE tblissuers_installation_identity SET master_staff_id=NULL');$db->query('UPDATE tblstaff SET admin=0 WHERE staffid=17');
$db->query("INSERT INTO tblcompany_department_members(issuer_id,department_source_kind,department_source_id,staff_id,revision,actor_id,created_at,updated_at) VALUES(2,'company_ticket_department',1,17,1,17,'2026-01-01','2026-01-01')");
$db->query("INSERT INTO tblcompany_ticket_native_metadata(issuer_id,ticket_id,department_source_kind,department_source_id,sender_origin,sender_name,sender_email,cc_json,opened_by,admin_read,project_source_kind,revision,actor_id,created_at,updated_at) VALUES(2,?,'company_ticket_department',1,'staff_entered','Test','test@example.invalid','[]',17,0,'',1,17,'2026-01-01','2026-01-01')",[$lastId]);
$db->queries=[];$all=$reader->relationOptions(2,17);$scanCount=count($db->queries);$ids=array_column($all,'id');
kc(in_array(NativeCompanyResourceId::encode('company_ticket',$lastId),$ids,true)&&count($all)===3,'keyset continues beyond denied records without hydrating their subjects');
kc(count(preg_grep('/AS candidate_id/',$db->queries))===12,'12 bounded keyset pages cover >1100 candidates');
kc(count(preg_grep('/SELECT subject FROM tblcompany_tickets/',$db->queries))===3,'only authorized subjects hydrated during denied-page scan');
echo '1104-candidate denied-department scan SQL count: '.$scanCount."\n";
$db->query('DELETE FROM tblcompany_department_members WHERE department_source_id=1');kc($reader->relationOptions(2,17)===[],'department revocation current, no cached permission');
$deny(fn()=>$reader->relationOptions(2,17,'',NativeCompanyResourceId::encode('company_ticket',$lastId)));
$deny(fn()=>$reader->relationOptions(3,17,'Paging match'));
// Restore primary authority and verify historical ownership boundary is still applied.
$db->query('UPDATE tblstaff SET admin=1 WHERE staffid=17');$db->query('UPDATE tblissuers_installation_identity SET master_staff_id=17');
$primary=$reader->relationOptions(1,17,'Historical primary');kc(count($primary)===1&&$primary[0]['id']==='1','principal historical owner retains native ticket');
kc($reader->relationOptions(1,17,'Private B native')===[],'principal keyset SQL boundary excludes other issuer before subject');
echo "Ticket relations actual SQLite: SQL keyset/search escaping, >1000 population, department-denied pages, A/B/primary and revocation PASS.\n";
