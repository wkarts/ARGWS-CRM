<?php
if(!in_array(getenv('NATIVE_RELATION_SALES')?:'estimate',['estimate','proposal'],true))throw new RuntimeException('This fixture covers estimate/proposal boundaries');
require __DIR__.'/relation_sales.php';
require_once APPPATH.'services/NativeCompanyResourceReferences.php';
// The relation fixture ends with the historical document linked to a named customer.
$denied=false;try{NativeCompanyResourceReferences::resolve($db,$p,$primary,7,$domain,$legacyId);}catch(DomainException$e){$denied=true;}essentialCheck($denied,'generic reference rejects historical document linked to named customer');
$db->query('UPDATE '.$p.$table.' SET '.$field.'=? WHERE id=?',[$historical,$legacyId]);
$ref=NativeCompanyResourceReferences::resolve($db,$p,$primary,7,$domain,$legacyId);essentialCheck($ref['source_id']===$legacyId,'generic historical reference preserves proven native identity');
if($domain==='proposal'){
 $db->query("INSERT INTO {$p}leads(id,client_id,issuer_id) VALUES(9911,?,NULL),(9912,?,NULL),(9913,?,?)",[$historical,$ca,$historical,$a]);
 $db->query("UPDATE {$p}proposals SET rel_type='lead',rel_id=9911 WHERE id=?",[$legacyId]);essentialCheck(NativeCompanyResourceReferences::resolve($db,$p,$primary,7,'proposal',$legacyId)['source_id']===$legacyId,'generic reference accepts actual historical lead');
 foreach([9912,9913,9914]as$lead){$db->query("UPDATE {$p}proposals SET rel_id=? WHERE id=?",[$lead,$legacyId]);$denied=false;try{NativeCompanyResourceReferences::resolve($db,$p,$primary,7,'proposal',$legacyId);}catch(DomainException$e){$denied=true;}essentialCheck($denied,'generic proposal rejects named or missing lead before label');}
}
echo "Generic sales reference lineage checks PASS ({$domain}).\n";
