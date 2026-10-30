<?php

defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
    'id',
    'capture_image',
    'name',
    'created_by',
    'created_at', 
    'active',
    'updated_at',
    ];
$sIndexColumn = 'id';
$sTable       = db_prefix().'ib_templates';
$join         = [];
$where = [];

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, ['name', 'default_template']);

$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];
    for ($i = 0; $i < count($aColumns); $i++) {
        $_data = $aRow[$aColumns[$i]];
        if($aColumns[$i] == 'name'){
            $_data = '<span class="template-name">'.$aRow[$aColumns[$i]].'</span>';
        }
        elseif($aColumns[$i] == 'created_at'){
            $_data = _dt($aRow['created_at']);
        }
        else if($aColumns[$i] == 'capture_image'){
            if(!($aRow['capture_image'] == '' || $aRow['capture_image'] == null)){
                $_data = '<div class="image-contain list-template-image"><img class="base64-image pointer" src="data:image/png;base64,'.$aRow['capture_image'].'"></div>';
            }
            else{
                $_data = '<div class="image-contain list-template-image"><img src="'.base_url('modules/invoices_builder/assets/images/no_image.jpg').'"></div>';
            }
        }
        else if($aColumns[$i] == 'created_by'){
            $_data = '<a href="'.admin_url('staff/profile/'. $aRow['created_by']).'">'.get_staff_full_name($aRow['created_by']).'</a>';
        }else if($aColumns[$i] == 'active'){
            // Toggle active/inactive customer
            $toggleActive = '<div class="onoffswitch" >
            <input type="checkbox" data-url="' . admin_url() . 'invoices_builder/change_template_active" name="onoffswitch" class="onoffswitch-checkbox" id="' . $aRow['id'] . '" data-id="' . $aRow['id'] . '" ' . ($aRow['active'] == 1 ? 'checked' : '') . '>
            <label class="onoffswitch-label" for="' . $aRow['id'] . '"></label>
            </div>';
            // For exporting
            $toggleActive .= '<span class="hide">' . ($aRow['active'] == 1 ? _l('is_active_export') : _l('is_not_active_export')) . '</span>';
            $_data = $toggleActive;
        }else if($aColumns[$i] == 'updated_at'){
            $_data = '';
            $_data .= '<a href="'.admin_url('invoices_builder/preview/'.$aRow['id']).'" class="btn btn-default btn-icon" data-toggle="tooltip" data-placement="top" title="'._l('ib_html_preview').'"><i class="fa fa-eye"></i></a>';
            if($aRow['default_template'] != 1 && is_admin()){
                $_data .= '<a href="'.admin_url('invoices_builder/template/'.$aRow['id']).'" class="btn btn-default btn-icon" data-toggle="tooltip" data-placement="top" title="'._l('edit').'"><i class="fa fa-edit"></i></a>';
                $_data .= '<a href="'.admin_url('invoices_builder/delete_template/'.$aRow['id']).'" class="btn btn-danger btn-icon _delete" data-toggle="tooltip" data-placement="top" title="'._l('delete').'"><i class="fa fa-remove"></i></a>';
            }
            $_data .= '<a href="javascript:void(0)" class="btn btn-success btn-icon" onclick="clone_template('.$aRow['id'].')" data-toggle="tooltip" data-placement="top" title="'._l('clone').'"><i class="fa fa-clone"></i></a>';
        }
        $row[] = $_data;
    }
    $output['aaData'][] = $row;

}
