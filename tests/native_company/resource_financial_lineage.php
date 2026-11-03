<?php
putenv('NATIVE_RELATION_SALES=credit_note');require __DIR__.'/relation_sales.php';
require_once APPPATH.'services/NativeCompanyResourceReferences.php';
foreach(['credit_note'=>['creditnotes',$legacyId],'invoice'=>['invoices',42]]as$type=>[$target,$source]){
 $db->query('UPDATE '.$p.$target.' SET clientid=? WHERE id=?',[$historical,$source]);
 essentialCheck(NativeCompanyResourceReferences::resolve($db,$p,$primary,7,$type,$source)['source_id']===$source,'principal financial reference preserves proved historical customer');
 foreach([$ca,999999]as$invalid){$db->query('UPDATE '.$p.$target.' SET clientid=? WHERE id=?',[$invalid,$source]);$db->queries=[];$deny=false;try{NativeCompanyResourceReferences::resolve($db,$p,$primary,7,$type,$source);}catch(DomainException $e){$deny=true;}essentialCheck($deny,'NULL financial reference cannot hydrate named or missing customer');essentialCheck(!array_filter($db->queries,static fn($q)=>str_contains($q[0],'number AS label')),'lineage denial occurs before number hydration');}
 $db->query('UPDATE '.$p.$target.' SET clientid=? WHERE id=?',[$historical,$source]);$deny=false;try{NativeCompanyResourceReferences::resolve($db,$p,$a,7,$type,$source);}catch(DomainException $e){$deny=true;}essentialCheck($deny,'named context cannot claim historical financial reference');
}
echo "Financial relation historical customer boundary 12 checks PASS.\n";
