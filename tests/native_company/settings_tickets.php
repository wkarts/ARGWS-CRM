<?php
require __DIR__.'/ticket_preferences.php';
require_once __DIR__.'/../../application/services/NativeCompanySettingsForms.php';
$db->query('UPDATE tblissuers_installation_identity SET master_staff_id=17');$db->query('UPDATE tblissuers_staff SET can_view=1 WHERE issuer_id=2 AND staff_id=17');
$forms=new NativeCompanySettingsForms($db,'tbl');$before=$forms->page(2,17,'tickets');$other=$prefs->read(3,18);
$changed=$forms->save(2,17,'tickets',['services'=>'1','ticket_replies_order'=>'asc'],[],$before['revision']);
kc($changed['values']['services']==='1'&&$changed['values']['ticket_replies_order']==='asc','existing Settings form and canonical ticket order saved together');
kc($changed['values']['ticket_import_reply_only']===$before['values']['ticket_import_reply_only'],'fields not submitted by original form preserved');
kc($prefs->read(3,18)===$other,'company B configuration unchanged');
$deny(fn()=>$forms->save(2,17,'tickets',['services'=>'0'],[],$before['revision']));
$deny(fn()=>$forms->save(2,17,'tickets',['default_ticket_reply_status'=>$other['values']['default_ticket_reply_status']],[],$changed['revision']));
$beforeJson=$db->query('SELECT settings_json FROM tblissuers_registry WHERE id=2')->row_array();
$db->query("CREATE TRIGGER settings_ticket_audit_fail BEFORE INSERT ON tblissuers_audit BEGIN SELECT RAISE(FAIL,'synthetic failure'); END");
try{$forms->save(2,17,'tickets',['services'=>'0','ticket_replies_order'=>'desc'],[],$changed['revision']);throw new LogicException('audit failure accepted');}catch(RuntimeException $e){kc($e->getMessage()==='issuer_storage_failed','audit failure fails entire settings transaction');}
$db->query('DROP TRIGGER settings_ticket_audit_fail');kc($db->query('SELECT settings_json FROM tblissuers_registry WHERE id=2')->row_array()===$beforeJson,'settings option and order rollback together');
echo "Native ticket Settings composition passed; same form catalogs/public form and pipe HTTP remain separate gates.\n";
function _l($key,...$args){return$key;}
function get_option($key){throw new RuntimeException('global ticket option lookup forbidden');}
function html_escape($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function set_value($name,$value=''){return html_escape($value);}
function e($value){return html_escape($value);}
function site_url($path=''){return 'https://crm.example.test/sub/'.$path;}
function hooks(){throw new RuntimeException('global ticket hooks forbidden');}
if(!defined('BASEPATH'))define('BASEPATH',__DIR__);
if(!defined('FCPATH'))define('FCPATH','/synthetic/');
if(!defined('VIEWPATH'))define('VIEWPATH',__DIR__.'/../../application/views/');
require_once __DIR__.'/../../application/helpers/fields_helper.php';
$native_settings_ticket_values=$changed['values'];$native_settings_issuer=2;
$native_settings_ticket_statuses=$catalog->list(2,17,'status');$ticket_priorities=$catalog->list(2,17,'priority');
ob_start();require __DIR__.'/../../application/views/admin/settings/includes/tickets.php';$html=ob_get_clean();
kc(str_contains($html,'settings[default_ticket_reply_status]')&&str_contains($html,$changed['values']['default_ticket_reply_status']),'native form renders actual owned catalog identifier');
kc(str_contains($html,'forms/ticket?issuer_id=2')&&str_contains($html,'forms/ticket?styled=1&issuer_id=2'),'native public form links carry explicit issuer rather than principal fallback');
