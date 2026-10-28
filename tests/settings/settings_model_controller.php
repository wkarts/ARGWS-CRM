<?php
/** Official Settings integration for the seven former company-scoped policy forms. */
require __DIR__.'/company_settings.php';
require_once dirname(__DIR__).'/support/StrictViewFixture.php';
require_once APPPATH.'services/NativeCompanySettingsForms.php';
require_once APPPATH.'services/NativeSettingsSections.php';
if(!function_exists('_l')){function _l($key){return$key;}}

$db->query("UPDATE {$p}issuers_registry SET active=1 WHERE id IN (41,82)");
foreach(['company_tasks','company_standalone_tasks']as$table){
    if(!$db->table_exists($p.$table))$db->query('CREATE TABLE `'.$p.$table.'` (`issuer_id` int unsigned NOT NULL,`id` int unsigned NOT NULL,PRIMARY KEY (`issuer_id`,`id`))');
}
CompanyTaskPrioritySchema::ensure($db,$p);
$forms=new NativeCompanySettingsForms($db,$p);
$groups=[
    'task_timer_stop'=>['auto_stop_tasks_timers_on_new_timer'=>'1'],
    'task_timers'=>['timer_started_change_status_in_progress'=>'1'],
    'task_operations'=>['default_task_priority'=>'4','new_task_auto_follower_current_member'=>'1'],
    'task_defaults'=>['default_task_status'=>'in_progress','new_task_auto_assign_current_member'=>'0'],
    'ticket_preferences'=>['ticket_replies_order'=>'desc'],
    'pdf_typography'=>['pdf_font'=>'freeserif','pdf_font_size'=>'14'],
    'project_files'=>['allow_contact_to_delete_files'=>'1'],
];
$defaults=[];$legacyBefore=essentialLegacy($f);
foreach($groups as$group=>$values){
    $page=$forms->page(41,12,$group);$defaults[$group]=$page['values'];
    essentialCheck($page['revision']==='0'&&array_keys($page['values'])===NativeCompanySettingsForms::fields($group),$group.' starts with its exact official DTO and integer revision');
    $saved=$forms->save(41,12,$group,$values,[],$page['revision']);
    essentialCheck($saved['values']===$values&&$saved['revision']==='1',$group.' saves through its issuer-owned adapter');
    settingThrows(fn()=>$forms->save(41,12,$group,$values,[],'0'),match($group){
        'task_timer_stop'=>'company_task_timer_stop_preferences_stale','task_timers'=>'company_task_timer_preferences_stale',
        'task_operations'=>'company_task_operation_preferences_stale','task_defaults'=>'company_task_preferences_stale',
        'ticket_preferences'=>'company_ticket_preferences_stale','pdf_typography'=>'company_pdf_typography_stale',
        'project_files'=>'company_project_file_settings_stale'},$group.' rejects a stale official form');
    settingThrows(fn()=>$forms->save(41,12,$group,$values+['smtp_password'=>'overpost'],[],'1'),'native_settings_invalid',$group.' rejects unrelated settings keys before mutation');
    essentialCheck($forms->page(82,12,$group)['values']===$defaults[$group],$group.' never inherits company A while viewing B');
}
essentialCheck(essentialLegacy($f)===$legacyBefore,'official policy forms never mutate installation-wide options');

$sections=NativeSettingsSections::collect();$visible=[];
foreach($sections as$section)foreach($section['children']as$child)$visible[$child['id']]=$child;
foreach(array_keys($groups)as$group){
    essentialCheck(isset($visible[$group])&&$visible[$group]['view']==='admin/settings/includes/company_policy',$group.' is registered in the official Settings navigation');
}

$viewHarness=new class {
    public object $db;public object $router;
    public function __construct(){
        $this->db=$GLOBALS['CI']->db;
        $this->router=new class{public function fetch_class():string{return'settings';}};
    }
    public function render(string$group,array$values):string
    {
        return strictFixtureRender(function()use($group,$values):void{
            $native_settings=true;$native_settings_policy_values=$values;$group=['id'=>$group];
            include APPPATH.'views/admin/settings/includes/company_policy.php';
        });
    }
};
$ids=[
    'task_timer_stop'=>'company-setting-auto_stop_tasks_timers_on_new_timer',
    'task_timers'=>'company-setting-timer_started_change_status_in_progress',
    'task_operations'=>'company-setting-default_task_priority',
    'task_defaults'=>'company-setting-default_task_status',
    'ticket_preferences'=>'company-setting-ticket_replies_order',
    'pdf_typography'=>'company-setting-pdf_font',
    'project_files'=>'company-setting-allow_contact_to_delete_files',
];
foreach($groups as$group=>$values){
    $html=$viewHarness->render($group,$values);
    essentialCheck(str_contains($html,'data-settings-policy="'.$group.'"')&&str_contains($html,'id="'.$ids[$group].'"'),$group.' renders inside the official form with stable browser selectors');
    foreach(array_keys($values)as$field)essentialCheck(substr_count($html,'name="settings['.$field.']"')===1,$group.' renders '.$field.' exactly once');
    essentialCheck(!str_contains($html,'<form')&&!str_contains($html,'_company_context'),'policy partial never creates a parallel form or token contract');
}

$settingsSource=file_get_contents(APPPATH.'controllers/admin/Settings.php');
$scopeSource=file_get_contents(APPPATH.'services/CompanyRequestScope.php');
essentialCheck(!str_contains($settingsSource,'isolated_index')&&!str_contains($settingsSource,'company_scoped'),'Settings controller has no parallel renderer or fallback');
essentialCheck(str_contains($scopeSource,'$sharedSettings')&&str_contains($scopeSource,'$sharedSettings ||'),'principal and named companies enter the same native Settings controller');
$legacySettingsView=APPPATH.'views/admin/settings/company_scoped.php';
essentialCheck(!is_file($legacySettingsView)||str_starts_with(trim((string)@file_get_contents($legacySettingsView)),'<?php // Removed.'),'parallel Settings view is absent or inert');
essentialUse($f);
echo 'Official Settings policy integration: '.$GLOBALS['essentialChecks'].' cumulative checks passed ('.$db->driver().').'.PHP_EOL;
