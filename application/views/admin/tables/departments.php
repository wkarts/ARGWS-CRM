<?php

defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
    'departmentid',
    'name',
    'email',
    'calendar_id',
];
$sIndexColumn = 'departmentid';
$sTable       = db_prefix() . 'departments';

$result  = data_tables_init($aColumns, $sIndexColumn, $sTable, [], [], ['email', 'hidefromclient', 'host', 'encryption', 'password', 'delete_after_import', 'imap_username', 'folder']);
$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = require APPPATH.'views/admin/departments/table_row.php';

    $output['aaData'][] = $row;
}
