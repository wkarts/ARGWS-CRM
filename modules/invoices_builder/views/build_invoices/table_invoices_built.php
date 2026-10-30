<?php

defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
    'invoice_id',
    'template_id',
    'clientid', 
    db_prefix().'invoices.status',
    db_prefix().'ib_invoices_built.id'
    ];
$sIndexColumn = 'id';
$sTable       = db_prefix().'ib_invoices_built';
$join         = [ 'LEFT JOIN '.db_prefix().'invoices ON '.db_prefix().'invoices.id = '.db_prefix().'ib_invoices_built.invoice_id' ];
$where = [];



if ($this->ci->input->post('invoice') && count($this->ci->input->post('invoice')) > 0) {
    array_push($where, 'AND invoice_id IN (' . implode(',', $this->ci->input->post('invoice')) . ')');
}

if ($this->ci->input->post('clients') && count($this->ci->input->post('clients')) > 0) {
    array_push($where, 'AND '.db_prefix().'invoices.clientid IN (' . implode(',', $this->ci->input->post('clients')) . ')');
}

if ($this->ci->input->post('sale_agent') && count($this->ci->input->post('sale_agent')) > 0) {
    array_push($where, 'AND '.db_prefix().'invoices.sale_agent IN (' . implode(',', $this->ci->input->post('sale_agent')) . ')');
}

if ($this->ci->input->post('template') && count($this->ci->input->post('template')) > 0) {
    array_push($where, 'AND template_id IN (' . implode(',', $this->ci->input->post('template')) . ')');
}

if (!has_permission('invoices', '', 'view')) {
    $userWhere = 'AND ' . get_invoices_where_sql_for_staff(get_staff_user_id());
    array_push($where, $userWhere);
}

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [ 'clientid', db_prefix().'invoices.hash']);

$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];

    for ($i = 0; $i < count($aColumns); $i++) {

        $_data = $aRow[$aColumns[$i]];
        if($aColumns[$i] == 'invoice_id'){
            $_data = format_invoice_number($aRow['invoice_id']);
        }else if($aColumns[$i] == 'template_id'){
            $_data = '<a href="'.admin_url('invoices_builder/template/'.$aRow['template_id']).'">' .ib_get_template_name_by_id($aRow['template_id']).'</a>';
        }else if($aColumns[$i] == 'clientid'){
            $_data = '<a href="'.admin_url('clients/client/'.$aRow['clientid']).'">'. get_company_name($aRow['clientid']).'</a>';
        }else if($aColumns[$i] == db_prefix().'invoices.status'){
            $_data = format_invoice_status($aRow[db_prefix().'invoices.status']);
        }else if($aColumns[$i] == db_prefix().'ib_invoices_built.id'){
            $option = '';
            $option .= '<a href="'.site_url('invoices_builder/invoice_link/index/'. $aRow[db_prefix().'ib_invoices_built.id'].'/'.$aRow['hash']).'" class="btn btn-icon btn-success"><i class="fa fa-eye"></i></a>';
            $option .= '<a href="'.admin_url('invoices_builder/pdf_view/'.$aRow[db_prefix().'ib_invoices_built.id'].'?output_type=I').'" class="btn btn-icon btn-default"><i class="fa fa-file-pdf"></i></a>';
            $option .= '<a href="javascript:void(0)" onclick="send_invoice('.$aRow[db_prefix().'ib_invoices_built.id'].'); return false;" class="btn btn-icon btn-info"><i class="fa fa-envelope"></i></a>';
            $option .= '<a href="'.admin_url('invoices_builder/edit_converted_invoice/'.$aRow[db_prefix().'ib_invoices_built.id']).'" class="btn btn-icon btn-warning"><i class="fa fa-edit"></i></a>';
            $option .= '<a href="'.admin_url('invoices_builder/delete_built_invoice/'.$aRow[db_prefix().'ib_invoices_built.id']).'" class="btn btn-icon btn-danger _delete"><i class="fa fa-remove"></i></a>';
            $_data = $option;
        }

        $row[] = $_data;
    }
    $output['aaData'][] = $row;
}
