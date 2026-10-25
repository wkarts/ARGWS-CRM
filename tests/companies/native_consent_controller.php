<?php
require __DIR__.'/native_contact_controller.php';
require APPPATH.'services/NativeCompanyConsents.php';NativeCompanyConsentSchema::ensure($db,$p);
require_once APPPATH.'helpers/template_helper.php';
foreach(['firstname','lastname']as$field)if(!$db->field_exists($field,$p.'staff'))$db->query('ALTER TABLE '.$p.'staff ADD '.$field.' varchar(191)');$db->query("UPDATE {$p}staff SET firstname='Synthetic',lastname='Owner' WHERE staffid=7");
// Extend the scoped controller seam only for this exact controller, not an HTTP selector.
// The production dispatcher remains centrally owned and must list each admitted method.
require APPPATH.'controllers/admin/Gdpr.php';
function redirect($url,$method='auto',$code=null){$GLOBALS['nativeGdprRedirect']=[$url,(int)$code];}
$gdpr=new Gdpr();$gdpr->db=$db;$gdpr->input=$controller->input;$gdpr->security=$controller->security;$gdpr->load=$controller->load;
$start=$GLOBALS['essentialChecks'];$gdpr->input->verb='GET';$gdpr->input->body=[];$gdpr->consent_purpose();
essentialCheck(str_contains($gdpr->load->html,'id="consentForm"')&&str_contains($gdpr->load->html,'native_consent_revision'),'same native consent modal includes scoped mutation/revision inputs');
$gdpr->input->verb='POST';$gdpr->input->body=['name'=>'Native controller purpose','description'=>'Original fields'];try{$gdpr->consent_purpose();throw new RuntimeException('missing token accepted');}catch(NativeContactHttpError$e){essentialCheck($e->getCode()>=400,'native purpose create rejects absent CSRF');}
$gdpr->input->body['_native_company_csrf']='synthetic-native-csrf';$gdpr->consent_purpose();essentialCheck($GLOBALS['nativeGdprRedirect'][1]===303&&str_ends_with($GLOBALS['nativeGdprRedirect'][0],'gdpr/index?page=consent'),'same native purpose action redirects to original scoped settings route');
$service=new NativeCompanyConsents($db,$p);$purpose=$service->purposes($a,7)[0];$gdpr->input->body=['contact_id'=>(string)$id,'action'=>'opt-in','purpose_id'=>$purpose['id'],'purpose_revision'=>(string)$purpose['revision'],'description'=>'Explicit','opt_in_purpose_description'=>$purpose['description'],'native_consent_revision'=>hash('sha256','[]'),'_native_company_csrf'=>'synthetic-native-csrf'];
$gdpr->contact_consent_opt_action();essentialCheck($GLOBALS['nativeGdprRedirect'][1]===303&&str_contains($GLOBALS['nativeGdprRedirect'][0],'clients/client/'.$client.'?group=contacts'),'same native consent action preserves customer route/context');
essentialCheck(count($service->history($a,7,$id))===1,'native controller writes only owned explicit consent');
try{$gdpr->contact_consent_opt_action();throw new RuntimeException('duplicate accepted');}catch(NativeContactHttpError$e){essentialCheck($e->getCode()===409,'replayed native consent form is rejected as stale');}
CompanyRequestScope::$issuer=$b;try{$gdpr->contact_consent_opt_action();throw new RuntimeException('foreign accepted');}catch(NativeContactHttpError$e){essentialCheck($e->getCode()===403,'foreign context rejects same native consent POST');}CompanyRequestScope::$issuer=$a;
echo 'Native GDPR controller: '.($GLOBALS['essentialChecks']-$start).' checks passed; no public portal claim.'.PHP_EOL;
$start=$GLOBALS['essentialChecks'];$controller->input->verb='GET';$controller->input->body=[];
$controller->load=new class{public string$html='';public array$data=[];public$load;function __construct(){$this->load=$this;}function view($view,$data=[]){$outer=$this->data;$this->data=array_replace($outer,$data);extract($this->data);ob_start();try{include APPPATH.'views/'.$view.'.php';$html=ob_get_contents();}finally{ob_end_clean();$this->data=$outer;}if($outer)echo$html;else$this->html=$html;}};
$controller->consents($id);essentialCheck(str_contains($controller->load->html,'id="consentModal"')&&str_contains($controller->load->html,'native_consent_revision')&&!str_contains($controller->load->html,'target="_blank"'),'same native admin modal renders owned history without creating disabled public capability');
CompanyRequestScope::$issuer=$b;try{$controller->consents($id);throw new RuntimeException('foreign consent modal accepted');}catch(NativeContactHttpError$e){essentialCheck($e->getCode()===403,'native modal cannot resolve foreign contact');}CompanyRequestScope::$issuer=$a;
echo 'Native contact consent modal: '.($GLOBALS['essentialChecks']-$start).' actual SQLite/controller/view checks passed.'.PHP_EOL;
$db->query('UPDATE '.$p.'contacts SET active=1 WHERE id=?',[$id]);$db->query('UPDATE '.$p.'clients SET active=1,registration_confirmed=1 WHERE userid=?',[$client]);$start=$GLOBALS['essentialChecks'];$settings=new CompanySettingsStorage($db,$p);foreach(['enable_gdpr','gdpr_enable_consent_for_contacts']as$option)$settings->mutate($a,7,'update',$option,'1',null);$controller->config=new class($portalSecret){function __construct(private$s){}function item($key){return$key==='encryption_key'?$this->s:null;}};
$before=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'issuers_audit')->row()->n;$controller->consents($id);essentialCheck(str_contains($controller->load->html,'consent/contact/v1.')&&str_contains($controller->load->html,'issuer_id='.$a)&&!str_contains($controller->load->html,'company_portal'),'same native consent modal issues bounded purpose link on original route');essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'issuers_audit')->row()->n===$before,'admin GET does not persist a consent key or mutate history');
echo 'Native contact consent enabled link: '.($GLOBALS['essentialChecks']-$start).' actual SQLite/controller/view checks passed.'.PHP_EOL;

$db->query('UPDATE '.$p.'contacts SET active=0 WHERE id=?',[$id]);$controller->consents($id);essentialCheck(str_contains($controller->load->html,'consentModal')&&!str_contains($controller->load->html,'consent/contact/v1.'),'revoked contact retains administrative historical view but no live public capability');
