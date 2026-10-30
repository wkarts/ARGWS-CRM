<?php

defined('BASEPATH') or exit('No direct script access allowed');

return App_table::find('projects')
    ->outputUsing(function ($params) {
        extract($params);

        $hasPermissionEdit   = staff_can('edit',  'projects');
        $hasPermissionDelete = staff_can('delete',  'projects');
        $hasPermissionCreate = staff_can('create',  'projects');

        $aColumns = [
            db_prefix() . 'projects.id as id',
            'name',
            get_sql_select_client_company(),
            '(SELECT GROUP_CONCAT(name SEPARATOR ",") FROM ' . db_prefix() . 'taggables JOIN ' . db_prefix() . 'tags ON ' . db_prefix() . 'taggables.tag_id = ' . db_prefix() . 'tags.id WHERE rel_id = ' . db_prefix() . 'projects.id and rel_type="project" ORDER by tag_order ASC) as tags',
            'start_date',
            'deadline',
            '(SELECT GROUP_CONCAT(CONCAT(firstname, \' \', lastname) SEPARATOR ",") FROM ' . db_prefix() . 'project_members JOIN ' . db_prefix() . 'staff on ' . db_prefix() . 'staff.staffid = ' . db_prefix() . 'project_members.staff_id WHERE project_id=' . db_prefix() . 'projects.id ORDER BY staff_id) as members',
            'status',
        ];


        $sIndexColumn = 'id';
        $sTable       = db_prefix() . 'projects';

        $join = [
            'JOIN ' . db_prefix() . 'clients ON ' . db_prefix() . 'clients.userid = ' . db_prefix() . 'projects.clientid',
        ];

        $where  = [];

        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        if ($clientid != '') {
            array_push($where, ' AND clientid=' . $this->ci->db->escape_str($clientid));
        }

        if (staff_cant('view', 'projects')) {
            array_push($where, ' AND ' . db_prefix() . 'projects.id IN (SELECT project_id FROM ' . db_prefix() . 'project_members WHERE staff_id=' . get_staff_user_id() . ')');
        }

        $custom_fields = get_table_custom_fields('projects');

        foreach ($custom_fields as $key => $field) {
            $selectAs = (is_cf_date($field) ? 'date_picker_cvalue_' . $key : 'cvalue_' . $key);
            array_push($customFieldsColumns, $selectAs);
            array_push($aColumns, 'ctable_' . $key . '.value as ' . $selectAs);
            array_push($join, 'LEFT JOIN ' . db_prefix() . 'customfieldsvalues as ctable_' . $key . ' ON ' . db_prefix() . 'projects.id = ctable_' . $key . '.relid AND ctable_' . $key . '.fieldto="' . $field['fieldto'] . '" AND ctable_' . $key . '.fieldid=' . $field['id']);
        }

        $join = hooks()->apply_filters('projects_table_sql_join', $join);
        $aColumns = hooks()->apply_filters('projects_table_sql_columns', $aColumns);

        // Fix for big queries. Some hosting have max_join_limit
        if (count($custom_fields) > 4) {
            @$this->ci->db->query('SET SQL_BIG_SELECTS=1');
        }

        $result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [
            'clientid',
            '(SELECT GROUP_CONCAT(staff_id SEPARATOR ",") FROM ' . db_prefix() . 'project_members WHERE project_id=' . db_prefix() . 'projects.id ORDER BY staff_id) as members_ids',
        ]);

        $output  = $result['output'];
        $rResult = $result['rResult'];

        foreach ($rResult as $aRow) {
            require_once APPPATH.'services/NativeProjectRowPresentation.php';
            $row = NativeProjectRowPresentation::render($aRow, $clientid,
                ['create'=>$hasPermissionCreate, 'edit'=>$hasPermissionEdit, 'delete'=>$hasPermissionDelete],
                ['url'=>'admin_url', 'translate'=>'_l', 'escape'=>'e', 'tags'=>'render_tags', 'date'=>'_d',
                 'avatar'=>'staff_profile_image', 'status'=>'get_project_status_by_id', 'brightness'=>'adjust_hex_brightness'],
                $customFieldsColumns);

            $row = hooks()->apply_filters('projects_table_row_data', $row, $aRow);

            $output['aaData'][] = $row;
        }
        return $output;
    })->setRules([
        App_table_filter::new('name','TextRule')->label(_l('project_name')),
        App_table_filter::new('start_date','DateRule')->label(_l('project_start_date')),
        App_table_filter::new('deadline','DateRule')->label(_l('project_deadline')),
        App_table_filter::new('billing_type','SelectRule')->label(_l('project_billing_type'))->options(function($ci) {
            return [
                ['value'=>1,'label'=>_l('project_billing_type_fixed_cost')],
                ['value'=>2,'label'=>_l('project_billing_type_project_hours')],
                ['value'=>3,'label'=>_l('project_billing_type_project_task_hours_hourly_rate')],
            ];
        }),
        App_table_filter::new('status','MultiSelectRule')->label(_l('project_status'))->options(function($ci){
                return collect($ci->projects_model->get_project_statuses())->map(fn ($data) => [
                    'value' => $data['id'],
                    'label' => $data['name'],
                ])->all();
        }),

        App_table_filter::new('members', 'MultiSelectRule')->label(_l('project_members'))
            ->isVisible(fn () => staff_can('view', 'projects'))
            ->options(function ($ci) {
                return collect($ci->projects_model->get_distinct_projects_members())->map(function ($staff) {
                    return [
                        'value' => $staff['staff_id'],
                        'label' => get_staff_full_name($staff['staff_id'])
                    ];
                })->all();
            })->raw(function ($value, $operator, $sqlOperator) {
                $dbPrefix = db_prefix();
                $sqlOperator = $sqlOperator['operator'];
                return "({$dbPrefix}projects.id IN (SELECT project_id FROM {$dbPrefix}project_members WHERE staff_id $sqlOperator ('" . implode("','", $value) . "')))";
            })
    ]);
