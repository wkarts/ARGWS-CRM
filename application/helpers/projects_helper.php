<?php

defined('BASEPATH') or exit('No direct script access allowed');

hooks()->add_action('app_admin_assets', '_maybe_init_admin_project_assets', 5);

function _maybe_init_admin_project_assets()
{
    $CI = &get_instance();
    if (strpos($_SERVER['REQUEST_URI'], get_admin_uri() . '/projects/view') !== false
        || strpos($_SERVER['REQUEST_URI'], get_admin_uri() . '/projects/gantt') !== false) {
        $CI = &get_instance();

        $CI->app_scripts->add('jquery-comments-js', 'assets/plugins/jquery-comments/js/jquery-comments.min.js', 'admin', ['vendor-js']);
        $CI->app_scripts->add('frappe-gantt-js', 'assets/plugins/frappe/frappe-gantt-es2015.js', 'admin', ['vendor-js']);

        $CI->app_css->add('frappe-gantt-js', 'assets/plugins//frappe/frappe-gantt.css', 'admin', ['vendor-css']);
        $CI->app_css->add('jquery-comments-css', 'assets/plugins/jquery-comments/css/jquery-comments.css', 'admin', ['reset-css']);
    }
}

/**
 * Default project tabs
 *
 * @return array
 */
function get_project_tabs_admin()
{
    return get_instance()->app_tabs->get_project_tabs();
}

/**
 * Init the default project tabs
 *
 * @return null
 */
function app_init_project_tabs()
{
    $CI = &get_instance();
    require_once APPPATH.'services/NativeProjectTabs.php';
    NativeProjectTabs::register($CI->app_tabs, '_l', [
        'project_tickets' => (get_option('access_tickets_to_none_staff_members') == 1 && ! is_staff_member()) || is_staff_member(),
        'project_contracts' => staff_can('view', 'contracts') || staff_can('view_own', 'contracts'),
        'sales' => (staff_can('view', 'estimates') || staff_can('view_own', 'estimates') || (get_option('allow_staff_view_estimates_assigned') == 1 && staff_has_assigned_estimates()))
            || (staff_can('view', 'invoices') || staff_can('view_own', 'invoices') || (get_option('allow_staff_view_invoices_assigned') == 1 && staff_has_assigned_invoices()))
            || (staff_can('view', 'expenses') || staff_can('view_own', 'expenses'))
            || (staff_can('view_own', 'proposals') || (get_option('allow_staff_view_proposals_assigned') == 1 && staff_has_assigned_proposals())),
        'project_invoices' => (staff_can('view', 'invoices') || staff_can('view_own', 'invoices') || (get_option('allow_staff_view_invoices_assigned') == 1 && staff_has_assigned_invoices())),
        'project_estimates' => (staff_can('view', 'estimates') || staff_can('view_own', 'estimates') || (get_option('allow_staff_view_estimates_assigned') == 1 && staff_has_assigned_estimates())),
        'project_expenses' => staff_can('view', 'expenses') || staff_can('view_own', 'expenses'),
        'project_credit_notes' => staff_can('view', 'credit_notes') || staff_can('view_own', 'credit_notes'),
        'project_subscriptions' => staff_can('view', 'subscriptions') || staff_can('view_own', 'subscriptions'),
        'project_proposals' => (staff_can('view', 'proposals') || staff_can('view_own', 'proposals') || (get_option('allow_staff_view_proposals_assigned') == 1 && staff_has_assigned_proposals())),
    ]);
}

/**
 * Filter only visible tabs selected from project settings
 *
 * @param array $tabs             available tabs
 * @param array $applied_settings current applied project visible tabs
 *
 * @return array
 */
