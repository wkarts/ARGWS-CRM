<?php
require __DIR__.'/invoice_recurring_modes.php';
require __DIR__.'/native_invoice_table_schema_fixture.php';
require_once APPPATH.'services/CompanyInvoiceCopies.php';
require_once APPPATH.'services/NativeCompanyInvoiceDraftCopy.php';
require_once APPPATH.'services/NativeCompanyInvoiceRecurringCopy.php';
NativeCompanyInvoiceDraftLinesSchema::ensure($db,$p);(new NativeCompanyInvoiceDraftLines($db,$p))->backfill();
$copies=new CompanyInvoiceCopies($db,$p);$source=$copies->preview($a,7,$main);$review=['source_revision'=>$source['revision'],'date'=>'2026-12-15','due_date'=>'2026-12-24','note'=>$source['note'],'confirm_amounts'=>'1'];
$writerCopy=new NativeCompanyInvoiceDraftCopy($db,$p);$key=hash('sha256','recurring-copy-source');$canonical=$writerCopy->copy($a,7,$main,$review,$key);$id=NativeCompanyResourceId::decode('invoice',$canonical)['source_id'];$draft=$drafts->document($a,7,$id);
essentialCheck(count($draft['source']['native_metadata']['recurring'])===1&&$draft['invoice_id']===null,'recurring source configuration is captured in private draft');
essentialThrows(fn()=>$drafts->issue($a,7,$id,1,hash('sha256','recurring-copy-bypass')),'company_invoice_draft_recurring_required','ordinary issue cannot silently drop copied recurrence');
$composer=new NativeCompanyInvoiceRecurringCopy($db,$p);$issueKey=hash('sha256','recurring-copy-issue');$result=$composer->issue($a,7,$id,1,$issueKey);
essentialCheck($result['recurring_status']==='scheduled'&&count($result['schedules'])===1,'issued copied invoice receives a real owned recurring schedule');
$created=$ir->document($a,7,$result['schedules'][0]['schedule_id']);$original=$ir->document($a,7,$sid);
essentialCheck($created['config']===$original['config']&&$created['mode']['principal_invoice_id']===$result['invoice_id']&&$created['next_date']==='2027-01-15','schedule preserves configuration, new principal and next occurrence rather than duplicating principal');
essentialCheck($composer->issue($a,7,$id,1,$issueKey)===$result,'repeat issue and recovery return the same invoice and schedule');
essentialThrows(fn()=>$composer->resume($b,7,$id),'company_invoice_draft_unavailable','foreign issuer cannot resume the recurring copy');
echo 'Copied recurring invoice: frozen configuration, gated issue, owned schedule and idempotent recovery passed; no external transmission.'.PHP_EOL;
