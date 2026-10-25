<?php
require __DIR__.'/settings_email.php';
require_once APPPATH.'services/NativeCompanySettingsForms.php';
function _l($key,...$args){return$key;}
function get_option($key){throw new RuntimeException('global email option lookup forbidden');}
function html_escape($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function set_value($name,$value=''){return html_escape($value);}
function e($value){return html_escape($value);}
function admin_url($path=''){return 'https://crm.example.test/sub/admin/'.$path;}
require_once APPPATH.'helpers/template_helper.php';
require_once APPPATH.'helpers/fields_helper.php';
$native_settings_email=(new NativeCompanySettingsForms($db,$p))->page(41,12,'email');
$native_settings_email['cron_ready']='1';$native_settings_email['queue']=[(object)['id'=>19,'subject'=>'<script>synthetic</script>','email'=>'a@example.invalid','status'=>'pending']];
$native_settings_email['command_fields']=['_native_company_csrf'=>str_repeat('a',64),'_company_context'=>'synthetic','csrf'=>'synthetic'];
set_error_handler(static function($level,$message){throw new ErrorException($message,0,$level);});
try{ob_start();require APPPATH.'views/admin/settings/includes/email.php';$html=ob_get_clean();}finally{restore_error_handler();}
essentialCheck(!str_contains($html,'synthetic-google-secret')&&!str_contains($html,'synthetic-microsoft-secret')&&!str_contains($html,'synthetic-smtp-secret'),'native original email form never hydrates saved credentials');
essentialCheck(!str_contains($html,'company_google_mail_oauth')&&!str_contains($html,'company_microsoft_mail_oauth')&&!str_contains($html,'/admin/company_context'),'native form contains no parallel provider-screen links');
essentialCheck(!str_contains($html,'</form>'),'include cannot prematurely close its native parent form');
essentialCheck(str_contains($html,'settings[smtp_host]')&&str_contains($html,'smtp_oauth_google/token')&&str_contains($html,'smtp_oauth_microsoft/token'),'original native fields and provider URLs retained under subpath');
essentialCheck(!str_contains($html,'<script>synthetic</script>')&&str_contains($html,'&lt;script&gt;synthetic'),'queue subject escaped');
essentialCheck(!str_contains($html,'id="sendmail"')&&!str_contains($html,'id="mail"'),'named company never offers server-global mail identity');
essentialCheck(str_contains($html,"form.method='POST'")&&str_contains($html,'native-mail-command'),'authorization, revoke and queue removal require explicit POST with context and CSRF');
echo "Native email original view and original helpers passed without global options, secret hydration, nested form closure or parallel URLs. Browser behavior remains pending.\n";
