<?php

defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = ['name'];

$sIndexColumn = 'id';
$sTable       = db_prefix() . 'customers_groups';

$where=[];
if($this->ci->db->table_exists(db_prefix().'company_customer_group_owners'))$where[]='AND NOT EXISTS (SELECT 1 FROM '.db_prefix().'company_customer_group_owners o WHERE o.group_id='.$sTable.'.id)';
$result  = data_tables_init($aColumns, $sIndexColumn, $sTable, [], $where, ['id']);
$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];

    for ($i = 0; $i < count($aColumns); $i++) {
        $_data = '<a href="#" class="tw-font-medium" data-toggle="modal" data-target="#customer_group_modal" data-id="' . $aRow['id'] . '">' . e($aRow[$aColumns[$i]]) . '</a>';

        $row[] = $_data;
    }
    $options = '<div class="tw-flex tw-items-center tw-space-x-2">';
    $options .= '<a href="#" class="tw-text-neutral-500 hover:tw-text-neutral-700 focus:tw-text-neutral-700" data-toggle="modal" data-target="#customer_group_modal" data-id="' . $aRow['id'] . '">
        <i class="fa-regular fa-pen-to-square fa-lg"></i>
    </a>';

    $options .= '<a href="' . admin_url('clients/delete_group/' . $aRow['id']) . '"
    class="tw-text-neutral-500 hover:tw-text-neutral-700 focus:tw-text-neutral-700 _delete">
        <i class="fa-regular fa-trash-can fa-lg"></i>
    </a>';
    $options .= '</div>';

    $row[] = $options;

    $output['aaData'][] = $row;
}
