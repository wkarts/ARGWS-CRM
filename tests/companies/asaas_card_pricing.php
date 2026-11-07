<?php
/** SQL ownership/CAS/audit + exact calculator from confirmed standalone1.5.6; no remote calls. */
require __DIR__.'/asaas_preferences.php';
require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyCardPricing.php';
$start=$GLOBALS['essentialChecks'];$db->queries=[];
\Asaas\CompanyCardPricingSchema::ensure($db,$p);unset($db->data_cache['table_names']);
$pricing=new \Asaas\CompanyCardPricing($db,$p);
essentialThrows(fn()=>$pricing->page($a,7,$accounts[$a]),'asaas_module_173_required','schema alone does not enable pricing before native tracking');
$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.7.3','asaas']);
$before=$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array();$callsBefore=count($calls);
$page=$pricing->page($a,7,$accounts[$a]);essentialCheck($page['settings']['card_fee_mode']==='absorb'&&$page['settings']['card_anticipation_mode']==='none','source defaults do not infer surcharge or anticipation');
essentialCheck($db->query('SELECT * FROM '.$p.'asaas_company_card_pricing')->result_array()===[],'read does not seed settings');
$values=$page['settings'];$values['card_fee_mode']='customer';$values['installmentCount']=3;
$pricing->save($a,7,$accounts[$a],$values,$page['revision'],$page['account_revision']);
essentialCheck($pricing->preview($a,7,$accounts[$a],'100.00',1)['charged_cents']===10359,'source fee example exact');
essentialCheck($pricing->preview($b,7,$accounts[$b],'100.00',1)['charged_cents']===10000,'B does not inherit A surcharge');
essentialThrows(fn()=>$pricing->page($b,7,$accounts[$a]),'asaas_company_access_denied','foreign account cannot be read');
essentialThrows(fn()=>$pricing->save($a,7,$accounts[$a],$values,$page['revision'],$page['account_revision']),'asaas_company_preferences_stale','stale form cannot overwrite saved pricing');
$current=$pricing->page($a,7,$accounts[$a]);$values['card_anticipation_mode']='manual';$pricing->save($a,7,$accounts[$a],$values,$current['revision'],$current['account_revision']);
essentialCheck($pricing->preview($a,7,$accounts[$a],'100.00',3)['charged_cents']===10778,'source three-installment anticipation example exact');
$current=$pricing->page($a,7,$accounts[$a]);$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows(fn()=>$pricing->save($a,7,$accounts[$a],$page['settings'],$current['revision'],$current['account_revision']),'issuer_storage_failed','audit failure prevents settings save');$db->failOn=null;
essentialCheck($pricing->page($a,7,$accounts[$a])===$current,'audit rollback preserves prior pricing and revision');
essentialThrows(fn()=>$pricing->save($a,7,$accounts[$a],$values,$current['revision'],str_repeat('f',64)),'asaas_company_preferences_stale','rotated account revision is rejected');
$bad=$values;$bad['card_fee_percent_1']='99.999';essentialThrows(fn()=>$pricing->save($a,7,$accounts[$a],$bad,$current['revision'],$current['account_revision']),'asaas_card_pricing_invalid','invalid decimal is rejected');
essentialCheck($db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()===$before&&count($calls)===$callsBefore,'settings and simulations neither change invoices nor call provider');
// Integrity failures never produce a quote with silently substituted settings.
$db->query('UPDATE '.$p.'asaas_company_card_pricing SET environment=? WHERE issuer_id=? AND account_row=?',['production',$a,$accounts[$a]]);
essentialThrows(fn()=>$pricing->preview($a,7,$accounts[$a],'100.00',1),'asaas_card_pricing_corrupt','environment drift rejects quote');
$db->query('UPDATE '.$p.'asaas_company_card_pricing SET environment=? WHERE issuer_id=? AND account_row=?',['sandbox',$a,$accounts[$a]]);
$db->query('UPDATE '.$p.'asaas_company_card_pricing SET settings_hash=? WHERE issuer_id=? AND account_row=?',[str_repeat('0',64),$a,$accounts[$a]]);
essentialThrows(fn()=>$pricing->page($a,7,$accounts[$a]),'asaas_card_pricing_corrupt','modified stored settings hash rejects read');
$stored=$db->query('SELECT settings_json FROM '.$p.'asaas_company_card_pricing WHERE issuer_id=? AND account_row=?',[$a,$accounts[$a]])->row()->settings_json;
$db->query('UPDATE '.$p.'asaas_company_card_pricing SET settings_hash=? WHERE issuer_id=? AND account_row=?',[hash('sha256',$stored),$a,$accounts[$a]]);
echo 'Scoped card pricing: '.($GLOBALS['essentialChecks']-$start).' additional checks ('.$db->driver().').'.PHP_EOL;
