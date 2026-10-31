<?php
/** Real tag rows, linked resource authority, immutable receipts and late rollback. */
require __DIR__.'/native_work_tags.php';
require_once APPPATH.'services/NativeCompanyTagAdministration.php';
$db->query('ALTER TABLE '.$p.'company_work_tags DROP COLUMN unexpected');
$db->query('UPDATE '.$p.'company_work_tag_links SET source_id=1 WHERE tag_id=3');
$start=$GLOBALS['essentialChecks'];$admin=new NativeCompanyTagAdministration($db,$p);
$lookup=static function(int $issuer,string $id)use($admin):array{foreach($admin->listing($issuer,7)as$row)if($row['id']===$id)return$row;throw new RuntimeException('Missing fixture tag');};
$id=NativeCompanyResourceId::encode('company_work_tag',3);$original=$lookup($a,$id);$other=$admin->listing($b,7);$project=$db->query('SELECT * FROM '.$p.'company_projects ORDER BY id')->result_array();
$key=hash('sha256','rename-original-tag');$admin->change($a,7,$id,$original['expected'],$key,'Stored renamed tag');
$renamed=$lookup($a,$id);essentialCheck($renamed['name']==='Stored renamed tag'&&$renamed['count']===1&&$renamed['revision']===$original['revision']+1,'Settings rename keeps the linked resource and advances the stored tag revision');
$admin->change($a,7,$id,$original['expected'],$key,'Stored renamed tag');essentialCheck($lookup($a,$id)===$renamed,'Exact Settings tag retry is durable and does not advance revision');
essentialThrows(fn()=>$admin->change($a,7,$id,$original['expected'],$key,'Different intent'),'native_tag_conflict','Same request cannot rename to another value');
essentialThrows(fn()=>$admin->change($a,7,$id,$original['expected'],hash('sha256','stale-tag'),null),'native_tag_stale','Obsolete tag/link revision cannot remove a newer tag');
$foreign=NativeCompanyResourceId::encode('company_work_tag',2);essentialThrows(fn()=>$admin->change($a,7,$foreign,$original['expected'],hash('sha256','foreign-tag'),null),'native_tag_denied','Known B tag is never changed from A');
$db->query('UPDATE '.$p.'issuers_staff SET can_view=1 WHERE issuer_id=? AND staff_id=51',[$a]);
foreach([['settings','view'],['settings','delete'],['projects','view']]as[$feature,$cap])$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,51,?,?)',[$a,$feature,$cap]);
essentialThrows(fn()=>$admin->change($a,51,$id,$renamed['expected'],hash('sha256','restricted-tag'),null),'native_tag_denied','Settings delete alone cannot edit linked project tags');
$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows(fn()=>$admin->change($a,7,$id,$renamed['expected'],hash('sha256','tag-rollback'),null),'issuer_storage_failed','Late audit failure aborts tag and link deletion');$db->failOn=null;
essentialCheck($lookup($a,$id)===$renamed,'Tag/link state fully restored after rollback');
$deleteKey=hash('sha256','delete-tag');$admin->change($a,7,$id,$renamed['expected'],$deleteKey,null);$admin->change($a,7,$id,$renamed['expected'],$deleteKey,null);
essentialCheck(!$db->query('SELECT id FROM '.$p.'company_work_tags WHERE id=3')->result_array()&&!$db->query('SELECT tag_id FROM '.$p.'company_work_tag_links WHERE tag_id=3')->result_array(),'Original deletion removes only the authorized tag and its links; retry is safe');
essentialCheck($admin->listing($b,7)===$other&&$db->query('SELECT * FROM '.$p.'company_projects ORDER BY id')->result_array()===$project,'Company B and linked project records are preserved byte for byte');
$legacy=$lookup(1,'1');$admin->change(1,7,'1',$legacy['expected'],hash('sha256','legacy-rename'),'Primary historical label');$legacy=$lookup(1,'1');$admin->change(1,7,'1',$legacy['expected'],hash('sha256','legacy-delete'),null);
essentialCheck(!$db->query('SELECT id FROM '.$p.'tags WHERE id=1')->result_array(),'Same service authorizes the primary historical tag without treating a raw ID as an owned ID');
$audit=$db->query('SELECT metadata_json FROM '.$p.'issuers_audit WHERE issuer_id=? AND event=?',[$a,'native_tag_command'])->result_array();essentialCheck(count($audit)===2&&str_contains($audit[1]['metadata_json'],'Stored renamed tag'),'Deletion retains original tag/link snapshots and exact request history');
echo 'Original Settings tags: '.($GLOBALS['essentialChecks']-$start).' additional checks passed ('.$db->driver().').'.PHP_EOL;
