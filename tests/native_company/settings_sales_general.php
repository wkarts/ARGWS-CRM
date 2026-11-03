<?php
require __DIR__.'/primary_catalog.php';
require_once APPPATH.'services/NativeCompanySettingsForms.php';
$forms=new NativeCompanySettingsForms($db,$p);$store=new CompanySettingsStorage($db,$p);
$values=['decimal_separator'=>',','thousand_separator'=>'.','number_padding_prefixes'=>'6','automatically_set_logged_in_staff_sales_agent'=>'1','show_tax_per_item'=>'1','remove_tax_name_from_item_table'=>'0','items_table_amounts_exclude_currency_symbol'=>'0','default_tax'=>['Secondary tax|6'],'remove_decimals_on_zero'=>'0','total_to_words_enabled'=>'1','total_to_words_lowercase'=>'0'];
$before=$forms->page(41,7,'sales_general');$primary=$forms->page($principal,7,'sales_general');
$after=$forms->save(41,7,'sales_general',$values,[],$before['revision']);
essentialCheck($after['values']['default_tax']===serialize(['Secondary tax|6.0000']),'native tax selection canonicalized in own settings');
essentialCheck($forms->page($principal,7,'sales_general')===$primary,'principal finance preferences unchanged');
settingThrows(fn()=>$forms->save(41,7,'sales_general',$values,[],$before['revision']),'native_settings_stale','stale financial preferences denied');
$bad=$values;$bad['default_tax']=['Historical tax|5'];
settingThrows(fn()=>$forms->save(41,7,'sales_general',$bad,[],$after['revision']),'company_catalog_invalid_tax_selection','other issuer taxes rejected before persistence');
$bad=$values;$bad['thousand_separator']=',';
settingThrows(fn()=>$forms->save(41,7,'sales_general',$bad,[],$after['revision']),'native_settings_invalid','ambiguous number format denied');
essentialCheck($forms->page(41,7,'sales_general')===$after,'invalid preference forms preserve all prior values');
$empty=$values;unset($empty['default_tax']);$after=$forms->save(41,7,'sales_general',$empty,[],$after['revision']);
essentialCheck($after['values']['default_tax']===serialize([]),'native unselected multiple select explicitly clears defaults');
$release=CompanySettingsRuntime::enterRequest($db,$p,41,7);
essentialCheck(CompanySettingsRuntime::get('decimal_separator')===','&&CompanySettingsRuntime::get('number_padding_prefixes')==='6','existing scoped option consumers observe own persisted values');$release();
echo "Native sales general settings: own catalogue, native option consumers, empty selection, CAS and A/B preservation passed.\n";
// Execute the original include with a scoped option reader and no global Misc model.
if(!function_exists('_l')){function _l($key){return$key;}}
if(!function_exists('get_option')){function get_option($key){return CompanySettingsRuntime::get($key);}}
if(!function_exists('render_yes_no_option')){function render_yes_no_option($key,...$rest){echo '<input name="settings['.htmlspecialchars($key,ENT_QUOTES).']" value="'.htmlspecialchars(get_option($key),ENT_QUOTES).'">';}}
if(!function_exists('render_input')){function render_input($name,$label,$value,...$rest){return '<input name="'.htmlspecialchars($name,ENT_QUOTES).'" value="'.htmlspecialchars($value,ENT_QUOTES).'">';}}
$native_settings=true;$native_settings_tax_dropdown=$namedCatalog->taxDropdown('settings[default_tax][]',[]);
$release=CompanySettingsRuntime::enterRequest($db,$p,41,7);
try{ob_start();require APPPATH.'views/admin/settings/includes/sales_general.php';$html=ob_get_clean();}finally{$release();}
essentialCheck(str_contains($html,'name="settings[decimal_separator]"')&&str_contains($html,'Secondary tax')&&!str_contains($html,'Historical tax'),'actual native sales form renders own tax catalogue and unchanged field names without a global model');