function filter_project_visible_tabs($tabs, $applied_settings)
{
    $newTabs = [];

    foreach ($tabs as $key => $tab) {
        $dropdown = isset($tab['collapse']) ? true : false;

        if ($dropdown) {
            $totalChildTabsHidden = 0;
            $newChild             = [];

            foreach ($tab['children'] as $d) {
                if ((isset($applied_settings[$d['slug']]) && $applied_settings[$d['slug']] == 0)) {
                    $totalChildTabsHidden++;
                } else {
                    $newChild[] = $d;
                }
            }

            if ($totalChildTabsHidden == count($tab['children'])) {
                continue;
            }

            if (count($newChild) > 0) {
                $tab['children'] = $newChild;
            }

            $newTabs[$tab['slug']] = $tab;
        } else {
            if (isset($applied_settings[$key]) && $applied_settings[$key] == 0) {
                continue;
            }

            $newTabs[$tab['slug']] = $tab;
        }
    }

    return hooks()->apply_filters('project_filtered_visible_tabs', $newTabs);
}

/**
 * Get project by ID or current queried project
 *
 * @param mixed $id project id
 *
 * @return mixed
 */
function get_project($id = null)
{
    if (empty($id) && isset($GLOBALS['project'])) {
        return $GLOBALS['project'];
    }

    // Client global object not set
    if (empty($id)) {
        return null;
    }

    if (! class_exists('projects_model', false)) {
        get_instance()->load->model('projects_model');
    }

    $project = get_instance()->projects_model->get($id);

    return $project;
}

/**
 * Get project status by passed project id
 *
 * @param mixed $id project id
 *
 * @return array
 */
function get_project_status_by_id($id)
{
    $CI = &get_instance();
    if (! class_exists('projects_model')) {
        $CI->load->model('projects_model');
    }

    $statuses = $CI->projects_model->get_project_statuses();

    $status = [
        'id'    => 0,
        'color' => '#333',
        'name'  => '[Status Not Found]',
        'order' => 1,
    ];

    foreach ($statuses as $s) {
        if ($s['id'] == $id) {
            $status = $s;

            break;
        }
    }

    return $status;
}

/**
 * Return logged in user pinned projects
 *
 * @return array
 */
function get_user_pinned_projects()
{
    $CI = &get_instance();
    \app\services\ClientIssuerScope::applyLegacy($CI->db, db_prefix(), db_prefix().'projects', 'clientid');
    $CI->db->select(db_prefix() . 'projects.id, ' . db_prefix() . 'projects.name, ' . db_prefix() . 'projects.clientid, ' . get_sql_select_client_company());
    $CI->db->join(db_prefix() . 'projects', db_prefix() . 'projects.id=' . db_prefix() . 'pinned_projects.project_id');
    $CI->db->join(db_prefix() . 'clients', db_prefix() . 'clients.userid=' . db_prefix() . 'projects.clientid');
    $CI->db->where(db_prefix() . 'pinned_projects.staff_id', get_staff_user_id());
    $projects = $CI->db->get(db_prefix() . 'pinned_projects')->result_array();
    $CI->load->model('projects_model');

    foreach ($projects as $key => $project) {
        $projects[$key]['progress'] = $CI->projects_model->calc_progress($project['id']);
    }

    return $projects;
}

/**
 * Get project name by passed id
 *
 * @param mixed $id
 *
 * @return string
 */
function get_project_name_by_id($id)
{
    $CI      = &get_instance();
    if (!\app\services\CustomerRelationBoundary::permitsRecord($CI->db, db_prefix(), 'projects', $id)) return '';
    $project = $CI->app_object_cache->get('project-name-data-' . $id);

    if (! $project) {
        $CI->db->select('name');
        $CI->db->where('id', $id);
        $project = $CI->db->get(db_prefix() . 'projects')->row();
        $CI->app_object_cache->add('project-name-data-' . $id, $project);
    }

    if ($project) {
        return $project->name;
    }

    return '';
}

/**
 * Return project milestones
 *
 * @param mixed $project_id project id
 *
 * @return array
 */
function get_project_milestones($project_id)
{
    $CI = &get_instance();
    if (!\app\services\CustomerRelationBoundary::permitsRecord($CI->db, db_prefix(), 'projects', $project_id)) return [];
    $CI->db->where('project_id', $project_id);
    $CI->db->order_by('milestone_order', 'ASC');

    return $CI->db->get(db_prefix() . 'milestones')->result_array();
}

