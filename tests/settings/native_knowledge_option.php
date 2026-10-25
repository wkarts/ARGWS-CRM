<?php
require __DIR__.'/company_settings.php';
require_once APPPATH.'services/CompanySettingsCatalog.php';
essentialCheck(CompanySettingsCatalog::fields('knowledge_base')===['use_knowledge_base','knowledge_base_without_registration'],'two distinct native publication options preserved');
essentialCheck(CompanySettingsCatalog::defaultValue('knowledge_base','use_knowledge_base')==='0','new company publication default disabled');
essentialCheck(CompanySettingsCatalog::defaultValue('knowledge_base','knowledge_base_without_registration')==='0','anonymous publication disabled separately by default');
foreach(['1','0']as$value)essentialCheck(CompanySettingsCatalog::prepare('knowledge_base',['use_knowledge_base'=>$value])===['use_knowledge_base'=>$value],'native boolean representation preserved');
foreach([['use_knowledge_base'=>true],['use_knowledge_base'=>'true'],['knowledge_base_without_registration'=>true],['knowledge_base_without_registration'=>'true'],['smtp_password'=>'x']]as$input)settingThrows(fn()=>CompanySettingsCatalog::prepare('knowledge_base',$input),'company_settings_invalid_input','invalid publication configuration rejected');
echo "Native knowledge publication option contract passed.\n";
essentialCheck(CompanySettingsCatalog::defaultValue('table_preferences','tables_pagination_limit')==='25','native table default preserved');
foreach(['1','25','500',(string)PHP_INT_MAX]as$value)essentialCheck(CompanySettingsCatalog::prepare('table_preferences',['tables_pagination_limit'=>$value])===['tables_pagination_limit'=>$value],'configured native table lengths not silently capped');
foreach(['0','-1','1.2','001',((string)PHP_INT_MAX).'0']as$value)settingThrows(fn()=>CompanySettingsCatalog::prepare('table_preferences',['tables_pagination_limit'=>$value]),'company_settings_invalid_input','invalid or overflowing default length rejected');
