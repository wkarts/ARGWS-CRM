<?php
require __DIR__.'/native_invoice_issue_commands.php';
$none=$draftInput;$none['due_date']='';$noneKey=hash('sha256','native-manual-no-due-date');$result=$commands->createIssued($a,12,$none,$metadata,$noneKey);
$row=$db->query('SELECT date,duedate,total FROM '.$p.'invoices WHERE id=?',[$result['invoice_id']])->row_array();
essentialCheck($row['duedate']===null&&$row['date']===$none['date'],'native manual empty due date persists NULL without inventing a date');
$none['due_date']=null;essentialCheck($commands->createIssued($a,12,$none,$metadata,$noneKey)===$result,'blank HTML date and explicit NULL replay the same canonical request');
essentialCheck((new CompanyInvoices($db,$p))->document($a,12,$result['invoice_id'])->data()['due_date']===null,'native document DTO preserves no due date');
foreach(['0000-00-00','2026-02-30','yesterday',false,[],0]as$invalid){$bad=$none;$bad['due_date']=$invalid;essentialThrows(fn()=>$commands->createIssued($a,12,$bad,$metadata,hash('sha256',serialize($invalid))),'company_invoice_copy_invalid','non-empty malformed due date is not silently erased');}
$draft=$commands->createDraft($a,12,$none,$metadata,hash('sha256','native-draft-no-due-date'));
$projection=$db->query('SELECT duedate FROM '.$p.NativeCompanyInvoiceDraftProjectionSchema::TABLE.' WHERE id=?',[$draft['draft_id']])->row_array();essentialCheck($projection['duedate']===null,'draft native table projection preserves NULL due date');
$draftWriter=new CompanyInvoiceDrafts($db,$p);$issued=$draftWriter->issue($a,12,$draft['draft_id'],1,hash('sha256','native-draft-no-due-date-issue'));
essentialCheck((new CompanyInvoices($db,$p))->document($a,12,$issued)->data()['due_date']===null,'private draft issues canonically without inventing due date');
essentialCheck($draftWriter->issue($a,12,$draft['draft_id'],1,hash('sha256','native-draft-no-due-date-issue'))===$issued,'NULL due date survives exact issue replay');
NativeCompanyInvoiceDraftProjectionSchema::ensure($db,$p);(new NativeCompanyInvoiceDraftProjection($db,$p))->backfill();
essentialCheck($db->query('SELECT duedate FROM '.$p.NativeCompanyInvoiceDraftProjectionSchema::TABLE.' WHERE id=?',[$draft['draft_id']])->row_array()['duedate']===null,'projection schema/backfill replay keeps no-due-date semantics');
require_once APPPATH.'services/CompanyInvoiceRecurring.php';
essentialThrows(fn()=>(new CompanyInvoiceRecurring($db,$p))->create($a,12,$none,['unit'=>'month','interval'=>1],hash('sha256','recurrence-no-due-date')),'company_sales_invalid','automatic recurrence still requires explicit valid date policy');
echo 'Native optional invoice due date: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
