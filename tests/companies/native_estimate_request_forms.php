<?php
declare(strict_types=1);
require __DIR__.'/native_misc_settings.php';
require_once APPPATH.'services/NativeCompanyEstimateRequestForms.php';
$start=$GLOBALS['essentialChecks'];
$db->query('CREATE TABLE '.$p.'estimate_request_forms(id INTEGER PRIMARY KEY,name TEXT NOT NULL,form_key TEXT NOT NULL)');unset($db->data_cache['table_names']);
$db->query('INSERT INTO '.$p."estimate_request_forms(id,name,form_key)VALUES(1,'Historical native form','synthetic-public-key')");
NativeCompanyEstimateRequestSchema::ensure($db,$p);NativeCompanyEstimateRequestSchema::ensure($db,$p);
$db->query('INSERT INTO '.$p."estimate_request_forms(id,name,form_key,issuer_id)VALUES(2,'A native form','synthetic-A',41),(3,'B native form','synthetic-B',82)");
$reader=new NativeCompanyEstimateRequestForms($db,$p);
essentialCheck($reader->forSettings(41,7)===[['id'=>2,'name'=>'A native form']],'A reads actual owned native row');
essentialCheck($reader->forSettings(82,7)===[['id'=>3,'name'=>'B native form']],'B reads actual owned native row');
essentialCheck($reader->forSettings($primary,7)===[['id'=>1,'name'=>'Historical native form']],'unowned historical native rows only primary');
essentialCheck($db->query('SELECT form_key,issuer_id FROM '.$p.'estimate_request_forms WHERE id=1')->row_array()===['form_key'=>'synthetic-public-key','issuer_id'=>null],'migration does not adopt or rewrite historical row');
settingThrows(fn()=>$reader->forSettings(41,14),'native_estimate_form_denied','membership without settings does not read native forms');
$joined=IssuerTransaction::run($db,$p,$reader->tables(),fn()=>$reader->forSettings(41,7));essentialCheck($joined===$reader->forSettings(41,7),'existing owned settings transaction supported');
$db->pdo->beginTransaction();try{settingThrows(fn()=>$reader->forSettings(41,7),'issuer_outer_transaction_not_allowed','arbitrary outer transaction cannot become authority');}finally{if($db->pdo->inTransaction())$db->pdo->rollBack();}
$index='nefri_'.substr(hash('sha256',$p.'estimate_request_forms'),0,40);$db->query('DROP INDEX '.$index);settingThrows(fn()=>$reader->forSettings(41,7),'issuer_storage_failed','missing ownership index fails closed');NativeCompanyEstimateRequestSchema::ensure($db,$p);
echo 'Native estimate form ownership: '.($GLOBALS['essentialChecks']-$start).' real SQLite lineage/read/ACL/replay/transaction/schema checks passed.'.PHP_EOL;
