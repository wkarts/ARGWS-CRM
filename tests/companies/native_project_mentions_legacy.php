<?php
require __DIR__.'/native_project_relation_options.php';
foreach(['firstname','lastname']as$column)if(!$db->field_exists($column,$p.'staff'))$db->query('ALTER TABLE '.$p.'staff ADD '.$column.' varchar(100) NULL');
$seed=file_get_contents(dirname(__DIR__,2).'/install/database.sql');preg_match('/CREATE TABLE `tblproject_members` \(.*?\) ENGINE=InnoDB[^;]*;/s',$seed,$m);$sql=str_replace('`tblproject_members`','`'.$p.'project_members`',$m[0]);$db->query($sql);$db->query('INSERT INTO '.$p.'project_members(id,project_id,staff_id) VALUES(1,?,7),(2,?,7)',[$pp,$pp+1000]);
$names=$read->mentionMembers($primary,7,(string)$pp);essentialCheck(count($names)===1&&$names[0]['id']===7,'Principal legacy mentions preserve exact original project-members lineage');
essentialThrows(fn()=>$read->mentionMembers($a,7,(string)$pp),'native_resource_reference_denied','Secondary cannot interpret raw legacy project ID as owned source');
essentialThrows(fn()=>$read->mentionMembers($primary,7,(string)($pp+1000)),'native_resource_reference_denied','Principal cannot hydrate member names for project whose customer belongs to A');
echo 'Original legacy project mentions: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
