<?php
require __DIR__.'/native_task_relation_option.php';
$db->query("UPDATE {$p}issuers_staff SET can_view=1 WHERE issuer_id=? AND staff_id=51",[$a]);
$start=count($db->queries);essentialThrows(fn()=>NativeCompanyResourceReferences::resolve($db,$p,$a,51,'task',$owned),'native_resource_reference_denied','Direct resolver requires parent project view before task data');
essentialCheck(!array_filter(array_slice($db->queries,$start),static fn($q)=>str_contains($q[0],' AS label FROM ')),'Direct resolver never hydrates task label for revoked project grant');
$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,51,'projects','view')",[$a]);$db->query("UPDATE {$p}company_tasks SET project_id=2 WHERE id=1");
$start=count($db->queries);essentialThrows(fn()=>NativeCompanyResourceReferences::resolve($db,$p,$a,51,'task',$owned),'native_resource_reference_denied','Direct resolver rejects cross-company parent before task label');
essentialCheck(!array_filter(array_slice($db->queries,$start),static fn($q)=>str_contains($q[0],' AS label FROM ')),'Damaged parent relation never reaches label hydration');
$db->query("UPDATE {$p}company_tasks SET project_id=1 WHERE id=1");essentialCheck(NativeCompanyResourceReferences::resolve($db,$p,$a,51,'task',$owned)['name']==='Project A','Valid direct resolver remains functional');
echo 'Direct task resource pre-hydration: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
$db->query("CREATE TABLE {$p}projects(id INT PRIMARY KEY,clientid INT NOT NULL,name VARCHAR(100) NOT NULL)");$db->query("INSERT INTO {$p}clients VALUES(20,'Actual historical customer')");$db->query("INSERT INTO {$p}projects VALUES(3,20,'Actual historical project')");$db->query("INSERT INTO {$p}tasks VALUES(3,'Historical project task','project',3)");
$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(1,51,1)");$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(1,51,'tasks','view')");unset($db->data_cache['table_names']);
$start=count($db->queries);essentialThrows(fn()=>NativeCompanyResourceReferences::resolve($db,$p,1,51,'task','3'),'native_resource_reference_denied','Historical project task also requires project grant before hydration');
essentialCheck(!array_filter(array_slice($db->queries,$start),static fn($q)=>str_contains($q[0],' AS label FROM ')),'Historical task label is not loaded under task-only grant');
$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(1,51,'projects','view')");essentialCheck(NativeCompanyResourceReferences::resolve($db,$p,1,51,'task','3')['name']==='Historical project task','Historical explicit parent grant preserves actual primary lineage');
echo 'Historical task direct ACL composition: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
