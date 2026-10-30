<?php
function original_app_init_project_tabs()
{
    $CI = &get_instance();

    $CI->app_tabs->add_project_tab('project_overview', [
        'name'     => _l('project_overview'),
        'icon'     => 'fa fa-th',
        'view'     => 'admin/projects/project_overview',
        'position' => 5,
    ]);

    $CI->app_tabs->add_project_tab('project_tasks', [
        'name'                      => _l('tasks'),
        'icon'                      => 'fa-regular fa-check-circle',
        'view'                      => 'admin/projects/project_tasks',
        'position'                  => 10,
        'linked_to_customer_option' => ['view_tasks'],
    ]);

    $CI->app_tabs->add_project_tab('project_timesheets', [
        'name'                      => _l('project_timesheets'),
        'icon'                      => 'fa-regular fa-clock',
        'view'                      => 'admin/projects/project_timesheets',
        'position'                  => 15,
        'linked_to_customer_option' => ['view_timesheets'],
    ]);

    $CI->app_tabs->add_project_tab('project_milestones', [
        'name'                      => _l('project_milestones'),
        'icon'                      => 'fa fa-rocket',
        'view'                      => 'admin/projects/project_milestones',
        'position'                  => 20,
        'linked_to_customer_option' => ['view_milestones'],
    ]);

    $CI->app_tabs->add_project_tab('project_files', [
        'name'                      => _l('project_files'),
        'icon'                      => 'fa-solid fa-file',
        'view'                      => 'admin/projects/project_files',
        'position'                  => 25,
        'linked_to_customer_option' => ['upload_files'],
    ]);

    $CI->app_tabs->add_project_tab('project_discussions', [
        'name'                      => _l('project_discussions'),
        'icon'                      => 'fa-regular fa-message',
        'view'                      => 'admin/projects/project_discussions',
        'position'                  => 30,
        'linked_to_customer_option' => ['open_discussions'],
    ]);

    $CI->app_tabs->add_project_tab('project_gantt', [
        'name'                      => _l('project_gant'),
        'icon'                      => 'fa-solid fa-chart-gantt',
        'view'                      => 'admin/projects/project_gantt',
        'position'                  => 35,
        'linked_to_customer_option' => ['view_gantt'],
    ]);

    $CI->app_tabs->add_project_tab('project_tickets', [
        'name'     => _l('project_tickets'),
        'icon'     => 'fa fa-life-ring',
        'view'     => 'admin/projects/project_tickets',
        'position' => 40,
        'visible'  => (get_option('access_tickets_to_none_staff_members') == 1 && ! is_staff_member()) || is_staff_member(),
    ]);

    $CI->app_tabs->add_project_tab('project_contracts', [
        'name'     => _l('contracts'),
        'icon'     => 'fa-solid fa-file-contract',
        'view'     => 'admin/projects/project_contracts',
        'position' => 45,
        'visible'  => staff_can('view', 'contracts') || staff_can('view_own', 'contracts'),
    ]);

    $CI->app_tabs->add_project_tab('sales', [
        'name'     => _l('sales_string'),
        'icon'     => 'fa-solid fa-bolt',
        'position' => 50,
        'collapse' => true,
        'visible'  => (staff_can('view', 'estimates') || staff_can('view_own', 'estimates') || (get_option('allow_staff_view_estimates_assigned') == 1 && staff_has_assigned_estimates()))
            || (staff_can('view', 'invoices') || staff_can('view_own', 'invoices') || (get_option('allow_staff_view_invoices_assigned') == 1 && staff_has_assigned_invoices()))
            || (staff_can('view', 'expenses') || staff_can('view_own', 'expenses'))
            || (staff_can('view_own', 'proposals') || (get_option('allow_staff_view_proposals_assigned') == 1 && staff_has_assigned_proposals())),
    ]);

    $CI->app_tabs->add_project_tab_children_item('sales', [
        'slug'     => 'project_invoices',
        'name'     => _l('project_invoices'),
        'view'     => 'admin/projects/project_invoices',
        'position' => 15,
        'visible'  => (staff_can('view', 'invoices') || staff_can('view_own', 'invoices') || (get_option('allow_staff_view_invoices_assigned') == 1 && staff_has_assigned_invoices())),
    ]);

    $CI->app_tabs->add_project_tab_children_item('sales', [
        'slug'     => 'project_estimates',
        'name'     => _l('estimates'),
        'view'     => 'admin/projects/project_estimates',
        'position' => 10,
        'visible'  => (staff_can('view', 'estimates') || staff_can('view_own', 'estimates') || (get_option('allow_staff_view_estimates_assigned') == 1 && staff_has_assigned_estimates())),
    ]);

    $CI->app_tabs->add_project_tab_children_item('sales', [
        'slug'     => 'project_expenses',
        'name'     => _l('project_expenses'),
        'view'     => 'admin/projects/project_expenses',
        'position' => 25,
        'visible'  => staff_can('view', 'expenses') || staff_can('view_own', 'expenses'),
    ]);

    $CI->app_tabs->add_project_tab_children_item('sales', [
        'slug'     => 'project_credit_notes',
        'name'     => _l('credit_notes'),
        'view'     => 'admin/projects/project_credit_notes',
        'position' => 30,
        'visible'  => staff_can('view', 'credit_notes') || staff_can('view_own', 'credit_notes'),
    ]);

    $CI->app_tabs->add_project_tab_children_item('sales', [
        'slug'     => 'project_subscriptions',
        'name'     => _l('subscriptions'),
        'view'     => 'admin/projects/project_subscriptions',
        'position' => 20,
        'visible'  => staff_can('view', 'subscriptions') || staff_can('view_own', 'subscriptions'),
    ]);

    $CI->app_tabs->add_project_tab_children_item('sales', [
        'slug'     => 'project_proposals',
        'name'     => _l('proposals'),
        'view'     => 'admin/projects/project_proposals',
        'position' => 5,
        'visible'  => (staff_can('view', 'proposals') || staff_can('view_own', 'proposals') || (get_option('allow_staff_view_proposals_assigned') == 1 && staff_has_assigned_proposals())),
    ]);

    $CI->app_tabs->add_project_tab('project_notes', [
        'name'     => _l('project_notes'),
        'icon'     => 'fa-regular fa-note-sticky',
        'view'     => 'admin/projects/project_notes',
        'position' => 55,
    ]);

    $CI->app_tabs->add_project_tab('project_activity', [
        'name'                      => _l('project_activity'),
        'icon'                      => 'fa-regular fa-file-lines',
        'view'                      => 'admin/projects/project_activity',
        'position'                  => 60,
        'linked_to_customer_option' => ['view_activity_log'],
    ]);
}
