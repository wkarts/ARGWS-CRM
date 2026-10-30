<?php
$presentationDirect=realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__;
if($presentationDirect&&PHP_SAPI!=='cli'){http_response_code(404);exit;}
set_error_handler(static function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
if($presentationDirect&&array_diff(array_slice($argv,1),['--render'])){throw new InvalidArgumentException('Unknown presentation fixture flag');}
// Dependency-negative real view renderer: models/hooks/options are forbidden.
define('BASEPATH',__DIR__);define('APPPATH',dirname(__DIR__,2).'/application/');define('ARGWS_VERSION','test');
function check($v,$m){if(!$v)throw new RuntimeException($m);}
check($app['options']['allowed_files']==='.pdf' && (int)$app['max_php_ini_upload_size_bytes']>0,'upload preferences and actual PHP cap projected');check(!array_key_exists('google_api',$app['options']),'no provider settings');
check($data['total_unfinished_todos']===5,'actual todo provider count not fabricated');
check(count($data['quick_actions'])===2,'quick actions use explicit create ACL and native route catalog');
check($data['quick_actions'][1]['href_attributes']['onclick']==='new_task();return false;','native task handler preserved');
ob_start();foreach(['head','header','setup_menu','aside'] as $name)$ci->view('admin/includes/'.$name);echo '<div id="wrapper"><main class="content"><div class="panel_s"><div class="panel-body"><form><label for="native-field">Nome</label><input id="native-field" name="name" class="form-control" value="Sintético"><button type="button" class="btn btn-primary">Salvar</button></form></div></div></main></div>'; $ci->view('admin/includes/scripts');echo '</body></html>';$html=ob_get_clean();
if($presentationDirect&&in_array('--render',$argv??[],true)){echo $html;exit;}
foreach(['id="header"','id="menu"','id="side-menu"','id="setup-menu"','native-company-admin','&lt;Alice&gt;','/sub/admin/clients','/sub/admin/invoices'] as $needle)check(str_contains($html,$needle),'native render '.$needle);
foreach(['company_context','clients_model','company-shell.js','company-workspace.js','assets/js/main.js','admin/projects/pinned'] as $needle)check(!str_contains($html,$needle),'no parallel/global hydration '.$needle);
check(!str_contains($html,'synthetic@example.test</script>'),'escaped identity');
