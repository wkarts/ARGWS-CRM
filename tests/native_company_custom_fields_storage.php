<?php
/** Synthetic real PDO; SQLite evidence is not MySQL migration evidence. */
require __DIR__.'/settings/native_settings.php';
require_once APPPATH.'services/NativeCompanyCustomFields.php';
NativeCompanyCustomFieldSchema::ensure($db,$p);NativeCompanyCustomFieldSchema::ensure($db,$p);
$db->query("UPDATE {$p}issuers_registry SET active=1 WHERE id IN(41,82)");
foreach([41,82]as$issuer)foreach(['view','edit']as$cap)$db->query("INSERT INTO {$p}issuers_resource_permissions VALUES(?,12,'items',?)",[$issuer,$cap]);
$db->query("CREATE TABLE {$p}company_items(id INT NOT NULL PRIMARY KEY,issuer_id INT NOT NULL)");if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);
$db->query("INSERT INTO {$p}company_items VALUES(1,41),(2,82)");
$definition=NativeCompanyCustomFields::definition(['fieldto'=>'items','name'=>'Synthetic field','type'=>'input']);
$db->query("INSERT INTO {$p}company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(?,?,?,?,?)",[41,'items','items_synthetic',json_encode($definition,JSON_THROW_ON_ERROR),1]);$fid=(int)$db->insert_id();
$field=NativeCompanyResourceId::encode('company_custom_field',$fid);$itemA=NativeCompanyResourceId::encode('company_item',1);$itemB=NativeCompanyResourceId::encode('company_item',2);
$service=new NativeCompanyCustomFields($db,$p);$tables=array_merge($service->tableNames(),['company_items']);
essentialCheck(count($service->definitions(41,12,'items_pr'))===1&&$service->definitions(82,12,'items_pr')===[],'definitions are issuer-bound');
IssuerTransaction::run($db,$p,$tables,fn()=>$service->saveInOwnedTransaction(41,12,'items_pr',$itemA,[$field=>'Owned A']));
essentialCheck($service->values(41,12,'items_pr',$itemA)===[$field=>'Owned A'],'field values persisted on decoded owned source');
IssuerTransaction::run($db,$p,$tables,fn()=>$service->saveInOwnedTransaction(41,12,'items_pr',$itemA,[$field=>'Owned A']));
essentialCheck((int)$db->query("SELECT revision FROM {$p}company_custom_field_values")->row()->revision===1,'same content replay does not increment revision');
settingThrows(fn()=>$service->values(82,12,'items_pr',$itemA),'native_custom_field_denied','guessed resource denied before values');
settingThrows(fn()=>IssuerTransaction::run($db,$p,$tables,fn()=>$service->saveInOwnedTransaction(82,12,'items_pr',$itemB,[$field=>'Forbidden'])),'native_custom_field_denied','foreign field cannot be written to owned resource');
settingThrows(fn()=>$service->saveInOwnedTransaction(41,12,'items_pr',$itemA,[$field=>'Unowned']),'issuer_owned_transaction_required','writes require caller owned transaction');
// Existing value remains intact after a deliberately failed independent write path.
settingThrows(fn()=>IssuerTransaction::run($db,$p,$tables,function()use($db,$p,$service,$itemA,$field){$service->saveInOwnedTransaction(41,12,'items_pr',$itemA,[$field=>'Temporary']);throw new DomainException('synthetic_abort');}),'synthetic_abort','caller failure rolls custom fields back');
essentialCheck($service->values(41,12,'items_pr',$itemA)===[$field=>'Owned A'],'rollback preserves canonical value');
$created=$service->saveDefinition(41,12,['fieldto'=>'items','name'=>'Choices','type'=>'multiselect','options'=>'A, B'],null,null,str_repeat('a',32));
$replay=$service->saveDefinition(41,12,['fieldto'=>'items','name'=>'Choices','type'=>'multiselect','options'=>'A, B'],null,null,str_repeat('a',32));
essentialCheck($created===$replay,'definition create replay returns same identity');
settingThrows(fn()=>$service->saveDefinition(41,12,['fieldto'=>'items','name'=>'Changed','type'=>'input'],null,null,str_repeat('a',32)),'native_custom_field_request_conflict','same request cannot change definition');
IssuerTransaction::run($db,$p,$tables,fn()=>$service->saveInOwnedTransaction(41,12,'items_pr',$itemA,[$created['id']=>['A','B']]));
essentialCheck($service->storedInput(41,12,'items_pr',$itemA)[$created['id']]===['A','B'],'persisted multi-choice is explicitly rehydrated for copy form');
settingThrows(fn()=>$service->saveDefinition(41,12,['fieldto'=>'items','name'=>'Choices','type'=>'multiselect','options'=>'A, C'],$created['id'],1),'native_custom_field_options_in_use','in-use options cannot be silently reinterpreted');
settingThrows(fn()=>$service->saveDefinition(82,12,['fieldto'=>'items','name'=>'Guess','type'=>'input'],$created['id'],1),'native_custom_field_stale','foreign definition cannot be updated');
$updated=$service->saveDefinition(41,12,['fieldto'=>'items','name'=>'Renamed','type'=>'multiselect','options'=>'A, B'],$created['id'],1);
essentialCheck($updated['revision']===2,'definition compare-and-set increments revision');
settingThrows(fn()=>$service->saveDefinition(41,12,['fieldto'=>'items','name'=>'Stale','type'=>'multiselect','options'=>'A, B'],$created['id'],1),'native_custom_field_stale','stale configuration rejected');
echo 'Native custom fields storage passed ('.$db->driver().").\n";
