<?php

// Inject sidebar menu and links for webhooks module
hooks()->add_action('admin_init', function (){
    $CI = &get_instance();
    if (has_permission('webhooks', '', 'view')) {
        $CI->app_menu->add_sidebar_menu_item('webhooks', [
            'slug' => 'webhooks',
            'name' => _l('webhooks'),
            'icon' => 'fa fa-handshake-o menu-icon fa-duotone fa-circle-nodes',
            'href' => 'webhooks',
            'position' => 30,
        ]);
    }

    if (has_permission('webhooks', '', 'view')) {
        $CI->app_menu->add_sidebar_children_item('webhooks', [
            'slug' => 'webhooks',
            'name' => _l('webhooks'),
            'icon' => 'fa fa-compress',
            'href' => admin_url(WEBHOOKS_MODULE),
            'position' => 1,
        ]);
    }

    if (has_permission('webhooks', '', 'view')) {
        $CI->app_menu->add_sidebar_children_item('webhooks', [
            'slug' => 'webhook_log',
            'name' => _l('webhook_log'),
            'icon' => 'fa fa-history',
            'href' => admin_url(WEBHOOKS_MODULE . '/logs'),
            'position' => 2,
        ]);
    }

    if (has_permission('webhooks', '', 'view')) {
        $CI->app_menu->add_sidebar_children_item('webhooks', [
            'slug' => 'webhooks_cron',
            'name' => _l('webhooks_cron'),
            'icon' => 'fa fa-fan',
            'href' => admin_url('settings?group=webhooks'),
            'position' => 3,
        ]);
    }

    $CI->app_tabs->add_settings_tab('webhooks', [
        'name' => _l('webhooks_cron_job'),
        'view' => 'webhooks/settings/webhooks_cron_job',
        'position' => 50,
    ]);
});