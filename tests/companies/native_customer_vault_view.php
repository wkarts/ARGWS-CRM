<?php
require __DIR__.'/native_customer_vault_controller.php';
function form_open($url,$attrs=[]){$s='<form method="post" action="'.e($url).'"';foreach($attrs as$k=>$v)$s.=' '.e($k).'="'.e($v).'"';return$s.'>';}
function form_close(){return'</form>';}
function render_input($name,$label='',$value='',$type='text',$attrs=[]){return'<input name="'.e($name).'" id="'.e($name).'" value="'.e($value).'" type="'.e($type).'">';}
function render_textarea($name,$label='',$value=''){return'<textarea name="'.e($name).'" id="'.e($name).'">'.e($value).'</textarea>';}
function _dt($v){return$v;}function time_ago($v){return$v;}
function is_admin(){throw new LogicException('native vault must not infer global administration');}
function get_staff_user_id(){throw new LogicException('native vault must not use global identity');}
function process_text_content_for_display($v){throw new LogicException('native vault description must be escaped explicitly');}
$GLOBALS['essentialHooks']=new class{function __call($name,$args){throw new LogicException('global hook forbidden');}};
$renderer=new class{public $load;function render($view,$data){extract($data);ob_start();try{include APPPATH.'views/'.$view.'.php';return ob_get_contents();}finally{ob_end_clean();}}};$renderer->load=new class($renderer){function __construct(private$r){}function view($view,$data=[]){echo$this->r->render($view,$GLOBALS['vaultViewData']+$data);}};
$GLOBALS['vaultViewData']=$view[1];$GLOBALS['vaultViewData']['vault_entries'][0]['description']='<script>synthetic injection</script>';$html=$renderer->render('admin/clients/groups/vault',$GLOBALS['vaultViewData']);
essentialCheck(str_contains($html,'id="entryModal"')&&str_contains($html,'id="vaultConfirmPassword"'),'original native vault and reauthentication modals retained');
essentialCheck(str_contains($html,'data-native-vault-revision=')&&str_contains($html,'name="_native_company_csrf"')&&str_contains($html,'data-native-admin-remove'),'native vault mutations carry revision and explicit token');
essentialCheck(!str_contains($html,'<script>synthetic injection</script>')&&str_contains($html,'&lt;script&gt;synthetic injection&lt;/script&gt;'),'description cannot inject HTML');
essentialCheck(str_contains($html,"document.addEventListener('DOMContentLoaded'")&&str_contains($html,'window.edit_vault_entry'),'same native script is initialized after dependencies without global hooks');
if(isset($argv[1]))file_put_contents($argv[1],$html);
echo "Native vault original view: 4 checks passed, no browser claim.\n";
