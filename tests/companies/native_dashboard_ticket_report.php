<?php
require __DIR__.'/../company_dashboard_presenter.php';
$db->query("ALTER TABLE {$p}staff ADD firstname VARCHAR(100) NOT NULL DEFAULT 'Synthetic'");$db->query("ALTER TABLE {$p}staff ADD lastname VARCHAR(100) NOT NULL DEFAULT 'Staff'");
$db->query("ALTER TABLE {$p}company_tickets ADD assignee_id INT NOT NULL DEFAULT 51");$db->query("ALTER TABLE {$p}company_tickets ADD created_at DATETIME NOT NULL DEFAULT '2026-10-01 12:00:00'");
$db->query("CREATE TABLE {$p}company_ticket_replies(id INT PRIMARY KEY,issuer_id INT NOT NULL,ticket_id INT NOT NULL,actor_id INT NOT NULL,created_at DATETIME NOT NULL)");
$db->query("INSERT INTO {$p}company_ticket_replies VALUES(1,?,1,7,'2026-10-01 12:02:00'),(2,?,2,7,'2026-10-01 18:00:00'),(3,?,1,0,'2026-10-01 12:01:00')",[$a,$b,$a]);
$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,51,'reports','view')",[$a]);unset($db->data_cache['table_names']);
$reader=new CompanyDashboardPresenter($db,$p,$a,51);$rows=$reader->ticketReport('this_month','2026-10-25');$by=[];foreach($rows as $row)$by[$row->staffid]=$row;
essentialCheck(count($rows)===2&&$by[51]->total_assigned===1&&$by[51]->total_open_tickets===1,'only owned assigned ticket counted');
essentialCheck($by[7]->total_replies===1,'other company and contact replies excluded from staff counts');
essentialCheck($by[51]->average_reply_time==='2 min','first staff reply used, not contact or unrelated issuer');
essentialThrows(fn()=>$reader->ticketReport('this_month OR 1=1','2026-10-25'),'company_dashboard_period_invalid','period finite allowlist');
// Principal combines only demonstrable legacy ownership and its own namespace.
$db->query("CREATE TABLE {$p}contacts(id INT PRIMARY KEY,userid INT NOT NULL)");
$db->query("CREATE TABLE {$p}tickets(ticketid INT PRIMARY KEY,userid INT NOT NULL,contactid INT NOT NULL,status INT NOT NULL,assigned INT NOT NULL,date DATETIME NOT NULL)");
$db->query("CREATE TABLE {$p}ticket_replies(id INT PRIMARY KEY,ticketid INT NOT NULL,admin INT NOT NULL,date DATETIME NOT NULL)");
$db->query("INSERT INTO {$p}clients VALUES(10,'Private A')");$db->query("INSERT INTO {$p}issuers_clients VALUES(10,?,'52998224725',7,'2026-10-25 12:00:00')",[$a]);
$db->query("INSERT INTO {$p}contacts VALUES(1,9),(2,10)");
$db->query("INSERT INTO {$p}tickets VALUES(1,9,1,1,51,'2026-10-01 12:00:00'),(2,10,2,5,51,'2026-10-01 12:00:00'),(3,9,2,1,51,'2026-10-01 12:00:00')");
$db->query("INSERT INTO {$p}ticket_replies VALUES(1,1,7,'2026-10-01 12:04:00'),(2,2,7,'2026-10-01 12:30:00'),(3,3,7,'2026-10-01 12:45:00')");
$db->query("INSERT INTO {$p}company_tickets VALUES(3,1,'open',51,'2026-10-01 12:00:00')");$db->query("INSERT INTO {$p}company_ticket_replies VALUES(4,1,3,7,'2026-10-01 12:02:00')");unset($db->data_cache['table_names']);
$primaryReader=new CompanyDashboardPresenter($db,$p,1,7);$primaryRows=$primaryReader->ticketReport('this_month','2026-10-25');$primaryBy=[];foreach($primaryRows as $row)$primaryBy[$row->staffid]=$row;
essentialCheck($primaryBy[51]->total_assigned===2&&$primaryBy[51]->total_open_tickets===2&&$primaryBy[51]->total_closed_tickets===0,'principal combines own and boundary-checked legacy, rejects foreign customer/contact');
essentialCheck($primaryBy[7]->total_replies===2&&$primaryBy[51]->average_reply_time==='3 min','legacy and owned response durations/replies aggregate without overwrite');
$again=$reader->ticketReport('this_month','2026-10-25');essentialCheck(count($again)===2&&$again[0]->total_replies===1,'secondary never receives legacy report rows');
$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=? AND staff_id=51 AND feature='reports'",[$a]);
essentialThrows(fn()=>$reader->confirm(),'company_dashboard_stale','report grant revoked before rendering invalidates output');
essentialThrows(fn()=>(new CompanyDashboardPresenter($db,$p,$a,51))->ticketReport('this_month','2026-10-25'),'company_dashboard_denied','report requires explicit reports and tickets permissions');
echo "Native dashboard tickets report: {$GLOBALS['essentialChecks']} cumulative checks passed.\n";
