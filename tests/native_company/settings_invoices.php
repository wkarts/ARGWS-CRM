<?php
require __DIR__.'/primary_catalog.php';
require_once APPPATH.'services/NativeCompanySettingsForms.php';
$forms=new NativeCompanySettingsForms($db,$p);$store=new CompanySettingsStorage($db,$p);$sequence=new NativeCompanyInvoiceSequencePreferences($db,$p);
$before=$forms->page(41,7,'invoices');$values=$before['values'];
essentialCheck($values['invoice_prefix']==='INV-'&&$values['next_invoice_number']==='1'&&$values['invoice_number_format']==='1','native public defaults without principal-option inheritance');
$principalOptions=$db->query('SELECT * FROM '.$p.'options ORDER BY name')->result_array();
$values['invoice_prefix']='B-';$values['next_invoice_number']='24';$values['invoice_number_format']='4';$values['predefined_clientnote_invoice']='Specific note';
$after=$forms->save(41,7,'invoices',$values,[],$before['revision']);
essentialCheck($after['values']===$values&&$sequence->read(41,7)['next_number']==='24','native form updates own options and real allocator in one transaction');
essentialCheck(!$store->exists(41,'next_invoice_number'),'no fake duplicated option counter is persisted');
essentialCheck($db->query('SELECT * FROM '.$p.'options ORDER BY name')->result_array()===$principalOptions,'native principal options unchanged');
settingThrows(fn()=>$forms->save(41,7,'invoices',$values,[],$before['revision']),'native_settings_stale','whole-form stale guard');
$changed=$values;$changed['next_invoice_number']='25';$changed['invoice_prefix']='C-';
$db->query("CREATE TRIGGER native_invoice_preferences_audit_fail BEFORE INSERT ON {$p}issuers_audit WHEN NEW.event='company_settings_batch_updated' BEGIN SELECT RAISE(IGNORE); END");
settingThrows(fn()=>$forms->save(41,7,'invoices',$changed,[],$after['revision']),'company_settings_write_unconfirmed','late settings audit must be confirmed');
$db->query('DROP TRIGGER native_invoice_preferences_audit_fail');
essentialCheck($forms->page(41,7,'invoices')===$after,'late option-audit failure rolls back sequence and all values');
$bad=$values;unset($bad['show_project_on_invoice']);settingThrows(fn()=>$forms->save(41,7,'invoices',$bad,[],$after['revision']),'native_settings_invalid','incomplete native form refused');
$bad=$values;$bad['invoice_number_format']='5';settingThrows(fn()=>$forms->save(41,7,'invoices',$bad,[],$after['revision']),'native_settings_invalid','unknown native format refused');
$bad=$values;$bad['next_invoice_number']='2147483648';settingThrows(fn()=>$forms->save(41,7,'invoices',$bad,[],$after['revision']),'native_invoice_sequence_unemittable','counter range validated by actual consumer contract');
echo "Native invoice Settings: original field wire, scoped defaults, atomic sequence/options, stale/invalid/late-failure proofs passed. Runtime consumers and browser remain separate acceptance gates.\n";
// Render the original include and original field helpers; a global option lookup is an error.
function _l($key,...$args){return$key;}
function get_option($key){throw new RuntimeException('global invoice option lookup forbidden');}
function html_escape($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function set_value($name,$value=''){return html_escape($value);}
require_once APPPATH.'helpers/template_helper.php';
require_once APPPATH.'helpers/fields_helper.php';
$native_settings_invoice_values=$after['values'];$native_settings_invoice_values['invoice_prefix']='\"<script>alert(1)</script>';
ob_start();require APPPATH.'views/admin/settings/includes/invoices.php';$html=ob_get_clean();
essentialCheck(str_contains($html,'settings[next_invoice_number]')&&str_contains($html,'value="24"'),'same native input uses real selected allocator');
essentialCheck(!str_contains($html,'<script>alert(1)</script>')&&str_contains($html,'&lt;script&gt;'),'prefix escaped by original invoice form');
essentialCheck(str_contains($html,'name="settings[show_project_on_invoice]"')&&str_contains($html,'checked'),'same native boolean helpers use explicit scoped values without global lookup');
