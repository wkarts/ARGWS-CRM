<?php
require __DIR__.'/native_project_create.php';
require_once APPPATH.'services/NativeCompanyCustomFields.php';require_once APPPATH.'services/NativeCompanyResourceTags.php';
NativeCompanyCustomFieldSchema::ensure($db,$p);NativeCompanyWorkTagsSchema::ensure($db,$p);
$nativeSeed=file_get_contents(dirname(__DIR__,2).'/install/database.sql');
foreach(['tags','taggables']as$nativeTable)if(!$db->table_exists($p.$nativeTable)){
    if(!preg_match('/CREATE TABLE `tbl'.preg_quote($nativeTable,'/').'` \(.*?\) ENGINE=InnoDB[^;]*;/s',$nativeSeed,$match))throw new RuntimeException('Native tags schema missing');
    $sql=str_replace('`tbl'.$nativeTable.'`','`'.$p.$nativeTable.'`',$match[0]);$sql=str_replace('`id` int NOT NULL,','`id` '.($db->driver()==='mysql'?'int NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT').',',$sql);
    if($db->driver()==='sqlite')$sql=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$sql);$db->query($sql);
}

$definition=NativeCompanyCustomFields::definition(['fieldto'=>'projects','name'=>'Owned required code','type'=>'input','required'=>1]);
$db->query('INSERT INTO '.$p.'company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(?,?,?,?,1)',[$a,'projects','owned_required_code',json_encode($definition,JSON_THROW_ON_ERROR)]);$field=(int)$db->insert_id();$fieldId=NativeCompanyResourceId::encode('company_custom_field',$field);
$decorations=['tags'=>['Native tagged project'],'custom_fields'=>[$fieldId=>'My actual field value']];
$created=$service->createNative($a,92,$form,$config,$tk('native-created-fields'),[92],$decorations);$canonical=NativeCompanyResourceId::encode('company_project',$created);
$fields=new NativeCompanyCustomFields($db,$p);$tags=new NativeCompanyResourceTags($db,$p);
essentialCheck(($fields->values($a,92,'projects',$canonical)[$fieldId]??null)==='My actual field value','Create-only writes actual required custom value, not an edit permission bypass');
essentialCheck(array_column($tags->read($a,92,'project',$canonical)['tags'],'name')===['Native tagged project'],'Create-only writes same owned tag catalogue/link source');
$count=count($db->query('SELECT id FROM '.$p.'company_projects')->result_array());
essentialThrows(fn()=>$service->createNative($a,92,$form,$config,$tk('native-missing-required'),[],['tags'=>[],'custom_fields'=>[]]),'native_custom_field_required','Required custom fields remain required during native create');
essentialCheck(count($db->query('SELECT id FROM '.$p.'company_projects')->result_array())===$count,'Custom field failure rolls back all native project creation');
$before=$db->query('SELECT * FROM '.$p.'company_custom_field_values ORDER BY source_id')->result_array();$db->failOn='INSERT INTO '.$p.'company_work_tag_links';
essentialThrows(fn()=>$service->createNative($a,92,$form,$config,$tk('native-tags-write-failure'),[],$decorations),'issuer_storage_failed','Tag link failure rolls back prior custom values and project');$db->failOn=null;
essentialCheck($db->query('SELECT * FROM '.$p.'company_custom_field_values ORDER BY source_id')->result_array()===$before&&count($db->query('SELECT id FROM '.$p.'company_projects')->result_array())===$count,'No partial fields or project survive tag failure');
essentialThrows(fn()=>$run(fn()=>$fields->saveCreatedProjectInOwnedTransaction($a,92,$created,[$fieldId=>'forged'],new stdClass())),'native_project_creation_proof_denied','Manufactured custom-field creation proof is refused');
essentialThrows(fn()=>$run(fn()=>$tags->createProjectTagsInOwnedTransaction($a,92,$created,[],new stdClass())),'native_project_creation_proof_denied','Manufactured tag creation proof is refused');
echo 'Native project creation fields and tags: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
$db->query('INSERT INTO '.$p.'company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(?,?,?,?,1)',[$b,'projects','foreign_required_code',json_encode($definition,JSON_THROW_ON_ERROR)]);$foreignField=NativeCompanyResourceId::encode('company_custom_field',(int)$db->insert_id());
essentialThrows(fn()=>$service->createNative($a,92,$form,$config,$tk('native-cross-issuer-field'),[],['tags'=>[],'custom_fields'=>[$foreignField=>'foreign']]),'native_custom_field_denied','A different company field cannot be used by create capability');
if($db->driver()==='sqlite'){
$db->query("CREATE TRIGGER {$p}native_creation_source_change AFTER INSERT ON {$p}company_custom_field_values WHEN NEW.source_kind='company_project' BEGIN UPDATE {$p}company_projects SET name='Changed during composed write' WHERE issuer_id=NEW.issuer_id AND id=NEW.source_id; END");
essentialThrows(fn()=>$service->createNative($a,92,$form,$config,$tk('native-create-proof-source-change'),[],$decorations),'native_project_creation_proof_denied','Creation proof rejects a different source snapshot during custom field write');$db->query('DROP TRIGGER '.$p.'native_creation_source_change');
essentialCheck(count($db->query('SELECT id FROM '.$p.'company_projects')->result_array())===$count,'Changed creation snapshot rolls back every source write');
}
echo 'Native project creation proof isolation: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
