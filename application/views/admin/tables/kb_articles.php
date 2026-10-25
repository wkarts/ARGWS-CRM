<?php

defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
    'subject',
    'articlegroup',
    'datecreated',
];
$sIndexColumn     = 'articleid';
$sTable           = db_prefix() . 'knowledge_base';
$additionalSelect = [
    'name',
    'groupid',
    'articleid',
    'slug',
    'staff_article',
    db_prefix() . 'knowledge_base.description',
];
$join = [
    'LEFT JOIN ' . db_prefix() . 'knowledge_base_groups ON ' . db_prefix() . 'knowledge_base_groups.groupid = ' . db_prefix() . 'knowledge_base.articlegroup',
];

$where   = [];
$filter  = [];
$groups  = $this->ci->knowledge_base_model->get_kbg();
$_groups = [];

foreach ($groups as $group) {
    if ($this->ci->input->post('kb_group_' . $group['groupid'])) {
        array_push($_groups, $group['groupid']);
    }
}
if (count($_groups) > 0) {
    array_push($filter, 'AND articlegroup IN (' . implode(', ', $_groups) . ')');
}
if (count($filter) > 0) {
    array_push($where, 'AND (' . prepare_dt_filter($filter) . ')');
}

if (staff_cant('create', 'knowledge_base') && staff_cant('edit', 'knowledge_base')) {
    array_push($where, ' AND ' . db_prefix() . 'knowledge_base.active=1');
}

$result  = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, $additionalSelect);
$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = require APPPATH.'views/admin/knowledge_base/table_row.php';

    $output['aaData'][] = $row;
}
