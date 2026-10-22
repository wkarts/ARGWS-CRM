<?php
/** Actual155 policy/service/owned transactions with SQLite or explicit disposable CI MySQL; transport simulated. */
require __DIR__.'/asaas_positive.php';
require_once APPPATH.'services/CompanySettingsRuntime.php';
$preferencesStart=$GLOBALS['essentialChecks'];$policy=new \Asaas\CompanyPreferences($db,$p);
$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.5.5','asaas']);
\Asaas\CompanyPreferencesSchema::ensure($db,$p);\Asaas\CompanyCheckoutSchema::ensure($db,$p);unset($db->data_cache['table_names']);
$accountsBefore=$db->query('SELECT * FROM '.$p.'asaas_company_accounts ORDER BY id')->result_array();
$originsBefore=$db->query('SELECT * FROM '.$p.'asaas_payment_origins ORDER BY id')->result_array();
// Clear only synthetic selection. GET does not seed either profile or choose an environment.
$db->query('DELETE FROM '.$p.'asaas_company_preferences WHERE issuer_id=?',[$a]);
$page=$policy->page($a,7);essentialCheck($page['ready']&&$page['active_environment']===null&&$page['revision']===\Asaas\CompanyPreferences::ZERO,'155 starts without an inferred active environment');
essentialCheck($page['profiles']['production']===$page['profiles']['sandbox']&&$page['profiles']['production']['description']==='Fatura {invoice_number}','unsaved independent defaults use current nominal contract, not legacy options');
essentialCheck($db->query('SELECT * FROM '.$p.'asaas_company_profiles')->result_array()===[],'GET does not seed profile rows');
$newInvoice=fn($issuer,$tag)=>$s->create($issuer,7,array_replace($input,['client_id'=>$issuer===$a?$ca:$customerB]),hash('sha256','preferences-invoice-'.$tag));
$newIssue=function($issuer,$account,$method,$tag,$transport=null,$expected=null)use($newInvoice,$asaas,$s,$dec,$mock,$a){$invoice=$newInvoice($issuer,$tag);$key=hash('sha256','preferences-request-'.$tag);$id=$asaas->issue($issuer,7,$invoice,$account,$issuer===$a?'cus_a':'cus_b',$method,$s->revision($issuer,7,$invoice),$key,$dec,$transport??$mock,$expected);return [$invoice,$id,$key];};
$invoice=$newInvoice($a,'no-selection');$before=count($calls);
essentialThrows(fn()=>$asaas->issue($a,7,$invoice,$accounts[$a],'cus_a','PIX',$s->revision($a,7,$invoice),hash('sha256','no-selection'),$dec,$mock),'asaas_company_active_environment_required','new issuance requires explicit selection');
essentialCheck(count($calls)===$before,'no-selection denial precedes any provider access');
$config=$asaas->accounts($a,7)[0];$policy->selectEnvironment($a,7,'sandbox',$page['revision'],$config['revision']);
$sandbox=array_replace(\Asaas\CompanyPreferences::DEFAULTS,['label'=>'Conta A <literal>','description'=>'Pedido {invoice_number} · A','allow_boleto'=>'0']);
$production=array_replace(\Asaas\CompanyPreferences::DEFAULTS,['label'=>'Produção A','description'=>'Produção {invoice_number}','allow_pix'=>'0','allow_card'=>'0']);
$policy->saveProfile($a,7,'sandbox',array_reverse($sandbox,true),$page['profiles']['sandbox']['revision'],$config['revision']);
$policy->saveProfile($a,7,'production',$production,$page['profiles']['production']['revision'],\Asaas\CompanyPreferences::ZERO);
$page=$policy->page($a,7);essentialCheck(array_intersect_key($page['profiles']['sandbox'],$sandbox)===$sandbox&&array_intersect_key($page['profiles']['production'],$production)===$production,'profile persistence canonicalizes input order and separates both environments');
essentialCheck($policy->page($b,7)['profiles']['sandbox']['label']==='Asaas','company B never inherits company A preferences');
essentialCheck($db->query('SELECT * FROM '.$p.'asaas_company_accounts ORDER BY id')->result_array()===$accountsBefore&&$db->query('SELECT * FROM '.$p.'asaas_payment_origins ORDER BY id')->result_array()===$originsBefore,'profile/selection edits never change accounts, credentials, automation or old origins');
$before=count($calls);essentialThrows(fn()=>$newIssue($a,$accounts[$a],'BOLETO','disabled'),'asaas_company_method_disabled','disabled method is denied by service, not only hidden in UI');essentialCheck(count($calls)===$before,'method denial precedes provider calls');
$expected=['selection_revision'=>$page['revision'],'profile_revision'=>$page['profiles']['sandbox']['revision'],'account_revision'=>$config['revision']];
[$ownedInvoice,$ownedOrigin,$ownedKey]=$newIssue($a,$accounts[$a],'PIX','snapshot',null,$expected);
$stored=$db->query('SELECT * FROM '.$p.'asaas_company_origin_profiles WHERE origin_id=?',[$ownedOrigin])->row_array();$frozen=json_decode($stored['snapshot_json'],true,16,JSON_THROW_ON_ERROR);
$remote=$db->query('SELECT remote_id FROM '.$p.'asaas_payment_origins WHERE id=?',[$ownedOrigin])->row()->remote_id;
essentialCheck($responses[$remote]['description']===$frozen['description']&&str_starts_with($frozen['description'],'Pedido ')&&$frozen['label']==='Conta A <literal>','outgoing description/label/revisions are frozen before the single remote POST');
essentialCheck($frozen['environment']==='sandbox'&&$frozen['account_row']===$accounts[$a]&&$frozen['allowed_methods']===['PIX','CREDIT_CARD'],'snapshot binds exact company/account/environment and allowed methods');
essentialCheck($responses[$remote]['fine']['value']===0&&$responses[$remote]['interest']['value']===0&&$responses[$remote]['discount']['value']===0&&$responses[$remote]['dueDate']===$db->query('SELECT duedate FROM '.$p.'invoices WHERE id=?',[$ownedInvoice])->row()->duedate,'profile cannot simulate financial charges, discounts or due-date changes');
$changed=array_replace($sandbox,['description'=>'Novo {invoice_number}','allow_pix'=>'0']);$policy->saveProfile($a,7,'sandbox',$changed,$page['profiles']['sandbox']['revision'],$config['revision']);
$before=count($calls);essentialCheck($asaas->issue($a,7,$ownedInvoice,$accounts[$a],'cus_a','PIX',$s->revision($a,7,$ownedInvoice),$ownedKey,$dec,$mock,$expected)===$ownedOrigin&&count($calls)===$before,'existing origin retry ignores later policy restrictions and makes no provider call');
$asaas->query($a,7,$ownedOrigin,$dec,$mock);essentialCheck($db->query('SELECT * FROM '.$p.'asaas_company_origin_profiles WHERE origin_id=?',[$ownedOrigin])->row_array()===$stored,'query preserves immutable profile after subsequent edits');
$before=count($calls);essentialThrows(fn()=>$newIssue($a,$accounts[$a],'PIX','now-disabled'),'asaas_company_method_disabled','new invoice cannot reuse an old enabled-method snapshot');essentialCheck(count($calls)===$before,'disabled new invoice has no remote request');
$current=$policy->page($a,7);$policy->saveProfile($a,7,'sandbox',$sandbox,$current['profiles']['sandbox']['revision'],$config['revision']);
$before=count($calls);essentialThrows(fn()=>$newIssue($a,$accounts[$a],'PIX','stale-ui',null,$expected),'asaas_company_preferences_stale','stale rendered profile revision is rejected before provider access');essentialCheck(count($calls)===$before,'stale UI makes zero provider calls');
// Real ACL and active-context negative cases, not a global-admin shortcut.
foreach([41,42] as $staff)$db->query('INSERT INTO '.$p.'staff(staffid,active,admin) VALUES(?,1,1)',[$staff]);
$db->query('INSERT INTO '.$p.'issuers_staff(issuer_id,staff_id,can_view) VALUES(?,41,1)',[$a]);
foreach(['view','edit'] as $cap)$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,41,?,?)',[$a,'settings',$cap]);
$current=$policy->page($a,41);$policy->saveProfile($a,41,'production',$production,$current['profiles']['production']['revision'],\Asaas\CompanyPreferences::ZERO);
essentialCheck($policy->page($a,41)['profiles']['production']['label']==='Produção A','scoped non-master admin can configure the active company');
essentialThrows(fn()=>$policy->page($b,41),'asaas_company_access_denied','membership in A never grants B profiles');
essentialThrows(fn()=>$policy->page($a,42),'asaas_company_access_denied','global admin without membership cannot read profiles');
essentialThrows(fn()=>CompanySettingsRuntime::run($db,$p,$a,7,fn()=>$policy->page($b,7)),'asaas_company_access_denied','ambient context mismatch is denied even for master');
essentialCheck(\Asaas\CompanyPreferences::validate(array_replace($sandbox,['label'=>str_repeat('á',191),'description'=>str_repeat('ç',500)]))['label']===str_repeat('á',191),'UTF-8 bounds count characters rather than bytes');
$beforeProfiles=$db->query('SELECT * FROM '.$p.'asaas_company_profiles ORDER BY issuer_id,environment')->result_array();
foreach([['allow_pix'=>'yes'],['description'=>'Unknown {customer_email}'],['label'=>''],['label'=>str_repeat('á',192)],['label'=>"bad\xFF"],['description'=>"bad\nline"],['description'=>str_repeat('x',501)],['interest_value'=>'1']] as $bad){$current=$policy->page($a,7);essentialThrows(fn()=>$policy->saveProfile($a,7,'sandbox',array_replace($sandbox,$bad),$current['profiles']['sandbox']['revision'],$config['revision']),'asaas_company_profile_invalid','unsupported or invalid preference is rejected');}
essentialThrows(fn()=>$policy->saveProfile($a,7,'sandbox',$sandbox,\Asaas\CompanyPreferences::ZERO,$config['revision']),'asaas_company_preferences_stale','stale profile revision fails');
essentialThrows(fn()=>$policy->saveProfile($a,7,'sandbox',$sandbox,$policy->page($a,7)['profiles']['sandbox']['revision'],str_repeat('f',64)),'asaas_company_preferences_stale','stale account revision fails');
essentialThrows(fn()=>$policy->selectEnvironment($a,7,'production',$policy->page($a,7)['revision'],\Asaas\CompanyPreferences::ZERO),'asaas_company_account_unverified','cannot select missing production account');
essentialCheck($db->query('SELECT * FROM '.$p.'asaas_company_profiles ORDER BY issuer_id,environment')->result_array()===$beforeProfiles,'rejected writes preserve all profile bytes');
$db->failOn='INSERT INTO '.$p.'issuers_audit';$current=$policy->page($a,7);
essentialThrows(fn()=>$policy->saveProfile($a,7,'sandbox',$changed,$current['profiles']['sandbox']['revision'],$config['revision']),'issuer_storage_failed','audit failure rolls profile update back');$db->failOn=null;
essentialCheck($policy->page($a,7)===$current,'failed transaction preserves profile/revision/selection');
// Migration structural presence is never authority for a new emission.
foreach(['1.5.4','1.5.50','1.5.5-preview'] as $version){$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',[$version,'asaas']);essentialCheck(!$policy->ready(),'new capability rejects old/malformed marker');}
$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.5.4','asaas']);$invoice=$newInvoice($a,'old-marker');$before=count($calls);
essentialThrows(fn()=>$asaas->issue($a,7,$invoice,$accounts[$a],'cus_a','PIX',$s->revision($a,7,$invoice),hash('sha256','old-marker'),$dec,$mock),'asaas_module_155_required','all new service entrypoints require native155 tracking');
essentialCheck(count($calls)===$before,'tracking refusal precedes HTTP');
essentialCheck($asaas->issue($a,7,$ownedInvoice,$accounts[$a],'cus_a','PIX',$s->revision($a,7,$ownedInvoice),$ownedKey,$dec,$mock)===$ownedOrigin,'replay remains available after code rollback marker');$asaas->query($a,7,$ownedOrigin,$dec,$mock);
$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.5.5','asaas']);
// Account/environment matrix uses repeated wallet IDs across environments, never across companies.
$productionAccounts=[];$productionResponses=[];
$both=function($environment,$method,$path,$payload,$secret)use($mock,&$productionResponses){if($environment==='sandbox')return $mock($environment,$method,$path,$payload,$secret);if($method==='POST'){$id='pay_prod_'.count($productionResponses);return $productionResponses[$id]=$payload+['id'=>$id,'status'=>'PENDING','deleted'=>false];}if(str_starts_with($path,'payments/'))return $productionResponses[substr($path,9)];return $mock($environment,$method,$path,$payload,$secret);};
foreach([$a=>'A',$b=>'B'] as $issuer=>$letter){$id=$asaas->saveAccount($issuer,7,'production','wallet-'.$letter,'key-'.$letter.'-production',\Asaas\CompanyPreferences::ZERO,$enc);$productionAccounts[$issuer]=$id;$acc=array_values(array_filter($asaas->accounts($issuer,7),fn($row)=>$row['environment']==='production'))[0];$asaas->verifyAccount($issuer,7,$id,$acc['revision'],$dec,$both);$state=$policy->page($issuer,7);$policy->selectEnvironment($issuer,7,'production',$state['revision'],$acc['revision']);[$inv,$origin]=$newIssue($issuer,$id,'BOLETO','production-'.$letter,$both);$o=$db->query('SELECT * FROM '.$p.'asaas_payment_origins WHERE id=?',[$origin])->row_array();essentialCheck((int)$o['issuer_id']===$issuer&&$o['environment']==='production'&&$o['account_id']==='wallet-'.$letter,'A/B production issuance binds its own account/environment');}
essentialCheck($policy->page($a,7)['profiles']['sandbox']['description']===$sandbox['description']&&$policy->page($b,7)['profiles']['sandbox']['description']==='Fatura {invoice_number}','switching active environment preserves independent A/B sandbox values');
$before=count($calls);essentialThrows(fn()=>$newIssue($a,$accounts[$a],'PIX','inactive-sandbox'),'asaas_company_active_environment_required','inactive account environment cannot issue even when enabled/verified');essentialCheck(count($calls)===$before,'inactive environment blocked before HTTP');
$asaas->query($a,7,$ownedOrigin,$dec,$mock);essentialCheck($db->query('SELECT snapshot_json FROM '.$p.'asaas_company_origin_profiles WHERE origin_id=?',[$ownedOrigin])->row()->snapshot_json===$stored['snapshot_json'],'old sandbox origin remains queryable while production is active');
// Preference race between canonical wallet GET and local reservation: no POST escapes.
$current=$policy->page($a,7);$policy->selectEnvironment($a,7,'sandbox',$current['revision'],$config['revision']);$racePosts=0;$once=false;
$race=function($environment,$method,$path,$payload,$secret)use($mock,$policy,$a,$config,&$once,&$racePosts,$changed){if($method==='POST')$racePosts++;if(!$once&&$path==='wallets/'){$once=true;$page=$policy->page($a,7);$policy->saveProfile($a,7,'sandbox',$changed,$page['profiles']['sandbox']['revision'],$config['revision']);}return $mock($environment,$method,$path,$payload,$secret);};
essentialThrows(fn()=>$newIssue($a,$accounts[$a],'PIX','profile-race',$race),'asaas_company_method_disabled','policy is rechecked under reservation lock after provider GET');essentialCheck($racePosts===0,'concurrent profile change prevents payment POST');
// Foreign/tampered snapshot never becomes valid evidence through current preferences.
$db->query('UPDATE '.$p.'asaas_company_origin_profiles SET snapshot_hash=? WHERE origin_id=?',[str_repeat('0',64),$ownedOrigin]);$before=count($calls);
essentialThrows(fn()=>$asaas->query($a,7,$ownedOrigin,$dec,$mock),'asaas_company_profile_snapshot_divergence','tampered immutable preference snapshot is rejected');essentialCheck(count($calls)===$before,'snapshot conflict rejected before provider request');
$db->query('UPDATE '.$p.'asaas_company_origin_profiles SET snapshot_hash=? WHERE origin_id=?',[$stored['snapshot_hash'],$ownedOrigin]);
echo 'Company Asaas155 preferences: '.($GLOBALS['essentialChecks']-$preferencesStart).' added; '.$GLOBALS['essentialChecks'].' cumulative passed ('.$db->driver().'). All transport simulated.'.PHP_EOL;
