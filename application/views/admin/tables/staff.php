<?php

defined('BASEPATH') or exit('No direct script access allowed');

$nativeStaff=!empty($native_staff_table);
if($nativeStaff){
    $custom_fields=$native_staff_fields;$rResult=$native_staff_rows;$output=$native_staff_output;$has_permission_delete=false;
    $aColumns=['firstname','email','role_name','last_login','active'];foreach($custom_fields as$n=>$field)$aColumns[]='cvalue_'.$n;
    $nativeFormatter=$native_staff_formatter;
}else{
$has_permission_delete = staff_can('delete', 'staff');

$custom_fields = get_custom_fields('staff', [
    'show_on_table' => 1,
]);
$aColumns = [
    'firstname',
    'email',
    db_prefix() . 'roles.name',
    'last_login',
    'active',
];
$sIndexColumn = 'staffid';
$sTable       = db_prefix() . 'staff';
$join         = ['LEFT JOIN ' . db_prefix() . 'roles ON ' . db_prefix() . 'roles.roleid = ' . db_prefix() . 'staff.role'];
$i            = 0;

foreach ($custom_fields as $field) {
    $select_as = 'cvalue_' . $i;
    if ($field['type'] == 'date_picker' || $field['type'] == 'date_picker_time') {
        $select_as = 'date_picker_cvalue_' . $i;
    }
    array_push($aColumns, 'ctable_' . $i . '.value as ' . $select_as);
    array_push($join, 'LEFT JOIN ' . db_prefix() . 'customfieldsvalues as ctable_' . $i . ' ON ' . db_prefix() . 'staff.staffid = ctable_' . $i . '.relid AND ctable_' . $i . '.fieldto="' . $field['fieldto'] . '" AND ctable_' . $i . '.fieldid=' . $field['id']);
    $i++;
}
// Fix for big queries. Some hosting have max_join_limit
if (count($custom_fields) > 4) {
    @$this->ci->db->query('SET SQL_BIG_SELECTS=1');
}

$join = hooks()->apply_filters('staff_table_sql_join', $join);
$where = hooks()->apply_filters('staff_table_sql_where', []);

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [
    'profile_image',
    'lastname',
    'staffid',
]);

$output  = $result['output'];
$rResult = $result['rResult'];

}

foreach ($rResult as $aRow) {
    $row = [];

    for ($i = 0; $i < count($aColumns); $i++) {
        if (!$nativeStaff && strpos($aColumns[$i], 'as') !== false && ! isset($aRow[$aColumns[$i]])) {
            $_data = $aRow[strafter($aColumns[$i], 'as ')];
        } else {
            $_data = $aRow[$aColumns[$i]];
        }
        if ($aColumns[$i] == 'last_login') {
            if ($_data != null) {
                $_data = '<span class="text-has-action is-date" data-toggle="tooltip" data-title="' . e($nativeStaff?$nativeFormatter->datetime($_data):_dt($_data)) . '">' . time_ago($_data) . '</span>';
            } else {
                $_data = 'Never';
            }
        } elseif ($aColumns[$i] == 'active') {
            $checked = '';
            if ($aRow['active'] == 1) {
                $checked = 'checked';
            }

            $_data = '<div class="onoffswitch">
                <input type="checkbox" ' . ($nativeStaff?($aRow['native_can_status']?'':'disabled title="'.e(_l($aRow['native_status_reason'])).'"'):(($aRow['staffid'] == get_staff_user_id() || (is_admin($aRow['staffid']) || staff_cant('edit', 'staff')) && ! is_admin()) ? 'disabled' : '')) . ' '.($nativeStaff?'data-native-revision="'.e($aRow['native_revision']).'" data-native-request-key="'.bin2hex(random_bytes(32)).'"':'').' data-switch-url="' . ($nativeStaff?CompanyRequestScope::adminUrl($native_staff_ci,'staff/change_staff_status'):admin_url('staff/change_staff_status')) . '" name="onoffswitch" class="onoffswitch-checkbox" id="c_' . $aRow['staffid'] . '" data-id="' . $aRow['staffid'] . '" ' . $checked . '>
                <label class="onoffswitch-label" for="c_' . $aRow['staffid'] . '"></label>
            </div>';

            // For exporting
            $_data .= '<span class="hide">' . ($checked == 'checked' ? _l('is_active_export') : _l('is_not_active_export')) . '</span>';
        } elseif ($aColumns[$i] == 'firstname') {
            $_data = '<a href="' . ($nativeStaff?CompanyRequestScope::adminUrl($native_staff_ci,'staff/member/'.$aRow['staffid']):admin_url('staff/profile/'.$aRow['staffid'])) . '">' . ($nativeStaff?StaffAvatarPresentation::render((object)$aRow,(int)$aRow['staffid'],['staff-profile-image-small'],'small',[],'base_url','file_exists'):staff_profile_image($aRow['staffid'],['staff-profile-image-small'])) . '</a>';
            $_data .= ' <a href="' . ($nativeStaff?CompanyRequestScope::adminUrl($native_staff_ci,'staff/member/'.$aRow['staffid']):admin_url('staff/member/'.$aRow['staffid'])) . '" class="tw-font-medium">' . e($aRow['firstname'] . ' ' . $aRow['lastname']) . '</a>';

            $_data .= '<div class="row-options">';
            $_data .= '<a href="' . ($nativeStaff?CompanyRequestScope::adminUrl($native_staff_ci,'staff/member/'.$aRow['staffid']):admin_url('staff/member/'.$aRow['staffid'])) . '">' . _l('view') . '</a>';

            if (!$nativeStaff && (($has_permission_delete && ($has_permission_delete && ! is_admin($aRow['staffid']))) || is_admin())) {
                if ($has_permission_delete && $output['iTotalRecords'] > 1 && $aRow['staffid'] != get_staff_user_id()) {
                    $_data .= ' | <a href="#" onclick="delete_staff_member(' . $aRow['staffid'] . '); return false;" class="text-danger">' . _l('delete') . '</a>';
                }
            }

            $_data .= '</div>';
        } elseif ($aColumns[$i] == 'email') {
            $_data = '<a href="mailto:' . e($_data) . '">' . e($_data) . '</a>';
        } else {
            if (strpos($aColumns[$i], 'date_picker_') !== false) {
                $_data = (strpos($_data, ' ') !== false ? _dt($_data) : _d($_data));
            }
        }
        if($nativeStaff&&str_starts_with($aColumns[$i],'cvalue_')){$type=$custom_fields[$i-5]['type'];if($_data!==''&&$type==='date_picker')$_data=$nativeFormatter->date($_data);elseif($_data!==''&&$type==='date_picker_time')$_data=$nativeFormatter->datetime($_data);}
        if($nativeStaff&&($aColumns[$i]==='role_name'||str_starts_with($aColumns[$i],'cvalue_')))$_data=e($_data);
        $row[] = $_data;
    }

    $row['DT_RowClass'] = 'has-row-options';

    if(!$nativeStaff)$row = hooks()->apply_filters('staff_table_row', $row, $aRow);

    $output['aaData'][] = $row;
}
