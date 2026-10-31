<?php
require __DIR__.'/native_clients.php';require APPPATH.'services/NativeCompanyClientsSettings.php';
$start=$GLOBALS['essentialChecks'];$aAdapter=new NativeCompanyClients($db,$p,41,7);$bAdapter=new NativeCompanyClients($db,$p,82,7);
$tabs=['profile'=>['name'=>'Profile'],'contacts'=>['name'=>'Contacts'],'notes'=>['name'=>'Notes'],'attachments'=>['name'=>'Files'],'map'=>['name'=>'Map']];
$store=new CompanySettingsStorage($db,$p);$map=serialize(['profile'=>false,'contacts'=>false,'notes'=>true,'attachments'=>true,'map'=>false]);$store->mutate(41,7,'update','visible_customer_profile_tabs',$map,null);
essentialCheck(array_keys($aAdapter->filterProfileTabs($nativeA,$tabs))===['profile','contacts','notes','attachments'],'same native profile filters selected tabs but mandatory identity/contact tabs remain');
essentialCheck($bAdapter->filterProfileTabs($nativeB,$tabs)===$tabs,'B absent option uses all tabs without inheriting A');
essentialThrows(fn()=>$aAdapter->filterProfileTabs($nativeB,$tabs),'company_client_access_denied','setting does not authorize foreign customer');
essentialThrows(fn()=>$aAdapter->filterProfileTabs($nativeA,['unknown'=>[]]),'native_clients_settings_invalid','unregistered tab cannot be injected by configuration');
$store->mutate(41,7,'update','visible_customer_profile_tabs','O:8:"stdClass":0:{}',null);essentialThrows(fn()=>$aAdapter->filterProfileTabs($nativeA,$tabs),'native_clients_settings_invalid','malformed stored visibility fails safely without object deserialization');
$store->mutate(41,7,'update','customer_default_country','32',null);essentialCheck($aAdapter->references($nativeA)['native_customer_default_country']==='32'&&$bAdapter->references($nativeB)['native_customer_default_country']==='','new native customer form uses issuer country, not hardcoded zero or another issuer');
$store->mutate(41,7,'update','customer_default_country','999999',null);essentialThrows(fn()=>$aAdapter->references($nativeA),'native_clients_settings_invalid','nonexistent configured country is not silently rendered');
echo 'Native customer profile visibility: '.($GLOBALS['essentialChecks']-$start).' checks passed.'.PHP_EOL;
