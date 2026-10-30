<?php
require __DIR__.'/staff_fixture.php';
$db=staffFixture();$p=$db->prefix;$s=new CompanyStaff($db,$p);$masterBefore=$db->query('SELECT * FROM '.$p.'issuers_installation_identity')->result_array();$profileMaster=$db->query('SELECT * FROM '.$p.'staff WHERE staffid=17')->result_array();
$profile=['firstname'=>'Ana <synthetic>','lastname'=>'Team','email'=>'ana@example.invalid','phonenumber'=>'000','password'=>'synthetic-initial-123'];$grant=['tasks'=>['view']];
$key=staffKey();$id=$s->create(2,21,$profile,$grant,true,$key);staffCheck($id>23,'authorized secondary manager creates native identity and membership');
$detail=$s->get(2,21,$id);staffCheck($detail['grants']===$grant&&$detail['default_current']===true,'own permission/default visible');staffCheck((int)$detail['admin']===0&&(int)$detail['role']===0,'no global promotion');
staffCheck($s->create(2,21,$profile,$grant,true,$key)===$id,'durable replay returns original identity');
staffThrows(fn()=>$s->create(2,21,array_replace($profile,['password'=>'different-password-456']),$grant,true,$key),'issuer_staff_conflict','changed password cannot acknowledge creation replay');
staffThrows(fn()=>$s->get(3,17,$id),'issuer_staff_denied','master cannot read unlinked company identity through scoped detail');
staffCheck(!in_array($id,array_map('intval',array_column($s->page(3,17)['items'],'staffid')),true),'other company list excludes new A member');
staffThrows(fn()=>$s->create(3,21,array_replace($profile,['email'=>'forged@example.invalid']),[],true,staffKey()),'issuer_staff_denied','manager cannot create in B');
staffThrows(fn()=>$s->update(2,21,$id,$detail['revision'],[],['tasks'=>['edit']],false,staffKey()),'issuer_staff_grant_denied','manager cannot escalate permission beyond own authority');
$s->update(2,21,$id,$detail['revision'],['firstname'=>'Ana updated'],$grant,false,staffKey());$detail=$s->get(2,21,$id);staffCheck($detail['firstname']==='Ana updated','exclusive identity can be edited by local manager');
$beforeLink=staffSnapshot($db);$s->link(3,17,'ana@example.invalid',['projects'=>['view']],false,staffKey());$b=$s->get(3,17,$id);staffCheck($b['grants']===['projects'=>['view']],'existing identity gains independent B grants');
staffCheck($s->get(2,17,$id)['grants']===$grant&&$s->get(2,17,$id)['default_current'],'B link preserves A grants/default');
staffCheck($beforeLink['staff']===staffSnapshot($db)['staff'],'link preserves all native profile bytes/password');
$a=$s->get(2,21,$id);staffCheck(!$a['profile_editable'],'shared identity UI identifies restricted profile');
staffThrows(fn()=>$s->update(2,21,$id,$a['revision'],['firstname'=>'Cross change'],$grant,false,staffKey()),'issuer_staff_shared_identity','nonmaster cannot edit shared identity');
$s->update(2,21,$id,$a['revision'],[],[],false,staffKey());staffCheck($s->get(3,17,$id)['grants']===['projects'=>['view']],'A grant revocation preserves B');
$a=$s->get(2,21,$id);$revoke=staffKey();$s->revoke(2,21,$id,$a['revision'],$revoke);staffCheck($s->revoke(2,21,$id,$a['revision'],$revoke)===$id,'revocation receipt replay does not restore access');
staffThrows(fn()=>$s->get(2,17,$id),'issuer_staff_denied','A membership revoked immediately');staffCheck($s->get(3,17,$id)['grants']===['projects'=>['view']],'B membership survives A removal');
staffCheck($db->query('SELECT * FROM '.$p.'issuers_staff_defaults WHERE staff_id=?',[$id])->result_array()===[],'only revoked default removed without fallback');
staffThrows(fn()=>$s->create(2,21,$profile,[],true,staffKey()),'issuer_staff_email_conflict','duplicate identity email fails before writes');
foreach([1,3]as$issuer){$x=$s->create($issuer,17,array_replace($profile,['email'=>'company'.$issuer.'@example.invalid']),['tasks'=>['view']],true,staffKey());staffCheck($s->get($issuer,17,$x)['default_current'],'positive primary and B creation');staffThrows(fn()=>$s->get(2,21,$x),'issuer_staff_denied','A cannot read foreign primary/B created user');}
$master=$s->get(2,17,17);staffThrows(fn()=>$s->revoke(2,17,17,$master['revision'],staffKey()),'issuer_master_protected','existing master cannot be revoked');
staffThrows(fn()=>$s->link(2,21,'staff23@example.invalid',[],false,staffKey()),'issuer_staff_denied','local manager cannot take global administrator identity');
staffCheck($masterBefore===$db->query('SELECT * FROM '.$p.'issuers_installation_identity')->result_array()&&$profileMaster===$db->query('SELECT * FROM '.$p.'staff WHERE staffid=17')->result_array(),'existing master/primary preserved byte for byte');
$baseline=staffSnapshot($db);
foreach(['INSERT INTO '.$p.'staff ','INSERT INTO '.$p.'issuers_staff(','INSERT INTO '.$p.'issuers_resource_permissions(','INSERT INTO '.$p.'issuers_staff_defaults(','INSERT INTO '.$p.'issuers_audit(','INSERT INTO '.$p.'company_staff_commands(']as$needle){
    $db->failOn=$needle;staffThrows(fn()=>$s->create(2,21,array_replace($profile,['email'=>'fault@example.invalid']),$grant,true,staffKey()),'issuer_storage_failed','fault rollback '.$needle);$db->failOn=null;staffCheck($baseline===staffSnapshot($db),'all rows restored after failing write');
}
foreach(['INSERT INTO '.$p.'issuers_staff(','INSERT INTO '.$p.'issuers_resource_permissions(','INSERT INTO '.$p.'issuers_staff_defaults(','INSERT INTO '.$p.'issuers_audit(','INSERT INTO '.$p.'company_staff_commands(']as$needle){
    $db->ignoreOn=$needle;staffThrows(fn()=>$s->create(2,21,array_replace($profile,['email'=>'ignored@example.invalid']),$grant,true,staffKey()),'issuer_staff_write_unconfirmed','ignored SQL detected '.$needle);$db->ignoreOn=null;staffCheck($baseline===staffSnapshot($db),'ignored SQL rolls back full unit');
}
$db->trans_begin();staffThrows(fn()=>$s->create(2,21,array_replace($profile,['email'=>'outer@example.invalid']),[],true,staffKey()),'issuer_outer_transaction_not_allowed','caller transaction refused');staffCheck($db->pdo->inTransaction(),'caller transaction retained');$db->trans_rollback();
$db->observer=static function($sql,$params,$db){if(str_starts_with($sql,'SELECT actor_id,event,issuer_id,subject_id,created_at,metadata_json FROM '.$db->prefix.'issuers_audit')){$db->observer=null;$db->pdo->exec('DELETE FROM '.$db->prefix.'issuers_resource_permissions WHERE issuer_id=2 AND staff_id=21 AND feature=\'staff\' AND capability=\'create\'');}};
staffThrows(fn()=>$s->create(2,21,array_replace($profile,['email'=>'revoked@example.invalid']),[],true,staffKey()),'issuer_staff_denied','revocation after audit vetoes commit');staffCheck($baseline===staffSnapshot($db),'late authority loss and whole operation roll back');
$st=$s->get(3,17,$id);$deleteKey=staffKey();$s->update(3,17,$id,$st['revision'],[],['projects'=>['view']],false,$deleteKey);$db->query('DELETE FROM '.$p.'staff WHERE staffid=?',[$id]);
staffCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_staff_commands WHERE staff_id=?',[$id])->row()->n>0,'historical receipt does not obstruct identity deletion');
staffThrows(fn()=>$s->update(3,17,$id,$st['revision'],[],['projects'=>['view']],false,$deleteKey),'issuer_staff_stale','deleted identity receipt cannot resurrect/replay');
echo 'Company staff management: '.$GLOBALS['staffChecks'].' checks passed; '.PHP_VERSION.'; '.$db->pdo->getAttribute(PDO::ATTR_SERVER_VERSION).PHP_EOL;
