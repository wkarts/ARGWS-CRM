<?php
/** Native controller guards and positive canonical services with synthetic bank. */
require __DIR__.'/banco_brasil_resume_439_mysql.php';
class AdminController{public$input,$session,$db,$load,$encryption,$config,$output;}
class ExtensionHttpStop extends RuntimeException{}class ExtensionRedirectStop extends RuntimeException{}
function get_staff_user_id(){return$GLOBALS['extensionControllerActor'];}function admin_url($path){return'/admin/'.$path;}function show_error($message,$status){throw new ExtensionHttpStop($message,$status);}function redirect($path){throw new ExtensionRedirectStop($path);}function set_alert($type,$message){$GLOBALS['extensionControllerAlert']=[$type,$message];}function issuer_resource_can($issuer,$actor,$feature,$cap){return(new IssuerResourceAccess($GLOBALS['extensionControllerDb'],db_prefix()))->can($issuer,$actor,$feature,$cap);}
require APP_MODULES_PATH.'banco_brasil/controllers/Banco_brasil.php';
class ExtensionControllerHarness extends Banco_brasil{protected function api():\BancoBrasil\ApiService{return$GLOBALS['extensionControllerService'];}}
class ExtensionRequest{public function __construct(public array$data,public string$verb='POST'){}public function post($key,$x=false){return$this->data[$key]??null;}public function method(){return$this->verb;}}
class ExtensionSession{public array$data=['bb_admin_csrf'=>'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'];public function userdata($key){return$this->data[$key]??null;}public function set_userdata($key,$value){$this->data[$key]=$value;}}
$GLOBALS['extensionControllerActor']=39;$GLOBALS['extensionControllerDb']=$db;$GLOBALS['extensionControllerService']=$bb439Api;
function extensionController(array$data,string$verb='POST'):ExtensionControllerHarness{$c=(new ReflectionClass(ExtensionControllerHarness::class))->newInstanceWithoutConstructor();$c->input=new ExtensionRequest($data,$verb);$c->session=new ExtensionSession();$c->db=$GLOBALS['extensionControllerDb'];return$c;}
// Existing employee39 has only view/issue/invoice-view. No amendment grant is inherited.
$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,39,'banco_brasil','api_query')",[$a]);
$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,39,'invoices','edit')",[$a]);
$invoice=$s->create($a,7,$apiInput,$bb439Key('controller-invoice'));$snapshot=$s->document($a,7,$invoice)->data();
$origin=$bb439Api->issue($a,7,$bb439DiscountProfile,$invoice,$bb439DiscountTitle,$bb439Key('controller-issue'));$bb439Api->query($a,7,$origin,$bb439Key('controller-normal'));
foreach([['amend_api_abatement','abatement',$bb439Abat,'amend_abat','api_amend_abatement'],['amend_api_address','address_instruction',$bb439Address,'amend_addr','api_amend_address'],['amend_api_discount','discount_instruction',$bb439Discount,'amend_disc','api_amend_discount']] as [$action,$field,$intent,$operation,$capability]){
 $base=['csrf_bb'=>str_repeat('a',64),'issuer_id'=>(string)$a,'origin_id'=>(string)$origin,'request_key'=>$bb439Key('controller-'.$action),$field=>$intent,'actor_id'=>'7'];$before=$bb439PatchCount;
 foreach([['GET',$base,405],['POST',array_replace($base,['csrf_bb'=>'bad']),403],['POST',array_replace($base,['issuer_id'=>(string)$b]),403],['POST',$base,403]] as [$verb,$input,$status]){
  try{extensionController($input,$verb)->$action();throw new LogicException('guard did not run');}catch(ExtensionHttpStop $e){essentialCheck($e->getCode()===$status,'119 native controller verb/CSRF/company/explicit capability guard');}essentialCheck($bb439PatchCount===$before,'119 rejected controller request never reaches PATCH');
 }
 $db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,39,'banco_brasil',?)",[$a,$capability]);
 foreach([array_replace($base,[$field=>'not-an-array']),array_replace($base,['request_key'=>null])]as$bad){try{extensionController($bad)->$action();throw new LogicException('invalid field did not redirect');}catch(ExtensionRedirectStop $e){essentialCheck($GLOBALS['extensionControllerAlert'][0]==='warning','119 malformed controller input warns');}essentialCheck($bb439PatchCount===$before,'119 malformed input makes no PATCH');}
 $bad=$base;unset($bad[$field]['contract_confirmed']);try{extensionController($bad)->$action();throw new LogicException('missing contract did not redirect');}catch(ExtensionRedirectStop $e){essentialCheck($GLOBALS['extensionControllerAlert'][0]==='warning','119 contract remains explicit at controller');}essentialCheck($bb439PatchCount===$before,'119 missing contract makes no PATCH');
 foreach([1,2]as$attempt){try{extensionController($base)->$action();throw new LogicException('positive request did not redirect');}catch(ExtensionRedirectStop $e){essentialCheck($GLOBALS['extensionControllerAlert'][0]==='success','119 actual native controller dispatches canonical service');}}
 $row=$db->query("SELECT * FROM {$p}bb_api_operations WHERE origin_id=? AND operation=? ORDER BY id DESC LIMIT 1",[$origin,$operation])->row_array();essentialCheck((int)$row['actor_id']===39&&$row['status']==='validated'&&$bb439PatchCount===$before+1,'119 session employee wins over forged actor and duplicate POST has one PATCH');
 try{extensionController(['csrf_bb'=>str_repeat('a',64),'issuer_id'=>(string)$a,'origin_id'=>(string)$origin,'request_key'=>$bb439Key('controller-confirm-'.$action)])->query_api();throw new LogicException('query did not redirect');}catch(ExtensionRedirectStop $e){essentialCheck($GLOBALS['extensionControllerAlert'][0]==='success','119 query controller independently reads account');}
 essentialCheck($db->query("SELECT status FROM {$p}bb_api_operations WHERE id=?",[$row['id']])->row()->status==='confirmed','119 query controller confirms intent only after provider observation');
 essentialCheck($s->document($a,7,$invoice)->data()===$snapshot&&$s->payments($a,7,$invoice)===[],'119 native controller preserves complete document and zero payments');
}
echo 'BB119 native amendment controllers: '.$GLOBALS['essentialChecks'].' cumulative real DB checks; actual controller methods and session/CSRF/ACL gates, request objects adapted, synthetic bank only.'.PHP_EOL;
