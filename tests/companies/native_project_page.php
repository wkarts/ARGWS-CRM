<?php
require __DIR__.'/native_project_choices.php';
require_once APPPATH.'services/NativeCompanyProjectPage.php';
foreach(['start_date'=>"DATE NOT NULL DEFAULT '2026-01-01'",'deadline'=>"DATE NULL",'billing_type'=>'INT NOT NULL DEFAULT 1']as$column=>$type)$db->query("ALTER TABLE {$p}projects ADD $column $type");
foreach(['project_members'=>'project_id INT NOT NULL,staff_id INT NOT NULL','tags'=>'id INT PRIMARY KEY,name VARCHAR(100)','taggables'=>'tag_id INT,rel_type VARCHAR(20),rel_id INT,tag_order INT','customfields'=>'id INT PRIMARY KEY,fieldto VARCHAR(20),field_order INT','customfieldsvalues'=>'fieldid INT,fieldto VARCHAR(20),relid INT,value TEXT']as$table=>$ddl)if(!$db->table_exists($p.$table))$db->query("CREATE TABLE {$p}{$table}($ddl)");
NativeCompanyWorkAssignmentsSchema::ensure($db,$p);NativeCompanyWorkTagsSchema::ensure($db,$p);NativeCompanyCustomFieldSchema::ensure($db,$p);NativeCompanyProjectMetadataSchema::ensure($db,$p);unset($db->data_cache['table_names']);
$request=['draw'=>'1','start'=>'0','length'=>'25','columns'=>array_map(static fn($i)=>['data'=>(string)$i,'name'=>'','searchable'=>true,'orderable'=>true,'search'=>['value'=>'','regex'=>false]],range(0,7)),'order'=>[['column'=>'0','dir'=>'asc']],'search'=>['value'=>'','regex'=>false]];
$page=new NativeCompanyProjectPage($db,$p);$result=$page->read($a,7,$request,'','2026-10-25');
essentialCheck($result['recordsTotal']>=2&&$result['recordsFiltered']===$result['recordsTotal']&&$result['draw']===1,'Real native table query supplies actual counts and rows');
foreach($result['rows']as$r)essentialCheck((int)$r['issuer_id']===$a&&$r['source_kind']==='company_project'&&$r['id']===NativeCompanyResourceId::encode('company_project',$r['source_id']),'No cross-issuer rows or raw-ID alias');
$filtered=$page->read($a,7,array_replace($request,['search'=>['value'=>'100%','regex'=>false]]),'','2026-10-25');essentialCheck(count($filtered['rows'])===1&&$filtered['rows'][0]['name']==='Native 100% exact','Native full-text search binds wildcard literally');
$principal=$page->read($primary,7,$request,'','2026-10-25');$pairs=array_map(static fn($r)=>$r['source_kind'].':'.$r['source_id'],$principal['rows']);essentialCheck(in_array('project:'.$pp,$pairs,true)&&in_array('company_project:'.$pp,$pairs,true),'Principal renders both colliding histories without adoption');
$before=essentialLegacy($f);$page->read($primary,7,$request,'','2026-10-25');essentialCheck(essentialLegacy($f)===$before,'Native project table reads are mutation-free');
essentialThrows(fn()=>$page->read($a,52,$request),'native_project_access_denied','Current project ACL required');
essentialThrows(fn()=>$page->read($a,7,array_replace($request,['order'=>[['column'=>8,'dir'=>'asc']]])),'native_project_table_request_invalid','Unknown order column is rejected');
echo 'Native projects real paged table: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
