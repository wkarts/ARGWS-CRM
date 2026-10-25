<?php
/** Render the unchanged native modal with real form helpers. Auth/controller/HTTP remain separate gates. */
define('BASEPATH',dirname(__DIR__).'/system/');define('APPPATH',dirname(__DIR__).'/application/');
require APPPATH.'language/portuguese/portuguese_lang.php';
function _l($key,...$args){return$GLOBALS['lang'][$key]??$key;}
function e($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function html_escape($value){return is_array($value)?array_map('html_escape',$value):e($value);}
function config_item($key){return$key==='charset'?'UTF-8':null;}
function &get_instance(){return$GLOBALS['CI'];}
function admin_url($path=''){return'https://crm.invalid/sub/admin/'.$path;}
function get_staff_user_id(){return7;}
function is_mobile(){return false;}
function html_purify($value){return strip_tags((string)$value,'<p><b>');} // Sanitizer boundary; not a purifier test.
function get_option(){throw new RuntimeException('Unexpected global option');}
function total_rows(){throw new RuntimeException('Unexpected global row query');}
function staff_can(){throw new RuntimeException('Unexpected global permission');}
function hooks(){throw new RuntimeException('Unexpected global hook');}
function get_relation_data(){throw new RuntimeException('Unexpected global relation');}
function get_tags_in(){throw new RuntimeException('Unexpected global tags');}
function _d(){throw new RuntimeException('Unexpected global date format');}
function _dt(){throw new RuntimeException('Unexpected global date format');}
function render_custom_fields($type,$id){if($type!=='tasks')throw new RuntimeException('Wrong custom field type');return'<div data-native-custom-field-boundary="'.e($id).'"></div>';}
require APPPATH.'helpers/func_helper.php';$source=file_get_contents(APPPATH.'helpers/template_helper.php');$start=strpos($source,'function clear_textarea_breaks(');$end=strpos($source,'/**',$start);eval(substr($source,$start,$end-$start));require BASEPATH.'helpers/form_helper.php';require APPPATH.'helpers/fields_helper.php';
$ci=new class{
 public $input,$config,$uri,$load;
 function __construct(){
  $this->input=new class{function get($key){return null;}function post($key=null){return null;}};
  $this->config=new class{function site_url($p){return'https://crm.invalid/sub/'.$p;}function item($k){return false;}};
  $this->uri=new class{function uri_string(){return'admin/tasks/task/8589934593';}};
  $this->load=new class{function is_loaded($key){return false;}};
 }
};$GLOBALS['CI']=$ci;
$dto=['id'=>'8589934593','title'=>'Editar tarefa nativa','members'=>[],'milestones'=>[],'checklistTemplates'=>[],'project_end_date_attrs'=>[],
 'task'=>(object)['id'=>'8589934593','name'=>'Literal <task>','description'=>'Native description','rel_type'=>'project','rel_id'=>'4294967297','billed'=>0,'is_public'=>0,'billable'=>1,'visible_to_client'=>1,'hourly_rate'=>'12.50','milestone'=>null,'startdate'=>'2026-10-25','duedate'=>null,'recurring'=>0,'last_recurring_date'=>null,'priority'=>2,'repeat_every'=>0,'recurring_type'=>null,'custom_recurring'=>0,'cycles'=>0,'total_cycles'=>0,'status'=>3,'datefinished'=>null],
 'native_task_form'=>['options'=>['default_task_status'=>1,'new_task_auto_assign_current_member'=>'0','new_task_auto_follower_current_member'=>'0'],'permissions'=>['view'=>true,'create'=>true,'edit'=>true,'delete'=>true],'counts'=>['assignees'=>2,'followers'=>0,'checklist'=>0,'attachments'=>0],'selected_relation'=>['id'=>'4294967297','name'=>'Projeto autorizado'],'tags_input'=>'Owned tag','start_date_label'=>'25/10/2026','due_date_label'=>'','finished_at_label'=>'','can_client_view'=>true,'project_task_rate'=>false,'fields'=>['_native_company_context'=>'synthetic-context','_native_company_csrf'=>'synthetic-csrf','native_revision'=>1]],
 'task_statuses'=>[['id'=>1,'name'=>'Não iniciado'],['id'=>2,'name'=>'Aguardando feedback'],['id'=>3,'name'=>'Testando'],['id'=>4,'name'=>'Em progresso'],['id'=>5,'name'=>'Concluído']]];
$render=Closure::bind(function($data){extract($data);ob_start();try{require APPPATH.'views/admin/tasks/task.php';return ob_get_clean();}catch(Throwable$e){ob_end_clean();throw$e;}},$ci);
$html=$render($dto);$checks=0;$check=function($pass,$label)use(&$checks){if(!$pass)throw new RuntimeException($label);$checks++;};
foreach(['id="_task_modal"','id="task-form"','name="hourly_rate"','name="repeat_every"','name="tags"','name="native_revision"']as$part)$check(str_contains($html,$part),'Native original field missing '.$part);
$check(str_contains($html,'Literal &lt;task&gt;'),'Task title escaped in original field');$check(str_contains($html,'25/10/2026'),'Supplied company date shown');
$bad=$dto;unset($bad['native_task_form']['counts']['followers']);try{$render($bad);throw new LogicException('Missing count accepted');}catch(RuntimeException$e){$check($e->getMessage()==='native_task_form_projection_incomplete','No invented missing count');}
echo "Native task original modal: $checks checks passed. Renderer only; no browser or authenticated HTTP claim.\n";
