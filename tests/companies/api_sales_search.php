<?php
require __DIR__.'/sales_document_readers.php';
require __DIR__.'/../../modules/api/models/Api_model.php';
function has_permission(...$args){return true;}
function startsWith($text,$prefix){return str_starts_with((string)$text,(string)$prefix);}
function strafter($text,$prefix){return substr($text,strlen((string)$prefix));}
$before=$n;$api=(new ReflectionClass(Api_model::class))->newInstanceWithoutConstructor();$api->db=$model->db;
$columns=['clients'=>['phonenumber','city','state','zip'], 'proposals'=>['subject','content','proposal_to','zip','state','city','address','email','phone'], 'estimates'=>['number','clientnote','adminnote'], 'creditnotes'=>['adminnote']];
foreach(['estimates','creditnotes']as$table)$columns[$table]=array_merge($columns[$table],['billing_street','billing_city','billing_state','billing_zip','shipping_street','shipping_city','shipping_state','shipping_zip']);
foreach($columns as$table=>$fields)foreach($fields as$field)if(!$model->db->field_exists($field,'q_'.$table))$pdo->exec('ALTER TABLE q_'.$table.' ADD '.$field." TEXT DEFAULT ''");
foreach(['proposals'=>['id','_search_proposals'],'estimates'=>['estimateid','_search_estimates'],'creditnotes'=>['credit_note_id','_search_credit_notes']]as$table=>[$key,$method]){
 check(array_column($api->$method('',0,true)['result'],$key)===($table==='proposals'?[1,3]:[1]),'API '.$table.' positive legacy/lead list preserved');
 check($api->$method("' OR 1=1 --",0,true)['result']===[],'API '.$table.' treats SQL-like input literally');
 check($api->$method('Private',0,true)['result']===[],'API '.$table.' private customer search denied');
}
echo 'Sales API search: '.($n-$before)." additional checks passed\n";
require __DIR__.'/../../application/services/InvoiceIssuerScope.php';
$pdo->exec("CREATE TABLE q_invoices(id INT,issuer_id INT,clientid INT,currency INT); INSERT INTO q_invoices VALUES(1,NULL,1,1),(2,NULL,2,1),(3,82,2,1)");
foreach(['number','clientnote','adminnote','billing_street','billing_city','billing_state','billing_zip','shipping_street','shipping_city','shipping_state','shipping_zip']as$field)$pdo->exec('ALTER TABLE q_invoices ADD '.$field." TEXT DEFAULT ''");
$rows=$api->_search_invoices('',0,[],true)['result'];
check(array_column($rows,'invoiceid')===[1,2],'invoice history remains attached to legacy issuer even when customer is private');
check(!array_key_exists('phonenumber',$rows[1]),'adopted-customer legacy invoice search retains minimal customer identity only');
check($api->_search_invoices("' OR 1=1 --",0,[],true)['result']===[],'invoice text search binds literal query');
echo 'Sales/invoice API search: '.($n-$before)." additional checks passed\n";
