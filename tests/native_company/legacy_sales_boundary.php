<?php
require __DIR__.'/legacy_item_order.php';require_once APPPATH.'services/NativeCompanyLegacySalesBoundary.php';
$db->query("INSERT INTO {$p}leads(id,client_id,issuer_id) VALUES(9991,?,NULL),(9992,?,NULL),(9993,?,?)",[$historicalClient,$ca,$historicalClient,$a]);
$db->query("UPDATE {$p}proposals SET rel_type='lead',rel_id=9991 WHERE id=9981");essentialCheck(NativeCompanyLegacySalesBoundary::permits($db,$p,'proposals',9981),'native lead proposal retains proven historical relation');
foreach([9992,9993,9994]as$lead){$db->query("UPDATE {$p}proposals SET rel_id=? WHERE id=9981",[$lead]);essentialCheck(!NativeCompanyLegacySalesBoundary::permits($db,$p,'proposals',9981),'proposal cannot follow named or missing lead '.$lead);}
$db->query("UPDATE {$p}proposals SET rel_type='LEAD',rel_id=9991 WHERE id=9981");essentialCheck(!NativeCompanyLegacySalesBoundary::permits($db,$p,'proposals',9981),'noncanonical lead relation is not interpreted through database collation');
$db->query("UPDATE {$p}proposals SET rel_type='',rel_id=0 WHERE id=9981");essentialCheck(NativeCompanyLegacySalesBoundary::permits($db,$p,'proposals',9981),'historical standalone proposal with no relation preserved');
$db->query("UPDATE {$p}proposals SET rel_id=9991 WHERE id=9981");essentialCheck(!NativeCompanyLegacySalesBoundary::permits($db,$p,'proposals',9981),'blank relation cannot carry an uninterpreted linked resource');
echo "Legacy proposal lead boundary 7 checks PASS\n";
