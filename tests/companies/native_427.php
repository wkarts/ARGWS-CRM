<?php
/** Native427 retains original invoices, cash and immutable evidence across additive replay. */
require __DIR__.'/native_426.php';require_once APPPATH.'migrations/427_version_427.php';
$oldPayments=$db->query('SELECT * FROM '.$p.'invoicepaymentrecords ORDER BY id')->result_array();
$oldEvents=$db->query('SELECT * FROM '.$p.'company_financial_events ORDER BY evidence_key')->result_array();
$m427=new Migration_Version_427();$m427->db=$db;$m427->up();$m427->up();
foreach(['CompanyOperationFilesSchema','CompanyBankComponentsSchema'] as $schema){$schema::assertValid($db,$p);essentialCheck(true,$schema.' exact native427 metadata after replay');}
essentialCheck($oldPayments===$db->query('SELECT * FROM '.$p.'invoicepaymentrecords ORDER BY id')->result_array(),'427 never rewrites existing native cash projections');
essentialCheck($oldEvents===$db->query('SELECT * FROM '.$p.'company_financial_events ORDER BY evidence_key')->result_array(),'427 preserves captured nominal journal byte values');
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_bank_receipt_components')->row()->n===0,'427 never invents bank components for old generic receipts');
essentialCheck($db->query('SELECT * FROM '.$p.'invoices WHERE id=42')->result_array()===$legacyBefore,'427 preserves original legacy NULL invoice');
echo 'Native427 additive metadata/replay/cash/legacy: '.$GLOBALS['essentialChecks'].' cumulative checks passed.'.PHP_EOL;
