<?php
/** Prior production schemas and representative rows for the actual core489 MySQL migration. */
require_once APPPATH.'services/CompanyApiSchema.php';
require_once APPPATH.'services/CompanyLeadsSchema.php';
require_once APPPATH.'services/CompanyContractsSchema.php';
require_once APPPATH.'services/CompanyExpenseSchema.php';
CompanyApiSchema::ensure($db,$p);
CompanyLeadsSchema::ensure($db,$p);
CompanyContractsSchema::ensure($db,$p);
CompanyExpenseSchema::ensure($db,$p);
unset($db->data_cache['table_names']);

$r8MigrationTime='2026-11-05 12:00:00';
$db->query('INSERT INTO '.$p.'company_lead_stages(issuer_id,name,category,position,active,revision,created_at,updated_at) VALUES(?,?,?,1,1,1,?,?)',[$a,'Preserved migration stage','open',$r8MigrationTime,$r8MigrationTime]);
$r8MigrationStage=(int)$db->insert_id();
$db->query('INSERT INTO '.$p.'company_lead_sources(issuer_id,name,active,revision,created_at,updated_at) VALUES(?,?,1,1,?,?)',[$a,'Preserved migration source',$r8MigrationTime,$r8MigrationTime]);
$r8MigrationSource=(int)$db->insert_id();
$db->query('INSERT INTO '.$p.'company_leads(issuer_id,created_by,assignee_id,name,company,email,phone,vat,description,stage_id,source_id,notify_assignee,reminder_at,reminder_timezone,archived,converted_client_id,converted_contact_id,converted_at,revision,created_at,updated_at) VALUES(?,7,7,?,?,?,?,?,?,?, ?,0,NULL,?,0,NULL,NULL,NULL,1,?,?)',[$a,'Preserved migration lead','Synthetic company','lead@example.invalid','','','Preserved lead description',$r8MigrationStage,$r8MigrationSource,'UTC',$r8MigrationTime,$r8MigrationTime]);
$r8MigrationLead=(int)$db->insert_id();
$db->query('INSERT INTO '.$p.'company_contracts(issuer_id,client_id,number,name,body,status,start_date,due_date,snapshot_json,revision,created_at,updated_at) VALUES(?,?,NULL,?,?,?,?,?,?,1,?,?)',[$a,$ca,'Preserved migration contract','Existing contract content','draft','2026-11-05','2026-12-05','{}',$r8MigrationTime,$r8MigrationTime]);
$r8MigrationContract=(int)$db->insert_id();
$db->query('INSERT INTO '.$p.'company_expense_categories(issuer_id,name,active,revision,created_at) VALUES(?,?,1,1,?)',[$a,'Preserved migration category',$r8MigrationTime]);
$r8MigrationCategory=(int)$db->insert_id();

$r8MigrationParents=static function()use($db,$p):array{
    $rows=[];
    foreach(['company_leads','company_lead_stages','company_lead_sources','company_contracts'] as $table)
        $rows[$table]=$db->query('SELECT * FROM '.$p.$table.' ORDER BY id')->result_array();
    // Compare the exact pre489 shape; the new optional column must remain NULL.
    $rows['company_expense_categories']=$db->query('SELECT id,issuer_id,name,active,revision,created_at FROM '.$p.'company_expense_categories ORDER BY id')->result_array();
    return $rows;
};
