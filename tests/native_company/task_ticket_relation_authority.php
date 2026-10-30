<?php
/** Real ticket reader/SQLite, canonical task identity; task shape is deliberately minimal for pre-label authority. */
require __DIR__.'/ticket_read.php';
require_once __DIR__.'/../../application/services/NativeCompanyTaskRelationAuthority.php';
$db->query('CREATE TABLE tblcompany_standalone_tasks(id INTEGER PRIMARY KEY,issuer_id INTEGER,name TEXT,native_fields_json TEXT,native_fields_revision INTEGER)');
$fields=['relation_type'=>'ticket','relation_source_kind'=>'company_ticket','relation_source_id'=>NativeCompanyResourceId::decode('ticket',$id)['source_id']];
$db->query('INSERT INTO tblcompany_standalone_tasks VALUES(1,2,?,?,1)',['Private task A',json_encode($fields)]);
$task=NativeCompanyResourceId::encode('company_standalone_task',1);
$r=NativeCompanyResourceReferences::resolve($db,'tbl',2,17,'task',$task);kc($r['name']==='Private task A','canonical task resolves only through actual ticket authority');
$captured=NativeCompanyTaskRelationAuthority::capture($db,'tbl',2,17,1);$db->query('UPDATE tblcompany_standalone_tasks SET native_fields_revision=2 WHERE id=1');$deny(fn()=>NativeCompanyTaskRelationAuthority::confirm($db,'tbl',2,17,1,$captured));
$db->query('UPDATE tblissuers_installation_identity SET master_staff_id=NULL');$db->query('UPDATE tblstaff SET admin=0 WHERE staffid=17');
$db->query("INSERT INTO tblissuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(2,17,'tasks','view')");
$db->queries=[];$deny(fn()=>NativeCompanyResourceReferences::resolve($db,'tbl',2,17,'task',$task));kc(!preg_grep('/SELECT id AS source_id, name AS label FROM tblcompany_standalone_tasks/',$db->queries),'department revocation denies before private task label hydration');
$db->query('UPDATE tblstaff SET admin=1 WHERE staffid=17');$db->query('UPDATE tblissuers_installation_identity SET master_staff_id=17');
$bad=$fields;$bad['relation_source_kind']='invoice';$db->query('UPDATE tblcompany_standalone_tasks SET native_fields_json=? WHERE id=1',[json_encode($bad)]);$deny(fn()=>NativeCompanyResourceReferences::resolve($db,'tbl',2,17,'task',$task));
$bad=$fields;$bad['relation_source_kind']='ticket';$bad['relation_source_id']=1;$db->query('UPDATE tblcompany_standalone_tasks SET native_fields_json=? WHERE id=1',[json_encode($bad)]);$deny(fn()=>NativeCompanyResourceReferences::resolve($db,'tbl',2,17,'task',$task));
$db->query('UPDATE tblcompany_standalone_tasks SET native_fields_json=NULL,native_fields_revision=NULL WHERE id=1');kc(NativeCompanyResourceReferences::resolve($db,'tbl',2,17,'task',$task)['name']==='Private task A','historical standalone without declaration stays unrelated, never inferred');
$db->query('ALTER TABLE tblcompany_standalone_tasks RENAME COLUMN native_fields_json TO removed_native_fields');
$db->query('UPDATE tblmigrations SET version=474');$deny(fn()=>NativeCompanyResourceReferences::resolve($db,'tbl',2,17,'task',$task));
$db->query('UPDATE tblmigrations SET version=473');kc(NativeCompanyResourceReferences::resolve($db,'tbl',2,17,'task',$task)['name']==='Private task A','explicit pre474 shape retains historical standalone reader');
echo "Task-to-ticket canonical authority: real parent ACL, source separation, CAS and pre-label revocation passed.\n";
