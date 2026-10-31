<?php
/** Reconstructed prerequisite for the byte-preserved440 packet tests; original439 prerequisite was unavailable. */
declare(strict_types=1);
register_shutdown_function(static function():void{if(!isset($GLOBALS['db'],$GLOBALS['p']))return;foreach(['company_invoice_schedule_discovery','company_invoice_schedule_drafts','company_invoice_schedule_modes','company_invoice_draft_line_transfers','company_invoice_draft_lines','company_invoice_draft_projection','company_invoice_draft_events','company_invoice_drafts','company_invoice_copy_requests','company_invoice_schedule_requests','company_invoice_schedule_runs','company_invoice_schedules']as$table)$GLOBALS['db']->query('DROP TABLE IF EXISTS '.$GLOBALS['p'].$table);unset($GLOBALS['db']->data_cache['table_names']);});
require __DIR__.'/invoice_delivery.php';
require_once APPPATH.'services/CompanyInvoiceRecurring.php';
if($db->driver()==='mysql'){CompanyInvoiceRecurringSchema::ensure($db,$p);CompanyInvoiceRecurringSchema::ensure($db,$p);}else{
 foreach(CompanyInvoiceRecurringSchema::definitions()as$table=>$fields){$parts=[];foreach($fields as$name=>$type){$null=str_ends_with($type,' NULL');$type=str_replace(' NULL','',$type);$type=str_contains($type,'AUTO_INCREMENT')?'INTEGER PRIMARY KEY AUTOINCREMENT':$type;$parts[]='`'.$name.'` '.$type.($null?' NULL':' NOT NULL');}foreach(CompanyInvoiceRecurringSchema::indexes($table)as$name=>[$cols,$non]){if($name==='PRIMARY'&&$table==='company_invoice_schedules'||$non)continue;$parts[]=($name==='PRIMARY'?'PRIMARY KEY':'UNIQUE').'('.implode(',',$cols).')';}$db->query('CREATE TABLE '.$p.$table.'('.implode(',',$parts).')');}
}
unset($db->data_cache['table_names']);
require_once APPPATH.'services/CompanyInvoiceRecurringDiscoverySchema.php';CompanyInvoiceRecurringDiscoverySchema::ensure($db,$p);CompanyInvoiceRecurringDiscoverySchema::ensure($db,$p);
$ir=new CompanyInvoiceRecurring($db,$p);$monthInput=['client_id'=>$ca,'currency'=>1,'date'=>'2026-01-01','due_date'=>'2026-01-10','items'=>[['description'=>'Recovered recurrence source','qty'=>'1.00','rate'=>'10.10']],'note'=>'Synthetic recovered440 prerequisite'];$ispec=['unit'=>'month','interval'=>1,'custom'=>false,'cycles'=>2,'send'=>false,'contact_id'=>0,'base_url'=>''];$legacyInvoice=$legacyBefore;
$principalBilling=(new IssuerResourceAccess($db,$p))->primaryId();$settings->saveIdentity($principalBilling,7,['legal_name'=>'Synthetic principal billing','tax_id'=>'12345678909','profile'=>['invoice_company_address'=>'Principal address','invoice_company_city'=>'Synthetic city','company_state'=>'BA','invoice_company_country_code'=>'BR','invoice_company_postal_code'=>'00000-000','invoice_company_phonenumber'=>'']]);$pc=$customers->create($principalBilling,7,['company'=>'Private principal recurrence recipient']);
$db->query("INSERT INTO {$p}contacts(id,userid,firstname,lastname,email,phonenumber,datecreated,active) VALUES(301,?,'Recurring','Contact','recurring@example.invalid','',?,1)",[$ca,gmdate('Y-m-d H:i:s')]);$db->query("INSERT INTO {$p}contact_permissions(userid,permission_id) VALUES(301,1)");$decryptRecurring=$decrypt;
// A genuine pre440 schedule is not backfilled into the new choice tables.
$oldSchedule=$ir->create($a,7,$monthInput,$ispec,hash('sha256','reconstructed-pre440-schedule'));
essentialCheck($ir->run($a,7,$oldSchedule,'2026-01-01')>0,'pre440 source emits using historical unqualified contract');
$oldDetail=$ir->document($a,7,$oldSchedule);$ir->control($a,7,$oldSchedule,$oldDetail['revision'],'cancel',hash('sha256','reconstructed-pre440-cancel'));
echo 'Reconstructed prerequisite for original440 recurring-mode packet: '.$GLOBALS['essentialChecks'].' cumulative ('.$db->driver().'). Native migration tracking is separate.'.PHP_EOL;
