<?php
declare(strict_types=1);
define('APPPATH',dirname(__DIR__,2).'/application/');
require APPPATH.'services/NativeCompanyClientsSettings.php';
$n=0;function check($ok,$label){global$n;if(!$ok)throw new RuntimeException($label);++$n;}function denied($fn){try{$fn();}catch(DomainException){check(true,'rejected');return;}throw new RuntimeException('invalid input accepted');}
$policy=NativeCompanyClientsSettings::DEFAULTS;NativeCompanyClientsSettings::validate($policy);
check(count($policy)===18,'all native product controls enumerated');
$seed=file_get_contents(dirname(__DIR__,2).'/install/database.sql');
foreach($policy as$k=>$v){if(in_array($k,['required_register_fields','use_knowledge_base','knowledge_base_without_registration'],true))continue;preg_match("/\\([0-9]+, '".preg_quote($k,'/')."', '([^']*)', [01]\\)/",$seed,$m);check(isset($m[1])&&stripslashes(str_replace(['\\r','\\n'],["\r","\n"],$m[1]))===$v,'literal public default '.$k);}
$input=$policy;$input['customer_settings']='true';foreach(['visible_customer_profile_tabs','required_register_fields','default_contact_permissions']as$k)$input[$k]=[];
$input['visible_customer_profile_tabs']=['notes','attachments'];$input['default_contact_permissions']=['1','6'];$input['required_register_fields']=['company_country'];
$out=NativeCompanyClientsSettings::input($input);$tabs=NativeCompanyClientsSettings::visibleTabs($out['visible_customer_profile_tabs']);
check($tabs['profile']&&$tabs['contacts']&&$tabs['notes']&&$tabs['attachments']&&!$tabs['map'],'native visible map retains mandatory tabs');
check(NativeCompanyContactPreferences::permissions($out['default_contact_permissions'])===[1,6]&&json_decode($out['required_register_fields'],true)===['company_country'],'explicit form arrays retain native wire formats');
foreach(['O:8:"stdClass":0:{}','a:1:{s:3:"map";s:1:"1";}','a:1:{s:4:"evil";b:1;}','a:2:{s:3:"map";b:1;}','a:1:{s:9:"map";b:1;}','a:2:{s:3:"map";b:1;s:3:"map";b:0;}']as$v)denied(fn()=>NativeCompanyClientsSettings::visibleTabs($v));
foreach(['allow_registration'=>'yes','clients_default_theme'=>'../../evil','show_estimate_request_in_customers_area'=>'1e3','customer_info_format'=>'{password}']as$k=>$v)denied(fn()=>NativeCompanyClientsSettings::input(array_replace($input,[$k=>$v])));
denied(fn()=>NativeCompanyClientsSettings::input(array_replace($input,['required_register_fields'=>['company_country','company_country']])));
$clean=NativeCompanyClientsSettings::format('<span style="color:red">{company_name}</span><img src="https://invalid.test/x"><script>x()</script>');check(str_contains($clean,'{company_name}')&&!str_contains($clean,'<img')&&!str_contains($clean,'<script'),'formatted customer identity never fetches remote resources or executes code');
check($policy['use_knowledge_base']==='0'&&$policy['knowledge_base_without_registration']==='0','new companies preserve explicit KB publication policy, not historical seed activation');
echo "Native Clients settings policy: $n checks passed.\n";
