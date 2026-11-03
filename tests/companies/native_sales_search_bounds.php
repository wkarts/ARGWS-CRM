<?php
// Synthetic bulk fixture proves filtering occurs before the result cap.
require __DIR__.'/native_sales_number_formats.php';
$start=$GLOBALS['essentialChecks'];
$seed=$db->query('SELECT * FROM '.$p.'company_sales_documents WHERE id=?',[$documents['1']])->row_array();
unset($seed['id']);$seed['state']='open';$seed['invoice_id']=0;$seed['conversion_actor_id']=0;
$context=json_decode($seed['context_json'],true,32,JSON_THROW_ON_ERROR);$context['sales_preferences']['format']=4;$context['sales_preferences']['number_format']='2';$context['sales_preferences']['padding']='3';
$insert=function(array $row)use($db,$p){$columns=array_keys($row);$db->query('INSERT INTO '.$p.'company_sales_documents (`'.implode('`,`',$columns).'`) VALUES ('.implode(',',array_fill(0,count($columns),'?')).')',array_values($row));return(int)$db->insert_id();};
for($i=0;$i<180;$i++){
 $row=$seed;$row['number']=10000+$i;$row['request_key']=hash('sha256','bulk-noise-'.$i);$noise=$context;$noise['sales_preferences']['prefix']='NOISE-';$row['context_json']=json_encode($noise,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$row['origin_hash']=hash('sha256',$row['context_json']);$insert($row);
}
$target=$seed;$target['number']=11000;$target['request_key']=hash('sha256','bulk-target');$wanted=$context;$wanted['sales_preferences']['prefix']='TARGET-';$target['context_json']=json_encode($wanted,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$target['origin_hash']=hash('sha256',$target['context_json']);$targetId=$insert($target);
$found=$sales->relationOptions($a,7,'estimates','TARGET-2026');
essentialCheck(count($found)===1&&(int)$found[0]['id']===$targetId&&$found[0]['name']==='TARGET-2026/11000','partial year/prefix finds target after 180 earlier unrelated rows');
essentialCheck($sales->relationOptions($b,7,'estimates','TARGET-2026')===[],'late target is invisible in company B');
$noise=$sales->relationOptions($a,7,'estimates','NOISE-2026');essentialCheck(count($noise)===50&&count(array_unique(array_column($noise,'id')))===50,'50-result cap is applied after matching and without duplicates');
essentialCheck($sales->relationOptions($a,7,'estimates','NOISE-%')===[]&&$sales->relationOptions($a,7,'estimates','NOISE-_')===[],'LIKE wildcards remain literal');
echo 'Native sales search bounded/cross-company/escape: '.($GLOBALS['essentialChecks']-$start)." checks.\n";
