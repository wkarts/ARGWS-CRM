<?php
require __DIR__.'/native_project_choices.php';
$all=$read->relationOptions($a,7,'100%',$ca);essentialCheck(count($all)===1&&$all[0]['id']===NativeCompanyResourceId::encode('company_project',$new),'Relation selector includes real completed projects unlike billing association choices');
essentialCheck($all[0]['link']==='projects/view/'.$all[0]['id'],'Link uses unchanged native view route with canonical transport identity');
essentialCheck($read->relationOptions($a,7,'100_',$ca)===[],'Relation search escapes SQL wildcards');
$primaryRows=$read->relationOptions($primary,7);$kinds=array_unique(array_column($primaryRows,'source_kind'));sort($kinds);essentialCheck($kinds===['company_project','project'],'Principal relation selector preserves both proven lineages');
essentialCheck(count(array_filter($primaryRows,fn($r)=>$r['name']==='Forbidden private client'))===0,'Principal legacy relation search excludes secondary-owned customer project');
essentialCheck(count($read->relationOptions($primary,7,'',null,1))===1,'Actual SQL search and final union obey bounded result limit');
essentialThrows(fn()=>$read->relationOptions($b,7,'',$ca),'native_resource_reference_denied','Relation filter validates customer authority, not just numeric ID');
essentialThrows(fn()=>$read->relationOptions($a,51),'native_project_access_denied','Relation lookup requires live project ACL');
essentialThrows(fn()=>$read->relationOptions($a,7,'',null,51),'native_project_query_invalid','Oversized caller limit is rejected');
echo 'Native project relation selector: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
