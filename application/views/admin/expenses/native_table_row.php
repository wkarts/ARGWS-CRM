<?php
defined('BASEPATH') or exit('No direct script access allowed');
$id=$expense_row['id'];$owned=$expense_row['source_kind']==='company_expense';$source=$owned?'company':'legacy';
$href=admin_url('expenses/list_expenses/'.$id.'?source='.$source);
$open='<a href="'.e($href).'" data-native-expense="'.e($id).'">'.e($expense_row['category_name']).'</a>';
$options='<div class="row-options"><a href="'.e($href).'" data-native-expense="'.e($id).'">'.e(_l('view')).'</a>';
if($capabilities['edit']&&$expense_row['state']==='recorded')$options.=' | <a href="'.e(admin_url('expenses/expense/'.$id.'?source='.$source)).'">'.e(_l('edit')).'</a>';
$options.='</div>';
$selectable=$owned&&$expense_row['state']==='recorded'&&($capabilities['edit']||$capabilities['delete']);
$row[]=$selectable?'<div class="checkbox"><input type="checkbox" id="expense-select-'.e($id).'" value="'.e($id).'" data-native-expense-selection data-revision="'.e($expense_row['revision_token']).'"><label for="expense-select-'.e($id).'"></label></div>':'';$row[]=e($expense_row['number']);$row[]=$open.$options;
if(!isset($currencies[$expense_row['currency']]))throw new DomainException('native_expense_currency_invalid');
$row[]=e(NativeCompanyExpenseIndex::formatCents($expense_row['total_cents'],$currencies[$expense_row['currency']]));
$row[]=e($expense_row['expense_name']);
$receipt='';if($expense_row['file_name']!==null&&$expense_row['file_name']!==''){
    $fileUrl=$owned?admin_url('expenses?source=company&expense='.$id.'&file='.rawurlencode($expense_row['file_key'])):$href;
    $receipt='<a href="'.e($fileUrl).'">'.e($expense_row['file_name']).'</a>';
}$row[]=$receipt;$row[]=e($dates->displayDate($expense_row['expense_date']));
$row[]=(int)$expense_row['project_visible']===1?'<a href="'.e(admin_url('projects/view/'.$expense_row['project_id'])).'">'.e($expense_row['project_name']).'</a>':e($expense_row['project_name']);
$row[]=(int)$expense_row['client_visible']===1?'<a href="'.e(admin_url('clients/client/'.$expense_row['client_id'])).'">'.e($expense_row['client_name']).'</a>':e($expense_row['client_name']);
$row[]=$expense_row['invoice_id']===null?'':'<a href="'.e(admin_url('invoices/list_invoices/'.$expense_row['invoice_id'])).'">'.e($expense_row['invoice_number']).'</a>';
$row[]=e($expense_row['reference_no']);$row[]=e($expense_row['payment_mode_name']);
foreach($page['definitions']as$field)$row[]=e($expense_row['custom_values'][$field['id']]??'');
$row['DT_RowClass']='has-row-options'.($expense_row['state']==='cancelled'?' text-muted':'');$row['DT_RowAttr']=['data-expense-id'=>$id,'data-expense-source'=>$expense_row['source_kind']];
