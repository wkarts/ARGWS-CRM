<?php
require_once dirname(__DIR__).'/issuers_core_support.php';
require_once APPPATH.'services/CompanyInvoiceProjectSchema.php';
register_shutdown_function(static function(){foreach($GLOBALS['essentialFixtures']??[] as $fixture)if($fixture->db->driver()==='mysql')$fixture->db->query('DROP TABLE IF EXISTS '.$fixture->prefix.CompanyInvoiceProjectSchema::TABLE);});
require __DIR__.'/invoices.php';
$before=$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array();
CompanyInvoiceProjectSchema::ensure($db,$p);CompanyInvoiceProjectSchema::ensure($db,$p);
essentialCheck($db->table_exists($p.CompanyInvoiceProjectSchema::TABLE),'new additive relation table exists after replay');
essentialCheck($db->query('SELECT * FROM '.$p.CompanyInvoiceProjectSchema::TABLE)->result_array()===[],'migration does not infer project links from coincident IDs');
essentialCheck($db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()===$before,'schema bootstrap never adopts or rewrites native invoice rows');
$db->trans_begin();
try{essentialThrows(fn()=>CompanyInvoiceProjectSchema::ensure($db,$p),'issuer_outer_transaction_not_allowed','DDL refuses caller transactions');}
finally{$db->trans_rollback();}
$db->query('ALTER TABLE '.$p.CompanyInvoiceProjectSchema::TABLE.' ADD unwanted int');
essentialThrows(fn()=>CompanyInvoiceProjectSchema::assertValid($db,$p),'issuer_invoice_project_schema_invalid','unexpected schema field fails closed');
$db->query('DROP TABLE '.$p.CompanyInvoiceProjectSchema::TABLE);
CompanyInvoiceProjectSchema::ensure($db,$p);
echo 'Native invoice project additive schema: '.$GLOBALS['essentialChecks'].' cumulative checks PASS ('.$db->driver().").\n";
