<?php
require __DIR__.'/primary_catalog.php';
if(!function_exists('_l')){function _l($key){return$key;}}
$html=$namedCatalog->taxDropdown('taxname[]',['Secondary tax|6.0000'],[['taxname'=>'Secondary tax','taxrate'=>'6.00']]);
essentialCheck(substr_count($html,'<option ')===count($namedCatalog->taxes()),'catalogue and document history normalize before option deduplication');
essentialCheck(str_contains($html,'value="Secondary tax|6.0000" selected '),'native selected decimal rate survives SQLite numeric projection');
$legacy=$ui->taxDropdown('taxname[]',['Historical tax|5']);
essentialCheck(substr_count($legacy,'<option ')===1&&str_contains($legacy,'value="Historical tax|5.0000" selected '),'primary historical choice keeps canonical decimal selection');
echo "Tax dropdown decimal projection: three actual SQLite checks passed.\n";
