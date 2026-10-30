<?php
/** Real public-route preparation/resolution; SQL uses a disposable SQLite fixture. */
require __DIR__.'/native_portal_files_controller.php';
$scopeStart=$GLOBALS['essentialChecks'];

// The existing native binding is read before global hydration even if the
// issuer selector is removed. Only an actual legacy session may fall through.
$ci->router->class='clients';$ci->router->method='files';$ci->input->verb='GET';
$ci->input->get=[];$ci->input->post=[];
essentialCheck(PortalRequestScope::prepare($ci)&&PortalRequestScope::resolve($ci)&&PortalRequestScope::issuerId()===41,'direct URL without selector recovers A from the authenticated portal session');
$boundSession=$ci->session;$ci->session=new class{public array$data=['client_logged_in'=>true,'client_user_id'=>99991,'contact_user_id'=>903];function userdata($key){return$this->data[$key]??null;}};
essentialCheck(!PortalRequestScope::prepare($ci),'only a verified ownerless principal contact keeps the legacy entry path');
$ci->session->data=['client_logged_in'=>true,'client_user_id'=>$nativeA,'contact_user_id'=>901];essentialCheck(PortalRequestScope::prepare($ci),'named-company session without a native binding is intercepted');essentialThrows(fn()=>PortalRequestScope::resolve($ci),'native_portal_context_invalid','named-company session without binding cannot reach global models');
$ci->session->data=['client_logged_in'=>true,'client_user_id'=>99991,'contact_user_id'=>901];essentialCheck(PortalRequestScope::prepare($ci),'mismatched contact and customer session is intercepted');essentialThrows(fn()=>PortalRequestScope::resolve($ci),'native_portal_context_invalid','mismatched legacy identity fails closed');$ci->session=$boundSession;
$ci->router->method='statement';essentialCheck(PortalRequestScope::prepare($ci),'unadapted direct URL without selector is intercepted for a native session');essentialThrows(fn()=>PortalRequestScope::resolve($ci),'native_portal_route_unavailable','direct unadapted URL never falls through to global models');
$ci->router->method='statement';$ci->input->get=['issuer_id'=>'41'];
essentialCheck(PortalRequestScope::prepare($ci),'unadapted selected customer route is retained before global hydration');
essentialThrows(fn()=>PortalRequestScope::resolve($ci),'native_portal_route_unavailable','unadapted statement never falls through to legacy global models');

$ci->router->method='files';
essentialCheck(PortalRequestScope::prepare($ci)&&PortalRequestScope::resolve($ci),'explicit files URL uses the real native route resolver');
essentialCheck(PortalRequestScope::sealedFor($ci)&&PortalRequestScope::issuerId()===41&&PortalRequestScope::identity()['binding']===$binding,'issuer and contact come from the authenticated binding');
$db->query('UPDATE '.$p.'contacts SET email_verified_at=NULL WHERE id=901');
essentialCheck(PortalRequestScope::prepare($ci),'unverified contact remains inside protected route guard');
essentialThrows(fn()=>PortalRequestScope::resolve($ci),'native_portal_verification_required','unverified or expired verification cannot access protected files directly');
$db->query("UPDATE {$p}contacts SET email_verified_at='2026-01-01 00:00:00' WHERE id=901");
essentialCheck(PortalRequestScope::prepare($ci)&&PortalRequestScope::resolve($ci),'a verified contact may use protected files without an artificial expiration');
$downloadUrl=NativePortalPresentation::url('download/file/client/'.$portalDto['attachment_key']);
essentialCheck(str_contains($downloadUrl,'/download/file/client/')&&str_contains($downloadUrl,'issuer_id=41')&&str_contains($downloadUrl,'_native_portal=1'),'original file route carries an explicit portal marker');
essentialCheck(!str_contains(NativePortalPresentation::url('clients/files'),'_native_portal='),'ordinary portal navigation has no download marker');

