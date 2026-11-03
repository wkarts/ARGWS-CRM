<?php
require __DIR__.'/native_portal_avatar.php';
require __DIR__.'/native_portal_http_fixture.php';
require_once APPPATH.'services/NativePortalIdentity.php';
require_once APPPATH.'vendor/autoload.php';require_once APPPATH.'services/ValidatesContact.php';require_once APPPATH.'controllers/Clients.php';require_once APPPATH.'helpers/custom_fields_helper.php';
function form_open_multipart($path,$attrs=[]){return form_open($path,$attrs);}
function set_alert($type,$message){$GLOBALS['profileAlerts'][]=[$type,$message];}
function time_ago($date){return$date;}
function is_client_logged_in(){return true;}
function is_admin(){throw new LogicException('public profile must not read staff authority');}
class NativeProfileControllerFixture extends Clients{
 public function layout($notInThemeViewFiles=false){extract($this->data);if(!in_array($this->view,['profile','native_profile','announcements','announcement'],true))throw new LogicException('unexpected native view');include APPPATH.'views/themes/perfex/views/'.$this->view.'.php';}
}
$ci=(new ReflectionClass(NativeProfileControllerFixture::class))->newInstanceWithoutConstructor();foreach(get_object_vars($auth)as$k=>$v)$ci->$k=$v;$ci->db=$db;$ci->config=new class($secret){function __construct(private$s){}function item($key){return$key==='encryption_key'?$this->s:($key==='base_url'?'https://fixture.test/crm/':null);}};
$db->query('UPDATE '.$p.'contacts SET active=1,email_verified_at=? WHERE id=?',['2026-01-01 00:00:00',$r['contact_id']]);$ci->session->set_userdata([NativePortalIdentity::SESSION_KEY=>$pw['binding'],'client_logged_in'=>true,'client_user_id'=>$r['client_id'],'contact_user_id'=>$r['contact_id']]);$ci->input->get=['issuer_id'=>'41'];$ci->input->post=[];$ci->input->verb='GET';$ci->router->class='clients';$ci->router->method='profile';
essentialCheck(PortalRequestScope::prepare($ci),'explicit profile route is admitted by the real portal gate');
$bindScope=function($method)use($ci){$ci->router->method=$method;if(!PortalRequestScope::prepare($ci)||!PortalRequestScope::resolve($ci))throw new RuntimeException('portal_scope_fixture_denied');};$bindScope('profile');$f->ci=$ci;essentialUse($f);
$ci->data(['native_portal'=>true,'native_portal_issuer'=>41,'native_portal_csrf'=>PortalRequestScope::csrfToken($ci)]);$start=$GLOBALS['essentialChecks'];ob_start();$ci->profile();$html=ob_get_clean();
essentialCheck(str_contains($html,'section-profile')&&str_contains($html,'profile-firstname-group')&&str_contains($html,'contact-profile-change-password-section'),'actual Clients profile reuses original native view/DOM');
essentialCheck(str_contains($html,'native_profile_revision')&&str_contains($html,'_native_portal_csrf')&&str_contains($html,'issuer_id'),'both original forms carry scoped revision and CSRF');
essentialCheck(str_contains($html,'custom_fields[contacts]')&&str_contains($html,'disabled')&&!str_contains($html,'Private'),'same field renderer excludes hidden definitions and disables readonly ones');
$revision=$ci->data['native_profile_revision'];$ci->input->verb='POST';$ci->input->post=$payload+['profile'=>'1','native_profile_revision'=>$revision,'_native_portal_csrf'=>PortalRequestScope::csrfToken($ci),'issuer_id'=>'41'];$ci->input->post['email']='changed-public@example.invalid';$ci->input->post['firstname']='HTTP';$ci->input->post['custom_fields']=['contacts'=>[$public['id']=>'HTTP value']];
try{$ci->profile();throw new RuntimeException('expected redirect');}catch(NativePortalRedirect$e){essentialCheck($e->status===303&&$e->url==='https://fixture.test/crm/clients/profile?issuer_id=41','actual POST returns original route only after confirmed write');}
essentialCheck($db->query('SELECT firstname FROM '.$p.'contacts WHERE id=?',[$r['contact_id']])->row()->firstname==='HTTP','actual controller writes bound contact only');
$ci->input->post['_native_portal_csrf']=str_repeat('0',64);essentialThrows(fn()=>$ci->profile(),'native_portal_csrf_denied','actual controller rejects invalid public CSRF');
$bindScope('remove_profile_image');$ci->input->verb='GET';essentialThrows(fn()=>$ci->remove_profile_image(),'native_portal_csrf_denied','avatar removal never mutates on GET');
$ci->input->verb='POST';$current=new NativePortalProfile($db,$p,$secret,41,$ci->session->userdata(NativePortalIdentity::SESSION_KEY));$ci->input->post=['issuer_id'=>'41','native_profile_revision'=>$current->read()['revision'],'_native_portal_csrf'=>PortalRequestScope::csrfToken($ci)];try{$ci->remove_profile_image();}catch(NativePortalRedirect$e){essentialCheck($e->status===303&&$current->avatar()===null,'actual POST removes only bound canonical avatar');}
echo 'Native public profile actual controller/original view: '.($GLOBALS['essentialChecks']-$start).' checks passed; HTTP browser is separate.'.PHP_EOL;
