<?php
/** Native additive migration, exact metadata, immutable originals and replay on InnoDB. */
require __DIR__.'/native_425.php';
require_once APPPATH.'migrations/426_version_426.php';
$existingUnits=$db->query('SELECT * FROM '.$p.'company_currency_units ORDER BY currency_id')->result_array();
$existingEvents=$db->query('SELECT * FROM '.$p.'company_financial_events ORDER BY evidence_key')->result_array();
$m426=new Migration_Version_426();$m426->db=$db;$m426->up();$m426->up();
foreach(['CompanySalesExtrasSchema','CompanyImportSchema','CompanyCurrencyUnitSchema','CompanyFinancialJournalSchema'] as $schema){$schema::assertValid($db,$p);essentialCheck(true,$schema.' native426 exact metadata after replay');}
foreach($existingUnits as $row)essentialCheck($db->query('SELECT * FROM '.$p.'company_currency_units WHERE currency_id=?',[$row['currency_id']])->row_array()===$row,'426 replay preserves captured currency meaning and provenance');
foreach($existingEvents as $row)essentialCheck($db->query('SELECT * FROM '.$p.'company_financial_events WHERE evidence_key=?',[$row['evidence_key']])->row_array()===$row,'426 never overwrites previously recorded financial evidence');
essentialCheck($db->query('SELECT * FROM '.$p.'invoices WHERE id=42')->result_array()===$legacyBefore,'426 preserves native NULL invoice values');
essentialCheck($db->query('SELECT issuer_id FROM '.$p.'creditnotes WHERE id=4200')->row_array()['issuer_id']===null,'426 never adopts native NULL credit');
$first=$db->query('SELECT * FROM '.$p.'company_financial_events ORDER BY evidence_key')->result_array();$m426->up();essentialCheck($first===$db->query('SELECT * FROM '.$p.'company_financial_events ORDER BY evidence_key')->result_array(),'426 baseline replay adds no duplicate observations');
echo 'Native426 metadata/replay/legacy: '.$GLOBALS['essentialChecks'].' cumulative checks passed.'.PHP_EOL;
