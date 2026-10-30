<?php
require __DIR__.'/staff_read.php';require_once APPPATH.'services/NativeCompanyStaffSelf.php';require_once APPPATH.'third_party/phpass.php';
$db->query('UPDATE '.$p.'issuers_registry SET active=1 WHERE id=82');$db->query('UPDATE '.$p.'issuers_staff SET can_view=1 WHERE staff_id=12');$db->query('UPDATE '.$p.'staff SET password=? WHERE staffid=12',[(new PasswordHash(8,true))->HashPassword('synthetic-old-password')]);
$db->query('DELETE FROM '.$p.'issuers_resource_permissions WHERE staff_id=12');
$self=new NativeCompanyStaffSelf($db,$p);$page=$self->page(41,12);$before=$db->query('SELECT * FROM '.$p.'issuers_resource_permissions')->result_array();
$revision=$self->saveProfile(41,12,$page['revision'],['firstname'=>'Self shared','email_signature'=>'<p>Signature</p>']);
essentialCheck($self->page(82,12)['member']->firstname==='Self shared','actor may edit own shared identity without tenant administrator grant');
essentialCheck(!isset($page['member']->password)&&$before===$db->query('SELECT * FROM '.$p.'issuers_resource_permissions')->result_array(),'personal profile exposes no password and changes no company grants');
settingThrows(fn()=>$self->saveProfile(41,12,$revision,['staffid'=>'14']),'issuer_staff_profile_field_denied','personal writer cannot target another identity');
settingThrows(fn()=>$self->saveProfile(41,12,$revision,['admin'=>'1']),'issuer_staff_profile_field_denied','personal writer cannot promote administrator');
settingThrows(fn()=>$self->changePassword(41,12,$revision,'wrong','synthetic-new-password','synthetic-new-password'),'native_staff_old_password_invalid','current password required');
$new=$self->changePassword(41,12,$revision,'synthetic-old-password','synthetic-new-password','synthetic-new-password');essentialCheck($new!==$revision&&(new PasswordHash(8,true))->CheckPassword('synthetic-new-password',$db->query('SELECT password FROM '.$p.'staff WHERE staffid=12')->row()->password),'personal password change verifies old hash and persists native new hash');
$db->query('CREATE TRIGGER deny_self_audit BEFORE INSERT ON '.$p.'issuers_audit BEGIN SELECT RAISE(IGNORE); END');settingThrows(fn()=>$self->saveProfile(41,12,$new,['firstname'=>'Rollback']),'issuer_staff_write_unconfirmed','audit failure rolls back personal identity');$db->query('DROP TRIGGER deny_self_audit');essentialCheck($self->page(41,12)['member']->firstname==='Self shared','personal update rollback preserves prior identity');
settingThrows(fn()=>$self->saveProfile(41,12,$new,['email'=>'changed@example.test']),'issuer_staff_denied','original personal email control requires staff edit capability');
$db->query('UPDATE '.$p.'issuers_staff SET can_view=0 WHERE issuer_id=41 AND staff_id=12');settingThrows(fn()=>$self->saveProfile(41,12,$new,['firstname'=>'Revoked']),'issuer_staff_denied','revoked context cannot mutate shared self identity');
echo "Staff personal profile: nine real SQLite authority/reauthentication/rollback checks.\n";
