<?php
require __DIR__.'/staff_form_command.php';
$db->query('UPDATE '.$p.'staff SET profile_image=? WHERE staffid=?',['synthetic-existing.png',$id]);$before=$reader->page(41,7,$id);$values=$custom->values(41,7,'staff',$id);
$revision=$command->removeAvatar(41,7,$id,$before['native_staff_revision'],hash('sha256','remove-native-avatar-command'));
$after=$reader->page(41,7,$id);essentialCheck($after['member']->profile_image===null&&$revision===$after['native_staff_revision'],'native avatar removal commits exact identity revision');
essentialCheck($custom->values(41,7,'staff',$id)===$values&&$before['staff_departments']===$after['staff_departments']&&$before['native_staff_permissions']===$after['native_staff_permissions']&&$before['member']->hourly_rate===$after['member']->hourly_rate,'avatar removal preserves custom values departments grants and membership wage');
settingThrows(fn()=>$command->removeAvatar(41,7,$id,$before['native_staff_revision'],hash('sha256','stale-avatar-remove')),'issuer_staff_stale','avatar command does not overwrite a newer profile');
settingThrows(fn()=>$command->removeAvatar(82,7,$id,$revision,hash('sha256','foreign-avatar-remove')),'issuer_staff_denied','avatar command cannot remove another company identity');
echo "Native avatar remove command: four real SQLite checks.\n";
