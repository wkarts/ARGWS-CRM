<?php
require __DIR__.'/native_estimate_portal_decisions.php';
$before=$portalSales->countVisible($sessionA,$a,'estimates');$sum=0;foreach(['1','2','3','4','5']as$status)$sum+=$portalSales->countVisible($sessionA,$a,'estimates',$status);essentialCheck($before===$sum,'status counts equal the exact visible count');
$columns=['issuer_id','actor_id','conversion_actor_id','client_id','currency','kind','number','state','payload_json','context_json','origin_hash','total','request_key','input_hash','invoice_id','created_at'];$select=$columns;$select[6]='?';$select[12]='?';$sql='INSERT INTO '.$p.'company_sales_documents (`'.implode('`,`',$columns).'`) SELECT '.implode(',',$select).' FROM '.$p.'company_sales_documents WHERE id=?';
for($n=0;$n<201;$n++)$db->query($sql,[100000+$n,hash('sha256','portal-unpublished-bulk-'.$n),$failed]);
$page=$portalSales->pageCursor($sessionA,$a,'estimates',null,10);essentialCheck($page['items']===[]&&$page['next']!==null,'a bounded batch of unpublished drafts returns a continuation cursor');
$next=$portalSales->pageCursor($sessionA,$a,'estimates',$page['next'],10);essentialCheck(count($next['items'])>0,'following an empty batch discovers older published estimates');
essentialCheck($portalSales->countVisible($sessionA,$a,'estimates')===$before,'unpublished drafts never inflate visible total');
essentialCheck($portalSales->countVisible($sessionB,$a,'estimates')===null,'other company session receives no visible count');
$grant=$grants->page($a,7,$ca)[$contactA];$grants->set($a,7,$ca,$contactA,$grant['revision'],'0');essentialCheck($portalSales->countVisible($sessionA,$a,'estimates')===null,'revoked contact receives no visible count');
echo "Native estimate portal bounded cursor and exact counts: 6 checks.\n";
