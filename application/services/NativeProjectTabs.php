<?php
declare(strict_types=1);
/** Original core tab registrations, shared verbatim in shape; ACL inputs are supplied by the caller. */
final class NativeProjectTabs
{
    public const VISIBILITY_KEYS=['project_tickets', 'project_contracts', 'sales', 'project_invoices', 'project_estimates', 'project_expenses', 'project_credit_notes', 'project_subscriptions', 'project_proposals'];
    public static function register(object$receiver,callable$translate,array$visibility):void
    {
        if(array_diff(self::VISIBILITY_KEYS,array_keys($visibility))||array_diff(array_keys($visibility),self::VISIBILITY_KEYS))throw new InvalidArgumentException('native_project_tabs_invalid');
        foreach($visibility as$value)if(!is_bool($value))throw new InvalidArgumentException('native_project_tabs_invalid');
    $receiver->add_project_tab('project_overview', [
        'name'     => $translate('project_overview'),
        'icon'     => 'fa fa-th',
        'view'     => 'admin/projects/project_overview',
        'position' => 5,
    ]);

    $receiver->add_project_tab('project_tasks', [
        'name'                      => $translate('tasks'),
        'icon'                      => 'fa-regular fa-check-circle',
        'view'                      => 'admin/projects/project_tasks',
        'position'                  => 10,
        'linked_to_customer_option' => ['view_tasks'],
    ]);

    $receiver->add_project_tab('project_timesheets', [
        'name'                      => $translate('project_timesheets'),
        'icon'                      => 'fa-regular fa-clock',
        'view'                      => 'admin/projects/project_timesheets',
        'position'                  => 15,
        'linked_to_customer_option' => ['view_timesheets'],
    ]);

    $receiver->add_project_tab('project_milestones', [
        'name'                      => $translate('project_milestones'),
        'icon'                      => 'fa fa-rocket',
        'view'                      => 'admin/projects/project_milestones',
        'position'                  => 20,
        'linked_to_customer_option' => ['view_milestones'],
    ]);

    $receiver->add_project_tab('project_files', [
        'name'                      => $translate('project_files'),
        'icon'                      => 'fa-solid fa-file',
        'view'                      => 'admin/projects/project_files',
        'position'                  => 25,
        'linked_to_customer_option' => ['upload_files'],
    ]);

    $receiver->add_project_tab('project_discussions', [
        'name'                      => $translate('project_discussions'),
        'icon'                      => 'fa-regular fa-message',
        'view'                      => 'admin/projects/project_discussions',
        'position'                  => 30,
        'linked_to_customer_option' => ['open_discussions'],
    ]);

    $receiver->add_project_tab('project_gantt', [
        'name'                      => $translate('project_gant'),
        'icon'                      => 'fa-solid fa-chart-gantt',
        'view'                      => 'admin/projects/project_gantt',
        'position'                  => 35,
        'linked_to_customer_option' => ['view_gantt'],
    ]);

    $receiver->add_project_tab('project_tickets', [
        'name'     => $translate('project_tickets'),
        'icon'     => 'fa fa-life-ring',
        'view'     => 'admin/projects/project_tickets',
        'position' => 40,
        'visible'  => $visibility['project_tickets'],
    ]);

    $receiver->add_project_tab('project_contracts', [
        'name'     => $translate('contracts'),
        'icon'     => 'fa-solid fa-file-contract',
        'view'     => 'admin/projects/project_contracts',
        'position' => 45,
        'visible'  => $visibility['project_contracts'],
    ]);

    $receiver->add_project_tab('sales', [
        'name'     => $translate('sales_string'),
        'icon'     => 'fa-solid fa-bolt',
        'position' => 50,
        'collapse' => true,
        'visible'  => $visibility['sales'],
    ]);

    $receiver->add_project_tab_children_item('sales', [
        'slug'     => 'project_invoices',
        'name'     => $translate('project_invoices'),
        'view'     => 'admin/projects/project_invoices',
        'position' => 15,
        'visible'  => $visibility['project_invoices'],
    ]);

    $receiver->add_project_tab_children_item('sales', [
        'slug'     => 'project_estimates',
        'name'     => $translate('estimates'),
        'view'     => 'admin/projects/project_estimates',
        'position' => 10,
        'visible'  => $visibility['project_estimates'],
    ]);

    $receiver->add_project_tab_children_item('sales', [
        'slug'     => 'project_expenses',
        'name'     => $translate('project_expenses'),
        'view'     => 'admin/projects/project_expenses',
        'position' => 25,
        'visible'  => $visibility['project_expenses'],
    ]);

    $receiver->add_project_tab_children_item('sales', [
        'slug'     => 'project_credit_notes',
        'name'     => $translate('credit_notes'),
        'view'     => 'admin/projects/project_credit_notes',
        'position' => 30,
        'visible'  => $visibility['project_credit_notes'],
    ]);

    $receiver->add_project_tab_children_item('sales', [
        'slug'     => 'project_subscriptions',
        'name'     => $translate('subscriptions'),
        'view'     => 'admin/projects/project_subscriptions',
        'position' => 20,
        'visible'  => $visibility['project_subscriptions'],
    ]);

    $receiver->add_project_tab_children_item('sales', [
        'slug'     => 'project_proposals',
        'name'     => $translate('proposals'),
        'view'     => 'admin/projects/project_proposals',
        'position' => 5,
        'visible'  => $visibility['project_proposals'],
    ]);

    $receiver->add_project_tab('project_notes', [
        'name'     => $translate('project_notes'),
        'icon'     => 'fa-regular fa-note-sticky',
        'view'     => 'admin/projects/project_notes',
        'position' => 55,
    ]);

    $receiver->add_project_tab('project_activity', [
        'name'                      => $translate('project_activity'),
        'icon'                      => 'fa-regular fa-file-lines',
        'view'                      => 'admin/projects/project_activity',
        'position'                  => 60,
        'linked_to_customer_option' => ['view_activity_log'],
    ]);
    }
    public static function definitions(callable$translate,array$visibility):array
    {
        $receiver=new class {public array$tabs=[];public function add_project_tab($slug,$tab):void{$this->tabs[$slug]=['slug'=>$slug,'badge'=>[],'children'=>[]]+$tab;}public function add_project_tab_children_item($parent,$tab):void{$this->tabs[$parent]['children'][]=['parent_slug'=>$parent,'badge'=>[]]+$tab;}};
        self::register($receiver,$translate,$visibility);$out=[];
        foreach($receiver->tabs as$slug=>$tab){if(isset($tab['collapse'])){$tab['children']=array_values(array_filter($tab['children'],static fn($child)=>$child['visible']??true));if(!$tab['children'])continue;usort($tab['children'],static fn($a,$b)=>$a['position']<=>$b['position']);}elseif(isset($tab['visible'])&&!$tab['visible'])continue;$out[$slug]=$tab;}
        uasort($out,static fn($a,$b)=>$a['position']<=>$b['position']);return$out;
    }
}
