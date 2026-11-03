<?php
require __DIR__.'/auxiliary_contract.php';
require_once APPPATH.'services/CompanyCurrencyUnits.php';
$db->query("UPDATE {$p}issuers_staff SET can_view=1 WHERE issuer_id=41 AND staff_id=12");
$db->query("CREATE TABLE {$p}currencies(id INTEGER PRIMARY KEY,name TEXT,symbol TEXT,isdefault INT,decimal_separator TEXT,thousand_separator TEXT,placement TEXT)");
$db->query("INSERT INTO {$p}currencies VALUES(1,'BRL','R$',1,',','.','before'),(2,'USD','$',0,'.',',','before')");
$db->query("CREATE TABLE {$p}company_currency_units(currency_id INT PRIMARY KEY,iso_code TEXT,basis TEXT,recorded_at TEXT,source_hash TEXT)");
$c=new NativeCompanyCatalog($db,$p,41,12);$id=$c->saveItem(['description'=>'USD catalogue price','rate'=>'10','rate_currency_2'=>'2.50']);
$units=new CompanyCurrencyUnits($db,$p);
essentialCheck($units->resolveRead(2)['iso_code']==='USD'&&$c->item($id)['rate_currency_2']==='2.5000','catalogue secondary price freezes currency meaning without conversion');
settingThrows(fn()=>$units->editCatalog(2,'EUR',fn()=>throw new RuntimeException('must not write')),'company_currency_catalog_locked','catalogue currency cannot silently change ISO');
settingThrows(fn()=>$units->deleteCatalog(2,fn()=>throw new RuntimeException('must not delete')),'company_currency_catalog_locked','catalogue currency cannot be deleted');
$db->query("UPDATE {$p}currencies SET name='EUR' WHERE id=2");
settingThrows(fn()=>$c->saveItem(['description'=>'Changed','rate'=>'10','rate_currency_2'=>'2.50'],$id,1),'company_currency_catalog_changed','external catalog mutation denied before updating item');
essentialCheck($db->query("SELECT description FROM {$p}company_items WHERE id=?",[$id])->row_array()['description']==='USD catalogue price','failed currency check rolls back item');
settingThrows(fn()=>$c->item($id),'company_currency_catalog_changed','read cannot relabel frozen price after external ISO mutation');
echo "Owned catalogue currency identity/fences: passed.\n";
