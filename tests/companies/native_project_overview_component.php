<?php
/** Render the original overview component with actual authorized project facts. */
require __DIR__.'/native_project_detail_hours.php';
if(!function_exists('admin_url')){function admin_url($path=''){return 'https://fixture.invalid/admin/'.$path;}}
if(!function_exists('e')){function e($v){return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}}
if(!function_exists('app_format_money')){function app_format_money($amount,$currency){return $currency->symbol.' '.$amount;}}
if(!function_exists('seconds_to_time_format')){function seconds_to_time_format($seconds){return (string)$seconds;}}
if(!function_exists('hours_to_seconds_format')){function hours_to_seconds_format($hours){return (int)((float)$hours*3600);}}
if(!function_exists('staff_can')){function staff_can(...$args){throw new RuntimeException('Global permission lookup forbidden');}}
if(!function_exists('get_custom_fields')){function get_custom_fields(...$args){throw new RuntimeException('Global custom fields forbidden');}}
if(!function_exists('get_tags_in')){function get_tags_in(...$args){throw new RuntimeException('Global tags forbidden');}}
if(!function_exists('_d')){function _d(...$args){throw new RuntimeException('Global date format forbidden');}}
$render=static function(array $input):string{$native_project_overview=$input;ob_start();try{include APPPATH.'views/admin/projects/_project_overview_description_list.php';return ob_get_contents();}finally{ob_end_clean();}};
$sample=$overview;$sample['facts']['project']['description']='<script>bad()</script>';$sample['facts']['custom_values'][(string)$fieldId]='<img src=x onerror=bad()>';
$html=$render($sample);
essentialCheck(str_contains($html,'project-overview-billing')&&str_contains($html,'123.45'),'original component displays authorized actual financial amount');
essentialCheck(str_contains($html,'&lt;script&gt;')&&!str_contains($html,'<script>bad()')&&str_contains($html,'&lt;img src=x'),'project description and custom values are escaped');
$privateHtml=$render($privateOverview);
essentialCheck(!str_contains($privateHtml,'project-overview-billing')&&!str_contains($privateHtml,'123.45'),'actor without financial authority sees no billing values');
$renderer=new class{public $load;function __construct(){$this->load=$this;}function view($name,$vars=[]){extract($vars);include APPPATH.'views/'.$name.'.php';}function render($overview){ob_start();try{$this->view('admin/projects/project_overview',['native_project_overview'=>$overview]);return ob_get_contents();}finally{ob_end_clean();}}};
$originalOverview=$renderer->render($overview);
essentialCheck(str_contains($originalOverview,'project-overview-open-tasks')&&str_contains($originalOverview,'project-overview-timesheets-chart')&&str_contains($originalOverview,'project-overview-billing'),'original overview and nested chart render actual authorized data');
$privateFull=$renderer->render($privateOverview);
essentialCheck(!str_contains($privateFull,'project-overview-billing')&&!str_contains($privateFull,'123.45'),'original overview enforces financial ACL');
$noTasksHtml=$renderer->render($withoutTasks);
essentialCheck(!str_contains($noTasksHtml,'project-overview-open-tasks')&&!str_contains($noTasksHtml,'project-overview-timesheets-chart'),'original overview omits task counts and chart when task access is absent');
echo "Original project overview component: scoped data, real finance and escaped fields passed.\n";