/**
 * Get project client id by passed project id
 *
 * @param mixed $id project id
 *
 * @return mixed
 */
function get_client_id_by_project_id($id)
{
    $CI = &get_instance();
    if (!\app\services\CustomerRelationBoundary::permitsRecord($CI->db, db_prefix(), 'projects', $id)) return false;
    $CI->db->select('clientid');
    $CI->db->where('id', $id);
    $project = $CI->db->get(db_prefix() . 'projects')->row();
    if ($project) {
        return $project->clientid;
    }

    return false;
}

/**
 * Check if customer has project assigned
 *
 * @param mixed $customer_id customer id to check
 *
 * @return bool
 */
function customer_has_projects($customer_id)
{
    $totalCustomerProjects = total_rows(db_prefix() . 'projects', 'clientid=' . get_instance()->db->escape_str($customer_id));

    return $totalCustomerProjects > 0 ? true : false;
}

/**
 * Get project billing type
 *
 * @param mixed $project_id
 *
 * @return mixed
 */
function get_project_billing_type($project_id)
{
    $CI = &get_instance();
    $CI->db->select('billing_type');
    $CI->db->where('id', $project_id);
    $project = $CI->db->get(db_prefix() . 'projects')->row();
    if ($project) {
        return $project->billing_type;
    }

    return false;
}
/**
 * Get project deadline
 *
 * @param mixed $project_id
 *
 * @return mixed
 */
function get_project_deadline($project_id)
{
    $CI = &get_instance();
    $CI->db->select('deadline');
    $CI->db->where('id', $project_id);
    $project = $CI->db->get(db_prefix() . 'projects')->row();
    if ($project) {
        return $project->deadline;
    }

    return false;
}

/**
 * Translated jquery-comment language based on app languages
 * This feature is used on both admin and customer area
 *
 * @return array
 */
function get_project_discussions_language_array()
{
    return [
        'discussion_add_comment'      => _l('discussion_add_comment'),
        'discussion_newest'           => _l('discussion_newest'),
        'discussion_oldest'           => _l('discussion_oldest'),
        'discussion_attachments'      => _l('discussion_attachments'),
        'discussion_send'             => _l('discussion_send'),
        'discussion_reply'            => _l('discussion_reply'),
        'discussion_edit'             => _l('discussion_edit'),
        'discussion_edited'           => _l('discussion_edited'),
        'discussion_you'              => _l('discussion_you'),
        'discussion_save'             => _l('discussion_save'),
        'discussion_delete'           => _l('discussion_delete'),
        'discussion_view_all_replies' => _l('discussion_view_all_replies'),
        'discussion_hide_replies'     => _l('discussion_hide_replies'),
        'discussion_no_comments'      => _l('discussion_no_comments'),
        'discussion_no_attachments'   => _l('discussion_no_attachments'),
        'discussion_attachments_drop' => _l('discussion_attachments_drop'),
    ];
}

/**
 * Check if project has recurring tasks
 *
 * @param mixed $id project id
 *
 * @return bool
 */
function project_has_recurring_tasks($id)
{
    return total_rows(db_prefix() . 'tasks', 'recurring=1 AND rel_id="' . get_instance()->db->escape_str($id) . '" AND rel_type="project"') > 0;
}

function total_project_tasks_by_milestone($milestone_id, $project_id)
{
    return total_rows(db_prefix() . 'tasks', [
        'rel_type'  => 'project',
        'rel_id'    => $project_id,
        'milestone' => $milestone_id,
    ]);
}

function total_project_finished_tasks_by_milestone($milestone_id, $project_id)
{
    return total_rows(db_prefix() . 'tasks', [
        'rel_type'  => 'project',
        'rel_id'    => $project_id,
        'status'    => 5,
        'milestone' => $milestone_id,
    ]);
}
