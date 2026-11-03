<?php
/** Native individual customers respect the persisted setting of their own company. */
require __DIR__.'/native_clients_controller.php';
function setIndividualPolicy($db,$p,$issuer,$value):void{
    $row=$db->query("SELECT settings_json FROM {$p}issuers_registry WHERE id=?",[$issuer])->row_array();
    $document=json_decode($row['settings_json'],true,512,JSON_THROW_ON_ERROR);$document['company_options']['company_is_required']=$value;
    $db->query("UPDATE {$p}issuers_registry SET settings_json=? WHERE id=?",[json_encode($document,JSON_THROW_ON_ERROR),$issuer]);
}
$individual=array_replace($payload,['company'=>'','request_key'=>hash('sha256','native-individual-a')]);
essentialThrows(fn()=>$adapterA->save($individual),'company_client_invalid_input','company name is required by default');
setIndividualPolicy($db,$p,41,'0');setIndividualPolicy($db,$p,82,'1');
essentialCheck($adapterA->references()['native_customer_company_required']==='0'&&$adapterB->references()['native_customer_company_required']==='1','form receives each issuer own customer name policy');
$id=$adapterA->save($individual);$details=$adapterA->details($id);
essentialCheck($details['client']['company']===''&&$details['client']['owner_issuer_id']===41,'optional company name remains empty and bound to its owner');
essentialCheck($adapterA->save($individual)===$id,'individual create receipt is idempotent');
essentialThrows(fn()=>$adapterB->save(array_replace($individual,['request_key'=>hash('sha256','native-individual-b')])),'company_client_invalid_input','B does not inherit A optional company-name policy');
essentialCheck($adapterB->details($id)===null,'other issuer cannot access individual client by direct ID');
$db->query("INSERT INTO {$p}contacts(id,userid,active,is_primary,firstname,lastname,email) VALUES(99101,?,1,1,'Nome','Individual','individual@example.invalid')",[$id]);
$controller->input->method='POST';$controller->input->post=['draw'=>'3','length'=>'100','search'=>['value'=>'Individual']];CompanyRequestScope::$issuer=41;$controller->table();
$page=json_decode($controller->output->body,true,512,JSON_THROW_ON_ERROR);
$row=array_values(array_filter($page['data'],fn($item)=>(int)$item[1]===$id))[0]??null;
essentialCheck($row!==null&&str_contains($row[2],'Nome Individual'),'native directory displays the real owner contact name for individual client');
setIndividualPolicy($db,$p,41,'1');essentialCheck($adapterA->save($individual)===$id,'existing idempotent receipt remains valid after policy change');
essentialThrows(fn()=>$adapterA->save(array_replace($individual,['request_key'=>hash('sha256','another-individual-a')])),'company_client_invalid_input','new blank company is rejected when policy is reenabled');
setIndividualPolicy($db,$p,41,'0');$before=$db->query("SELECT settings_json FROM {$p}issuers_registry WHERE id=41")->row_array()['settings_json'];
essentialThrows(fn()=>$clients->create(41,12,['company'=>''],hash('sha256','individual-policy-race'),fn()=>setIndividualPolicy($db,$p,41,'1')),'company_client_invalid_input','policy is checked again before commit');
essentialCheck($db->query("SELECT settings_json FROM {$p}issuers_registry WHERE id=41")->row_array()['settings_json']===$before,'late policy change rolls back with the customer transaction');
$primary=(new IssuerResourceAccess($db,$p))->primaryId();
$option=$db->query("SELECT value FROM {$p}options WHERE name=?",['company_is_required'])->row_array();
if($option)$db->query("UPDATE {$p}options SET value=? WHERE name=?",['0','company_is_required']);else$db->query("INSERT INTO {$p}options(name,value) VALUES(?,?)",['company_is_required','0']);
$primaryId=$clients->create($primary,7,['company'=>''],hash('sha256','primary-individual'));
essentialCheck($clients->get($primary,7,$primaryId)['company']==='','principal company uses its own native setting and preserves empty name');
echo "Individual customer policy, identity and transaction fencing passed.\n";
