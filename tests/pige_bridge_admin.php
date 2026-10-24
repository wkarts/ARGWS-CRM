<?php
/** Actual controller entrypoint guards with synthetic framework I/O; no database. */
define('BASEPATH',__DIR__.'/../system/');define('APPPATH',__DIR__.'/../application/');
$admin=true;$errors=[];$alerts=[];
function is_admin(){return $GLOBALS['admin'];}function access_denied($name){$GLOBALS['errors'][]=403;}function show_error($message,$code){$GLOBALS['errors'][]=$code;}function get_staff_user_id(){return 7;}function db_prefix(){return 'fixture_';}function admin_url($url){return '/admin/'.$url;}function redirect($url){}function set_alert($level,$message){$GLOBALS['alerts'][]=$level;}
class AdminController {public $db,$encryption,$input,$output,$session,$load;public function __construct(){ $this->db=new stdClass();$this->encryption=new stdClass();$this->input=new class {public $verb='POST';public array $values=[];public function method($upper=false){return $this->verb;}public function post($key,$xss=false){return $this->values[$key]??null;}};$this->output=new class {public function set_header($header){return $this;}};$this->session=new class {public function userdata($key){return str_repeat('a',64);}};$this->load=new class {public function library($name){}};}}
require __DIR__.'/../modules/pige_bridge/controllers/Settings.php';
function adminOk($ok){if(!$ok)throw new RuntimeException('admin boundary failed');}
$c=new Settings();$admin=false;$c->save();adminOk(end($errors)===403);$admin=true;$c->input->verb='GET';$c->save();adminOk(end($errors)===405);
$c->input->verb='POST';$c->input->values=['pige_csrf'=>'wrong'];$c->save();adminOk(end($errors)===403);
$c->input->values=['pige_csrf'=>str_repeat('a',64),'revision'=>'1','secret'=>str_repeat('synthetic-',5)];$c->save();adminOk(end($alerts)==='danger'); // Rotation confirmation required before any DB.
$c->input->values=['pige_csrf'=>str_repeat('a',64),'actor_id'=>['invalid']];$c->mapping();adminOk(end($alerts)==='danger');
echo "PIGE admin guards: synthetic controller checks only\n";
