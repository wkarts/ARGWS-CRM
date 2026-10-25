<?php
/** Principal catalogue never needed an owned shadow: these tables are new in unreleased core474. */
require __DIR__.'/primary_catalog_models.php';
$release=CompanySettingsRuntime::enterRequest($db,$p,$principal,7);
$c=new NativeCompanyCatalogUi($db,$p,$principal,7,$ci);
$tax=$c->saveTax(['name'=>'New native tax','taxrate'=>'9']);$group=$c->saveGroup(['name'=>'New native group']);
essentialCheck((int)$tax>0&&(int)$group>0&&!str_contains($tax,'e'),'principal creation returns actual unchanged native identifiers');
essentialCheck($db->query('SELECT name FROM '.$p.'taxes WHERE id=?',[$tax])->row()->name==='New native tax'&&$db->query('SELECT name FROM '.$p.'items_groups WHERE id=?',[$group])->row()->name==='New native group','actual native models own principal catalogue creation');
foreach(['company_items','company_taxes','company_item_groups']as$table)essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.$table.' WHERE issuer_id=?',[$principal])->row()->n===0,'no principal catalogue shadow created');
$owned=new NativeCompanyCatalog($db,$p,$principal,7);
settingThrows(fn()=>$owned->saveTax(['name'=>'Wrong storage','taxrate'=>'1']),'company_catalog_primary_native_required','direct owned writer cannot create a principal shadow');
$db->query("CREATE TRIGGER ignore_native_tax BEFORE INSERT ON {$p}taxes BEGIN SELECT RAISE(IGNORE); END");
settingThrows(fn()=>$c->saveTax(['name'=>'Must not exist','taxrate'=>'2']),'issuer_catalog_write_unconfirmed','ignored native tax insertion cannot be mistaken for existing record');$db->query('DROP TRIGGER ignore_native_tax');
$db->query("INSERT INTO {$p}company_taxes(issuer_id,name,taxrate,revision) VALUES(?,'Unexpected existing owned',4,1)",[$principal]);
settingThrows(fn()=>new NativeCompanyCatalogUi($db,$p,$principal,7,$ci),'company_catalog_primary_owned_migration_required','unexpected principal owned data requires explicit migration rather than being hidden or discarded');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}company_taxes WHERE issuer_id=? AND name='Unexpected existing owned'",[$principal])->row()->n===1,'unexpected data remains byte-preserved');
$release();echo "Principal native catalogue creation, secondary storage fence and unexpected-data preservation: passed.\n";
