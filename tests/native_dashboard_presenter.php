<?php
/** Real PDO/IssuerResourceAccess, disposable rows. No gateways, mail, hooks or browser session. */
require __DIR__.'/issuers_core_support.php';
require_once APPPATH.'services/issuers/IssuerRegistry.php';
require_once APPPATH.'services/CompanyDashboardPresenter.php';
register_shutdown_function(static function():void{foreach(array_reverse($GLOBALS['dashboardTicketFixtureTables']??[])as$table){$GLOBALS['dashboardTicketFixtureDb']->query('DROP TABLE IF EXISTS '.$table);}essentialCleanup();});
$f=essentialFixture('1.0.2',1);$db=$f->db;$p=$f->prefix;
IssuerCore::reconcile($db,$p);IssuerCore::bindInstallationMaster($db,$p,7);
$r=new IssuerRegistry($db,$p);
$a=$r->saveIssuer(7,['legal_name'=>'Dashboard A','tax_id'=>'11222333000181']);
$b=$r->saveIssuer(7,['legal_name'=>'Dashboard B','tax_id'=>'52998224725']);
$db->query("INSERT INTO {$p}staff(staffid,active,admin) VALUES(51,1,0),(52,1,0)");
foreach([$a,$b] as $issuer){
 $db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(?,51,1)",[$issuer]);
 foreach(['invoices','projects','tasks','tickets','leads'] as $feature)$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,51,?,'view')",[$issuer,$feature]);
}
$db->query("ALTER TABLE {$p}invoices ADD status INT NOT NULL DEFAULT 1");
foreach(['company_projects','company_tasks','company_standalone_tasks'] as $table)$db->query("CREATE TABLE {$p}{$table}(id INT PRIMARY KEY,issuer_id INT NOT NULL,status VARCHAR(20) NOT NULL)");
$db->query("CREATE TABLE {$p}company_lead_stages(id INT PRIMARY KEY,issuer_id INT NOT NULL,name VARCHAR(100) NOT NULL)");
$db->query("CREATE TABLE {$p}company_leads(id INT PRIMARY KEY,issuer_id INT NOT NULL,stage_id INT NOT NULL,archived INT NOT NULL,converted_client_id INT NULL)");
$db->query("INSERT INTO {$p}invoices(id,issuer_id,status) VALUES(101,?,1),(102,?,2),(103,?,1),(104,?,1)",[$a,$a,$b,$b]);
$db->query("INSERT INTO {$p}company_projects VALUES(1,?,'active'),(2,?,'completed'),(3,?,'active')",[$a,$a,$b]);
$db->query("INSERT INTO {$p}company_tasks VALUES(1,?,'todo'),(2,?,'done')",[$a,$b]);
$db->query("INSERT INTO {$p}company_standalone_tasks VALUES(1,?,'in_progress'),(2,?,'done')",[$a,$b]);
// The dashboard now calls the real authority-aware ticket reader, not a three-column status stub.
require_once APPPATH.'services/CompanyContractsSchema.php';
$GLOBALS['dashboardTicketFixtureTables']=[];$GLOBALS['dashboardTicketFixtureDb']=$db;
$createTicketFixtureTable=static function(string$table,array$parts)use($db,$p):void{
    if($db->driver()==='sqlite'){$parts=array_values(array_filter($parts,static fn($part)=>str_starts_with($part,'`')));$parts=array_map(static fn($part)=>preg_replace('/ CHARACTER SET [a-z0-9_]+ COLLATE [a-z0-9_]+/','',$part),$parts);}
    $db->query('CREATE TABLE `'.$p.$table.'` ('.implode(',',$parts).') ENGINE=InnoDB');$GLOBALS['dashboardTicketFixtureTables'][]=$p.$table;
};
foreach(['tickets'=>['`ticketid` int NOT NULL PRIMARY KEY','`userid` int NOT NULL','`contactid` int NOT NULL','`status` int NOT NULL','`merged_ticket_id` int NULL'],'ticket_replies'=>['`id` int NOT NULL PRIMARY KEY'],'contacts'=>['`id` int NOT NULL PRIMARY KEY','`userid` int NOT NULL'],'projects'=>['`id` int NOT NULL PRIMARY KEY','`clientid` int NOT NULL','`status` int NOT NULL'],'departments'=>['`departmentid` int NOT NULL PRIMARY KEY'],'staff_departments'=>['`staffid` int NOT NULL','`departmentid` int NOT NULL'],'tickets_status'=>['`ticketstatusid` int NOT NULL PRIMARY KEY'],'tickets_priorities'=>['`priorityid` int NOT NULL PRIMARY KEY']]as$table=>$parts)$createTicketFixtureTable($table,$parts);
$defs=CompanyContractsSchema::definitions($p);foreach(['company_tickets','company_ticket_replies']as$table)$createTicketFixtureTable($table,$defs[$table]);
foreach(array_merge(NativeCompanyDepartmentSchema::definitions($p),NativeCompanyTicketCatalogSchema::definitions($p),NativeCompanyTicketReplySchema::definitions($p))as$table=>$parts)$createTicketFixtureTable($table,$parts);
$db->query("ALTER TABLE {$p}staff ADD is_not_staff INT NOT NULL DEFAULT 0");
foreach([[$a,11,'open'],[$b,12,'closed']]as[$issuer,$client,$state]){
    $db->query("INSERT INTO {$p}clients(userid,company) VALUES(?,?)",[$client,'Synthetic ticket client']);$db->query("INSERT INTO {$p}issuers_clients(client_id,owner_issuer_id,created_by,created_at) VALUES(?,?,7,'2026-10-25 00:00:00')",[$client,$issuer]);
    $db->query("INSERT INTO {$p}company_tickets(id,issuer_id,client_id,assignee_id,subject,body,status,priority,revision,created_at,updated_at) VALUES(?,?,?,51,'Synthetic ticket','Historical body',?,'normal',1,'2026-10-25 00:00:00','2026-10-25 00:00:00')",[$issuer===$a?1:2,$issuer,$client,$state]);
    foreach([['status','answered','Answered'],['priority','medium','Medium']]as[$kind,$code,$label])$db->query("INSERT INTO {$p}company_ticket_catalog(issuer_id,kind,code,name,message,color,sort_order,is_default,revision,actor_id,created_at,updated_at) VALUES(?,?,?,?,'','#123456',1,1,1,7,'2026-10-25 00:00:00','2026-10-25 00:00:00')",[$issuer,$kind,$code,$label]);
    // Explicit test policy: these historical, unassigned-department tickets are visible to authorized company staff.
    $document=json_decode($db->query("SELECT settings_json FROM {$p}issuers_registry WHERE id=?",[$issuer])->row_array()['settings_json'],true,512,JSON_THROW_ON_ERROR);$document['company_options']['staff_access_only_assigned_departments']='0';$db->query("UPDATE {$p}issuers_registry SET settings_json=? WHERE id=?",[json_encode($document,JSON_THROW_ON_ERROR),$issuer]);
}
$db->query("INSERT INTO {$p}company_lead_stages VALUES(1,?,'Stage A'),(2,?,'Stage B')",[$a,$b]);
$db->query("INSERT INTO {$p}company_leads VALUES(1,?,1,0,9),(2,?,2,0,NULL)",[$a,$b]);
unset($db->data_cache['table_names']);
$pa=new CompanyDashboardPresenter($db,$p,$a,51);$pb=new CompanyDashboardPresenter($db,$p,$b,51);
$before=essentialLegacy($f);
$da=$pa->counters('2026-10-25');$dbb=$pb->counters('2026-10-25');
essentialCheck($da['widgets']['invoices']['data'][1]===1&&$dbb['widgets']['invoices']['data'][1]===2,'invoice counts do not leak A/B/legacy row');
essentialCheck($da['widgets']['projects']['data']['completed']===1&&$dbb['widgets']['projects']['data']['completed']===0,'project status per issuer');
essentialCheck($da['widgets']['tasks']['data']['unfinished']===2&&$dbb['widgets']['tasks']['data']['unfinished']===0,'project and standalone tasks use actual owned counts');
essentialCheck($da['widgets']['tickets']['data']['open']===1&&($dbb['widgets']['tickets']['data']['open']??0)===0&&$dbb['widgets']['tickets']['data']['closed']===1,'tickets scoped');
essentialCheck($da['widgets']['leads']['data']['stages'][0]['name']==='Stage A'&&$dbb['widgets']['leads']['data']['stages'][0]['name']==='Stage B','lead labels and counts scoped');
essentialCheck($da['permissions']['reports']===false&&$da['permissions']['payments']===false,'financial permission absence remains denied');
essentialCheck($da['permissions']['calendar']===false,'calendar unauthorized is not global events');
essentialCheck(essentialLegacy($f)===$before,'projection never mutates legacy data');
essentialThrows(fn()=>(new CompanyDashboardPresenter($db,$p,$a,52))->counters(),'company_dashboard_denied','foreign staff denied');
$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=? AND staff_id=51 AND feature='projects'",[$a]);
essentialThrows(fn()=>$pa->confirm(),'company_dashboard_stale','revocation after snapshot invalidates response');
$denied=(new CompanyDashboardPresenter($db,$p,$a,51))->counters('2026-10-25');
essentialCheck($denied['widgets']['projects']['data']===null&&$denied['widgets']['tasks']['data']['project']===null&&$denied['widgets']['tasks']['data']['total']===1,'standalone tasks remain visible while project task data is denied');
$db->query("UPDATE {$p}issuers_staff SET can_view=0 WHERE issuer_id=? AND staff_id=51",[$b]);
essentialThrows(fn()=>$pb->confirm(),'company_dashboard_denied','membership revoked before emission');
$db->query("DROP TABLE {$p}company_lead_stages");unset($db->data_cache['table_names']);
essentialThrows(fn()=>(new CompanyDashboardPresenter($db,$p,$a,51))->counters('2026-10-25'),'company_dashboard_schema_unavailable','missing table does not produce empty chart');
echo 'Company dashboard real read model: '.$GLOBALS['essentialChecks']." checks passed.\n";