// A selector is not an authorization. It must match the signed-in identity.
$ci->input->get=['issuer_id'=>'82'];
essentialCheck(PortalRequestScope::prepare($ci)&&PortalRequestScope::resolve($ci)&&PortalRequestScope::identity()===null,'foreign issuer never adopts the A contact session');
essentialThrows(fn()=>$ci->files(),'native_portal_files_denied','foreign issuer cannot render A files');
$db->query('UPDATE '.$p.'contacts SET password=? WHERE id=902',[password_hash($password,PASSWORD_DEFAULT)]);
$bindingB=$portal->login(82,'other@example.invalid',$password,'password_verify');
essentialCheck(is_array($bindingB),'B contact has its own real session binding');
(new NativePortalIdentity($db,$p,str_repeat('synthetic-',4)))->establish($ci->session,82,$bindingB);
essentialCheck(PortalRequestScope::prepare($ci)&&PortalRequestScope::resolve($ci)&&PortalRequestScope::identity()['binding']===$bindingB,'B resolves only after re-authentication for B');
$ci->input->get=[];essentialCheck(PortalRequestScope::prepare($ci)&&PortalRequestScope::resolve($ci)&&PortalRequestScope::issuerId()===82,'direct URL without selector preserves B binding');
ob_start();$ci->files();$htmlB=ob_get_clean();essentialCheck(!str_contains($htmlB,$portalDto['attachment_key']),'B file page does not expose the A file key');
(new NativePortalIdentity($db,$p,str_repeat('synthetic-',4)))->establish($ci->session,41,$binding);
$ci->input->get=['issuer_id'=>'41'];essentialCheck(PortalRequestScope::prepare($ci)&&PortalRequestScope::resolve($ci)&&PortalRequestScope::identity()['binding']===$binding,'switching back restores only the A contact binding');
$ci->input->get=['issuer_id'=>['41']];
essentialCheck(PortalRequestScope::prepare($ci),'malformed selector cannot fall back to the legacy route');
essentialThrows(fn()=>PortalRequestScope::resolve($ci),'native_portal_context_invalid','malformed issuer selector is denied before data access');
$ci->router->method='upload_files';$ci->input->verb='POST';$ci->input->get=['issuer_id'=>'41'];$ci->input->post=['issuer_id'=>'82'];
essentialCheck(PortalRequestScope::prepare($ci),'conflicting mutation selectors remain inside the native guard');
essentialThrows(fn()=>PortalRequestScope::resolve($ci),'native_portal_context_invalid','conflicting company selectors cannot be submitted');
$ci->input->post=['issuer_id'=>'41'];
essentialCheck(PortalRequestScope::prepare($ci)&&PortalRequestScope::resolve($ci),'upload resolves the authenticated company before processing any file');
essentialThrows(fn()=>$ci->upload_files(),'native_portal_csrf_denied','a company selector without contact CSRF never grants upload');

