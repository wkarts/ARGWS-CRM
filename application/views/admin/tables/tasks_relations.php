<?php

defined('BASEPATH') or exit('No direct script access allowed');

return App_table::find('related_tasks')
    ->outputUsing(function ($params) {
        extract($params);

        $hasPermissionEdit   = staff_can('edit', 'tasks');
        $hasPermissionDelete = staff_can('delete', 'tasks');
        $tasksPriorities     = get_tasks_priorities();
        $task_statuses       = $this->ci->tasks_model->get_statuses();

        $aColumns = [
            '1', // bulk actions
            db_prefix() . 'tasks.id as id',
            db_prefix() . 'tasks.name as task_name',
            'status',
            'startdate',
            'duedate',
            get_sql_select_task_asignees_full_names() . ' as assignees',
            '(SELECT GROUP_CONCAT(name SEPARATOR ",") FROM ' . db_prefix() . 'taggables JOIN ' . db_prefix() . 'tags ON ' . db_prefix() . 'taggables.tag_id = ' . db_prefix() . 'tags.id WHERE rel_id = ' . db_prefix() . 'tasks.id and rel_type="task" ORDER by tag_order ASC) as tags',
            'priority',
        ];

        $sIndexColumn = 'id';
        $sTable       = db_prefix() . 'tasks';

        $where = [];

        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        if (staff_cant('view', 'tasks')) {
            $where[] = get_tasks_where_string();
        }

        if (! $this->ci->input->post('tasks_related_to')) {
            array_push($where, 'AND rel_id="' . $this->ci->db->escape_str($rel_id) . '" AND rel_type="' . $this->ci->db->escape_str($rel_type) . '"');
        } else {
            // Used in the customer profile filters
            $tasks_related_to = explode(',', $this->ci->input->post('tasks_related_to'));
            $rel_to_query     = 'AND (';

            $lastElement = end($tasks_related_to);

            foreach ($tasks_related_to as $rel_to) {
                if ($rel_to == 'invoice') {
                    $rel_to_query .= '(rel_id IN (SELECT id FROM ' . db_prefix() . 'invoices WHERE clientid=' . $this->ci->db->escape_str($rel_id) . ')';
                } elseif ($rel_to == 'estimate') {
                    $rel_to_query .= '(rel_id IN (SELECT id FROM ' . db_prefix() . 'estimates WHERE clientid=' . $this->ci->db->escape_str($rel_id) . ')';
                } elseif ($rel_to == 'contract') {
                    $rel_to_query .= '(rel_id IN (SELECT id FROM ' . db_prefix() . 'contracts WHERE client=' . $this->ci->db->escape_str($rel_id) . ')';
                } elseif ($rel_to == 'ticket') {
                    $rel_to_query .= '(rel_id IN (SELECT ticketid FROM ' . db_prefix() . 'tickets WHERE userid=' . $this->ci->db->escape_str($rel_id) . ')';
                } elseif ($rel_to == 'expense') {
                    $rel_to_query .= '(rel_id IN (SELECT id FROM ' . db_prefix() . 'expenses WHERE clientid=' . $this->ci->db->escape_str($rel_id) . ')';
                } elseif ($rel_to == 'proposal') {
                    $rel_to_query .= '(rel_id IN (SELECT id FROM ' . db_prefix() . 'proposals WHERE rel_type=' . $this->ci->db->escape_str($rel_id) . ' AND rel_type="customer")';
                } elseif ($rel_to == 'customer') {
                    $rel_to_query .= '(rel_id IN (SELECT userid FROM ' . db_prefix() . 'clients WHERE userid=' . $this->ci->db->escape_str($rel_id) . ')';
                } elseif ($rel_to == 'project') {
                    $rel_to_query .= '(rel_id IN (SELECT id FROM ' . db_prefix() . 'projects WHERE clientid=' . $this->ci->db->escape_str($rel_id) . ')';
                }

                $rel_to_query .= ' AND rel_type="' . $this->ci->db->escape_str($rel_to) . '")';
                if ($rel_to != $lastElement) {
                    $rel_to_query .= ' OR ';
                }
            }

            $rel_to_query .= ')';

            array_push($where, $rel_to_query);
        }

        $join = [];

        $custom_fields = get_table_custom_fields('tasks');

        foreach ($custom_fields as $key => $field) {
            $selectAs = (is_cf_date($field) ? 'date_picker_cvalue_' . $key : 'cvalue_' . $key);
            array_push($customFieldsColumns, $selectAs);
            array_push($aColumns, '(SELECT value FROM ' . db_prefix() . 'customfieldsvalues WHERE ' . db_prefix() . 'customfieldsvalues.relid=' . db_prefix() . 'tasks.id AND ' . db_prefix() . 'customfieldsvalues.fieldid=' . $field['id'] . ' AND ' . db_prefix() . 'customfieldsvalues.fieldto="' . $field['fieldto'] . '" LIMIT 1) as ' . $selectAs);
        }

        $aColumns = hooks()->apply_filters('tasks_related_table_sql_columns', $aColumns);

        // Fix for big queries. Some hosting have max_join_limit
        if (count($custom_fields) > 4) {
            @$this->ci->db->query('SET SQL_BIG_SELECTS=1');
        }

        $result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [
            'billed',
            'recurring',
            '(SELECT staffid FROM ' . db_prefix() . 'task_assigned WHERE taskid=' . db_prefix() . 'tasks.id AND staffid=' . get_staff_user_id() . ') as is_assigned',
            get_sql_select_task_assignees_ids() . ' as assignees_ids',
            '(SELECT MAX(id) FROM ' . db_prefix() . 'taskstimers WHERE task_id=' . db_prefix() . 'tasks.id and staff_id=' . get_staff_user_id() . ' and end_time IS NULL) as not_finished_timer_by_current_staff',
            '(SELECT staffid FROM ' . db_prefix() . 'task_assigned WHERE taskid=' . db_prefix() . 'tasks.id AND staffid=' . get_staff_user_id() . ') as current_user_is_assigned',
            '(SELECT CASE WHEN addedfrom=' . get_staff_user_id() . ' AND is_added_from_contact=0 THEN 1 ELSE 0 END) as current_user_is_creator',
        ]);

        $output  = $result['output'];
        $rResult = $result['rResult'];

        foreach ($rResult as $aRow) {
            require_once APPPATH.'services/NativeTaskRowPresentation.php';
            $row=NativeTaskRowPresentation::render($aRow,$hasPermissionEdit,$hasPermissionDelete,$task_statuses,$tasksPriorities,
                ['url'=>'admin_url','translate'=>'_l','escape'=>'e','date'=>'_d','statusById'=>'get_task_status_by_id','statusLabel'=>'format_task_status','brightness'=>'adjust_hex_brightness','members'=>'format_members_by_ids_and_names','tags'=>'render_tags','priorityColor'=>'task_priority_color','priorityLabel'=>'task_priority'], $customFieldsColumns??[],date('Y-m-d'));

            $row = hooks()->apply_filters('tasks_related_table_row_data', $row, $aRow);

            $output['aaData'][] = $row;
        }

        return $output;
    });
