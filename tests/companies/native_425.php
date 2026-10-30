<?php
/** Exact native core schemas on a native old-data fixture, including idempotent replay. */
require __DIR__.'/credits_operations.php';
if($db->driver()!=='mysql'){echo "Native425 engine proof requires MySQL/MariaDB.\n";exit(2);}
require_once APPPATH.'migrations/425_version_425.php';$native425=new Migration_Version_425();$native425->db=$db;$native425->up();$native425->up();
foreach(['CompanyContractsSchema','CompanySalesSchema','CompanyApiSchema','CompanyProjectExtrasSchema','CompanyStandaloneTasksSchema'] as $schema){$schema::assertValid($db,$p);essentialCheck(true,$schema.' exact schema after native425 replay');}
essentialCheck($db->query("SELECT * FROM {$p}invoices WHERE id=42")->result_array()===$legacyBefore,'native425 preserves original NULL legacy invoice byte values');
essentialCheck($db->query("SELECT issuer_id FROM {$p}creditnotes WHERE id=4200")->row_array()['issuer_id']===null,'native425 never adopts legacy credit ownership');
echo 'Native425 replay/metadata/legacy: '.$GLOBALS['essentialChecks'].' cumulative checks passed.'.PHP_EOL;