// Portal and staff share a canonical download endpoint. No portal marker means
// this scope leaves the request for the existing staff/legacy bootstrap.
$download->router->class='download';$download->router->method='file';$download->input->verb='GET';$download->input->post=[];$download->input->get=['issuer_id'=>'41'];$download->uri=new class{function segment($n){return$n===3?'client':null;}};
essentialCheck(!PortalRequestScope::prepare($download),'unmarked download remains available to the separate staff/legacy scope');
$download->input->get=[];$download->session->unset_userdata(['staff_logged_in','staff_user_id']);
essentialCheck(PortalRequestScope::prepare($download)&&PortalRequestScope::resolve($download)&&PortalRequestScope::issuerId()===41,'contact-only session recovers company on unmarked direct download');
$download->session->set_userdata(['staff_logged_in'=>true,'staff_user_id'=>7]);$download->input->get=['issuer_id'=>'41'];
$download->input->get['_native_portal']='1';
essentialCheck(PortalRequestScope::prepare($download)&&PortalRequestScope::resolve($download)&&PortalRequestScope::sealedFor($download),'marked original download route resolves the portal identity');
$db->query('UPDATE '.$p.'contacts SET email_verified_at=NULL WHERE id=901');
essentialCheck(PortalRequestScope::prepare($download),'unverified marked download is retained in protected bootstrap');
essentialThrows(fn()=>PortalRequestScope::resolve($download),'native_portal_verification_required','unverified contact cannot bypass policy with direct file download');
$db->query("UPDATE {$p}contacts SET email_verified_at='2026-01-01 00:00:00' WHERE id=901");
essentialCheck(PortalRequestScope::prepare($download)&&PortalRequestScope::resolve($download),'verified contact can resolve the original download route');
essentialCheck($download->session->userdata('staff_logged_in')===true&&$download->session->userdata('staff_user_id')===7,'simultaneous staff session stays independent from contact download scope');
$download->file('client',$portalDto['attachment_key']);essentialCheck($download->output->body===$bytes,'marked portal download uses the contact binding even when staff is also signed in');
essentialThrows(fn()=>$download->file('ticket',$portalDto['attachment_key']),'fixture_http_404','portal marker cannot access unrelated download types');
$native_portal=true;$native_portal_identity=PortalRequestScope::identity();$native_portal_issuer=41;$native_portal_csrf=PortalRequestScope::csrfToken($download);$native_portal_logo='https://fixture.test/logo.png';$native_portal_options=['companyname'=>'Synthetic'];$menu=NativePortalPresentation::menu(['allow_registration'=>'1']);
essentialCheck(isset($menu['files'])&&!isset($menu['login'])&&str_contains($menu['files']['href'],'clients/files?issuer_id=41'),'authenticated original navigation links to the company-scoped files page');
ob_start();include APPPATH.'views/themes/perfex/template_parts/navigation.php';$nav=ob_get_clean();
essentialCheck(str_contains($nav,'authentication/logout?issuer_id=41')&&str_contains($nav,'method="post"')&&str_contains($nav,'name="_native_portal_csrf"'),'authenticated original navigation logs out with scoped POST and CSRF');
NativePortalIdentity::clear($download->session);
essentialCheck(PortalRequestScope::prepare($download)&&PortalRequestScope::resolve($download)&&PortalRequestScope::identity()===null,'portal marker does not create a contact session');
essentialThrows(fn()=>$download->file('client',$portalDto['attachment_key']),'fixture_http_404','staff session alone cannot use the contact-bound download');
essentialCheck($download->session->userdata('staff_logged_in')===true&&$download->session->userdata('staff_user_id')===7,'clearing contact session preserves staff authentication');
essentialCheck(isset(NativePortalPresentation::menu(['allow_registration'=>'1'])['login']),'anonymous navigation retains the original login option');

// Explicit primary selection is bound independently of the two secondaries.
$primaryNativeBinding=$portal->login($primary,'principal-file@example.invalid',$password,'password_verify');
essentialCheck(is_array($primaryNativeBinding),'principal contact authenticates under the actual portal secret');
(new NativePortalIdentity($db,$p,str_repeat('synthetic-',4)))->establish($ci->session,$primary,$primaryNativeBinding);
$ci->router->class='clients';$ci->router->method='files';$ci->input->verb='GET';$ci->input->get=['issuer_id'=>(string)$primary];$ci->input->post=[];
essentialCheck(PortalRequestScope::prepare($ci)&&PortalRequestScope::resolve($ci)&&PortalRequestScope::issuerId()===$primary&&PortalRequestScope::identity()['binding']===$primaryNativeBinding,'principal resolves only its own persisted binding after A and B');
$ci->input->get=[];essentialCheck(PortalRequestScope::prepare($ci)&&PortalRequestScope::resolve($ci)&&PortalRequestScope::issuerId()===$primary,'direct principal URL without selector preserves the authenticated principal binding');
ob_start();$ci->files();$htmlPrimary=ob_get_clean();essentialCheck(!str_contains($htmlPrimary,$portalDto['attachment_key']),'principal files never expose a secondary company key');
$ci->router->class='estimate';$ci->router->method='unadapted';$ci->input->get=[];essentialCheck(PortalRequestScope::prepare($ci),'unadapted official estimate action with native binding is intercepted');essentialThrows(fn()=>PortalRequestScope::resolve($ci),'native_portal_route_unavailable','unadapted estimate action cannot hydrate legacy global data');

echo 'Native portal files route scope: '.($GLOBALS['essentialChecks']-$scopeStart).' SQLite checks passed; HTTP browser still requires validation.'.PHP_EOL;
