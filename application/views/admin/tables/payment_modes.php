<?php

defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
    'id',
    'name',
    'description',
    'active',
];
$sIndexColumn = 'id';
$sTable       = db_prefix() . 'payment_modes';

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, [], [], [
    'expenses_only',
    'invoices_only',
    'show_on_pdf',
    'selected_by_default',
]);
$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    require APPPATH . 'views/admin/paymentmodes/table_row.php';
    $output['aaData'][] = $row;
}
