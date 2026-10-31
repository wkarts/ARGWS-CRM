<?php
/** Actual durable value/renewal and TCPDF output; no mail transport or legal signature. */
require __DIR__.'/contracts_operations.php';$start=$GLOBALS['essentialChecks'];
if(!$db->table_exists($p.'currencies'))$db->query('CREATE TABLE '.$p.'currencies(id int PRIMARY KEY,name varchar(3) NOT NULL)');
if(!$db->query('SELECT id FROM '.$p.'currencies WHERE name=?',['BRL'])->row_array())$db->query('INSERT INTO '.$p.'currencies(id,name) VALUES(1,?)',['BRL']);
if(!$db->query('SELECT id FROM '.$p.'currencies WHERE name=?',['USD'])->row_array())$db->query('INSERT INTO '.$p.'currencies(id,name) VALUES(2,?)',['USD']);
unset($db->data_cache['table_names']);
$image=imagecreatetruecolor(2,2);imagefill($image,0,0,imagecolorallocate($image,10,30,90));ob_start();imagepng($image);$png=ob_get_clean();unset($image);$assets=new CompanyAssets($db,$p);$ids=[];
foreach([[$a,$ca],[$b,$cb],[$primary,$cp]]as[$owner,$client]){
 foreach(['logo','signature']as$kind)$assets->save($owner,7,$kind,$png,$assets->view($owner,7)['revision']);
 (new CompanySettingsStorage($db,$p))->saveIdentity($owner,7,['profile'=>['invoice_company_address'=>'Stored address '.$owner,'invoice_company_city'=>'Salvador','company_state'=>'BA','invoice_company_postal_code'=>'40000-000','invoice_company_country_code'=>'BR']]);
 $input=array_replace($contractInput,['client_id'=>$client,'contract_value'=>'1234.50','currency'=>'BRL']);$id=$contracts->create($owner,7,$input,$key('valued-contract-'.$owner));$ids[$owner]=$id;
 essentialCheck(CompanyContractValue::stored(json_decode($contracts->get($owner,7,$id)['snapshot_json'],true))===['contract_value'=>'1234.50','currency'=>'BRL'],'A/B/principal store explicit value and currency on the draft');
 $contracts->transition($owner,7,$id,1,'activate',$key('valued-activate-'.$owner));$old=$contracts->get($owner,7,$id);$snapshot=json_decode($old['snapshot_json'],true);
 essentialCheck($snapshot['contract_value']==='1234.50'&&$snapshot['identity']['address']==='Stored address '.$owner&&$snapshot['identity']['logo_inline']!==''&&$snapshot['identity']['signature_inline']!=='','Activation freezes actual value, identity and owned branding');
 $bytes=CompanyContractsPdf::render($old);essentialCheck(str_starts_with($bytes,'%PDF-')&&str_contains($bytes,'/Subtype /Image'),'Actual PDF embeds stored raster images');
 $new=$contracts->renew($owner,7,$id,$old['revision'],'2027-01-01','2027-12-31',$key('valued-renew-'.$owner),'1500.25','BRL');$next=$contracts->get($owner,7,$new);
 essentialCheck(CompanyContractValue::stored(json_decode($next['snapshot_json'],true))===['contract_value'=>'1500.25','currency'=>'BRL']&&$contracts->get($owner,7,$id)===$old,'Renewal applies new value only to the successor and preserves the issued source');
 essentialCheck($contracts->renew($owner,7,$id,$old['revision'],'2027-01-01','2027-12-31',$key('valued-renew-'.$owner),'1500.25','BRL')===$new,'Renewal value participates in durable replay');
 essentialThrows(fn()=>$contracts->renew($owner,7,$id,$old['revision'],'2027-01-01','2027-12-31',$key('valued-renew-'.$owner),'999.00','BRL'),'company_operation_request_conflict','A changed renewal value cannot reuse its request');
 $inherited=$contracts->renew($owner,7,$id,$old['revision'],'2028-01-01','2028-12-31',$key('inherited-renew-'.$owner));essentialCheck(CompanyContractValue::stored(json_decode($contracts->get($owner,7,$inherited)['snapshot_json'],true))['contract_value']==='1234.50','Empty renewal value preserves the actual source value');
 $foreignCurrency=$contracts->renew($owner,7,$id,$old['revision'],'2028-01-01','2028-12-31',$key('currency-renew-'.$owner),'375.42','USD');
 essentialCheck(CompanyContractValue::stored(json_decode($contracts->get($owner,7,$foreignCurrency)['snapshot_json'],true))===['contract_value'=>'375.42','currency'=>'USD']&&$contracts->get($owner,7,$id)===$old,'Renewal can explicitly change value and stored currency without currency conversion or rewriting the predecessor');
 $contracts->transition($owner,7,$foreignCurrency,1,'activate',$key('currency-activate-'.$owner));$currencyDocument=$contracts->get($owner,7,$foreignCurrency);
 essentialCheck(CompanyContractValue::stored(json_decode($currencyDocument['snapshot_json'],true))===['contract_value'=>'375.42','currency'=>'USD']&&str_starts_with(CompanyContractsPdf::render($currencyDocument),'%PDF-'),'issued successor and real PDF retain the selected stored currency');
 essentialThrows(fn()=>$contracts->renew($owner,7,$id,$old['revision'],'2028-01-01','2028-12-31',$key('currency-renew-'.$owner),'375.42','BRL'),'company_operation_request_conflict','same renewal key cannot silently change currency');
}
$id=$ids[$b];$row=$contracts->get($b,7,$id);$before=[];foreach(['company_contracts','company_contract_requests','issuers_audit']as$table)$before[$table]=$db->query('SELECT * FROM '.$p.$table)->result_array();$db->failOn='INSERT INTO '.$p.'company_contract_requests';essentialThrows(fn()=>$contracts->renew($b,7,$id,$row['revision'],'2029-01-01','2029-12-31',$key('value-rollback'),'9.99','BRL'),'issuer_storage_failed','Late receipt failure rolls back value renewal');$db->failOn=null;foreach($before as$table=>$rows)essentialCheck($db->query('SELECT * FROM '.$p.$table)->result_array()===$rows,'Renewal rollback preserves '.$table);
essentialThrows(fn()=>$contracts->renew($a,7,$id,$row['revision'],'2029-01-01','2029-12-31',$key('value-foreign'),'9.99','BRL'),'company_operation_access_denied','Known foreign contract cannot be renewed');
essentialThrows(fn()=>$contracts->create($b,7,array_replace($contractInput,['client_id'=>$cb,'contract_value'=>'1.234','currency'=>'BRL']),$key('invalid-value')),'company_contract_value_invalid','Value precision cannot silently round');
essentialThrows(fn()=>$contracts->create($b,7,array_replace($contractInput,['client_id'=>$cb,'contract_value'=>'1.00','currency'=>'ZZZ']),$key('invalid-currency')),'company_contract_value_invalid','Currency must exist in the real stored reference catalog');
$forged=$row;$snapshot=json_decode($row['snapshot_json'],true);$snapshot['identity']['logo_inline']='https://external.invalid/logo.png';$forged['snapshot_json']=json_encode($snapshot);essentialThrows(fn()=>CompanyContractsPdf::render($forged),'company_document_asset_invalid','PDF refuses remote/path branding');
echo 'Contract values, renewal, frozen identity/branding, actual PDF and rollback: '.($GLOBALS['essentialChecks']-$start).' added checks passed ('.$db->driver().').'.PHP_EOL;
