<?php
/** Real SQLite search plus real custom-field/resource services; no provider or MySQL claim. */
require __DIR__.'/native_company_custom_fields_storage.php';
$db->query("ALTER TABLE {$p}company_items ADD COLUMN description TEXT");$db->query("UPDATE {$p}company_items SET description=CASE WHEN issuer_id=41 THEN 'Item A' ELSE 'Private item B' END");
$a=$service->search(41,7,'Owned');essentialCheck(count($a)===1&&$a[0]['label']==='Item A','admin native search resolves owned item A');
essentialCheck($service->search(82,7,'Owned')===[],'same query B excludes A definitions/values');
essentialCheck($service->search(41,12,'Owned')===[],'native custom-field search remains administrative, not global admin guess');
// Corrupt owned projection cannot expose a different company's resource label or value.
$db->query("INSERT INTO {$p}company_custom_field_values(issuer_id,field_source_kind,field_id,source_kind,source_id,value,revision) VALUES(?,?,?,?,?,?,1)",[41,'company_custom_field',$fid,'company_item',2,'Owned foreign match']);
$a=$service->search(41,7,'Owned');essentialCheck(count($a)===1&&$a[0]['label']==='Item A','foreign relation rejected before value hydration');
$db->query("UPDATE {$p}company_custom_field_values SET value='Owned% literal' WHERE issuer_id=41 AND field_id=? AND source_id=1",[$fid]);
essentialCheck(count($service->search(41,7,'% literal'))===1,'LIKE wildcard searched literally');
essentialCheck($service->search(41,7,'__')===[],'underscore not SQL wildcard');
settingThrows(fn()=>$service->search(41,7,'x'),'native_custom_field_invalid','short query rejected');
settingThrows(fn()=>$service->search(41,7,'Owned',101),'native_custom_field_invalid','bounded result limit');
$definition['active']=0;$db->query("UPDATE {$p}company_custom_fields SET definition_json=? WHERE id=?",[json_encode($definition,JSON_THROW_ON_ERROR),$fid]);
essentialCheck($service->search(41,7,'Owned')===[],'inactive definition not searchable');
// Primary-only legacy definitions retain native lineage; owned override must win.
$legacy=NativeCompanyCustomFields::definition(['fieldto'=>'projects','name'=>'Legacy field','type'=>'input']);
$columns=['id INTEGER PRIMARY KEY','slug TEXT'];foreach($legacy as $key=>$value)$columns[]=$key.(is_int($value)?' INTEGER':' TEXT');
$db->query("CREATE TABLE {$p}customfields(".implode(',',$columns).')');
$db->query("CREATE TABLE {$p}customfieldsvalues(fieldid INTEGER,fieldto TEXT,relid INTEGER,value TEXT)");
$db->query("CREATE TABLE {$p}projects(id INTEGER PRIMARY KEY,clientid INTEGER,name TEXT)");
if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);
$keys=array_merge(['id','slug'],array_keys($legacy));$params=array_merge([100,'projects_legacy'],array_values($legacy));
$db->query("INSERT INTO {$p}customfields(".implode(',',$keys).') VALUES('.implode(',',array_fill(0,count($keys),'?')).')',$params);
$db->query("INSERT INTO {$p}projects VALUES(901,0,'Historical primary project'),(902,902,'Private B legacy relation')");
$db->query("INSERT INTO {$p}issuers_clients(client_id,owner_issuer_id,created_by,created_at) VALUES(902,82,7,'2026-10-25 12:00:00')");
$db->query("INSERT INTO {$p}customfieldsvalues VALUES(100,'projects',901,'historical needle'),(100,'projects',902,'historical private needle')");
$matches=$service->search($primary,7,'historical');essentialCheck(count($matches)===1&&$matches[0]['label']==='Historical primary project','primary historical matches enforce actual resource boundary');
essentialCheck($service->search(82,7,'historical')===[],'legacy definitions never adopted by named company');
$db->query("INSERT INTO {$p}company_custom_field_values(issuer_id,field_source_kind,field_id,source_kind,source_id,value,revision) VALUES(?,?,?,?,?,?,1)",[$primary,'custom_field',100,'project',901,'current replacement']);
essentialCheck($service->search($primary,7,'historical')===[],'owned override prevents stale legacy match');
$db->query("UPDATE {$p}issuers_registry SET active=0 WHERE id=41");settingThrows(fn()=>$service->search(41,7,'Owned'),'native_custom_field_denied','revoked company denied');
echo "Native custom-field search: owned A/B, administrative policy, foreign relation, literal wildcard, active definition and revocation passed.\n";
