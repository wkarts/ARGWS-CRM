<?php
/** Actual Authentication/ClientsController/original theme and identity services; framework transport seams only. */
require __DIR__.'/native_portal_remember.php';
require APPPATH.'services/PortalRequestScope.php';
require APPPATH.'services/NativePortalPreferences.php';
require __DIR__.'/native_portal_http_fixture.php';
$db->query('ALTER TABLE '.$p."contacts ADD email_verified_at datetime DEFAULT '2026-01-01 00:00:00'");
$start=$GLOBALS['essentialChecks'];$auth->input->get=['issuer_id'=>(string)$a];PortalRequestScope::prepare($auth);PortalRequestScope::resolve($auth);$auth->__construct();ob_start();$auth->login();$html=ob_get_clean();
essentialCheck(str_contains($html,'class="login-form"')&&str_contains($html,'name="remember"')&&str_contains($html,'name="issuer_id"')&&str_contains($html,'name="_native_portal_csrf"'),'original login form and native hidden ownership fields render without global hooks/options');
essentialCheck(str_contains($html,'assets/themes/perfex/css/style.css')&&str_contains($html,'authentication/forgot_password?issuer_id='.$a)&&!str_contains($html,'company_portal'),'original native theme and routes retain explicit issuer');
$auth->input->verb='POST';$auth->input->post=['issuer_id'=>(string)$a,'email'=>'new@example.invalid','password'=>'final-new-password-a','remember'=>'on','_native_portal_csrf'=>PortalRequestScope::csrfToken($auth)];
try{$auth->login();throw new RuntimeException('expected redirect');}catch(NativePortalRedirect$e){essentialCheck($e->status===303&&$e->url==='https://fixture.test/crm/clients?issuer_id='.$a,'native login redirects only to original clients route');}
essentialCheck($auth->session->userdata('client_logged_in')===true&&$auth->session->userdata('staff_user_id')===7,'native login establishes contact without replacing independent staff session');
$cookieHeaders=array_values(array_filter($auth->output->headers,fn($v)=>str_starts_with($v,'Set-Cookie:')));$header=end($cookieHeaders);essentialCheck(str_contains($header,'Path=/crm/')&&str_contains($header,'Secure; HttpOnly; SameSite=Lax')&&!str_contains($header,'Domain='),'actual adapter emits secure host-only cookie despite old CI helper limitations');
preg_match('/argws_native_contact_remember=([^;]+)/',$header,$match);$auth->input->cookies[NativePortalRemember::COOKIE]=rawurldecode($match[1]);
$auth->router->method='logout';PortalRequestScope::prepare($auth);PortalRequestScope::resolve($auth);$auth->input->post['_native_portal_csrf']=PortalRequestScope::csrfToken($auth);
try{$auth->logout();throw new RuntimeException('expected logout redirect');}catch(NativePortalRedirect$e){essentialCheck($e->status===303&&str_contains($e->url,'authentication/login?issuer_id='),'logout preserves original issuer login URL');}
essentialCheck($auth->session->userdata('client_logged_in')===null&&$remember->consume($a,$auth->input->cookies[NativePortalRemember::COOKIE])===null,'logout clears contact session and revokes remembered validator');
echo 'Native portal actual authentication/theme: '.($GLOBALS['essentialChecks']-$start).' checks passed; no browser pixels.'.PHP_EOL;
$primary=(int)$db->query('SELECT primary_issuer_id FROM '.$p.'issuers_installation_identity WHERE id=1')->row()->primary_issuer_id;
$auth->router->method='login';$auth->input->get=[];$auth->input->cookies=[];$auth->input->post=[];$auth->input->verb='GET';PortalRequestScope::prepare($auth);PortalRequestScope::resolve($auth);essentialCheck(PortalRequestScope::issuerId()===$primary,'no selector resolves persisted principal, not named identity by email');
$auth->input->verb='POST';$auth->input->post=['email'=>'new@example.invalid','password'=>'final-new-password-a','_native_portal_csrf'=>PortalRequestScope::csrfToken($auth)];ob_start();$auth->login();ob_end_clean();essentialCheck($auth->session->userdata('client_logged_in')===null,'named unique email cannot authenticate through issuer-absent principal route');
$db->query('INSERT INTO '.$p.'contacts(userid,firstname,lastname,email,phonenumber,datecreated,password,active,is_primary,email_verified_at) VALUES(?,?,?,?,?,?,?,1,1,?)',[9,'Principal','Synthetic','new@example.invalid','',gmdate('Y-m-d H:i:s'),password_hash('Principal-password-123',PASSWORD_DEFAULT),gmdate('Y-m-d H:i:s')]);$principalContact=(int)$db->insert_id();
$auth->input->post['password']='Principal-password-123';try{$auth->login();throw new RuntimeException('expected principal redirect');}catch(NativePortalRedirect$e){essentialCheck($e->url==='https://fixture.test/crm/clients?issuer_id='.$primary&&(int)$auth->session->userdata('contact_user_id')===$principalContact,'colliding A/principal emails resolve only within persisted principal');}
$db->query('UPDATE '.$p.'contacts SET active=0 WHERE id=?',[$principalContact]);essentialCheck((new NativePortalIdentity($db,$p,str_repeat('synthetic-',4)))->current($auth->session,$primary)===null,'principal contact revocation invalidates native binding');NativePortalIdentity::clear($auth->session);
echo 'Native principal authentication: 4 additional actual controller/SQLite checks passed.'.PHP_EOL;
