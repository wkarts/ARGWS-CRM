<?php
require __DIR__.'/../issuers_core_support.php';
require_once APPPATH.'services/issuers/IssuerRegistry.php';
require_once APPPATH.'services/CompanyTaskPrioritySchema.php';
require_once APPPATH.'services/NativeCompanyTicketBootstrap.php';
register_shutdown_function('essentialCleanup');
$historical=essentialFixture('1.0.2',1);$db=$historical->db;$p=$historical->prefix;
essentialCheck((int)$db->query('SELECT version FROM '.$p.'migrations')->row_array()['version']===447,'Minimal synthetic fixture has explicit pre-priority marker, not a claimed installed 473');
essentialCheck(CompanyTaskPrioritySchema::tables($db,$p)===[],'Before448 boundary permits historical priority absence');
$db->query('UPDATE '.$p.'migrations SET version=448');essentialThrows(fn()=>CompanyTaskPrioritySchema::tables($db,$p),'issuer_task_priority_schema_invalid','448 marker never bypasses missing mandatory priority schema');
$current=essentialFixture('1.0.2',1,true,474);$db=$current->db;$p=$current->prefix;
essentialCheck((int)$db->query('SELECT version FROM '.$p.'migrations')->row_array()['version']===474,'Requested fixture version is explicit, not silently reset');
essentialThrows(fn()=>CompanyTaskPrioritySchema::tables($db,$p),'issuer_task_priority_schema_invalid','474 marker still requires historical priority sidecars; no fixture bypass');
echo 'Synthetic fixture persisted version boundaries: '.$GLOBALS['essentialChecks']." checks passed. Not a migration-chain test.\n";
