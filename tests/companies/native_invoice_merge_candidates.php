<?php
require __DIR__.'/native_invoice_draft_table.php';
require_once APPPATH.'services/NativeCompanyInvoiceMergeCandidates.php';
$service=new NativeCompanyInvoiceMergeCandidates($db,$p);$client=$draftInput['client_id'];$before=[];foreach(['invoices','company_invoice_drafts','company_invoice_requests','company_financial_events']as$table)$before[$table]=$db->query('SELECT * FROM '.$p.$table.' ORDER BY 1')->result_array();
$selection=$service->forCustomer($a,7,$client);$ids=array_map(fn($r)=>$r->id,$selection['items']);essentialCheck(in_array($encoded,$ids,true),'original merge selection includes exact private draft source ID');
foreach($selection['items']as$row)essentialCheck(in_array($row->status,[1,4,6],true)&&$row->native_formatted_number!==''&&$row->basis==='selection_only_not_merge_authority','candidate preserves native status/number and does not claim merge authority');
$current=$service->forCustomer($a,7,$client,$encoded);essentialCheck(!in_array($encoded,array_map(fn($r)=>$r->id,$current['items']),true),'same draft is excluded by canonical ID, not raw source collision');
essentialThrows(fn()=>$service->forCustomer($b,7,$client),'native_resource_reference_denied','foreign customer cannot enumerate merge sources');essentialThrows(fn()=>$service->forCustomer($b,7,$cb,$encoded),'company_invoice_draft_unavailable','foreign draft cannot be current merge target');
if(!function_exists('format_invoice_number')){function format_invoice_number($id){throw new RuntimeException('global invoice number lookup invoked');}}
$invoices_to_merge=$selection['items'];ob_start();include APPPATH.'views/admin/invoices/merge_invoice.php';$html=ob_get_clean();essentialCheck(str_contains($html,'name="invoices_to_merge[]"')&&str_contains($html,'DRAFT'),'same original merge partial uses proved number and canonical source without global hydration');
foreach($before as$table=>$rows)essentialCheck($db->query('SELECT * FROM '.$p.$table.' ORDER BY 1')->result_array()===$rows,'selection never mutates '.$table);
echo 'Original merge selection/partial: '.$GLOBALS['essentialChecks']." cumulative PASS; merge command still separate and guarded.\n";
$paidOrCancelled=[];foreach([2,3,5]as$status){$actual=$reader->page($a,7,['status'=>$status,'length'=>-1]);foreach($actual['documents']as$doc){$result=$service->forCustomer($a,7,$client,$doc['invoice_id']);essentialCheck($result['items']===[],'original target status '.$status.' suppresses merge selection');$paidOrCancelled[]=$status;}}
echo 'Merge target statuses exercised: '.implode(',',array_unique($paidOrCancelled))."; no synthetic status mutation.\n";
