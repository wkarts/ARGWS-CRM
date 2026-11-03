<?php

defined('BASEPATH') or exit('No direct script access allowed');
require_once APPPATH.'services/NativeAdminMenuCatalog.php';

function app_init_admin_sidebar_menu_items()
{
    $CI = &get_instance();

    $CI->app_menu->add_sidebar_menu_item('dashboard', NativeAdminMenuCatalog::legacy('sidebar_menu:dashboard'));

    if (
        staff_can('view',  'customers')
        || (have_assigned_customers()
            || (!have_assigned_customers() && staff_can('create',  'customers')))
    ) {
        $CI->app_menu->add_sidebar_menu_item('customers', NativeAdminMenuCatalog::legacy('sidebar_menu:customers'));
    }

    $CI->app_menu->add_sidebar_menu_item('sales', NativeAdminMenuCatalog::legacy('sidebar_menu:sales'));

    if ((staff_can('view',  'proposals') || staff_can('view_own',  'proposals'))
        || (staff_has_assigned_proposals() && get_option('allow_staff_view_proposals_assigned') == 1)
    ) {
        $CI->app_menu->add_sidebar_children_item('sales', NativeAdminMenuCatalog::legacy('sidebar_children:proposals'));
    }

    if ((staff_can('view',  'estimates') || staff_can('view_own',  'estimates'))
        || (staff_has_assigned_estimates() && get_option('allow_staff_view_estimates_assigned') == 1)
    ) {
        $CI->app_menu->add_sidebar_children_item('sales', NativeAdminMenuCatalog::legacy('sidebar_children:estimates'));
    }

    if ((staff_can('view',  'invoices') || staff_can('view_own',  'invoices'))
        || (staff_has_assigned_invoices() && get_option('allow_staff_view_invoices_assigned') == 1)
    ) {
        $CI->app_menu->add_sidebar_children_item('sales', NativeAdminMenuCatalog::legacy('sidebar_children:invoices'));
    }

    if (
        staff_can('view',  'payments') || staff_can('view_own',  'invoices')
        || (get_option('allow_staff_view_invoices_assigned') == 1 && staff_has_assigned_invoices())
    ) {
        $CI->app_menu->add_sidebar_children_item('sales', NativeAdminMenuCatalog::legacy('sidebar_children:payments'));
    }

    if (staff_can('view',  'credit_notes') || staff_can('view_own',  'credit_notes')) {
        $CI->app_menu->add_sidebar_children_item('sales', NativeAdminMenuCatalog::legacy('sidebar_children:credit_notes'));
    }

    if (staff_can('view',  'items')) {
        $CI->app_menu->add_sidebar_children_item('sales', NativeAdminMenuCatalog::legacy('sidebar_children:items'));
    }

    if (staff_can('view',  'subscriptions') || staff_can('view_own',  'subscriptions')) {
        $CI->app_menu->add_sidebar_menu_item('subscriptions', NativeAdminMenuCatalog::legacy('sidebar_menu:subscriptions'));
    }

    if (staff_can('view',  'expenses') || staff_can('view_own',  'expenses')) {
        $CI->app_menu->add_sidebar_menu_item('expenses', NativeAdminMenuCatalog::legacy('sidebar_menu:expenses'));
    }

    if (staff_can('view',  'contracts') || staff_can('view_own',  'contracts')) {
        $CI->app_menu->add_sidebar_menu_item('contracts', NativeAdminMenuCatalog::legacy('sidebar_menu:contracts'));
    }

    $CI->app_menu->add_sidebar_menu_item('projects', NativeAdminMenuCatalog::legacy('sidebar_menu:projects'));

    $CI->app_menu->add_sidebar_menu_item('tasks', NativeAdminMenuCatalog::legacy('sidebar_menu:tasks'));

    if ((!is_staff_member() && get_option('access_tickets_to_none_staff_members') == 1) || is_staff_member()) {
        $enable_badge = get_option('enable_support_menu_badges');
        $CI->app_menu->add_sidebar_menu_item('support', array_replace(NativeAdminMenuCatalog::legacy('sidebar_menu:support'), ['collapse'=>$enable_badge ? true : null]));

        $CI->load->model('tickets_model');
        $statuses = $CI->tickets_model->get_ticket_status();

        if ($enable_badge) {
            foreach ($statuses as $status) {
                $CI->app_menu->add_sidebar_children_item('support', [
                    'slug'     => 'support-' . $status['ticketstatusid'],
                    'name'     => ticket_status_translate($status['ticketstatusid']),
                    'href'     => admin_url('tickets/index/' . $status['ticketstatusid']),
                    'position' => $status['statusorder'],
                    'badge'    => [
                        'value' => $CI->tickets_model->ticket_count($status['ticketstatusid']),
                        'color' => $status['statuscolor'],
                    ],
                ]);
            }
        }
    }

    if (is_staff_member()) {
        $CI->app_menu->add_sidebar_menu_item('leads', NativeAdminMenuCatalog::legacy('sidebar_menu:leads'));
    }

    if ((staff_can('view',  'estimate_request') || staff_can('view_own',  'estimate_request'))) {
        $CI->app_menu->add_sidebar_menu_item('estimate_request', NativeAdminMenuCatalog::legacy('sidebar_menu:estimate_request'));
    }

    if (staff_can('view',  'knowledge_base')) {
        $CI->app_menu->add_sidebar_menu_item('knowledge-base', NativeAdminMenuCatalog::legacy('sidebar_menu:knowledge-base'));
    }

    // Utilities
    $CI->app_menu->add_sidebar_menu_item('utilities', NativeAdminMenuCatalog::legacy('sidebar_menu:utilities'));

    $CI->app_menu->add_sidebar_children_item('utilities', NativeAdminMenuCatalog::legacy('sidebar_children:media'));

    if (staff_can('view',  'bulk_pdf_exporter')) {
        $CI->app_menu->add_sidebar_children_item('utilities', NativeAdminMenuCatalog::legacy('sidebar_children:bulk-pdf-exporter'));
    }

    $CI->app_menu->add_sidebar_children_item('utilities', NativeAdminMenuCatalog::legacy('sidebar_children:calendar'));


    if (is_admin()) {
        $CI->app_menu->add_sidebar_children_item('utilities', NativeAdminMenuCatalog::legacy('sidebar_children:announcements'));

        $CI->app_menu->add_sidebar_children_item('utilities', NativeAdminMenuCatalog::legacy('sidebar_children:activity-log'));

        $CI->app_menu->add_sidebar_children_item('utilities', NativeAdminMenuCatalog::legacy('sidebar_children:ticket-pipe-log'));
    }

    if (staff_can('view-timesheets', 'reports') || staff_can('view', 'reports')) {
        $CI->app_menu->add_sidebar_menu_item('reports', NativeAdminMenuCatalog::legacy('sidebar_menu:reports'));
    }

    if (staff_can('view-timesheets', 'reports')) {
        $CI->app_menu->add_sidebar_children_item('reports', NativeAdminMenuCatalog::legacy('sidebar_children:timesheets-reports'));
    }

    if (staff_can('view',  'reports')) {
        $CI->app_menu->add_sidebar_children_item('reports', NativeAdminMenuCatalog::legacy('sidebar_children:sales-reports'));
        $CI->app_menu->add_sidebar_children_item('reports', NativeAdminMenuCatalog::legacy('sidebar_children:expenses-reports'));
        $CI->app_menu->add_sidebar_children_item('reports', NativeAdminMenuCatalog::legacy('sidebar_children:expenses-vs-income-reports'));
        $CI->app_menu->add_sidebar_children_item('reports', NativeAdminMenuCatalog::legacy('sidebar_children:leads-reports'));
        $CI->app_menu->add_sidebar_children_item('reports', NativeAdminMenuCatalog::legacy('sidebar_children:knowledge-base-reports'));
    }

    // Setup menu: companies are a native core resource, immediately before Staff.
    if (issuer_core_ready() && (is_admin() || count((new IssuerRegistry($CI->db, db_prefix()))->available(get_staff_user_id())) > 0)) {
        $CI->app_menu->add_setup_menu_item('issuers', NativeAdminMenuCatalog::legacy('setup_menu:issuers'));
    }
    if (staff_can('view',  'staff')) {
        $CI->app_menu->add_setup_menu_item('staff', NativeAdminMenuCatalog::legacy('setup_menu:staff'));
    }

    if (is_admin()) {
        $CI->app_menu->add_setup_menu_item('customers', NativeAdminMenuCatalog::legacy('setup_menu:customers'));

        $CI->app_menu->add_setup_children_item('customers', NativeAdminMenuCatalog::legacy('setup_children:customer-groups'));
        $CI->app_menu->add_setup_menu_item('support', NativeAdminMenuCatalog::legacy('setup_menu:support'));

        $CI->app_menu->add_setup_children_item('support', NativeAdminMenuCatalog::legacy('setup_children:departments'));
        $CI->app_menu->add_setup_children_item('support', NativeAdminMenuCatalog::legacy('setup_children:tickets-predefined-replies'));
        $CI->app_menu->add_setup_children_item('support', NativeAdminMenuCatalog::legacy('setup_children:tickets-priorities'));
        $CI->app_menu->add_setup_children_item('support', NativeAdminMenuCatalog::legacy('setup_children:tickets-statuses'));

        $CI->app_menu->add_setup_children_item('support', NativeAdminMenuCatalog::legacy('setup_children:tickets-services'));
        $CI->app_menu->add_setup_children_item('support', NativeAdminMenuCatalog::legacy('setup_children:tickets-spam-filters'));

        $CI->app_menu->add_setup_menu_item('leads', NativeAdminMenuCatalog::legacy('setup_menu:leads'));
        $CI->app_menu->add_setup_children_item('leads', NativeAdminMenuCatalog::legacy('setup_children:leads-sources'));
        $CI->app_menu->add_setup_children_item('leads', NativeAdminMenuCatalog::legacy('setup_children:leads-statuses'));
        $CI->app_menu->add_setup_children_item('leads', NativeAdminMenuCatalog::legacy('setup_children:leads-email-integration'));
        $CI->app_menu->add_setup_children_item('leads', NativeAdminMenuCatalog::legacy('setup_children:web-to-lead'));

        $CI->app_menu->add_setup_menu_item('finance', NativeAdminMenuCatalog::legacy('setup_menu:finance'));
        $CI->app_menu->add_setup_children_item('finance', NativeAdminMenuCatalog::legacy('setup_children:taxes'));
        $CI->app_menu->add_setup_children_item('finance', NativeAdminMenuCatalog::legacy('setup_children:currencies'));
        $CI->app_menu->add_setup_children_item('finance', NativeAdminMenuCatalog::legacy('setup_children:payment-modes'));
        $CI->app_menu->add_setup_children_item('finance', NativeAdminMenuCatalog::legacy('setup_children:expenses-categories'));

        $CI->app_menu->add_setup_menu_item('contracts', NativeAdminMenuCatalog::legacy('setup_menu:contracts'));
        $CI->app_menu->add_setup_children_item('contracts', NativeAdminMenuCatalog::legacy('setup_children:contracts-types'));

        $modulesNeedsUpgrade = $CI->app_modules->number_of_modules_that_require_database_upgrade();

        $CI->app_menu->add_setup_menu_item('modules', [
            'href'     => admin_url('modules'),
            'name'     => _l('modules'),
            'position' => 35,
            'badge'    => [
                'value' => $modulesNeedsUpgrade > 0 ? $modulesNeedsUpgrade : null,
                'type' => 'warning',
            ],
        ]);

        $CI->app_menu->add_setup_menu_item('custom-fields', NativeAdminMenuCatalog::legacy('setup_menu:custom-fields'));

        $CI->app_menu->add_setup_menu_item('gdpr', NativeAdminMenuCatalog::legacy('setup_menu:gdpr'));

        $CI->app_menu->add_setup_menu_item('roles', NativeAdminMenuCatalog::legacy('setup_menu:roles'));

        /*             $CI->app_menu->add_setup_menu_item('api', [
                          'href'     => admin_url('api'),
                          'name'     => 'API',
                          'position' => 65,
                  ]);*/
    }

    if (staff_can('view',  'settings')) {
        $CI->app_menu->add_setup_menu_item('settings', NativeAdminMenuCatalog::legacy('setup_menu:settings'));
    }

    if (staff_can('view',  'email_templates')) {
        $CI->app_menu->add_setup_menu_item('email-templates', NativeAdminMenuCatalog::legacy('setup_menu:email-templates'));
    }

    if (staff_can('view',  'settings')) {
        $CI->app_menu->add_setup_menu_item('estimate_request', NativeAdminMenuCatalog::legacy('setup_menu:estimate_request'));
    }

    $CI->app_menu->add_setup_children_item('estimate_request', NativeAdminMenuCatalog::legacy('setup_children:estimate-request-forms'));

    $CI->app_menu->add_setup_children_item('estimate_request', NativeAdminMenuCatalog::legacy('setup_children:estimate-request-statuses'));
}