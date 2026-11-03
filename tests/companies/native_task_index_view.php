<?php
require __DIR__.'/native_task_main_filters.php';
require_once APPPATH.'services/NativeCompanyTaskScopedDb.php';
require_once APPPATH.'services/NativeCompanyTaskIndexProjection.php';
foreach(['NativeCompanyTaskScopedDb','NativeCompanyTaskIndexProjection','NativeCompanyRelatedTaskTable','TaskPriorityPresentation','NativeCompanyFilters','NativeCompanyCustomFields','IssuerResourceAccess','IssuerPolicy','CompanyStandaloneTasks','WorkStatusPresentation','NativeCompanyResourceReferences'] as $class) class_alias($class,'NativeTaskHttpTest\\'.$class);
eval('namespace NativeTaskHttpTest; class NativeCompanyAdminPresentation { public static function prepare($ci):void {} }'); // Shell boot is tested separately.
if(!function_exists('init_head')){function init_head(){echo '<head data-native-shell-fixture></head>';}}
if(!function_exists('init_tail')){function init_tail(){}}
if(!function_exists('get_table_last_order')){function get_table_last_order($id){if($id!=='tasks')throw new RuntimeException('Wrong table');return '';}}
if(!function_exists('render_datatable')){function render_datatable($columns,$class,$classes=[],$attrs=[]){echo '<table class="table-'.e($class).'">';foreach($columns as$column)echo '<th>'.(is_array($column)?($column['name']??''):$column).'</th>';echo '</table>';}}
$controller->load=new class($controller){
 private array $vars=[];public function __construct(private $ci){}
 public function view($path,$data=[],$return=false){
  if(!str_starts_with($path,'admin/tasks/'))throw new RuntimeException('Unexpected view '.$path);
  $this->vars=array_replace($this->vars,$data);$vars=$this->vars;
  $render=Closure::bind(function()use($path,$vars){extract($vars);ob_start();try{require APPPATH.'views/'.$path.'.php';return ob_get_clean();}catch(Throwable $e){ob_end_clean();throw $e;}},$this->ci);
  $html=$render();if($return)return$html;echo$html;
 }
};
\NativeTaskHttpTest\CompanyRequestScope::$issuer=$a;\NativeTaskHttpTest\CompanyRequestScope::$actor=7;
$controller->input->verb='GET';$controller->input->query=[];$controller->list_tasks();$html=$controller->output->body;
essentialCheck(str_contains($html,'table-tasks')&&str_contains($html,'app-filters')&&str_contains($html,'tasks_bulk_actions'),'Original task index, filter and bulk modal templates render using scoped data');
essentialCheck(str_contains($html,'new_task(')&&str_contains($html,'tasks/switch_kanban'),'Original controls remain present; their complete routes are separate gates');
$controller->input->verb='POST';try{$controller->list_tasks();throw new RuntimeException('POST index accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 422','Index rendering rejects mutation verbs');}
echo 'Original task index view/controller: '.$GLOBALS['essentialChecks']." cumulative checks passed; mocked shell and table primitive; route/browser pending.\n";
