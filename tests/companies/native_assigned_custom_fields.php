<?php
require __DIR__.'/native_contact_assignments.php';
require_once APPPATH.'services/NativeCompanyCustomFields.php';
NativeCompanyCustomFieldSchema::ensure($db,$p);unset($db->data_cache['table_names']);
$grant=$access->assignments($a,7,101);$access->assign($a,7,101,[51],$grant['revision']);
$fields=new NativeCompanyCustomFields($db,$p);$start=$GLOBALS['essentialChecks'];
foreach(['customers','contacts']as$to){$def=NativeCompanyCustomFields::definition(['fieldto'=>$to,'name'=>'Owned detail','type'=>'input','required'=>1,'show_on_table'=>1]);$db->query("INSERT INTO {$p}company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(?,?,?,?,1)",[$a,$to,$to.'_assigned',json_encode($def)]);$ids[$to]=NativeCompanyResourceId::encode('company_custom_field',$db->insert_id());}
essentialThrows(fn()=>$fields->definitions($a,51,'contacts'),'native_custom_field_denied','assignment never grants global definition listing');
essentialCheck(count($fields->definitionsForResource($a,51,'customers',101))===1,'exact assigned customer definitions readable');
essentialCheck(count($fields->definitionsForContactCreation($a,51,101))===1,'contact creation definitions require explicit assigned parent');
essentialThrows(fn()=>$fields->definitionsForContactCreation($b,51,202),'native_custom_field_denied','membership alone cannot inspect B contact definitions');
essentialThrows(fn()=>$fields->definitionsForResource($a,51,'contacts',$nb),'native_custom_field_denied','foreign contact definition lookup denied');
$input=['firstname'=>'Assigned fields','lastname'=>'Contact','email'=>'assigned-fields@example.invalid','permissions'=>[]];$capture=null;
$id=$native->save($a,51,101,$input,'password_hash',null,null,function($id,$cap,$writer)use($fields,$a,$ids,&$capture){$capture=[$id,$writer];$fields->saveCreatedInOwnedTransaction($a,51,'contacts',$id,[$ids['contacts']=>'created'],$writer);},$fields->tableNames());
essentialCheck($fields->storedInput($a,51,'contacts',$id)===[$ids['contacts']=>'created'],'assigned creation and field insert commit together');
$before=$native->get($a,51,101,$id);$input['title']='Edited fields';$native->save($a,51,101,$input,'password_hash',$id,$before['revision'],fn($id)=>$fields->saveInOwnedTransaction($a,51,'contacts',$id,[$ids['contacts']=>'edited']),$fields->tableNames());
essentialCheck($fields->values($a,51,'contacts',$id)===[$ids['contacts']=>'edited'],'assigned edit uses resource authority, not global grant');
essentialCheck(count($native->page($a,51,101,['draw'=>'1','start'=>'0','length'=>'25'],true)['custom_fields'])===1,'assigned contact table uses parent-scoped catalog');
$count=(int)$db->query("SELECT COUNT(*) n FROM {$p}contacts")->row()->n;$input['email']='rollback-assigned-fields@example.invalid';
essentialThrows(fn()=>$native->save($a,51,101,$input,'password_hash',null,null,fn($id,$cap,$writer)=>$fields->saveCreatedInOwnedTransaction($a,51,'contacts',$id,[],$writer),$fields->tableNames()),'native_custom_field_required','required fields reject assigned creation');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}contacts")->row()->n===$count,'required-field failure rolls back new contact');
$def=NativeCompanyCustomFields::definition(['fieldto'=>'contacts','name'=>'Administrator private','type'=>'input','only_admin'=>1]);$db->query("INSERT INTO {$p}company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(?,?,?,?,1)",[$a,'contacts','contacts_admin_private',json_encode($def)]);
essentialCheck(count($fields->definitionsForResource($a,51,'contacts',$id))===1,'per-resource grant does not expose administrative definitions');
essentialThrows(fn()=>$capture[1]->assertCreatedInOwned($db,$p,$a,51,'contact',$capture[0]),'native_creation_attestation_invalid','assigned creation attestation expires after callback');
essentialThrows(fn()=>$fields->saveCreatedInOwnedTransaction($a,51,'contacts',$id,[],new stdClass()),'native_custom_field_creation_denied','caller cannot fabricate creation authority');
$grant=$access->assignments($a,7,101);$access->assign($a,7,101,[],$grant['revision']);
essentialThrows(fn()=>$fields->values($a,51,'contacts',$id),'native_custom_field_denied','revocation closes exact contact field reader');
essentialThrows(fn()=>$fields->definitionsForContactCreation($a,51,101),'native_custom_field_denied','revocation closes new-contact catalog');
echo 'Assigned native custom fields: '.($GLOBALS['essentialChecks']-$start).' checks passed.'.PHP_EOL;
