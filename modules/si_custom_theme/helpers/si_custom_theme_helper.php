<?php
defined('BASEPATH') or exit('No direct script access allowed');

function si_custom_theme_get_styling_areas($type = 'admin')
{
	$areas = [
		'admin' => [
			[
				'name'                 => _l('si_ct_sidebar_bg_color'),
				'id'                   => 'admin-menu',
				'target'               => '.admin #side-menu,.admin #setup-menu,.admin .sidebar, .sm\:tw-bg-neutral-900\/50',//.admin .sidevar,.sm\:tw-bg-neutral-900\/50 
				'css'                  => 'background',
				'additional_selectors' => 'body|background+#setup-menu-wrapper|background',
			],
			[
				'name'                 => _l('si_ct_sidebar_open_bg_color'),
				'id'                   => 'admin-menu-submenu-open',
				'target'               => '.admin #side-menu li .nav-second-level li,.admin #setup-menu li .nav-second-level li',
				'css'                  => 'background',
				'additional_selectors' => '',
			],
			[
				'name'                 => _l('si_ct_sidebar_links_color'),
				'id'                   => 'admin-menu-links',
				'target'               => '.admin #side-menu li a,.admin #setup-menu li a,#setup-menu li:first-child',
				'css'                  => 'color',
				'additional_selectors' => '',
			],
			[
				'name'                 => _l('si_ct_sidebar_user_welcome_bg_color'),
				'id'                   => 'user-welcome-bg-color',
				'target'               => '#side-menu li.dashboard_user',
				'css'                  => 'background-color',
				'additional_selectors' => '',
			],
			[
				'name'                 => _l('si_ct_sidebar_user_welcome_text_color'),
				'id'                   => 'user-welcome-text-color',
				'target'               => '#side-menu li.dashboard_user',
				'css'                  => 'color',
				'additional_selectors' => '',
			],
			[
				'name'   => _l('si_ct_sidebar_active_item_bg_color'),
				'id'     => 'admin-menu-active-item',
				'target' => '
				.admin #side-menu li.active > a,
				.admin #setup-menu li.active > a,
				#side-menu.nav > li > a:hover,
				#side-menu.nav > li > a:focus,
				#setup-menu > li > a:hover,
				#setup-menu > li > a:focus',
				'css'                  => 'background',
				'additional_selectors' => '',
			],
			[
				'name'   => _l('si_ct_sidebar_active_item_color'),
				'id'     => 'admin-menu-active-item-color',
				'target' => '
				.admin #side-menu li.active > a,
				.admin #setup-menu li.active > a,
				#side-menu.nav > li > a:hover,
				#side-menu.nav > li > a:focus,
				#setup-menu > li > a:hover,
				#setup-menu > li > a:focus',
				'css'                  => 'color',
				'additional_selectors' => '.admin #side-menu li.active, .admin #setup-menu li.active|border-left-color',
			],
			[
				'name'                 => _l('si_ct_sidebar_active_sub_item_bg_color'),
				'id'                   => 'admin-menu-active-subitem',
				'target'               => '.admin #side-menu li .nav-second-level li.active a,.admin #setup-menu li .nav-second-level li.active a,
				.admin #side-menu li .nav-second-level > li > a:hover,
				.admin #side-menu li .nav-second-level > li > a:focus,
				.admin #setup-menu li .nav-second-level > li > a:hover,
				.admin #setup-menu li .nav-second-level > li > a:focus',
				'css'                  => 'background',
				'additional_selectors' => '',
			],
			[
				'name'                 => _l('si_ct_sidebar_active_sub_item_links_color'),
				'id'                   => 'admin-menu-submenu-links',
				'target'               => '.admin #side-menu li .nav-second-level li a,.admin #setup-menu li .nav-second-level li a',
				'css'                  => 'color',
				'additional_selectors' => '',
			],
			[
				'name'                 => _l('si_ct_top_header_bg_color'),
				'id'                   => 'top-header',
				'target'               => '.admin #header',
				'css'                  => 'background',
				'additional_selectors' => '',
			],
			[
				'name'                 => _l('si_ct_top_header_bg_links_color'),
				'id'                   => 'top-header-links',
				'target'               => '.admin .navbar-nav > li > a, ul.mobile-icon-menu>li>a,.mobile-menu-toggle, .open-customizer-mobile,.header-timers .tw-text-neutral-900,.header-notifications .tw-text-neutral-900,.navbar-nav .tw-bg-primary-600,.hide-menu, #top_search_button .tw-text-neutral-600',//,.header-timers .tw-text-neutral-900,.header-notifications .tw-text-neutral-900,.navbar-nav .tw-bg-primary-600,.hide-menu, #top_search_button .tw-text-neutral-600 
				'css'                  => 'color',
				'additional_selectors' => '',
			],
		],
		'customers' => [
			[
				'name'                 => _l('si_ct_customer_login_background'),
				'id'                   => 'customer-login-background,.customers .kb-search-jumbotron,.customers .jumbotron', //.customers .kb-search-jumbotron,.customers .jumbotron 
				'target'               => 'body.customers_login',
				'css'                  => 'background',
				'additional_selectors' => '',
			],
			[
				'name'                 => _l('si_ct_navigation_bg_color'),
				'id'                   => 'customers-navigation',
				'target'               => '.customers .navbar-default',
				'css'                  => 'background',
				'additional_selectors' => '',
			],
			[
				'name'                 => _l('si_ct_navigation_link_color'),
				'id'                   => 'customers-navigation-links',
				'target'               => '.customers .navbar-default .navbar-nav>li>a',
				'css'                  => 'color',
				'additional_selectors' => '',
			],
			[
				'name'                 => _l('si_ct_footer_background'),
				'id'                   => 'customers-footer-background',
				'target'               => '.customers footer',
				'css'                  => 'background',
				'additional_selectors' => '',
			],
			[
				'name'                 => _l('si_ct_footer_text_color'),
				'id'                   => 'customers-footer-text',
				'target'               => '.customers footer',
				'css'                  => 'color',
				'additional_selectors' => '',
			],
		],
		'general' => [
			[
				'name'                 => _l('si_ct_admin_login_background'),
				'id'                   => 'admin-login-background',
				'target'               => 'body.login_admin,body.forgot-password',
				'css'                  => 'background',
				'additional_selectors' => '',
			],
			[
				'name'                 => _l('si_ct_admin_page_background'),
				'id'                   => 'admin-page-background',
				'target'               => 'body.customers,body.admin #wrapper', 
				'css'                  => 'background',
				'additional_selectors' => '.fn-gantt .bottom, .fn-gantt .leftPanel .desc, .fn-gantt .rightPanel .month, .fn-gantt .rightPanel .year, .fn-gantt .spacer, .fn-gantt .wd|background-color',
			],
			[
				'name'                 => _l('si_ct_admin_page_text_color'),
				'id'                   => 'admin-page-text-color',
				'target'               => 'body,.input-group-addon,.form-control,.pagination>.disabled>a, .pagination>.disabled>a:focus, .pagination>.disabled>a:hover, .pagination>.disabled>span, .pagination>.disabled>span:focus, .pagination>.disabled>span:hover,.dataTables_empty,div.dataTables_wrapper div.dataTables_info, div.dataTables_wrapper div.dataTables_length label,.btn-default,.dropdown-menu li a,.filter-option-inner-inner,.control-label, label,.dropdown-menu>.active>a, .dropdown-menu>.active>a:focus, .dropdown-menu>.active>a:hover,.label-tag,.mce-menu-item .mce-ico, .mce-menu-item .mce-text,.gantt_project_name,.fn-gantt .leftPanel .fn-label,.fn-gantt .rightPanel .month,.fn-gantt .rightPanel .year,.fn-gantt .sa, .fn-gantt .sn,.fn-gantt .day, .fn-gantt .date,.table>tbody>tr>td .tw-text-neutral-700', //added ,.table>tbody>tr>td .tw-text-neutral-700 
				'css'                  => 'color',
				'additional_selectors' => '.caret|border-color', //added .caret|border-color 
			],
			[
				'name'                 => _l('si_ct_admin_inputs_bg_color'),
				'id'                   => 'admin-inputs-background',
				'target'               => '.input-group-addon,.form-control,.pagination>.disabled>a, .pagination>.disabled>a:focus, .pagination>.disabled>a:hover, .pagination>.disabled>span, .pagination>.disabled>span:focus, .pagination>.disabled>span:hover,.dt-buttons.btn-group .btn,.dropdown-menu,.bootstrap-select .btn-default,.bootstrap-select .btn-default.disabled,.dropdown-menu>li>a:hover,textarea,.bg-stripe, .table.items .main, .table>tbody>tr>td, .table>tfoot>tr>td, form.dropzone, #dropzoneDragArea,.dropzoneDragArea, .mce-panel, .kan-ban-content, .email-template-heading,.dt-button-collection.dropdown-menu>li.active>a, .dt-button-collection.dropdown-menu>li>a:focus, .dt-button-collection.dropdown-menu>li>a:hover,.screen-options-area',
				'css'                  => 'background',
				'additional_selectors' => '.pagination>.disabled>a, .pagination>.disabled>a:focus, .pagination>.disabled>a:hover, .pagination>.disabled>span, .pagination>.disabled>span:focus, .pagination>.disabled>span:hover|border-color+.label-tag|background-color+.list-status a.active|background-color',
			],
			[
				'name'                 => _l('si_ct_admin_inputs_border_color'),
				'id'                   => 'admin-inputs-border',
				'target'               => '.input-group-addon,.form-control,.pagination>.disabled>a, .pagination>.disabled>a:focus, .pagination>.disabled>a:hover, .pagination>.disabled>span, .pagination>.disabled>span:focus, .pagination>.disabled>span:hover,.dt-buttons.btn-group .btn,.dropdown-menu,.bootstrap-select .btn-default,.bootstrap-select .btn-default.disabled,.dropdown-menu>li>a:hover,textarea, .bg-stripe, form.dropzone, #dropzoneDragArea,.dropzoneDragArea, .mce-panel, .kan-ban-content, .email-template-heading,.dt-button-collection.dropdown-menu>li.active>a, .dt-button-collection.dropdown-menu>li>a:focus, .dt-button-collection.dropdown-menu>li>a:hover,.screen-options-area,.btn-default',
				'css'                  => 'border-color',
				'additional_selectors' => '.pagination>.disabled>a, .pagination>.disabled>a:focus, .pagination>.disabled>a:hover, .pagination>.disabled>span, .pagination>.disabled>span:focus, .pagination>.disabled>span:hover|border-color',
			],
			[
				'name'                 => _l('si_ct_admin_horizontal_line_color'),
				'id'                   => 'admin-hr-color',
				'target'               => 'hr',
				'css'                  => 'border-color',
				'additional_selectors' => 'hr|border-top-color+.btn-bottom-toolbar|border-top-color',
			],
			[
				'name'                 => '<a href="#" onclick="return false;">' . _l('si_ct_links') . '</a> ' . _l('si_ct_color') . ' (href)',
				'id'                   => 'links-color',
				'target'               => 'a:not(.btn,.nav-tabs>li>a)',
				'css'                  => 'color',
				'additional_selectors' => '',
			],
			[
				'name'                 => _l('si_ct_link_hover_color'),
				'id'                   => 'links-hover-focus',
				'target'               => 'a:not(.btn,.nav-tabs>li>a):hover,a:not(.btn,.nav-tabs>li>a):focus',
				'css'                  => 'color',
				'additional_selectors' => '',
			],
			[
				'name'                 => _l('si_ct_admin_panel_background'),
				'id'                   => 'admin-panel-background',
				'target'               => '.panel_s .panel-heading:not(.panel-heading-bg,.task-phase,.info-bg), .panel_s .panel-body, .panel_s .panel-footer,.list-group-item,.dt-button-collection.dropdown-menu>li>a,.top_stats_wrapper,.btn-bottom-toolbar,.authentication-form,.task-info-inline-input-edit,.login_admin .tw-bg-white,.forgot-password .tw-bg-white',//,.login_admin .tw-bg-white,.forgot-password .tw-bg-white 
				'css'                  => 'background',
				'additional_selectors' => '.panel_s .panel-heading|border-color+.panel_s .panel-body|border-color+.panel_s .panel-footer|border-color+.top_stats_wrapper|border-color+.fn-gantt .sa, .fn-gantt .sn|background-color',
				'example'              => '<div class="panel_s"><div class="panel-body">' . _l('si_ct_example_admin_panel') . '</div></div>',
			],
			[
				'name'                 => _l('si_ct_admin_panel_heading_color'),
				'id'                   => 'admin-panel-color',
				'target'               => '.panel_s .panel-heading:not(.panel-heading-bg,.info-bg),.panel_s .panel-heading h4, .panel-heading h4,.panel-heading .panel-title a,.customer-profile-group-heading,.panel_s .panel-body h4:not(.panel-body .modal-title),.login_admin h1,.forgot-password h1,.customers h3.tw-text-neutral-700,.customers h4.tw-text-neutral-700,h4.tw-text-neutral-700,h4.tw-text-neutral-700 span,h4.tw-text-neutral-800',//.login_admin h1,.forgot-password h1,.customers h3.tw-text-neutral-700,.customers h4.tw-text-neutral-700,h4.tw-text-neutral-700,h4.tw-text-neutral-700 span,h4.tw-text-neutral-800 
				'css'                  => 'color',
				'additional_selectors' => '.customer-top-submenu li a|color+h4 .caret|border-color',//.customer-top-submenu for client side,+h4 .caret|border-color 
				'example'              => '<div class="panel_s"><div class="panel-heading"><h4>' . _l('si_ct_example_admin_panel') . '</h4></div></div>',
			],
			[
				'name'                 => _l('si_ct_table_headings_color'),
				'id'                   => 'table-headings',
				'target'               => '.table.items thead tr>th,table.dataTable thead tr>th, .table.dataTable>thead:first-child>tr:first-child>th,table.dataTable thead>tr>td.sorting_asc,table.dataTable thead>tr>td.sorting_desc,table.dataTable thead>tr>th.sorting_asc,table.dataTable thead>tr>th.sorting_desc,.dataTables_filter .input-group-addon,.dt-buttons.btn-group .btn, .table>thead>tr>th',
				'css'                  => 'color',
				'additional_selectors' => '',
				'example'              => '<table class="table dataTable"><thead><tr><th class="sorting">' . _l('si_ct_example_table_heading') . ' 1</th><th class="sorting">' . _l('si_ct_example_table_heading') . ' 2</th></tr></thead></table>',
			],
			[
				'name'                 => _l('si_ct_table_headings_bg_color'),
				'id'                   => 'table-items-heading',
				'target'               => '.table.items thead,table.dataTable thead>tr>th',
				'css'                  => 'background',
				'additional_selectors' => 'table.dataTable thead>tr>th|border-color',
				'example'              => '<table class="table items"><thead><tr><th>' . _l('si_ct_example_table_heading') . ' 1</th><th>' . _l('si_ct_example_table_heading') . ' 2</th></tr></thead></table>',
			],
			[
				'name'                 => _l('si_ct_table_content_bg_color'),
				'id'                   => 'table-items-content',
				'target'               => '.table.items .main, .table>tbody>tr>td, .table>tfoot>tr>td',
				'css'                  => 'background',
				'additional_selectors' => '',
				'example'              => '<table class="table items"><thead><tr><th>' . _l('si_ct_example_table_heading') . ' 1</th><th>' . _l('si_ct_example_table_heading') . ' 2</th></tr></thead><tbody><tr><td>' . _l('si_ct_example_table_content') . ' 1</td><td>' . _l('si_ct_example_table_content') . ' 2</td></tr></tbody></table>',
			],
		],
		'texts' => [
			[
				'name'                 => _l('si_ct_text_muted'),
				'id'                   => 'text-muted',
				'target'               => '.text-muted',
				'css'                  => 'color',
				'additional_selectors' => '',
				'example'              => '<p>' . _l('si_ct_example_text', '<span class="bold text-muted">' . _l('si_ct_text_muted') . '</span>') . '</p>',
			],
			[
				'name'                 => _l('si_ct_text_danger'),
				'id'                   => 'text-danger',
				'target'               => '.text-danger',
				'css'                  => 'color',
				'additional_selectors' => '',
				'example'              => '<p>' . _l('si_ct_example_text', '<span class="bold text-danger">' . _l('si_ct_text_danger') . '</span>') . '</p>',
			],
			[
				'name'                 => _l('si_ct_text_warning'),
				'id'                   => 'text-warning',
				'target'               => '.text-warning',
				'css'                  => 'color',
				'additional_selectors' => '',
				'example'              => '<p>' . _l('si_ct_example_text', '<span class="bold text-warning">' . _l('si_ct_text_warning') . '</span>') . '</p>',
			],
			[
				'name'                 => _l('si_ct_text_info'),
				'id'                   => 'text-info',
				'target'               => '.text-info',
				'css'                  => 'color',
				'additional_selectors' => '',
				'example'              => '<p>' . _l('si_ct_example_text', '<span class="bold text-info">' . _l('si_ct_text_info') . '</span>') . '</p>',
			],
			[
				'name'                 => _l('si_ct_text_success'),
				'id'                   => 'text-success',
				'target'               => '.text-success',
				'css'                  => 'color',
				'additional_selectors' => '',
				'example'              => '<p>' . _l('si_ct_example_text', '<span class="bold text-success">' . _l('si_ct_text_success') . '</span>') . '</p>',
			],
		],
		'tabs' => [
			[
				'name'                 => _l('si_ct_tabs_bg_color'),
				'id'                   => 'tabs-bg',
				'target'               => '.nav-tabs,.user-data .home-activity .nav.nav-tabs',
				'css'                  => 'background',
				'additional_selectors' => '',
			],
			[
				'name'                 => _l('si_ct_tabs_links_color'),
				'id'                   => 'tabs-links',
				'target'               => '.nav-tabs>li>a,.lead-info-heading h4,.user-data .home-activity .nav.nav-tabs>li>a',
				'css'                  => 'color',
				'additional_selectors' => '',
			],
			[
				'name'                 => _l('si_ct_tabs_active_links_color'),
				'id'                   => 'tabs-links-active-hover',
				'target'               => '.nav-tabs>li.active>a, .nav-tabs>li.active>a:focus, .nav-tabs>li.active>a:hover, .nav-tabs>li>a:focus, .nav-tabs>li>a:hover, .user-data .home-activity .nav-tabs>li.active>a, .user-data .home-activity .nav-tabs>li.active>a:focus, .user-data .home-activity .nav-tabs>li.active>a:hover, .user-data .home-activity .nav-tabs>li>a:focus, .user-data .home-activity .nav-tabs>li>a:hover,.nav-tabs>li.active>a i.menu-icon', //.nav-tabs>li.active>a i.menu-icon  
				'css'                  => 'color',
				'additional_selectors' => '',
			],
	
			[
				'name'                 => _l('si_ct_tabs_active_border_color'),
				'id'                   => 'tabs-active-border',
				'target'               => '.nav-tabs>li.active>a, .nav-tabs>li.active>a:focus, .nav-tabs>li.active>a:hover, .nav-tabs>li>a:focus, .nav-tabs>li>a:hover',
				'css'                  => 'border-bottom-color',
				'additional_selectors' => '',
			],
			[
				'name'                 => _l('si_ct_tabs_border_color'),
				'id'                   => 'tabs-border',
				'target'               => '.nav-tabs',
				'css'                  => 'border-color',
				'additional_selectors' => '',
			],
		],
		'modals' => [
			[
				'name'                 => _l('si_ct_modal_heading_bg'),
				'id'                   => 'modal-heading',
				'target'               => '.modal-header,.lead-info-heading',
				'css'                  => 'background',
				'additional_selectors' => '',
			],
			[
				'name'                 => _l('si_ct_modal_heading_color'),
				'id'                   => 'modal-heading-color',
				'target'               => '.modal-header .modal-title',
				'css'                  => 'color',
				'additional_selectors' => '',
			],
			[
				'name'                 => _l('si_ct_modal_close_btn_color'),
				'id'                   => 'modal-close-button-color',
				'target'               => '.modal-header .close',
				'css'                  => 'color',
				'additional_selectors' => '',
			],
			[
				'name'                 => _l('si_ct_modal_white_text_color'),
				'id'                   => 'modal-header-white-text-color',
				'target'               => '.modal-header .color-white',
				'css'                  => 'color',
				'additional_selectors' => '',
			],
			[
				'name'                 => _l('si_ct_modal_body_bg'),
				'id'                   => 'modal-body',
				'target'               => '.modal-body,.modal-footer,.task-single-col-left,.task-single-col-right',
				'css'                  => 'background',
				'additional_selectors' => '.tasks-phases .panel-body,.tasks-phases .panel-footer|background-color',//tasks-phases for client side kanban task
			],
		],
		'buttons' => [
			[
				'name'                 => _l('si_ct_button_default'),
				'id'                   => 'btn-default',
				'target'               => '.btn-default',
				'css'                  => 'background-color',
				'additional_selectors' => '.btn-default:not(.bootstrap-select .btn-default, .input-group-btn .btn-default)|border-color+.pagination>li>a, .pagination>li>span|background-color+.bootstrap-select.task-action-select .btn-default|background-color',
				'example'              => '<button type="button" class="btn btn-default">' . _l('si_ct_button_default') . '</button>',
			],
			[
				'name'                 => _l('si_ct_button_info'),
				'id'                   => 'btn-info',
				'target'               => '.btn-info, .btn-primary,body.customers_login .navbar-default .navbar-nav>li.customers-nav-item-login>a',//.btn-primary,body.customers_login .navbar-default .navbar-nav>li.customers-nav-item-login>a 
				'css'                  => 'background-color',
				'additional_selectors' => '.btn-info|border-color+.dropdown-menu>.active>a, .dropdown-menu>.active>a:focus, .dropdown-menu>.active>a:hover|background-color+.dropdown-menu>li>a:hover|color+.pagination>.active>a, .pagination>.active>a:focus, .pagination>.active>a:hover, .pagination>.active>span, .pagination>.active>span:focus, .pagination>.active>span:hover|background-color+.pagination>.active>a, .pagination>.active>a:focus, .pagination>.active>a:hover, .pagination>.active>span, .pagination>.active>span:focus, .pagination>.active>span:hover|border-color+.checkbox-info label::after,.checkbox-primary label::after,.radio-info label::after,.radio-primary label::after|background-color+.checkbox-info label::before,.checkbox-primary label::before,.radio-info  label::before,.radio-primary  label::before|border-color',
				'example'              => '<button type="button" class="btn btn-info">' . _l('si_ct_button_info') . '</button>',
			],
			[
				'name'                 => _l('si_ct_button_success'),
				'id'                   => 'btn-success',
				'target'               => '.btn-success',
				'css'                  => 'background-color',
				'additional_selectors' => '.btn-success|border-color',
				'example'              => '<button type="button" class="btn btn-success">' . _l('si_ct_button_success') . '</button>',
			],
			[
				'name'                 => _l('si_ct_button_danger'),
				'id'                   => 'btn-danger',
				'target'               => '.btn-danger',
				'css'                  => 'background-color',
				'additional_selectors' => '.btn-danger|border-color',
				'example'              => '<button type="button" class="btn btn-danger">' . _l('si_ct_button_danger') . '</button>',
			],
		],
	];
	
	
	$CI   = & get_instance();
	$tags = get_tags();
	
	$areas['tags'] = [];
	
	foreach ($tags as $tag) {
		array_push($areas['tags'], [
				'name'                 => $tag['name'],
				'id'                   => 'tag-' . $tag['id'],
				'target'               => '.tag-id-' . $tag['id'],
				'css'                  => 'color',
				'additional_selectors' => '.tag-id-' . $tag['id'] . '|border-color+ul.tagit li.tagit-choice-editable.tag-id-' . $tag['id'] . '|border-color+ul.tagit li.tagit-choice.tag-id-' . $tag['id'] . ' .tagit-label:not(a)|color',
				'example'              => '<span class="label label-tag tag-id-' . $tag['id'] . '">' . $tag['name'] . '</span>',
			]);
	}
	
	$areas = hooks()->apply_filters('si_custom_theme_get_styling_areas', $areas);
	
	if (!is_array($type)) {
		return $areas[$type];
	}
	
	$_areas = [];
	foreach ($type as $t) {
		$_areas[] = $areas[$t];
	}
	
	return $_areas;
}
/**
* Will fetch from database the stored applied theme styles and return
* @return object
*/
function si_custom_theme_get_applied_styling_area($theme_id)
{
	if(!is_numeric($theme_id)){
		$theme_style = get_option(SI_CUSTOM_THEME_MODULE_NAME.'_style');
	}
	else{
		$theme_style = '';
		$theme = get_instance()->si_custom_theme_model->get_themes($theme_id);
		if(!empty($theme) && isset($theme['theme_style']))
		{
			$theme_style = $theme['theme_style'];
		}
		
	}
	if ($theme_style == '') {
		return [];
	}
	$theme_style = json_decode($theme_style);
	
	return $theme_style;
}
/**
* Function that will  render the theme style
* @param  string $type,$theme_id
* @return void
*/
function si_custom_theme_render($type,$theme_id='')
{
	$theme_style   = si_custom_theme_get_applied_styling_area($theme_id);
	$styling_areas = si_custom_theme_get_styling_areas($type);
	
	
	foreach ($styling_areas as $type => $area) {
		foreach ($area as $_area) {
			foreach ($theme_style as $applied_style) {
				if ($applied_style->id == $_area['id']) {
					echo '<style class="si_ct_loaded si_custom_theme_style_' . $_area['id'] . '">' . PHP_EOL;
					echo ($_area['target']) . '{' . PHP_EOL;
					echo ($_area['css']) . ':' . $applied_style->color . ' !important;' . PHP_EOL;
					echo '}' . PHP_EOL;
					if (startsWith($_area['target'], '.btn')) {
						echo '
						' . $_area['target'] . ':focus,' . $_area['target'] . '.focus,' . $_area['target'] . ':hover,' . $_area['target'] . ':active,
						' . $_area['target'] . '.active,
						.open > .dropdown-toggle' . $_area['target'] . ',' . $_area['target'] . ':active:hover,
						' . $_area['target'] . '.active:hover,
						.open > .dropdown-toggle' . $_area['target'] . ':hover,
						' . $_area['target'] . ':active:focus,
						' . $_area['target'] . '.active:focus,
						.open > .dropdown-toggle' . $_area['target'] . ':focus,
						' . $_area['target'] . ':active.focus,
						' . $_area['target'] . '.active.focus,
						.open > .dropdown-toggle' . $_area['target'] . '.focus,
						' . $_area['target'] . ':active,
						' . $_area['target'] . '.active,
						.open > .dropdown-toggle' . $_area['target'] . '{background-color:' . adjust_color_brightness($applied_style->color, -50) . ';color:#fff;border-color:' . adjust_color_brightness($applied_style->color, -50) . ' !important}';
						echo '
						' . $_area['target'] . '.disabled,
						' . $_area['target'] . '[disabled],
						fieldset[disabled] ' . $_area['target'] . ',
						' . $_area['target'] . '.disabled:hover,
						' . $_area['target'] . '[disabled]:hover,
						fieldset[disabled] ' . $_area['target'] . ':hover,
						' . $_area['target'] . '.disabled:focus,
						' . $_area['target'] . '[disabled]:focus,
						fieldset[disabled] ' . $_area['target'] . ':focus,
						' . $_area['target'] . '.disabled.focus,
						' . $_area['target'] . '[disabled].focus,
						fieldset[disabled] ' . $_area['target'] . '.focus,
						' . $_area['target'] . '.disabled:active,
						' . $_area['target'] . '[disabled]:active,
						fieldset[disabled] ' . $_area['target'] . ':active,
						' . $_area['target'] . '.disabled.active,
						' . $_area['target'] . '[disabled].active,
						fieldset[disabled] ' . $_area['target'] . '.active {
							background-color: ' . adjust_color_brightness($applied_style->color, 50) . ';color:#fff;border-color:' . adjust_color_brightness($applied_style->color, 50) . ' !important;}';
					}
					if ($_area['additional_selectors'] != '') {
						$additional_selectors = explode('+', $_area['additional_selectors']);
						foreach ($additional_selectors as $as) {
							$_temp = explode('|', $as);
							echo ($_temp[0]) . ' {' . PHP_EOL;
							echo ($_temp[1]) . ':' . $applied_style->color . ' !important;' . PHP_EOL;
							echo '}' . PHP_EOL;
						}
					}
					echo '</style>' . PHP_EOL;
				}
			}
		}
	}
}

function si_custom_theme_get_custom_style_values($type, $selector,$theme_id='')
{
	$value         = '';
	$theme_style   = si_custom_theme_get_applied_styling_area($theme_id);
	$styling_areas = si_custom_theme_get_styling_areas($type);
	foreach ($styling_areas as $area) {
		if ($area['id'] == $selector) {
			foreach ($theme_style as $applied_style) {
				if ($applied_style->id == $selector) {
					$value = $applied_style->color;
					break;
				}
			}
		}
	}
	return $value;
}

function si_custom_theme_render_theme_styling_picker($id, $value, $target, $css, $additional = '')
{
	echo '<div class="input-group mbot15 si-ct-colorpickers" data-target="' . $target . '" data-css="' . $css . '" data-additional="' . $additional . '">
	<input type="text" value="' . $value . '" data-id="' . $id . '" class="form-control" />
	<span class="input-group-addon"><i></i></span>
	</div>';
}

function handle_si_custom_theme_image_upload()
{
	$success   = true;
	$attachmentIndex = ['admin_login', 'customer_login','admin_menu','admin_pages','customer_pages'];
	foreach ($attachmentIndex as $attachment) {
		$index = SI_CUSTOM_THEME_MODULE_NAME.'_bg_img_'.$attachment;
		if (isset($_FILES[$index]['name']) && $_FILES[$index]['name'] != '') {
			$path = SI_CUSTOM_THEME_UPLOAD_PATH;
			# Get the temp file path
			$tmpFilePath = $_FILES[$index]['tmp_name'];
			# Make sure we have a filepath
			if (!empty($tmpFilePath) && $tmpFilePath != '') {
				# Getting file extension
				$extension          = strtolower(pathinfo($_FILES[$index]['name'], PATHINFO_EXTENSION));
				$allowed_extensions = [
					'jpg',
					'jpeg',
					'png',
					'gif',
				];
				if (!in_array($extension, $allowed_extensions)) {
					set_alert('warning', 'Image extension not allowed.');
	
					return false;
				}
				# Setup our new file path
				$filename    = "bg_img_{$attachment}_".time()."." . $extension;
				$newFilePath = $path . $filename;
				_maybe_create_upload_path($path);
				# Upload the file into the company uploads dir
				if (move_uploaded_file($tmpFilePath, $newFilePath)) {
					update_option($index, $filename);
					$success = true;
				}
			}   
		}
	}
	return $success;
}

function si_ct_bg_image_render()
{
	$admin_login_bg_image = get_option(SI_CUSTOM_THEME_MODULE_NAME.'_bg_img_admin_login');
	$customer_login_bg_image = get_option(SI_CUSTOM_THEME_MODULE_NAME.'_bg_img_customer_login');
	$admin_menu_bg_image 	= get_option(SI_CUSTOM_THEME_MODULE_NAME.'_bg_img_admin_menu');  
	$admin_pages_bg_image 	= get_option(SI_CUSTOM_THEME_MODULE_NAME.'_bg_img_admin_pages');  
	$customer_pages_bg_image 	= get_option(SI_CUSTOM_THEME_MODULE_NAME.'_bg_img_customer_pages');
	
	if($admin_login_bg_image!='' && !is_staff_logged_in())
		echo '<style> body.login_admin{background-image:url('.base_url('modules/si_custom_theme/uploads/'.$admin_login_bg_image).') !important;}</style>'.PHP_EOL;
	if($customer_login_bg_image!='' && !is_client_logged_in())
		echo '<style> body.customers_login{background-image:url('.base_url('modules/si_custom_theme/uploads/'.$customer_login_bg_image).') !important;}</style>'.PHP_EOL;
	if($admin_menu_bg_image!='' && is_staff_logged_in())
		echo '<style>.admin #side-menu,.admin #setup-menu,.admin #setup-menu-wrapper{background-image:url('.base_url('modules/si_custom_theme/uploads/'.$admin_menu_bg_image).') !important;}</style>'.PHP_EOL;
	if($admin_pages_bg_image!='' && is_staff_logged_in())
		echo '<style> body.admin #wrapper{background-image:url('.base_url('modules/si_custom_theme/uploads/'.$admin_pages_bg_image).') !important;}</style>'.PHP_EOL;
	if($customer_pages_bg_image!='' && is_client_logged_in())
		echo '<style> body.customers{background-image:url('.base_url('modules/si_custom_theme/uploads/'.$customer_pages_bg_image).') !important;}</style>'.PHP_EOL;		
}

