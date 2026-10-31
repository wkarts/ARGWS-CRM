<?php
require __DIR__.'/../native_dashboard_presenter.php';
register_shutdown_function(static function()use($db,$p):void{foreach(['leads','leads_status','ticket_replies','tickets','taskstimers','tasks','projects','contacts'] as $t)$db->query('DROP TABLE IF EXISTS '.$p.$t);});
$db->query("CREATE TABLE {$p}company_lead_stages(id INT PRIMARY KEY,issuer_id INT NOT NULL,name VARCHAR(100) NOT NULL)");
$db->query("DROP TABLE {$p}projects");$db->query("CREATE TABLE {$p}projects(id INT PRIMARY KEY,clientid INT NOT NULL,status INT NOT NULL)");
$db->query("CREATE TABLE {$p}tasks(id INT PRIMARY KEY,status INT NOT NULL,rel_type VARCHAR(32),rel_id INT)");


$db->query("CREATE TABLE {$p}leads_status(id INT PRIMARY KEY,name VARCHAR(100) NOT NULL)");
$db->query("CREATE TABLE {$p}leads(id INT PRIMARY KEY,status INT NOT NULL,client_id INT NOT NULL DEFAULT 0,junk INT NOT NULL DEFAULT 0,addedfrom INT NOT NULL DEFAULT 7,assigned INT NOT NULL DEFAULT 7)");
$db->query("INSERT INTO {$p}clients VALUES(10,'Private A')");$db->query("INSERT INTO {$p}issuers_clients VALUES(10,?,'52998224725',7,'2026-10-25 12:00:00')",[$a]);
$db->query("INSERT INTO {$p}projects VALUES(1,9,2),(2,10,2)");
$db->query("INSERT INTO {$p}tasks VALUES(1,4,'project',1),(2,4,'project',2),(3,5,NULL,NULL)");
$db->query("INSERT INTO {$p}contacts VALUES(1,9),(2,10)");
$db->query("INSERT INTO {$p}tickets VALUES(1,9,1,1,NULL),(2,10,2,1,NULL),(3,9,2,1,NULL)");
$db->query("INSERT INTO {$p}leads_status VALUES(1,'Converted'),(2,'Open')");$db->query("INSERT INTO {$p}leads(id,status,client_id) VALUES(1,1,0),(2,2,0),(3,1,10)");unset($db->data_cache['table_names']);
$db->query("INSERT INTO {$p}leads(id,status,client_id,junk) VALUES(4,2,0,1)");
$primary=(new CompanyDashboardPresenter($db,$p,1,7))->counters('2026-10-25');
essentialCheck($primary['widgets']['projects']['data']['legacy_2']===1&&$primary['widgets']['projects']['data']['active']===0,'principal legacy project excludes secondary-owned customer');
essentialCheck($primary['widgets']['tasks']['data']['total']===2&&$primary['widgets']['tasks']['data']['unfinished']===1,'principal legacy task relation is scoped transitively');
essentialCheck($primary['widgets']['tickets']['data']['legacy_1']===1,'principal legacy ticket validates both customer and contact ownership');
essentialCheck($primary['widgets']['leads']['data']['total']===2&&$primary['widgets']['leads']['data']['converted']===1,'legacy lead lineage retained without changing state');
$secondary=(new CompanyDashboardPresenter($db,$p,$a,7))->counters('2026-10-25');
essentialCheck(!isset($secondary['widgets']['tickets']['data']['legacy_1'])&&$secondary['widgets']['projects']['data']['active']===1,'secondary never receives native legacy lineage');
$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(1,51,1)");$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(1,51,'leads','view')");
$db->query("INSERT INTO {$p}leads(id,status,client_id,junk,addedfrom,assigned) VALUES(5,1,0,0,51,51),(6,1,0,1,51,51)");
$member=(new CompanyDashboardPresenter($db,$p,1,51))->counters('2026-10-25');essentialCheck($member['widgets']['leads']['data']['total']===1&&$member['widgets']['leads']['data']['converted']===1,'native non-admin top stats preserve creator/assignee and junk restriction');
echo "Native dashboard primary lineage: {$GLOBALS['essentialChecks']} cumulative checks passed.\n";
