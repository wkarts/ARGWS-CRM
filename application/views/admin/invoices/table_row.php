<?php defined('BASEPATH') or exit('No direct script access allowed');
// Shared native row presentation. Callers supply authorized data and presentation only.
$row = [];

$numberOutput = '';

// If is from client area table
if (is_numeric($clientid) || $project_id) {
    $numberOutput = '<a href="' . admin_url('invoices/list_invoices/' . $aRow['id']) . '" target="_blank" class="tw-font-medium">' . e($formattedNumber) . '</a>';
} else {
    $numberOutput = '<a href="' . admin_url('invoices/list_invoices/' . $aRow['id']) . '" onclick="init_invoice(' . $aRow['id'] . '); return false;" class="tw-font-medium">' . e($formattedNumber) . '</a>';
}

if ($aRow['recurring'] > 0) {
    $numberOutput .= '<br /><span class="label label-primary inline-block tw-mt-1 tw-font-medium"> ' . _l('invoice_recurring_indicator') . '</span>';
}

$numberOutput .= '<div class="row-options">';

$numberOutput .= '<a href="' . ($invoiceViewUrl ?? site_url('invoice/' . $aRow['id'] . '/' . $aRow['hash'])) . '" target="_blank">' . _l('view') . '</a>';
if ($canEditInvoice) {
    $numberOutput .= ' | <a href="' . admin_url('invoices/invoice/' . $aRow['id']) . '">' . _l('edit') . '</a>';
}
$numberOutput .= '</div>';

$row[] = $numberOutput;

$row[] = '<span class="tw-font-medium">' . e(app_format_money($aRow['total'], $aRow['currency_name'])) . '</span>';

$row[] = '<span class="tw-font-medium">' . e(app_format_money($aRow['total_tax'], $aRow['currency_name'])) . '</span>';

$row[] = e($aRow['year']);

$row[] = e(_d($aRow['date']));

if (empty($aRow['deleted_customer_name']) && ($invoiceCustomerLink ?? true)) {
    $row[] = '<a href="' . admin_url('clients/client/' . $aRow['clientid']) . '">' . e($aRow['company']) . '</a>';
} else {
    $row[] = e(!empty($aRow['deleted_customer_name']) ? $aRow['deleted_customer_name'] : $aRow['company']);
}

$row[] = ($invoiceProjectLink ?? true) ? '<a href="' . admin_url('projects/view/' . $aRow['project_id']) . '">' . e($aRow['project_name']) . '</a>' : e($aRow['project_name']);

$row[] = $invoiceTagsHtml;

$row[] = e(_d($aRow['duedate']));

$row[] = format_invoice_status($invoiceStatus);

// Custom fields add values
foreach ($customFieldsColumns as $customFieldColumn) {
    $row[] = (strpos($customFieldColumn, 'date_picker_') !== false ? _d($aRow[$customFieldColumn]) : $aRow[$customFieldColumn]);
}

$row['DT_RowClass'] = 'has-row-options';

return $row;
