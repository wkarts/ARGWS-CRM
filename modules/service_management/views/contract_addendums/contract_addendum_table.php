<?php

defined('BASEPATH') or exit('No direct script access allowed');

$base_currency = get_base_currency();

$aColumns = [
    db_prefix() . 'sm_contract_addendums.id as id',
    'subject',
    'contract_id',
    'datestart',
    ];

$sIndexColumn = 'id';
$sTable       = db_prefix() . 'sm_contract_addendums';

$join = [
];


$where  = [];
$filter = [];

$contract_id_filter = $this->ci->input->post('contract_id');

if ($this->ci->input->post('exclude_trashed_contracts')) {
    array_push($filter, 'AND trash = 0');
}

if ($this->ci->input->post('trash')) {
    array_push($filter, 'AND trash = 1');
}

if ($this->ci->input->post('expired')) {
    array_push($filter, 'AND dateend IS NOT NULL AND dateend <"' . date('Y-m-d') . '" and trash = 0');
}

if ($this->ci->input->post('without_dateend')) {
    array_push($filter, 'AND dateend IS NULL AND trash = 0');
}

$years      = $this->ci->service_contract_model->get_contracts_years();
$yearsArray = [];
foreach ($years as $year) {
    if ($this->ci->input->post('year_' . $year['year'])) {
        array_push($yearsArray, $year['year']);
    }
}
if (count($yearsArray) > 0) {
    array_push($filter, 'AND YEAR(datestart) IN (' . implode(', ', $yearsArray) . ')');
}

$monthArray = [];
for ($m = 1; $m <= 12; $m++) {
    if ($this->ci->input->post('contracts_by_month_' . $m)) {
        array_push($monthArray, $m);
    }
}

if (count($monthArray) > 0) {
    array_push($filter, 'AND MONTH(datestart) IN (' . implode(', ', $monthArray) . ')');
}

if (count($filter) > 0) {
    array_push($where, 'AND (' . prepare_dt_filter($filter) . ')');
}

if ($clientid != '') {
    array_push($where, 'AND client=' . $this->ci->db->escape_str($clientid));
}

if (!has_permission('service_management', '', 'view')) {
    array_push($where, 'AND ' . db_prefix() . 'sm_contract_addendums.addedfrom=' . get_staff_user_id());
}

if(isset($contract_id_filter)){
    $where[] = 'AND '.db_prefix().'sm_contract_addendums.contract_id = '.$contract_id_filter;
}


$aColumns = hooks()->apply_filters('contract_addendums_table_sql_columns', $aColumns);

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [db_prefix() . 'sm_contract_addendums.id', 'trash', 'hash', 'marked_as_signed']);

$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];

    $row[] = $aRow['id'];

    $subjectOutput = '<a href="' . admin_url('service_management/contract_addendum/' . $aRow['id']) . '">' . $aRow['subject'] . '</a>';
    if ($aRow['trash'] == 1) {
        $subjectOutput .= '<span class="label label-danger pull-right">' . _l('contract_trash') . '</span>';
    }

    $subjectOutput .= '<div class="row-options">';

    $subjectOutput .= '<a href="' . site_url('service_management/service_management_client/client_contract/' . $aRow['id'] . '/' . $aRow['hash']) . '" target="_blank" class="hide">' . _l('view') . '</a>';

    if (has_permission('service_management', '', 'edit')) {
        $subjectOutput .= '<a href="' . admin_url('service_management/contract_addendum/' . $aRow['id']) . '">' . _l('edit') . '</a>';
    }

    if (has_permission('service_management', '', 'delete')) {
        $subjectOutput .= ' | <a href="' . admin_url('service_management/delete_contract_addendum/' . $aRow['id']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
    }

    $subjectOutput .= '</div>';
    $row[] = $subjectOutput;



    $row[] = '<a href="' . admin_url('service_management/contract/' . $aRow['contract_id'] ) . '" >' . sm_contract_name($aRow['contract_id']) . '</a>';
    $row[] = '<a href="' . admin_url('clients/client/' . sm_client_id_from_contract($aRow['contract_id'])) . '">' . get_company_name(sm_client_id_from_contract($aRow['contract_id'])) . '</a>';
    $row[] = _d($aRow['datestart']);

    if (isset($row['DT_RowClass'])) {
        $row['DT_RowClass'] .= ' has-row-options';
    } else {
        $row['DT_RowClass'] = 'has-row-options';
    }

    $row = hooks()->apply_filters('contract_addendums_table_row_data', $row, $aRow);

    $output['aaData'][] = $row;
}
