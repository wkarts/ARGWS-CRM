<?php
require __DIR__.'/native_clients.php';
$start=$GLOBALS['essentialChecks'];
$db->query("INSERT INTO {$p}contacts(id,userid,active,is_primary,firstname,lastname,email,last_login) VALUES(999,?,1,1,'Only A','Primary','only-a@example.invalid','2026-01-01 00:00:00')",[$nativeA]);
$db->query("INSERT INTO {$p}customers_groups(name) VALUES('Owner group')");$group=(int)$db->insert_id();$db->query("INSERT INTO {$p}company_customer_group_owners(group_id,issuer_id) VALUES(?,41)",[$group]);$db->query("INSERT INTO {$p}customer_groups(customer_id,groupid) VALUES(?,?)",[$nativeA,$group]);
$owner=$adapterA->table(['search'=>['value'=>'only-a@'],'order'=>[['column'=>'4','dir'=>'asc']]]);essentialCheck($owner['recordsFiltered']===1&&$owner['rows'][0]['primary_email']==='only-a@example.invalid'&&$owner['rows'][0]['groups'][0]['name']==='Owner group','same native table projects/searches actual primary contact and owner groups');
$db->query("INSERT INTO {$p}issuers_resource_permissions VALUES(82,12,'customers','view')");$shared=$adapterB->table(['search'=>['value'=>'only-a@']]);essentialCheck($shared['recordsFiltered']===0,'shared identity cannot infer private primary contact through search');
$shared=$adapterB->table(['search'=>['value'=>'Native A']]);$row=$shared['rows'][0];essentialCheck($row['primary_email']===null&&$row['primary_firstname']===null&&$row['groups']===[],'shared identity does not reveal primary contact or private group names');
$db->query("INSERT INTO {$p}contacts(id,userid,active,is_primary,firstname,lastname,email) VALUES(1000,?,1,1,'Corrupt','Second','second@example.invalid')",[$nativeA]);essentialThrows(fn()=>$adapterA->table([]),'company_contact_invalid_primary','ambiguous primary contact fails instead of selecting an arbitrary identity');
echo 'Native customer table projection: '.($GLOBALS['essentialChecks']-$start).' checks passed.'.PHP_EOL;
