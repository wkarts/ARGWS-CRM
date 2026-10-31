<?php
require __DIR__.'/asaas_preferences.php';
require_once APPPATH.'services/NativeCompanyInvoicePaymentChoices.php';
NativeCompanyPaymentModesSchema::ensure($db,$p);$offline=new NativeCompanyPaymentModes($db,$p);
$mode=$offline->save($a,7,['name'=>'Synthetic native invoice offline','description'=>'','active'=>1,'selected_by_default'=>1,'show_on_pdf'=>1,'invoices_only'=>1,'expenses_only'=>0]);
$other=$offline->save($b,7,['name'=>'B private offline','description'=>'','active'=>1,'selected_by_default'=>0,'show_on_pdf'=>1,'invoices_only'=>1,'expenses_only'=>0]);
$choices=new NativeCompanyInvoicePaymentChoices($db,$p);
// A's configured and verified synthetic sandbox account; explicit policy selection through actual service.
$config=$asaas->accounts($a,7)[0];$current=$policy->page($a,7);$policy->selectEnvironment($a,7,'sandbox',$current['revision'],$config['revision']);
$current=$policy->page($a,7);$profile=$current['profiles']['sandbox'];unset($profile['revision']);$profile['allow_pix']='1';$policy->saveProfile($a,7,'sandbox',$profile,$current['profiles']['sandbox']['revision'],$config['revision']);
$before=count($calls);$tables=['invoices','issuers_sequences','issuers_audit','asaas_company_preferences','asaas_company_profiles','asaas_company_accounts'];$snapshot=[];foreach($tables as$table)$snapshot[$table]=$db->query('SELECT * FROM '.$p.$table)->result_array();
$list=$choices->listing($a,7);$byId=array_column($list,null,'id');
essentialCheck(isset($byId[$mode['id']])&&!isset($byId[$other['id']]),'payment select contains only the real current-issuer offline catalogue');
essentialCheck($byId['asaas']['selection_available']===true&&$byId['asaas']['checkout_ready']===false,'verified selected Asaas environment permits selection but does not claim an invoice checkout');
essentialCheck($choices->validate($a,7,[(string)$mode['id'],'asaas'])===[(string)$mode['id'],'asaas'],'offline canonical ID and provider string remain distinct');
essentialThrows(fn()=>$choices->validate($a,7,[(string)$other['id']]),'native_invoice_payment_mode_denied','foreign offline mode cannot enter form selection');
essentialThrows(fn()=>$choices->validate($a,7,['asaas','asaas']),'native_invoice_payment_mode_denied','duplicate mode cannot produce ambiguous persisted selection');
$serialized=json_encode($list,JSON_THROW_ON_ERROR);foreach(['secret_cipher','token_hash','token_cipher','wallet_id','account_hash','synthetic:']as$private)essentialCheck(!str_contains($serialized,$private),'payment selection never exports '.$private);
essentialCheck(count($calls)===$before,'read-only choices never contact provider');foreach($snapshot as$table=>$rows)essentialCheck($db->query('SELECT * FROM '.$p.$table)->result_array()===$rows,'choices leave '.$table.' untouched');
$db->query('UPDATE '.$p.'asaas_company_accounts SET enabled=0 WHERE id=?',[$config['id']]);
try{$inactive=array_column($choices->listing($a,7),null,'id');essentialCheck(!$inactive['asaas']['selection_available'],'revoked account cannot be offered as configured');essentialThrows(fn()=>$choices->validate($a,7,['asaas']),'native_invoice_payment_mode_denied','stale form cannot select revoked account gateway');}finally{$db->query('UPDATE '.$p.'asaas_company_accounts SET enabled=1 WHERE id=?',[$config['id']]);}
$db->query('UPDATE '.$p.'asaas_company_preferences SET active_environment=NULL WHERE issuer_id=?',[$a]);
try{$unset=array_column($choices->listing($a,7),null,'id');essentialCheck(!$unset['asaas']['selection_available'],'no active environment does not infer production or first account');}finally{$db->query('UPDATE '.$p.'asaas_company_preferences SET active_environment=? WHERE issuer_id=?',['sandbox',$a]);}
$owned=IssuerTransaction::run($db,$p,NativeCompanyInvoicePaymentChoices::validationTables(['asaas']),fn()=>$choices->validateInOwned($a,7,['asaas']));essentialCheck($owned===['asaas'],'Asaas permission validates inside exact issuer transaction without nested provider operation');
$creation=$input;$creation['native_preferences']=['cancel_overdue_reminders'=>1,'allowed_payment_modes'=>['asaas']];$issued=(new CompanyInvoices($db,$p))->create($a,7,$creation,hash('sha256','native-asaas-selection-only'));
essentialCheck($db->query('SELECT allowed_payment_modes FROM '.$p.'invoices WHERE id=?',[$issued])->row()->allowed_payment_modes===serialize(['asaas']),'canonical invoice stores exact Asaas gateway string, not an offline or receipt mode');
essentialCheck(count($calls)===$before,'gateway selection and canonical issue never create a provider charge');
echo 'Native payment choices real Asaas/offline: '.$GLOBALS['essentialChecks']." cumulative PASS; Efí/BB real MySQL coverage remains separate.\n";
