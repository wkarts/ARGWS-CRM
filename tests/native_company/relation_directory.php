<?php
require dirname(__DIR__).'/settings/company_settings.php';
require_once APPPATH.'services/NativeCompanyRelationDirectory.php';
$db->query("UPDATE {$p}issuers_registry SET active=1 WHERE id=82");
$db->query("ALTER TABLE {$p}clients ADD active INT DEFAULT 1");
$db->query("ALTER TABLE {$p}staff ADD firstname VARCHAR(80)");$db->query("ALTER TABLE {$p}staff ADD lastname VARCHAR(80)");$db->query("UPDATE {$p}staff SET firstname='Synthetic',lastname='Staff'");
$db->query("CREATE TABLE {$p}contacts(id INT PRIMARY KEY,userid INT,firstname VARCHAR(80),lastname VARCHAR(80),email VARCHAR(100),active INT)");
$db->query("CREATE TABLE {$p}contact_permissions(userid INT,permission_id INT)");
$db->query("CREATE TABLE {$p}company_customer_admins(issuer_id INT,client_id INT,staff_id INT)");$db->query("CREATE TABLE {$p}customer_admins(customer_id INT,staff_id INT)");
foreach([41,82]as$i)foreach(['customers','staff']as$feature)$db->query("INSERT INTO {$p}issuers_resource_permissions VALUES(?,12,?,'view')",[$i,$feature]);
foreach([701=>'A',702=>'B',703=>'Legacy',704=>'Assigned']as$id=>$label)$db->query("INSERT INTO {$p}clients(userid,company,active) VALUES(?,?,1)",[$id,$label]);
foreach([701=>41,702=>82,704=>41]as$id=>$i)$db->query("INSERT INTO {$p}issuers_clients(client_id,owner_issuer_id,created_by,created_at) VALUES(?,?,7,'2026-10-25 00:00:00')",[$id,$i]);
foreach([801=>701,802=>702,803=>703,804=>704]as$id=>$client)$db->query("INSERT INTO {$p}contacts VALUES(?,?,'Person','Test',?,1)",[$id,$client,'synthetic'.$id.'@example.invalid']);
$d=new NativeCompanyRelationDirectory($db,$p);
essentialCheck(array_column($d->options(41,12,'customer'),'id')===['701','704'],'customer options bound to own issuer');
essentialCheck(array_column($d->options(82,12,'contact'),'id')===['802'],'contacts never cross issuer');
settingThrows(fn()=>$d->options(41,12,'contact','',802),'native_relation_denied','selected foreign contact denied');
settingThrows(fn()=>$d->options(41,12,'customer','',703),'native_relation_denied','secondary cannot select NULL historical customer');
essentialCheck($d->options($primary,7,'customer','',703)[0]['name']==='Legacy','proven primary preserves native NULL customer');
$db->query("INSERT INTO {$p}issuers_client_links(client_id,issuer_id,linked_by,created_at) VALUES(702,41,7,'2026-10-25 00:00:00')");
essentialCheck($d->options(41,12,'customer','',702)[0]['name']==='B','explicit shared customer projection allowed');
settingThrows(fn()=>$d->options(41,12,'contact','',802),'native_relation_denied','sharing customer does not expose contact');
$db->query("INSERT INTO {$p}company_customer_admins VALUES(41,704,14)");essentialCheck(array_column($d->options(41,14,'customer'),'id')===['704'],'assigned-only actor sees assigned customer');
essentialCheck(array_column($d->options(41,14,'contact'),'id')===['804'],'assigned-only contact authority follows owner');
essentialCheck($d->options(41,12,'contact','',null,701)[0]['id']==='801','client filter preserves relation');
essentialCheck($d->options(41,12,'customer','%')===[],'wildcard search escaped');
$db->query("UPDATE {$p}issuers_staff SET can_view=0 WHERE issuer_id=41 AND staff_id=14");settingThrows(fn()=>$d->options(41,14,'contact'),'native_relation_denied','membership revocation denies assigned reader');
settingThrows(fn()=>$d->options(41,12,'customer',str_repeat('x',201)),'native_relation_invalid','unbounded search refused');
$staffIds=array_column($d->options(41,12,'staff'),'id');essentialCheck(in_array('7',$staffIds,true)&&in_array('12',$staffIds,true)&&!in_array('13',$staffIds,true),'staff directory includes current master/member, not arbitrary global admin');
settingThrows(fn()=>$d->options(41,12,'staff','',13),'native_relation_denied','selected nonmember staff denied');
for($n=0;$n<55;$n++){$id=900+$n;$db->query("INSERT INTO {$p}clients(userid,company,active) VALUES(?,?,1)",[$id,'Bound '.$n]);$db->query("INSERT INTO {$p}issuers_clients(client_id,owner_issuer_id,created_by,created_at) VALUES(?,41,7,'2026-10-25 00:00:00')",[$id]);}
essentialCheck(count($d->options(41,12,'customer','Bound'))===50,'relation response is bounded to 50 actual records');
echo "Native relation customer/contact/staff directory PASS\n";

essentialCheck(array_column($d->options(41,12,'contact','Person Test'),'id')===['801','804'],'contact full-name search preserves native concatenated semantics');
essentialCheck(in_array('12',array_column($d->options(41,12,'staff','Synthetic Staff'),'id'),true),'staff full-name search preserves native concatenated semantics');

essentialCheck(array_column($d->options(41,12,'customer','synthetic801@example.invalid'),'id')===['701'],'customer query can match own authorized contact email');
essentialCheck($d->options(41,12,'customer','synthetic802@example.invalid')===[],'shared customer display does not leak contact email through search results');
