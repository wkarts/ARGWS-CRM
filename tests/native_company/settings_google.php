<?php
require __DIR__.'/calendar_preferences.php';require_once APPPATH.'services/NativeCompanySettingsForms.php';
$forms=new NativeCompanySettingsForms($db,$p);$store=new CompanySettingsStorage($db,$p);
$enc=static fn($v)=>'fixture-cipher:'.base64_encode($v);$dec=static fn($v)=>base64_decode(substr($v,15),true);
$first=$forms->page($a,7,'google');$other=$forms->page($b,7,'google');
$values=$first['values'];$values=array_replace($values,['google_api_key'=>'synthetic-browser-key','google_client_id'=>'synthetic.apps.googleusercontent.com','google_calendar_main_calendar'=>'synthetic-calendar@example.invalid','recaptcha_site_key'=>'synthetic_site_key','recaptcha_secret_key'=>'synthetic_secret_123456','recaptcha_ignore_ips'=>'192.0.2.10','use_recaptcha_customers_area'=>'1','enable_google_picker'=>'1']);
$after=$forms->save($a,7,'google',$values,[],$first['revision'],[],$enc);
essentialCheck($after['values']['recaptcha_secret_key']===''&&$after['secret_configured']===true&&$store->get($a,'recaptcha_secret_key')!==$values['recaptcha_secret_key'],'same Google form saves named captcha encrypted and never hydrates secret');
essentialCheck((new NativePortalPreferences($db,$p))->captchaSecret($a,$dec)===$values['recaptcha_secret_key'],'real portal consumer decrypts only selected company');
essentialCheck($forms->page($b,7,'google')===$other,'Google A never mutates or inherits B');
$preserve=$after['values'];$preserve['google_calendar_main_calendar']='changed@example.invalid';$next=$forms->save($a,7,'google',$preserve,[],$after['revision'],[],$enc);
essentialCheck((new NativePortalPreferences($db,$p))->captchaSecret($a,$dec)===$values['recaptcha_secret_key'],'unhydrated blank password preserves configured secret');
essentialThrows(fn()=>$forms->save($a,7,'google',$preserve,[],$after['revision'],[],$enc),'native_google_settings_stale','full revision guards every Google subcomponent');
$before=$db->query('SELECT settings_json FROM '.$p.'issuers_registry WHERE id=?',[$a])->row()->settings_json;
$db->query("CREATE TRIGGER google_late_fail BEFORE UPDATE ON {$p}issuers_registry WHEN NEW.id={$a} AND NEW.settings_json LIKE '%reject.apps.googleusercontent.com%' BEGIN SELECT RAISE(ABORT,'synthetic late picker failure'); END");
$bad=$next['values'];$bad['google_client_id']='reject.apps.googleusercontent.com';$bad['google_api_key']='must-roll-back';$bad['recaptcha_secret_key']='changed_secret_123456';
essentialThrows(fn()=>$forms->save($a,7,'google',$bad,[],$next['revision'],[],$enc),'issuer_storage_failed','late picker failure rolls back earlier calendar and encrypted secret');
$db->query('DROP TRIGGER google_late_fail');essentialCheck($db->query('SELECT settings_json FROM '.$p.'issuers_registry WHERE id=?',[$a])->row()->settings_json===$before,'exact selected settings document preserved on late failure');
$bad=$next['values'];$bad['recaptcha_ignore_ips']='*';essentialThrows(fn()=>$forms->save($a,7,'google',$bad,[],$next['revision'],[],$enc),'native_portal_preferences_invalid','captcha bypass cannot become wildcard');
essentialThrows(fn()=>$forms->save($a,50,'google',$next['values'],[],$next['revision'],[],$enc),'native_settings_denied','viewer cannot mutate Google credentials');
echo "Native Google Settings atomic compositor: 9 SQLite checks PASS; browser API/OAuth not invoked.\n";
foreach($values as$key=>$value){$db->query('DELETE FROM '.$p.'options WHERE name=?',[$key]);$db->query('INSERT INTO '.$p.'options(name,value) VALUES(?,?)',[$key,$value]);}
$primary=$forms->page($calendarPrimary,7,'google');$pv=$primary['values'];$pv['google_client_id']='principal.apps.googleusercontent.com';$forms->save($calendarPrimary,7,'google',$pv,[],$primary['revision'],[],$enc);
essentialCheck($store->get($calendarPrimary,'google_client_id')===$pv['google_client_id']&&(new NativePortalPreferences($db,$p))->captchaSecret($calendarPrimary,fn()=>throw new LogicException('principal must use native representation'))===$values['recaptcha_secret_key'],'principal original representation preserved without encrypting legacy captcha unexpectedly');
essentialCheck($forms->page($a,7,'google')===$next,'principal settings cannot mutate named company');
function get_option($key){throw new LogicException('global Google option read');}
function _l($key,...$args){return$key;}
function e($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function admin_url($path=''){return'https://fixture.invalid/admin/'.$path;}
function render_input($name,$label,$value,$type='text',$attributes=[]){return '<input type="'.e($type).'" name="'.e($name).'" value="'.e($value).'">';}
function render_textarea($name,$label,$value){return '<textarea name="'.e($name).'">'.e($value).'</textarea>';}
function render_yes_no_option($name,$label,$tooltip='',$yes='',$no='',$one='',$zero='',$explicit=null){if(!is_string($explicit))throw new LogicException('Google boolean missing explicit value');echo render_input('settings['.$name.']',$label,$explicit);}
$native_settings_google=$next;ob_start();require APPPATH.'views/admin/settings/includes/google.php';$html=ob_get_clean();preg_match_all('/name="settings\[([^\]]+)\]"/',$html,$matches);$actual=$matches[1];sort($actual);$expected=NativeCompanyGoogleSettings::FIELDS;sort($expected);
essentialCheck($actual===$expected&&!str_contains($html,$values['recaptcha_secret_key'])&&!str_contains($html,'fixture-cipher:'),'same original Google form has all eight fields and no secret/ciphertext');
essentialCheck(str_contains($html,'type="password"')&&str_contains($html,'/admin/departments'),'native secret input and original departments destination retained');
echo "Native Google original view/principal compatibility: 4 additional checks PASS; rendering helpers are explicit seams.\n";
