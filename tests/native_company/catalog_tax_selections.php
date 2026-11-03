<?php
require __DIR__.'/primary_catalog.php';
essentialCheck($ui->validateTaxSelections(['Historical tax|5'])===['Historical tax|5.0000'],'native default tax wire preserved with canonical decimal rate');
essentialCheck($namedCatalog->validateTaxSelections(['Secondary tax|6.00'])===['Secondary tax|6.0000'],'secondary defaults resolve only its own catalogue');
settingThrows(fn()=>$namedCatalog->validateTaxSelections(['Historical tax|5']),'company_catalog_invalid_tax_selection','secondary cannot inherit principal default tax');
settingThrows(fn()=>$ui->validateTaxSelections(['Historical tax|6']),'company_catalog_invalid_tax_selection','same label with mismatched rate is not accepted');
settingThrows(fn()=>$ui->validateTaxSelections(['Historical tax|5','Historical tax|5.0000']),'company_catalog_invalid_tax_selection','duplicate normalized selection cannot apply tax twice');
$db->query("INSERT INTO {$p}taxes(id,name,taxrate) VALUES(99,'Historical tax',5)");
settingThrows(fn()=>$ui->validateTaxSelections(['Historical tax|5']),'company_catalog_invalid_tax_selection','ambiguous native catalogue pair requires correction, not first match');
essentialCheck($ui->validateTaxSelections([])===[],'explicit empty defaults stay empty');
echo "Native tax default wire, exact issuer resolution and ambiguity checks: passed.\n";
