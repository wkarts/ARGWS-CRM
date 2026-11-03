<?php
// Shared synthetic ticket setup for existing task writer/form composition; not a MySQL migration proof.
require_once APPPATH.'services/NativeCompanyTicketRead.php';
// The dashboard now calls the real authority-aware ticket reader, not a three-column status stub.
require_once APPPATH.'services/CompanyContractsSchema.php';
$GLOBALS['dashboardTicketFixtureTables']=[];$GLOBALS['dashboardTicketFixtureDb']=$db;
$createTicketFixtureTable=static function(string$table,array$parts)use($db,$p):void{
    if($db->driver()==='sqlite'){$parts=array_values(array_filter($parts,static fn($part)=>str_starts_with($part,'`')));$parts=array_map(static fn($part)=>preg_replace('/ CHARACTER SET [a-z0-9_]+ COLLATE [a-z0-9_]+/','',$part),$parts);}
    else foreach($parts as $index=>$part)if(str_starts_with($part,'FOREIGN KEY ')){
        // Synthetic prefixes can make InnoDB's generated names exceed 64 bytes.
        // Explicit unique test names do not alter the referenced columns/rules.
        $parts[$index]='CONSTRAINT `fixture_fk_'.substr(hash('sha256',$p.$table.':'.$index),0,20).'` '.$part;
    }
    if($db->table_exists($p.$table))return;
    $db->query('CREATE TABLE `'.$p.$table.'` ('.implode(',',$parts).') ENGINE=InnoDB');$GLOBALS['dashboardTicketFixtureTables'][]=$p.$table;
};
foreach(['tickets'=>['`ticketid` int NOT NULL PRIMARY KEY','`userid` int NOT NULL','`contactid` int NOT NULL','`status` int NOT NULL','`merged_ticket_id` int NULL'],'ticket_replies'=>['`id` int NOT NULL PRIMARY KEY'],'contacts'=>['`id` int NOT NULL PRIMARY KEY','`userid` int NOT NULL'],'projects'=>['`id` int NOT NULL PRIMARY KEY','`clientid` int NOT NULL','`status` int NOT NULL'],'departments'=>['`departmentid` int NOT NULL PRIMARY KEY'],'staff_departments'=>['`staffid` int NOT NULL','`departmentid` int NOT NULL'],'tickets_status'=>['`ticketstatusid` int NOT NULL PRIMARY KEY'],'tickets_priorities'=>['`priorityid` int NOT NULL PRIMARY KEY']]as$table=>$parts)$createTicketFixtureTable($table,$parts);
$defs=CompanyContractsSchema::definitions($p);foreach(['company_tickets','company_ticket_replies']as$table)$createTicketFixtureTable($table,$defs[$table]);
foreach(array_merge(NativeCompanyDepartmentSchema::definitions($p),NativeCompanyTicketCatalogSchema::definitions($p),NativeCompanyTicketReplySchema::definitions($p))as$table=>$parts)$createTicketFixtureTable($table,$parts);
if(!$db->field_exists('is_not_staff',$p.'staff'))$db->query("ALTER TABLE {$p}staff ADD is_not_staff INT NOT NULL DEFAULT 0");
foreach([[$a,$ca,'open'],[$b,$cb,'closed']]as[$issuer,$client,$state]){
    $db->query("INSERT INTO {$p}company_tickets(id,issuer_id,client_id,assignee_id,subject,body,status,priority,revision,created_at,updated_at) VALUES(?,?,?,51,'Synthetic ticket','Historical body',?,'normal',1,'2026-10-25 00:00:00','2026-10-25 00:00:00')",[$issuer===$a?1:2,$issuer,$client,$state]);
    foreach([['status','answered','Answered'],['priority','medium','Medium']]as[$kind,$code,$label])$db->query("INSERT INTO {$p}company_ticket_catalog(issuer_id,kind,code,name,message,color,sort_order,is_default,revision,actor_id,created_at,updated_at) VALUES(?,?,?,?,'','#123456',1,1,1,7,'2026-10-25 00:00:00','2026-10-25 00:00:00')",[$issuer,$kind,$code,$label]);
    // Explicit test policy: these historical, unassigned-department tickets are visible to authorized company staff.
    $document=json_decode($db->query("SELECT settings_json FROM {$p}issuers_registry WHERE id=?",[$issuer])->row_array()['settings_json'],true,512,JSON_THROW_ON_ERROR);$document['company_options']['staff_access_only_assigned_departments']='0';$db->query("UPDATE {$p}issuers_registry SET settings_json=? WHERE id=?",[json_encode($document,JSON_THROW_ON_ERROR),$issuer]);
}

unset($db->data_cache["table_names"]);
