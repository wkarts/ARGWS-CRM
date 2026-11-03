<?php
/** Individual customer import keeps each company's own name policy. */
require __DIR__.'/client_imports.php';
$row=$db->query("SELECT settings_json FROM {$p}issuers_registry WHERE id=41")->row_array();$document=json_decode($row['settings_json'],true,512,JSON_THROW_ON_ERROR);$document['company_options']['company_is_required']='0';
$db->query("UPDATE {$p}issuers_registry SET settings_json=? WHERE id=41",[json_encode($document,JSON_THROW_ON_ERROR)]);
$bytes=json_encode([['company'=>'','vat'=>'']],JSON_THROW_ON_ERROR);
$batch=$imports->preview(41,12,'json',$bytes,hash('sha256','individual-import-a'));
$review=$imports->get(41,12,$batch);$result=$imports->commit(41,12,$batch,$review['revision'],hash('sha256','individual-commit-a'));
essentialCheck($clients->get(41,12,$result['client_ids'][0])['company']==='','individual import persists exactly empty company in A');
essentialThrows(fn()=>$imports->preview(82,12,'json',$bytes,hash('sha256','individual-import-b')),'company_client_invalid_input','B cannot inherit A optional-name policy');
$primary=(new IssuerResourceAccess($db,$p))->primaryId();$option=$db->query("SELECT value FROM {$p}options WHERE name=?",['company_is_required'])->row_array();
if($option)$db->query("UPDATE {$p}options SET value=? WHERE name=?",['0','company_is_required']);else$db->query("INSERT INTO {$p}options(name,value) VALUES(?,?)",['company_is_required','0']);
$batch=$imports->preview($primary,7,'json',$bytes,hash('sha256','individual-import-primary'));$review=$imports->get($primary,7,$batch);$result=$imports->commit($primary,7,$batch,$review['revision'],hash('sha256','individual-commit-primary'));
essentialCheck($clients->get($primary,7,$result['client_ids'][0])['company']==='','principal import owns its options table in the transaction');
echo "Individual customer imports preserve company-specific policy.\n";
