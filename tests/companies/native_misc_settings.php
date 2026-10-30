<?php
require __DIR__.'/../native_company/staff_preferences.php';
require_once APPPATH.'services/NativeCompanySettingsForms.php';
if(!$db->table_exists($p.'user_meta'))$db->query('CREATE TABLE '.$p.'user_meta(umeta_id INTEGER PRIMARY KEY AUTOINCREMENT,staff_id INTEGER,meta_key TEXT,meta_value TEXT)');
if(!$db->table_exists($p.'roles'))$db->query('CREATE TABLE '.$p.'roles(roleid INTEGER PRIMARY KEY,name TEXT)');
unset($db->data_cache['table_names']);
$db->query('INSERT INTO '.$p."roles(roleid,name) VALUES(101,'Native principal role')");
foreach(NativeCompanyMiscSettings::defaults()+['default_staff_role'=>'101']as$key=>$value){$rows=$db->query('SELECT name FROM '.$p.'options WHERE name=?',[$key])->result_array();if($rows)$db->query('UPDATE '.$p.'options SET value=? WHERE name=?',[$value,$key]);else$db->query('INSERT INTO '.$p.'options(name,value) VALUES(?,?)',[$key,$value]);}
$primary=(new IssuerResourceAccess($db,$p))->primaryId();$forms=new NativeCompanySettingsForms($db,$p);$first=$forms->page(41,7,'misc');$other=$forms->page(82,7,'misc');$legacy=$forms->page($primary,7,'misc');
$start=$GLOBALS['essentialChecks'];
foreach(['issuer:41:customers-table-last-order','issuer:82:customers-table-last-order','customers-table-last-order','issuer:41:unrelated']as$key)$db->query('INSERT INTO '.$p.'user_meta(staff_id,meta_key,meta_value) VALUES(7,?,?)',[$key,'synthetic']);
$v=$first['values'];$v['save_last_order_for_tables']='1';$v['tables_pagination_limit']='50';$v['show_table_export_button']='hide';
$saved=$forms->save(41,7,'misc',$v,[],$first['revision']);essentialCheck($saved['values']===$v,'native misc controls saved exactly');
essentialCheck($forms->page(82,7,'misc')===$other&&$forms->page($primary,7,'misc')===$legacy,'A save preserves B and principal');
$keys=array_column($db->query('SELECT meta_key FROM '.$p.'user_meta ORDER BY meta_key')->result_array(),'meta_key');essentialCheck(!in_array('issuer:41:customers-table-last-order',$keys,true)&&in_array('issuer:82:customers-table-last-order',$keys,true)&&in_array('customers-table-last-order',$keys,true)&&in_array('issuer:41:unrelated',$keys,true),'native reset clears only exact issuer table-order namespace');
settingThrows(fn()=>$forms->save(41,7,'misc',$v,[],$first['revision']),'native_settings_stale','stale form rejected');
$bad=$v;$bad['default_staff_role']='101';settingThrows(fn()=>$forms->save(41,7,'misc',$bad,[],$saved['revision']),'native_staff_preferences_invalid','native principal role cannot become named role');
$bad=$v;$bad['show_table_export_button']='everyone';settingThrows(fn()=>$forms->save(41,7,'misc',$bad,[],$saved['revision']),'native_misc_settings_invalid','export enum exact');
$bad=$v;$bad['dropbox_app_key']="synthetic\nheader";settingThrows(fn()=>$forms->save(41,7,'misc',$bad,[],$saved['revision']),'native_misc_settings_invalid','control characters refused');
$before=$forms->page(41,7,'misc');$db->query("CREATE TRIGGER reject_misc_audit BEFORE INSERT ON {$p}issuers_audit WHEN NEW.event='company_settings_batch_updated' BEGIN SELECT RAISE(ABORT,'synthetic'); END");
$bad=$v;$bad['default_staff_role']='';$bad['tables_pagination_limit']='100';settingThrows(fn()=>$forms->save(41,7,'misc',$bad,[],$before['revision']),'issuer_storage_failed','audit failure rolls back role and settings');$db->query('DROP TRIGGER reject_misc_audit');essentialCheck($forms->page(41,7,'misc')===$before,'no partial settings or role write');
$pv=$legacy['values'];$pv['default_staff_role']='';$pv['save_last_order_for_tables']='1';$primarySaved=$forms->save($primary,7,'misc',$pv,[],$legacy['revision']);essentialCheck($primarySaved['values']===$pv&&$forms->page(41,7,'misc')===$saved,'principal native roles and persisted settings remain separate');
$keys=array_column($db->query('SELECT meta_key FROM '.$p.'user_meta')->result_array(),'meta_key');essentialCheck(!in_array('customers-table-last-order',$keys,true)&&in_array('issuer:82:customers-table-last-order',$keys,true),'principal reset preserves all named company order preferences');
settingThrows(fn()=>$forms->page(41,14,'misc'),'native_settings_denied','membership alone does not expose settings');
echo 'Native misc settings: '.($GLOBALS['essentialChecks']-$start).' actual SQLite exact form/role/namespace/reset/CAS/rollback checks passed.'.PHP_EOL;
if(!function_exists('_l')){function _l($key,...$args){return$key;}}
if(!function_exists('e')){function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}}
if(!function_exists('html_escape')){function html_escape($v){return is_array($v)?array_map('html_escape',$v):e($v);}}
if(!function_exists('config_item')){function config_item($key){return$key==='charset'?'UTF-8':null;}}
if(!function_exists('get_option')){function get_option(){throw new RuntimeException('Global option read in original misc form');}}
if(!function_exists('hooks')){function hooks(){throw new RuntimeException('Global hook in original misc form');}}
require_once APPPATH.'helpers/func_helper.php';require_once BASEPATH.'helpers/form_helper.php';require_once APPPATH.'helpers/fields_helper.php';
$GLOBALS['CI']->input=new class{public function post($key){return null;}};
$native_settings_misc=$saved;$roles=$saved['roles'];
set_error_handler(static function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
ob_start();try{require APPPATH.'views/admin/settings/includes/misc.php';$html=ob_get_clean();}catch(Throwable$e){ob_end_clean();throw$e;}finally{restore_error_handler();}
$dom=new DOMDocument();$dom->loadHTML($html,LIBXML_NOERROR|LIBXML_NOWARNING);$xpath=new DOMXPath($dom);
foreach(NativeCompanyMiscSettings::fields()as$key)essentialCheck($xpath->query('//*[@name="settings['.$key.']"]')->length>0,'original misc field preserved '.$key);
essentialCheck($xpath->query('//*[@id="misc" or @id="settings_tables" or @id="inline_create"]')->length===3,'all three original tabs retained');
echo "Original Settings/misc partial rendered all twenty controls through real field helpers, no global options/hooks.\n";
