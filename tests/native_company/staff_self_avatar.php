<?php
require __DIR__.'/staff_self.php';$db->query('UPDATE '.$p.'issuers_staff SET can_view=1 WHERE issuer_id=41 AND staff_id=12');
$root=sys_get_temp_dir().'/native-staff-self-avatar-'.bin2hex(random_bytes(8));mkdir($root,0700);define('STAFF_PROFILE_IMAGES_FOLDER',$root.'/');$im=imagecreatetruecolor(12,12);ob_start();imagepng($im);$bytes=ob_get_clean();imagedestroy($im);
$prepared=NativeCompanyStaffAvatar::prepare($db,$p,41,12,12,$bytes,true);$revision=$self->saveProfile(41,12,$self->page(41,12)['revision'],[],$prepared);$name=$self->page(41,12)['member']->profile_image;
essentialCheck(is_file($root.'/12/small_'.$name)&&$self->page(82,12)['member']->profile_image===$name,'personal actor may change shared identity avatar without staff administration');
settingThrows(fn()=>NativeCompanyStaffAvatar::prepare($db,$p,41,12,14,$bytes,true),'issuer_staff_denied','personal avatar cannot nominate another staff identity');
$bad=NativeCompanyStaffAvatar::prepare($db,$p,41,12,12,$bytes,true);$db->query('CREATE TRIGGER ignore_self_avatar_audit BEFORE INSERT ON '.$p.'issuers_audit BEGIN SELECT RAISE(IGNORE); END');settingThrows(fn()=>$self->saveProfile(41,12,$revision,[],$bad),'issuer_staff_write_unconfirmed','personal avatar late audit rolls back marker');$db->query('DROP TRIGGER ignore_self_avatar_audit');essentialCheck(count(glob($root.'/12/*'))===2&&$self->page(41,12)['member']->profile_image===$name,'personal rollback cleans attempt files only');
$target=$root.'/target';mkdir($target,0700);symlink($target,$root.'/14');$admin=NativeCompanyStaffAvatar::prepare($db,$p,41,7,14,$bytes);
settingThrows(fn()=>IssuerTransaction::run($db,$p,(new CompanyStaff($db,$p))->tables(),fn()=>$admin->apply(14,false,new CompanyStaff($db,$p))),'issuer_storage_failed','symlink staff directory cannot redirect avatar writes');essentialCheck(glob($target.'/*')===[],'symlink destination remains untouched');
unlink($root.'/14');rmdir($target);foreach(glob($root.'/12/*')as$file)unlink($file);rmdir($root.'/12');rmdir($root);
echo "Personal staff avatar: six real SQLite/GD/filesystem checks.\n";
