<?php
/** Real canonical credit reader, ledger and SQL; no HTTP/browser or provider claims. */
require __DIR__.'/credits_operations.php';
require __DIR__.'/native_invoice_table_schema_fixture.php';
require_once APPPATH.'services/NativeCompanyCreditDirectory.php';
require_once APPPATH.'services/NativeCompanyCreditTableFactory.php';
require_once APPPATH.'services/NativeCompanyDataTableWire.php';
if(!function_exists('_l')){function _l($key){return$key;}}
$directory=new NativeCompanyCreditDirectory($db,$p);
$wire=['start'=>0,'length'=>25,'search'=>'','orders'=>[['field'=>'number','direction'=>'asc']]];
$before=$db->query('SELECT * FROM '.$p.'creditnotes ORDER BY id')->result_array();
$page=$directory->listing($a,7,$wire);
essentialCheck($page['total']===2&&$page['filtered']===2&&array_column(array_column($page['rows'],'document'),'id')===[$credit,$untouched],'directory restores original paging/order on owned canonical credits');
essentialCheck($page['rows'][0]['document']['available']==='60.00'&&$page['rows'][1]['document']['available']==='0.00','list reconciles movement reversals and cancelled credit balance');
$other=$directory->listing($b,7,$wire);
essentialCheck($other['total']===1&&$other['rows'][0]['document']['id']===$creditB,'same actor switching company receives only B records and counts');
essentialCheck($directory->listing($principal,7,$wire)['total']===0,'principal does not adopt secondary credit documents or legacy rows lacking snapshots');
$balanceRule=['match_type'=>'and','rules'=>[['id'=>'remaining_amount','type'=>'NumberRule','operator'=>'greater','value'=>'50','has_dynamic_value'=>false]]];
essentialCheck($directory->listing($a,7,$wire,$balanceRule)['filtered']===1,'remaining balance filter follows ledger projection including void semantics');
$range=['match_type'=>'and','rules'=>[['id'=>'date','type'=>'DateRule','operator'=>'between','value'=>['2026-10-01','2026-10-31']]]];
essentialCheck($directory->listing($a,7,$wire,$range)['filtered']===2,'original credit date range filter runs with bound dates');
$bad=$balanceRule;$bad['rules'][0]['id']='issuer_id';essentialThrows(fn()=>$directory->listing($a,7,$wire,$bad),'native_credit_filter_invalid','filter cannot inject context column');
$bad=$wire;$bad['orders'][0]['field']='c.id DESC; DROP TABLE';essentialThrows(fn()=>$directory->listing($a,7,$bad),'native_credit_table_invalid','submitted ordering cannot become SQL');
$search=$wire;$search['search']=$other['rows'][0]['document']['recipient']['name'];essentialCheck($directory->listing($a,7,$search)['filtered']===0,'search cannot broaden company scope');
$fields=new NativeCompanyCustomFields($db,$p);
$definition=NativeCompanyCustomFields::definition(['fieldto'=>'credit_note','name'=>'Evidence category','type'=>'input','active'=>1,'show_on_table'=>1]);
$fieldIds=[];
foreach([$a,$b]as$issuer){$db->query('INSERT INTO '.$p.'company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(?,?,?,?,1)',[$issuer,'credit_note','credit_category',json_encode($definition,JSON_THROW_ON_ERROR)]);$fieldIds[$issuer]=NativeCompanyResourceId::encode('company_custom_field',$db->insert_id());}
IssuerTransaction::run($db,$p,array_merge($fields->tableNames(),['creditnotes']),fn()=>$fields->saveInOwnedTransaction($a,7,'credit_note',$credit,[$fieldIds[$a]=>'Literal 50% <script>']));
$rule=['match_type'=>'and','rules'=>[['id'=>'credit_category','type'=>'TextRule','operator'=>'contains','value'=>'50%','has_dynamic_value'=>false]]];
$custom=$directory->listing($a,7,$wire,$rule);
essentialCheck($custom['filtered']===1&&$custom['rows'][0]['custom_values'][(string)$fieldIds[$a]]==='Literal 50% <script>','original custom-field rules return actual scoped values and literal percent');
$rule['rules'][0]['value']='50_';essentialCheck($directory->listing($a,7,$wire,$rule)['filtered']===0,'custom underscore is literal and cannot expand a search');
$sort=$wire;$sort['orders']=[['field'=>'custom_field_'.$fieldIds[$a],'direction'=>'desc']];essentialCheck($directory->listing($a,7,$sort)['rows'][0]['document']['id']===$credit,'custom ordering uses authorized stored value');
essentialThrows(fn()=>$directory->listing($b,7,$sort),'native_credit_table_invalid','A custom field sort is not available under B');
$all=$wire;$all['length']=-1;essentialCheck(count($directory->listing($a,7,$all)['rows'])===2,'ALL has no silent 50/100 row truncation');
$second=$wire;$second['start']=1;$second['length']=1;essentialCheck($directory->listing($a,7,$second)['rows'][0]['document']['id']===$untouched,'offset and length produce the requested page');
$table=(new NativeCompanyCreditTableFactory($db,$p))->make($a,7);
essentialCheck($table->id()==='credit_notes'&&$table->viewName()==='credit_notes'&&count($table->rules())===8,'original filter vocabulary plus owned custom definition is exposed');
$db->query("UPDATE {$p}credits SET amount='19.99' WHERE credit_id=?",[$credit]);
essentialThrows(fn()=>$directory->listing($a,7,$wire),'company_credit_balance_review','table refuses a tampered ledger projection instead of presenting false balance');
$db->query("UPDATE {$p}credits SET amount='20.00' WHERE credit_id=?",[$credit]);
essentialCheck($db->query('SELECT * FROM '.$p.'creditnotes ORDER BY id')->result_array()===$before,'directory/filter/sort never rewrite documents or ledger');
echo 'Native credit table and filters: '.$GLOBALS['essentialChecks']." cumulative checks PASS (".$db->driver()."). No HTTP/browser or external financial calls.\n";
