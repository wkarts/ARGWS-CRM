<?php
/** Official Projects::view composes the owned detail without hydrating the global model. */
require __DIR__.'/native_projects_controller.php';
require_once APPPATH.'services/utilities/Format.php';
if(!function_exists('get_weekdays')){function get_weekdays(){return['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];}}
if(!function_exists('get_weekdays_original')){function get_weekdays_original(){return['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];}}
if(!function_exists('sec2qty')){function sec2qty($seconds){return round(((int)$seconds)/3600,2);}}
if(!function_exists('get_weekdays_between_dates')){function get_weekdays_between_dates($from,$to){$days=[];for($day=$from;$day<=$to;$day=$day->modify('+1 day'))$days[]=$day->format('Y-m-d');return[1=>$days];}}
if(!function_exists('split_weeks_chart_label')){function split_weeks_chart_label($weeks,$index){return 'Week '.$index;}}
// This fixture starts at the project migration boundary. Add the later task
// projection columns/timetable that the current project detail reads.
foreach(['company_tasks','company_standalone_tasks']as$table){
    $db->query('ALTER TABLE '.$p.$table.' ADD native_fields_json longtext NULL');
    $db->query('ALTER TABLE '.$p.$table.' ADD native_fields_revision int unsigned NULL');
}
$db->query('CREATE TABLE '.$p.'company_task_schedules(id INTEGER PRIMARY KEY AUTOINCREMENT,issuer_id int unsigned NOT NULL,kind varchar(16) NOT NULL,source_id int unsigned NOT NULL)');
unset($db->data_cache['table_names']);
$canonical=NativeCompanyResourceId::encode('company_project',$pa);
$controller->input->verb='GET';$controller->input->query=[];$controller->load->views=[];
CompanyRequestScope::$issuer=$a;CompanyRequestScope::$actor=7;
$controller->view($canonical);
$view=$controller->load->views[0]??null;
essentialCheck(is_array($view)&&$view[0]==='admin/projects/view','Official project detail dispatches the original project view');
$data=$view[1];
essentialCheck(($data['native_project_view']['canonical_id']??null)===$canonical&&isset($data['native_project_overview'],$data['native_project_files']),'Project detail carries only the issuer-owned overview and file projection');
essentialCheck(($data['native_project_view']['context_token']??null)==='synthetic-project-context'&&($data['native_project_view']['csrf_token']??null)==='synthetic-project-csrf','Project detail binds official mutations to context and native CSRF');
essentialCheck(in_array('Cache-Control: no-store',$controller->output->headers,true),'Project detail is never cached across companies');

CompanyRequestScope::$issuer=$b;$reject(fn()=>$controller->view($canonical),403);
CompanyRequestScope::$issuer=$a;$controller->input->verb='POST';$reject(fn()=>$controller->view($canonical),422);
echo "Official project detail: owned overview/files, original view, method and cross-company guards passed.\n";
