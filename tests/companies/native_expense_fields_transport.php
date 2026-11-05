<?php
/** Real retained fields view, URL-encoded form values, transport decoder and canonical writer. */
require __DIR__.'/native_expense_form_profile.php';
require_once APPPATH.'services/NativeCompanyExpensesController.php';
$start=$GLOBALS['essentialChecks'];
$decode=new ReflectionMethod(NativeCompanyExpensesController::class,'expenseInput');
// This inspects emitted controls; no DOM/browser, HTTP routing or visual comparison is claimed.
$render=function(array $values)use($ex,$a,$ca):array{
    $esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $field='edit';$categories=$ex->categories($a,7);$clients=[['userid'=>$ca,'company'=>'Synthetic client']];$projects=[];
    ob_start();try{require APPPATH.'views/admin/expenses/fields.php';$html=ob_get_clean();}catch(Throwable $e){ob_end_clean();throw $e;}
    $attribute=static function(string $tag,string $key):?string{return preg_match('/\\b'.preg_quote($key,'/').'="([^"]*)"/',$tag,$m)?html_entity_decode($m[1],ENT_QUOTES|ENT_HTML5,'UTF-8'):null;};
    $pairs=[];
    preg_match_all('/<input\\b[^>]*>/',$html,$inputs);
    foreach($inputs[0] as $tag){$name=$attribute($tag,'name');if($name!==null)$pairs[]=urlencode($name).'='.urlencode($attribute($tag,'value')??'');}
    preg_match_all('/<select\\b([^>]*)>(.*?)<\\/select>/s',$html,$selects,PREG_SET_ORDER);
    foreach($selects as $select){$name=$attribute($select[1],'name');preg_match_all('/<option\\b([^>]*)>(.*?)<\\/option>/s',$select[2],$options,PREG_SET_ORDER);$chosen=$options[0]??null;foreach($options as $option)if(preg_match('/\\bselected\\b/',$option[1]))$chosen=$option;if($name!==null&&$chosen!==null)$pairs[]=urlencode($name).'='.urlencode($attribute($chosen[1],'value')??strip_tags($chosen[2]));}
    preg_match_all('/<textarea\\b([^>]*)>(.*?)<\\/textarea>/s',$html,$areas,PREG_SET_ORDER);
    foreach($areas as $area){$name=$attribute($area[1],'name');if($name!==null)$pairs[]=urlencode($name).'='.urlencode(html_entity_decode($area[2],ENT_QUOTES|ENT_HTML5,'UTF-8'));}
    parse_str(implode('&',$pairs),$wire);return [$html,$wire['expense']];
};
$db->query('INSERT INTO '.$p.'company_taxes(id,issuer_id,name,taxrate,revision) VALUES(3,?,?,?,1)',[$a,'A & <native>','7.2500']);
$db->query('INSERT INTO '.$p.'company_taxes(id,issuer_id,name,taxrate,revision) VALUES(4,?,?,?,1)',[$a,'B "native"','2.1700']);
$transport=$input;$transport['amount']='12345.67';$transport['native_form']['name']='Name & <script>safe</script>';$transport['native_form']['taxes']=[
    ['position'=>1,'id'=>NativeCompanyResourceId::encode('company_tax',3),'name'=>'A & <native>','rate'=>'7.2500'],
    ['position'=>2,'id'=>NativeCompanyResourceId::encode('company_tax',4),'name'=>'B "native"','rate'=>'2.1700'],
];
$transportId=$ex->create($a,7,$transport,hash('sha256','retained-expense-transport-create'));
$before=$ex->get($a,7,$transportId);[$html,$wire]=$render($before);
essentialCheck($wire['amount']==='12345.67'&&$wire['native_form']['taxes'][0]['rate']==='7.2500'&&$wire['native_form']['taxes'][1]['rate']==='2.1700','Rendered controls preserve decimal amount and both exact tax strings through URL encoding');
essentialCheck($wire['native_form']['taxes'][0]['id']===$transport['native_form']['taxes'][0]['id']&&$wire['native_form']['taxes'][0]['position']==='1','HTML transport retains canonical ID and carries tax position as its actual string');
essentialCheck(!str_contains($html,'<script>safe</script>')&&$wire['native_form']['name']===$transport['native_form']['name'],'Retained fields escape display while restoring the exact stored profile text');
$decoded=$decode->invoke(null,$wire);
essentialCheck($decoded['native_form']===$transport['native_form']&&!array_key_exists('tax_rate',$decoded),'Real decoder restores the ordered native profile without adding a summed scalar');
$decoded['note']='Edited through retained form';$ex->change($a,7,$transportId,$before['revision_token'],hash('sha256','retained-expense-transport-edit'),$decoded);
$after=$ex->get($a,7,$transportId);
essentialCheck($after['native_form']===$before['native_form']&&(int)$after['total_cents']===(int)$before['total_cents']&&(int)$after['total_cents']===1350863,'Actual edit writer preserves independent percentages and exact final cents after form serialization');
$empty=$transport;$empty['native_form']['taxes']=[];$emptyId=$ex->create($a,7,$empty,hash('sha256','retained-expense-empty-tax-create'));$emptyRow=$ex->get($a,7,$emptyId);
[$emptyHtml,$emptyWire]=$render($emptyRow);$emptyDecoded=$decode->invoke(null,$emptyWire);
essentialCheck(($emptyWire['native_form']['empty_taxes']??null)==='1'&&$emptyDecoded['native_form']['taxes']===[]&&!isset($emptyDecoded['native_form']['empty_taxes']),'Actual empty-list marker survives HTML omission and restores zero taxes explicitly');
$ex->change($a,7,$emptyId,$emptyRow['revision_token'],hash('sha256','retained-expense-empty-tax-edit'),$emptyDecoded);
essentialCheck($ex->get($a,7,$emptyId)['native_form']===$empty['native_form'],'Zero-tax retained editor remains writable with its original native profile');
$bad=$wire;$bad['native_form']['taxes'][0]['position']='1.0';$bad=$decode->invoke(null,$bad);
essentialThrows(fn()=>$ex->change($a,7,$transportId,$after['revision_token'],hash('sha256','retained-expense-invalid-position'),$bad),'native_expense_form_invalid','Noncanonical tax positions remain rejected by actual financial writer');
essentialCheck($ex->get($a,7,$transportId)===$after,'Rejected transport leaves amount, metadata and revision unchanged');
echo 'Native expense retained fields: '.($GLOBALS['essentialChecks']-$start).' added render/serialization/writer checks passed ('.$db->driver().').'.PHP_EOL;
