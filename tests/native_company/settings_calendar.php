<?php
require __DIR__.'/calendar_preferences.php';
require_once APPPATH.'services/NativeCompanySettingsForms.php';
$forms=new NativeCompanySettingsForms($db,$p);
$before=$forms->page($a,49,'calendar');$beforeB=$forms->page($b,49,'calendar');
$values=$before['values'];$values['calendar_first_day']='5';
$after=$forms->save($a,49,'calendar',$values,[],$before['revision']);
essentialCheck($after['values']===$values&&$forms->page($b,49,'calendar')===$beforeB,'same Settings adapter commits all calendar fields only to selected company');
essentialThrows(fn()=>$forms->save($a,49,'calendar',$values,[],$before['revision']),'native_calendar_preferences_stale','Settings calendar rejects stale revision');
essentialThrows(fn()=>$forms->save($a,49,'calendar',$values,['injected'=>'x'],$after['revision']),'native_settings_invalid','Settings calendar rejects unrelated custom fields');
// Render the original include with a scoped DTO; global options are forbidden.
function get_option($key){throw new RuntimeException('global calendar option read: '.$key);}
function _l($key,...$args){return $key;}
function e($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function get_weekdays(){return ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];}
function render_input($name,$label,$value,$type='text'){return '<input name="'.e($name).'" value="'.e($value).'">';}
function render_color_picker($name,$label,$value){return render_input($name,$label,$value);}
function render_yes_no_option($name,$label,$tooltip='',$yes='',$no='',$one='',$zero='',$explicit=null){if(!is_string($explicit))throw new RuntimeException('missing explicit calendar value');echo render_input('settings['.$name.']',$label,$explicit);}
$native_settings_calendar_values=$after['values'];
set_error_handler(static function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
try{ob_start();require APPPATH.'views/admin/settings/includes/calendar.php';$html=ob_get_clean();}finally{restore_error_handler();}
preg_match_all('/name="settings\[([^\]]+)\]"/',$html,$matches);$actual=$matches[1];sort($actual);$expected=NativeCompanySettingsForms::fields('calendar');sort($expected);
essentialCheck($actual===$expected,'original native calendar markup contains all and only the same 26 fields');
essentialCheck(str_contains($html,'nav-tabs-horizontal')&&!str_contains($html,'company_context'),'original tabs preserved without a parallel route');
echo "Native Settings/calendar compositor and original include: 5 checks PASS; no browser or MySQL claim.\n";
