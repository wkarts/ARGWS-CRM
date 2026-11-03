<?php
require __DIR__.'/native_registry_identity.php';
$start=$GLOBALS['essentialChecks'];
$profile=['company_trade_name'=>'Loja sintética','company_state_registration'=>'ISENTO','invoice_company_address'=>'Rua sintética','company_address_number'=>'42','company_address_complement'=>'Sala 2','company_address_district'=>'Centro','invoice_company_city'=>'Cidade teste','company_state'=>'SP','invoice_company_country_code'=>'BR','invoice_company_postal_code'=>'01001-000'];
$identity->save($new,7,['profile'=>$profile]);
foreach($profile as $key=>$value){essentialCheck($identity->get($new,$key)===$value,'new profile persisted '.$key);}
essentialCheck($identity->displayName($new)==='Loja sintética','trade name display');
essentialCheck($identity->get($new,'companyname')==='Wizard company','fiscal legal name unchanged');
essentialCheck(CompanySettingsIdentity::documentAddress(fn($key)=>$identity->get($new,$key))==='Rua sintética, 42, Sala 2, Centro','snapshot address projection');
$before=$identity->read($new);$db->failOn='INSERT INTO '.$p.'issuers_audit';
settingThrows(fn()=>$identity->save($new,7,['profile'=>['company_trade_name'=>'Rollback','company_address_number'=>'99']]),'issuer_storage_failed','new profile failure rolls back');$db->failOn=null;
essentialCheck($identity->read($new)===$before,'profile rollback exact');
settingThrows(fn()=>$identity->save($new,12,['profile'=>['company_trade_name'=>'Foreign']]),'company_settings_access_denied','tenant ACL unchanged');
$identity->save($new,7,['profile'=>['company_trade_name'=>'']]);essentialCheck($identity->displayName($new)==='Wizard company','empty fantasy falls back legal name');
$primary=(new IssuerResourceAccess($db,$p))->primaryId();$identity->save($primary,7,['profile'=>$profile]);
foreach($profile as $key=>$value){essentialCheck($identity->get($primary,$key)===$value,'primary option canonical '.$key);}
essentialCheck($identity->displayName($primary)==='Loja sintética','primary trade name display');
essentialCheck(CompanySettingsIdentity::documentAddress(fn($key)=>$key==='invoice_company_address'?'Legacy aggregate':'')==='Legacy aggregate','old address not reconstructed');
echo 'Company profile: '.($GLOBALS['essentialChecks']-$start).' new checks passed ('.$db->driver().").\n";
