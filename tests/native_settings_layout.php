<?php
/** Actual native Settings parent + shared component; framework I/O is synthetic. */
define('BASEPATH', __DIR__);
define('APPPATH', dirname(__DIR__).'/application/');
require __DIR__.'/support/StrictViewFixture.php';
$GLOBALS['layoutHooks']=[];
function init_head(){echo '<!doctype html><html lang="pt-BR" dir="ltr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>';}
function init_tail(){}
function _l($key){return ['settings'=>'Definições','settings_save'=>'Salvar definições','settings_update'=>'Atualizar'][$key]??$key;}
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function admin_url($s){return 'https://synthetic.invalid/admin/'.$s;}
function site_url($s){return 'https://synthetic.invalid/'.$s;}
function is_admin(){return true;}
function form_open_multipart($url,$attrs){return '<form method="post" enctype="multipart/form-data" action="'.e($url).'" id="'.e($attrs['id']).'" class="'.e($attrs['class']).'"><input type="hidden" name="synthetic_csrf" value="synthetic">';}
function form_close(){return '</form>';}
function hooks(){return new class{public function do_action($event,...$args){$GLOBALS['layoutHooks'][]=$event;}};}
$renderer=new class {
 public $session;public $uri;public $input;public $load;
 public function __construct(){
  $this->session=new class{public function flashdata($key){return null;}};
  $this->uri=new class{public function uri_string(){return 'admin/settings';}};
  $this->input=new class{public function get($key){return null;}};
  $this->load=new class{public function view($name){if($name!=='synthetic-fields')throw new LogicException('Unexpected fixture view');echo '<label for="synthetic">Synthetic field</label><input class="form-control" id="synthetic" name="settings[synthetic]" value="synthetic">';}};
 }
 public function render(){return strictFixtureRender(function(){
  $sections=['general'=>['title'=>'Geral','children'=>[
   ['id'=>'company','name'=>'Informações da Empresa','icon'=>'fa-solid fa-bars-staggered'],
   ['id'=>'email','name'=>'Servidor SMTP','icon'=>'fa-regular fa-envelope','badge'=>['value'=>'2','type'=>'info']],
  ]]];
  $group=['id'=>'email','name'=>'Servidor SMTP','view'=>'synthetic-fields'];
  require APPPATH.'views/admin/settings/all.php';
 });}
};
$html=$renderer->render();
if($GLOBALS['layoutHooks']!==['before_settings_group_view','after_settings_group_view','settings_group_end'])throw new RuntimeException('Native hooks changed');
foreach(['id="settings-form"','enctype="multipart/form-data"','name="synthetic_csrf"','settings-group-update','settings-group-argws_platform','settings-group-info','aria-current="page"','bg-info'] as $required)if(!str_contains($html,$required))throw new RuntimeException('Missing native contract '.$required);
$base=['title'=>'Settings','heading'=>'Safe','active'=>'email','content'=>'<form id="trusted"></form>','sections'=>[['title'=>'Group','children'=>[['id'=>'email','name'=>'<script>bad</script>','url'=>'/admin/settings?group=email','icon'=>'fa fa-envelope']]]]];
$render=static function($settingsPresentation){return strictFixtureRender(static function()use($settingsPresentation){require APPPATH.'views/admin/settings/presentation_layout.php';});};
$safe=$render($base);if(str_contains($safe,'<script>bad</script>')||!str_contains($safe,'&lt;script&gt;'))throw new RuntimeException('Labels not escaped');
foreach(['url'=>['javascript:alert(1)','//external.invalid','/admin/ bad'],'id'=>['bad" onclick="x'],'icon'=>['fa" onload="x']]as$key=>$invalids)foreach($invalids as$value){$bad=$base;$bad['sections'][0]['children'][0][$key]=$value;try{$render($bad);throw new RuntimeException('Unsafe DTO accepted');}catch(LogicException $expected){}}
if(in_array('--html-json',$argv,true))echo json_encode(['native'=>$html],JSON_THROW_ON_ERROR);
else echo "PASS actual native parent hooks/form/sidebar and shared DTO rejection.\n";
