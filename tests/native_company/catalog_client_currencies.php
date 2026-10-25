<?php
require __DIR__.'/primary_catalog.php';
$db->query("CREATE TABLE {$p}currencies(id INTEGER PRIMARY KEY,name TEXT,symbol TEXT,isdefault INT,decimal_separator TEXT,thousand_separator TEXT,placement TEXT)");
$db->query("INSERT INTO {$p}currencies VALUES(1,'BRL','R$',1,',','.','before'),(2,'USD','$',0,'.',',','before')");
if(!$db->field_exists('default_currency',$p.'clients'))$db->query('ALTER TABLE '.$p.'clients ADD default_currency INT');
$db->query("INSERT INTO {$p}clients(userid,company,default_currency) VALUES(101,'Synthetic owned',2)");
$db->query("INSERT INTO {$p}issuers_clients(client_id,owner_issuer_id,tax_id,created_by,created_at) VALUES(101,41,NULL,7,'2026-10-25 00:00:00')");
$ref=new NativeCompanyCurrencyReference($db,$p,41,7);$row=$ref->currency(1);essentialCheck(array_key_exists('isdefault',$row)&&(int)$row['isdefault']===1,'original currency DTO includes real default marker');
$ids=$ref->clientCurrencyIds();essentialCheck($ids===[2],'issuer currency usage from explicit customer ownership');
$other=new NativeCompanyCurrencyReference($db,$p,82,7);essentialCheck($other->clientCurrencyIds()===[],'other issuer never inherits currency from unrelated customer');
echo "Native item currency references: three real SQLite checks passed.\n";
