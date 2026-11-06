<?php
/** Actual owned schedule readers/calculators and DataTables query, using disposable SQL fixtures. */
require __DIR__.'/native_invoice_calculation_recurring.php';
require_once APPPATH.'services/NativeCompanyInvoiceRecurringDirectory.php';
$directory=new NativeCompanyInvoiceRecurringDirectory($db,$p);
$columns=[];for($column=0;$column<8;$column++)$columns[]=['data'=>$column,'name'=>'','searchable'=>'true','orderable'=>'true','search'=>['value'=>'','regex'=>'false']];
$wire=['draw'=>'18','start'=>'0','length'=>'-1','search'=>['value'=>'','regex'=>'false'],'columns'=>$columns,'order'=>[['column'=>'0','dir'=>'asc']],'last_order_identifier'=>'recurring-invoices'];
$before=$directory->table($a,7,$wire);
essentialCheck($before['iTotalRecords']===3&&count($before['rows'])===3,'complete A directory includes cancelled historical and new native-calculation schedules');
$byId=array_column($before['rows'],null,'id');
essentialCheck($byId[$id]['total']==='99.00'&&$byId[$zeroId]['total']==='0.00','table uses each real immutable calculation contract, including zero lines');
essentialCheck($byId[$id]['last_date']==='2026-01-01'&&$byId[$id]['next_date']==='2026-02-01'&&$byId[$id]['cycles_remaining']===1,'last confirmed invoice, next anchored date and remaining cycles come from canonical runs');
essentialCheck($byId[$oldSchedule]['next_date']===''&&$byId[$oldSchedule]['state']==='cancelled','cancelled schedule does not promise another invoice date');
$ownB=$ir->create($b,7,array_replace($monthInput,['client_id'=>$cb]),$ispec,hash('sha256','directory-recurring-b'));
$pageB=$directory->table($b,7,$wire);
essentialCheck(array_column($pageB['rows'],'id')===[$ownB],'company B sees its actual schedule and none of A');
essentialCheck($directory->table($principalBilling,7,$wire)['iTotalRecords']===0,'principal directory does not adopt NULL legacy invoices or foreign schedules');
$future=$ir->create($a,7,array_replace($monthInput,['date'=>'2027-01-31','due_date'=>'2027-02-10']),array_replace($ispec,['cycles'=>0]),hash('sha256','directory-recurring-future'));
$year=$directory->table($a,7,$wire+['year_2027'=>'2027']);
essentialCheck(array_column($year['rows'],'id')===[$future]&&$year['rows'][0]['cycles_remaining']===null,'original year filter and actual unlimited schedule remain explicit');
$search=$wire;$search['search']['value']='Série #'.$future;
essentialCheck(array_column($directory->table($a,7,$search)['rows'],'id')===[$future],'literal search addresses schedule ID without pretending it is an invoice number');
essentialThrows(fn()=>$directory->table($a,7,$wire+['issuer_id'=>$b]),'native_company_table_request_invalid','payload cannot override the active company');
essentialThrows(fn()=>$directory->table($a,7,$wire+['sale_agent_999999'=>'999999']),'native_company_table_request_invalid','unavailable foreign sales-agent filter is rejected');
$bad=$wire;$bad['order'][0]['column']='8';essentialThrows(fn()=>$directory->table($a,7,$bad),'native_company_table_request_invalid','recurring table has eight exact columns, separate from invoice table');
$search['search']['value']='%';essentialCheck($directory->table($a,7,$search)['iTotalDisplayRecords']===0,'SQL wildcard characters are literal search text');
$catalogue=$directory->catalogue($a,7);essentialCheck(array_column($catalogue['invoices_years'],'year')===['2027','2026'],'catalogue derives only real owned years');
$saved=$db->query('SELECT payload_hash FROM '.$p.'company_invoice_schedules WHERE id=?',[$future])->row()->payload_hash;
$db->query('UPDATE '.$p.'company_invoice_schedules SET payload_hash=? WHERE id=?',[str_repeat('0',64),$future]);
try{essentialThrows(fn()=>$directory->table($a,7,$wire),'issuer_invoice_recurring_corrupt','table does not bypass the canonical schedule integrity reader');}
finally{$db->query('UPDATE '.$p.'company_invoice_schedules SET payload_hash=? WHERE id=?',[$saved,$future]);}
// Exercise the removed 100-row list ceiling using real creation/audit records, not fabricated database rows.
for($n=0;$n<98;$n++)$ir->create($a,7,$monthInput,$ispec,hash('sha256','directory-recurring-many-'.$n));
$all=$directory->table($a,7,$wire);
essentialCheck($all['iTotalRecords']===102&&count($all['rows'])===102,'ALL returns all 102 genuine schedules, without the historical summary ceiling');
$paged=$wire;$paged['length']='25';$paged['start']='100';$last=$directory->table($a,7,$paged);
essentialCheck($last['iTotalRecords']===102&&count($last['rows'])===2,'stable paging retains complete totals beyond 100 records');
echo 'Native recurring directory/filters/calculation/integrity: '.$GLOBALS['essentialChecks'].' cumulative PASS ('.$db->driver()."). HTTP/browser remains separate.\n";
