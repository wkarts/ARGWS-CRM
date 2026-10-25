<?php
require __DIR__.'/primary_catalog.php';
$db->query('UPDATE '.$p.'issuers_staff SET can_view=1 WHERE staff_id=12');
$db->query('DELETE FROM '.$p.'issuers_resource_permissions WHERE staff_id=12');foreach(['view','create']as$cap)$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(41,12,?,?)',['invoices',$cap]);
$editor=new NativeCompanyCatalogUi($db,$p,41,12);$choices=$editor->invoiceEditorChoices();
essentialCheck($choices['ajaxItems']===false&&count($choices['items_groups'])===count($namedCatalog->groups())&&count($choices['items'])>0,'invoice-create-only actor reads original item/group choices without items grant');
essentialCheck($editor->itemForInvoiceEditor($id)['description']==='Secondary item'&&in_array($id,array_column($editor->searchForInvoiceEditor('item'),'id'),true),'same editor item/search use canonical own catalogue identity');
settingThrows(fn()=>$editor->groups(),'company_catalog_denied','temporary invoice reader authority never opens item catalogue');
settingThrows(fn()=>$editor->saveItem(['description'=>'Unauthorized','rate'=>'1']),'company_catalog_denied','invoice creation permission cannot create catalogue items');
settingThrows(fn()=>$editor->itemForInvoiceEditor('1'),'company_catalog_denied','invoice editor cannot load principal raw ID');
$other=new NativeCompanyCatalogUi($db,$p,82,12);settingThrows(fn()=>$other->invoiceEditorChoices(),'company_catalog_denied','grant does not cross company');
$db->query('INSERT INTO '.$p.'issuers_staff(issuer_id,staff_id,can_view) VALUES(?,12,1)',[$principal]);foreach(['view','edit']as$cap)$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,?,?)',[$principal,'invoices',$cap]);
$historical=new NativeCompanyCatalogUi($db,$p,$principal,12);$h=$historical->invoiceEditorChoices();essentialCheck($historical->itemForInvoiceEditor('1')['description']==='Historical item'&&count($h['items_groups'])===1,'principal invoice editor preserves original catalogue without named leakage or item customfield hydration');
$db->query('DELETE FROM '.$p.'issuers_resource_permissions WHERE issuer_id=41 AND staff_id=12 AND feature=? AND capability=?',['invoices','create']);settingThrows(fn()=>$editor->invoiceEditorChoices(),'company_catalog_denied','live invoice-create revocation denies subsequent lookup');
echo "Native invoice catalogue selection: eight actual SQLite authority/lineage/read-only checks passed.\n";

$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(41,12,?,?)',['invoices','create']);
for($n=0;$n<201;$n++)$db->query('INSERT INTO '.$p.'company_items(issuer_id,description,long_description,rate,currency_rates_json,unit,tax,tax2,group_id,revision) VALUES(41,?,?,?,?,?,NULL,NULL,NULL,1)',['Bulk '.$n,'',1,'{}','un']);
$large=$editor->invoiceEditorChoices();essentialCheck($large['ajaxItems']===true&&$large['items']===[]&&count($editor->searchForInvoiceEditor('Bulk 200'))===1,'native threshold200 explicitly enables real AJAX lookup rather than truncating options');
