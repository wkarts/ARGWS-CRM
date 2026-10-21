<?php
/** Schema-only prerequisites of the complete native CLI as of454.
 * The expense fixture predates task schedules and the internal Leads inbox.
 * Install their real empty schemas; never mock or skip a worker to test costs.
 * Native migration/installer behavior has its own dedicated acceptance suites.
 */
if($db->driver()!=='mysql')throw new RuntimeException('The complete recurring CLI requires a real MySQL/MariaDB fixture.');
require_once APPPATH.'services/CompanyTaskRecurringSchema.php';
require_once APPPATH.'services/CompanyLeadsSchema.php';
require_once APPPATH.'services/CompanyApiSchema.php';
require_once APPPATH.'services/CompanyClientRequestSchema.php';
require_once APPPATH.'services/CompanyPortalSchema.php';
if(!$db->table_exists($p.'contacts')){
    $native=file_get_contents(dirname(__DIR__,2).'/install/database.sql');
    if(!preg_match('/CREATE TABLE `tblcontacts` \(.*?\) ENGINE=InnoDB[^;]*;/s',$native,$match))throw new RuntimeException('Native contacts fixture missing');
    $sql=str_replace(['`tblcontacts`','`id` int NOT NULL,'],['`'.$p.'contacts`','`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,'],$match[0]);
    $db->query($sql);
}
CompanyClientRequestSchema::ensure($db,$p);
CompanyPortalSchema::ensure($db,$p);
CompanyApiSchema::ensure($db,$p);
CompanyTaskRecurringSchema::ensure($db,$p);
CompanyLeadsSchema::ensure($db,$p);
unset($db->data_cache['table_names']);
$sharedCliBusinessTables=array_values(array_diff(array_merge(array_keys(CompanyTaskRecurringSchema::definitions($p)),array_keys(CompanyLeadsSchema::definitions($p))),['company_task_schedule_cursor','company_lead_notice_cursor']));
foreach($sharedCliBusinessTables as$table)essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.$table)->row()->n===0,'complete native CLI schema starts with no operational task/lead records '.$table);
